<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

enum CSSRenderMode: string
{
	case Pretty = 'pretty';
	case Minified = 'minified';
}
