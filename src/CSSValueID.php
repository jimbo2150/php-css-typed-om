<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm;

enum CSSValueID: string
{
	case CSSValueInitial = 'initial';
	case CSSValueInherit = 'inherit';
	case CSSValueUnset = 'unset';
	case CSSValueRevert = 'revert';
	case CSSValueRevertLayer = 'revert-layer';
	case CSSValueDefault = 'default';
}
