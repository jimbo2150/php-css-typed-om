<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use Jimbo2150\PhpCssTypedOm\Grammar\CSSPropertyGrammarRegistry;

/**
 * Registry of recognized standard CSS property names used by standalone `CSS::supports()`.
 *
 * Delegates directly to {@see CSSPropertyGrammarRegistry} as the single source of truth.
 *
 * @internal
 */
final class CSSStandardProperties
{
	public static function isKnown(string $propertyName): bool
	{
		return CSSPropertyGrammarRegistry::isKnown(strtolower($propertyName));
	}
}
