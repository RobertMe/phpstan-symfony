<?php

namespace PHPStan\Symfony;

class LocatorMap
{
	/** @var array<string, LocatorDefinition> */
	private $serviceMapping;

	/**
	 * @param array<string, LocatorDefinition> $serviceMapping
	 */
	public function __construct(array $serviceMapping)
	{
		$this->serviceMapping = $serviceMapping;
	}

	/**
	 * @param array<string, list<string>> $classesWithLocators
	 * @param array<string, LocatorDefinition> $locators
	 */
	public static function create(array $classesWithLocators, array $locators, array $locatorFactories): self
	{
		$maps = [];
		foreach ($classesWithLocators as $class => $locatorIds) {
			$map = [];
			foreach ($locatorIds as $locatorId) {
				if (isset($locatorFactories[$locatorId])) {
					$locatorId = $locatorFactories[$locatorId];
				}

				if (!isset($locators[$locatorId])) {
					continue;
				}

				foreach ($locators[$locatorId]->getIdMap() as $idInLocator => $idInContainer) {
					$map[$idInLocator] = array_merge_recursive($map[$idInLocator] ?? [], $idInContainer);
				}
			}
			$maps[$class] = new Locator($map);
		}

		return new self($maps);
	}

	public function getServiceId(string $serviceId, string $className): ?string
	{
		if (!isset($this->serviceMapping[$className])) {
			return $serviceId;
		}

		return $this->serviceMapping[$className]->getIdFor($serviceId);
	}
}
