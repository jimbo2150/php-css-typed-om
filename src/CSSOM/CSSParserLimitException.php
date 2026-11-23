<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;

/**
 * Exception thrown when CSS parsing, evaluation, or rendering exceeds a configured resource limit.
 */
final class CSSParserLimitException extends InvalidArgumentException
{
}
