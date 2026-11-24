<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleDeclaration;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
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
		$normalized = CSSStyleDeclaration::normalizePropertyName($property);
		$options = CSSParserOptions::default();
		[$serialized, $partCount] = $this->serializeValues($normalized, $values, $options);
		if ($options->maxBytes !== null && strlen($serialized) > $options->maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS property value exceeds maximum size of %d bytes.', $options->maxBytes));
		}
		if ($partCount > 1) {
			CSSStyleDeclaration::validatePropertyValue($serialized);
		}
		$this->style->setValidatedProperty($normalized, $serialized, '');
	}

	/**
	 * Appends one or more comma-separated values to a property atomically, preserving priority.
	 *
	 * @throws InvalidArgumentException When the property name or any value is invalid
	 */
	public function append(string $property, CSSStyleValue|string ...$values): void
	{
		if ($values === []) {
			throw new InvalidArgumentException('A style property requires at least one value.');
		}
		$normalized = CSSStyleDeclaration::normalizePropertyName($property);
		$options = CSSParserOptions::default();
		$parts = [];
		$existing = $this->style->getDeclarationsRaw()[$normalized] ?? null;
		$current = $existing['value'] ?? '';
		$priority = $existing['priority'] ?? '';
		$runningBytes = $current === '' ? 0 : strlen($current);
		foreach ($values as $item) {
			$part = $this->validateSingleValue($normalized, $item, $options, $runningBytes, $current !== '' || $parts !== [] ? 2 : 0);
			$parts[] = $part;
		}
		$serialized = implode(', ', $parts);
		$next = $current === '' ? $serialized : $current . ', ' . $serialized;
		if ($current !== '' || count($parts) > 1) {
			CSSStyleDeclaration::validatePropertyValue($next);
		}
		$this->style->setValidatedProperty($normalized, $next, $priority);
	}

	public function delete(string $property): void
	{
		$this->style->removeProperty($property);
	}

	public function clear(): void
	{
		$this->style->clear();
	}

	/**
	 * @param list<CSSStyleValue|string|array<int, CSSStyleValue|string>> $values
	 * @return array{string, int}
	 */
	private function serializeValues(string $normalizedProperty, array $values, CSSParserOptions $options): array
	{
		$flat = [];
		foreach ($values as $value) {
			if (is_array($value)) {
				array_push($flat, ...array_values($value));
			} else {
				$flat[] = $value;
			}
		}

		if ($flat === []) {
			throw new InvalidArgumentException('A style property requires at least one value.');
		}

		$allowEmpty = str_starts_with($normalizedProperty, '--') && count($flat) === 1;
		$joined = '';
		$runningBytes = 0;
		foreach ($flat as $index => $item) {
			$sep = $index === 0 ? '' : (CSSSyntaxScanner::endsWithBareHexEscape($joined) ? '  ' : ' ');
			$part = $this->validateSingleValue($normalizedProperty, $item, $options, $runningBytes, strlen($sep), $allowEmpty);
			$joined .= $sep . $part;
		}

		return [$joined, count($flat)];
	}

	private function validateSingleValue(
		string $normalizedProperty,
		mixed $item,
		CSSParserOptions $options,
		int &$runningBytes,
		int $separatorBytes,
		bool $allowEmpty = false,
	): string {
		if (!$item instanceof CSSStyleValue && !is_string($item)) {
			throw new InvalidArgumentException('StylePropertyMap values must be CSSStyleValue objects or strings.');
		}

		$cssText = CSSSyntaxScanner::trimCssWhitespace((string) $item);
		if ($cssText === '') {
			if ($allowEmpty) {
				return '';
			}
			throw new InvalidArgumentException('StylePropertyMap values cannot be empty.');
		}
		$runningBytes += $separatorBytes + strlen($cssText);
		if ($options->maxBytes !== null && $runningBytes > $options->maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS property value exceeds maximum size of %d bytes.', $options->maxBytes));
		}

		CSSStyleDeclaration::validatePropertyValue($cssText);
		if (!$item instanceof CSSStyleValue) {
			CSSStyleValue::parseValidated($normalizedProperty, $cssText, $options);
		}

		return $cssText;
	}
}
