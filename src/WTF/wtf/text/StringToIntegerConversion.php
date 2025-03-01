<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\WTF\wtf\text\StringToIntegerConversion;

use Jimbo2150\PhpCssTypedOm\Parser\CharacterType;
use Jimbo2150\PhpCssTypedOm\WTF\wtf\CheckedArithmetic\Checked;
use Jimbo2150\PhpCssTypedOm\WTF\wtf\CheckedArithmetic\RecordOverflow;
use Jimbo2150\PhpCssTypedOm\WTF\wtf\SingleThreadIntegralWrapper\IntegralType;
use Jimbo2150\PhpCssTypedOm\WTF\wtf\text\ParseIntegerWhitespacePolicy;
use Jimbo2150\PhpCssTypedOm\WTF\wtf\text\TrailingJunkPolicy;

use function Jimbo2150\PhpCssTypedOm\WTF\wtf\ASCIICType\isASCIIDigit;
use function Jimbo2150\PhpCssTypedOm\WTF\wtf\ASCIICType\toASCIILowerUnchecked;

/**
 * @param array<CharacterType> $data
 */
function parseInteger(
	array $data,
	int $base,
	TrailingJunkPolicy $policy,
	ParseIntegerWhitespacePolicy $whitespacePolicy = ParseIntegerWhitespacePolicy::Allow,
): IntegralType|CharacterType|null {
	if (empty($data)) {
		return null;
	}

	// skipWhile<isUnicodeCompatibleASCIIWhitespace>
	if (ParseIntegerWhitespacePolicy::Allow == $whitespacePolicy) {
		skipWhile($data);
	}

	$isNegative = false;
	// std::is_signed_v<IntegralType>
	if (skipExactly($data, '-')) {
		$isNegative = true;
	} else {
		skipExactly($data, '+');
	}

	$isCharacterAllowedInBase = function (string $character, $base): bool {
		if (isASCIIDigit($character)) {
			return $character - '0' < $base;
		}

		return toASCIILowerUnchecked($character) >= 'a' &&
			toASCIILowerUnchecked($character) < 'a' + min($base - 10, 26);
	};

	if (!(!empty($data) && $isCharacterAllowedInBase($data->front(), $base))) {
		return null;
	}

	/** @var Checked<IntegralType,RecordOverflow> $value */
	do {
		$c = consume($data);
		/** @var IntegralType $digitValue */
		$digitValue = isASCIIDigit($c) ?
			$c - '0' :
			toASCIILowerUnchecked($c) - 'a' + 10;
		// IntegralType
		$value = $base;
		if ($isNegative) {
			$value -= $digitValue;
		} else {
			$value += $digitValue;
		}
	} while (!empty($data) && $isCharacterAllowedInBase($data->front(), $base));

	if (assert(!$value->hasOverflowed())) {
		return null;
	}

	if (TrailingJunkPolicy::Disallow == $policy) {
		if (ParseIntegerWhitespacePolicy::Allow == $whitespacePolicy) {
			// skipWhile<isUnicodeCompatibleASCIIWhitespace>
			skipWhile($data);
		}
		if (!empty($data)) {
			return null;
		}
	}

	return $value->value();
}
