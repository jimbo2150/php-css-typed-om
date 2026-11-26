<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM\Shorthand;

use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\Grammar\CSSValueComponentParser;

/**
 * Reconstructs canonical shortest CSS shorthand values and serializes declaration blocks
 * per W3C CSSOM §6.7.2.
 *
 * @internal
 */
final class CSSShorthandSerializer
{
	/**
	 * Serializes a single shorthand property from `$declarations` and `$pendingShorthands`.
	 * Returns `''` when any longhand is absent, priorities conflict, pending/CSS-wide states mix,
	 * or the longhand values cannot be represented by `$shorthand`.
	 *
	 * @param array<string, array{value: string, priority: string}> $declarations
	 * @param array<string, array{shorthand: string, value: string, id: int, kind: 'substitution'|'system-font'}> $pendingShorthands
	 */
	public static function serializeShorthand(
		string $shorthand,
		array $declarations,
		array $pendingShorthands,
		?CSSParserOptions $options = null,
	): string {
		$longhands = CSSShorthandRegistry::getLonghands($shorthand);
		if ($longhands === null || $longhands === []) {
			return '';
		}

		$extracted = self::extractShorthandValues($shorthand, $longhands, $declarations, $pendingShorthands);
		if ($extracted === null) {
			return '';
		}
		if (is_string($extracted)) {
			return $extracted;
		}

		return self::serializeExtractedValues($shorthand, $extracted, $options);
	}

	/**
	 * @param array<string, string> $values
	 */
	private static function serializeExtractedValues(string $shorthand, array $values, ?CSSParserOptions $options): string
	{
		$options ??= CSSParserOptions::default();

		return match (CSSShorthandRegistry::getCodecInternal($shorthand)) {
			'box' => BoxAndLineShorthandCodec::serialize($shorthand, $values, $options),
			'bg' => BackgroundAndMaskShorthandCodec::serialize($shorthand, $values, $options),
			'font' => FontShorthandCodec::serialize($shorthand, $values, $options),
			'grid' => GridShorthandCodec::serialize($shorthand, $values, $options),
			'timing' => LayeredTimingShorthandCodec::serialize($shorthand, $values, $options),
			default => '',
		};
	}

