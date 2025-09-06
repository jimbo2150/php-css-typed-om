<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values\Numeric;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitTypeEnum;
use PHPUnit\Framework\TestCase;
use ValueError;

class CSSUnitTypeEnumTest extends TestCase
{
	public function test() {
		$instance = CSSUnitTypeEnum::FLEX;
		$this->assertSame('string', $instance->verifyValue('test'));

		$instance = CSSUnitTypeEnum::from('percentHint');
		$this->assertInstanceOf(
			InvalidArgumentException::class,
			$instance->verifyValue('test')
		);

		$instance = CSSUnitTypeEnum::from('percentHint');
		$this->assertSame(
			'int',
			$instance->verifyValue(2)
		);
	}
}