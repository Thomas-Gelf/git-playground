<?php

namespace IcingaDataNode\Inventory;

use gipfl\Json\JsonSerialization;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class InventoryAction implements JsonSerialization
{
    protected ?array $allDbValues = null;

    public function __construct(
        public readonly UuidInterface $sourceNode,
        public readonly string $tableName,
        // e.g. "1694703295979-0"
        public readonly string $streamPosition,
        public readonly InventoryActionType $action,
        public readonly string $key,
        public readonly ?string $checksum,
        public readonly array $keyProperties,
        public readonly ?array $values = null,
    ) {}

    public static function fromSerialization($any): InventoryAction
    {
        $any->sourceNode = Uuid::fromString($any->sourceNode);
        $any->action = InventoryActionType::from($any->action);
        if ($any->values !== null) {
            $any->values = (array) $any->values;
        }

        return new InventoryAction(...(array) $any);
    }

    public function jsonSerialize(): array
    {
        return [
            'sourceNode'     => $this->sourceNode->toString(),
            'tableName'      => $this->tableName,
            'streamPosition' => $this->streamPosition,
            'action'         => $this->action->value,
            'key'            => $this->key,
            'checksum'       => $this->checksum,
            'keyProperties'  => $this->keyProperties,
            'values'         => $this->values,
        ];
    }

    public function getDbValuesForUpdate(): array
    {
        $values = $this->getAllDbValues();
        foreach ($this->keyProperties as $property) {
            unset($values[$property]);
        }

        return $values;
    }

    public function getDbKeyProperties(): array
    {
        $values = $this->getAllDbValues(); // TODO: this is not efficient
        $properties = [];
        foreach ($this->keyProperties as $property) {
            $properties[$property] = $values[$property] ?? null;
        }

        return $properties;
    }

    public function getAllDbValues(): array
    {
        return $this->allDbValues ??= $this->prepareAllDbValues();
    }

    public function prepareAllDbValues(): array
    {
        $values = $this->values;
        if ($values === null) {
            return [];
        }

        foreach ($values as $property => &$value) {
            if (str_contains($property, 'uuid') && strlen($value) === 36) {
                $value = Uuid::fromString($value)->getBytes();
            }
            if (is_object($value) && isset($value->oid)) {
                $value = $value->oid;
            }
            if (is_bool($value)) {
                $value = $value ? 'y' : 'n';
            }
            if (is_string($value)) {
                if (str_starts_with($value, '0x')) {
                    $value = hex2bin(substr($value, 2));
                }
            } elseif ($value !== null && ! is_int($value)) {
                if (is_object($value)) {
                    throw new InvalidArgumentException('Not a string, an object: ' . json_encode($value));
                } elseif (is_array($value)) {
                    throw new InvalidArgumentException('Not a string, an array: ' . json_encode($value));
                } else {
                    throw new InvalidArgumentException('Not a string: ' . get_debug_type($value) . json_encode($value));
                }
            }
        }

        return $values;
    }
}

/**
 * Redis stream example:
 *
 * 1683) 1) "1694703295979-0"
 * 2) 1) "action"
 * 2) "update"
 * 3) "key"
 * 4) "a2f2606d-4182-443b-99c3-77abe4f006da"
 * 5) "checksum"
 * 6) "4c2f8b8449a583c04ed2ffcac2c2d0e30f712150"
 * 7) "value"
 * 8) "{\"device_uuid\":\"a2f2606d-4182-443b-99c3-77abe4f006da\",\"system_name\":\"0x736e6d70\",\"system_description\":\"0x4c696e757820736e6d7020362e322e302d33322d67656e65726963202333327e32322e30342e312d5562756e747520534d5020505245454d50545f44594e414d494320467269204175672031382031303a34303a3133205554432032207838365f3634\",\"system_location\":\"0x53697474696e67206f6e2074686520446f636b206f662074686520426179\",\"system_contact\":\"0x4d65203c6d65406578616d706c652e6f72673e\",\"system_services\":\"72\",\"system_oid\":{\"oid\":\"1.3.6.1.4.1.8072.3.2.10\"},\"system_engine_id\":\"0x80001f88801ece804702bb096400000000\",\"system_engine_boot_count\":53,\"system_engine_boot_time\":87246}"
 */
