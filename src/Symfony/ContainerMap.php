<?php

namespace PHPStan\Symfony;

interface ContainerMap
{
	public function getDefault(): ServiceMap;

	/**
	 * @param class-string $className
	 */
	public function getForClass(string $className): ?ServiceMap;
}
