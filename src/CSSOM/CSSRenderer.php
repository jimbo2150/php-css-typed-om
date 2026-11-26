<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use LogicException;
use RuntimeException;

/**
 * Renders CSSOM stylesheets and rules with deterministic formatting.
 *
 * Opaque rules retain their source verbatim because their syntax is not understood.
 */
final class CSSRenderer
{
	private const string INDENT = '  ';
	public const int MAX_RENDER_DEPTH = 512;

	private const int FRAME_ENTER = 0;
	private const int FRAME_CLOSE = 1;

	/** @var array<int, string> */
	private static array $indentCache = [0 => ''];

	public static function render(CSSStyleSheet|CSSRule $source, ?CSSRenderOptions $options = null): string
	{
		$options ??= new CSSRenderOptions();
		$pretty = $options->mode === CSSRenderMode::Pretty;
		$callback = $options->ruleCallback;

		$result = $callback === null
			? self::renderDirect($source, $options, $pretty)
			: self::renderWithCallback($source, $options, $pretty, $callback);

		if ($options->escapeHtmlStyleTag && $result !== '' && stripos($result, '</style') !== false) {
			$escaped = preg_replace('/<\/(style(?=[\t\n\f\r \/>]|$))/i', '<\/$1', $result);
			if ($escaped === null) {
				throw new RuntimeException('Failed to escape closing </style tags in CSS output.');
			}
			return $escaped;
		}

		return $result;
	}

	private static function renderDirect(CSSStyleSheet|CSSRule $source, CSSRenderOptions $options, bool $pretty): string
	{
		$chunks = [];
		$containerEmitted = [false];
		$topEmittedIdx = 0;
		$visited = [];

		if ($source instanceof CSSStyleSheet) {
			foreach ($source->getCssRules()->getRulesRaw() as $topRule) {
				if (!$topRule instanceof CSSGroupingRule || $topRule->getCssRules()->getRulesRaw() === []) {
					$id = spl_object_id($topRule);
					if (isset($visited[$id])) {
						throw new LogicException('Cannot serialize a cyclic or DAG-shared CSS rule tree.');
					}
					$visited[$id] = true;
					$rendered = self::renderFlatRule($topRule, $options, $pretty, 0);
					if ($rendered !== '') {
						if ($pretty && $containerEmitted[0]) {
							$chunks[] = "\n";
						}
						$chunks[] = $rendered;
						$containerEmitted[0] = true;
					}
					continue;
				}
				self::processDirectWorkStack($topRule, $options, $pretty, $chunks, $containerEmitted, $topEmittedIdx, $visited);
			}
			return count($chunks) === 1 ? $chunks[0] : implode('', $chunks);
		}

		if (!$source instanceof CSSGroupingRule || $source->getCssRules()->getRulesRaw() === []) {
			return self::renderFlatRule($source, $options, $pretty, 0);
		}

		self::processDirectWorkStack($source, $options, $pretty, $chunks, $containerEmitted, $topEmittedIdx, $visited);
		return count($chunks) === 1 ? $chunks[0] : implode('', $chunks);
	}

