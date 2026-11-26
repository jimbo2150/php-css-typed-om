<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM\Shorthand;

use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\Grammar\CSSValueComponentParser;
use Jimbo2150\PhpCssTypedOm\Grammar\CSSValueValidator;
use Jimbo2150\PhpCssTypedOm\Parser\CSSTokenType;

/**
 * Expands and canonically serializes `grid-row`, `grid-column`, `grid-area`,
 * `grid-template`, and `grid` shorthands.
 *
 * @phpstan-import-type ParsedComponent from CSSValueComponentParser
 * @internal
 */
final class GridShorthandCodec
{
	public static function supports(string $shorthand): bool
	{
		return match ($shorthand) {
			'grid-row', 'grid-column', 'grid-area', 'grid-template', 'grid' => true,
			default => false,
		};
	}

	/**
	 * @param list<ParsedComponent> $components
	 * @return ?array<string, string>
	 */
	public static function expand(
		string $shorthand,
		string $source,
		bool $hasComments,
		array $components,
		CSSParserOptions $options,
	): ?array {
		return match ($shorthand) {
			'grid-row' => self::expandGridLinePair(['grid-row-start', 'grid-row-end'], $source, $hasComments, $components, $options),
			'grid-column' => self::expandGridLinePair(['grid-column-start', 'grid-column-end'], $source, $hasComments, $components, $options),
			'grid-area' => self::expandGridArea($source, $hasComments, $components, $options),
			'grid-template' => self::expandGridTemplate($source, $hasComments, $components, $options),
			'grid' => self::expandGrid($source, $hasComments, $components, $options),
			default => null,
		};
	}

	/**
	 * @param array<string, string> $values
	 */
	public static function serialize(string $shorthand, array $values, CSSParserOptions $options): string
	{
		return match ($shorthand) {
			'grid-row' => self::serializeGridLinePair($values['grid-row-start'], $values['grid-row-end'], $options),
			'grid-column' => self::serializeGridLinePair($values['grid-column-start'], $values['grid-column-end'], $options),
			'grid-area' => self::serializeGridArea($values, $options),
			'grid-template' => self::serializeGridTemplate($values, $options),
			'grid' => self::serializeGrid($values, $options),
			default => '',
		};
	}

	/**
	 * @param list<string> $longhands
	 * @param list<ParsedComponent> $components
	 * @return ?array<string, string>
	 */
	private static function expandGridLinePair(
		array $longhands,
		string $source,
		bool $hasComments,
		array $components,
		CSSParserOptions $options,
	): ?array {
		$segments = ShorthandCodecHelper::splitByDelimiter($components, $source, CSSTokenType::Delim, '/');
		if ($segments === null || count($segments) > 2) {
			return null;
		}
		$first = ShorthandCodecHelper::joinComponents($source, $segments[0], $hasComments);
		if (isset($segments[1])) {
			$second = ShorthandCodecHelper::joinComponents($source, $segments[1], $hasComments);
		} else {
			$second = self::isBareCustomIdent($segments[0], $source, $hasComments, $options) ? $first : 'auto';
		}

		return [$longhands[0] => $first, $longhands[1] => $second];
	}

	private static function serializeGridLinePair(string $start, string $end, CSSParserOptions $options): string
	{
		$parsedStart = ShorthandCodecHelper::parseValue($start, $options);
		if ($parsedStart === null) {
			return '';
		}
		$isBare = self::isBareCustomIdent($parsedStart['components'], $parsedStart['source'], $parsedStart['hasComments'], $options);
		if ($isBare && $end === $start) {
			return $start;
		}
		if (!$isBare && $end === 'auto') {
			return $start;
		}

		return $start . ' / ' . $end;
	}

