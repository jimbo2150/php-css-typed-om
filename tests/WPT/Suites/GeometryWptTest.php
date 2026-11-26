<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\WPT\Suites;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Geometry\DOMMatrix;
use Jimbo2150\PhpCssTypedOm\Geometry\DOMMatrixReadOnly;
use Jimbo2150\PhpCssTypedOm\Tests\WPT\Harness\WptAssertions;
use Jimbo2150\PhpCssTypedOm\Tests\WPT\Harness\WptContractTestCase;
use Jimbo2150\PhpCssTypedOm\Tests\WPT\Harness\WptFixtureCase;
use Jimbo2150\PhpCssTypedOm\Tests\WPT\Harness\WptFixtureLoader;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * WPT conformance suite for css/geometry.
 */
final class GeometryWptTest extends WptContractTestCase
{
	/**
	 * @return array<string, array{WptFixtureCase}>
	 */
	public static function provideCases(): array
	{
		return WptFixtureLoader::loadDomain('css/geometry');
	}

	#[DataProvider('provideCases')]
	public function testConformance(WptFixtureCase $case): void
	{
		$this->assertCase($case, $this->verifyCase(...));
	}

	private function verifyCase(WptFixtureCase $case): void
	{
		match ($case->operation) {
			'geometry.matrix.construct' => $this->runConstruct($case),
			'geometry.matrix.fromMatrix' => $this->runFromMatrix($case),
			'geometry.matrix.fromFloatArray' => $this->runFromFloatArray($case),
			'geometry.matrix.ops' => $this->runOps($case),
			'geometry.matrix.serialize' => $this->runSerialize($case),
			'geometry.matrix.transformPoint' => $this->runTransformPoint($case),
			'geometry.matrix.invalid' => $this->runInvalid($case),
			default => throw new LogicException(sprintf('Unsupported css/geometry operation "%s" in case "%s".', $case->operation, $case->id)),
		};
	}

	private function runConstruct(WptFixtureCase $case): void
	{
		$mutable = (bool) ($case->input['mutable'] ?? false);
		$init = $case->input['init'] ?? null;
		/** @var DOMMatrixReadOnly|list<int|float>|array<string, mixed>|string|null $init */
		$matrix = $mutable ? new DOMMatrix($init) : new DOMMatrixReadOnly($init);
		$this->assertMatrixResult($case, $matrix);
	}

	private function runFromMatrix(WptFixtureCase $case): void
	{
		$mutable = (bool) ($case->input['mutable'] ?? false);
		$other = $case->input['other'] ?? [];
		if (!is_array($other)) {
			throw new LogicException(sprintf('Case "%s" requires array input.other.', $case->id));
		}
		/** @var list<int|float>|array<string, mixed> $other */
		$matrix = $mutable ? DOMMatrix::fromMatrix($other) : DOMMatrixReadOnly::fromMatrix($other);
		$this->assertMatrixResult($case, $matrix);
	}

	private function runFromFloatArray(WptFixtureCase $case): void
	{
		$kind = $case->input['kind'] ?? 'float64';
		$mutable = (bool) ($case->input['mutable'] ?? false);
		$values = $case->input['values'] ?? null;
		if (!is_array($values)) {
			throw new LogicException(sprintf('Case "%s" requires list input.values.', $case->id));
		}
		/** @var list<int|float> $typedValues */
		$typedValues = array_values($values);

		$matrix = match ([$kind, $mutable]) {
			['float32', false] => DOMMatrixReadOnly::fromFloat32Array($typedValues),
			['float32', true] => DOMMatrix::fromFloat32Array($typedValues),
			['float64', false] => DOMMatrixReadOnly::fromFloat64Array($typedValues),
			['float64', true] => DOMMatrix::fromFloat64Array($typedValues),
			default => throw new LogicException(sprintf('Unsupported float array kind "%s".', (string) $kind)),
		};

		$this->assertMatrixResult($case, $matrix);
	}

	private function runOps(WptFixtureCase $case): void
	{
		$mutable = (bool) ($case->input['mutable'] ?? true);
		$init = $case->input['init'] ?? null;
		$steps = $case->input['steps'] ?? [];
		if (!is_array($steps)) {
			throw new LogicException(sprintf('Case "%s" requires array input.steps.', $case->id));
		}

		/** @var list<int|float>|array<string, mixed>|string|null $init */
		$matrix = $mutable ? new DOMMatrix($init) : new DOMMatrixReadOnly($init);
		$originalRef = $matrix;

		foreach ($steps as $step) {
			if (!is_array($step) || !is_string($step['op'] ?? null)) {
				throw new LogicException(sprintf('Case "%s" step requires string op.', $case->id));
			}
			$args = $step['args'] ?? [];
			if (!is_array($args)) {
				throw new LogicException(sprintf('Case "%s" step args must be array.', $case->id));
			}
			$matrix = $this->applyMatrixOp($matrix, $step['op'], array_values($args));
		}

		if (($case->expected['immutableReceiver'] ?? false) === true) {
			self::assertNotSame($originalRef, $matrix, $case->id);
			if (isset($case->expected['receiverCssText'])) {
				self::assertSame((string) $case->expected['receiverCssText'], (string) $originalRef, $case->id);
			}
		}

		$this->assertMatrixResult($case, $matrix);
	}

