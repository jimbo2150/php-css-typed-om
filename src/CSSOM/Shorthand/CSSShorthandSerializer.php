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

		$firstLh = $longhands[0];
		if (!isset($declarations[$firstLh])) {
			return '';
		}
		$expectedPriority = $declarations[$firstLh]['priority'];
		$firstPending = $pendingShorthands[$firstLh] ?? null;

		if ($firstPending !== null) {
			if ($firstPending['shorthand'] !== $shorthand) {
				return '';
			}
			$pendingId = $firstPending['id'];
			foreach ($longhands as $lh) {
				if (!isset($declarations[$lh], $pendingShorthands[$lh])) {
					return '';
				}
				if ($declarations[$lh]['priority'] !== $expectedPriority || $pendingShorthands[$lh]['id'] !== $pendingId) {
					return '';
				}
			}

			return $firstPending['value'];
		}

		$values = [];
		$firstLower = strtolower($declarations[$firstLh]['value']);
		$isCssWide = isset(CSSValueComponentParser::CSS_WIDE_KEYWORDS[$firstLower]);

		foreach ($longhands as $lh) {
			if (!isset($declarations[$lh]) || isset($pendingShorthands[$lh])) {
				return '';
			}
			$entry = $declarations[$lh];
			if ($entry['priority'] !== $expectedPriority) {
				return '';
			}
			if ($isCssWide) {
				if (strtolower($entry['value']) !== $firstLower) {
					return '';
				}
			} elseif (isset(CSSValueComponentParser::CSS_WIDE_KEYWORDS[strtolower($entry['value'])])) {
				return '';
			}
			$values[$lh] = $entry['value'];
		}

		if ($isCssWide) {
			return $firstLower;
		}
		if ($shorthand === 'all') {
			return '';
		}

		$options ??= CSSParserOptions::default();

		if (BoxAndLineShorthandCodec::supports($shorthand)) {
			return BoxAndLineShorthandCodec::serialize($shorthand, $values, $options);
		}
		if (BackgroundAndMaskShorthandCodec::supports($shorthand)) {
			return BackgroundAndMaskShorthandCodec::serialize($shorthand, $values, $options);
		}
		if (FontShorthandCodec::supports($shorthand)) {
			return FontShorthandCodec::serialize($shorthand, $values, $options);
		}
		if (GridShorthandCodec::supports($shorthand)) {
			return GridShorthandCodec::serialize($shorthand, $values, $options);
		}
		if (LayeredTimingShorthandCodec::supports($shorthand)) {
			return LayeredTimingShorthandCodec::serialize($shorthand, $values, $options);
		}

		return '';
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
					&& self::canCollapsePending($sh, $shLonghands, $pInfo['id'], $declarations, $pendingShorthands, $positionMap, $orderedProps, $consumed)
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
					if ($shLonghands === null
						|| !self::canAttemptShorthand($sh, $shLonghands, $declarations, $pendingShorthands, $positionMap, $orderedProps, $consumed)
					) {
						continue;
					}
					$serialized = self::serializeShorthand($sh, $declarations, $pendingShorthands, $options);
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
	 * @param list<string> $longhands
	 * @param array<string, array{value: string, priority: string}> $declarations
	 * @param array<string, array{shorthand: string, value: string, id: int, kind: 'substitution'|'system-font'}> $pendingShorthands
	 * @param array<string, int> $positionMap
	 * @param list<string> $orderedProps
	 * @param array<string, true> $consumed
	 */
	private static function canCollapsePending(
		string $shorthand,
		array $longhands,
		int $pendingId,
		array $declarations,
		array $pendingShorthands,
		array $positionMap,
		array $orderedProps,
		array $consumed,
	): bool {
		$firstLh = $longhands[0];
		if (!isset($declarations[$firstLh])) {
			return false;
		}
		$priority = $declarations[$firstLh]['priority'];
		$minPos = PHP_INT_MAX;
		$maxPos = -1;

		foreach ($longhands as $lh) {
			if (isset($consumed[$lh])
				|| !isset($declarations[$lh], $pendingShorthands[$lh])
				|| $pendingShorthands[$lh]['id'] !== $pendingId
				|| $declarations[$lh]['priority'] !== $priority
			) {
				return false;
			}
			$p = $positionMap[$lh];
			if ($p < $minPos) {
				$minPos = $p;
			}
			if ($p > $maxPos) {
				$maxPos = $p;
			}
		}

		return self::passesLogicalBarrierCheck($shorthand, $longhands, $minPos, $maxPos, $orderedProps, $consumed);
	}

	/**
	 * @param list<string> $longhands
	 * @param array<string, array{value: string, priority: string}> $declarations
	 * @param array<string, array{shorthand: string, value: string, id: int, kind: 'substitution'|'system-font'}> $pendingShorthands
	 * @param array<string, int> $positionMap
	 * @param list<string> $orderedProps
	 * @param array<string, true> $consumed
	 */
	private static function canAttemptShorthand(
		string $shorthand,
		array $longhands,
		array $declarations,
		array $pendingShorthands,
		array $positionMap,
		array $orderedProps,
		array $consumed,
	): bool {
		$firstLh = $longhands[0];
		if (!isset($declarations[$firstLh])) {
			return false;
		}
		$priority = $declarations[$firstLh]['priority'];
		$minPos = PHP_INT_MAX;
		$maxPos = -1;

		foreach ($longhands as $lh) {
			if (isset($consumed[$lh])
				|| isset($pendingShorthands[$lh])
				|| !isset($declarations[$lh])
				|| $declarations[$lh]['priority'] !== $priority
			) {
				return false;
			}
			$p = $positionMap[$lh];
			if ($p < $minPos) {
				$minPos = $p;
			}
			if ($p > $maxPos) {
				$maxPos = $p;
			}
		}

		return self::passesLogicalBarrierCheck($shorthand, $longhands, $minPos, $maxPos, $orderedProps, $consumed);
	}

	/**
	 * Ensures no unconsumed property from an opposite physical/logical side of the same logical group
	 * appears between `$minPos` and `$maxPos` of the candidate shorthand's longhands.
	 *
	 * @param list<string> $longhands
	 * @param list<string> $orderedProps
	 * @param array<string, true> $consumed
	 */
	private static function passesLogicalBarrierCheck(
		string $shorthand,
		array $longhands,
		int $minPos,
		int $maxPos,
		array $orderedProps,
		array $consumed,
	): bool {
		if ($maxPos - $minPos <= 1) {
			return true;
		}
		$groups = CSSShorthandRegistry::getLogicalGroupsForShorthand($shorthand);
		if ($groups === []) {
			return true;
		}

		$groupLogical = [];
		foreach ($groups as $g) {
			$groupLogical[$g['group']] = $g['logical'];
		}
		$lhSet = array_flip($longhands);

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