	/**
	 * @param list<ParsedComponent> $components
	 * @return ?array<string, string>
	 */
	private static function expandGridArea(
		string $source,
		bool $hasComments,
		array $components,
		CSSParserOptions $options,
	): ?array {
		$segments = ShorthandCodecHelper::splitByDelimiter($components, $source, CSSTokenType::Delim, '/');
		if ($segments === null || count($segments) > 4) {
			return null;
		}

		$s0 = ShorthandCodecHelper::joinComponents($source, $segments[0], $hasComments);
		$bare0 = self::isBareCustomIdent($segments[0], $source, $hasComments, $options);

		if (count($segments) === 1) {
			$fallback = $bare0 ? $s0 : 'auto';

			return [
				'grid-row-start' => $s0,
				'grid-column-start' => $fallback,
				'grid-row-end' => $fallback,
				'grid-column-end' => $fallback,
			];
		}

		$s1 = ShorthandCodecHelper::joinComponents($source, $segments[1], $hasComments);
		$bare1 = self::isBareCustomIdent($segments[1], $source, $hasComments, $options);

		if (count($segments) === 2) {
			return [
				'grid-row-start' => $s0,
				'grid-column-start' => $s1,
				'grid-row-end' => $bare0 ? $s0 : 'auto',
				'grid-column-end' => $bare1 ? $s1 : 'auto',
			];
		}

		$s2 = ShorthandCodecHelper::joinComponents($source, $segments[2], $hasComments);
		if (count($segments) === 3) {
			return [
				'grid-row-start' => $s0,
				'grid-column-start' => $s1,
				'grid-row-end' => $s2,
				'grid-column-end' => $bare1 ? $s1 : 'auto',
			];
		}

		$s3 = ShorthandCodecHelper::joinComponents($source, $segments[3], $hasComments);

		return [
			'grid-row-start' => $s0,
			'grid-column-start' => $s1,
			'grid-row-end' => $s2,
			'grid-column-end' => $s3,
		];
	}

	/**
	 * @param array<string, string> $values
	 */
	private static function serializeGridArea(array $values, CSSParserOptions $options): string
	{
		$rStart = $values['grid-row-start'];
		$cStart = $values['grid-column-start'];
		$rEnd = $values['grid-row-end'];
		$cEnd = $values['grid-column-end'];

		$p0 = ShorthandCodecHelper::parseValue($rStart, $options);
		$p1 = ShorthandCodecHelper::parseValue($cStart, $options);
		if ($p0 === null || $p1 === null) {
			return '';
		}
		$bare0 = self::isBareCustomIdent($p0['components'], $p0['source'], $p0['hasComments'], $options);
		$bare1 = self::isBareCustomIdent($p1['components'], $p1['source'], $p1['hasComments'], $options);

		$default0 = $bare0 ? $rStart : 'auto';
		$default1 = $bare1 ? $cStart : 'auto';

		if ($cStart === $default0 && $rEnd === $default0 && $cEnd === $default0) {
			return $rStart;
		}
		if ($rEnd === $default0 && $cEnd === $default1) {
			return $rStart . ' / ' . $cStart;
		}
		if ($cEnd === $default1) {
			return $rStart . ' / ' . $cStart . ' / ' . $rEnd;
		}

		return $rStart . ' / ' . $cStart . ' / ' . $rEnd . ' / ' . $cEnd;
	}

	/**
	 * @param list<ParsedComponent> $components
	 */
	private static function isBareCustomIdent(
		array $components,
		string $source,
		bool $hasComments,
		CSSParserOptions $options,
	): bool {
		if (count($components) !== 1) {
			return false;
		}
		$lower = ShorthandCodecHelper::identLower($components[0]);
		if ($lower === null || $lower === 'auto' || $lower === 'span') {
			return false;
		}

		return ShorthandCodecHelper::matchesGrammar('<custom-ident>', $source, $hasComments, $components, $options);
	}

