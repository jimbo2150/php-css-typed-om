<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Parser;

function isCSSSpace(CharacterType $c): bool
{
	return ' ' == $c || "\t" == $c || "\n" == $c;
}

function isNameStartCodePoint(CharacterType $c): bool
{
	return isASCIIAlpha($c) || '_' == $c || !isASCII($c);
}

function isNameCodePoint(CharacterType $c): bool
{
	return isNameStartCodePoint($c) || isASCIIDigit($c) || '-' == $c;
}

function isCSSWideKeyword(CSSValueID $valueID): bool
{
	switch ($valueID) {
		case CSSValueInitial:
		case CSSValueInherit:
		case CSSValueUnset:
		case CSSValueRevert:
		case CSSValueRevertLayer:
			return true;
		default:
			return false;
	}
}

function isValidCustomIdentifier(CSSValueID $valueID): bool
{
	// "default" is obsolete as a CSS-wide keyword but is still not allowed as a custom identifier.
	return !isCSSWideKeyword($valueID) && CSSValueDefault != $valueID;
}
