<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Traits;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericArray;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;

/**
 * Trait for array-backed numeric collection classes (`CSSNumericArray`).
 */
trait MultiValueArrayTrait
{
	/** @var list<CSSNumericValue> */
	public protected(set) array $values = [];

	/**
	 * @param CSSNumericValue|CSSNumericArray|list<CSSNumericValue> $value The value to initialize with
	 */
	public function __construct(CSSNumericValue|CSSNumericArray|array $value)
	{
		if ($value instanceof CSSNumericArray) {
			$this->values = $value->values;
		} elseif ($value instanceof CSSNumericValue) {
			$this->values = [$value];
		} else {
			if (count($value) > CSSNumericValue::MAX_MATH_NODES) {
				throw new CSSParserLimitException(sprintf('Maximum CSS numeric array node count of %d exceeded.', CSSNumericValue::MAX_MATH_NODES));
			}
			$values = [];
			foreach ($value as $entry) {
				if (!$entry instanceof CSSNumericValue) {
					throw new InvalidArgumentException('All values must be of type ' . CSSNumericValue::class);
				}
				$values[] = $entry;
			}
			$this->values = $values;
		}
	}

	public function __toString(): string
	{
		if ($this->values === []) {
			return '';
		}

		$parts = [];
		$totalBytes = 0;
		$maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES;
		foreach ($this->values as $i => $value) {
			$part = (string) $value;
			$totalBytes += strlen($part) + ($i > 0 ? 2 : 0);
			if ($totalBytes > $maxBytes) {
				throw new CSSParserLimitException(sprintf('CSS numeric array serialization exceeds maximum size of %d bytes.', $maxBytes));
			}
			$parts[] = $part;
		}

		return implode(', ', $parts);
	}
}
