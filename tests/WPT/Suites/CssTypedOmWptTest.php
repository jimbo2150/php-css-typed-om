<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\WPT\Suites;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSS;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleDeclaration;
use Jimbo2150\PhpCssTypedOm\Tests\WPT\Harness\WptAssertions;
use Jimbo2150\PhpCssTypedOm\Tests\WPT\Harness\WptContractTestCase;
use Jimbo2150\PhpCssTypedOm\Tests\WPT\Harness\WptFixtureCase;
use Jimbo2150\PhpCssTypedOm\Tests\WPT\Harness\WptFixtureLoader;
use Jimbo2150\PhpCssTypedOm\TypedOM\StylePropertyMap;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSPositionValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSTransformValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnparsedValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSVariableReferenceValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;

/**
 * WPT conformance suite for css/css-typed-om.
 */
final class CssTypedOmWptTest extends WptContractTestCase
{
	/**
	 * @return array<string, array{WptFixtureCase}>
	 */
	public static function provideCases(): array
	{
		return WptFixtureLoader::loadDomain('css/css-typed-om');
	}

	#[DataProvider('provideCases')]
	public function testConformance(WptFixtureCase $case): void
	{
		$this->assertCase($case, $this->verifyCase(...));
	}

	private function verifyCase(WptFixtureCase $case): void
	{
		match ($case->operation) {
			'typed-om.stylevalue.parse' => $this->runStyleValueParse($case),
			'typed-om.stylevalue.parseAll' => $this->runStyleValueParseAll($case),
			'typed-om.numeric.parse' => $this->runNumericParse($case),
			'typed-om.numeric.arithmetic' => $this->runNumericArithmetic($case),
			'typed-om.numeric.to' => $this->runNumericTo($case),
			'typed-om.numeric.toSum' => $this->runNumericToSum($case),
			'typed-om.numeric.type' => $this->runNumericType($case),
			'typed-om.numeric.equals' => $this->runNumericEquals($case),
			'typed-om.transform.parse' => $this->runTransformParse($case),
			'typed-om.transform.toMatrix' => $this->runTransformToMatrix($case),
			'typed-om.position.parse' => $this->runPositionParse($case),
			'typed-om.property-map.ops' => $this->runPropertyMapOps($case),
			'typed-om.unit-factory' => $this->runUnitFactory($case),
			default => throw new LogicException(sprintf('Unsupported css/css-typed-om operation "%s" in case "%s".', $case->operation, $case->id)),
		};
	}

	private function runStyleValueParse(WptFixtureCase $case): void
	{
		$property = $case->input['property'] ?? null;
		$cssText = $case->input['cssText'] ?? null;
		if (!is_string($property) || ($cssText !== null && !is_string($cssText))) {
			throw new LogicException(sprintf('Case "%s" has invalid stylevalue.parse input.', $case->id));
		}

		$expected = $case->expected;
		if (($expected['throws'] ?? null) === 'InvalidArgumentException') {
			WptAssertions::assertThrowsException(
				InvalidArgumentException::class,
				static function () use ($property, $cssText): void {
					CSSStyleValue::parse($property, $cssText);
				},
				$case->id,
			);
			return;
		}

		$parsed = CSSStyleValue::parse($property, $cssText);
		$this->assertStyleValue($case, $parsed);
	}

	private function runStyleValueParseAll(WptFixtureCase $case): void
	{
		$property = $case->input['property'] ?? null;
		$cssText = $case->input['cssText'] ?? null;
		if (!is_string($property) || !is_string($cssText)) {
			throw new LogicException(sprintf('Case "%s" requires string property and cssText.', $case->id));
		}

		$expected = $case->expected;
		if (($expected['throws'] ?? null) === 'InvalidArgumentException') {
			WptAssertions::assertThrowsException(
				InvalidArgumentException::class,
				static function () use ($property, $cssText): void {
					CSSStyleValue::parseAll($property, $cssText);
				},
				$case->id,
			);
			return;
		}

		$list = CSSStyleValue::parseAll($property, $cssText);
		if (array_key_exists('count', $expected)) {
			self::assertCount((int) $expected['count'], $list, $case->id);
		}
		if (isset($expected['items']) && is_array($expected['items'])) {
			self::assertSame($expected['items'], array_map('strval', $list), $case->id);
		}
		if (isset($expected['itemClasses']) && is_array($expected['itemClasses'])) {
			$actualClasses = array_map(static fn (CSSStyleValue $v): string => (new ReflectionClass($v))->getShortName(), $list);
			self::assertSame($expected['itemClasses'], $actualClasses, $case->id);
		}
	}

