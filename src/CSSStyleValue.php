<?php
declare(strict_types=1);
namespace Jimbo2150\PhpCssTypedOm;

use Jimbo2150\PhpCssTypedOm\Interface\CSSStyleValueInterface;

abstract class CSSStyleValue implements CSSStyleValueInterface {

	public static function parse(string $property, string $cssText): self {
		return CSSStyleValueFactory::parseStyleValue($property, $cssText);
	}

	public static function parseAll(string $property, string $value): self {
		return CSSStyleValueFactory::parseStyleValue($property, $value);
	}

}