	/**
	 * Serializes a declaration block into canonical shortest shorthands + remaining longhands
	 * in insertion order, respecting `!important` parity, pending-substitution orphans, and
	 * physical/logical cascade barriers.
	 *
	 * @param array<string, array{value: string, priority: string}> $declarations
	 * @param array<string, array{shorthand: string, value: string, id: int, kind: 'substitution'|'system-font'}> $pendingShorthands
	 * @return list<array{property: string, value: string, priority: string}>
	 */
	public static function serializeDeclarationBlock(
		array $declarations,
		array $pendingShorthands,
		bool $hasShorthandLonghand,
		?CSSParserOptions $options = null,
	): array {
		if ($declarations === []) {
			return [];
		}

		if (!$hasShorthandLonghand && $pendingShorthands === []) {
			$out = [];
			foreach ($declarations as $property => $decl) {
				$out[] = [
					'property' => $property,
					'value' => $decl['value'],
					'priority' => $decl['priority'],
				];
			}

			return $out;
		}

		$orderedProps = array_keys($declarations);
		$total = count($orderedProps);
		$positionMap = array_flip($orderedProps);
		$consumed = [];
		$result = [];

		$allLonghands = null;
		$allValue = '';
		$allFirstPos = null;
		$allPriority = '';
		if ($total >= CSSShorthandRegistry::getAllLonghandsCount()) {
			$allValue = self::serializeShorthand('all', $declarations, $pendingShorthands, $options);
			if ($allValue !== '') {
				$allLonghands = CSSShorthandRegistry::getAllLonghands();
				$allSet = array_flip($allLonghands);
				$allPriority = $declarations[$allLonghands[0]]['priority'];
				foreach ($orderedProps as $pos => $p) {
					if (isset($allSet[$p])) {
						$allFirstPos = $pos;
						break;
					}
				}
			}
		}

		for ($pos = 0; $pos < $total; $pos++) {
			$prop = $orderedProps[$pos];
			if (isset($consumed[$prop])) {
				continue;
			}

			if ($allLonghands !== null && $pos === $allFirstPos) {
				$result[] = [
					'property' => 'all',
					'value' => $allValue,
					'priority' => $allPriority,
				];
				foreach ($allLonghands as $lh) {
					$consumed[$lh] = true;
				}
				continue;
			}

			if (isset($pendingShorthands[$prop])) {
				$pInfo = $pendingShorthands[$prop];
				$sh = $pInfo['shorthand'];
				$shLonghands = CSSShorthandRegistry::getLonghands($sh);
				if ($shLonghands !== null
					&& self::extractShorthandValues(
						$sh,
						$shLonghands,
						$declarations,
						$pendingShorthands,
						$positionMap,
						$orderedProps,
						$consumed,
						$pInfo['id'],
					) !== null
				) {
					$result[] = [
						'property' => $sh,
						'value' => $pInfo['value'],
						'priority' => $declarations[$prop]['priority'],
					];
					foreach ($shLonghands as $lh) {
						$consumed[$lh] = true;
					}
				}
				// Orphaned pending-substitution/system-font longhands serialize as "" and are omitted.
				continue;
			}

			if (CSSShorthandRegistry::isShorthandLonghand($prop)) {
				$collapsed = false;
				foreach (CSSShorthandRegistry::getShorthandsForLonghand($prop) as $sh) {
					$shLonghands = CSSShorthandRegistry::getLonghands($sh);
					if ($shLonghands === null) {
						continue;
					}
					$extracted = self::extractShorthandValues(
						$sh,
						$shLonghands,
						$declarations,
						$pendingShorthands,
						$positionMap,
						$orderedProps,
						$consumed,
					);
					if ($extracted === null) {
						continue;
					}
					$serialized = is_string($extracted)
						? $extracted
						: self::serializeExtractedValues($sh, $extracted, $options);
					if ($serialized !== '') {
						$result[] = [
							'property' => $sh,
							'value' => $serialized,
							'priority' => $declarations[$prop]['priority'],
						];
						foreach ($shLonghands as $lh) {
							$consumed[$lh] = true;
						}
						$collapsed = true;
						break;
					}
				}
				if ($collapsed) {
					continue;
				}
			}

			$consumed[$prop] = true;
			$result[] = [
				'property' => $prop,
				'value' => $declarations[$prop]['value'],
				'priority' => $declarations[$prop]['priority'],
			];
		}

		return $result;
	}

