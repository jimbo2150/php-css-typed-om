<?php
declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Css;

use stdClass;

abstract class CSSProperty {

	protected static stdClass $properties;

	protected static $initialized = false;
	protected string $property;

	public static function getProperties(): stdClass {
		return static::$properties;
	}

	public static function initialize(): void {
		if(static::$initialized) {
			return;
		}
		static::$initialized = true;
		static::$properties = json_decode(
			file_get_contents(realpath(
				'./vendor/WebKit/WebKit/main/Source/WebCore/css/CSSProperties.json'
			))
		)->properties;
	}

	public static function isPropertyShorthand(string|self $property): bool {
		return $property instanceof self ?
			$property->_isShorthand() : static::__isShorthand($property);
	}

	public function isShorthand(): bool {
		return static::isShorthand($this->property);
	}
}

CSSProperty::initialize();
