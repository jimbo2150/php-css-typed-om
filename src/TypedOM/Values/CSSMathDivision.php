<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

/**
 * A CSSMathDivision is a CSSNumericValue that is the result of a division.
 * It is used to represent `calc()` expressions with division.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSMathDivision
 */
class CSSMathDivision extends CSSNumericValue
{
	/** @var CSSNumericValue[] */
	private array $values;

	public function __construct(CSSNumericValue ...$values)
	{
		if (count($values) < 1) {
			throw new \InvalidArgumentException('CSSMathDivision requires at least one value.');
		}
		$this->values = $values;
		parent::__construct('math-division');
	}

	public function getValues(): array
	{
		return $this->values;
	}

	public function toString(): string
	{
		$parts = [];
		foreach ($this->values as $v) {
			$parts[] = $v->toString();
		}

		return 'calc('.implode(' / ', $parts).')';
	}

	public function isValid(): bool
	{
		if (empty($this->values)) {
			return false;
		}
		foreach ($this->values as $value) {
			if (!$value->isValid()) {
				return false;
			}
		}

		return true;
	}

	public function clone(): CSSStyleValue
	{
		return new self(...$this->values);
	}

	public function to(string $unit): ?CSSUnitValue
	{
		// Not implemented
		return null;
	}
}
