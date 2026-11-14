<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

final class CSSFontPaletteValuesRule extends CSSAtRule
{
	public function __construct(string $name, CSSStyleDeclaration $descriptors)
	{
		parent::__construct('font-palette-values', $name, $descriptors, true);
	}
}
