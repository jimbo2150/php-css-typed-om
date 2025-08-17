<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnparsedValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSVariableReferenceValue;
use PHPUnit\Framework\TestCase;

class CSSUnparsedValueTest extends TestCase
{
	public function testToStringWithStrings()
	{
		$unparsed = new CSSUnparsedValue(['10px', ' ', 'solid', ' ', 'red']);
		$this->assertEquals('10px solid red', $unparsed->toString());
	}

	public function testToStringWithVariableReference()
	{
		$unparsed = new CSSUnparsedValue([
			'1px solid ',
			new CSSVariableReferenceValue('--my-color'),
		]);
		$this->assertEquals('1px solid var(--my-color)', $unparsed->toString());
	}

	public function testEntries()
	{
		$unparsed = new CSSUnparsedValue(['10px', ' ', 'solid']);
		$entries = [];
		foreach ($unparsed->entries() as $entry) {
			$entries[] = $entry;
		}
		$this->assertEquals([[0, '10px'], [1, ' '], [2, 'solid']], $entries);

		$unparsedWithVar = new CSSUnparsedValue(['var(', '--my-color', ')']);
		$entriesWithVar = [];
		foreach ($unparsedWithVar->entries() as $entry) {
			$entriesWithVar[] = $entry;
		}
		$this->assertEquals([[0, 'var('], [1, '--my-color'], [2, ')']], $entriesWithVar);
	}

	public function testForEach()
	{
		$unparsed = new CSSUnparsedValue(['10px', ' ', 'solid']);
		$collected = [];
		$unparsed->forEach(function ($value, $key, $obj) use (&$collected, $unparsed) {
			$collected[] = ['value' => $value, 'key' => $key];
			$this->assertSame($unparsed, $obj);
		});
		$this->assertEquals([
			['value' => '10px', 'key' => 0],
			['value' => ' ', 'key' => 1],
			['value' => 'solid', 'key' => 2],
		], $collected);
	}

	public function testKeys()
	{
		$unparsed = new CSSUnparsedValue(['10px', ' ', 'solid']);
		$keys = [];
		foreach ($unparsed->keys() as $key) {
			$keys[] = $key;
		}
		$this->assertEquals([0, 1, 2], $keys);

		$unparsedEmpty = new CSSUnparsedValue([]);
		$keysEmpty = [];
		foreach ($unparsedEmpty->keys() as $key) {
			$keysEmpty[] = $key;
		}
		$this->assertEquals([], $keysEmpty);
	}
}
