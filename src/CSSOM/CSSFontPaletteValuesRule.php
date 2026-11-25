<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;

/**
 * Represents a CSS `@font-palette-values` rule.
 *
 * @property-read string $name
 * @property-read string $fontFamily
 * @property-read string $basePalette
 * @property-read string $overrideColors
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSFontPaletteValuesRule
 */
final class CSSFontPaletteValuesRule extends CSSAtRule
{
	public function __construct(
		string $name,
		CSSStyleDeclaration $descriptors,
		?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES,
	) {
		self::assertValidMaxBytesInternal($maxBytes);
		if ($maxBytes !== null && strlen($name) > $maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS @font-palette-values name exceeds maximum size of %d bytes.', $maxBytes));
		}
		$normalized = CSSStyleDeclaration::normalizePropertyName($name);
		if (!str_starts_with($normalized, '--')) {
			throw new InvalidArgumentException('@font-palette-values requires a dashed identifier starting with "--".');
		}
		parent::__construct('font-palette-values', $normalized, $descriptors, true, $maxBytes);
	}

	public function getNameText(): string
	{
		return $this->prelude;
	}

	public function getFontFamily(): string
	{
		return $this->styleDeclaration?->getPropertyValue('font-family') ?? '';
	}

	public function getBasePalette(): string
	{
		return $this->styleDeclaration?->getPropertyValue('base-palette') ?? '';
	}

	public function getOverrideColors(): string
	{
		return $this->styleDeclaration?->getPropertyValue('override-colors') ?? '';
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'name' => $this->getNameText(),
			'fontFamily' => $this->getFontFamily(),
			'basePalette' => $this->getBasePalette(),
			'overrideColors' => $this->getOverrideColors(),
			default => parent::__get($name),
		};
	}

	public function __isset(string $name): bool
	{
		return match ($name) {
			'name', 'overrideColors', 'fontFamily', 'basePalette' => true,
			default => parent::__isset($name),
		};
	}
}