	private function runNumericParse(WptFixtureCase $case): void
	{
		$cssText = $case->input['cssText'] ?? null;
		if (!is_string($cssText)) {
			throw new LogicException(sprintf('Case "%s" requires string input.cssText.', $case->id));
		}

		$expected = $case->expected;
		if (($expected['throws'] ?? null) === 'InvalidArgumentException') {
			WptAssertions::assertThrowsException(
				InvalidArgumentException::class,
				static function () use ($cssText): void {
					CSSNumericValue::parse($cssText);
				},
				$case->id,
			);
			return;
		}

		$parsed = CSSNumericValue::parse($cssText);
		$this->assertStyleValue($case, $parsed);
		if (array_key_exists('value', $expected)) {
			self::assertInstanceOf(CSSUnitValue::class, $parsed, $case->id);
			WptAssertions::assertFloatEquals((float) $expected['value'], $parsed->value, $case->tolerance ?? 1e-9, $case->id);
		}
		if (array_key_exists('unit', $expected)) {
			self::assertInstanceOf(CSSUnitValue::class, $parsed, $case->id);
			self::assertSame((string) $expected['unit'], $parsed->unit, $case->id);
		}
	}

	private function runNumericArithmetic(WptFixtureCase $case): void
	{
		$base = $case->input['base'] ?? null;
		$method = $case->input['method'] ?? null;
		$args = $case->input['args'] ?? [];
		if (!is_string($base) || !is_string($method) || !is_array($args)) {
			throw new LogicException(sprintf('Case "%s" has invalid numeric.arithmetic input.', $case->id));
		}

		$receiver = CSSNumericValue::parse($base);
		$parsedArgs = [];
		foreach ($args as $arg) {
			if (is_int($arg) || is_float($arg)) {
				$parsedArgs[] = $arg;
			} elseif (is_string($arg)) {
				$parsedArgs[] = CSSNumericValue::parse($arg);
			} else {
				throw new LogicException(sprintf('Case "%s" arithmetic arg must be numeric or string.', $case->id));
			}
		}

		$invoke = static fn (): CSSNumericValue => match ($method) {
			'add' => $receiver->add(...$parsedArgs),
			'sub' => $receiver->sub(...$parsedArgs),
			'mul' => $receiver->mul(...$parsedArgs),
			'div' => $receiver->div(...$parsedArgs),
			'min' => $receiver->min(...$parsedArgs),
			'max' => $receiver->max(...$parsedArgs),
			default => throw new LogicException('Unsupported arithmetic method: ' . $method),
		};

		if (($case->expected['throws'] ?? null) === 'InvalidArgumentException') {
			WptAssertions::assertThrowsException(InvalidArgumentException::class, $invoke, $case->id);
			return;
		}

		$result = $invoke();
		$this->assertStyleValue($case, $result);
	}

