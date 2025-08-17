<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSImageValue;
use PHPUnit\Framework\TestCase;

class CSSImageValueTest extends TestCase
{
	public function testToString()
	{
		$image = new CSSImageValue('http://example.com/image.png');
		$this->assertEquals('url(http://example.com/image.png)', $image->toString());
	}

	public function testIsValid()
	{
		$validImage = new CSSImageValue('http://example.com/image.png');
		$this->assertTrue($validImage->isValid());

		$invalidImage = new CSSImageValue('not-a-url');
		$this->assertFalse($invalidImage->isValid());
	}
}
