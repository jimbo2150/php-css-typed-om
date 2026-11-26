<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSS;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleDeclaration;
use Jimbo2150\PhpCssTypedOm\Grammar\CSSValueValidator;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Jimbo2150\PhpCssTypedOm\TypedOM\CSSContext;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSColorValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSDeferredColorException;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Stringable;

/**
 * Base class for CSS Typed OM values.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSStyleValue
 */
abstract class CSSStyleValue implements Stringable
{
	public const int MAX_LIST_ITEMS = 4_096;

	/** @var array<string, true> */
	private const array GLOBAL_KEYWORDS = [
		'inherit' => true,
		'initial' => true,
		'unset' => true,
		'revert' => true,
		'revert-layer' => true,
	];

	/** @var array<string, array<string, true>> */
	private const array PROPERTY_KEYWORDS = [
		'display' => ['block' => true, 'contents' => true, 'flex' => true, 'flow' => true, 'flow-root' => true, 'grid' => true, 'inline' => true, 'inline-block' => true, 'inline-flex' => true, 'inline-grid' => true, 'inline-table' => true, 'list-item' => true, 'none' => true, 'ruby' => true, 'table' => true],
		'position' => ['absolute' => true, 'fixed' => true, 'relative' => true, 'static' => true, 'sticky' => true],
		'width' => ['auto' => true, 'fit-content' => true, 'max-content' => true, 'min-content' => true, 'stretch' => true],
		'height' => ['auto' => true, 'fit-content' => true, 'max-content' => true, 'min-content' => true, 'stretch' => true],
		'min-width' => ['auto' => true, 'fit-content' => true, 'max-content' => true, 'min-content' => true, 'stretch' => true],
		'max-width' => ['none' => true, 'fit-content' => true, 'max-content' => true, 'min-content' => true, 'stretch' => true],
		'min-height' => ['auto' => true, 'fit-content' => true, 'max-content' => true, 'min-content' => true, 'stretch' => true],
		'max-height' => ['none' => true, 'fit-content' => true, 'max-content' => true, 'min-content' => true, 'stretch' => true],
		'inline-size' => ['auto' => true, 'fit-content' => true, 'max-content' => true, 'min-content' => true, 'stretch' => true],
		'block-size' => ['auto' => true, 'fit-content' => true, 'max-content' => true, 'min-content' => true, 'stretch' => true],
		'overflow' => ['auto' => true, 'clip' => true, 'hidden' => true, 'scroll' => true, 'visible' => true],
		'overflow-x' => ['auto' => true, 'clip' => true, 'hidden' => true, 'scroll' => true, 'visible' => true],
		'overflow-y' => ['auto' => true, 'clip' => true, 'hidden' => true, 'scroll' => true, 'visible' => true],
		'visibility' => ['collapse' => true, 'hidden' => true, 'visible' => true],
		'float' => ['inline-end' => true, 'inline-start' => true, 'left' => true, 'none' => true, 'right' => true],
		'clear' => ['both' => true, 'inline-end' => true, 'inline-start' => true, 'left' => true, 'none' => true, 'right' => true],
		'box-sizing' => ['border-box' => true, 'content-box' => true],
		'transform' => ['none' => true],
		'background-image' => ['none' => true],
		'mask-image' => ['none' => true],
		'border-style' => ['dashed' => true, 'dotted' => true, 'double' => true, 'groove' => true, 'hidden' => true, 'inset' => true, 'none' => true, 'outset' => true, 'ridge' => true, 'solid' => true],
		'text-align' => ['center' => true, 'end' => true, 'justify' => true, 'left' => true, 'match-parent' => true, 'right' => true, 'start' => true],
		'justify-content' => ['center' => true, 'end' => true, 'flex-end' => true, 'flex-start' => true, 'left' => true, 'normal' => true, 'right' => true, 'space-around' => true, 'space-between' => true, 'space-evenly' => true, 'start' => true, 'stretch' => true],
		'align-items' => ['baseline' => true, 'center' => true, 'end' => true, 'flex-end' => true, 'flex-start' => true, 'normal' => true, 'self-end' => true, 'self-start' => true, 'start' => true, 'stretch' => true],
		'caret-color' => ['auto' => true],
		'accent-color' => ['auto' => true],
		'outline-color' => ['auto' => true, 'invert' => true],
		'fill' => ['context-fill' => true, 'context-stroke' => true, 'none' => true],
		'stroke' => ['context-fill' => true, 'context-stroke' => true, 'none' => true],
	];

