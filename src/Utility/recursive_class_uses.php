<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Utility;

/**
 * @return array<string,string>
 *
 * @throws \InvalidArgumentException
 */
function recursive_class_uses(object|string $object): array
{
	$classname = is_object($object) ? $object::class : $object;
	$ancestors = class_parents($object);
	if (false === $ancestors) {
		throw new \InvalidArgumentException('Class '.$classname.' does not exist.');
	}
	$uses = class_uses($object);
	foreach ($ancestors as $ancestor) {
		$uses = array_merge($uses, class_uses($ancestor));
	}

	return array_unique($uses);
}
