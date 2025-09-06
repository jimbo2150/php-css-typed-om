<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Traits;

use Error;
use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\MagicPropertyAccessTrait;
use PHPUnit\Framework\TestCase;
use stdClass;
use TypeError;

class MagicPropertyAccessTraitTest extends TestCase
{
	use MagicPropertyAccessTrait;

	private static $testValues = [
		'test' => 'test',
		'not' => 4,
		'huh' => 94.329,
		'isSet' => false,
		'other' => 5
	];

	private static $testTypes = [
		'test' => 'string',
		'not' => 'int',
		'huh' => 'float',
		'isSet' => 'bool',
		'other' => 'int'
	];

	protected function setUp(): void
    {
        $this->initializeProperties(
			self::$testValues,
			self::$testTypes
		);
    }

    public function test()
    {
		$this->setProperties(self::$testValues);
		$this->assertSame(self::$testValues, $this->getProperties());
		$this->assertFalse($this->__isset('got'));
		$this->assertSame(self::$testValues['test'], $this->__get('test'));
		$this->assertSame(self::$testTypes, $this->__get('type'));
    }

	public function testInvalidPropertyTypeError()
    {
		$this->expectException(TypeError::class);
		$this->setProperties(['other' => new stdClass()]);
    }

	public function testNonexistentPropertyError()
    {
		$this->expectException(Error::class);
		$this->setProperties(['non_existent' => true]);
    }

	public function testAcquireUndefinedProperty()
    {
		$this->expectException(Error::class);
		$this->__get('non_existent');
    }

}