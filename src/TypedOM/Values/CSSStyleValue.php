<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSS;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleDeclaration;
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
	private const array SIZING_PROPERTIES = [
		'width' => true,
		'height' => true,
		'min-width' => true,
		'max-width' => true,
		'min-height' => true,
		'max-height' => true,
		'inline-size' => true,
		'block-size' => true,
	];

	/** @var array<string, true> */
	private const array SINGLE_COLOR_PROPERTIES = [
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
	];

	/** @var array<string, true> */
	private const array MULTI_COLOR_PROPERTIES = [
		'border-color' => true,
		'border-block-color' => true,
		'border-inline-color' => true,
	];

	/** @var array<string, true> */
	private const array SINGLE_KEYWORD_PROPERTIES = [
		'position' => true,
		'overflow-x' => true,
		'overflow-y' => true,
		'visibility' => true,
		'float' => true,
		'clear' => true,
		'box-sizing' => true,
		'text-align' => true,
	];

	/** @var array<string, true> */
	private const array MULTI_KEYWORD_PROPERTIES = [
		'border-style' => true,
		'overflow' => true,
		'display' => true,
		'justify-content' => true,
		'align-items' => true,
	];

	/** @var array<string, true> */
	private const array KNOWN_MATH_OR_COLOR_FUNCTIONS = [
		'calc' => true,
		'min' => true,
		'max' => true,
		'clamp' => true,
		'round' => true,
		'mod' => true,
		'rem' => true,
		'sin' => true,
		'cos' => true,
		'tan' => true,
		'asin' => true,
		'acos' => true,
		'atan' => true,
		'atan2' => true,
		'pow' => true,
		'sqrt' => true,
		'hypot' => true,
		'log' => true,
		'exp' => true,
		'abs' => true,
		'sign' => true,
		'rgb' => true,
		'rgba' => true,
		'hsl' => true,
		'hsla' => true,
		'hwb' => true,
		'lab' => true,
		'lch' => true,
		'oklab' => true,
		'oklch' => true,
		'color' => true,
		'color-mix' => true,
		'light-dark' => true,
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
	): array {
		if (str_starts_with($normalizedProperty, '--')) {
			$trimmed = CSSSyntaxScanner::trimCssWhitespace($validatedCssText);
			if ($trimmed === '') {
				throw new InvalidArgumentException('CSS value list cannot be empty.');
			}
			return [self::parseValidated($normalizedProperty, $trimmed, $options, 0, $context)];
		}

		$values = [];
		foreach (CSSSyntaxScanner::splitTopLevel($validatedCssText, ',', 0, null, self::MAX_LIST_ITEMS) as $value) {
			$value = CSSSyntaxScanner::trimCssWhitespace($value);
			if ($value !== '' && (!str_contains($value, '/*') || CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($value)) !== '')) {
				if (count($values) >= self::MAX_LIST_ITEMS) {
					throw new CSSParserLimitException(sprintf('CSS value list exceeds maximum limit of %d items.', self::MAX_LIST_ITEMS));
				}
				$values[] = self::parseValidated($normalizedProperty, $value, $options, 0, $context);
			}
		}

		if ($values === []) {
			throw new InvalidArgumentException('CSS value list cannot be empty.');
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
	): CSSStyleValue {
		$lower = strtolower($trimmedValue);
		if (isset(self::GLOBAL_KEYWORDS[$lower])) {
			return new CSSKeywordValue($lower);
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
		$isColorProp = isset(self::SINGLE_COLOR_PROPERTIES[$normalizedProperty]) || isset(self::MULTI_COLOR_PROPERTIES[$normalizedProperty]);
		$isDisallowedColorProp = isset(self::SIZING_PROPERTIES[$normalizedProperty])
			|| $normalizedProperty === 'opacity'
			|| isset(self::SINGLE_KEYWORD_PROPERTIES[$normalizedProperty])
			|| isset(self::MULTI_KEYWORD_PROPERTIES[$normalizedProperty]);
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
				if ($isDisallowedColorProp) {
					throw new InvalidArgumentException(sprintf('Color value is not valid for property "%s".', $normalizedProperty));
				}
				if ($color !== null) {
					return $color;
				}

				return CSSUnparsedValue::fromCssText($trimmedValue, $options);
			}
		}

		if ($isColorProp && $fn === null) {
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
			if ($isColorProp
				|| isset(self::SINGLE_KEYWORD_PROPERTIES[$normalizedProperty])
				|| isset(self::MULTI_KEYWORD_PROPERTIES[$normalizedProperty])
			) {
				throw new InvalidArgumentException(sprintf('Numeric value is not valid for property "%s".', $normalizedProperty));
			}
			if (isset(self::SIZING_PROPERTIES[$normalizedProperty])) {
				$type = $numeric->getNumericTypeInternal();
				$category = $numeric->getNumericCategory();
				$hint = $type->percentHint;
				$isUnitlessZero = $numeric instanceof Numeric\CSSUnitValue && $numeric->unit === 'number' && $numeric->value === 0.0;
				$isValidSizing = $isUnitlessZero
					|| ($category === 'length' && ($hint === null || $hint === 'length'))
					|| ($category === 'percent' && $hint === null);
				if (!$isValidSizing) {
					throw new InvalidArgumentException(sprintf('Numeric value is not valid for property "%s".', $normalizedProperty));
				}
			} elseif ($normalizedProperty === 'opacity') {
				$type = $numeric->getNumericTypeInternal();
				$category = $numeric->getNumericCategory();
				if ($type->percentHint !== null || ($category !== 'number' && $category !== 'percent')) {
					throw new InvalidArgumentException('Numeric value is not valid for property "opacity".');
				}
			}
			return $numeric;
		}

		$unparsed = CSSUnparsedValue::fromCssText($trimmedValue, $options);
		self::assertAllowedUnparsedForModeledProperty($normalizedProperty, $trimmedValue, $fn);

		return $unparsed;
	}

	private static function assertAllowedUnparsedForModeledProperty(
		string $normalizedProperty,
		string $trimmedValue,
		?string $leadingFunction,
	): void {
		$isSizing = isset(self::SIZING_PROPERTIES[$normalizedProperty]);
		$isOpacity = $normalizedProperty === 'opacity';
		$isSingleColor = isset(self::SINGLE_COLOR_PROPERTIES[$normalizedProperty]);
		$isMultiColor = isset(self::MULTI_COLOR_PROPERTIES[$normalizedProperty]);
		$isSingleKeyword = isset(self::SINGLE_KEYWORD_PROPERTIES[$normalizedProperty]);
		$isMultiKeyword = isset(self::MULTI_KEYWORD_PROPERTIES[$normalizedProperty]);

		if (!$isSizing && !$isOpacity && !$isSingleColor && !$isMultiColor && !$isSingleKeyword && !$isMultiKeyword) {
			return;
		}

		if (CSS::hasVarReference($trimmedValue) || stripos($trimmedValue, 'env(') !== false) {
			return;
		}

		if ($leadingFunction !== null) {
			$hasTrailingTokens = false;
			$open = strpos($trimmedValue, '(');
			if ($open !== false) {
				$close = CSSSyntaxScanner::findMatchingDelimiter($trimmedValue, $open);
				$trailingSlice = substr($trimmedValue, $close + 1);
				if (str_contains($trailingSlice, '/*')) {
					$trailingSlice = CSSSyntaxScanner::removeComments($trailingSlice);
				}
				$hasTrailingTokens = CSSSyntaxScanner::trimCssWhitespace($trailingSlice) !== '';
			}
			if (isset(self::KNOWN_MATH_OR_COLOR_FUNCTIONS[$leadingFunction])) {
				if ($isMultiColor && $hasTrailingTokens) {
					return;
				}
				// On color properties, keep legacy 10-function fallback (e.g. rgb(1,2,3,4,5)),
				// while rejecting syntactically invalid color-mix() / light-dark() on single color properties.
				if (($isSingleColor || $isMultiColor) && in_array($leadingFunction, ['rgb', 'rgba', 'hsl', 'hsla', 'hwb', 'lab', 'lch', 'oklab', 'oklch', 'color'], true)) {
					return;
				}
				throw new InvalidArgumentException(sprintf('Invalid function value "%s" for property "%s".', $trimmedValue, $normalizedProperty));
			}
			if ($isSingleKeyword || $isOpacity) {
				throw new InvalidArgumentException(sprintf('Function value "%s" is not valid for property "%s".', $trimmedValue, $normalizedProperty));
			}
			if ($isSizing && $hasTrailingTokens) {
				throw new InvalidArgumentException(sprintf('Invalid sizing value "%s" for property "%s".', $trimmedValue, $normalizedProperty));
			}
			return;
		}

		$tokens = CSSSyntaxScanner::splitTopLevelWhitespace($trimmedValue);
		if (count($tokens) > 1 && ($isMultiColor || $isMultiKeyword)) {
			return;
		}

		throw new InvalidArgumentException(sprintf('Invalid value "%s" for property "%s".', $trimmedValue, $normalizedProperty));
	}

	abstract public function __toString(): string;

	abstract public function clone(): static;
}
