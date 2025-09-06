<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;
use PHPUnit\Framework\TestCase;

class CSSStyleValueTest extends TestCase
{
	public function testClone() {
		$mockClone = clone ($this->getMockBuilder(CSSStyleValue::class)->getMock());
		$this->assertInstanceOf(CSSStyleValue::class, $mockClone);
	}
}