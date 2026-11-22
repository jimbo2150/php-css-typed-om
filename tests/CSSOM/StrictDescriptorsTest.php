<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSFontFaceDescriptors;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSFontFaceRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSMarginRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSPageDescriptors;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSPageRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSPositionTryDescriptors;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSPositionTryRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleProperties;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleSheet;
use PHPUnit\Framework\TestCase;

final class StrictDescriptorsTest extends TestCase
{
	public function testFontFaceDescriptorsEnforceWhitelistAndNoImportant(): void
	{
		$descriptors = new CSSFontFaceDescriptors('font-family: "Inter"; src: url("inter.woff2"); font-display: swap;');
		self::assertSame('"Inter"', $descriptors->fontFamily);
		self::assertSame('swap', $descriptors->fontDisplay);
		self::assertNotNull(CSSFontFaceDescriptors::getAllowedDescriptors());

		// Direct constructor, setCssText, setProperty (even with empty value), custom property, and !important all reject
		foreach ([
			static fn () => new CSSFontFaceDescriptors('color: red;'),
			static fn () => $descriptors->setCssText('color: red;'),
			static fn () => $descriptors->setProperty('color', 'red'),
			static fn () => $descriptors->setProperty('color', ''),
			static fn () => $descriptors->setProperty('--custom', '1'),
			static fn () => $descriptors->setProperty('font-display', 'swap', 'important'),
		] as $attempt) {
			try {
				$attempt();
				self::fail('Expected disallowed descriptor or !important to throw.');
			} catch (InvalidArgumentException) {
				self::assertSame('swap', $descriptors->fontDisplay);
			}
		}

		// Non-mutating reads remain non-throwing
		self::assertSame('', $descriptors->getPropertyValue('color'));
		self::assertFalse(isset($descriptors['color']));
		self::assertSame('', $descriptors->removeProperty('color'));
	}

	public function testPositionTryDescriptorsEnforceWhitelistAndNoImportant(): void
	{
		$descriptors = new CSSPositionTryDescriptors('position-area: top; inset-block-start: 10px; width: 50%;');
		self::assertSame('top', $descriptors->positionArea);
		self::assertSame('10px', $descriptors->insetBlockStart);

		try {
			$descriptors->setProperty('color', 'red');
			self::fail('Expected disallowed descriptor in CSSPositionTryDescriptors to throw.');
		} catch (InvalidArgumentException) {
			self::assertSame('top', $descriptors->positionArea);
		}

		try {
			$descriptors->setProperty('width', '50%', 'important');
			self::fail('Expected !important in CSSPositionTryDescriptors to throw.');
		} catch (InvalidArgumentException) {
			self::assertSame('50%', $descriptors->width);
		}
	}

	public function testPageDescriptorsEnforceWhitelistWhileAllowingCustomProperties(): void
	{
		$descriptors = new CSSPageDescriptors('size: A4; margin: 2cm; --page-accent: navy;');
		self::assertSame('A4', $descriptors->size);
		self::assertSame('navy', $descriptors->getPropertyValue('--page-accent'));

		$this->expectException(InvalidArgumentException::class);
		$descriptors->setProperty('display', 'flex');
	}

	public function testStylesheetParserRecoversGracefullyFromDisallowedDescriptors(): void
	{
		$source = <<<'CSS'
@font-face { font-family: "Demo"; color: red; src: url(demo.woff2); font-display: swap !important; }
@page :first { size: A4; display: grid; @top-left { content: "Header"; } position: fixed; margin: 1cm; }
@position-try --flip { position-area: bottom; color: blue; width: 100px !important; inset-inline-start: 0; }
CSS;
		$sheet = CSSStyleSheet::parse($source);
		$errors = $sheet->getParseErrors();

		self::assertCount(3, $sheet->cssRules);
		self::assertInstanceOf(CSSFontFaceRule::class, $sheet->cssRules[0]);
		self::assertSame('"Demo"', $sheet->cssRules[0]->style->fontFamily);
		self::assertSame('url(demo.woff2)', $sheet->cssRules[0]->style->src);
		self::assertSame('', $sheet->cssRules[0]->style->getPropertyValue('color'));
		self::assertSame('', $sheet->cssRules[0]->style->getPropertyValue('font-display'));

		self::assertInstanceOf(CSSPageRule::class, $sheet->cssRules[1]);
		self::assertSame('A4', $sheet->cssRules[1]->style->size);
		self::assertSame('1cm', $sheet->cssRules[1]->style->margin);
		self::assertSame('', $sheet->cssRules[1]->style->getPropertyValue('display'));
		self::assertSame('', $sheet->cssRules[1]->style->getPropertyValue('position'));
		self::assertCount(1, $sheet->cssRules[1]->getMarginRules());
		self::assertInstanceOf(CSSMarginRule::class, $sheet->cssRules[1]->getMarginRules()[0]);

		self::assertInstanceOf(CSSPositionTryRule::class, $sheet->cssRules[2]);
		self::assertSame('bottom', $sheet->cssRules[2]->style->positionArea);
		self::assertSame('0', $sheet->cssRules[2]->style->insetInlineStart);
		self::assertSame('', $sheet->cssRules[2]->style->getPropertyValue('color'));
		self::assertSame('', $sheet->cssRules[2]->style->getPropertyValue('width'));

		// Exact diagnostics recorded for every disallowed descriptor / !important
		self::assertCount(6, $errors);
		self::assertSame(strpos($source, 'color: red'), $errors[0]['offset']);
		self::assertSame(strpos($source, 'display: grid'), $errors[2]['offset']);
		self::assertSame(strpos($source, 'position: fixed'), $errors[3]['offset']);
	}

	public function testStylePropertiesCssFloatAccessor(): void
	{
		$props = new CSSStyleProperties();
		$props->setCssFloat('left');
		self::assertSame('left', $props->getCssFloat());
		self::assertSame('left', $props->cssFloat);
		self::assertSame('left', $props->getPropertyValue('float'));
	}
}
