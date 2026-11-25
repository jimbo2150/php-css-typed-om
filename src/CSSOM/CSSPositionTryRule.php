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
		if ($maxBytes !== null && strlen($name) > $maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS @position-try name exceeds maximum size of %d bytes.', $maxBytes));
		}
		$normalized = CSSStyleDeclaration::normalizePropertyName($name);
		if (!str_starts_with($normalized, '--')) {
			throw new InvalidArgumentException('@position-try requires a dashed identifier starting with "--".');
		}
		if ($style instanceof CSSPositionTryDescriptors) {
			$descriptors = $style->getParentRule() !== null ? clone $style : $style;
		} else {
			$descriptors = new CSSPositionTryDescriptors();
			foreach ($style?->getDeclarationsRaw() ?? [] as $property => $declaration) {
				$descriptors->setValidatedProperty($property, $declaration['value'], $declaration['priority']);
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
