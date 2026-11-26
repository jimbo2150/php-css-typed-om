<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM\Shorthand;

use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\Grammar\CSSValueComponentParser;
use Jimbo2150\PhpCssTypedOm\Parser\CSSTokenType;

/**
 * Expands and canonically serializes multi-layer timing and timeline shorthands:
 * `transition`, `animation`, `animation-range`, `scroll-timeline`, and `view-timeline`.
 *
 * @phpstan-import-type ParsedComponent from CSSValueComponentParser
 * @internal
 */
final class LayeredTimingShorthandCodec
{
	private const array EASING_KEYWORDS = [
		'linear' => true,
		'ease' => true,
		'ease-in' => true,
		'ease-out' => true,
		'ease-in-out' => true,
		'step-start' => true,
		'step-end' => true,
	];

	private const array DIRECTION_KEYWORDS = [
		'normal' => true,
		'reverse' => true,
		'alternate' => true,
		'alternate-reverse' => true,
	];

	private const array FILL_MODE_KEYWORDS = [
		'none' => true,
		'forwards' => true,
		'backwards' => true,
		'both' => true,
	];

	private const array PLAY_STATE_KEYWORDS = [
		'running' => true,
		'paused' => true,
	];

	private const array TIMELINE_AXIS_KEYWORDS = [
		'block' => true,
		'inline' => true,
		'x' => true,
		'y' => true,
	];

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
			'transition' => self::expandTransition($source, $hasComments, $components, $options),
			'animation' => self::expandAnimation($source, $hasComments, $components, $options),
			'animation-range' => self::expandAnimationRange($source, $hasComments, $components, $options),
			'scroll-timeline' => self::expandScrollTimeline($source, $hasComments, $components),
			'view-timeline' => self::expandViewTimeline($source, $hasComments, $components),
			default => null,
		};
	}

	/**
	 * @param array<string, string> $values
	 */
	public static function serialize(string $shorthand, array $values, CSSParserOptions $options): string
	{
		return match ($shorthand) {
			'transition' => self::serializeTransition($values, $options),
			'animation' => self::serializeAnimation($values, $options),
			'animation-range' => self::serializeAnimationRange($values, $options),
			'scroll-timeline' => self::serializeScrollTimeline($values, $options),
			'view-timeline' => self::serializeViewTimeline($values, $options),
			default => '',
		};
	}

	/**
	 * @param list<ParsedComponent> $components
	 * @return ?array<string, string>
	 */
	private static function expandTransition(
		string $source,
		bool $hasComments,
		array $components,
		CSSParserOptions $options,
	): ?array {
		$layers = ShorthandCodecHelper::splitByDelimiter($components, $source, CSSTokenType::Comma);
		if ($layers === null) {
			return null;
		}

		$props = [];
		$durations = [];
		$timings = [];
		$delays = [];
		$behaviors = [];

		foreach ($layers as $layer) {
			$prop = null;
			$duration = null;
			$timing = null;
			$delay = null;
			$behavior = null;

			foreach ($layer as $comp) {
				$lower = ShorthandCodecHelper::identLower($comp);
				$text = ShorthandCodecHelper::componentText($source, $comp, $hasComments);

				if ($duration === null && ShorthandCodecHelper::matchesProperty('transition-duration', $source, $hasComments, [$comp], $options)) {
					$duration = $text;
				} elseif ($delay === null && ShorthandCodecHelper::matchesProperty('transition-delay', $source, $hasComments, [$comp], $options)) {
					$delay = $text;
				} elseif ($timing === null && self::isEasingComponent($comp, $source, $hasComments, $options)) {
					$timing = $text;
				} elseif ($behavior === null && ($lower === 'normal' || $lower === 'allow-discrete')) {
					$behavior = $lower;
				} elseif ($prop === null && ($lower === 'none' || $lower === 'all' || ShorthandCodecHelper::matchesNonTerminal('single-transition-property', $source, $hasComments, [$comp], $options))) {
					$prop = $text;
				} else {
					return null;
				}
			}

			$props[] = $prop ?? 'all';
			$durations[] = $duration ?? '0s';
			$timings[] = $timing ?? 'ease';
			$delays[] = $delay ?? '0s';
			$behaviors[] = $behavior ?? 'normal';
		}

		return [
			'transition-property' => implode(', ', $props),
			'transition-duration' => implode(', ', $durations),
			'transition-timing-function' => implode(', ', $timings),
			'transition-delay' => implode(', ', $delays),
			'transition-behavior' => implode(', ', $behaviors),
		];
	}

	/**
	 * @param array<string, string> $values
	 */
	private static function serializeTransition(array $values, CSSParserOptions $options): string
	{
		$split = ShorthandCodecHelper::splitCommaLayers([
			'prop' => $values['transition-property'],
			'dur' => $values['transition-duration'],
			'timing' => $values['transition-timing-function'],
			'delay' => $values['transition-delay'],
			'behav' => $values['transition-behavior'],
		], $options, false);
		if ($split === null) {
			return '';
		}

		$count = $split['count'];
		$out = [];
		for ($i = 0; $i < $count; $i++) {
			$prop = $split['text']['prop'][$i];
			$dur = $split['text']['dur'][$i];
			$timing = $split['text']['timing'][$i];
			$delay = $split['text']['delay'][$i];
			$behav = $split['text']['behav'][$i];

			$parts = [];
			if ($prop !== 'all') {
				$parts[] = $prop;
			}
			$emitDur = $dur !== '0s' || $delay !== '0s';
			if ($emitDur) {
				$parts[] = $dur;
			}
			$propLower = strtolower($prop);
			$emitTiming = $timing !== 'ease' || ($prop !== 'all' && isset(self::EASING_KEYWORDS[$propLower]));
			if ($emitTiming) {
				$parts[] = $timing;
			}
			if ($delay !== '0s') {
				$parts[] = $delay;
			}
			$emitBehav = $behav !== 'normal' || $propLower === 'normal' || $propLower === 'allow-discrete';
			if ($emitBehav) {
				$parts[] = $behav;
			}

			$out[] = $parts === [] ? 'all' : implode(' ', $parts);
		}

		return implode(', ', $out);
	}

	/**
	 * @param list<ParsedComponent> $components
	 * @return ?array<string, string>
	 */
	private static function expandAnimation(
		string $source,
		bool $hasComments,
		array $components,
		CSSParserOptions $options,
	): ?array {
		$layers = ShorthandCodecHelper::splitByDelimiter($components, $source, CSSTokenType::Comma);
		if ($layers === null) {
			return null;
		}

		$durations = [];
		$timings = [];
		$delays = [];
		$iters = [];
		$dirs = [];
		$fills = [];
		$plays = [];
		$names = [];
		$timelines = [];

		foreach ($layers as $layer) {
			$duration = null;
			$timing = null;
			$delay = null;
			$iter = null;
			$dir = null;
			$fill = null;
			$play = null;
			$timeline = null;
			$nameCandidates = [];

			foreach ($layer as $comp) {
				$lower = ShorthandCodecHelper::identLower($comp);
				$text = ShorthandCodecHelper::componentText($source, $comp, $hasComments);

				if ($duration === null && ($lower === 'auto' || ShorthandCodecHelper::matchesGrammar('<time [0s,∞]>', $source, $hasComments, [$comp], $options))) {
					$duration = $text;
				} elseif ($delay === null && ShorthandCodecHelper::matchesProperty('animation-delay', $source, $hasComments, [$comp], $options)) {
					$delay = $text;
				} elseif ($timing === null && self::isEasingComponent($comp, $source, $hasComments, $options)) {
					$timing = $text;
				} elseif ($iter === null && ShorthandCodecHelper::matchesNonTerminal('single-animation-iteration-count', $source, $hasComments, [$comp], $options)) {
					$iter = $text;
				} elseif ($dir === null && $lower !== null && isset(self::DIRECTION_KEYWORDS[$lower])) {
					$dir = $lower;
				} elseif ($fill === null && $lower !== null && isset(self::FILL_MODE_KEYWORDS[$lower])) {
					$fill = $lower;
				} elseif ($play === null && $lower !== null && isset(self::PLAY_STATE_KEYWORDS[$lower])) {
					$play = $lower;
				} elseif ($timeline === null && self::isExplicitAnimationTimelineComponent($comp, $lower)) {
					$timeline = $text;
				} elseif (ShorthandCodecHelper::matchesProperty('animation-name', $source, $hasComments, [$comp], $options)) {
					$nameCandidates[] = ['text' => $text, 'lower' => $lower];
				} else {
					return null;
				}
			}

			// Assign last unmatched ident/string to animation-name; any extra earlier 'none' or dashed-ident resolves timeline/name
			$name = 'none';
			if (count($nameCandidates) === 1) {
				$name = $nameCandidates[0]['text'];
			} elseif (count($nameCandidates) === 2 && $timeline === null) {
				$c0 = $nameCandidates[0];
				$c1 = $nameCandidates[1];
				if ($c0['lower'] === 'none') {
					$name = 'none';
					$timeline = $c1['text'];
				} elseif (($c1['text'] !== '' && str_starts_with($c1['text'], '--')) || $c1['lower'] === 'none') {
					$name = $c0['text'];
					$timeline = $c1['text'];
				} else {
					return null;
				}
			} elseif ($nameCandidates !== []) {
				return null;
			}

			$durations[] = $duration ?? 'auto';
			$timings[] = $timing ?? 'ease';
			$delays[] = $delay ?? '0s';
			$iters[] = $iter ?? '1';
			$dirs[] = $dir ?? 'normal';
			$fills[] = $fill ?? 'none';
			$plays[] = $play ?? 'running';
			$names[] = $name;
			$timelines[] = $timeline ?? 'auto';
		}

		$count = count($layers);
		$rangeNormals = implode(', ', array_fill(0, $count, 'normal'));

		return [
			'animation-duration' => implode(', ', $durations),
			'animation-timing-function' => implode(', ', $timings),
			'animation-delay' => implode(', ', $delays),
			'animation-iteration-count' => implode(', ', $iters),
			'animation-direction' => implode(', ', $dirs),
			'animation-fill-mode' => implode(', ', $fills),
			'animation-play-state' => implode(', ', $plays),
			'animation-name' => implode(', ', $names),
			'animation-timeline' => implode(', ', $timelines),
			'animation-range-start' => $rangeNormals,
			'animation-range-end' => $rangeNormals,
		];
	}

	/**
	 * @param array<string, string> $values
	 */
	private static function serializeAnimation(array $values, CSSParserOptions $options): string
	{
		$split = ShorthandCodecHelper::splitCommaLayers([
			'dur' => $values['animation-duration'],
			'timing' => $values['animation-timing-function'],
			'delay' => $values['animation-delay'],
			'iter' => $values['animation-iteration-count'],
			'dir' => $values['animation-direction'],
			'fill' => $values['animation-fill-mode'],
			'play' => $values['animation-play-state'],
			'name' => $values['animation-name'],
			'timeline' => $values['animation-timeline'],
		], $options, false);
		if ($split === null) {
			return '';
		}

		$count = $split['count'];
		$rawRangeStart = $values['animation-range-start'];
		$rawRangeEnd = $values['animation-range-end'];
		$bothSingleNormal = $count === 1
			&& strtolower($rawRangeStart) === 'normal'
			&& strtolower($rawRangeEnd) === 'normal';
		if (!$bothSingleNormal) {
			$parsedStart = ShorthandCodecHelper::parseValue($rawRangeStart, $options);
			$parsedEnd = ShorthandCodecHelper::parseValue($rawRangeEnd, $options);
			if ($parsedStart === null || $parsedEnd === null) {
				return '';
			}
			$startComps = ShorthandCodecHelper::splitByDelimiter($parsedStart['components'], $parsedStart['source'], CSSTokenType::Comma);
			$endComps = ShorthandCodecHelper::splitByDelimiter($parsedEnd['components'], $parsedEnd['source'], CSSTokenType::Comma);
			if ($startComps === null || $endComps === null || count($startComps) !== $count || count($endComps) !== $count) {
				return '';
			}
			for ($i = 0; $i < $count; $i++) {
				if (!ShorthandCodecHelper::isIdent($startComps[$i][0], 'normal') || count($startComps[$i]) !== 1
					|| !ShorthandCodecHelper::isIdent($endComps[$i][0], 'normal') || count($endComps[$i]) !== 1
				) {
					return '';
				}
			}
		}

		$out = [];
		for ($i = 0; $i < $count; $i++) {
			$dur = $split['text']['dur'][$i];
			$timing = $split['text']['timing'][$i];
			$delay = $split['text']['delay'][$i];
			$iter = $split['text']['iter'][$i];
			$dir = $split['text']['dir'][$i];
			$fill = $split['text']['fill'][$i];
			$play = $split['text']['play'][$i];
			$name = $split['text']['name'][$i];
			$timeline = $split['text']['timeline'][$i];

			$nameLower = strtolower($name);
			if ($nameLower === 'auto') {
				return '';
			}
			$parts = [];

			$emitDur = $dur !== 'auto' || $delay !== '0s';
			if ($emitDur) {
				$parts[] = $dur;
			}
			$emitTiming = $timing !== 'ease' || isset(self::EASING_KEYWORDS[$nameLower]);
			if ($emitTiming) {
				$parts[] = $timing;
			}
			if ($delay !== '0s') {
				$parts[] = $delay;
			}
			$emitIter = $iter !== '1' || $nameLower === 'infinite';
			if ($emitIter) {
				$parts[] = $iter;
			}
			$emitDir = $dir !== 'normal' || isset(self::DIRECTION_KEYWORDS[$nameLower]);
			if ($emitDir) {
				$parts[] = $dir;
			}
			$emitFill = $fill !== 'none' || (isset(self::FILL_MODE_KEYWORDS[$nameLower]) && $nameLower !== 'none');
			if ($emitFill) {
				$parts[] = $fill;
			}
			$emitPlay = $play !== 'running' || isset(self::PLAY_STATE_KEYWORDS[$nameLower]);
			if ($emitPlay) {
				$parts[] = $play;
			}

			$timelineNeedsExplicitName = $name === 'none' && ($timeline === 'none' || str_starts_with($timeline, '--'));
			if ($name !== 'none' || $timelineNeedsExplicitName) {
				$parts[] = $name;
			}
			if ($timeline !== 'auto') {
				$parts[] = $timeline;
			}

			$out[] = $parts === [] ? 'none' : implode(' ', $parts);
		}

		return implode(', ', $out);
	}

	/**
	 * @param ParsedComponent $comp
	 */
	private static function isEasingComponent(
		array $comp,
		string $source,
		bool $hasComments,
		CSSParserOptions $options,
	): bool {
		$lower = ShorthandCodecHelper::identLower($comp);
		if ($lower !== null) {
			return isset(self::EASING_KEYWORDS[$lower]);
		}
		if (($comp['kind'] ?? '') === 'function') {
			$fn = (string) ($comp['name'] ?? '');
			if ($fn === 'steps' || $fn === 'cubic-bezier' || $fn === 'linear') {
				return ShorthandCodecHelper::matchesNonTerminal('easing-function', $source, $hasComments, [$comp], $options);
			}
		}

		return false;
	}

	/**
	 * @param ParsedComponent $comp
	 */
	private static function isExplicitAnimationTimelineComponent(array $comp, ?string $lower): bool
	{
		if ($lower === 'auto') {
			return true;
		}
		if (($comp['kind'] ?? '') === 'function') {
			$fn = (string) ($comp['name'] ?? '');

			return $fn === 'scroll' || $fn === 'view';
		}

		return false;
	}

	/**
	 * @param list<ParsedComponent> $components
	 * @return ?array<string, string>
	 */
	private static function expandAnimationRange(
		string $source,
		bool $hasComments,
		array $components,
		CSSParserOptions $options,
	): ?array {
		$layers = ShorthandCodecHelper::splitByDelimiter($components, $source, CSSTokenType::Comma);
		if ($layers === null) {
			return null;
		}
		$starts = [];
		$ends = [];

		foreach ($layers as $layer) {
			$pair = self::splitAnimationRangeLayer($layer, $source, $hasComments, $options);
			if ($pair === null) {
				return null;
			}
			$starts[] = $pair[0];
			$ends[] = $pair[1];
		}

		return [
			'animation-range-start' => implode(', ', $starts),
			'animation-range-end' => implode(', ', $ends),
		];
	}

	/**
	 * @param list<ParsedComponent> $layer
	 * @return ?array{string, string}
	 */
	private static function splitAnimationRangeLayer(
		array $layer,
		string $source,
		bool $hasComments,
		CSSParserOptions $options,
	): ?array {
		if (ShorthandCodecHelper::matchesNonTerminal('timeline-range-item', $source, $hasComments, $layer, $options)) {
			$startStr = ShorthandCodecHelper::joinComponents($source, $layer, $hasComments);
			$firstLower = ShorthandCodecHelper::identLower($layer[0]);
			$endStr = ($firstLower !== null && $firstLower !== 'normal' && ShorthandCodecHelper::matchesNonTerminal('timeline-range-name', $source, $hasComments, [$layer[0]], $options))
				? $firstLower
				: 'normal';

			return [$startStr, $endStr];
		}

		$count = count($layer);
		for ($split = 1; $split < $count; $split++) {
			$a = array_slice($layer, 0, $split);
			$b = array_slice($layer, $split);
			if (ShorthandCodecHelper::matchesNonTerminal('timeline-range-item', $source, $hasComments, $a, $options)
				&& ShorthandCodecHelper::matchesNonTerminal('timeline-range-item', $source, $hasComments, $b, $options)
			) {
				return [
					ShorthandCodecHelper::joinComponents($source, $a, $hasComments),
					ShorthandCodecHelper::joinComponents($source, $b, $hasComments),
				];
			}
		}

		return null;
	}

	/**
	 * @param array<string, string> $values
	 */
	private static function serializeAnimationRange(array $values, CSSParserOptions $options): string
	{
		$split = ShorthandCodecHelper::splitCommaLayers([
			'start' => $values['animation-range-start'],
			'end' => $values['animation-range-end'],
		], $options);
		if ($split === null) {
			return '';
		}

		$out = [];
		for ($i = 0; $i < $split['count']; $i++) {
			$start = $split['text']['start'][$i];
			$end = $split['text']['end'][$i];
			$startComps = $split['comps']['start'][$i];
			$firstLower = ShorthandCodecHelper::identLower($startComps[0]);
			$impliedEnd = ($firstLower !== null && $firstLower !== 'normal' && ShorthandCodecHelper::matchesNonTerminal('timeline-range-name', $split['parsed']['start']['source'], $split['parsed']['start']['hasComments'], [$startComps[0]], $options))
				? $firstLower
				: 'normal';

			$out[] = $end === $impliedEnd ? $start : $start . ' ' . $end;
		}

		return implode(', ', $out);
	}

	/**
	 * @param list<ParsedComponent> $components
	 * @return ?array<string, string>
	 */
	private static function expandScrollTimeline(string $source, bool $hasComments, array $components): ?array
	{
		$layers = ShorthandCodecHelper::splitByDelimiter($components, $source, CSSTokenType::Comma);
		if ($layers === null) {
			return null;
		}
		$names = [];
		$axes = [];
		foreach ($layers as $layer) {
			if (count($layer) < 1 || count($layer) > 2) {
				return null;
			}
			$names[] = ShorthandCodecHelper::componentText($source, $layer[0], $hasComments);
			$axes[] = isset($layer[1]) ? strtolower(ShorthandCodecHelper::componentText($source, $layer[1], $hasComments)) : 'block';
		}

		return [
			'scroll-timeline-name' => implode(', ', $names),
			'scroll-timeline-axis' => implode(', ', $axes),
		];
	}

	/**
	 * @param array<string, string> $values
	 */
	private static function serializeScrollTimeline(array $values, CSSParserOptions $options): string
	{
		$split = ShorthandCodecHelper::splitCommaLayers([
			'name' => $values['scroll-timeline-name'],
			'axis' => $values['scroll-timeline-axis'],
		], $options, false);
		if ($split === null) {
			return '';
		}
		$out = [];
		for ($i = 0; $i < $split['count']; $i++) {
			$name = $split['text']['name'][$i];
			$axis = $split['text']['axis'][$i];
			$out[] = $axis === 'block' ? $name : $name . ' ' . $axis;
		}

		return implode(', ', $out);
	}

	/**
	 * @param list<ParsedComponent> $components
	 * @return ?array<string, string>
	 */
	private static function expandViewTimeline(string $source, bool $hasComments, array $components): ?array
	{
		$layers = ShorthandCodecHelper::splitByDelimiter($components, $source, CSSTokenType::Comma);
		if ($layers === null) {
			return null;
		}
		$names = [];
		$axes = [];
		$insets = [];
		foreach ($layers as $layer) {
			$c = count($layer);
			if ($c < 1) {
				return null;
			}
			$names[] = ShorthandCodecHelper::componentText($source, $layer[0], $hasComments);
			$idx = 1;
			$axis = 'block';
			if ($idx < $c) {
				$lower = ShorthandCodecHelper::identLower($layer[$idx]);
				if ($lower !== null && isset(self::TIMELINE_AXIS_KEYWORDS[$lower])) {
					$axis = $lower;
					$idx++;
				}
			}
			$axes[] = $axis;
			$insets[] = $idx < $c
				? ShorthandCodecHelper::joinComponents($source, array_slice($layer, $idx), $hasComments)
				: 'auto';
		}

		return [
			'view-timeline-name' => implode(', ', $names),
			'view-timeline-axis' => implode(', ', $axes),
			'view-timeline-inset' => implode(', ', $insets),
		];
	}

	/**
	 * @param array<string, string> $values
	 */
	private static function serializeViewTimeline(array $values, CSSParserOptions $options): string
	{
		$split = ShorthandCodecHelper::splitCommaLayers([
			'name' => $values['view-timeline-name'],
			'axis' => $values['view-timeline-axis'],
			'inset' => $values['view-timeline-inset'],
		], $options, false);
		if ($split === null) {
			return '';
		}
		$out = [];
		for ($i = 0; $i < $split['count']; $i++) {
			$name = $split['text']['name'][$i];
			$axis = $split['text']['axis'][$i];
			$inset = $split['text']['inset'][$i];

			$parts = [$name];
			if ($axis !== 'block') {
				$parts[] = $axis;
			}
			if ($inset !== 'auto') {
				$parts[] = $inset;
			}
			$out[] = implode(' ', $parts);
		}

		return implode(', ', $out);
	}
}
