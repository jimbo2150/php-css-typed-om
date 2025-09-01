<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;

/**
 * Base class for CSS Typed OM values
 * Represents a CSS value that can be manipulated through the Typed OM API.
 */
abstract class CSSStyleValue
{

	public function __construct()
	{
		
	}

	/**
	 * Clone this value.
	 */
	abstract public function clone(): static;
}
