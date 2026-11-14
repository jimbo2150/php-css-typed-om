<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;

/**
 * Resource-limit configuration for CSS parsing to protect against adversarial inputs.
 *
 * Pass `null` for an individual limit (or use `CSSParserOptions::unlimited()`) to disable it.
 */
final readonly class CSSParserOptions
{
	public const int DEFAULT_MAX_BYTES = 16 * 1024 * 1024;
	public const int DEFAULT_MAX_DEPTH = 256;
	public const int DEFAULT_MAX_RULES = 100_000;
	public const int DEFAULT_MAX_MATH_DEPTH = 256;

	public function __construct(
		public ?int $maxBytes = self::DEFAULT_MAX_BYTES,
		public ?int $maxDepth = self::DEFAULT_MAX_DEPTH,
		public ?int $maxRules = self::DEFAULT_MAX_RULES,
		public ?int $maxMathDepth = self::DEFAULT_MAX_MATH_DEPTH,
	) {
		foreach (['maxBytes' => $maxBytes, 'maxDepth' => $maxDepth, 'maxRules' => $maxRules, 'maxMathDepth' => $maxMathDepth] as $name => $limit) {
			if ($limit !== null && $limit < 1) {
				throw new InvalidArgumentException(sprintf('Parser option "%s" must be a positive integer or null.', $name));
			}
		}
	}

	/**
	 * Creates options with all parser size/depth/rule-count limits disabled.
	 */
	public static function unlimited(): self
	{
		return new self(null, null, null, null);
	}
}
