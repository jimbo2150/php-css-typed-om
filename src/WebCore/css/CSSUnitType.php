<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\WebCore\css;

enum CSSUnitType
{
	case CSS_UNKNOWN;
	case CSS_NUMBER;
	case CSS_INTEGER;
	case CSS_PERCENTAGE;
	case CSS_EM;
	case CSS_EX;
	case CSS_PX;
	case CSS_CM;
	case CSS_MM;
	case CSS_IN;
	case CSS_PT;
	case CSS_PC;
	case CSS_DEG;
	case CSS_RAD;
	case CSS_GRAD;
	case CSS_MS;
	case CSS_S;
	case CSS_HZ;
	case CSS_KHZ;
	case CSS_DIMENSION;
	case CSS_STRING;
	case CSS_URI;
	case CSS_IDENT;
	case CSS_ATTR;

	case CSS_VW;
	case CSS_VH;
	case CSS_VMIN;
	case CSS_VMAX;
	case CSS_VB;
	case CSS_VI;
	case CSS_SVW;
	case CSS_SVH;
	case CSS_SVMIN;
	case CSS_SVMAX;
	case CSS_SVB;
	case CSS_SVI;
	case CSS_LVW;
	case CSS_LVH;
	case CSS_LVMIN;
	case CSS_LVMAX;
	case CSS_LVB;
	case CSS_LVI;
	case CSS_DVW;
	case CSS_DVH;
	case CSS_DVMIN;
	case CSS_DVMAX;
	case CSS_DVB;
	case CSS_DVI;
	public const FirstViewportCSSUnitType = self::CSS_VW;
	public const LastViewportCSSUnitType = self::CSS_DVI;

	case CSS_CQW;
	case CSS_CQH;
	case CSS_CQI;
	case CSS_CQB;
	case CSS_CQMIN;
	case CSS_CQMAX;

	case CSS_DPPX;
	case CSS_X;
	case CSS_DPI;
	case CSS_DPCM;
	case CSS_FR;
	case CSS_Q;
	case CSS_LH;
	case CSS_RLH;

	case CustomIdent;

	case CSS_TURN;
	case CSS_REM;
	case CSS_REX;
	case CSS_CAP;
	case CSS_RCAP;
	case CSS_CH;
	case CSS_RCH;
	case CSS_IC;
	case CSS_RIC;

	case CSS_CALC;
	case CSS_CALC_PERCENTAGE_WITH_ANGLE;
	case CSS_CALC_PERCENTAGE_WITH_LENGTH;

	case CSS_FONT_FAMILY;

	case CSS_PROPERTY_ID;
	case CSS_VALUE_ID;

	// This value is used to handle quirky margins in reflow roots (body, td, and th) like WinIE.
	// The basic idea is that a stylesheet can use the value __qem (for quirky em) instead of em.
	// When the quirky value is used, if you're in quirks mode, the margin will collapse away
	// inside a table cell. This quirk is specified in the HTML spec but our impl is different.
	case CSS_QUIRKY_EM;

	// Note that CSSValue allocates 7 bits for m_primitiveUnitType, so there can be no value here > 127.
}
