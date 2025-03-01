<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\WTF\wtf\CheckedArithmetic;

class RecordOverflow
{
	private bool $hasOverflowed = false;

	public function hasOverflowed(): bool
	{
		return $this->hasOverflowed;
	}

	public function setOverflowed(): void
	{
		$this->hasOverflowed = true;
	}
}
