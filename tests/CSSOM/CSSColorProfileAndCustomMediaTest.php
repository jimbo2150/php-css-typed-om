<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSColorProfileRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSCustomMediaRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSImportRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderer;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderMode;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderOptions;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleSheet;
use Jimbo2150\PhpCssTypedOm\CSSOM\MediaList;
use PHPUnit\Framework\TestCase;

final class CSSColorProfileAndCustomMediaTest extends TestCase
{
	public function testParsesAndSerializesColorProfileRule(): void
	{
		$css = <<<'CSS'
@color-profile --swop5 {
  src: url("SWOP2006_Coated5v2.icc");
  rendering-intent: relative-colorimetric;
  components: cyan, magenta, yellow, black;
}
@color-profile device-cmyk {
  src: url("cmyk.icc");
  rendering-intent: Perceptual;
}
CSS;
		$sheet = CSSStyleSheet::parse($css);
		self::assertSame([], $sheet->getParseErrors());
		self::assertCount(2, $sheet->cssRules);

		$profile1 = $sheet->cssRules[0];
		self::assertInstanceOf(CSSColorProfileRule::class, $profile1);
		self::assertSame(CSSRule::COLOR_PROFILE_RULE, $profile1->type);
		self::assertSame('--swop5', $profile1->name);
		self::assertSame('url("SWOP2006_Coated5v2.icc")', $profile1->src);
		self::assertSame('relative-colorimetric', $profile1->renderingIntent);
		self::assertSame('cyan, magenta, yellow, black', $profile1->components);

		$profile2 = $sheet->cssRules[1];
		self::assertInstanceOf(CSSColorProfileRule::class, $profile2);
		self::assertSame('device-cmyk', $profile2->name);
		self::assertSame('perceptual', $profile2->renderingIntent);

		$reparsed = CSSStyleSheet::parse(CSSRenderer::render($sheet, new CSSRenderOptions(CSSRenderMode::Minified)));
		self::assertSame([], $reparsed->getParseErrors());
		self::assertSame($sheet->getCssText(), $reparsed->getCssText());
	}

	public function testRejectsInvalidColorProfileNames(): void
	{
		foreach (['', 'srgb', 'display-p3', '--', '/* c */', '--a --b'] as $badName) {
			try {
				new CSSColorProfileRule($badName, new \Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleDeclaration());
				self::fail('Expected invalid @color-profile name to throw: ' . $badName);
			} catch (InvalidArgumentException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}
	}

	public function testParsesMutatesAndClonesCustomMediaRule(): void
	{
		$css = <<<'CSS'
@custom-media --narrow (400px <= width <= 800px);
@custom-media --always TRUE;
@custom-media --never false;
@media (--narrow) { .box { color: red; } }
CSS;
		$sheet = CSSStyleSheet::parse($css);
		self::assertSame([], $sheet->getParseErrors());
		self::assertCount(4, $sheet->cssRules);

		$r0 = $sheet->cssRules[0];
		self::assertInstanceOf(CSSCustomMediaRule::class, $r0);
		self::assertSame(CSSRule::CUSTOM_MEDIA_RULE, $r0->type);
		self::assertSame('--narrow', $r0->name);
		self::assertInstanceOf(MediaList::class, $r0->query);
		self::assertSame('(400px <= width <= 800px)', $r0->query->mediaText);

		$r1 = $sheet->cssRules[1];
		self::assertInstanceOf(CSSCustomMediaRule::class, $r1);
		self::assertTrue($r1->query);
		self::assertSame('@custom-media --always true;', $r1->cssText);

		$r2 = $sheet->cssRules[2];
		self::assertInstanceOf(CSSCustomMediaRule::class, $r2);
		self::assertFalse($r2->query);
		self::assertSame('@custom-media --never false;', $r2->cssText);

		// Live MediaList mutation updates prelude and clone stays independent
		$cloned = clone $r0;
		$r0->query->appendMedium('print');
		self::assertSame('@custom-media --narrow (400px <= width <= 800px), print;', $r0->cssText);
		self::assertSame('@custom-media --narrow (400px <= width <= 800px);', $cloned->cssText);

		// Clearing all media queries from @custom-media is rejected atomically
		try {
			$cloned->query->setMediaText('');
			self::fail('Expected clearing @custom-media query list to throw.');
		} catch (InvalidArgumentException $exception) {
			self::assertNotSame('', $exception->getMessage());
		}
		self::assertSame('@custom-media --narrow (400px <= width <= 800px);', $cloned->getCssText());
	}

	public function testRejectsMalformedCustomMediaPreludes(): void
	{
		foreach (['--a', 'not-dashed true', '--a ()', '--a true extra', '--a (a) and (b) or (c)'] as $bad) {
			try {
				new CSSCustomMediaRule($bad);
				self::fail('Expected invalid @custom-media prelude to throw: ' . $bad);
			} catch (InvalidArgumentException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}
	}

	public function testImportRuleSyncsCanonicalAndBrowserRecoveredMediaPrelude(): void
	{
		$canonicalImport = new CSSImportRule('url("a.css") SCREEN, PRINT');
		self::assertSame('screen, print', $canonicalImport->mediaText);
		self::assertSame('@import url("a.css") screen, print;', $canonicalImport->cssText);

		$recoveredSheet = CSSStyleSheet::parse(
			'@import url("a.css") screen, (bad) );',
			new CSSParserOptions(browserErrorRecovery: true),
		);
		self::assertSame([], $recoveredSheet->getParseErrors());
		self::assertCount(1, $recoveredSheet->cssRules);
		$recovered = $recoveredSheet->cssRules[0];
		self::assertInstanceOf(CSSImportRule::class, $recovered);
		self::assertSame('screen, not all', $recovered->mediaText);
		self::assertSame('@import url("a.css") screen, not all;', $recovered->cssText);
	}
}
