<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\WPT\Harness;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSS;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use OutOfBoundsException;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;

/**
 * Base TestCase enforcing strict WPT pass / xfail / XPASS semantics without skipping tests.
 */
abstract class WptContractTestCase extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		CSS::clearRegisteredProperties();
	}

	protected function tearDown(): void
	{
		CSS::clearRegisteredProperties();
		parent::tearDown();
	}

	/**
	 * @param callable(WptFixtureCase): void $verifySpec
	 */
	protected function assertCase(WptFixtureCase $case, callable $verifySpec): void
	{
		if ($case->status === 'pass') {
			$verifySpec($case);
			return;
		}

		$failedAsExpected = false;
		try {
			$verifySpec($case);
		} catch (CSSParserLimitException $e) {
			throw $e;
		} catch (ExpectationFailedException | InvalidArgumentException | OutOfBoundsException) {
			$failedAsExpected = true;
		}

		if (!$failedAsExpected) {
			self::fail(sprintf(
				"XPASS: WPT case '%s' passed unexpectedly; promote status from 'xfail' to 'pass' in %s.",
				$case->id,
				$case->fixtureFile,
			));
		}

		$this->addToAssertionCount(1);
	}
}
