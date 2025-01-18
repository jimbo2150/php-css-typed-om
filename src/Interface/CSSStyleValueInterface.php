<?php
declare(strict_types=1);
namespace Jimbo2150\PhpCssTypedOm\Interface;

interface CSSStyleValueInterface {
	/**
	 * Summary of parse
	 * 
	 * @param string $property	A CSS property to set.
	 * @param string $cssText 	A comma-separated string containing one or more values that apply to
	 * 							the provided property.
	 * @return self
	 */
	public static function parse(string $property, string $cssText): self;


	/**
	 * Summary of parseAll
	 * 
	 * @param string $property	A CSS property to set.
	 * @param string $value 	A comma-separated string containing one or more values that apply to
	 * 							the provided property.
	 * @return self
	 */
	public static function parseAll(string $property, string $value): self;
}