	/**
	 * @param list<ParsedComponent> $components
	 * @return ?array<string, string>
	 */
	private static function expandGridTemplate(
		string $source,
		bool $hasComments,
		array $components,
		CSSParserOptions $options,
	): ?array {
		if (count($components) === 1 && ShorthandCodecHelper::isIdent($components[0], 'none')) {
			return [
				'grid-template-rows' => 'none',
				'grid-template-columns' => 'none',
				'grid-template-areas' => 'none',
			];
		}

		$halves = ShorthandCodecHelper::splitByDelimiter($components, $source, CSSTokenType::Delim, '/');
		if ($halves === null || count($halves) > 2) {
			return null;
		}

		$hasString = false;
		foreach ($halves[0] as $comp) {
			if (($comp['kind'] ?? '') === 'token' && ($comp['tokenType'] ?? null) === CSSTokenType::String) {
				$hasString = true;
				break;
			}
		}

		if (!$hasString) {
			if (count($halves) !== 2) {
				return null;
			}

			return [
				'grid-template-rows' => ShorthandCodecHelper::joinComponents($source, $halves[0], $hasComments),
				'grid-template-columns' => ShorthandCodecHelper::joinComponents($source, $halves[1], $hasComments),
				'grid-template-areas' => 'none',
			];
		}

		// Areas syntax: [ <line-names>? <string> <track-size>? <line-names>? ]+ [ / <explicit-track-list> ]?
		$cols = isset($halves[1]) ? ShorthandCodecHelper::joinComponents($source, $halves[1], $hasComments) : 'none';
		$left = $halves[0];
		$lCount = count($left);
		$areas = [];
		$rowsParts = [];
		$pendingNames = [];
		$i = 0;

		while ($i < $lCount) {
			// Optional leading line-names
			if (($left[$i]['kind'] ?? '') === 'bracket') {
				foreach ($left[$i]['components'] ?? [] as $idComp) {
					$pendingNames[] = ShorthandCodecHelper::componentText($source, $idComp, $hasComments);
				}
				$i++;
			}
			if ($i >= $lCount || ($left[$i]['kind'] ?? '') !== 'token' || ($left[$i]['tokenType'] ?? null) !== CSSTokenType::String) {
				return null;
			}
			$areas[] = ShorthandCodecHelper::componentText($source, $left[$i], $hasComments);
			$i++;

			if ($pendingNames !== []) {
				$rowsParts[] = '[' . implode(' ', $pendingNames) . ']';
				$pendingNames = [];
			}

			// Optional <track-size>
			if ($i < $lCount && ($left[$i]['kind'] ?? '') !== 'bracket') {
				$trackComp = $left[$i];
				if (ShorthandCodecHelper::matchesNonTerminal('track-size', $source, $hasComments, [$trackComp], $options)) {
					$rowsParts[] = ShorthandCodecHelper::componentText($source, $trackComp, $hasComments);
					$i++;
				} else {
					return null;
				}
			} else {
				$rowsParts[] = 'auto';
			}

			// Optional trailing line-names (merged with next row's leading line-names!)
			if ($i < $lCount && ($left[$i]['kind'] ?? '') === 'bracket') {
				foreach ($left[$i]['components'] ?? [] as $idComp) {
					$pendingNames[] = ShorthandCodecHelper::componentText($source, $idComp, $hasComments);
				}
				$i++;
			}
		}

		if ($pendingNames !== []) {
			$rowsParts[] = '[' . implode(' ', $pendingNames) . ']';
		}

		return [
			'grid-template-rows' => $rowsParts === [] ? 'none' : implode(' ', $rowsParts),
			'grid-template-columns' => $cols,
			'grid-template-areas' => implode(' ', $areas),
		];
	}

