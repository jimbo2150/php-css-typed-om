<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSMatrixComponent;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnitValue;
use Jimbo2150\PhpCssTypedOm\DOM\DOMMatrix;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CSSMatrixComponent class.
 */
class CSSMatrixComponentTest extends TestCase
{
    public function testConstructor()
    {
        $matrix = new DOMMatrix([1, 0, 0, 1, 0, 0]);
        
        $component = new CSSMatrixComponent($matrix);
        $this->assertInstanceOf(CSSMatrixComponent::class, $component);
    }

    public function testGetMatrix()
    {
        $matrix = new DOMMatrix([1, 0, 0, 1, 0, 0]);
        
        $component = new CSSMatrixComponent($matrix);
        $this->assertSame($matrix, $component->getMatrix());
    }

    public function testToString()
    {
        $matrix = new DOMMatrix([1, 0, 0, 1, 0, 0]);
        
        $component = new CSSMatrixComponent($matrix);
        $this->assertSame('matrix(1, 0, 0, 1, 0, 0)', $component->toString());
    }

    public function testIsValid()
    {
        $matrix = new DOMMatrix([1, 0, 0, 1, 0, 0]);
        
        $component = new CSSMatrixComponent($matrix);
        $this->assertTrue($component->isValid());
    }

    public function testClone()
    {
        $matrix = new DOMMatrix([1, 0, 0, 1, 0, 0]);
        
        $component = new CSSMatrixComponent($matrix);
        $cloned = $component->clone();
        
        $this->assertInstanceOf(CSSMatrixComponent::class, $cloned);
        $this->assertNotSame($component, $cloned);
    }

    public function testToUnit()
    {
        $matrix = new DOMMatrix([1, 0, 0, 1, 0, 0]);
        
        $component = new CSSMatrixComponent($matrix);
        $result = $component->to('');
        
        $this->assertNull($result);
    }

    public function testIdentityMatrix()
    {
        $matrix = new DOMMatrix([1, 0, 0, 1, 0, 0]);
        
        $component = new CSSMatrixComponent($matrix);
        $this->assertSame('matrix(1, 0, 0, 1, 0, 0)', $component->toString());
    }

    public function testTranslationMatrix()
    {
        $matrix = new DOMMatrix([1, 0, 0, 1, 10, 20]);
        
        $component = new CSSMatrixComponent($matrix);
        $this->assertSame('matrix(1, 0, 0, 1, 10, 20)', $component->toString());
    }

    public function testScaleMatrix()
    {
        $matrix = new DOMMatrix([2, 0, 0, 2, 0, 0]);
        
        $component = new CSSMatrixComponent($matrix);
        $this->assertSame('matrix(2, 0, 0, 2, 0, 0)', $component->toString());
    }

    public function testRotationMatrix()
    {
        $angle = pi() / 4; // 45 degrees
        $cos = cos($angle);
        $sin = sin($angle);
        
        $matrix = new DOMMatrix([$cos, $sin, -$sin, $cos, 0, 0]);
        
        $component = new CSSMatrixComponent($matrix);
        $this->assertSame('matrix(' . $cos . ', ' . $sin . ', ' . -$sin . ', ' . $cos . ', 0, 0)', $component->toString());
    }

    public function testSkewMatrix()
    {
        $matrix = new DOMMatrix([1, 0.5, 0.5, 1, 0, 0]);
        
        $component = new CSSMatrixComponent($matrix);
        $this->assertSame('matrix(1, 0.5, 0.5, 1, 0, 0)', $component->toString());
    }

    public function testComplexMatrix()
    {
        $matrix = new DOMMatrix([1.5, 0.3, -0.2, 1.8, 10, -5]);
        
        $component = new CSSMatrixComponent($matrix);
        $this->assertSame('matrix(1.5, 0.3, -0.2, 1.8, 10, -5)', $component->toString());
    }
}
