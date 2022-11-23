<?php

namespace IcingaFeature\Snmp\Scenario;

use IcingaDataNode\Monitoring\Ci;
use IcingaDataNode\Monitoring\Measurement;
use IcingaDataNode\Monitoring\Metric;
use IcingaFeature\Snmp\DataStructure\DataNodeIdentifier;
use IcingaFeature\Snmp\DataStructure\Measurement as MeasurementAttribute;
use IcingaFeature\Snmp\DataStructure\DbColumn;
use IcingaFeature\Snmp\DataStructure\DbTable;
use IcingaFeature\Snmp\DataStructure\DeviceIdentifier;
use IcingaFeature\Snmp\DataStructure\Metric as MetricAttribute;
use IcingaFeature\Snmp\DataStructure\Oid;
use IcingaFeature\Snmp\DataStructure\SnmpTable;
use IcingaFeature\Snmp\DataStructure\SnmpTableIndex;
use IcingaFeature\Snmp\DataStructure\SnmpTableIndexValue;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionType;
use RuntimeException;

class ScenarioResultHandler
{
    protected DbTable|bool|null $dbTable = null;
    protected ?bool $needsWalk = null;

    /** @var array<string, string> OID/property-mappings */
    protected ?array $oidList = null;
    protected ?array $dbColumnMap = null;
    protected ?array $metricMap = null;
    protected ?MeasurementAttribute $measurement = null;
    /** @var ?LookupMap[]  */
    protected ?array $redisMaps = null;

    public function __construct(
        protected readonly string $scenarioName,
        protected readonly ReflectionClass $class,
        protected readonly LoggerInterface $logger,
    ) {
    }

    public function needsWalk(): bool
    {
        return $this->needsWalk ??= !empty($this->class->getAttributes(SnmpTable::class));
    }

    public function getScenarioOids(): array
    {
        return $this->oidList ??= $this->loadScenarioOids();
    }

    public function fixResult($result)
    {
        $indexes = $this->getSnmpTableIndexes();
        if ($indexes === null) {
            $this->logger->notice('Got NO SNMP Table indexes');
            return $result; // Really? I'd prefer to see an exception, as this shouldn't happen
        }

        $keys = $this->getScenarioOids();

        // TODO: flat walk result, no keys
        return SnmpTableHelper::flattenResult($this->logger, $indexes, $result, $keys);
    }

    public function prepareMeasurements(UuidInterface $deviceUuid, array $instances): array
    {
        $measurements = [];
        foreach ($instances as $instance) {
            if ($measurement = $this->prepareMeasurement($deviceUuid, $instance)) {
                $measurements[] = $measurement;
            }
        }

        return $measurements;
    }

    /**
     * @return ?array{0: string, 1: string, 2: object}
     */
    public function prepareDbUpdate(object $instance): ?array
    {
        $dbTable = $this->getDbTable();
        if ($dbTable === null) {
            $this->logger->notice("NO TABLE NAME FOR $this->scenarioName");
            return null;
        }

        return [
            $dbTable->tableName,
            $this->getDbUpdateKey($instance),
            array_keys($dbTable->keyProperties),
            $this->getInstanceDbProperties($instance)
        ];
    }

    public function instanceToMeasurement(UuidInterface $deviceUuid, $instance): Measurement
    {
        return new Measurement(
            new Ci($deviceUuid->toString()),
            null,
            $this->getInstanceMetrics($instance)
        );
    }

    public function prepareMeasurement(UuidInterface $deviceUuid, $instance): ?Measurement
    {
        $attribute = $this->getMeasurementAttribute();
        if ($attribute === null) {
            // $this->logger->notice("NO MEASUREMENT ATTRIBUTE FOR $this->scenarioName");
            return null;
        }

        try {
            if ($attribute->instanceProperty === null) {
                $instanceId = null;
            } else {
                $instanceId = $instance->{$attribute->instanceProperty};
            }
            return new Measurement(
                new Ci($deviceUuid->toString(), $attribute->name, $instanceId),
                null,
                $this->getInstanceMetrics($instance)
            );
        } catch (\Throwable $e) {
            $this->logger->error('NO MEASUREMENT: ' . $e->getMessage());
            return null;
        }
    }

