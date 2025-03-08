<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\WebCore\css;

use Jimbo2150\PhpCssTypedOm\Css\CSSPropertyID;
use Jimbo2150\PhpCssTypedOm\CSSValueID;

class CSSPrimitiveValue
{
	private CSSPrimitiveValueValue $m_value;

	public static function isValidCSSUnitTypeForDoubleConversion(CSSUnitType $unitType): bool
	{
		switch ($unitType) {
			case CSSUnitType::CSS_CALC:
			case CSSUnitType::CSS_CALC_PERCENTAGE_WITH_ANGLE:
			case CSSUnitType::CSS_CALC_PERCENTAGE_WITH_LENGTH:
			case CSSUnitType::CSS_CAP:
			case CSSUnitType::CSS_CH:
			case CSSUnitType::CSS_IC:
			case CSSUnitType::CSS_CM:
			case CSSUnitType::CSS_DEG:
			case CSSUnitType::CSS_DIMENSION:
			case CSSUnitType::CSS_DVB:
			case CSSUnitType::CSS_DVH:
			case CSSUnitType::CSS_DVI:
			case CSSUnitType::CSS_DVMAX:
			case CSSUnitType::CSS_DVMIN:
			case CSSUnitType::CSS_DVW:
			case CSSUnitType::CSS_EM:
			case CSSUnitType::CSS_EX:
			case CSSUnitType::CSS_FR:
			case CSSUnitType::CSS_GRAD:
			case CSSUnitType::CSS_HZ:
			case CSSUnitType::CSS_IN:
			case CSSUnitType::CSS_KHZ:
			case CSSUnitType::CSS_MM:
			case CSSUnitType::CSS_MS:
			case CSSUnitType::CSS_NUMBER:
			case CSSUnitType::CSS_INTEGER:
			case CSSUnitType::CSS_PC:
			case CSSUnitType::CSS_PERCENTAGE:
			case CSSUnitType::CSS_PT:
			case CSSUnitType::CSS_PX:
			case CSSUnitType::CSS_Q:
			case CSSUnitType::CSS_LH:
			case CSSUnitType::CSS_LVB:
			case CSSUnitType::CSS_LVH:
			case CSSUnitType::CSS_LVI:
			case CSSUnitType::CSS_LVMAX:
			case CSSUnitType::CSS_LVMIN:
			case CSSUnitType::CSS_LVW:
			case CSSUnitType::CSS_RLH:
			case CSSUnitType::CSS_QUIRKY_EM:
			case CSSUnitType::CSS_RAD:
			case CSSUnitType::CSS_RCAP:
			case CSSUnitType::CSS_RCH:
			case CSSUnitType::CSS_REM:
			case CSSUnitType::CSS_REX:
			case CSSUnitType::CSS_RIC:
			case CSSUnitType::CSS_S:
			case CSSUnitType::CSS_SVB:
			case CSSUnitType::CSS_SVH:
			case CSSUnitType::CSS_SVI:
			case CSSUnitType::CSS_SVMAX:
			case CSSUnitType::CSS_SVMIN:
			case CSSUnitType::CSS_SVW:
			case CSSUnitType::CSS_TURN:
			case CSSUnitType::CSS_VB:
			case CSSUnitType::CSS_VH:
			case CSSUnitType::CSS_VI:
			case CSSUnitType::CSS_VMAX:
			case CSSUnitType::CSS_VMIN:
			case CSSUnitType::CSS_VW:
			case CSSUnitType::CSS_DPCM:
			case CSSUnitType::CSS_DPI:
			case CSSUnitType::CSS_DPPX:
			case CSSUnitType::CSS_X:
			case CSSUnitType::CSS_CQW:
			case CSSUnitType::CSS_CQH:
			case CSSUnitType::CSS_CQI:
			case CSSUnitType::CSS_CQB:
			case CSSUnitType::CSS_CQMIN:
			case CSSUnitType::CSS_CQMAX:
				return true;
			case CSSUnitType::CSS_ATTR:
			case CSSUnitType::CSS_FONT_FAMILY:
			case CSSUnitType::CustomIdent:
			case CSSUnitType::CSS_PROPERTY_ID:
			case CSSUnitType::CSS_STRING:
			case CSSUnitType::CSS_UNKNOWN:
			case CSSUnitType::CSS_URI:
			case CSSUnitType::CSS_VALUE_ID:
				return false;
			case CSSUnitType::CSS_IDENT:
				break;
		}

		return false;
	}

