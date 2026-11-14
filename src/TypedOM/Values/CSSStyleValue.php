<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;

/**
 * Base class for CSS Typed OM values
 * Represents a CSS value that can be manipulated through the Typed OM API.
 */
abstract class CSSStyleValue
{

	/**
	 * Constructs a new CSSStyleValue.
	 */
	public function __construct()
	{
	}

	/**
	 * Parse one property value. The one-argument form is retained for parsing a generic value.
	 */
	public static function parse(string $propertyOrCssText, ?string $cssText = null): CSSStyleValue
	{
		$property = $cssText === null ? '' : strtolower(trim($propertyOrCssText));
		$cssText = trim($cssText ?? $propertyOrCssText);
		if ($cssText === '') {
			throw new InvalidArgumentException('CSS value cannot be empty.');
		}
		if (!CSSSyntaxScanner::isBalanced($cssText)) {
			throw new InvalidArgumentException('CSS value has unbalanced syntax.');
		}
		if (in_array(strtolower($cssText), ['inherit', 'initial', 'unset', 'revert', 'revert-layer'], true)) {
			return new CSSKeywordValue(strtolower($cssText));
		}
		if (str_starts_with($property, '--')) {
			return CSSUnparsedValue::fromCssText($cssText);
		}

		if ($property === 'transform' || in_array($property, ['translate', 'rotate', 'scale'], true)) {
			try {
				return CSSTransformValue::fromCssText($cssText);
			} catch (InvalidArgumentException) {
				// Retain values outside the supported transform grammar as unparsed values.
			}
		}

		if (preg_match('/^(?:url|image|image-set|cross-fade|paint|element|(?:repeating-)?(?:linear|radial|conic)-gradient)\(/i', $cssText)) {
			return new CSSImageValue($cssText);
		}

		if (in_array($property, ['object-position', 'background-position'], true)) {
			try {
				return CSSPositionValue::fromCssText($cssText);
			} catch (InvalidArgumentException) {
				// Unsupported position syntax remains available as source text below.
			}
		}

		try {
			return CSSNumericValue::parse($cssText);
		} catch (InvalidArgumentException) {
			// Most CSS property grammars are intentionally not inferred without a property registry.
		}

		if (self::isKnownPropertyKeyword($property, $cssText)) {
			return new CSSKeywordValue($cssText);
		}

		return CSSUnparsedValue::fromCssText($cssText);
	}

	private static function isKnownPropertyKeyword(string $property, string $value): bool
	{
		$keywords = [
			'display' => ['block', 'contents', 'flex', 'flow', 'flow-root', 'grid', 'inline', 'inline-block', 'inline-flex', 'inline-grid', 'inline-table', 'list-item', 'none', 'ruby', 'table'],
			'position' => ['absolute', 'fixed', 'relative', 'static', 'sticky'],
			'width' => ['auto', 'fit-content', 'max-content', 'min-content', 'stretch'],
			'height' => ['auto', 'fit-content', 'max-content', 'min-content', 'stretch'],
			'min-width' => ['auto', 'fit-content', 'max-content', 'min-content', 'stretch'],
			'max-width' => ['none', 'fit-content', 'max-content', 'min-content', 'stretch'],
			'min-height' => ['auto', 'fit-content', 'max-content', 'min-content', 'stretch'],
			'max-height' => ['none', 'fit-content', 'max-content', 'min-content', 'stretch'],
			'inline-size' => ['auto', 'fit-content', 'max-content', 'min-content', 'stretch'],
			'block-size' => ['auto', 'fit-content', 'max-content', 'min-content', 'stretch'],
			'overflow' => ['auto', 'clip', 'hidden', 'scroll', 'visible'],
			'overflow-x' => ['auto', 'clip', 'hidden', 'scroll', 'visible'],
			'overflow-y' => ['auto', 'clip', 'hidden', 'scroll', 'visible'],
			'visibility' => ['collapse', 'hidden', 'visible'],
			'float' => ['inline-end', 'inline-start', 'left', 'none', 'right'],
			'clear' => ['both', 'inline-end', 'inline-start', 'left', 'none', 'right'],
			'box-sizing' => ['border-box', 'content-box'],
			'transform' => ['none'],
			'background-image' => ['none'],
			'mask-image' => ['none'],
			'border-style' => ['dashed', 'dotted', 'double', 'groove', 'hidden', 'inset', 'none', 'outset', 'ridge', 'solid'],
			'text-align' => ['center', 'end', 'justify', 'left', 'match-parent', 'right', 'start'],
			'justify-content' => ['center', 'end', 'flex-end', 'flex-start', 'left', 'normal', 'right', 'space-around', 'space-between', 'space-evenly', 'start', 'stretch'],
			'align-items' => ['baseline', 'center', 'end', 'flex-end', 'flex-start', 'normal', 'self-end', 'self-start', 'start', 'stretch'],
		];

		return in_array(strtolower($value), $keywords[$property] ?? [], true);
	}

	/** @return list<CSSStyleValue> */
	public static function parseAll(string $property, string $cssText): array
	{
		$values = [];
		foreach (CSSSyntaxScanner::splitTopLevel($cssText, ',') as $value) {
			$value = trim($value);
			if ($value !== '') {
				$values[] = self::parse($property, $value);
			}
		}

		if ($values === []) {
			throw new InvalidArgumentException('CSS value list cannot be empty.');
		}

		return $values;
	}

	/**
	 * Clone this value.
	 *
	 * @return static A clone of this value
	 */
	abstract public function clone(): static;
}
