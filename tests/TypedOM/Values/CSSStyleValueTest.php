<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;
use PHPUnit\Framework\TestCase;

class CSSStyleValueTest extends TestCase
{
	public function testClone(): void
	{
		$value = new class () extends CSSStyleValue {
			public function __toString(): string
			{
				return 'test';
			}

			public function clone(): static
			{
				return new static();
			}
		};
		$cloned = clone $value;
		$this->assertInstanceOf(CSSStyleValue::class, $cloned);
		$this->assertNotSame($value, $cloned);
	}
}