	/**
	 * @param array<int, string> $chunks
	 * @param array<int, bool> $containerEmitted
	 * @param array<int, true> $visited
	 */
	private static function processDirectWorkStack(
		CSSGroupingRule $rootRule,
		CSSRenderOptions $options,
		bool $pretty,
		array &$chunks,
		array &$containerEmitted,
		int &$topEmittedIdx,
		array &$visited,
	): void {
		$workStack = [[self::FRAME_ENTER, $rootRule, 0]];

		while ($workStack !== []) {
			$frame = array_pop($workStack);
			if ($frame[0] === self::FRAME_CLOSE) {
				[, , $depth, $openIdx, $header] = $frame;
				$selfEmitted = array_pop($containerEmitted);
				$topEmittedIdx--;
				self::closeGroupingFrame($chunks, $pretty, $depth, $openIdx, $header, $selfEmitted);
				$containerEmitted[$topEmittedIdx] = true;
				continue;
			}

			[, $rule, $depth] = $frame;
			$id = spl_object_id($rule);
			if (isset($visited[$id])) {
				throw new LogicException('Cannot serialize a cyclic or DAG-shared CSS rule tree.');
			}
			$visited[$id] = true;
			if ($depth > self::MAX_RENDER_DEPTH) {
				throw new CSSParserLimitException(sprintf('Maximum CSS rendering depth of %d exceeded.', self::MAX_RENDER_DEPTH));
			}

			if ($pretty && ($depth > 0 || $containerEmitted[$topEmittedIdx])) {
				$sepIdx = count($chunks);
				$chunks[] = "\n";
			} else {
				$sepIdx = null;
			}

			if (!$rule instanceof CSSGroupingRule || $rule->getCssRules()->getRulesRaw() === []) {
				$rendered = self::renderFlatRule($rule, $options, $pretty, $depth);
				if ($rendered !== '') {
					$chunks[] = $rendered;
					$containerEmitted[$topEmittedIdx] = true;
				} elseif ($sepIdx !== null && count($chunks) === $sepIdx + 1) {
					array_pop($chunks);
				}
				continue;
			}

			$nested = $rule->getCssRules()->getRulesRaw();
			[$openIdx, $header, $emitted] = self::appendNonEmptyGroupingOpen($rule, $pretty, $depth, $chunks);
			$containerEmitted[] = $emitted;
			$topEmittedIdx++;
			$workStack[] = [self::FRAME_CLOSE, $rule, $depth, $openIdx, $header];
			for ($i = count($nested) - 1; $i >= 0; $i--) {
				$workStack[] = [self::FRAME_ENTER, $nested[$i], $depth + 1];
			}
		}
	}

	private static function renderWithCallback(
		CSSStyleSheet|CSSRule $source,
		CSSRenderOptions $options,
		bool $pretty,
		callable $callback,
	): string {
		/** @var list<list<string>> $bufferStack */
		$bufferStack = [[]];
		$bufTopIdx = 0;
		$containerEmitted = [false];
		$emTopIdx = 0;
		$visited = [];

		$workStack = [];
		if ($source instanceof CSSStyleSheet) {
			$rules = $source->getCssRules()->getRulesRaw();
			for ($i = count($rules) - 1; $i >= 0; $i--) {
				$workStack[] = [self::FRAME_ENTER, $rules[$i], 0];
			}
		} else {
			$workStack[] = [self::FRAME_ENTER, $source, 0];
		}

		while ($workStack !== []) {
			$frame = array_pop($workStack);
			if ($frame[0] === self::FRAME_CLOSE) {
				[, $closeRule, $depth, $openIdx, $header, $sepIdx] = $frame;
				$selfEmitted = array_pop($containerEmitted);
				$emTopIdx--;
				$local = array_pop($bufferStack);
				$bufTopIdx--;

				self::closeGroupingFrame($local, $pretty, $depth, $openIdx, $header, $selfEmitted);
				$serialized = count($local) === 1 ? $local[0] : implode('', $local);
				$transformed = $callback($closeRule, $serialized);
				if ($transformed === '') {
					if ($sepIdx !== null && count($bufferStack[$bufTopIdx]) === $sepIdx + 1) {
						array_pop($bufferStack[$bufTopIdx]);
					}
				} else {
					$bufferStack[$bufTopIdx][] = $transformed;
					$containerEmitted[$emTopIdx] = true;
				}
				continue;
			}

			[, $rule, $depth] = $frame;
			$id = spl_object_id($rule);
			if (isset($visited[$id])) {
				throw new LogicException('Cannot serialize a cyclic or DAG-shared CSS rule tree.');
			}
			$visited[$id] = true;
			if ($depth > self::MAX_RENDER_DEPTH) {
				throw new CSSParserLimitException(sprintf('Maximum CSS rendering depth of %d exceeded.', self::MAX_RENDER_DEPTH));
			}

			$sepIdx = null;
			if ($pretty && ($depth > 0 || $containerEmitted[$emTopIdx])) {
				$sepIdx = count($bufferStack[$bufTopIdx]);
				$bufferStack[$bufTopIdx][] = "\n";
			}

			if (!$rule instanceof CSSGroupingRule || $rule->getCssRules()->getRulesRaw() === []) {
				$serialized = self::renderFlatRule($rule, $options, $pretty, $depth);
				$transformed = $callback($rule, $serialized);
				if ($transformed === '') {
					if ($sepIdx !== null && count($bufferStack[$bufTopIdx]) === $sepIdx + 1) {
						array_pop($bufferStack[$bufTopIdx]);
					}
				} else {
					$bufferStack[$bufTopIdx][] = $transformed;
					$containerEmitted[$emTopIdx] = true;
				}
				continue;
			}

			$nested = $rule->getCssRules()->getRulesRaw();
			$bufferStack[] = [];
			$bufTopIdx++;
			[$openIdx, $header, $emitted] = self::appendNonEmptyGroupingOpen($rule, $pretty, $depth, $bufferStack[$bufTopIdx]);
			$containerEmitted[] = $emitted;
			$emTopIdx++;
			$workStack[] = [self::FRAME_CLOSE, $rule, $depth, $openIdx, $header, $sepIdx];
			for ($i = count($nested) - 1; $i >= 0; $i--) {
				$workStack[] = [self::FRAME_ENTER, $nested[$i], $depth + 1];
			}
		}

		$rootChunks = $bufferStack[0];
		return count($rootChunks) === 1 ? $rootChunks[0] : implode('', $rootChunks);
	}

