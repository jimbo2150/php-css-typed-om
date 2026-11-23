<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericArray;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericType;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use ReflectionClass;

/**
 * Represents a CSS sum expression (`+` / `-` inside `calc()`).
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSMathSum
 */
class CSSMathSum extends CSSMathValue
{
	public const ?string operator = 'sum';
	public const ?string sign = '+';

	/** @param CSSNumericValue|CSSNumericArray|list<CSSNumericValue> $values */
	public function __construct(CSSNumericValue|CSSNumericArray|array $values)
	{
		parent::__construct($values);
		$this->requireOperandCount(1);
		$this->validateAdditiveTypes();
	}

	/**
	 * @internal Fast path for already type-checked operand lists.
	 * @param list<CSSNumericValue> $values
	 */
	public static function fromValidated(array $values, CSSNumericType $type): self
	{
		static $reflection = null;
		$reflection ??= new ReflectionClass(self::class);
		/** @var self $sum */
		$sum = $reflection->newInstanceWithoutConstructor();
		$sum->values = new CSSNumericArray($values);
		$sum->cachedNumericType = $type;

		return $sum;
	}
}
