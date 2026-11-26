<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\WPT\Suites;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSS;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleDeclaration;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleSheet;
use Jimbo2150\PhpCssTypedOm\CSSOM\MediaList;
use Jimbo2150\PhpCssTypedOm\Tests\WPT\Harness\WptAssertions;
use Jimbo2150\PhpCssTypedOm\Tests\WPT\Harness\WptContractTestCase;
use Jimbo2150\PhpCssTypedOm\Tests\WPT\Harness\WptFixtureCase;
use Jimbo2150\PhpCssTypedOm\Tests\WPT\Harness\WptFixtureLoader;
use LogicException;
use OutOfBoundsException;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * WPT conformance suite for css/cssom.
 */
final class CssomWptTest extends WptContractTestCase
{
	/**
	 * @return array<string, array{WptFixtureCase}>
	 */
	public static function provideCases(): array
	{
		return WptFixtureLoader::loadDomain('css/cssom');
	}

	#[DataProvider('provideCases')]
	public function testConformance(WptFixtureCase $case): void
	{
		$this->assertCase($case, $this->verifyCase(...));
	}

	private function verifyCase(WptFixtureCase $case): void
	{
		match ($case->operation) {
			'css.escape' => $this->runCssEscape($case),
			'css.supports' => $this->runCssSupports($case),
			'cssom.stylesheet.parse' => $this->runStylesheetParse($case),
			'cssom.stylesheet.insertRule' => $this->runStylesheetInsertRule($case),
			'cssom.stylesheet.deleteRule' => $this->runStylesheetDeleteRule($case),
			'cssom.stylesheet.replaceSync' => $this->runStylesheetReplaceSync($case),
			'cssom.declaration.setProperty' => $this->runDeclarationSetProperty($case),
			'cssom.declaration.cssText' => $this->runDeclarationCssText($case),
			'cssom.mediaList' => $this->runMediaList($case),
			default => throw new LogicException(sprintf('Unsupported css/cssom operation "%s" in case "%s".', $case->operation, $case->id)),
		};
	}

	private function runCssEscape(WptFixtureCase $case): void
	{
		$input = $case->input;
		if (array_key_exists('rawHex', $input)) {
			$rawHex = $input['rawHex'];
			if (!is_string($rawHex)) {
				throw new LogicException(sprintf('Case "%s" input.rawHex must be a string.', $case->id));
			}
			$decoded = hex2bin($rawHex);
			if ($decoded === false) {
				throw new LogicException(sprintf('Case "%s" input.rawHex is invalid hex.', $case->id));
			}
			$arg = $decoded;
		} elseif (array_key_exists('value', $input)) {
			$arg = $input['value'];
			if (!is_string($arg) && !is_int($arg) && !is_float($arg) && $arg !== null) {
				throw new LogicException(sprintf('Case "%s" input.value has invalid type.', $case->id));
			}
		} else {
			throw new LogicException(sprintf('Case "%s" requires input.value or input.rawHex.', $case->id));
		}

		$expected = $case->expected['value'] ?? null;
		if (!is_string($expected)) {
			throw new LogicException(sprintf('Case "%s" expected.value must be a string.', $case->id));
		}

		self::assertSame($expected, CSS::escape($arg), $case->id);
	}

	private function runCssSupports(WptFixtureCase $case): void
	{
		$arg1 = $case->input['propertyOrCondition'] ?? null;
		if (!is_string($arg1)) {
			throw new LogicException(sprintf('Case "%s" requires string input.propertyOrCondition.', $case->id));
		}
		$arg2 = $case->input['value'] ?? null;
		if ($arg2 !== null && !is_string($arg2)) {
			throw new LogicException(sprintf('Case "%s" input.value must be string or null.', $case->id));
		}
		$expected = $case->expected['result'] ?? null;
		if (!is_bool($expected)) {
			throw new LogicException(sprintf('Case "%s" expected.result must be bool.', $case->id));
		}

		self::assertSame($expected, CSS::supports($arg1, $arg2), $case->id);
	}

