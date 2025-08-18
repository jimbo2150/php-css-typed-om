<?php

declare(strict_types=1);

namespace Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSImageValue;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CSSImageValue class.
 */
class CSSImageValueTest extends TestCase
{
    public function testConstructor()
    {
        $image = new CSSImageValue('url("test.jpg")');
        $this->assertInstanceOf(CSSImageValue::class, $image);
    }

    public function testGetUrl()
    {
        $image = new CSSImageValue('url("test.jpg")');
        $this->assertSame('url("test.jpg")', $image->getUrl());
    }

    public function testToString()
    {
        $image = new CSSImageValue('url("test.jpg")');
        $this->assertSame('url("test.jpg")', $image->toString());
    }

    public function testIsValid()
    {
        $image = new CSSImageValue('url("test.jpg")');
        $this->assertTrue($image->isValid());
    }

    public function testClone()
    {
        $image = new CSSImageValue('url("test.jpg")');
        $cloned = $image->clone();
        
        $this->assertInstanceOf(CSSImageValue::class, $cloned);
        $this->assertNotSame($image, $cloned);
        $this->assertSame($image->getUrl(), $cloned->getUrl());
    }

    public function testEmptyUrl()
    {
        $image = new CSSImageValue('');
        $this->assertSame('', $image->getUrl());
    }

    public function testComplexUrl()
    {
        $url = 'url("https://example.com/path/to/image.png")';
        $image = new CSSImageValue($url);
        $this->assertSame($url, $image->getUrl());
    }
}
