<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values\Numeric;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericType;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitTypeEnum;
use PHPUnit\Framework\TestCase;
use ValueError;

class CSSNumericTypeTest extends TestCase
{
	public function test() {
		$instance = new CSSNumericType();
		$instance->__set('length', 'px');
		$this->assertSame('px', $instance->{CSSUnitTypeEnum::from('length')->value});
		$instance->{CSSUnitTypeEnum::from('length')->value};
	}

	public function testInvalidType() {
		$instance = new CSSNumericType();
		$this->expectException(ValueError::class);
		$instance->__set('test', 5);
	}

	public function testInvalidValue() {
		$instance = new CSSNumericType();
		$this->expectException(InvalidArgumentException::class);
		$instance->__set('percentHint', 'f');
	}
}