	/**
	 * @param array<string, string> $values
	 */
	private static function serializeGridTemplate(array $values, CSSParserOptions $options): string
	{
		$rows = $values['grid-template-rows'];
		$cols = $values['grid-template-columns'];
		$areas = $values['grid-template-areas'];

		if ($areas === 'none') {
			if ($rows === 'none' && $cols === 'none') {
				return 'none';
			}

			return $rows . ' / ' . $cols;
		}

		$parsedAreas = ShorthandCodecHelper::parseValue($areas, $options);
		$parsedRows = ShorthandCodecHelper::parseValue($rows, $options);
		if ($parsedAreas === null || $parsedRows === null) {
			return '';
		}
		if ($cols !== 'none') {
			$parsedCols = ShorthandCodecHelper::parseValue($cols, $options);
			if ($parsedCols === null || !ShorthandCodecHelper::matchesNonTerminal('explicit-track-list', $parsedCols['source'], $parsedCols['hasComments'], $parsedCols['components'], $options)) {
				return '';
			}
		}

		$areaStrings = [];
		foreach ($parsedAreas['components'] as $c) {
			if (($c['kind'] ?? '') !== 'token' || ($c['tokenType'] ?? null) !== CSSTokenType::String) {
				return '';
			}
			$areaStrings[] = ShorthandCodecHelper::componentText($parsedAreas['source'], $c, $parsedAreas['hasComments']);
		}

		// Parse explicit row tracks: must match N area strings with optional line-names
		$rComps = $parsedRows['components'];
		$rCount = count($rComps);
		$rIdx = 0;
		$rowTracks = [];
		while ($rIdx < $rCount) {
			$lineNames = null;
			if (($rComps[$rIdx]['kind'] ?? '') === 'bracket') {
				$lineNames = ShorthandCodecHelper::componentText($parsedRows['source'], $rComps[$rIdx], $parsedRows['hasComments']);
				$rIdx++;
			}
			if ($rIdx >= $rCount) {
				if ($lineNames !== null && $rowTracks !== []) {
					$rowTracks[count($rowTracks) - 1]['after'] = $lineNames;
					break;
				}

				return '';
			}
			$trackComp = $rComps[$rIdx];
			if (!ShorthandCodecHelper::matchesNonTerminal('track-size', $parsedRows['source'], $parsedRows['hasComments'], [$trackComp], $options)) {
				return '';
			}
			$rowTracks[] = [
				'before' => $lineNames,
				'size' => ShorthandCodecHelper::componentText($parsedRows['source'], $trackComp, $parsedRows['hasComments']),
				'after' => null,
			];
			$rIdx++;
		}
		if (count($rowTracks) !== count($areaStrings)) {
			return '';
		}

		$chunks = [];
		foreach ($areaStrings as $idx => $str) {
			$t = $rowTracks[$idx];
			$rowParts = [];
			if ($t['before'] !== null) {
				$rowParts[] = $t['before'];
			}
			$rowParts[] = $str;
			if ($t['size'] !== 'auto') {
				$rowParts[] = $t['size'];
			}
			if ($t['after'] !== null) {
				$rowParts[] = $t['after'];
			}
			$chunks[] = implode(' ', $rowParts);
		}

		$out = implode(' ', $chunks);
		if ($cols !== 'none') {
			$out .= ' / ' . $cols;
		}

		return CSSValueValidator::validateStandardProperty('grid-template', $out, $options) ? $out : '';
	}