	private function runSerialize(WptFixtureCase $case): void
	{
		$init = $case->input['init'] ?? null;
		$mutable = (bool) ($case->input['mutable'] ?? false);
		/** @var list<int|float>|array<string, mixed>|string|null $init */
		$matrix = $mutable ? new DOMMatrix($init) : new DOMMatrixReadOnly($init);

		$this->assertMatrixResult($case, $matrix);
		if (isset($case->expected['toJSON']) && is_array($case->expected['toJSON'])) {
			$json = $matrix->toJSON();
			foreach ($case->expected['toJSON'] as $k => $v) {
				self::assertArrayHasKey((string) $k, $json, $case->id);
				if (is_bool($v)) {
					self::assertSame($v, $json[(string) $k], $case->id . " toJSON[$k]");
				} else {
					WptAssertions::assertFloatEquals((float) $v, (float) $json[(string) $k], $case->tolerance ?? 1e-6, $case->id . " toJSON[$k]");
				}
			}
		}
	}

	private function runTransformPoint(WptFixtureCase $case): void
	{
		$init = $case->input['init'] ?? null;
		$point = $case->input['point'] ?? [];
		if (!is_array($point)) {
			throw new LogicException(sprintf('Case "%s" requires array input.point.', $case->id));
		}
		/** @var list<int|float>|array<string, mixed>|string|null $init */
		$matrix = new DOMMatrixReadOnly($init);
		/** @var array{x?: int|float, y?: int|float, z?: int|float, w?: int|float} $point */
		$out = $matrix->transformPoint($point);
		$delta = $case->tolerance ?? 1e-6;

		foreach (['x', 'y', 'z', 'w'] as $coord) {
			if (array_key_exists($coord, $case->expected)) {
				WptAssertions::assertFloatEquals((float) $case->expected[$coord], $out[$coord], $delta, $case->id . ".$coord");
			}
		}
	}

	private function runInvalid(WptFixtureCase $case): void
	{
		$target = $case->input['target'] ?? 'construct';
		if (!is_string($target)) {
			throw new LogicException(sprintf('Case "%s" requires string input.target.', $case->id));
		}

		WptAssertions::assertThrowsException(
			InvalidArgumentException::class,
			function () use ($case, $target): void {
				switch ($target) {
					case 'construct':
						/** @var list<int|float>|array<string, mixed>|string|null $init */
						$init = $case->input['init'] ?? null;
						new DOMMatrixReadOnly($init);
						break;
					case 'fromMatrix':
						/** @var list<int|float>|array<string, mixed> $other */
						$other = $case->input['other'] ?? [];
						DOMMatrixReadOnly::fromMatrix($other);
						break;
					case 'fromFloat32Array':
						/** @var list<int|float> $values */
						$values = $case->input['values'] ?? [];
						DOMMatrixReadOnly::fromFloat32Array($values);
						break;
					case 'fromFloat64Array':
						/** @var list<int|float> $values */
						$values = $case->input['values'] ?? [];
						DOMMatrixReadOnly::fromFloat64Array($values);
						break;
					case 'invertSelf':
						/** @var list<int|float>|array<string, mixed>|string|null $init */
						$init = $case->input['init'] ?? null;
						(new DOMMatrix($init))->invertSelf();
						break;
					case 'inverse':
						/** @var list<int|float>|array<string, mixed>|string|null $init */
						$init = $case->input['init'] ?? null;
						(new DOMMatrixReadOnly($init))->inverse();
						break;
					case 'readOnlyOffsetSet':
						$ro = new DOMMatrixReadOnly();
						$ro[0] = 2.0;
						break;
					default:
						throw new LogicException(sprintf('Unsupported invalid target "%s".', $target));
				}
			},
			$case->id,
		);
	}

