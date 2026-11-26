<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleDeclaration;
use Jimbo2150\PhpCssTypedOm\CSSOM\Shorthand\CSSShorthandRegistry;
use Jimbo2150\PhpCssTypedOm\Grammar\CSSPropertyGrammarRegistry;
use Jimbo2150\PhpCssTypedOm\Grammar\CSSValueValidator;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSColorValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSImageValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSPositionValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSTransformComponent;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSTransformValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnparsedValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSVariableReferenceValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathValue;

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
	 * @param CSSStyleValue|string|array<int|string, CSSStyleValue|string> ...$values
	 * @throws InvalidArgumentException When the property name or any value is invalid
	 */
	public function set(string $property, CSSStyleValue|string|array ...$values): void
	{
		$options = $this->resolveSheetParserOptions();
		$normalized = $this->normalizeBoundedPropertyName($property, $options);
		[$serialized, $partCount] = $this->serializeValues($normalized, $values, $options);
		if ($options->maxBytes !== null && (strlen($normalized) + strlen($serialized)) > $options->maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS property declaration exceeds maximum size of %d bytes.', $options->maxBytes));
		}
		if (!str_starts_with($normalized, '--')
			&& str_contains($serialized, ',')
			&& CSSSyntaxScanner::countTopLevelDelimiters($serialized, ',') >= CSSStyleValue::MAX_LIST_ITEMS
		) {
			throw new CSSParserLimitException(sprintf('CSS property value list exceeds maximum limit of %d items.', CSSStyleValue::MAX_LIST_ITEMS));
		}
		if ($partCount > 1) {
			CSSStyleDeclaration::validatePropertyValue($serialized);
		}
		if ($this->style->shouldValidatePropertyValuesInternal() || str_starts_with($normalized, '--')) {
			if (!CSSValueValidator::validatePropertyValue($normalized, $serialized, $options, true)) {
				throw new InvalidArgumentException(sprintf('Invalid value "%s" for property "%s".', $serialized, $normalized));
			}
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
		$options = $this->resolveSheetParserOptions();
		$normalized = $this->normalizeBoundedPropertyName($property, $options);
		if (CSSPropertyGrammarRegistry::isKnown($normalized) && !CSSValueValidator::isListValuedProperty($normalized)) {
			throw new InvalidArgumentException(sprintf('CSS property "%s" does not support list values.', $normalized));
		}
		$parts = [];
		if ($this->style->shouldExpandShorthandsInternal() && CSSShorthandRegistry::isShorthand($normalized)) {
			$current = $this->style->getPropertyValue($normalized);
			$priority = $this->style->getPropertyPriority($normalized);
		} else {
			$existing = $this->style->getDeclarationsRaw()[$normalized] ?? null;
			$current = $existing['value'] ?? '';
			$priority = $existing['priority'] ?? '';
		}
		$runningBytes = $current === '' ? 0 : strlen($current);
		$itemCount = $current === '' ? 0 : (CSSSyntaxScanner::countTopLevelDelimiters($current, ',') + 1);
		foreach ($values as $item) {
			$part = $this->validateSingleValue($normalized, $item, $options, $runningBytes, $current !== '' || $parts !== [] ? 2 : 0, false, false);
			$itemCount += CSSSyntaxScanner::countTopLevelDelimiters($part, ',') + 1;
			if ($itemCount > CSSStyleValue::MAX_LIST_ITEMS) {
				throw new CSSParserLimitException(sprintf('CSS property value list exceeds maximum limit of %d items.', CSSStyleValue::MAX_LIST_ITEMS));
			}
			$parts[] = $part;
		}
		$serialized = implode(', ', $parts);
		$next = $current === '' ? $serialized : $current . ', ' . $serialized;
		if ($options->maxBytes !== null && (strlen($normalized) + strlen($next)) > $options->maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS property declaration exceeds maximum size of %d bytes.', $options->maxBytes));
		}
		if ($this->style->shouldValidatePropertyValuesInternal() || str_starts_with($normalized, '--')) {
			if (!CSSValueValidator::validatePropertyValue($normalized, $next, $options, true)) {
				throw new InvalidArgumentException(sprintf('Invalid value "%s" for property "%s".', $next, $normalized));
			}
		}
		$this->style->setValidatedProperty($normalized, $next, $priority);
	}

	public function delete(string $property): void
	{
		$options = $this->resolveSheetParserOptions();
		$normalized = $this->normalizeBoundedPropertyName($property, $options);
		$this->style->removeProperty($normalized);
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
		if (count($values) === 1 && !is_array($values[0])) {
			$allowEmpty = str_starts_with($normalizedProperty, '--');
			$runningBytes = 0;

			return [$this->validateSingleValue($normalizedProperty, $values[0], $options, $runningBytes, 0, $allowEmpty, false), 1];
		}

		$flat = [];
		foreach ($values as $value) {
			if (is_array($value)) {
				if ((count($flat) + count($value)) > CSSStyleValue::MAX_LIST_ITEMS) {
					throw new CSSParserLimitException(sprintf('CSS property value list exceeds maximum limit of %d items.', CSSStyleValue::MAX_LIST_ITEMS));
				}
				foreach ($value as $item) {
					$flat[] = $item;
				}
			} else {
				if (count($flat) >= CSSStyleValue::MAX_LIST_ITEMS) {
					throw new CSSParserLimitException(sprintf('CSS property value list exceeds maximum limit of %d items.', CSSStyleValue::MAX_LIST_ITEMS));
				}
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
			$sep = $index === 0 ? '' : (str_contains($joined, '\\') && CSSSyntaxScanner::endsWithBareHexEscape($joined) ? '  ' : ' ');
			$part = $this->validateSingleValue($normalizedProperty, $item, $options, $runningBytes, strlen($sep), $allowEmpty, false);
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
		bool $validatePropertyGrammar = true,
	): string {
		if (!$item instanceof CSSStyleValue && !is_string($item)) {
			throw new InvalidArgumentException('StylePropertyMap values must be CSSStyleValue objects or strings.');
		}
		if ($options->maxBytes !== null && ($runningBytes + $separatorBytes) > $options->maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS property value exceeds maximum size of %d bytes.', $options->maxBytes));
		}
		$remaining = $options->maxBytes !== null ? $options->maxBytes - $runningBytes - $separatorBytes : null;
		if ($remaining !== null) {
			if (is_string($item) && strlen($item) > $remaining) {
				throw new CSSParserLimitException(sprintf('CSS property value exceeds maximum size of %d bytes.', $options->maxBytes));
			}
			if ($item instanceof CSSUnparsedValue || $item instanceof CSSVariableReferenceValue) {
				$item->measureRenderedBytesInternal($remaining);
			}
		}

		$serialized = match (true) {
			$item instanceof CSSMathValue => $item->toBoundedCssTextInternal($remaining, $options->maxMathDepth),
			$item instanceof CSSTransformValue,
			$item instanceof CSSTransformComponent,
			$item instanceof CSSPositionValue,
			$item instanceof CSSColorValue => $item->toBoundedCssTextInternal($remaining),
			default => (string) $item,
		};
		$cssText = CSSSyntaxScanner::trimCssWhitespace($serialized);
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

		if ($item instanceof CSSUnitValue) {
			if ($validatePropertyGrammar && $normalizedProperty !== '' && !CSSValueValidator::validatePropertyValue($normalizedProperty, $cssText, $options, true)) {
				throw new InvalidArgumentException(sprintf('Invalid value "%s" for property "%s".', $cssText, $normalizedProperty));
			}

			return $cssText;
		}

		CSSStyleDeclaration::validatePropertyValue($cssText);
		if (!str_starts_with($normalizedProperty, '--') && str_contains($cssText, '/*') && CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($cssText)) === '') {
			throw new InvalidArgumentException('StylePropertyMap values cannot be empty.');
		}
		if (!$item instanceof CSSStyleValue) {
			$canSkipParseValidated = strcspn($cssText, '([{') === strlen($cssText)
				&& ($options->maxBytes === null || $options->maxBytes >= 0)
				&& ($options->maxDepth === null || $options->maxDepth >= 0)
				&& ($options->maxMathDepth === null || $options->maxMathDepth >= 0);
			if ($canSkipParseValidated) {
				if ($validatePropertyGrammar && $normalizedProperty !== '' && !CSSValueValidator::validatePropertyValue($normalizedProperty, $cssText, $options, true)) {
					throw new InvalidArgumentException(sprintf('Invalid value "%s" for property "%s".', $cssText, $normalizedProperty));
				}
			} else {
				CSSStyleValue::parseValidated($validatePropertyGrammar ? $normalizedProperty : '', $cssText, $options, 0, null, $validatePropertyGrammar);
			}
		} elseif ($item instanceof CSSUnparsedValue || $item instanceof CSSVariableReferenceValue) {
			if ($options->maxDepth !== null || $options->maxMathDepth !== null) {
				CSSStyleValue::parseValidated($validatePropertyGrammar ? $normalizedProperty : '', $cssText, $options, 0, null, $validatePropertyGrammar);
			}
		} elseif (!$item instanceof CSSMathValue && $options->maxMathDepth !== null) {
			self::assertValueMathDepthWithin($item, $options->maxMathDepth);
		}

		return $cssText;
	}

	private static function assertValueMathDepthWithin(CSSStyleValue $item, int $maxMathDepth): void
	{
		$stack = [$item];
		while ($stack !== []) {
			$current = array_pop($stack);
			if ($current instanceof CSSMathValue) {
				$current->assertMathDepthWithinInternal($maxMathDepth);
			} elseif ($current instanceof CSSImageValue) {
				CSSImageValue::assertEmbeddedMathDepthWithinInternal($current->getImage(), $maxMathDepth);
			} elseif ($current instanceof CSSTransformValue) {
				foreach ($current as $component) {
					$stack[] = $component;
				}
			} elseif ($current instanceof CSSTransformComponent) {
				foreach ($current->getComponentValuesInternal() as $part) {
					if ($part instanceof CSSStyleValue) {
						$stack[] = $part;
					}
				}
			} elseif ($current instanceof CSSPositionValue) {
				$stack[] = $current->getX();
				$stack[] = $current->getY();
			} elseif ($current instanceof CSSColorValue) {
				foreach ($current->getComponentValuesInternal() as $channel) {
					$stack[] = $channel;
				}
			}
		}
	}
}
