<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\TypedOM\CSSContext;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSRGB;
use PHPUnit\Framework\TestCase;

class CSSContextTest extends TestCase
{
	public function testConstructorWithDefaultValues(): void
	{
		$context = new CSSContext();
		$this->assertEquals(16.0, $context->getFontSize());
		$this->assertEquals(960.0, $context->getViewportWidth());
		$this->assertEquals(540.0, $context->getViewportHeight());
	}

	public function testConstructorWithCustomValues(): void
	{
		$context = new CSSContext(20.0, 1280.0, 720.0);
		$this->assertEquals(20.0, $context->getFontSize());
		$this->assertEquals(1280.0, $context->getViewportWidth());
		$this->assertEquals(720.0, $context->getViewportHeight());
	}

	public function testSettersAndGetters(): void
	{
		$context = new CSSContext();
		$context->setFontSize(24.0);
		$this->assertEquals(24.0, $context->getFontSize());

		$context->setViewportWidth(1920.0);
		$this->assertEquals(1920.0, $context->getViewportWidth());

		$context->setViewportHeight(1080.0);
		$this->assertEquals(1080.0, $context->getViewportHeight());
	}

	public function testGetToPxFactor(): void
	{
		$context = new CSSContext(16.0, 1000.0, 800.0);
		$this->assertEquals(1.0, $context->getToPxFactor('px'));
		$this->assertEquals(96.0 / 72.0, $context->getToPxFactor('pt'));
		$this->assertEquals(16.0, $context->getToPxFactor('pc'));
		$this->assertEquals(96.0, $context->getToPxFactor('in'));
		$this->assertEquals(96.0 / 2.54, $context->getToPxFactor('cm'));
		$this->assertEquals(96.0 / 25.4, $context->getToPxFactor('mm'));
		$this->assertEquals(16.0, $context->getToPxFactor('em'));
		$this->assertEquals(16.0, $context->getToPxFactor('rem'));
		$this->assertEquals(10.0, $context->getToPxFactor('vw'));
		$this->assertEquals(8.0, $context->getToPxFactor('vh'));
		$this->assertEquals(8.0, $context->getToPxFactor('vmin'));
		$this->assertEquals(10.0, $context->getToPxFactor('vmax'));
		$this->assertEquals(1.0, $context->getToPxFactor('unknown'));
	}

	public function testGetFromPxFactor(): void
	{
		$context = new CSSContext(16.0, 1000.0, 800.0);
		$this->assertEquals(1.0, $context->getFromPxFactor('px'));
		$this->assertEqualsWithDelta(0.75, $context->getFromPxFactor('pt'), 0.001);
		$this->assertEquals(1 / 16.0, $context->getFromPxFactor('em'));
		$this->assertEquals(1 / 10.0, $context->getFromPxFactor('vw'));
		$this->assertEquals(1 / 8.0, $context->getFromPxFactor('vh'));
		$this->assertEquals(1 / 8.0, $context->getFromPxFactor('vmin'));
		$this->assertEquals(1 / 10.0, $context->getFromPxFactor('vmax'));
		$this->assertEquals(1.0, $context->getFromPxFactor('unknown'));
	}

	public function testFactorCache(): void
	{
		$context = new CSSContext();
		$this->assertEquals(1.0, $context->getToPxFactor('px'));
		// This will be cached
		$this->assertEquals(1.0, $context->getToPxFactor('px'));
	}

	public function testCacheIsClearedOnContextChange(): void
	{
		$context = new CSSContext();
		$this->assertEquals(16.0, $context->getToPxFactor('em'));
		$context->setFontSize(20.0);
		$this->assertEquals(20.0, $context->getToPxFactor('em'));
	}

	public function testColorSchemeAndCurrentColorAreValidatedAndIsolated(): void
	{
		$red = new CSSRGB(1, 0, 0);
		$context = new CSSContext(16.0, 960.0, 540.0, 'DARK', $red);
		self::assertSame('dark', $context->getColorScheme());

		$retrieved = $context->getCurrentColor();
		self::assertInstanceOf(CSSRGB::class, $retrieved);
		self::assertNotSame($red, $retrieved);
		$red->setR(0);
		$currentAfterMutation = $context->getCurrentColor();
		self::assertInstanceOf(CSSRGB::class, $currentAfterMutation);
		self::assertEqualsWithDelta(100.0, $currentAfterMutation->r->value, 1e-9);

		$cloned = clone $context;
		$clonedColor = $cloned->getCurrentColor();
		self::assertInstanceOf(CSSRGB::class, $clonedColor);
		self::assertNotSame($retrieved, $clonedColor);

		$fluent = $context->setColorScheme('Light')->setCurrentColor(null);
		self::assertSame($context, $fluent);
		self::assertSame('light', $context->getColorScheme());
		self::assertNull($context->getCurrentColor());

		$this->expectException(InvalidArgumentException::class);
		$context->setColorScheme('sepia');
	}
}
