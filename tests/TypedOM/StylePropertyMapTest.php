<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM;

use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleDeclaration;
use Jimbo2150\PhpCssTypedOm\TypedOM\StylePropertyMap;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSKeywordValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;
use PHPUnit\Framework\TestCase;

final class StylePropertyMapTest extends TestCase
{
	public function testTypedGetSetAppendAndDelete(): void
	{
		$map = new StylePropertyMap(CSSStyleDeclaration::parse('display: block'));
		$map->set('width', new CSSUnitValue(10, 'px'));
		$map->append('display', new CSSKeywordValue('flex'));

		self::assertInstanceOf(CSSUnitValue::class, $map->get('width'));
		self::assertSame('block', (string) $map->get('display'));
		self::assertCount(2, $map->getAll('display'));
		self::assertCount(2, $map);

		$map->delete('width');
		self::assertFalse($map->has('width'));
	}

	public function testCustomPropertyAcceptsEmptyValueWhileStandardPropertyAndAppendRejectIt(): void
	{
		$map = new StylePropertyMap(new \Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleDeclaration());
		$map->set('--empty', '');
		$map->set('--empty2', new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnparsedValue([]));
		self::assertInstanceOf(\Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnparsedValue::class, $map->get('--empty'));
		self::assertSame('', (string) $map->get('--empty'));
		self::assertSame('', (string) $map->get('--empty2'));

		foreach ([
			static fn () => $map->set('color', ''),
			static fn () => $map->append('--empty', ''),
		] as $badCall) {
			try {
				$badCall();
				self::fail('Expected empty value to throw InvalidArgumentException.');
			} catch (\InvalidArgumentException) {
				self::assertTrue(true);
			}
		}
	}
}
