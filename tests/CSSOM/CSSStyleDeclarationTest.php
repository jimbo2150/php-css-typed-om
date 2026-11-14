<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleDeclaration;
use PHPUnit\Framework\TestCase;

final class CSSStyleDeclarationTest extends TestCase
{
	public function testParsesAndMutatesOrderedDeclarations(): void
	{
		$style = CSSStyleDeclaration::parse('color: red; margin: 0 !important; --x: fn(a;b);');

		self::assertSame(3, $style->getLength());
		self::assertSame('red', $style->getPropertyValue('COLOR'));
		self::assertSame('important', $style->getPropertyPriority('margin'));
		self::assertSame('fn(a;b)', $style->getPropertyValue('--x'));

		$style->setProperty('color', 'blue');
		$style->removeProperty('margin');

		self::assertSame('color: blue; --x: fn(a;b);', $style->getCssText());
	}

	public function testRejectsInvalidMutationWithoutChangingDeclarations(): void
	{
		$style = CSSStyleDeclaration::parse('color: red');

		try {
			$style->setProperty('bad property', 'blue');
			self::fail('Expected invalid property name to throw.');
		} catch (InvalidArgumentException) {
			self::assertSame('color: red;', $style->getCssText());
		}
	}

	public function testCssTextReplacementIsAtomic(): void
	{
		$style = CSSStyleDeclaration::parse('color: red');

		try {
			$style->setCssText('color: blue; broken');
			self::fail('Expected malformed declaration text to throw.');
		} catch (InvalidArgumentException) {
			self::assertSame('color: red;', $style->cssText);
		}
	}

	public function testIgnoresCommentsOutsideStrings(): void
	{
		$style = CSSStyleDeclaration::parse('color /* comment */ : red; content: "/* retained */";');

		self::assertSame('red', $style->getPropertyValue('color'));
		self::assertSame('"/* retained */"', $style->getPropertyValue('content'));
	}

	public function testRejectsUnbalancedValuesAndEmbeddedPriorityAtomically(): void
	{
		$style = CSSStyleDeclaration::parse('color: red');
		foreach (['rgb(1, 2, 3', 'blue !important'] as $invalidValue) {
			try {
				$style->setProperty('color', $invalidValue);
				self::fail('Expected invalid CSS value to throw.');
			} catch (InvalidArgumentException) {
				self::assertSame('red', $style->getPropertyValue('color'));
			}
		}
	}

	public function testImportantDuplicateDeclarationWins(): void
	{
		$style = CSSStyleDeclaration::parse('color: red !important; color: blue; color: green !important;');

		self::assertSame('green', $style->getPropertyValue('color'));
		self::assertSame('important', $style->getPropertyPriority('color'));
	}

	public function testStylePropertiesSupportCamelCaseAccessors(): void
	{
		$style = new CSSStyleDeclaration();
		$style->backgroundColor = 'red';
		$style->cssFloat = 'left';

		self::assertSame('red', $style->getPropertyValue('background-color'));
		self::assertSame('left', $style->getPropertyValue('float'));
		self::assertSame('red', $style->backgroundColor);
	}
}
