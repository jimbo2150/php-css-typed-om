<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\WPT\Suites;

use Jimbo2150\PhpCssTypedOm\Tests\WPT\Harness\WptFixtureLoader;
use Jimbo2150\PhpCssTypedOm\Tests\WPT\Harness\WptSchema;
use PHPUnit\Framework\TestCase;

/**
 * Validates WPT manifest integrity, schema v1 invariants, category coverage, and orphan-free fixtures.
 */
final class WptManifestIntegrityTest extends TestCase
{
	public function testManifestAndFixturesAreValidAndComplete(): void
	{
		$report = WptFixtureLoader::audit();

		self::assertSame([], $report->errors, implode("\n", $report->errors));
		self::assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $report->wptCommit);
		self::assertGreaterThanOrEqual(350, $report->totalCases);

		foreach (WptSchema::SPECS as $spec) {
			self::assertArrayHasKey($spec, $report->countsBySpec);
			$stats = $report->countsBySpec[$spec];
			self::assertGreaterThan(0, $stats['total']);
			foreach (WptSchema::CATEGORIES as $category) {
				self::assertGreaterThan(
					0,
					$stats['categories'][$category],
					sprintf('Spec "%s" must include at least one "%s" case.', $spec, $category),
				);
			}
		}
	}
}
