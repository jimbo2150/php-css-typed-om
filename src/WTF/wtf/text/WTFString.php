<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\WTF\wtf\text\WTFString;

use Jimbo2150\PhpCssTypedOm\WTF\wtf\text\TrailingJunkPolicy;

use function Jimbo2150\PhpCssTypedOm\WTF\wtf\ASCIICType\isUnicodeCompatibleASCIIWhitespace;
use function Jimbo2150\PhpCssTypedOm\WTF\wtf\dtoa\parseDouble;

function toDoubleType(
	array $data,
	bool &$ok,
	int &$parsedLength,
	?TrailingJunkPolicy $policy = null,
): float {
	$policy = $policy ?: TrailingJunkPolicy::default();
	$leadingSpacesLength = 0;
	while (
		$leadingSpacesLength < $data->size() &&
		isUnicodeCompatibleASCIIWhitespace($data[$leadingSpacesLength])
	) {
		++$leadingSpacesLength;
	}

	$number = parseDouble(
		$data->subspan($leadingSpacesLength),
		$parsedLength
	);
	if (!$parsedLength) {
		if ($ok) {
			$ok = false;
		}

		return 0.0;
	}

	$parsedLength += $leadingSpacesLength;
	if ($ok) {
		$ok = TrailingJunkPolicy::Allow == $policy || $parsedLength == count($data);
	}

	return $number;
}

// std::span<const LChar>
function charactersToDouble(array $data, bool &$ok): float
{
	$parsedLength = 0;

	// toDoubleType<LChar, TrailingJunkPolicy::Disallow>(data, ok, parsedLength)
	return toDoubleType($data, $ok, $parsedLength);
}