	/**
	 * @param array<int, string> $buffer
	 */
	private static function closeGroupingFrame(
		array &$buffer,
		bool $pretty,
		int $depth,
		int $openIdx,
		string $header,
		bool $selfEmitted,
	): void {
		if (!$pretty) {
			$buffer[] = '}';
		} elseif (!$selfEmitted) {
			$buffer[$openIdx] = self::indent($depth) . $header . ' {}';
		} else {
			$buffer[] = "\n" . self::indent($depth) . '}';
		}
	}

	/**
	 * @return array{0: string, 1: bool}
	 */
	private static function formatOpeningHeader(CSSRule $rule): array
	{
		if ($rule instanceof CSSStyleRule) {
			return [$rule->getSelectorText(), true];
		}
		if ($rule instanceof CSSPageRule) {
			$prelude = $rule->getSelectorText();
			return ['@page' . ($prelude === '' ? '' : ' ' . $prelude), true];
		}
		if ($rule instanceof CSSKeyframeRule) {
			return [$rule->getKeyText(), true];
		}
		if ($rule instanceof CSSFontFeatureValuesRule) {
			return ['@font-feature-values ' . $rule->getFontFamily(), false];
		}
		assert($rule instanceof CSSGroupingRule);
		$prelude = $rule->getPrelude();
		return ['@' . $rule->getName() . ($prelude === '' ? '' : ' ' . $prelude), false];
	}

	private static function formatFlatBlockRule(
		string $header,
		CSSStyleDeclaration $style,
		bool $pretty,
		int $depth,
		bool $collapseCommas,
	): string {
		$decls = self::buildDeclarationsString($style, $pretty, $depth + 1, $pretty);
		if (!$pretty) {
			return self::normalizeMinifiedWhitespace($header, $collapseCommas) . '{' . $decls . '}';
		}
		$indent = self::indent($depth);
		return $decls === '' ? $indent . $header . ' {}' : $indent . $header . ' {' . $decls . "\n" . $indent . '}';
	}

	/**
	 * @param list<string> $out
	 * @return array{int, string, bool}
	 */
	private static function appendNonEmptyGroupingOpen(CSSGroupingRule $rule, bool $pretty, int $depth, array &$out): array
	{
		$openIdx = count($out);
		[$header, $collapseCommas] = self::formatOpeningHeader($rule);
		$out[] = $pretty ? self::indent($depth) . $header . ' {' : self::normalizeMinifiedWhitespace($header, $collapseCommas) . '{';

		if ($rule instanceof CSSStyleRule || $rule instanceof CSSPageRule) {
			$decls = self::buildDeclarationsString($rule->getStyle(), $pretty, $depth + 1, $pretty);
			if ($decls !== '') {
				$out[] = $decls;
				return [$openIdx, $header, true];
			}
			return [$openIdx, $header, false];
		}

		if ($rule instanceof CSSFontFeatureValuesRule) {
			$emitted = self::appendFontFeatureMaps($rule->getFeatureMaps(), $pretty, $depth, $out);
			return [$openIdx, $header, $emitted];
		}

		return [$openIdx, $header, false];
	}