	private function runNumericTo(WptFixtureCase $case): void
	{
		$cssText = $case->input['cssText'] ?? null;
		$unit = $case->input['unit'] ?? null;
		if (!is_string($cssText) || !is_string($unit)) {
			throw new LogicException(sprintf('Case "%s" requires string cssText and unit.', $case->id));
		}

		$val = CSSNumericValue::parse($cssText);
		if (($case->expected['throws'] ?? null) === 'InvalidArgumentException') {
			WptAssertions::assertThrowsException(
				InvalidArgumentException::class,
				static function () use ($val, $unit): void {
					$val->to($unit);
				},
				$case->id,
			);
			return;
		}

		$converted = $val->to($unit);
		$this->assertStyleValue($case, $converted);
		if (array_key_exists('value', $case->expected)) {
			self::assertInstanceOf(CSSUnitValue::class, $converted, $case->id);
			WptAssertions::assertFloatEquals((float) $case->expected['value'], $converted->value, $case->tolerance ?? 1e-6, $case->id);
		}
		if (array_key_exists('unit', $case->expected)) {
			self::assertInstanceOf(CSSUnitValue::class, $converted, $case->id);
			self::assertSame((string) $case->expected['unit'], $converted->unit, $case->id);
		}
	}

	private function runNumericToSum(WptFixtureCase $case): void
	{
		$cssText = $case->input['cssText'] ?? null;
		$units = $case->input['units'] ?? null;
		if (!is_string($cssText) || !is_array($units)) {
			throw new LogicException(sprintf('Case "%s" requires cssText and units list.', $case->id));
		}
		$stringUnits = array_map(static fn (mixed $u): string => (string) $u, $units);
		$val = CSSNumericValue::parse($cssText);

		if (($case->expected['throws'] ?? null) === 'InvalidArgumentException') {
			WptAssertions::assertThrowsException(
				InvalidArgumentException::class,
				static function () use ($val, $stringUnits): void {
					$val->toSum(...$stringUnits);
				},
				$case->id,
			);
			return;
		}

		$sum = $val->toSum(...$stringUnits);
		$this->assertStyleValue($case, $sum);
	}

	private function runNumericType(WptFixtureCase $case): void
	{
		$cssText = $case->input['cssText'] ?? null;
		if (!is_string($cssText)) {
			throw new LogicException(sprintf('Case "%s" requires string input.cssText.', $case->id));
		}
		$type = CSSNumericValue::parse($cssText)->type();
		$expectedType = $case->expected['type'] ?? null;
		if (!is_array($expectedType)) {
			throw new LogicException(sprintf('Case "%s" requires expected.type array.', $case->id));
		}
		/** @var array<string, int|string|null> $expectedType */
		WptAssertions::assertNumericType($expectedType, $type, $case->id);
	}

	private function runNumericEquals(WptFixtureCase $case): void
	{
		$left = $case->input['left'] ?? null;
		$right = $case->input['right'] ?? null;
		if (!is_string($left) || !is_array($right)) {
			throw new LogicException(sprintf('Case "%s" requires left string and right list.', $case->id));
		}
		$expected = $case->expected['equal'] ?? null;
		if (!is_bool($expected)) {
			throw new LogicException(sprintf('Case "%s" requires bool expected.equal.', $case->id));
		}

		$lhs = CSSNumericValue::parse($left);
		$rhs = array_map(static fn (mixed $item): CSSNumericValue => CSSNumericValue::parse((string) $item), $right);
		self::assertSame($expected, $lhs->equals(...$rhs), $case->id);
	}

	private function runTransformParse(WptFixtureCase $case): void
	{
		$cssText = $case->input['cssText'] ?? null;
		if (!is_string($cssText)) {
			throw new LogicException(sprintf('Case "%s" requires string input.cssText.', $case->id));
		}

		if (($case->expected['throws'] ?? null) === 'InvalidArgumentException') {
			WptAssertions::assertThrowsException(
				InvalidArgumentException::class,
				static function () use ($cssText): void {
					CSSTransformValue::fromCssText($cssText);
				},
				$case->id,
			);
			return;
		}

		$transform = CSSTransformValue::fromCssText($cssText);
		$this->assertStyleValue($case, $transform);
		if (array_key_exists('is2D', $case->expected)) {
			self::assertSame((bool) $case->expected['is2D'], $transform->is2D, $case->id);
		}
		if (array_key_exists('length', $case->expected)) {
			self::assertSame((int) $case->expected['length'], $transform->length, $case->id);
		}
		if (isset($case->expected['componentClasses']) && is_array($case->expected['componentClasses'])) {
			$actual = array_map(static fn (object $c): string => (new ReflectionClass($c))->getShortName(), $transform->getComponents());
			self::assertSame($case->expected['componentClasses'], $actual, $case->id);
		}
	}

