<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Css;

abstract class CSSProperty
{
	protected static \stdClass $properties;

	protected static $initialized = false;

	public static function getProperties(): \stdClass
	{
		return static::$properties;
	}

	public static function initialize(): void
	{
		if (static::$initialized) {
			return;
		}
		static::$initialized = true;
		static::$properties = json_decode(
			file_get_contents(realpath(
				'./dist/CSSProperties/CSSProperties.json'
			))
		)->properties;
	}

	public static function isShorthand(string $property): bool
	{
		$longhands = (array) static::getProperties()?->{$property}?->
			{'codegen-properties'}?->longhands ?? [];

		return count($longhands) > 0;
	}
}

CSSProperty::initialize();