	private function runStylesheetParse(WptFixtureCase $case): void
	{
		$css = $case->input['css'] ?? null;
		if (!is_string($css)) {
			throw new LogicException(sprintf('Case "%s" requires string input.css.', $case->id));
		}

		$sheet = CSSStyleSheet::parse($css);
		$expected = $case->expected;

		if (array_key_exists('ruleCount', $expected)) {
			self::assertCount((int) $expected['ruleCount'], $sheet->cssRules, $case->id);
		}
		if (array_key_exists('cssText', $expected)) {
			self::assertSame((string) $expected['cssText'], $sheet->getCssText(), $case->id);
		}
		if (array_key_exists('ruleTypes', $expected)) {
			$actualTypes = array_map(static fn (CSSRule $r): int => $r->getType(), $sheet->cssRules->getRulesRaw());
			self::assertSame($expected['ruleTypes'], $actualTypes, $case->id);
		}
		if (array_key_exists('parseErrorsEmpty', $expected)) {
			if ($expected['parseErrorsEmpty']) {
				self::assertSame([], $sheet->getParseErrors(), $case->id);
			} else {
				self::assertNotEmpty($sheet->getParseErrors(), $case->id);
			}
		}
		if (($expected['roundTrip'] ?? false) === true) {
			$reparsed = CSSStyleSheet::parse($sheet->getCssText());
			self::assertSame($sheet->getCssText(), $reparsed->getCssText(), $case->id . ' round-trip');
		}
	}

	private function runStylesheetInsertRule(WptFixtureCase $case): void
	{
		$initialCss = $case->input['initialCss'] ?? '';
		$ruleText = $case->input['rule'] ?? null;
		$index = $case->input['index'] ?? 0;
		if (!is_string($initialCss) || !is_string($ruleText) || !is_int($index)) {
			throw new LogicException(sprintf('Case "%s" has invalid insertRule input.', $case->id));
		}

		$sheet = CSSStyleSheet::parse($initialCss);
		$expected = $case->expected;
		$throws = $expected['throws'] ?? null;
		if ($throws !== null) {
			$exClass = match ($throws) {
				'InvalidArgumentException' => InvalidArgumentException::class,
				'OutOfBoundsException' => OutOfBoundsException::class,
				default => throw new LogicException(sprintf('Unsupported throws class "%s".', (string) $throws)),
			};
			WptAssertions::assertThrowsException(
				$exClass,
				static function () use ($sheet, $ruleText, $index): void {
					$sheet->insertRule($ruleText, $index);
				},
				$case->id,
			);
			if (array_key_exists('cssTextAfter', $expected)) {
				self::assertSame((string) $expected['cssTextAfter'], $sheet->getCssText(), $case->id);
			}
			return;
		}

		$targetPath = $case->input['targetRuleIndex'] ?? null;
		if (is_int($targetPath)) {
			$parent = $sheet->cssRules[$targetPath];
			if (!$parent instanceof \Jimbo2150\PhpCssTypedOm\CSSOM\CSSGroupingRule) {
				throw new LogicException(sprintf('Case "%s" targetRuleIndex is not a CSSGroupingRule.', $case->id));
			}
			$returned = $parent->insertRule($ruleText, $index);
		} else {
			$returned = $sheet->insertRule($ruleText, $index);
		}

		if (array_key_exists('returnedIndex', $expected)) {
			self::assertSame((int) $expected['returnedIndex'], $returned, $case->id);
		}
		if (array_key_exists('ruleCount', $expected)) {
			self::assertCount((int) $expected['ruleCount'], $sheet->cssRules, $case->id);
		}
		if (array_key_exists('cssText', $expected)) {
			self::assertSame((string) $expected['cssText'], $sheet->getCssText(), $case->id);
		}
	}