	public static function isStringType(CSSUnitType $type): bool
	{
		switch ($type) {
			case CSSUnitType::CSS_STRING:
			case CSSUnitType::CustomIdent:
			case CSSUnitType::CSS_URI:
			case CSSUnitType::CSS_ATTR:
			case CSSUnitType::CSS_FONT_FAMILY:
				return true;
			case CSSUnitType::CSS_CALC:
			case CSSUnitType::CSS_CALC_PERCENTAGE_WITH_ANGLE:
			case CSSUnitType::CSS_CALC_PERCENTAGE_WITH_LENGTH:
			case CSSUnitType::CSS_CAP:
			case CSSUnitType::CSS_CH:
			case CSSUnitType::CSS_IC:
			case CSSUnitType::CSS_CM:
			case CSSUnitType::CSS_DEG:
			case CSSUnitType::CSS_DIMENSION:
			case CSSUnitType::CSS_DPCM:
			case CSSUnitType::CSS_DPI:
			case CSSUnitType::CSS_DPPX:
			case CSSUnitType::CSS_DVB:
			case CSSUnitType::CSS_DVH:
			case CSSUnitType::CSS_DVI:
			case CSSUnitType::CSS_DVMAX:
			case CSSUnitType::CSS_DVMIN:
			case CSSUnitType::CSS_DVW:
			case CSSUnitType::CSS_X:
			case CSSUnitType::CSS_EM:
			case CSSUnitType::CSS_EX:
			case CSSUnitType::CSS_FR:
			case CSSUnitType::CSS_GRAD:
			case CSSUnitType::CSS_HZ:
			case CSSUnitType::CSS_IDENT:
			case CSSUnitType::CSS_IN:
			case CSSUnitType::CSS_KHZ:
			case CSSUnitType::CSS_LVB:
			case CSSUnitType::CSS_LVH:
			case CSSUnitType::CSS_LVI:
			case CSSUnitType::CSS_LVMAX:
			case CSSUnitType::CSS_LVMIN:
			case CSSUnitType::CSS_LVW:
			case CSSUnitType::CSS_MM:
			case CSSUnitType::CSS_MS:
			case CSSUnitType::CSS_NUMBER:
			case CSSUnitType::CSS_INTEGER:
			case CSSUnitType::CSS_PC:
			case CSSUnitType::CSS_PERCENTAGE:
			case CSSUnitType::CSS_PROPERTY_ID:
			case CSSUnitType::CSS_PT:
			case CSSUnitType::CSS_PX:
			case CSSUnitType::CSS_Q:
			case CSSUnitType::CSS_LH:
			case CSSUnitType::CSS_RLH:
			case CSSUnitType::CSS_QUIRKY_EM:
			case CSSUnitType::CSS_RAD:
			case CSSUnitType::CSS_RCAP:
			case CSSUnitType::CSS_RCH:
			case CSSUnitType::CSS_REM:
			case CSSUnitType::CSS_REX:
			case CSSUnitType::CSS_RIC:
			case CSSUnitType::CSS_S:
			case CSSUnitType::CSS_SVB:
			case CSSUnitType::CSS_SVH:
			case CSSUnitType::CSS_SVI:
			case CSSUnitType::CSS_SVMAX:
			case CSSUnitType::CSS_SVMIN:
			case CSSUnitType::CSS_SVW:
			case CSSUnitType::CSS_TURN:
			case CSSUnitType::CSS_UNKNOWN:
			case CSSUnitType::CSS_VALUE_ID:
			case CSSUnitType::CSS_VB:
			case CSSUnitType::CSS_VH:
			case CSSUnitType::CSS_VI:
			case CSSUnitType::CSS_VMAX:
			case CSSUnitType::CSS_VMIN:
			case CSSUnitType::CSS_VW:
			case CSSUnitType::CSS_CQW:
			case CSSUnitType::CSS_CQH:
			case CSSUnitType::CSS_CQI:
			case CSSUnitType::CSS_CQB:
			case CSSUnitType::CSS_CQMIN:
			case CSSUnitType::CSS_CQMAX:
				return false;
		}

		return false;
	}

	public function primitiveType(): CSSUnitType
	{
		$type = $this->primitiveUnitType();
		switch ($type) {
			case CSSUnitType::CSS_PROPERTY_ID:
			case CSSUnitType::CSS_VALUE_ID:
			case CSSUnitType::CustomIdent:
				return CSSUnitType::CSS_IDENT;
			case CSSUnitType::CSS_FONT_FAMILY:
				// Web-exposed content expects font family values to have CSSUnitType::CSS_STRING primitive type
				// so we need to map our internal CSSUnitType::CSS_FONT_FAMILY type here.
				return CSSUnitType::CSS_STRING;
			default:
				if (!$this->isCalculated()) {
					return $type;
				}

				return $this->m_value->calc->primitiveType();
		}
	}

