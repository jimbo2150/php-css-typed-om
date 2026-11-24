<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Traits;

use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\TypeableUnitTrait;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitEnum;
use LogicException;
use PHPUnit\Framework\TestCase;

class TypeableUnitTraitTest extends TestCase
{
	use TypeableUnitTrait {
		setUnit as public;
	}

	public function testSetUnitWithString(): void
	{
		$this->setUnit('px');
		$this->assertSame('px', $this->unit);
	}

	public function testUnitNamesAreCaseInsensitive(): void
	{
		$this->setUnit('PX');
		$this->assertSame('px', $this->unit);
		$this->setUnit('q');
		$this->assertSame('Q', $this->unit);
	}

	public function testSetUnitWithEnum(): void
	{
		$this->setUnit(CSSUnitEnum::LENGTH_px);
		$this->assertSame('px', $this->unit);
	}

	public function testType(): void
	{
		$this->setUnit('px');
		$this->assertSame('length', $this->type());

		$this->setUnit('deg');
		$this->assertSame('angle', $this->type());

		$this->setUnit('s');
		$this->assertSame('time', $this->type());

		$this->setUnit('hz');
		$this->assertSame('frequency', $this->type());

		$this->setUnit('dpi');
		$this->assertSame('resolution', $this->type());

		$this->setUnit(CSSUnitEnum::PERCENT);
		$this->assertSame('percent', $this->type());

		$this->unitObj = null;
		$this->expectException(LogicException::class);
		$this->type();
	}

	public function testGetUnit(): void
	{
		$this->assertSame('', $this->unit);

		$this->setUnit(CSSUnitEnum::LENGTH_px);
		$this->assertSame('px', $this->unit);
	}

	public function testUnsetType(): void
	{
		$this->expectException(LogicException::class);
		$this->expectExceptionMessage('No unit set.');
		$this->type();
	}
}
