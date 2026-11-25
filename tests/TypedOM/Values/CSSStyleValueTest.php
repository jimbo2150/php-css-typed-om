<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSColorValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use PHPUnit\Framework\TestCase;

class CSSStyleValueTest extends TestCase
{
	public function testClone(): void
	{
		$value = new class () extends CSSStyleValue {
			public function __toString(): string
			{
				return 'test';
			}

			public function clone(): static
			{
				return new static();
			}
		};
		$cloned = $value->clone();
		self::assertInstanceOf(CSSStyleValue::class, $cloned);
		self::assertNotSame($value, $cloned);
		self::assertSame('test', (string) $cloned);
	}

	public function testTwoArgParseEntryPointsStrictlyValidatePropertyName(): void
	{
		$tight = new CSSParserOptions(maxBytes: 8);
		foreach ([
			static fn () => CSSStyleValue::parse('color;evil', 'red'),
			static fn () => CSSStyleValue::parse("color\x0B", 'red'),
			static fn () => CSSStyleValue::parseAll('color;evil', 'red'),
			static fn () => CSSColorValue::parse('color;evil', '#ff0000'),
			static fn () => CSSNumericValue::parse('width;evil', '10px'),
		] as $call) {
			try {
				$call();
				self::fail('Expected invalid property name to throw InvalidArgumentException.');
			} catch (InvalidArgumentException $e) {
				self::assertStringContainsString('property name', strtolower($e->getMessage()));
			}
		}

		foreach ([
			static fn () => CSSStyleValue::parse('very-long-property-name', '10px', $tight),
			static fn () => CSSStyleValue::parseAll('very-long-property-name', '10px', $tight),
			static fn () => CSSColorValue::parse('very-long-property-name', '#fff', $tight),
			static fn () => CSSNumericValue::parse('very-long-property-name', '10px', $tight),
		] as $limitCall) {
			try {
				$limitCall();
				self::fail('Expected oversized property name to throw CSSParserLimitException.');
			} catch (CSSParserLimitException $e) {
				self::assertStringContainsString('maximum size', strtolower($e->getMessage()));
			}
		}
	}
}
