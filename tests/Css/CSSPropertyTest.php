<?php declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\Css;

use Jimbo2150\PhpCssTypedOm\Css\CSSProperty;
use PHPUnit\Framework\TestCase;
use stdClass;

final class CSSPropertyTest extends TestCase
{
    public function testCSSPropertyInitialization(): void
    {
        $propertiesObj = CSSProperty::getProperties();
		$objProperties = array_keys(get_object_vars($propertiesObj));
		$this->assertTrue($propertiesObj instanceof stdClass);
		$this->assertNotEmpty($objProperties, "No CSS properties.");
    }
}