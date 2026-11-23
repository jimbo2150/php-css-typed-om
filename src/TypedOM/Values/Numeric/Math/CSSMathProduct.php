<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericArray;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericType;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use ReflectionClass;

/**
 * Represents a CSS product expression (`*` / `/` inside `calc()`).
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSMathProduct
 */
class CSSMathProduct extends CSSMathValue
{
	public const ?string operator = 'product';
	public const ?string sign = '*';

	/** @param CSSNumericValue|CSSNumericArray|list<CSSNumericValue> $values */
	public function __construct(CSSNumericValue|CSSNumericArray|array $values)
	{
		parent::__construct($values);
		$this->requireOperandCount(1);
		$this->cachedNumericType = $this->getNumericTypeInternal();
	}

	/**
	 * @internal Fast path for already type-checked operand lists.
	 * @param list<CSSNumericValue> $values
	 */
	public static function fromValidated(array $values, ?CSSNumericType $type = null): self
	{
		static $reflection = null;
		$reflection ??= new ReflectionClass(self::class);
		/** @var self $product */
		$product = $reflection->newInstanceWithoutConstructor();
		$product->values = new CSSNumericArray($values);
		$product->cachedNumericType = $type;

		return $product;
	}
}
