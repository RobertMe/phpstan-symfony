<?php

namespace PHPStan\Symfony;

interface LocatorDefinition
{
	/**
	 * @return array<string, list<string>>
	 */
	public function getIdMap(): array;

	public function getIdFor(string $id): ?string;

}
