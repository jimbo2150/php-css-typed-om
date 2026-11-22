<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSColorValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Stringable;

/**
 * Base class for CSS Typed OM values.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSStyleValue
 */
abstract class CSSStyleValue implements Stringable
{
	/** @var array<string, list<string>> */
	private const array PROPERTY_KEYWORDS = [
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

	public function __construct()
	{
	}

	/**
	 * Parses one property value into a Typed OM representation.
	 * The single-argument form parses a generic standalone value.
	 *
	 * @throws InvalidArgumentException When the value is empty, unbalanced, or contains disallowed control/delimiter bytes
	 */
	public static function parse(string $propertyOrCssText, ?string $cssText = null, ?CSSParserOptions $options = null): CSSStyleValue
	{
		$property = $cssText === null ? '' : strtolower(trim($propertyOrCssText));
		$cssText = trim($cssText ?? $propertyOrCssText);
		if ($cssText === '') {
			throw new InvalidArgumentException('CSS value cannot be empty.');
		}
		CSSSyntaxScanner::assertValidFragment($cssText, 'CSS value', [';', '{', '}']);

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
			try {
				return new CSSImageValue($cssText);
			} catch (InvalidArgumentException) {
				// Fall through to generic unparsed value when extra trailing tokens are present.
			}
		}

		if (in_array($property, ['object-position', 'background-position'], true)) {
			try {
				return CSSPositionValue::fromCssText($cssText);
			} catch (InvalidArgumentException) {
				// Unsupported position syntax remains available as source text below.
			}
		}

		if ($cssText[0] === '#' || preg_match('/^(?:rgba?|hsla?|hwb|lab|lch|oklab|oklch|color)\s*\(/i', $cssText)) {
			try {
				return CSSColorValue::parse($cssText, null, $options);
			} catch (InvalidArgumentException $exception) {
				if (str_contains($exception->getMessage(), 'depth') || str_contains($exception->getMessage(), 'maximum size')) {
					throw $exception;
				}
				// Unsupported/relative/var() color syntax falls through to unparsed value below.
			}
		}

		try {
			return CSSNumericValue::parse($cssText, null, $options);
		} catch (InvalidArgumentException $exception) {
			if (str_contains($exception->getMessage(), 'depth') || str_contains($exception->getMessage(), 'maximum size')) {
				throw $exception;
			}
		}

		if (self::isKnownPropertyKeyword($property, $cssText)) {
			return new CSSKeywordValue($cssText);
		}

		return CSSUnparsedValue::fromCssText($cssText);
	}

	/**
	 * Parses a comma-separated list of values for a property.
	 *
	 * @return list<CSSStyleValue>
	 * @throws InvalidArgumentException When the list is empty or malformed
	 */
	public static function parseAll(string $property, string $cssText, ?CSSParserOptions $options = null): array
	{
		CSSSyntaxScanner::assertValidFragment($cssText, 'CSS value list', [';', '{', '}']);

		$values = [];
		foreach (CSSSyntaxScanner::splitTopLevel($cssText, ',') as $value) {
			$value = trim($value);
			if ($value !== '') {
				$values[] = self::parse($property, $value, $options);
			}
		}

		if ($values === []) {
			throw new InvalidArgumentException('CSS value list cannot be empty.');
		}

		return $values;
	}

	abstract public function __toString(): string;

	abstract public function clone(): static;

	private static function isKnownPropertyKeyword(string $property, string $value): bool
	{
		return in_array(strtolower($value), self::PROPERTY_KEYWORDS[$property] ?? [], true);
	}
}