	private function runTransformToMatrix(WptFixtureCase $case): void
	{
		$cssText = $case->input['cssText'] ?? null;
		if (!is_string($cssText)) {
			throw new LogicException(sprintf('Case "%s" requires string input.cssText.', $case->id));
		}
		$transform = CSSTransformValue::fromCssText($cssText);

		if (($case->expected['throws'] ?? null) === 'InvalidArgumentException') {
			WptAssertions::assertThrowsException(
				InvalidArgumentException::class,
				static function () use ($transform): void {
					$transform->toMatrix();
				},
				$case->id,
			);
			return;
		}

		$matrix = $transform->toMatrix();
		$elements = $case->expected['elements'] ?? null;
		$is2D = $case->expected['is2D'] ?? null;
		if (!is_array($elements) || !is_bool($is2D)) {
			throw new LogicException(sprintf('Case "%s" requires expected.elements and expected.is2D.', $case->id));
		}
		/** @var list<int|float> $elements */
		WptAssertions::assertMatrixElements($elements, $matrix, $is2D, $case->tolerance ?? 1e-6, $case->id);
	}

	private function runPositionParse(WptFixtureCase $case): void
	{
		$cssText = $case->input['cssText'] ?? null;
		if (!is_string($cssText)) {
			throw new LogicException(sprintf('Case "%s" requires string input.cssText.', $case->id));
		}

		if (($case->expected['throws'] ?? null) === 'InvalidArgumentException') {
			WptAssertions::assertThrowsException(
				InvalidArgumentException::class,
				static function () use ($cssText): void {
					CSSPositionValue::fromCssText($cssText);
				},
				$case->id,
			);
			return;
		}

		$pos = CSSPositionValue::fromCssText($cssText);
		$this->assertStyleValue($case, $pos);
		if (array_key_exists('x', $case->expected)) {
			self::assertSame((string) $case->expected['x'], (string) $pos->x, $case->id);
		}
		if (array_key_exists('y', $case->expected)) {
			self::assertSame((string) $case->expected['y'], (string) $pos->y, $case->id);
		}
	}

	private function runPropertyMapOps(WptFixtureCase $case): void
	{
		$initialCss = $case->input['initialCss'] ?? '';
		$steps = $case->input['steps'] ?? [];
		if (!is_string($initialCss) || !is_array($steps)) {
			throw new LogicException(sprintf('Case "%s" has invalid property-map.ops input.', $case->id));
		}

		$decl = $initialCss === '' ? new CSSStyleDeclaration() : CSSStyleDeclaration::parse($initialCss);
		$map = new StylePropertyMap($decl);
		$expected = $case->expected;

		if (($expected['throws'] ?? null) === 'InvalidArgumentException') {
			WptAssertions::assertThrowsException(
				InvalidArgumentException::class,
				static function () use ($map, $steps): void {
					self::applyMapSteps($map, $steps);
				},
				$case->id,
			);
			if (array_key_exists('declCssText', $expected)) {
				self::assertSame((string) $expected['declCssText'], $decl->getCssText(), $case->id);
			}
			return;
		}

		self::applyMapSteps($map, $steps);
		if (array_key_exists('size', $expected)) {
			self::assertSame((int) $expected['size'], $map->size, $case->id);
		}
		if (array_key_exists('declCssText', $expected)) {
			self::assertSame((string) $expected['declCssText'], $decl->getCssText(), $case->id);
		}
		if (isset($expected['has']) && is_array($expected['has'])) {
			foreach ($expected['has'] as $prop => $bool) {
				self::assertSame((bool) $bool, $map->has((string) $prop), $case->id . " has($prop)");
			}
		}
		if (isset($expected['get']) && is_array($expected['get'])) {
			foreach ($expected['get'] as $prop => $valStr) {
				$got = $map->get((string) $prop);
				if ($valStr === null) {
					self::assertNull($got, $case->id . " get($prop)");
				} else {
					self::assertNotNull($got, $case->id . " get($prop)");
					self::assertSame((string) $valStr, (string) $got, $case->id . " get($prop)");
				}
			}
		}
		if (isset($expected['getAll']) && is_array($expected['getAll'])) {
			foreach ($expected['getAll'] as $prop => $vals) {
				self::assertSame($vals, array_map('strval', $map->getAll((string) $prop)), $case->id . " getAll($prop)");
			}
		}
	}

