<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericArray;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericType;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use ReflectionClass;

/**
 * Represents a CSS `min()` calculation.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSMathMin
 */
class CSSMathMin extends CSSMathValue
{
	public const ?string operator = 'min';

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
		/** @var self $node */
		$node = $reflection->newInstanceWithoutConstructor();
		$node->values = new CSSNumericArray($values);
		$node->cachedNumericType = $type;

		return $node;
	}
}
