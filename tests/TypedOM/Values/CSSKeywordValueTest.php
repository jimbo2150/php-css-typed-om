<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSKeywordValue;
use PHPUnit\Framework\TestCase;

class CSSKeywordValueTest extends TestCase
{
	public function testKeyword() {
		$instance = new CSSKeywordValue('test');
		$this->assertSame('test', $instance->getKeyword());
		$this->assertSame('test', (string) $instance);
	}

	public function testClone() {
		$instance = new CSSKeywordValue('test');
		$clone = $instance->clone();
		$this->assertInstanceOf(CSSKeywordValue::class, $clone);
		$this->assertSame('test', $clone->getKeyword());
	}
}