    public function getInstanceDbProperties($instance): array
    {
        $result = [];
        $map = $this->getDbColumnMap();
        foreach (get_object_vars($instance) as $key => $value) {
            if (isset($map[$key])) {
                if ($value instanceof Oid) {
                    $value = $value->oid;
                }
                $result[$map[$key]] = $value;
            }
        }

        return $result;
    }

    public function getTableEntries(UuidInterface $deviceUuid, array $instances): array
    {
        $tables = [];
        if ($dbTable = $this->getDbTable()) {
            foreach ($instances as $instance) {
                $tables[$this->getDbUpdateKey($instance)] = $this->getInstanceDbProperties($instance);
            }
            $this->sendTableEntries($dbTable, $deviceUuid, array_keys($dbTable->keyProperties), $tables);
        }
        return $tables;
    }

    /**
     * @param $instance
     * @return Metric[]
     */
    protected function getInstanceMetrics($instance): array
    {
        $result = [];
        $map = $this->getMetricMap();
        foreach (get_object_vars($instance) as $key => $value) {
            if (isset($map[$key])) {
                $result[$map[$key][0]] = new Metric($key, $value, $map[$key][1], $map[$key][2]);
            }
        }

        return $result;
    }

    protected function getDbColumnMap(): array
    {
        return $this->dbColumnMap ??= $this->prepareDbColumnMap();
    }

    protected function prepareDbColumnMap(): array
    {
        $map = [];
        foreach ($this->class->getProperties() as $property) {
            foreach ($property->getAttributes(DbColumn::class) as $column) {
                $args = $column->getArguments();
                // Instance Property => DB column
                $map[$property->getName()] = $args['columnName'] ?? $args[0];
            }
        }

        return $map;
    }

    protected function getMetricMap(): array
    {
        return $this->metricMap ??= $this->prepareMetricMap();
    }

    protected function prepareMetricMap(): array
    {
        $map = [];
        foreach ($this->class->getProperties() as $property) {
            foreach ($property->getAttributes(MetricAttribute::class) as $column) {
                $args = $column->getArguments();
                // Instance Property => DB column
                $map[$property->getName()] = [
                    $args['dataSource'] ?? $args[0],
                    $args['dataType'] ?? $args[1],
                    $args['unit'] ?? $args[2] ?? null,
                ];
            }
        }

        return $map;
    }

    /**
     * HINT: unfinished, unused
     * @return LookupMap[]
     */
    public function getRedisMaps(): array
    {
        return $this->redisMaps ??= $this->prepareRedisMaps();
    }

    protected function prepareRedisMaps(): array
    {
        $maps = [];
        foreach ($this->class->getAttributes(LookupMap::class) as $attribute) {
            /** @var LookupMap $instance */
            $instance = $attribute->newInstance();
            $maps[] = [$instance->keyProperty, $instance->valueProperty];
        }

        return $maps;
    }

    protected function loadScenarioOids(): array
    {
        $result = [];
        foreach ($this->class->getProperties() as $property) {
            $hasOid = false;
            foreach ($property->getAttributes(Oid::class) as $oid) {
                $args = $oid->getArguments();
                $result[$args['oid'] ?? $args[0]] = $property->getName();
                $hasOid = true;
            }
            if (! $hasOid) {
                $typeClass = $property->getType()->getName();
                if (class_exists($typeClass)) {
                    $typeRef = new ReflectionClass($typeClass);
                    foreach ($typeRef->getAttributes(Oid::class) as $oid) {
                        $args = $oid->getArguments();
                        $result[$args['oid'] ?? $args[0]] = $property->getName();
                    };
                }
            }
        }

        return $result;
    }

    /**
     * @return ?SnmpTableIndex[]
     */
    protected function getSnmpTableIndexes(): ?array
    {
        $arguments = null;
        foreach ($this->class->getAttributes(SnmpTable::class) as $attribute) {
            $arguments = $attribute->getArguments();
        }
        if ($arguments === null) {
            return null;
        }
        /** @var SnmpTableIndex[] $indexes */
        $indexes = $arguments['indexes'] ?? $arguments[0];

        return $indexes;
    }