	private function runStylesheetDeleteRule(WptFixtureCase $case): void
	{
		$initialCss = $case->input['initialCss'] ?? '';
		$index = $case->input['index'] ?? 0;
		if (!is_string($initialCss) || !is_int($index)) {
			throw new LogicException(sprintf('Case "%s" has invalid deleteRule input.', $case->id));
		}

		$sheet = CSSStyleSheet::parse($initialCss);
		$expected = $case->expected;
		if (($expected['throws'] ?? null) === 'OutOfBoundsException') {
			WptAssertions::assertThrowsException(
				OutOfBoundsException::class,
				static function () use ($sheet, $index): void {
					$sheet->deleteRule($index);
				},
				$case->id,
			);
			return;
		}

		$sheet->deleteRule($index);
		if (array_key_exists('ruleCount', $expected)) {
			self::assertCount((int) $expected['ruleCount'], $sheet->cssRules, $case->id);
		}
		if (array_key_exists('cssText', $expected)) {
			self::assertSame((string) $expected['cssText'], $sheet->getCssText(), $case->id);
		}
	}

	private function runStylesheetReplaceSync(WptFixtureCase $case): void
	{
		$initialCss = $case->input['initialCss'] ?? '';
		$nextCss = $case->input['nextCss'] ?? null;
		if (!is_string($initialCss) || !is_string($nextCss)) {
			throw new LogicException(sprintf('Case "%s" has invalid replaceSync input.', $case->id));
		}

		$sheet = CSSStyleSheet::parse($initialCss);
		$firstRule = $sheet->cssRules[0] ?? null;
		$sheet->replaceSync($nextCss);

		$expected = $case->expected;
		if (array_key_exists('cssText', $expected)) {
			self::assertSame((string) $expected['cssText'], $sheet->getCssText(), $case->id);
		}
		if (array_key_exists('ruleCount', $expected)) {
			self::assertCount((int) $expected['ruleCount'], $sheet->cssRules, $case->id);
		}
		if (($expected['oldRuleDetached'] ?? false) === true && $firstRule !== null) {
			self::assertNull($firstRule->getParentStyleSheet(), $case->id);
		}
	}

	private function runDeclarationSetProperty(WptFixtureCase $case): void
	{
		$initialCss = $case->input['initialCss'] ?? '';
		$property = $case->input['property'] ?? null;
		$value = $case->input['value'] ?? null;
		$priority = $case->input['priority'] ?? '';
		if (!is_string($initialCss) || !is_string($property) || !is_string($value) || !is_string($priority)) {
			throw new LogicException(sprintf('Case "%s" has invalid setProperty input.', $case->id));
		}

		$decl = $initialCss === '' ? new CSSStyleDeclaration() : CSSStyleDeclaration::parse($initialCss);
		$expected = $case->expected;
		if (($expected['throws'] ?? null) === 'InvalidArgumentException') {
			WptAssertions::assertThrowsException(
				InvalidArgumentException::class,
				static function () use ($decl, $property, $value, $priority): void {
					$decl->setProperty($property, $value, $priority);
				},
				$case->id,
			);
			if (array_key_exists('cssText', $expected)) {
				self::assertSame((string) $expected['cssText'], $decl->getCssText(), $case->id);
			}
			return;
		}

		$decl->setProperty($property, $value, $priority);
		if (array_key_exists('cssText', $expected)) {
			self::assertSame((string) $expected['cssText'], $decl->getCssText(), $case->id);
		}
		if (array_key_exists('propertyValue', $expected)) {
			$lookup = is_string($case->input['lookup'] ?? null) ? $case->input['lookup'] : $property;
			self::assertSame((string) $expected['propertyValue'], $decl->getPropertyValue($lookup), $case->id);
		}
		if (array_key_exists('priority', $expected)) {
			$lookup = is_string($case->input['lookup'] ?? null) ? $case->input['lookup'] : $property;
			self::assertSame((string) $expected['priority'], $decl->getPropertyPriority($lookup), $case->id);
		}
		if (array_key_exists('length', $expected)) {
			self::assertSame((int) $expected['length'], $decl->getLength(), $case->id);
		}
	}

