<?php
declare(strict_types=1);
namespace Jimbo2150\PhpCssTypedOm\Utility;

use Exception;

function isASCII(string $input): bool {
	return mb_check_encoding($input, 'ASCII');
}