    public function getDbTable(): ?DbTable
    {
        if ($this->dbTable === null) {
            $dbTable = false;
            foreach ($this->class->getAttributes(DbTable::class) as $table) {
                $dbTable = $table->newInstance();
            }
            $this->dbTable = $dbTable;
        }

        return $this->dbTable;
    }

    public function getMeasurementAttribute(): ?MeasurementAttribute
    {
        if ($this->measurement === null) {
            $measurement = false;
            foreach ($this->class->getAttributes(MeasurementAttribute::class) as $attribute) {
                $measurement = $attribute->newInstance();
            }
            if ($measurement) {
                $this->measurement = $measurement;
            }
        }

        return $this->measurement;
    }

    public function getDbUpdateKey(object $instance): string
    {
        $dbTable = $this->getDbTable();
        if (count($dbTable->keyProperties) === 1) {
            $keyValue = $instance->{$dbTable->keyProperties[array_key_first($dbTable->keyProperties)]};
            $key = $keyValue ? (string)$keyValue : '- no key -';
        } else {
            $key = [];
            foreach ($dbTable->keyProperties as $column) {
                $value = $instance->$column;
                $key[] = (string) $value;
            }
            $key = implode('/', $key);
        }

        return $key;
    }

    public function getResultObjectInstance(UuidInterface $nodeUuid, UuidInterface $deviceUuid, $result): object
    {
        $ref = $this->class;
        $props = [];
        try {
            foreach ($ref->getProperties() as $property) {
                $propertyName = $property->getName();
                if (isset($result[$propertyName])) {
                    if (is_string($result[$propertyName])) { // e.g. indexValue, already normalized
                        $props[$propertyName] = $result[$propertyName];
                    } else {
                        $props[$propertyName] = $this->dataTypeToProperty($result[$propertyName], $property->getType());
                    }
                } else {
                    foreach ($property->getAttributes(DeviceIdentifier::class)  as $attribute) {
                        $props[$propertyName] = $deviceUuid;
                    }
                    foreach ($property->getAttributes(DataNodeIdentifier::class)  as $attribute) {
                        $props[$propertyName] = $nodeUuid;
                    }
                    foreach ($property->getAttributes(SnmpTableIndexValue::class)  as $attribute) {
                        $indexName = $attribute->getArguments()[0];
                        if (isset($result[$indexName])) {
                            $props[$propertyName] = $result[$indexName]; // Should be normalized
                        } else {
                            throw new RuntimeException("Cannot access index $indexName in result");
                        }
                    }

                }
            }
        } catch (\Throwable $e) {
            $this->logger->notice($e->getMessage());
        }

        return $ref->newInstance(...$props);
    }

    /**
     * @param object{type: string, value: mixed} $data serialized \gipfl\Protocol\Snmp\DataType\DataType
     */
    protected function dataTypeToProperty(object $data, ReflectionType $type): mixed
    {
        if (!$type instanceof ReflectionNamedType) {
            throw new RuntimeException("Type $type is not supported");
        }
        $data = (object) $data->jsonSerialize();
        if ($type->allowsNull() && $data->type === null) {
            return null;
        }

        if ($type->isBuiltin()) {
            $value = match ($type->getName()) {
                'int'    => (int) $data->value,
                'float'  => (float) $data->value,
                'string' => (string) $data->value,
                'array'  => (array) $data->value,
                default  => throw new RuntimeException("Builtin type $type is not supported"),
            };
            // Soll am Ziel passieren, dann gibt es keine Probleme mit JSON:
            // if ($type->getName() === 'string' && str_starts_with($data->value, '0x')) {
            //     $value = hex2bin(substr($data->value, 2));
            // }
        } else {

            if (enum_exists($type->getName())) {
                $value = $type->getName()::tryFrom($data->value);
            } else {
                $value = match ($type->getName()) {
                    UuidInterface::class => Uuid::fromBytes($data->value),
                    Oid::class           => new Oid($data->value),
                    default              => throw new RuntimeException("Type $type is not supported"),
                };
            }
        }

        return $value;
    }
}
