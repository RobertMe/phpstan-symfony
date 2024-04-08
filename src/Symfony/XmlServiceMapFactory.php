<?php declare(strict_types = 1);

namespace PHPStan\Symfony;

use SimpleXMLElement;
use function file_get_contents;
use function simplexml_load_string;
use function sprintf;
use function strpos;
use function substr;

final class XmlServiceMapFactory implements ServiceMapFactory
{

	/** @var string|null */
	private $containerXml;

	public function __construct(Configuration $configuration)
	{
		$this->containerXml = $configuration->getContainerXmlPath();
	}

	public function create(): ServiceMap
	{
		if ($this->containerXml === null) {
			return new FakeServiceMap();
		}

		$fileContents = file_get_contents($this->containerXml);
		if ($fileContents === false) {
			throw new XmlContainerNotExistsException(sprintf('Container %s does not exist', $this->containerXml));
		}

		$xml = @simplexml_load_string($fileContents);
		if ($xml === false) {
			throw new XmlContainerNotExistsException(sprintf('Container %s cannot be parsed', $this->containerXml));
		}

		/** @var Service[] $services */
		$services = [];
		/** @var Service[] $aliases */
		$aliases = [];
		/** @var array<string, list<string>> $classesWithLocators */
		$classesWithLocators = [];
		$locators = [];
		$locatorFactories = [];
		foreach ($xml->services->service as $def) {
			/** @var SimpleXMLElement $attrs */
			$attrs = $def->attributes();
			if (!isset($attrs->id)) {
				continue;
			}

			$service = new Service(
				strpos((string) $attrs->id, '.') === 0 ? substr((string) $attrs->id, 1) : (string) $attrs->id,
				isset($attrs->class) ? (string) $attrs->class : null,
				isset($attrs->public) && (string) $attrs->public === 'true',
				isset($attrs->synthetic) && (string) $attrs->synthetic === 'true',
				isset($attrs->alias) ? (string) $attrs->alias : null
			);

			if ($service->getAlias() !== null) {
				$aliases[] = $service;
			} else {
				$services[$service->getId()] = $service;
			}

			if (isset($attrs->class)) {
				$usingLocators = $this->findLocators($def);
				if ($usingLocators !== []) {
					$classesWithLocators[(string) $attrs->class] = $usingLocators;
				}
			}

			if ((string) $attrs->class === 'Symfony\Component\DependencyInjection\ServiceLocator') {
				$serviceMap = [];

				if (isset($def->factory)) {
					$locatorFactories[(string) $attrs->id] = (string) $def->factory->attributes()->service;
				} elseif ($def->argument->argument !== null) {
					foreach ($def->argument->argument as $argument) {
						$argAttrs = $argument->attributes();
						$serviceMap[(string) $argAttrs->key] = [(string) $argAttrs->id];
					}
					$locators[(string) $attrs->id] = new Locator($serviceMap);
				}
			}
		}
		foreach ($aliases as $service) {
			$alias = $service->getAlias();
			if ($alias !== null && !isset($services[$alias])) {
				continue;
			}
			$id = $service->getId();
			$services[$id] = new Service(
				$id,
				$services[$alias]->getClass(),
				$service->isPublic(),
				$service->isSynthetic(),
				$alias
			);
		}

		return new DefaultServiceMap($services, LocatorMap::create($classesWithLocators, $locators, $locatorFactories));
	}

	private function findLocators(SimpleXMLElement $serviceNode): array
	{
		$locators = [];
		foreach ($serviceNode->argument as $argument) {
			$attrs = $argument->attributes();
			$id = (string) $attrs->id;
			if ((string) $attrs->type === 'service' && str_starts_with($id, '.service_locator.')) {
				$locators[] = $id;
			}
		}

		foreach ($serviceNode->call as $call) {
			if ($call->argument !== null) {
				foreach ($call->argument as $argument) {
					$attrs = $argument->attributes();
					$id = (string) $attrs->id;
					if ((string) $attrs->type === 'service' && str_starts_with($id, '.service_locator.')) {
						$locators[] = $id;
					}
				}
			}
		}

		return $locators;
	}

}
