<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM;

use Jimbo2150\PhpCssTypedOm\TypedOM\CSSContext;
use PHPUnit\Framework\TestCase;

class CSSContextTest extends TestCase
{
    public function testConstructorWithDefaultValues()
    {
        $context = new CSSContext();
        $this->assertEquals(16.0, $context->getFontSize());
        $this->assertEquals(960.0, $context->getViewportWidth());
        $this->assertEquals(540.0, $context->getViewportHeight());
    }

    public function testConstructorWithCustomValues()
    {
        $context = new CSSContext(20.0, 1280.0, 720.0);
        $this->assertEquals(20.0, $context->getFontSize());
        $this->assertEquals(1280.0, $context->getViewportWidth());
        $this->assertEquals(720.0, $context->getViewportHeight());
    }

    public function testSettersAndGetters()
    {
        $context = new CSSContext();
        $context->setFontSize(24.0);
        $this->assertEquals(24.0, $context->getFontSize());

        $context->setViewportWidth(1920.0);
        $this->assertEquals(1920.0, $context->getViewportWidth());

        $context->setViewportHeight(1080.0);
        $this->assertEquals(1080.0, $context->getViewportHeight());
    }

    public function testGetToPxFactor()
    {
        $context = new CSSContext(16.0, 1000.0, 800.0);
        $this->assertEquals(1.0, $context->getToPxFactor('px'));
        $this->assertEquals(1.333, $context->getToPxFactor('pt'));
        $this->assertEquals(16.0, $context->getToPxFactor('em'));
        $this->assertEquals(10.0, $context->getToPxFactor('vw'));
        $this->assertEquals(8.0, $context->getToPxFactor('vh'));
        $this->assertEquals(8.0, $context->getToPxFactor('vmin'));
        $this->assertEquals(10.0, $context->getToPxFactor('vmax'));
        $this->assertEquals(1.0, $context->getToPxFactor('unknown'));
    }

    public function testGetFromPxFactor()
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

    public function testFactorCache()
    {
        $context = new CSSContext();
        $this->assertEquals(1.0, $context->getToPxFactor('px'));
        // This will be cached
        $this->assertEquals(1.0, $context->getToPxFactor('px'));
    }

    public function testCacheIsClearedOnContextChange()
    {
        $context = new CSSContext();
        $this->assertEquals(16.0, $context->getToPxFactor('em'));
        $context->setFontSize(20.0);
        $this->assertEquals(20.0, $context->getToPxFactor('em'));
    }
}