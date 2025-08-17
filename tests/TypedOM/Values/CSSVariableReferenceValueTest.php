<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnparsedValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSVariableReferenceValue;
use PHPUnit\Framework\TestCase;

class CSSVariableReferenceValueTest extends TestCase
{
	public function testToString()
	{
		$varRef = new CSSVariableReferenceValue('--my-var');
		$this->assertEquals('var(--my-var)', $varRef->toString());
	}

	public function testToStringWithFallback()
	{
		$fallback = new CSSUnparsedValue(['red']);
		$varRef = new CSSVariableReferenceValue('--my-var', $fallback);
		$this->assertEquals('var(--my-var, red)', $varRef->toString());
	}

	public function testIsValid()
	{
		$varRef = new CSSVariableReferenceValue('--my-var');
		$this->assertTrue($varRef->isValid());

		$invalidVarRef = new CSSVariableReferenceValue('my-var');
		$this->assertFalse($invalidVarRef->isValid());
	}
}
