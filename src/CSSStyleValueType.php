<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm;

enum CSSStyleValueType
{
	case CSSStyleValue;
	case CSSStyleImageValue;
	case CSSTransformValue;
	case CSSMathClamp;
	case CSSMathInvert;
	case CSSMathMin;
	case CSSMathMax;
	case CSSMathNegate;
	case CSSMathProduct;
	case CSSMathSum;
	case CSSUnitValue;
	case CSSUnparsedValue;
	case CSSKeywordValue;

	public static function isCSSNumericValue(self $type)
	{
		switch ($type) {
			case self::CSSMathClamp:
			case self::CSSMathInvert:
			case self::CSSMathMin:
			case self::CSSMathMax:
			case self::CSSMathNegate:
			case self::CSSMathProduct:
			case self::CSSMathSum:
			case self::CSSUnitValue:
				return true;
			case self::CSSStyleValue:
			case self::CSSStyleImageValue:
			case self::CSSTransformValue:
			case self::CSSUnparsedValue:
			case self::CSSKeywordValue:
				break;
		}

		return false;
	}

	public static function isCSSMathValue(self $type)
	{
		switch ($type) {
			case self::CSSMathClamp:
			case self::CSSMathInvert:
			case self::CSSMathMin:
			case self::CSSMathMax:
			case self::CSSMathNegate:
			case self::CSSMathProduct:
			case self::CSSMathSum:
				return true;
			case self::CSSUnitValue:
			case self::CSSStyleValue:
			case self::CSSStyleImageValue:
			case self::CSSTransformValue:
			case self::CSSUnparsedValue:
			case self::CSSKeywordValue:
				break;
		}

		return false;
	}
}