	/**
	 * Extracts values (or returns a pending/CSS-wide string) while optionally verifying
	 * unconsumed status, uniform `!important`, and logical cascade barriers in a single pass.
	 *
	 * @param list<string> $longhands
	 * @param array<string, array{value: string, priority: string}> $declarations
	 * @param array<string, array{shorthand: string, value: string, id: int, kind: 'substitution'|'system-font'}> $pendingShorthands
	 * @param ?array<string, int> $positionMap
	 * @param ?list<string> $orderedProps
	 * @param ?array<string, true> $consumed
	 * @return string|array<string, string>|null
	 */
	private static function extractShorthandValues(
		string $shorthand,
		array $longhands,
		array $declarations,
		array $pendingShorthands,
		?array $positionMap = null,
		?array $orderedProps = null,
		?array $consumed = null,
		?int $requiredPendingId = null,
	): string|array|null {
		$firstLh = $longhands[0];
		if (!isset($declarations[$firstLh])) {
			return null;
		}

		$expectedPriority = $declarations[$firstLh]['priority'];
		$firstPending = $pendingShorthands[$firstLh] ?? null;

		if ($requiredPendingId !== null || $firstPending !== null) {
			if ($firstPending === null) {
				return null;
			}
			$pendingId = $requiredPendingId ?? $firstPending['id'];
			if ($firstPending['id'] !== $pendingId || $firstPending['shorthand'] !== $shorthand) {
				return null;
			}
			$minPos = PHP_INT_MAX;
			$maxPos = -1;
			foreach ($longhands as $lh) {
				if (($consumed !== null && isset($consumed[$lh]))
					|| !isset($declarations[$lh], $pendingShorthands[$lh])
					|| $pendingShorthands[$lh]['id'] !== $pendingId
					|| $declarations[$lh]['priority'] !== $expectedPriority
				) {
					return null;
				}
				if ($positionMap !== null) {
					$p = $positionMap[$lh];
					if ($p < $minPos) {
						$minPos = $p;
					}
					if ($p > $maxPos) {
						$maxPos = $p;
					}
				}
			}
			if ($orderedProps !== null && $consumed !== null && !self::passesLogicalBarrierCheck($shorthand, $minPos, $maxPos, $orderedProps, $consumed)) {
				return null;
			}

			return $firstPending['value'];
		}

		$firstLower = strtolower($declarations[$firstLh]['value']);
		$isCssWide = isset(CSSValueComponentParser::CSS_WIDE_KEYWORDS[$firstLower]);
		$values = [];
		$minPos = PHP_INT_MAX;
		$maxPos = -1;

		foreach ($longhands as $lh) {
			if (($consumed !== null && isset($consumed[$lh]))
				|| isset($pendingShorthands[$lh])
				|| !isset($declarations[$lh])
			) {
				return null;
			}
			$entry = $declarations[$lh];
			if ($entry['priority'] !== $expectedPriority) {
				return null;
			}
			if ($isCssWide) {
				if (strtolower($entry['value']) !== $firstLower) {
					return null;
				}
			} else {
				if (isset(CSSValueComponentParser::CSS_WIDE_KEYWORDS[strtolower($entry['value'])])) {
					return null;
				}
				$values[$lh] = $entry['value'];
			}
			if ($positionMap !== null) {
				$p = $positionMap[$lh];
				if ($p < $minPos) {
					$minPos = $p;
				}
				if ($p > $maxPos) {
					$maxPos = $p;
				}
			}
		}

		if ($orderedProps !== null && $consumed !== null && !self::passesLogicalBarrierCheck($shorthand, $minPos, $maxPos, $orderedProps, $consumed)) {
			return null;
		}
		if ($isCssWide) {
			return $firstLower;
		}
		if ($shorthand === 'all') {
			return null;
		}

		return $values;
	}

	/**
	 * Ensures no unconsumed property from an opposite physical/logical side of the same logical group
	 * appears between `$minPos` and `$maxPos` of the candidate shorthand's longhands.
	 *
	 * @param list<string> $orderedProps
	 * @param array<string, true> $consumed
	 */
	private static function passesLogicalBarrierCheck(
		string $shorthand,
		int $minPos,
		int $maxPos,
		array $orderedProps,
		array $consumed,
	): bool {
		if ($maxPos - $minPos <= 1) {
			return true;
		}
		$groupLogical = CSSShorthandRegistry::getShorthandLogicalGroupMapInternal($shorthand);
		if ($groupLogical === []) {
			return true;
		}

		$lhSet = CSSShorthandRegistry::getShorthandLonghandSetInternal($shorthand);

		for ($k = $minPos + 1; $k < $maxPos; $k++) {
			$mid = $orderedProps[$k];
			if (isset($lhSet[$mid]) || isset($consumed[$mid])) {
				continue;
			}
			$midGroup = CSSShorthandRegistry::getLogicalGroup($mid);
			if ($midGroup !== null
				&& isset($groupLogical[$midGroup['group']])
				&& $groupLogical[$midGroup['group']] !== $midGroup['logical']
			) {
				return false;
			}
		}

		return true;
	}
}
