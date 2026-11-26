<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\CSSOM;

use Jimbo2150\PhpCssTypedOm\CSSOM\CSSPageDescriptors;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSPositionTryDescriptors;
use Jimbo2150\PhpCssTypedOm\CSSOM\Shorthand\CSSShorthandRegistry;
use Jimbo2150\PhpCssTypedOm\Grammar\CSSPropertyGrammarRegistry;
use Jimbo2150\PhpCssTypedOm\Grammar\CSSValueValidator;
use PHPUnit\Framework\TestCase;

final class CSSShorthandRegistryTest extends TestCase
{
	public function testAll77ShorthandsAndLonghandsAreRegisteredAndValid(): void
	{
		$shorthands = CSSShorthandRegistry::getAllShorthands();
		self::assertCount(77, $shorthands);

		$allProps = array_flip(CSSPropertyGrammarRegistry::allProperties());
		$shorthandSet = array_flip($shorthands);

		foreach ($shorthands as $shorthand) {
			self::assertArrayHasKey($shorthand, $allProps, "Shorthand {$shorthand} missing from CSSPropertyGrammarRegistry.");
			self::assertTrue(CSSShorthandRegistry::isShorthand($shorthand));

			$longhands = CSSShorthandRegistry::getLonghands($shorthand);
			self::assertNotNull($longhands);
			self::assertNotSame([], $longhands);

			foreach ($longhands as $lh) {
				self::assertArrayHasKey($lh, $allProps, "Longhand {$lh} of {$shorthand} missing from CSSPropertyGrammarRegistry.");
				self::assertArrayNotHasKey($lh, $shorthandSet, "Longhand {$lh} of {$shorthand} must not itself be a shorthand.");

				if ($shorthand !== 'all') {
					self::assertTrue(CSSShorthandRegistry::isShorthandLonghand($lh), "Longhand {$lh} missing from INITIAL_VALUES.");
					$init = CSSShorthandRegistry::getInitialValue($lh);
					self::assertTrue(
						CSSValueValidator::validateStandardProperty($lh, $init),
						"Initial value '{$init}' for longhand '{$lh}' failed validation.",
					);
				}
			}
		}

		$allLonghands = CSSShorthandRegistry::getAllLonghands();
		self::assertNotContains('direction', $allLonghands);
		self::assertNotContains('unicode-bidi', $allLonghands);
	}

	public function testStrictDescriptorWhitelistsAreClosedOverAllAllowedShorthands(): void
	{
		foreach ([CSSPageDescriptors::class, CSSPositionTryDescriptors::class] as $descriptorClass) {
			$allowed = $descriptorClass::getAllowedDescriptors();
			self::assertNotNull($allowed);
			$allowedSet = array_flip($allowed);

			foreach ($allowed as $prop) {
				if (!CSSShorthandRegistry::isShorthand($prop)) {
					continue;
				}
				$longhands = CSSShorthandRegistry::getLonghands($prop) ?? [];
				foreach ($longhands as $lh) {
					self::assertArrayHasKey(
						$lh,
						$allowedSet,
						"{$descriptorClass} allows shorthand '{$prop}' but omits constituent longhand '{$lh}'.",
					);
				}
			}
		}
	}
}
