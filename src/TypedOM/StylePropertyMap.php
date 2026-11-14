<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;

final class StylePropertyMap extends StylePropertyMapReadOnly
{
	public function set(string $property, CSSStyleValue|string|array $value): void
	{
		$serialized = $this->serializeValue($property, $value);
		$this->style->setProperty($property, $serialized);
	}

	public function append(string $property, CSSStyleValue|string $value): void
	{
		$serialized = $this->serializeValue($property, $value);
		$current = $this->style->getPropertyValue($property);
		$this->style->setProperty($property, $current === '' ? $serialized : $current . ', ' . $serialized);
	}

	public function delete(string $property): void
	{
		$this->style->removeProperty($property);
	}

	public function clear(): void
	{
		$this->style->clear();
	}

	private function serializeValue(string $property, CSSStyleValue|string|array $value): string
	{
		$values = is_array($value) ? $value : [$value];
		if ($values === []) {
			throw new InvalidArgumentException('A style property requires at least one value.');
		}

		$serialized = [];
		foreach ($values as $item) {
			if (!$item instanceof CSSStyleValue && !is_string($item)) {
				throw new InvalidArgumentException('StylePropertyMap values must be CSSStyleValue objects or strings.');
			}
			$cssText = trim((string) $item);
			if ($cssText === '') {
				throw new InvalidArgumentException('StylePropertyMap values cannot be empty.');
			}
			CSSStyleValue::parse($property, $cssText);
			$serialized[] = $cssText;
		}

		return implode(' ', $serialized);
	}
}
