<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

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
		$normalized = self::validateDashedIdentInternal(
			$name,
			$maxBytes,
			'CSS @position-try name',
			'@position-try requires a dashed identifier starting with "--".',
		);
		if ($style !== null && $maxBytes !== null) {
			$style->assertContentBytesWithinInternal($maxBytes);
		}
		$this->initAtRuleInternal('position-try', $normalized, CSSPositionTryDescriptors::adoptOrConvert($style, $maxBytes), true, $maxBytes);
	}

	public function validatesDescriptorPropertyValuesInternal(): bool
	{
		return false;
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