	public function __construct()
	{
		$type = $this->primitiveUnitType();
		switch ($type) {
			case CSSUnitType::CSS_STRING:
			case CSSUnitType::CustomIdent:
			case CSSUnitType::CSS_URI:
			case CSSUnitType::CSS_FONT_FAMILY:
				if ($this->m_value->string) {
					$this->m_value->string->deref();
				}
				break;
			case CSSUnitType::CSS_ATTR:
				$this->m_value->attr->deref();
				break;
			case CSSUnitType::CSS_CALC:
				$this->m_value->calc->deref();
				break;
			case CSSUnitType::CSS_CALC_PERCENTAGE_WITH_ANGLE:
			case CSSUnitType::CSS_CALC_PERCENTAGE_WITH_LENGTH:
				assert(false);
				break;
			case CSSUnitType::CSS_DIMENSION:
			case CSSUnitType::CSS_NUMBER:
			case CSSUnitType::CSS_INTEGER:
			case CSSUnitType::CSS_PERCENTAGE:
			case CSSUnitType::CSS_EM:
			case CSSUnitType::CSS_QUIRKY_EM:
			case CSSUnitType::CSS_EX:
			case CSSUnitType::CSS_CAP:
			case CSSUnitType::CSS_CH:
			case CSSUnitType::CSS_IC:
			case CSSUnitType::CSS_RCAP:
			case CSSUnitType::CSS_RCH:
			case CSSUnitType::CSS_REM:
			case CSSUnitType::CSS_REX:
			case CSSUnitType::CSS_RIC:
			case CSSUnitType::CSS_PX:
			case CSSUnitType::CSS_CM:
			case CSSUnitType::CSS_MM:
			case CSSUnitType::CSS_IN:
			case CSSUnitType::CSS_PT:
			case CSSUnitType::CSS_PC:
			case CSSUnitType::CSS_DEG:
			case CSSUnitType::CSS_RAD:
			case CSSUnitType::CSS_GRAD:
			case CSSUnitType::CSS_MS:
			case CSSUnitType::CSS_S:
			case CSSUnitType::CSS_HZ:
			case CSSUnitType::CSS_KHZ:
			case CSSUnitType::CSS_TURN:
			case CSSUnitType::CSS_VW:
			case CSSUnitType::CSS_VH:
			case CSSUnitType::CSS_VMIN:
			case CSSUnitType::CSS_VMAX:
			case CSSUnitType::CSS_VB:
			case CSSUnitType::CSS_VI:
			case CSSUnitType::CSS_SVW:
			case CSSUnitType::CSS_SVH:
			case CSSUnitType::CSS_SVMIN:
			case CSSUnitType::CSS_SVMAX:
			case CSSUnitType::CSS_SVB:
			case CSSUnitType::CSS_SVI:
			case CSSUnitType::CSS_LVW:
			case CSSUnitType::CSS_LVH:
			case CSSUnitType::CSS_LVMIN:
			case CSSUnitType::CSS_LVMAX:
			case CSSUnitType::CSS_LVB:
			case CSSUnitType::CSS_LVI:
			case CSSUnitType::CSS_DVW:
			case CSSUnitType::CSS_DVH:
			case CSSUnitType::CSS_DVMIN:
			case CSSUnitType::CSS_DVMAX:
			case CSSUnitType::CSS_DVB:
			case CSSUnitType::CSS_DVI:
			case CSSUnitType::CSS_DPPX:
			case CSSUnitType::CSS_X:
			case CSSUnitType::CSS_DPI:
			case CSSUnitType::CSS_DPCM:
			case CSSUnitType::CSS_FR:
			case CSSUnitType::CSS_Q:
			case CSSUnitType::CSS_LH:
			case CSSUnitType::CSS_RLH:
			case CSSUnitType::CSS_IDENT:
			case CSSUnitType::CSS_UNKNOWN:
			case CSSUnitType::CSS_PROPERTY_ID:
			case CSSUnitType::CSS_VALUE_ID:
			case CSSUnitType::CSS_CQW:
			case CSSUnitType::CSS_CQH:
			case CSSUnitType::CSS_CQI:
			case CSSUnitType::CSS_CQB:
			case CSSUnitType::CSS_CQMIN:
			case CSSUnitType::CSS_CQMAX:
				assert(!is_string($type));
				break;
		}
		if ($this->m_hasCachedCSSText) {
			assert($this->serializedPrimitiveValues()->contains($this));
			$this->serializedPrimitiveValues()->remove($this);
		}
	}

	public static function createWithPropertyId(CSSPropertyID $propertyID): self
	{
		$new = new self();
		$new->setPrimitiveUnitType(CSSUnitType::CSS_PROPERTY_ID);
		$new->m_value->propertyID = $propertyID;

		return $new;
	}

	public static function createWithUnitType(
		float|string $value,
		CSSUnitType $type,
		bool $isStatic = false,
	): self {
		$new = new self();
		$new->setPrimitiveUnitType($type);
		if (is_string($value)) {
			$new->m_value->string = $value;
		} else {
			$new->m_value->number = $value;
		}
		if ($isStatic) {
			$new->makeStatic();
		}

		return $new;
	}

	public static function createWithStaticValueTag(
		CSSValueID $valueID,
		bool $isStatic = false,
	): self {
		$new = new self();
		$new->setPrimitiveUnitType(CSSUnitType::CSS_VALUE_ID);
		$new->m_value->valueID = $valueID;
		if ($isStatic) {
			$new->makeStatic();
		}

		return $new;
	}

	public static function createWithValue(CSSCalcValue|CSSAttrValue $value): self
	{
		$new = new self();
		$prop = null;
		switch ($value::class) {
			case CSSCalcValue::class:
				$new->setPrimitiveUnitType(CSSUnitType::CSS_CALC);
				$prop = 'calc';
				break;
			case CSSAttrValue::class:
				$new->setPrimitiveUnitType(CSSUnitType::CSS_ATTR);
				$prop = 'attr';
				break;
		}
		$new->m_value->{$prop} = $value;

		return $new;
	}
}
