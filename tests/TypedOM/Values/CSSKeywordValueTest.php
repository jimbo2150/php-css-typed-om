<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSKeywordValue;
use PHPUnit\Framework\TestCase;

class CSSKeywordValueTest extends TestCase
{
	public function testKeyword(): void {
		$instance = new CSSKeywordValue('test');
		$this->assertSame('test', $instance->getKeyword());
		$this->assertSame('test', (string) $instance);
	}

	public function testClone(): void {
		$instance = new CSSKeywordValue('test');
		$clone = $instance->clone();
		$this->assertInstanceOf(CSSKeywordValue::class, $clone);
		$this->assertNotSame($instance, $clone);
		$this->assertSame('test', $clone->getKeyword());
	}

	public function testValidationAndBareHexEscape(): void {
		$escaped = new CSSKeywordValue('foo\\61');
		$this->assertSame('foo\\61', (string) $escaped);

		$this->expectException(\InvalidArgumentException::class);
		new CSSKeywordValue('');
	}

	public function testRejectsControlCharactersAndOversizedKeyword(): void {
		try {
			new CSSKeywordValue("auto\x00");
			$this->fail('Expected control character in keyword to fail.');
		} catch (\InvalidArgumentException $e) {
			$this->assertNotSame('', $e->getMessage());
		}

		$this->expectException(\Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException::class);
		new CSSKeywordValue(str_repeat('a', \Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions::DEFAULT_MAX_BYTES + 1));
	}
}