	private function runUnitFactory(WptFixtureCase $case): void
	{
		$method = $case->input['method'] ?? null;
		$value = $case->input['value'] ?? null;
		if (!is_string($method) || (!is_int($value) && !is_float($value) && !is_string($value))) {
			throw new LogicException(sprintf('Case "%s" has invalid unit-factory input.', $case->id));
		}

		$unitVal = CSS::{$method}($value);
		self::assertInstanceOf(CSSUnitValue::class, $unitVal, $case->id);
		if (array_key_exists('cssText', $case->expected)) {
			self::assertSame((string) $case->expected['cssText'], (string) $unitVal, $case->id);
		}
		if (array_key_exists('unit', $case->expected)) {
			self::assertSame((string) $case->expected['unit'], $unitVal->unit, $case->id);
		}
		if (array_key_exists('value', $case->expected)) {
			WptAssertions::assertFloatEquals((float) $case->expected['value'], $unitVal->value, $case->tolerance ?? 1e-9, $case->id);
		}
	}

	private function assertStyleValue(WptFixtureCase $case, CSSStyleValue $actual): void
	{
		$expected = $case->expected;
		if (array_key_exists('class', $expected)) {
			self::assertSame((string) $expected['class'], (new ReflectionClass($actual))->getShortName(), $case->id);
		}
		if (array_key_exists('cssText', $expected)) {
			self::assertSame((string) $expected['cssText'], (string) $actual, $case->id);
		}
		if ($actual instanceof CSSUnparsedValue && array_key_exists('segments', $expected) && is_array($expected['segments'])) {
			$actualSegments = [];
			foreach ($actual->getValues() as $segment) {
				if ($segment instanceof CSSVariableReferenceValue) {
					$actualSegments[] = [
						'var' => $segment->variable,
						'fallback' => $segment->fallback !== null ? (string) $segment->fallback : null,
					];
				} else {
					$actualSegments[] = $segment;
				}
			}
			self::assertSame($expected['segments'], $actualSegments, $case->id);
		}
	}

	/**
	 * @param array<int|string, mixed> $steps
	 */
	private static function applyMapSteps(StylePropertyMap $map, array $steps): void
	{
		foreach ($steps as $step) {
			if (!is_array($step) || !is_string($step['action'] ?? null) || !is_string($step['property'] ?? null)) {
				throw new LogicException('PropertyMap step requires string action and property.');
			}
			$prop = $step['property'];
			switch ($step['action']) {
				case 'set':
					$rawValues = $step['values'] ?? null;
					if (!is_array($rawValues)) {
						throw new LogicException('PropertyMap set step requires values array.');
					}
					$typed = array_map(static fn (mixed $v): string => (string) $v, array_values($rawValues));
					$map->set($prop, ...$typed);
					break;
				case 'append':
					$rawValues = $step['values'] ?? null;
					if (!is_array($rawValues)) {
						throw new LogicException('PropertyMap append step requires values array.');
					}
					$typed = array_map(static fn (mixed $v): string => (string) $v, array_values($rawValues));
					$map->append($prop, ...$typed);
					break;
				case 'delete':
					$map->delete($prop);
					break;
				case 'clear':
					$map->clear();
					break;
				default:
					throw new LogicException('Unsupported PropertyMap action: ' . $step['action']);
			}
		}
	}
}
