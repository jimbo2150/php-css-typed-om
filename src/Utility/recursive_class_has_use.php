<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Utility;

function recursive_class_has_use(object|string $object, object|string $use): bool
{
	return isset(
		recursive_class_uses($object)[
			is_object($use) ? get_class($use) : $use
		]
	);
}
