<?php

namespace IcingaFeature\Snmp\Scenario;

use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionException;

class ScenarioLoader
{
    protected array $scenarios;

    public function __construct(
        protected readonly LoggerInterface $logger,
    ) {
        $this->scenarios = $this->loadScenarios();
    }

    public function resultHandler(string $scenarioName): ScenarioResultHandler
    {
        return $this->resultHandler[$scenarioName] ??= new ScenarioResultHandler(
            $scenarioName,
            new ReflectionClass($this->scenarios[$scenarioName]),
            $this->logger
        );
    }

    public function listScenarios(): array
    {
        return $this->scenarios;
    }

    protected function loadScenarios(): array
    {
        $implementations = [];
        foreach (ScenarioRegistry::CLASSES as $class) {
            try {
                $ref = new ReflectionClass($class);
            } catch (ReflectionException $e) {
                $this->logger->error("Failed to load scenario $class: " . $e->getMessage());
                continue;
            } catch (\Throwable $e) {
                // Parse error in class
                $this->logger->error("Failed to load scenario $class: " . $e->getMessage());
            }
            foreach ($ref->getAttributes(PollingTask::class) as $attribute) {
                $arg = $attribute->getArguments();
                $name = $arg['name'] ?? $arg[0];
                $implementations[$name] = $class;
            }
        }

        return $implementations;
    }
}
