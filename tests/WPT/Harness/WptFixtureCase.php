<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\WPT\Harness;

/**
 * Immutable value object describing one WPT conformance fixture case.
 */
final readonly class WptFixtureCase
{
	/**
	 * @param array<string, mixed> $input
	 * @param array<string, mixed> $expected
	 */
	public function __construct(
		public string $id,
		public string $spec,
		public string $upstream,
		public string $category,
		public string $operation,
		public array $input,
		public array $expected,
		public string $status,
		public ?string $xfailReason,
		public ?string $divergenceKind,
		public ?string $specRef,
		public ?float $tolerance,
		public string $fixtureFile,
	) {
	}
}