	/**
	 * @param list<mixed> $args
	 */
	private function applyMatrixOp(DOMMatrixReadOnly $matrix, string $op, array $args): DOMMatrixReadOnly
	{
		if ($matrix instanceof DOMMatrix) {
			return match ($op) {
				'multiplySelf' => $matrix->multiplySelf($args[0]),
				'preMultiplySelf' => $matrix->preMultiplySelf($args[0]),
				'translateSelf' => $matrix->translateSelf((float) ($args[0] ?? 0.0), (float) ($args[1] ?? 0.0), (float) ($args[2] ?? 0.0)),
				'scaleSelf' => $matrix->scaleSelf(
					(float) ($args[0] ?? 1.0),
					isset($args[1]) ? (float) $args[1] : null,
					(float) ($args[2] ?? 1.0),
					(float) ($args[3] ?? 0.0),
					(float) ($args[4] ?? 0.0),
					(float) ($args[5] ?? 0.0),
				),
				'scale3dSelf' => $matrix->scale3dSelf(
					(float) ($args[0] ?? 1.0),
					(float) ($args[1] ?? 0.0),
					(float) ($args[2] ?? 0.0),
					(float) ($args[3] ?? 0.0),
				),
				'rotateSelf' => $matrix->rotateSelf(
					(float) ($args[0] ?? 0.0),
					isset($args[1]) ? (float) $args[1] : null,
					isset($args[2]) ? (float) $args[2] : null,
				),
				'rotateFromVectorSelf' => $matrix->rotateFromVectorSelf((float) ($args[0] ?? 0.0), (float) ($args[1] ?? 0.0)),
				'rotateAxisAngleSelf' => $matrix->rotateAxisAngleSelf(
					(float) ($args[0] ?? 0.0),
					(float) ($args[1] ?? 0.0),
					(float) ($args[2] ?? 0.0),
					(float) ($args[3] ?? 0.0),
				),
				'skewXSelf' => $matrix->skewXSelf((float) ($args[0] ?? 0.0)),
				'skewYSelf' => $matrix->skewYSelf((float) ($args[0] ?? 0.0)),
				'invertSelf' => $matrix->invertSelf(),
				'setMatrixValue' => $matrix->setMatrixValue((string) ($args[0] ?? '')),
				default => $this->applyReadOnlyOp($matrix, $op, $args),
			};
		}

		return $this->applyReadOnlyOp($matrix, $op, $args);
	}

	/**
	 * @param list<mixed> $args
	 */
	private function applyReadOnlyOp(DOMMatrixReadOnly $matrix, string $op, array $args): DOMMatrixReadOnly
	{
		return match ($op) {
			'multiply' => $matrix->multiply($args[0]),
			'translate' => $matrix->translate((float) ($args[0] ?? 0.0), (float) ($args[1] ?? 0.0), (float) ($args[2] ?? 0.0)),
			'scale' => $matrix->scale(
				(float) ($args[0] ?? 1.0),
				isset($args[1]) ? (float) $args[1] : null,
				(float) ($args[2] ?? 1.0),
				(float) ($args[3] ?? 0.0),
				(float) ($args[4] ?? 0.0),
				(float) ($args[5] ?? 0.0),
			),
			'scaleNonUniform' => $matrix->scaleNonUniform((float) ($args[0] ?? 1.0), (float) ($args[1] ?? 1.0)),
			'scale3d' => $matrix->scale3d(
				(float) ($args[0] ?? 1.0),
				(float) ($args[1] ?? 0.0),
				(float) ($args[2] ?? 0.0),
				(float) ($args[3] ?? 0.0),
			),
			'rotate' => $matrix->rotate(
				(float) ($args[0] ?? 0.0),
				isset($args[1]) ? (float) $args[1] : null,
				isset($args[2]) ? (float) $args[2] : null,
			),
			'rotateFromVector' => $matrix->rotateFromVector((float) ($args[0] ?? 0.0), (float) ($args[1] ?? 0.0)),
			'rotateAxisAngle' => $matrix->rotateAxisAngle(
				(float) ($args[0] ?? 0.0),
				(float) ($args[1] ?? 0.0),
				(float) ($args[2] ?? 0.0),
				(float) ($args[3] ?? 0.0),
			),
			'skewX' => $matrix->skewX((float) ($args[0] ?? 0.0)),
			'skewY' => $matrix->skewY((float) ($args[0] ?? 0.0)),
			'flipX' => $matrix->flipX(),
			'flipY' => $matrix->flipY(),
			'inverse' => $matrix->inverse(),
			default => throw new LogicException('Unsupported matrix operation: ' . $op),
		};
	}

	private function assertMatrixResult(WptFixtureCase $case, DOMMatrixReadOnly $matrix): void
	{
		$expected = $case->expected;
		$delta = $case->tolerance ?? 1e-6;

		if (array_key_exists('is2D', $expected) && isset($expected['elements']) && is_array($expected['elements'])) {
			/** @var list<int|float> $elements */
			$elements = $expected['elements'];
			WptAssertions::assertMatrixElements($elements, $matrix, (bool) $expected['is2D'], $delta, $case->id);
		} else {
			if (array_key_exists('is2D', $expected)) {
				self::assertSame((bool) $expected['is2D'], $matrix->is2D(), $case->id);
			}
			if (isset($expected['elements']) && is_array($expected['elements'])) {
				/** @var list<int|float> $elements */
				$elements = $expected['elements'];
				WptAssertions::assertMatrixElements($elements, $matrix, $matrix->is2D(), $delta, $case->id);
			}
		}

		if (array_key_exists('isIdentity', $expected)) {
			self::assertSame((bool) $expected['isIdentity'], $matrix->isIdentity(), $case->id);
		}
		if (array_key_exists('cssText', $expected)) {
			self::assertSame((string) $expected['cssText'], (string) $matrix, $case->id);
		}
		if (isset($expected['fields']) && is_array($expected['fields'])) {
			foreach ($expected['fields'] as $field => $val) {
				WptAssertions::assertFloatEquals((float) $val, (float) $matrix->{$field}, $delta, $case->id . ".$field");
			}
		}
	}
}
