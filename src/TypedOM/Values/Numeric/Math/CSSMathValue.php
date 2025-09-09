<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math;

use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\MultiValueTrait;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;

/**
 * Represents a CSS math value.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSMathValue
 */
abstract class CSSMathValue extends CSSNumericValue
{
	use CSSMathOperationTrait, MultiValueTrait;

	/**
	 * Convert to string representation.
	 *
	 * @return string The CSS string representation
	 */
    public function __toString(): string
    {
        $values = array_map(fn($v) => (string)$v, $this->values->values);
        $count = count($values);
		$trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
		$caller = $trace[1];
		$calledByMathValue = is_a($caller['class'], self::class, true);
		$hasOperator = (static::operator ?? static::operator !== '' ? true : false) ? true : false;
		$wrapWithCalc = false == $calledByMathValue && false == $hasOperator;

		$printArgs = [
			'wrap-open' =>
				($wrapWithCalc ?
					'calc(' :
					($calledByMathValue && $count > 1 ? '(' : '')
				) .
				($hasOperator ?
					static::operator . '(' : ''
				),
			'inner' => implode(
				(
					defined(static::class . '::sign') ?
						' ' . static::sign . ' ' :
						', '
				),
				$values
			),
			'wrap-close' =>
				($wrapWithCalc ?
					')' :
					($calledByMathValue && $count > 1 ? ')' : '')
				) .
				($hasOperator ?
					')' : ''
				),
		];

        if ($count == 1) {
            if ($this instanceof CSSMathInvert) {
                $printArgs['inner'] = '1 / ' . $values[0];
            } elseif ($this instanceof CSSMathNegate) {
				$printArgs['inner'] = '-' . $values[0];
            }
        }

		return $printArgs['wrap-open'] . $printArgs['inner'] . $printArgs['wrap-close'];
    }
   
    /**
     * Clone this math value.
     *
     * @return static The cloned value
     */
    public function clone(): static
    {
        $clonedValues = [];
        foreach ($this->values->values as $value) {
            $clonedValues[] = clone $value;
        }
        return new static($clonedValues);
    }
}