	/** @var array<string, true> */
	private const array COLOR_PROPERTIES = [
		'color' => true,
		'background-color' => true,
		'border-top-color' => true,
		'border-right-color' => true,
		'border-bottom-color' => true,
		'border-left-color' => true,
		'border-block-start-color' => true,
		'border-block-end-color' => true,
		'border-inline-start-color' => true,
		'border-inline-end-color' => true,
		'outline-color' => true,
		'text-decoration-color' => true,
		'text-emphasis-color' => true,
		'caret-color' => true,
		'accent-color' => true,
		'column-rule-color' => true,
		'fill' => true,
		'stroke' => true,
		'stop-color' => true,
		'flood-color' => true,
		'lighting-color' => true,
		'border-color' => true,
		'border-block-color' => true,
		'border-inline-color' => true,
	];

	public function __construct()
	{
	}

	/**
	 * Parses one property value into a Typed OM representation.
	 * The single-argument form parses a generic standalone value.
	 *
	 * @throws InvalidArgumentException When the value is empty, unbalanced, or contains disallowed control/delimiter bytes
	 */
	public static function parse(
		string $propertyOrCssText,
		?string $cssText = null,
		?CSSParserOptions $options = null,
		?CSSContext $context = null,
	): CSSStyleValue {
		$options ??= CSSParserOptions::default();
		if ($cssText !== null) {
			if ($options->maxBytes !== null && strlen($propertyOrCssText) > $options->maxBytes) {
				throw new CSSParserLimitException(sprintf('CSS property name exceeds maximum size of %d bytes.', $options->maxBytes));
			}
			$property = CSSStyleDeclaration::normalizePropertyName($propertyOrCssText);
			$raw = $cssText;
		} else {
			$property = '';
			$raw = $propertyOrCssText;
		}
		if ($options->maxBytes !== null && strlen($raw) > $options->maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS value exceeds maximum size of %d bytes.', $options->maxBytes));
		}

		$trimmed = CSSSyntaxScanner::trimCssWhitespace($raw);
		if ($trimmed === '') {
			throw new InvalidArgumentException('CSS value cannot be empty.');
		}
		CSSSyntaxScanner::assertValidFragment($trimmed, 'CSS value', [';', '{', '}']);
		if (!str_starts_with($property, '--') && str_contains($trimmed, '/*') && CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($trimmed)) === '') {
			throw new InvalidArgumentException('CSS value cannot be empty.');
		}

