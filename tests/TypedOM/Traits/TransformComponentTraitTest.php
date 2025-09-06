<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Traits;

use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\TransformComponentTrait;
use PHPUnit\Framework\TestCase;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;

class TransformComponentTraitTest extends TestCase
{
	use TransformComponentTrait {
	       initializeTransformComponent as public;
	       cloneValues as public;
	       toTransformString as public;
	       validateRequiredValues as public;
	   }

	/** @var \PHPUnit\Framework\MockObject\MockObject&CSSUnitValue */
	   private $cssUnitValueMock;

    private bool $is2D = true;
	   public function getTransformType(): string
	   {
	       return 'test';
	   }

	   protected function setUp(): void
	   {
	       parent::setUp();
	       $this->cssUnitValueMock = $this->createMock(CSSUnitValue::class);
	   }

	   public function testInitializeTransformComponent(): void
	   {
	       $this->initializeTransformComponent(['x' => $this->cssUnitValueMock], false);
	       $this->assertFalse($this->is2D());
	       $this->assertSame(['x' => $this->cssUnitValueMock], $this->getValues());

	       $this->initializeTransformComponent(['y' => $this->cssUnitValueMock]);
	       $this->assertTrue($this->is2D());
	       $this->assertSame(['y' => $this->cssUnitValueMock], $this->getValues());
	   }

	   public function testGetAndSetValues(): void
	   {
	       $this->assertNull($this->getValue('x'));

	       $this->setValue('x', $this->cssUnitValueMock);
	       $this->assertSame($this->cssUnitValueMock, $this->getValue('x'));
	       $this->assertSame(['x' => $this->cssUnitValueMock], $this->getValues());
	   }

	   public function testCloneValues(): void
	   {
	       $clonedMock = $this->createMock(CSSUnitValue::class);
	       $this->cssUnitValueMock->method('clone')->willReturn($clonedMock);

	       $this->setValue('x', $this->cssUnitValueMock);
	       
	       $clonedValues = $this->cloneValues();

	       $this->assertNotSame($this->getValues(), $clonedValues);
	       $this->assertArrayHasKey('x', $clonedValues);
	       $this->assertSame($clonedMock, $clonedValues['x']);
	   }

	   public function testToTransformString(): void
	   {
	       $this->cssUnitValueMock->method('__toString')->willReturn('10px');
	       $this->initializeTransformComponent(['x' => $this->cssUnitValueMock, 'y' => $this->cssUnitValueMock, 'z' => $this->cssUnitValueMock], true);
	       
	       // Test 2D
	       $this->assertSame('test(10px, 10px)', $this->toTransformString('test', ['x', 'y', 'z']));
	       
	       // Test 3D
	       $this->initializeTransformComponent(['x' => $this->cssUnitValueMock, 'y' => $this->cssUnitValueMock, 'z' => $this->cssUnitValueMock], false);
	       $this->assertSame('test(10px, 10px, 10px)', $this->toTransformString('test', ['x', 'y', 'z']));
	   }

	   public function testValidateRequiredValues(): void
	   {
	       $this->initializeTransformComponent(['x' => $this->cssUnitValueMock]);
	       
	       // Should not throw
	       $this->validateRequiredValues(['x']);

	       $this->expectException(\InvalidArgumentException::class);
	       $this->expectExceptionMessage('Required value "y" is missing for test transform');
	       $this->validateRequiredValues(['y']);
	   }
}