<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color;

use InvalidArgumentException;

/**
 * Internal marker exception thrown when a syntactically valid CSS Color 5 expression
 * cannot be eagerly resolved without runtime context (e.g. unresolved `currentcolor`,
 * `light-dark()` without a `CSSContext`, or unsimplified math channels in an origin color).
 *
 * @internal
 */
final class CSSDeferredColorException extends InvalidArgumentException
{
}