	/**
	 * @param list<ParsedComponent> $components
	 * @return ?array<string, string>
	 */
	private static function expandGrid(
		string $source,
		bool $hasComments,
		array $components,
		CSSParserOptions $options,
	): ?array {
		$hasAutoFlow = false;
		foreach ($components as $comp) {
			if (ShorthandCodecHelper::isIdent($comp, 'auto-flow')) {
				$hasAutoFlow = true;
				break;
			}
		}

		if (!$hasAutoFlow) {
			$tpl = self::expandGridTemplate($source, $hasComments, $components, $options);
			if ($tpl === null) {
				return null;
			}
			$tpl['grid-auto-flow'] = 'row';
			$tpl['grid-auto-rows'] = 'auto';
			$tpl['grid-auto-columns'] = 'auto';

			return $tpl;
		}

		$halves = ShorthandCodecHelper::splitByDelimiter($components, $source, CSSTokenType::Delim, '/');
		if ($halves === null || count($halves) !== 2) {
			return null;
		}

		$left = $halves[0];
		$right = $halves[1];

		$leftFlow = self::extractAutoFlowClause($left, $source, $hasComments);
		if ($leftFlow !== null) {
			return [
				'grid-template-rows' => 'none',
				'grid-template-columns' => ShorthandCodecHelper::joinComponents($source, $right, $hasComments),
				'grid-template-areas' => 'none',
				'grid-auto-flow' => $leftFlow['dense'] ? 'row dense' : 'row',
				'grid-auto-rows' => $leftFlow['tracks'] ?? 'auto',
				'grid-auto-columns' => 'auto',
			];
		}

		$rightFlow = self::extractAutoFlowClause($right, $source, $hasComments);
		if ($rightFlow !== null) {
			return [
				'grid-template-rows' => ShorthandCodecHelper::joinComponents($source, $left, $hasComments),
				'grid-template-columns' => 'none',
				'grid-template-areas' => 'none',
				'grid-auto-flow' => $rightFlow['dense'] ? 'column dense' : 'column',
				'grid-auto-rows' => 'auto',
				'grid-auto-columns' => $rightFlow['tracks'] ?? 'auto',
			];
		}

		return null;
	}

	/**
	 * @param list<ParsedComponent> $clause
	 * @return ?array{dense: bool, tracks: ?string}
	 */
	private static function extractAutoFlowClause(array $clause, string $source, bool $hasComments): ?array
	{
		$c = count($clause);
		if ($c === 0) {
			return null;
		}
		$first = ShorthandCodecHelper::identLower($clause[0]);
		$second = $c >= 2 ? ShorthandCodecHelper::identLower($clause[1]) : null;

		$dense = false;
		$trackStart = null;
		if ($first === 'auto-flow') {
			$trackStart = 1;
			if ($second === 'dense') {
				$dense = true;
				$trackStart = 2;
			}
		} elseif ($first === 'dense' && $second === 'auto-flow') {
			$dense = true;
			$trackStart = 2;
		} else {
			return null;
		}

		$tracks = $trackStart < $c
			? ShorthandCodecHelper::joinComponents($source, array_slice($clause, $trackStart), $hasComments)
			: null;

		return ['dense' => $dense, 'tracks' => $tracks];
	}

	/**
	 * @param array<string, string> $values
	 */
	private static function serializeGrid(array $values, CSSParserOptions $options): string
	{
		$rows = $values['grid-template-rows'];
		$cols = $values['grid-template-columns'];
		$areas = $values['grid-template-areas'];
		$flow = $values['grid-auto-flow'];
		$autoRows = $values['grid-auto-rows'];
		$autoCols = $values['grid-auto-columns'];

		if ($flow === 'row' && $autoRows === 'auto' && $autoCols === 'auto') {
			return self::serializeGridTemplate($values, $options);
		}

		if ($areas !== 'none') {
			return '';
		}

		if ($cols === 'none' && $autoRows === 'auto' && ($flow === 'column' || $flow === 'column dense')) {
			$flowKw = $flow === 'column dense' ? 'auto-flow dense' : 'auto-flow';
			$right = $autoCols === 'auto' ? $flowKw : $flowKw . ' ' . $autoCols;
			$candidate = $rows . ' / ' . $right;

			return CSSValueValidator::validateStandardProperty('grid', $candidate, $options) ? $candidate : '';
		}

		if ($rows === 'none' && $autoCols === 'auto' && ($flow === 'row' || $flow === 'row dense')) {
			$flowKw = $flow === 'row dense' ? 'auto-flow dense' : 'auto-flow';
			$left = $autoRows === 'auto' ? $flowKw : $flowKw . ' ' . $autoRows;
			$candidate = $left . ' / ' . $cols;

			return CSSValueValidator::validateStandardProperty('grid', $candidate, $options) ? $candidate : '';
		}

		return '';
	}
}