	private static function renderFlatRule(CSSRule $rule, CSSRenderOptions $options, bool $pretty, int $depth): string
	{
		if ($rule instanceof CSSStyleRule || $rule instanceof CSSPageRule || $rule instanceof CSSKeyframeRule) {
			[$header, $collapseCommas] = self::formatOpeningHeader($rule);
			return self::formatFlatBlockRule($header, $rule->getStyle(), $pretty, $depth, $collapseCommas);
		}

		if ($rule instanceof CSSFontFeatureValuesRule) {
			$out = [];
			[$header] = self::formatOpeningHeader($rule);
			$indent = $pretty ? self::indent($depth) : '';
			$out[] = $pretty ? $indent . $header . ' {' : self::normalizeMinifiedWhitespace($header, false) . '{';
			$emitted = self::appendFontFeatureMaps($rule->getFeatureMaps(), $pretty, $depth, $out);
			if (!$pretty) {
				$out[] = '}';
				return implode('', $out);
			}
			if (!$emitted) {
				return $indent . $header . ' {}';
			}
			$out[] = "\n" . $indent . '}';
			return implode('', $out);
		}

		if ($rule instanceof CSSGroupingRule) {
			[$header] = self::formatOpeningHeader($rule);
			return $pretty ? self::indent($depth) . $header . ' {}' : self::normalizeMinifiedWhitespace($header, false) . '{}';
		}

		if ($rule instanceof CSSOpaqueRule) {
			$raw = $options->keepComments ? $rule->getCssText() : CSSSyntaxScanner::removeComments($rule->getCssText());
			if ($raw === '') {
				return '';
			}
			return $pretty && $depth > 0 ? self::indent($depth) . $raw : $raw;
		}

		if ($rule instanceof CSSAtRule) {
			return self::renderAtRule($rule, $pretty, $depth);
		}

		if ($rule instanceof CSSNestedDeclarations || $rule instanceof CSSFunctionDeclarations) {
			return self::buildDeclarationsString($rule->getStyle(), $pretty, $depth, false);
		}

		return ($pretty ? self::indent($depth) : '') . $rule->getCssText();
	}

	private static function renderAtRule(CSSAtRule $rule, bool $pretty, int $depth): string
	{
		$prelude = $rule->getPrelude();
		$header = '@' . $rule->getName() . ($prelude === '' ? '' : ' ' . $prelude);

		if (!$rule->hasBlock()) {
			return $pretty ? self::indent($depth) . $header . ';' : self::normalizeMinifiedWhitespace($header, false) . ';';
		}

		$style = $rule->getStyle();
		$decls = $style !== null ? self::buildDeclarationsString($style, $pretty, $depth + 1, $pretty) : '';
		$hasEmptyInitial = $rule instanceof CSSPropertyRule
			&& $rule->getInitialValue() === ''
			&& ($style === null || !isset($style['initial-value']));

		if (!$pretty) {
			if ($hasEmptyInitial) {
				$decls .= 'initial-value: ;';
			}
			return self::normalizeMinifiedWhitespace($header, false) . '{' . $decls . '}';
		}

		$indent = self::indent($depth);
		if ($hasEmptyInitial) {
			$decls .= "\n" . self::indent($depth + 1) . 'initial-value: ;';
		}
		return $decls === '' ? $indent . $header . ' {}' : $indent . $header . ' {' . $decls . "\n" . $indent . '}';
	}

	/**
	 * @param array<string, CSSFontFeatureValuesMap> $maps
	 * @param list<string> $out
	 */
	private static function appendFontFeatureMaps(array $maps, bool $pretty, int $depth, array &$out): bool
	{
		$emitted = false;
		if (!$pretty) {
			foreach ($maps as $category => $map) {
				$entries = $map->getEntriesRaw();
				if ($entries === []) {
					continue;
				}
				$emitted = true;
				$out[] = '@' . $category . '{';
				foreach ($entries as $name => $values) {
					$out[] = $name . ':' . implode(' ', $values) . ';';
				}
				$out[] = '}';
			}
			return $emitted;
		}

		$subIndent = self::indent($depth + 1);
		$declIndent = self::indent($depth + 2);
		foreach ($maps as $category => $map) {
			$entries = $map->getEntriesRaw();
			if ($entries === []) {
				continue;
			}
			$emitted = true;
			$out[] = "\n" . $subIndent . '@' . $category . ' {';
			foreach ($entries as $name => $values) {
				$out[] = "\n" . $declIndent . $name . ': ' . implode(' ', $values) . ';';
			}
			$out[] = "\n" . $subIndent . '}';
		}
		return $emitted;
	}

