<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Traits;

use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\TransformComponentTrait;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class TransformComponentTraitTest extends TestCase
{
	use TransformComponentTrait {
		initializeTransformComponent as public;
		cloneValues as public;
		toTransformString as public;
		validateRequiredValues as public;
	}

	/** @var Stub&CSSUnitValue */
	private Stub $cssUnitValueStub;

	private bool $is2D = true;

	public function getTransformType(): string
	{
		return 'test';
	}

	protected function setUp(): void
	{
		parent::setUp();
		$this->cssUnitValueStub = $this->createStub(CSSUnitValue::class);
	}

	public function testInitializeTransformComponent(): void
	{
		$this->initializeTransformComponent(['x' => $this->cssUnitValueStub], false);
		$this->assertFalse($this->is2D());
		$this->assertSame(['x' => $this->cssUnitValueStub], $this->getValues());

		$this->initializeTransformComponent(['y' => $this->cssUnitValueStub]);
		$this->assertTrue($this->is2D());
		$this->assertSame(['y' => $this->cssUnitValueStub], $this->getValues());
	}

	public function testGetAndSetValues(): void
	{
		$this->assertNull($this->getValue('x'));

		$this->setValue('x', $this->cssUnitValueStub);
		$this->assertSame($this->cssUnitValueStub, $this->getValue('x'));
		$this->assertSame(['x' => $this->cssUnitValueStub], $this->getValues());
	}

	public function testCloneValues(): void
	{
		$clonedStub = $this->createStub(CSSUnitValue::class);
		$this->cssUnitValueStub->method('clone')->willReturn($clonedStub);

		$this->setValue('x', $this->cssUnitValueStub);

		$clonedValues = $this->cloneValues();

		$this->assertNotSame($this->getValues(), $clonedValues);
		$this->assertArrayHasKey('x', $clonedValues);
		$this->assertSame($clonedStub, $clonedValues['x']);
	}

	public function testToTransformString(): void
	{
		$this->cssUnitValueStub->method('__toString')->willReturn('10px');
		$this->initializeTransformComponent(['x' => $this->cssUnitValueStub, 'y' => $this->cssUnitValueStub, 'z' => $this->cssUnitValueStub], true);

		// Test 2D
		$this->assertSame('test(10px, 10px)', $this->toTransformString('test', ['x', 'y', 'z']));

		// Test 3D
		$this->initializeTransformComponent(['x' => $this->cssUnitValueStub, 'y' => $this->cssUnitValueStub, 'z' => $this->cssUnitValueStub], false);
		$this->assertSame('test(10px, 10px, 10px)', $this->toTransformString('test', ['x', 'y', 'z']));
	}

	public function testValidateRequiredValues(): void
	{
		$this->initializeTransformComponent(['x' => $this->cssUnitValueStub]);

		// Should not throw
		$this->validateRequiredValues(['x']);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Required value "y" is missing for test transform');
		$this->validateRequiredValues(['y']);
	}
}
