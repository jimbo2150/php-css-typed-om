<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;

/**
 * Represents a CSS `@position-try` fallback rule for anchor positioning.
 *
 * @property-read string $name
 * @property-read CSSPositionTryDescriptors $style
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSPositionTryRule
 */
final class CSSPositionTryRule extends CSSAtRule
{
	public function __construct(
		string $name,
		?CSSStyleDeclaration $style = null,
		?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES,
	) {
		self::assertValidMaxBytesInternal($maxBytes);
		if ($maxBytes !== null && strlen($name) > $maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS @position-try name exceeds maximum size of %d bytes.', $maxBytes));
		}
		if ($style !== null && $maxBytes !== null) {
			$style->assertContentBytesWithinInternal($maxBytes);
		}
		$normalized = CSSStyleDeclaration::normalizePropertyName($name);
		if (!str_starts_with($normalized, '--')) {
			throw new InvalidArgumentException('@position-try requires a dashed identifier starting with "--".');
		}
		if ($style instanceof CSSPositionTryDescriptors) {
			$descriptors = $style->getParentRule() !== null ? clone $style : $style;
		} else {
			$descriptors = new CSSPositionTryDescriptors();
			if ($style !== null) {
				$descriptors->setLimitsInternal(
					self::minLimitInternal($maxBytes, $style->resolveMaxBytesInternal()),
					$style->resolveMaxDeclarationsInternal(),
					$style->resolveMaxDepthInternal(),
					$style->resolveMaxMathDepthInternal(),
					true,
				);
				$descriptors->copyDeclarationsFromInternal($style);
			}
		}
		parent::__construct('position-try', $normalized, $descriptors, true, $maxBytes);
	}

	public function getNameText(): string
	{
		return $this->prelude;
	}

	public function getStyle(): CSSPositionTryDescriptors
	{
		assert($this->styleDeclaration instanceof CSSPositionTryDescriptors);
		return $this->styleDeclaration;
	}

	public function getType(): int
	{
		return self::POSITION_TRY_RULE;
	}

	public function __get(string $name): mixed
	{
		return $name === 'name' ? $this->getNameText() : parent::__get($name);
	}

	public function __isset(string $name): bool
	{
		return $name === 'name' || parent::__isset($name);
	}
}