		return self::parseValidated($property, $trimmed, $options, 0, $context);
	}

	/**
	 * Parses a comma-separated list of values for a property.
	 *
	 * @return list<CSSStyleValue>
	 * @throws InvalidArgumentException When the list is empty or malformed
	 */
	public static function parseAll(
		string $property,
		string $cssText,
		?CSSParserOptions $options = null,
		?CSSContext $context = null,
	): array {
		$options ??= CSSParserOptions::default();
		if ($options->maxBytes !== null && strlen($property) > $options->maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS property name exceeds maximum size of %d bytes.', $options->maxBytes));
		}
		if ($options->maxBytes !== null && strlen($cssText) > $options->maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS value list exceeds maximum size of %d bytes.', $options->maxBytes));
		}
		$normalizedProp = CSSStyleDeclaration::normalizePropertyName($property);
		CSSSyntaxScanner::assertValidFragment($cssText, 'CSS value list', [';', '{', '}']);

		return self::parseAllValidated($normalizedProp, $cssText, $options, $context);
	}

	/**
	 * @internal Parses an already-fragment-validated value list without re-scanning for balanced delimiters/control bytes.
	 * @return list<CSSStyleValue>
	 * @throws InvalidArgumentException When the value list is empty or invalid
	 */
	public static function parseAllValidated(
		string $normalizedProperty,
		string $validatedCssText,
		CSSParserOptions $options,
		?CSSContext $context = null,
		bool $validateGrammar = true,
	): array {
		if (str_starts_with($normalizedProperty, '--')) {
			$trimmed = CSSSyntaxScanner::trimCssWhitespace($validatedCssText);
			if ($trimmed === '') {
				throw new InvalidArgumentException('CSS value list cannot be empty.');
			}
			if ($validateGrammar && !CSSValueValidator::validatePropertyValue($normalizedProperty, $trimmed, $options, true)) {
				throw new InvalidArgumentException(sprintf('Invalid value "%s" for property "%s".', $trimmed, $normalizedProperty));
			}

			return [self::parseValidated($normalizedProperty, $trimmed, $options, 0, $context, false)];
		}

		$rawSegments = [];
		$hasEmptySegment = false;
		foreach (CSSSyntaxScanner::splitTopLevel($validatedCssText, ',', 0, null, self::MAX_LIST_ITEMS) as $value) {
			$value = CSSSyntaxScanner::trimCssWhitespace($value);
			$isEmpty = $value === '' || (str_contains($value, '/*') && CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($value)) === '');
			if ($isEmpty) {
				$hasEmptySegment = true;
				continue;
			}
			if (count($rawSegments) >= self::MAX_LIST_ITEMS) {
				throw new CSSParserLimitException(sprintf('CSS value list exceeds maximum limit of %d items.', self::MAX_LIST_ITEMS));
			}
			$rawSegments[] = $value;
		}

		if ($rawSegments === [] || ($validateGrammar && $hasEmptySegment)) {
			throw new InvalidArgumentException('CSS value list cannot be empty.');
		}

		if ($validateGrammar && $normalizedProperty !== '') {
			$trimmedAll = CSSSyntaxScanner::trimCssWhitespace($validatedCssText);
			if (!CSSValueValidator::validatePropertyValue($normalizedProperty, $trimmedAll, $options, true)) {
				throw new InvalidArgumentException(sprintf('Invalid value "%s" for property "%s".', $trimmedAll, $normalizedProperty));
			}
		}

		$values = [];
		foreach ($rawSegments as $segment) {
			$values[] = self::parseValidated($normalizedProperty, $segment, $options, 0, $context, false);
		}

		return $values;
	}

	/**
	 * @internal Parses a property/value pair while propagating initial math nesting depth.
	 */
	public static function parseWithMathDepthInternal(
		string $property,
		string $cssText,
		?CSSParserOptions $options,
		int $mathDepth,
		?CSSContext $context = null,
	): self {
		$options ??= CSSParserOptions::default();
		if ($options->maxBytes !== null && strlen($property) > $options->maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS property name exceeds maximum size of %d bytes.', $options->maxBytes));
		}
		$normalized = CSSStyleDeclaration::normalizePropertyName($property);
		if ($options->maxBytes !== null && strlen($cssText) > $options->maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS value exceeds maximum size of %d bytes.', $options->maxBytes));
		}

		$trimmed = CSSSyntaxScanner::trimCssWhitespace($cssText);
		if ($trimmed === '') {
			throw new InvalidArgumentException('CSS value cannot be empty.');
		}
		CSSSyntaxScanner::assertValidFragment($trimmed, 'CSS value', [';', '{', '}']);
		if (!str_starts_with($normalized, '--') && str_contains($trimmed, '/*') && CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($trimmed)) === '') {
			throw new InvalidArgumentException('CSS value cannot be empty.');
		}

		return self::parseValidated($normalized, $trimmed, $options, $mathDepth, $context);
	}

	/**
	 * @internal Parses a trimmed, already-fragment-validated value into a Typed OM object.
	 */
	public static function parseValidated(
		string $normalizedProperty,
		string $trimmedValue,
		CSSParserOptions $options,
		int $mathDepth = 0,
		?CSSContext $context = null,
		bool $validateGrammar = true,
	): CSSStyleValue {
		$lower = strtolower($trimmedValue);
		if (isset(self::GLOBAL_KEYWORDS[$lower])) {
			return new CSSKeywordValue($lower);
		}
		if ($validateGrammar && $normalizedProperty !== '') {
			if (!CSSValueValidator::validatePropertyValue($normalizedProperty, $trimmedValue, $options, true)) {
				throw new InvalidArgumentException(sprintf('Invalid value "%s" for property "%s".', $trimmedValue, $normalizedProperty));
			}
		}
		if (str_starts_with($normalizedProperty, '--')) {
			return CSSUnparsedValue::fromCssText($trimmedValue, $options);
		}
		if (isset(self::PROPERTY_KEYWORDS[$normalizedProperty][$lower])) {
			return new CSSKeywordValue($trimmedValue);
		}

		if ($normalizedProperty === 'transform' || $normalizedProperty === 'translate' || $normalizedProperty === 'rotate' || $normalizedProperty === 'scale') {
			try {
				return CSSTransformValue::fromCssText($trimmedValue, $options);
			} catch (CSSParserLimitException $e) {
				throw $e;
			} catch (InvalidArgumentException) {
				// Retain values outside the supported transform grammar as unparsed values.
			}
		}

		$open = strpos($trimmedValue, '(');
		$fn = $open !== false ? CSSSyntaxScanner::decodeLeadingFunctionName($trimmedValue, $open) : null;
		if ($fn !== null
			&& preg_match('/^(?:url|image|image-set|cross-fade|paint|element|(?:repeating-)?(?:linear|radial|conic)-gradient)$/D', $fn) === 1
			&& ($fn === 'url' || !CSS::hasVarReference($trimmedValue))
		) {
			try {
				return new CSSImageValue($trimmedValue, $options);
			} catch (CSSParserLimitException $e) {
				throw $e;
			} catch (InvalidArgumentException) {
				// Fall through to generic unparsed value when extra trailing tokens are present.
			}
		}

		if ($normalizedProperty === 'object-position' || $normalizedProperty === 'background-position') {
			try {
				return CSSPositionValue::fromCssText($trimmedValue, $options);
			} catch (CSSParserLimitException $e) {
				throw $e;
			} catch (InvalidArgumentException) {
				// Unsupported position syntax remains available as source text below.
			}
		}

		$c0 = $trimmedValue[0];
		$isColorFunction = $fn !== null && preg_match('/^(?:rgba?|hsla?|hwb|lab|lch|oklab|oklch|color|color-mix|light-dark)$/D', $fn) === 1;
		if ($c0 === '#' || $isColorFunction) {
			$color = null;
			$isDeferredColor = false;
			try {
				$color = CSSColorValue::parseInternal($trimmedValue, null, $options, $context, $context === null);
			} catch (CSSParserLimitException $e) {
				throw $e;
			} catch (CSSDeferredColorException) {
				$isDeferredColor = true;
			} catch (InvalidArgumentException) {
				// Unsupported/var() color syntax falls through to unparsed validation below.
			}

			if ($color !== null || $isDeferredColor) {
				if ($color !== null) {
					return $color;
				}

				return CSSUnparsedValue::fromCssText($trimmedValue, $options);
			}
		}

		if ($fn === null && isset(self::COLOR_PROPERTIES[$normalizedProperty])) {
			$current = CSSColorValue::tryParseCurrentColorInternal($trimmedValue, $context);
			if ($current !== null) {
				return $current;
			}
			$namedColor = CSSColorValue::tryParseNamedColor($trimmedValue);
			if ($namedColor !== null) {
				return $namedColor;
			}
		}

		$numeric = CSSNumericValue::tryParseValidated($trimmedValue, $options, $mathDepth);
		if ($numeric !== null) {
			return $numeric;
		}

		return CSSUnparsedValue::fromCssText($trimmedValue, $options);
	}

	abstract public function __toString(): string;

	abstract public function clone(): static;
}
