<?php
declare(strict_types=1);
namespace Jimbo2150\PhpCssTypedOm\Css;

abstract class CSSProperty {
	protected string $property;

	public static function isPropertyShorthand(string|self $property): bool {
		return $property instanceof self ?
			$property->_isShorthand() : static::__isShorthand($property);
	}

	public function isShorthand(): bool {
		return static::isShorthand($this->property);
	}
}