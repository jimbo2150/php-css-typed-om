<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\WTF\wtf\ASCIICType;

use Jimbo2150\PhpCssTypedOm\Parser\CharacterType;
use Jimbo2150\PhpCssTypedOm\WTF\wtf\text\LChar;

use function Jimbo2150\PhpCssTypedOm\Utility\recursive_class_has_use;

$asciiCaseFoldTable = \SplFixedArray::fromArray(
	[
		0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0A, 0x0B, 0x0C, 0x0D, 0x0E,
		0x0F, 0x10, 0x11, 0x12, 0x13, 0x14, 0x15, 0x16, 0x17, 0x18, 0x19, 0x1A, 0x1B, 0x1C, 0x1D,
		0x1E, 0x1F,	0x20, 0x21, 0x22, 0x23, 0x24, 0x25, 0x26, 0x27, 0x28, 0x29, 0x2A, 0x2B, 0x2C,
		0x2D, 0x2E, 0x2F, 0x30, 0x31, 0x32, 0x33, 0x34, 0x35, 0x36, 0x37, 0x38, 0x39, 0x3A, 0x3B,
		0x3C, 0x3D, 0x3E, 0x3F,	0x40, 0x61, 0x62, 0x63, 0x64, 0x65, 0x66, 0x67, 0x68, 0x69, 0x6A,
		0x6B, 0x6C, 0x6D, 0x6E, 0x6F, 0x70, 0x71, 0x72, 0x73, 0x74, 0x75, 0x76, 0x77, 0x78, 0x79,
		0x7A, 0x5B, 0x5C, 0x5D, 0x5E, 0x5F,	0x60, 0x61, 0x62, 0x63, 0x64, 0x65, 0x66, 0x67, 0x68,
		0x69, 0x6A, 0x6B, 0x6C, 0x6D, 0x6E, 0x6F, 0x70, 0x71, 0x72, 0x73, 0x74, 0x75, 0x76, 0x77,
		0x78, 0x79, 0x7A, 0x7B, 0x7C, 0x7D, 0x7E, 0x7F,	0x80, 0x81, 0x82, 0x83, 0x84, 0x85, 0x86,
		0x87, 0x88, 0x89, 0x8A, 0x8B, 0x8C, 0x8D, 0x8E, 0x8F, 0x90, 0x91, 0x92, 0x93, 0x94, 0x95,
		0x96, 0x97, 0x98, 0x99, 0x9A, 0x9B, 0x9C, 0x9D, 0x9E, 0x9F,	0xA0, 0xA1, 0xA2, 0xA3, 0xA4,
		0xA5, 0xA6, 0xA7, 0xA8, 0xA9, 0xAA, 0xAB, 0xAC, 0xAD, 0xAE, 0xAF, 0xB0, 0xB1, 0xB2, 0xB3,
		0xB4, 0xB5, 0xB6, 0xB7, 0xB8, 0xB9, 0xBA, 0xBB, 0xBC, 0xBD, 0xBE, 0xBF,	0xC0, 0xC1, 0xC2,
		0xC3, 0xC4, 0xC5, 0xC6, 0xC7, 0xC8, 0xC9, 0xCA, 0xCB, 0xCC, 0xCD, 0xCE, 0xCF, 0xD0, 0xD1,
		0xD2, 0xD3, 0xD4, 0xD5, 0xD6, 0xD7, 0xD8, 0xD9, 0xDA, 0xDB, 0xDC, 0xDD, 0xDE, 0xDF,	0xE0,
		0xE1, 0xE2, 0xE3, 0xE4, 0xE5, 0xE6, 0xE7, 0xE8, 0xE9, 0xEA, 0xEB, 0xEC, 0xED, 0xEE, 0xEF,
		0xF0, 0xF1, 0xF2, 0xF3, 0xF4, 0xF5, 0xF6, 0xF7, 0xF8, 0xF9, 0xFA, 0xFB, 0xFC, 0xFD, 0xFE,
		0xFF,
	],
	false
);

function isASCII(string $character): bool
{
	return false !== mb_detect_encoding($character, 'ASCII', true);
}

function isASCIILower(CharacterType $character): bool
{
	return ctype_alpha($character);
}

function toASCIILowerUnchecked(CharacterType $character): CharacterType
{
	return $character->toASCIILower();
}

function isASCIIAlpha(CharacterType $character): bool
{
	return isASCIILower(toASCIILowerUnchecked($character));
}

