<?php
declare(strict_types=1);
namespace Jimbo2150\PhpCssTypedOm;

use function Jimbo2150\PhpCssTypedOm\Parser\isCustomPropertyName;

abstract class CSSStyleValueFactory {

	/**
	 * Summary of parseStyleValue
	 * 
	 * @param string $property
	 * @param mixed $value
	 * @throws \Exception
	 * @return CSSStyleValue
	 */
	public static function parseStyleValue(string $property, $value): CSSStyleValue {
		if(isCustomPropertyName($property)) {
			return static::extractCustomCSSValues($value);
		}

		$property = strtolower($property);
	}

	public static function extractCustomCSSValues(string $cssText): CSSUnparsedValue {
		if(empty($cssText)) {
			// throw exception
		}

		$tokenizer = CSSTokenizer::tokenize($cssText);
		return CSSUnparsedValue::create($tokenizer->tokenRange);
	}

}