	private static function buildDeclarationsString(
		CSSStyleDeclaration $style,
		bool $pretty,
		int $depth,
		bool $leadingNewline,
	): string {
		$entries = $style->getSerializedDeclarationsInternal();
		if ($entries === []) {
			return '';
		}

		$out = '';
		if (!$pretty) {
			foreach ($entries as $entry) {
				$property = $entry['property'];
				$value = $entry['value'];
				$priority = $entry['priority'];
				if ($value === '' && str_starts_with($property, '--')) {
					$out .= $priority === ''
						? $property . ': ;'
						: $property . ': !important;';
					continue;
				}
				$out .= $priority === ''
					? $property . ':' . $value . ';'
					: $property . ':' . $value . '!important;';
			}
			return $out;
		}

		$indent = self::indent($depth);
		$linePrefix = "\n" . $indent;
		if ($leadingNewline) {
			foreach ($entries as $entry) {
				$out .= $entry['priority'] === ''
					? $linePrefix . $entry['property'] . ': ' . $entry['value'] . ';'
					: $linePrefix . $entry['property'] . ': ' . $entry['value'] . ' !important;';
			}
			return $out;
		}

		$first = true;
		foreach ($entries as $entry) {
			$prefix = $first ? $indent : $linePrefix;
			$first = false;
			$out .= $entry['priority'] === ''
				? $prefix . $entry['property'] . ': ' . $entry['value'] . ';'
				: $prefix . $entry['property'] . ': ' . $entry['value'] . ' !important;';
		}

		return $out;
	}

