<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\WPT\Harness;

/**
 * Immutable audit summary for WPT conformance fixtures.
 */
final readonly class WptAuditReport
{
	/**
	 * @param array<string, array{total: int, pass: int, xfail: int, categories: array<string, int>}> $countsBySpec
	 * @param array<string, int> $countsByCategory
	 * @param list<string> $upstreamFiles
	 * @param list<string> $errors
	 */
	public function __construct(
		public string $wptCommit,
		public int $totalCases,
		public int $passCount,
		public int $xfailCount,
		public array $countsBySpec,
		public array $countsByCategory,
		public array $upstreamFiles,
		public array $errors,
	) {
	}

	public function isValid(): bool
	{
		return $this->errors === [];
	}

	/**
	 * @return array{
	 *     wptCommit: string,
	 *     totalCases: int,
	 *     passCount: int,
	 *     xfailCount: int,
	 *     countsBySpec: array<string, array{total: int, pass: int, xfail: int, categories: array<string, int>}>,
	 *     countsByCategory: array<string, int>,
	 *     upstreamFiles: list<string>,
	 *     errors: list<string>
	 * }
	 */
	public function toArray(): array
	{
		return [
			'wptCommit' => $this->wptCommit,
			'totalCases' => $this->totalCases,
			'passCount' => $this->passCount,
			'xfailCount' => $this->xfailCount,
			'countsBySpec' => $this->countsBySpec,
			'countsByCategory' => $this->countsByCategory,
			'upstreamFiles' => $this->upstreamFiles,
			'errors' => $this->errors,
		];
	}
}
