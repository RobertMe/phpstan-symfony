<?php

namespace PHPStan\Symfony;

class Locator implements LocatorDefinition
{
	/** @var array<string, list<string>> */
	private $idMap;

	public function __construct(array $idMap)
	{
		$this->idMap = $idMap;
	}

	public function getIdMap(): array
	{
		return $this->idMap;
	}

	public function getIdFor(string $locatorId): ?string
	{
		$containerIds = $this->idMap[$locatorId] ?? [];
		return count($containerIds) === 1 ? $containerIds[0] : null;
	}

}