	private static function normalizeMinifiedWhitespace(string $text, bool $collapseTopLevelCommas): string
	{
		$trimmed = ($text !== '' && $text[0] > ' ' && $text[-1] > ' ')
			? $text
			: CSSSyntaxScanner::trimCssWhitespace($text);
		if ($trimmed === '') {
			return '';
		}
		if (strpbrk($trimmed, "\r\n\t\f") === false && !str_contains($trimmed, '  ')) {
			if (!$collapseTopLevelCommas || (!str_contains($trimmed, ' ,') && !str_contains($trimmed, ', '))) {
				return $trimmed;
			}
			if (strpbrk($trimmed, "\"'\\/*()[]{}") === false) {
				return str_replace([' ,', ', '], ',', $trimmed);
			}
		}

		$length = strlen($trimmed);
		$offset = 0;
		$stack = [];
		$output = '';
		$pendingSpace = false;
		$lastEscapeOffset = -1;
		$mask = "\\/\"'()[]{} \t\n\r\f,";

		while ($offset < $length) {
			$span = strcspn($trimmed, $mask, $offset, $length - $offset);
			if ($span > 0) {
				if ($pendingSpace && $output !== '') {
					$output .= ' ';
				}
				$pendingSpace = false;
				$output .= substr($trimmed, $offset, $span);
				$offset += $span;
				if ($offset >= $length) {
					break;
				}
			}

			$ch = $trimmed[$offset];

			if ($ch === ' ' || $ch === "\t" || $ch === "\n" || $ch === "\r" || $ch === "\f") {
				$wsLen = strspn($trimmed, " \t\n\r\f", $offset);
				if ($wsLen > 0) {
					$pendingSpace = true;
					$offset += $wsLen;
				}
				continue;
			}

			if ($ch === ',') {
				if ($collapseTopLevelCommas && $stack === []) {
					$pendingSpace = false;
					$output .= ',';
					$offset++;
					$offset += strspn($trimmed, " \t\n\r\f", $offset);
					continue;
				}
				if ($pendingSpace && $output !== '') {
					$output .= ' ';
				}
				$pendingSpace = false;
				$output .= ',';
				$offset++;
				continue;
			}

			if ($ch === '\\') {
				if ($pendingSpace && $output !== '') {
					$output .= ' ';
				}
				$pendingSpace = false;
				if ($offset + 1 < $length && $trimmed[$offset + 1] !== "\n" && $trimmed[$offset + 1] !== "\r" && $trimmed[$offset + 1] !== "\f") {
					$endEsc = CSSSyntaxScanner::advanceIdentEscapeEnd($trimmed, $offset, $length);
					$hexLen = strspn($trimmed, CSSSyntaxScanner::HEX_DIGITS, $offset + 1, min(6, $length - $offset - 1));
					$output .= $hexLen > 0
						? substr($trimmed, $offset, 1 + $hexLen) . ($endEsc > ($offset + 1 + $hexLen) ? ' ' : '')
						: substr($trimmed, $offset, $endEsc - $offset);
				} else {
					$step = ($offset + 1 < $length && $trimmed[$offset + 1] === "\r" && ($trimmed[$offset + 2] ?? '') === "\n") ? 3 : 2;
					$endEsc = min($length, $offset + $step);
					$output .= substr($trimmed, $offset, $endEsc - $offset);
				}
				$lastEscapeOffset = $offset;
				$offset = $endEsc;
				continue;
			}

			if ($ch === '"' || $ch === "'") {
				if ($pendingSpace && $output !== '') {
					$output .= ' ';
				}
				$pendingSpace = false;
				$strValid = true;
				$strEnd = CSSSyntaxScanner::scanQuotedStringEnd($trimmed, $offset, $length, $strValid);
				if ($strValid) {
					$output .= substr($trimmed, $offset, $strEnd - $offset);
					$offset = $strEnd;
					continue;
				}
				$start = $offset;
				$quote = $ch;
				$offset++;
				while ($offset < $length) {
					$next = $trimmed[$offset];
					if ($next === '\\') {
						$step = ($offset + 1 < $length && $trimmed[$offset + 1] === "\r" && ($trimmed[$offset + 2] ?? '') === "\n") ? 3 : 2;
						$offset += $step;
						continue;
					}
					$offset++;
					if ($next === $quote) {
						break;
					}
				}
				$output .= substr($trimmed, $start, min($length, $offset) - $start);
				continue;
			}

			if ($ch === '/' && $offset + 1 < $length && $trimmed[$offset + 1] === '*') {
				if ($pendingSpace && $output !== '') {
					$output .= ' ';
				}
				$pendingSpace = false;
				$endComment = strpos($trimmed, '*/', $offset + 2);
				$endOffset = $endComment === false ? $length : $endComment + 2;
				$output .= substr($trimmed, $offset, $endOffset - $offset);
				$offset = $endOffset;
				continue;
			}

			if ($ch === '(') {
				// Preserve unquoted url(...) payloads verbatim via shared CSSSyntaxScanner rules
				if (!$pendingSpace && CSSSyntaxScanner::isUnquotedUrlStartInternal($trimmed, $offset, 0, $length, $lastEscapeOffset)) {
					$urlValid = false;
					$urlEnd = CSSSyntaxScanner::scanUnquotedUrlEnd($trimmed, $offset, $length, $urlValid);
					$endPos = $urlEnd < $length ? $urlEnd + 1 : $length;
					$output .= substr($trimmed, $offset, $endPos - $offset);
					$offset = $endPos;
					continue;
				}
				if ($pendingSpace && $output !== '') {
					$output .= ' ';
				}
				$pendingSpace = false;
				$stack[] = '(';
				$output .= '(';
				$offset++;
				continue;
			}

			if ($pendingSpace && $output !== '') {
				$output .= ' ';
			}
			$pendingSpace = false;

			if ($ch === '[' || $ch === '{') {
				$stack[] = $ch;
			} elseif ($ch === ')' && end($stack) === '(') {
				array_pop($stack);
			} elseif ($ch === ']' && end($stack) === '[') {
				array_pop($stack);
			} elseif ($ch === '}' && end($stack) === '{') {
				array_pop($stack);
			}

			$output .= $ch;
			$offset++;
		}

		return $output;
	}

	private static function indent(int $depth): string
	{
		return self::$indentCache[$depth] ??= str_repeat(self::INDENT, $depth);
	}
}
