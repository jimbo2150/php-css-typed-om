<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleDeclaration;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;

/**
 * Mutable Typed OM map backed by a CSSStyleDeclaration.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/StylePropertyMap
 */
final class StylePropertyMap extends StylePropertyMapReadOnly
{
	/**
	 * Sets a property to one or more CSS values atomically.
	 *
	 * @throws InvalidArgumentException When the property name or any value is invalid
	 */
	public function set(string $property, CSSStyleValue|string|array ...$values): void
	{
		$serialized = $this->serializeValues($property, $values);
		$this->style->setProperty($property, $serialized);
	}

	/**
	 * Appends one or more comma-separated values to a property atomically.
	 *
	 * @throws InvalidArgumentException When the property name or any value is invalid
	 */
	public function append(string $property, CSSStyleValue|string ...$values): void
	{
		if ($values === []) {
			throw new InvalidArgumentException('A style property requires at least one value.');
		}
		$serialized = implode(', ', array_map(
			fn (CSSStyleValue|string $item): string => $this->validateSingleValue($property, $item),
			$values,
		));
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

	/** @param list<CSSStyleValue|string|array<int, CSSStyleValue|string>> $values */
	private function serializeValues(string $property, array $values): string
	{
		$flat = [];
		foreach ($values as $value) {
			if (is_array($value)) {
				array_push($flat, ...$value);
			} else {
				$flat[] = $value;
			}
		}

		if ($flat === []) {
			throw new InvalidArgumentException('A style property requires at least one value.');
		}

		$serialized = [];
		foreach ($flat as $item) {
			$serialized[] = $this->validateSingleValue($property, $item);
		}

		return implode(' ', $serialized);
	}

	private function validateSingleValue(string $property, mixed $item): string
	{
		if (!$item instanceof CSSStyleValue && !is_string($item)) {
			throw new InvalidArgumentException('StylePropertyMap values must be CSSStyleValue objects or strings.');
		}
		$cssText = trim((string) $item);
		if ($cssText === '') {
			throw new InvalidArgumentException('StylePropertyMap values cannot be empty.');
		}
		CSSStyleDeclaration::validatePropertyValue($cssText);
		if (!$item instanceof CSSStyleValue) {
			CSSStyleValue::parse($property, $cssText);
		}

		return $cssText;
	}
}