function isASCIIDigit(CharacterType $character): bool
{
	return ctype_digit($character);
}

function isASCIIAlphanumeric(CharacterType $character): bool
{
	return isASCIIDigit($character) || isASCIIAlpha($character);
}

function isASCIIHexDigit(CharacterType $character): bool
{
	return isASCIIDigit($character) ||
		(toASCIILowerUnchecked($character) >= 'a' &&
		toASCIILowerUnchecked($character) <= 'f');
}

function isASCIIBinaryDigit(CharacterType $character): bool
{
	return '0' == $character || '1' == $character;
}

function isASCIIOctalDigit(CharacterType $character): bool
{
	return $character >= '0' && $character <= '7';
}

function isASCIIPrintable(CharacterType $character): bool
{
	return $character >= ' ' && $character <= '~';
}

function isTabOrSpace(CharacterType $character): bool
{
	return ' ' == $character || "\t" == $character;
}

function isASCIIWhitespace(CharacterType $character): bool
{
	return ' ' == $character || "\n" == $character || "\t" == $character || "\r" == $character ||
		"\f" == $character;
}

function isASCIIWhitespaceWithoutFF(CharacterType $character): bool
{
	return ' ' == $character || "\n" == $character || "\t" == $character || "\r" == $character;
}

function isUnicodeCompatibleASCIIWhitespace(CharacterType $character): bool
{
	return isASCIIWhitespace($character) || "\v" == $character;
}

function isASCIIUpper(CharacterType $character): bool
{
	return $character >= 'A' && $character <= 'Z';
}

function isNotASCIIWhitespace(CharacterType $character): bool
{
	return !isASCIIWhitespace($character);
}

function toASCIILower(CharacterType|LChar $character): CharacterType|LChar
{
	global $asciiCaseFoldTable;
	if (recursive_class_has_use($character, CharacterType::class)) {
		return $character->toASCIILower();
	}

	return $asciiCaseFoldTable[$character];
}

function toASCIIUpper(CharacterType $character): CharacterType
{
	return $character->toASCIIUpper();
}

function toASCIIHexValue(
	CharacterType $firstCharacter,
	?CharacterType $secondCharacter = null,
): int {
	if (null === $secondCharacter) {
		return $firstCharacter < 'A' ?
			$firstCharacter - '0' : ($firstCharacter - 'A' + 10) & 0xF;
	}

	return toASCIIHexValue($firstCharacter) << 4 |
		toASCIIHexValue($secondCharacter);
}

function lowerNibbleToASCIIHexDigit(int $value): string
{
	$nibble = $value & 0xF;

	return (string) ($nibble + ($nibble < 10 ? '0' : 'A' - 10));
}

function upperNibbleToASCIIHexDigit(int $value): string
{
	$nibble = $value >> 4;

	return (string) ($nibble + ($nibble < 10 ? '0' : 'A' - 10));
}

function lowerNibbleToLowercaseASCIIHexDigit(int $value): string
{
	$nibble = $value & 0xF;

	return (string) ($nibble + ($nibble < 10 ? '0' : 'a' - 10));
}

function upperNibbleToLowercaseASCIIHexDigit(int $value): string
{
	$nibble = $value >> 4;

	return (string) ($nibble + ($nibble < 10 ? '0' : 'a' - 10));
}

function isASCIIAlphaCaselessEqual(
	CharacterType $inputCharacter,
	CharacterType $expectedASCIILowercaseLetter,
): bool {
	// Name of this argument says this must be a lowercase letter, but it can actually be:
	//   - a lowercase letter
	//   - a numeric digit
	//   - a space
	//   - punctuation in the range 0x21-0x3F, including "-", "/", and "+"
	// It cannot be:
	//   - an uppercase letter
	//   - a non-ASCII character
	//   - other punctuation, such as underscore and backslash
	//   - a control character such as "\n"
	// FIXME: Would be nice to make both the function name and expectedASCIILowercaseLetter argument name clearer.
	assert(
		toASCIILowerUnchecked($expectedASCIILowercaseLetter) ==
			$expectedASCIILowercaseLetter
	);

	return toASCIILowerUnchecked($inputCharacter) == $expectedASCIILowercaseLetter;
}

function isASCIIDigitOrPunctuation(CharacterType $character): bool
{
	return ($character >= '!' && $character <= '@') || ($character >= '[' && $character <= '`') ||
		($character >= '{' && $character <= '~');
}