	private function runDeclarationCssText(WptFixtureCase $case): void
	{
		$mode = $case->input['mode'] ?? 'parse';
		$cssText = $case->input['cssText'] ?? null;
		if (!is_string($mode) || !is_string($cssText)) {
			throw new LogicException(sprintf('Case "%s" has invalid declaration cssText input.', $case->id));
		}

		$expected = $case->expected;
		if ($mode === 'setCssText') {
			$initial = $case->input['initialCss'] ?? '';
			if (!is_string($initial)) {
				throw new LogicException(sprintf('Case "%s" input.initialCss must be string.', $case->id));
			}
			$decl = CSSStyleDeclaration::parse($initial);
			if (($expected['throws'] ?? null) === 'InvalidArgumentException') {
				WptAssertions::assertThrowsException(
					InvalidArgumentException::class,
					static function () use ($decl, $cssText): void {
						$decl->setCssText($cssText);
					},
					$case->id,
				);
				if (array_key_exists('cssText', $expected)) {
					self::assertSame((string) $expected['cssText'], $decl->getCssText(), $case->id);
				}
				return;
			}
			$decl->setCssText($cssText);
		} else {
			if (($expected['throws'] ?? null) === 'InvalidArgumentException') {
				WptAssertions::assertThrowsException(
					InvalidArgumentException::class,
					static function () use ($cssText): void {
						CSSStyleDeclaration::parse($cssText, true);
					},
					$case->id,
				);
				return;
			}
			$decl = CSSStyleDeclaration::parse($cssText, true);
		}

		if (array_key_exists('cssText', $expected)) {
			self::assertSame((string) $expected['cssText'], $decl->getCssText(), $case->id);
		}
		if (array_key_exists('length', $expected)) {
			self::assertSame((int) $expected['length'], $decl->getLength(), $case->id);
		}
		if (isset($expected['properties']) && is_array($expected['properties'])) {
			foreach ($expected['properties'] as $prop => $val) {
				self::assertSame((string) $val, $decl->getPropertyValue((string) $prop), $case->id . " ($prop)");
			}
		}
		if (($expected['parentRuleFromSheet'] ?? false) === true) {
			$sheet = CSSStyleSheet::parse('a { ' . $cssText . ' }');
			$rule = $sheet->cssRules[0] ?? null;
			self::assertInstanceOf(CSSStyleRule::class, $rule, $case->id);
			self::assertSame($rule, $rule->style->getParentRule(), $case->id);
		}
	}

	private function runMediaList(WptFixtureCase $case): void
	{
		$initial = $case->input['initial'] ?? '';
		$steps = $case->input['steps'] ?? [];
		if (!is_string($initial) || !is_array($steps)) {
			throw new LogicException(sprintf('Case "%s" has invalid MediaList input.', $case->id));
		}

		$list = new MediaList($initial);
		$expected = $case->expected;
		if (isset($expected['throws']) && is_string($expected['throws'])) {
			$exClass = $expected['throws'] === 'InvalidArgumentException'
				? InvalidArgumentException::class
				: throw new LogicException('Unsupported exception class.');
			WptAssertions::assertThrowsException(
				$exClass,
				static function () use ($list, $steps): void {
					self::applyMediaSteps($list, $steps);
				},
				$case->id,
			);
			return;
		}

		self::applyMediaSteps($list, $steps);
		if (array_key_exists('mediaText', $expected)) {
			self::assertSame((string) $expected['mediaText'], $list->getMediaText(), $case->id);
		}
		if (array_key_exists('length', $expected)) {
			self::assertSame((int) $expected['length'], $list->count(), $case->id);
		}
		if (isset($expected['items']) && is_array($expected['items'])) {
			foreach ($expected['items'] as $idx => $itemText) {
				self::assertSame((string) $itemText, $list->item((int) $idx), $case->id);
			}
		}
	}

	/**
	 * @param array<int|string, mixed> $steps
	 */
	private static function applyMediaSteps(MediaList $list, array $steps): void
	{
		foreach ($steps as $step) {
			if (!is_array($step) || !is_string($step['action'] ?? null) || !is_string($step['value'] ?? null)) {
				throw new LogicException('MediaList step must specify string action and value.');
			}
			match ($step['action']) {
				'appendMedium' => $list->appendMedium($step['value']),
				'deleteMedium' => $list->deleteMedium($step['value']),
				'setMediaText' => $list->setMediaText($step['value']),
				default => throw new LogicException('Unknown MediaList step action: ' . $step['action']),
			};
		}
	}
}
