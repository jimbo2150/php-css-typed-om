<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
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
		$hasCallback = $callback !== null;

		$chunks = [];
		// Each open container entry holds ['emitted' => bool]; index 0 represents top-level output.
		$openContainers = [['emitted' => false]];
		/** @var list<list<string>> $bufferStack */
		$bufferStack = $hasCallback ? [&$chunks] : [];

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
				$selfState = array_pop($openContainers);
				$selfEmitted = $selfState['emitted'];
				$parentIdx = array_key_last($openContainers);

				if ($hasCallback) {
					$local = array_pop($bufferStack);
					if (!$pretty) {
						$local[] = '}';
					} elseif (!$selfEmitted) {
						$local[$openIdx] = self::indent($depth) . $header . ' {}';
					} else {
						$local[] = "\n" . self::indent($depth) . '}';
					}
					$serialized = count($local) === 1 ? $local[0] : implode('', $local);
					$transformed = $callback($closeRule, $serialized);
					$parentBufIdx = array_key_last($bufferStack);
					if ($transformed === '') {
						if ($sepIdx !== null && count($bufferStack[$parentBufIdx]) === $sepIdx + 1) {
							array_pop($bufferStack[$parentBufIdx]);
						}
					} else {
						$bufferStack[$parentBufIdx][] = $transformed;
						$openContainers[$parentIdx]['emitted'] = true;
					}
				} else {
					if (!$pretty) {
						$chunks[] = '}';
					} elseif (!$selfEmitted) {
						$chunks[$openIdx] = self::indent($depth) . $header . ' {}';
					} else {
						$chunks[] = "\n" . self::indent($depth) . '}';
					}
					$openContainers[$parentIdx]['emitted'] = true;
				}
				continue;
			}

			[, $rule, $depth] = $frame;
			if ($depth > self::MAX_RENDER_DEPTH) {
				throw new CSSParserLimitException(sprintf('Maximum CSS rendering depth of %d exceeded.', self::MAX_RENDER_DEPTH));
			}

			$parentIdx = array_key_last($openContainers);
			$sepIdx = null;
			if ($pretty && ($depth > 0 || $openContainers[$parentIdx]['emitted'])) {
				if ($hasCallback) {
					$parentBufIdx = array_key_last($bufferStack);
					$sepIdx = count($bufferStack[$parentBufIdx]);
					$bufferStack[$parentBufIdx][] = "\n";
				} else {
					$sepIdx = count($chunks);
					$chunks[] = "\n";
				}
			}

			$isGrouping = $rule instanceof CSSGroupingRule;
			if ($isGrouping) {
				if ($hasCallback) {
					$bufferStack[] = [];
					$targetIdx = array_key_last($bufferStack);
					$out = &$bufferStack[$targetIdx];
				} else {
					$out = &$chunks;
				}

				if ($rule instanceof CSSStyleRule) {
					$header = $rule->getSelectorText();
					$style = $rule->getStyle();
					$nested = $rule->getCssRules()->getRulesRaw();
					$openIdx = count($out);
					$out[] = $pretty
						? self::indent($depth) . $header . ' {'
						: self::normalizeMinifiedWhitespace($header, true) . '{';
					$emitted = self::appendDeclarations($style, $pretty, $depth + 1, $pretty, $out);
				} elseif ($rule instanceof CSSPageRule) {
					$prelude = $rule->getSelectorText();
					$header = '@page' . ($prelude === '' ? '' : ' ' . $prelude);
					$style = $rule->getStyle();
					$nested = $rule->getCssRules()->getRulesRaw();
					$openIdx = count($out);
					$out[] = $pretty
						? self::indent($depth) . $header . ' {'
						: self::normalizeMinifiedWhitespace($header, true) . '{';
					$emitted = self::appendDeclarations($style, $pretty, $depth + 1, $pretty, $out);
				} elseif ($rule instanceof CSSFontFeatureValuesRule) {
					$header = '@font-feature-values ' . $rule->getFontFamily();
					$maps = $rule->getFeatureMaps();
					$nested = $rule->getCssRules()->getRulesRaw();
					$openIdx = count($out);
					$out[] = $pretty
						? self::indent($depth) . $header . ' {'
						: self::normalizeMinifiedWhitespace($header, false) . '{';
					$emitted = self::appendFontFeatureMaps($maps, $pretty, $depth, $out);
				} else {
					$prelude = $rule->getPrelude();
					$header = '@' . $rule->getName() . ($prelude === '' ? '' : ' ' . $prelude);
					$nested = $rule->getCssRules()->getRulesRaw();
					$openIdx = count($out);
					$out[] = $pretty
						? self::indent($depth) . $header . ' {'
						: self::normalizeMinifiedWhitespace($header, false) . '{';
					$emitted = false;
				}

				unset($out);
				$openContainers[] = ['emitted' => $emitted];
				$workStack[] = [self::FRAME_CLOSE, $rule, $depth, $openIdx, $header, $sepIdx];
				for ($i = count($nested) - 1; $i >= 0; $i--) {
					$workStack[] = [self::FRAME_ENTER, $nested[$i], $depth + 1];
				}
				continue;
			}

			// Leaf rule inline emission
			if ($hasCallback) {
				$local = [];
				self::appendLeafRuleBody($rule, $options, $pretty, $depth, $local);
				$serialized = count($local) === 1 ? $local[0] : implode('', $local);
				$transformed = $callback($rule, $serialized);
				$parentBufIdx = array_key_last($bufferStack);
				if ($transformed === '') {
					if ($sepIdx !== null && count($bufferStack[$parentBufIdx]) === $sepIdx + 1) {
						array_pop($bufferStack[$parentBufIdx]);
					}
				} else {
					$bufferStack[$parentBufIdx][] = $transformed;
					$openContainers[$parentIdx]['emitted'] = true;
				}
			} else {
				$before = count($chunks);
				self::appendLeafRuleBody($rule, $options, $pretty, $depth, $chunks);
				if (count($chunks) > $before) {
					$openContainers[$parentIdx]['emitted'] = true;
				} elseif ($sepIdx !== null && count($chunks) === $sepIdx + 1) {
					array_pop($chunks);
				}
			}
		}

		$result = count($chunks) === 1 ? $chunks[0] : implode('', $chunks);
		if ($options->escapeHtmlStyleTag && $result !== '' && stripos($result, '</style') !== false) {
			$escaped = preg_replace('/<\/(style(?=[\t\n\f\r \/>]|$))/i', '<\/$1', $result);
			if ($escaped === null) {
				throw new RuntimeException('Failed to escape closing </style tags in CSS output.');
			}
			return $escaped;
		}

		return $result;
	}

	/**
	 * @param list<string> $out
	 */
	private static function appendLeafRuleBody(
		CSSRule $rule,
		CSSRenderOptions $options,
		bool $pretty,
		int $depth,
		array &$out,
	): void {
		if ($rule instanceof CSSOpaqueRule) {
			$raw = $options->keepComments ? $rule->getCssText() : CSSSyntaxScanner::removeComments($rule->getCssText());
			$out[] = $pretty && $depth > 0 ? self::indent($depth) . $raw : $raw;
			return;
		}

		if ($rule instanceof CSSKeyframeRule) {
			self::appendKeyframeRule($rule, $pretty, $depth, $out);
			return;
		}

		if ($rule instanceof CSSAtRule) {
			self::appendAtRule($rule, $pretty, $depth, $out);
			return;
		}

		if ($rule instanceof CSSNestedDeclarations || $rule instanceof CSSFunctionDeclarations) {
			self::appendDeclarations($rule->getStyle(), $pretty, $depth, false, $out);
			return;
		}

		$out[] = ($pretty ? self::indent($depth) : '') . $rule->getCssText();
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

	/**
	 * @param list<string> $out
	 */
	private static function appendKeyframeRule(
		CSSKeyframeRule $rule,
		bool $pretty,
		int $depth,
		array &$out,
	): void {
		$header = $rule->getKeyText();
		$style = $rule->getStyle();

		if (!$pretty) {
			$out[] = self::normalizeMinifiedWhitespace($header, true);
			$out[] = '{';
			self::appendDeclarations($style, false, $depth + 1, false, $out);
			$out[] = '}';
			return;
		}

		$indent = self::indent($depth);
		$openIdx = count($out);
		$out[] = $indent . $header . ' {';
		$emitted = self::appendDeclarations($style, true, $depth + 1, true, $out);
		if (!$emitted) {
			$out[$openIdx] = $indent . $header . ' {}';
			return;
		}

		$out[] = "\n" . $indent . '}';
	}

	/**
	 * @param list<string> $out
	 */
	private static function appendPageRule(
		CSSPageRule $rule,
		CSSRenderOptions $options,
		bool $pretty,
		int $depth,
		array &$out,
	): void {
		$prelude = $rule->getSelectorText();
		$header = '@page' . ($prelude === '' ? '' : ' ' . $prelude);
		$style = $rule->getStyle();
		$nested = $rule->getCssRules()->getRulesRaw();

		if (!$pretty) {
			$out[] = self::normalizeMinifiedWhitespace($header, true);
			$out[] = '{';
			self::appendDeclarations($style, false, $depth + 1, false, $out);
			foreach ($nested as $nestedRule) {
				self::appendRule($nestedRule, $options, false, $depth + 1, $out);
			}
			$out[] = '}';
			return;
		}

		$indent = self::indent($depth);
		$openIdx = count($out);
		$out[] = $indent . $header . ' {';
		$emitted = self::appendDeclarations($style, true, $depth + 1, true, $out);
		foreach ($nested as $nestedRule) {
			$sepIdx = count($out);
			$out[] = "\n";
			self::appendRule($nestedRule, $options, true, $depth + 1, $out);
			if (count($out) === $sepIdx + 1) {
				array_pop($out);
			} else {
				$emitted = true;
			}
		}

		if (!$emitted) {
			$out[$openIdx] = $indent . $header . ' {}';
			return;
		}

		$out[] = "\n" . $indent . '}';
	}

	/**
	 * @param list<string> $out
	 */
	private static function appendGroupingRule(
		CSSGroupingRule $rule,
		CSSRenderOptions $options,
		bool $pretty,
		int $depth,
		array &$out,
	): void {
		$prelude = $rule->getPrelude();
		$header = '@' . $rule->getName() . ($prelude === '' ? '' : ' ' . $prelude);
		$nested = $rule->getCssRules()->getRulesRaw();

		if (!$pretty) {
			$out[] = self::normalizeMinifiedWhitespace($header, false);
			$out[] = '{';
			foreach ($nested as $nestedRule) {
				self::appendRule($nestedRule, $options, false, $depth + 1, $out);
			}
			$out[] = '}';
			return;
		}

		$indent = self::indent($depth);
		$openIdx = count($out);
		$out[] = $indent . $header . ' {';
		$emitted = false;
		foreach ($nested as $nestedRule) {
			$sepIdx = count($out);
			$out[] = "\n";
			self::appendRule($nestedRule, $options, true, $depth + 1, $out);
			if (count($out) === $sepIdx + 1) {
				array_pop($out);
			} else {
				$emitted = true;
			}
		}

		if (!$emitted) {
			$out[$openIdx] = $indent . $header . ' {}';
			return;
		}

		$out[] = "\n" . $indent . '}';
	}

	/**
	 * @param list<string> $out
	 */
	private static function appendAtRule(
		CSSAtRule $rule,
		bool $pretty,
		int $depth,
		array &$out,
	): void {
		$prelude = $rule->getPrelude();
		$header = '@' . $rule->getName() . ($prelude === '' ? '' : ' ' . $prelude);

		if (!$rule->hasBlock()) {
			$out[] = $pretty ? self::indent($depth) . $header . ';' : self::normalizeMinifiedWhitespace($header, false) . ';';
			return;
		}

		$style = $rule->getStyle();
		if (!$pretty) {
			$out[] = self::normalizeMinifiedWhitespace($header, false);
			$out[] = '{';
			if ($style !== null) {
				self::appendDeclarations($style, false, $depth + 1, false, $out);
			}
			$out[] = '}';
			return;
		}

		$indent = self::indent($depth);
		$openIdx = count($out);
		$out[] = $indent . $header . ' {';
		$emitted = $style !== null && self::appendDeclarations($style, true, $depth + 1, true, $out);
		if (!$emitted) {
			$out[$openIdx] = $indent . $header . ' {}';
			return;
		}

		$out[] = "\n" . $indent . '}';
	}

	/**
	 * @param list<string> $out
	 */
	private static function appendFontFeatureValuesRule(
		CSSFontFeatureValuesRule $rule,
		CSSRenderOptions $options,
		bool $pretty,
		int $depth,
		array &$out,
	): void {
		$header = '@font-feature-values ' . $rule->getFontFamily();
		$maps = $rule->getFeatureMaps();
		$nested = $rule->getCssRules()->getRulesRaw();

		if (!$pretty) {
			$out[] = self::normalizeMinifiedWhitespace($header, false);
			$out[] = '{';
			foreach ($maps as $category => $map) {
				$entries = $map->getEntriesRaw();
				if ($entries === []) {
					continue;
				}
				$out[] = '@' . $category . '{';
				foreach ($entries as $name => $values) {
					$out[] = $name . ':' . implode(' ', $values) . ';';
				}
				$out[] = '}';
			}
			foreach ($nested as $nestedRule) {
				self::appendRule($nestedRule, $options, false, $depth + 1, $out);
			}
			$out[] = '}';
			return;
		}

		$indent = self::indent($depth);
		$subIndent = self::indent($depth + 1);
		$declIndent = self::indent($depth + 2);
		$openIdx = count($out);
		$out[] = $indent . $header . ' {';
		$emitted = false;

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

		foreach ($nested as $nestedRule) {
			$sepIdx = count($out);
			$out[] = "\n";
			self::appendRule($nestedRule, $options, true, $depth + 1, $out);
			if (count($out) === $sepIdx + 1) {
				array_pop($out);
			} else {
				$emitted = true;
			}
		}

		if (!$emitted) {
			$out[$openIdx] = $indent . $header . ' {}';
			return;
		}

		$out[] = "\n" . $indent . '}';
	}

	/**
	 * @param list<string> $out
	 */
	private static function appendDeclarations(
		CSSStyleDeclaration $style,
		bool $pretty,
		int $depth,
		bool $leadingNewline,
		array &$out,
	): bool {
		$declarations = $style->getDeclarationsRaw();
		if ($declarations === []) {
			return false;
		}

		if (!$pretty) {
			foreach ($declarations as $property => $declaration) {
				$out[] = $declaration['priority'] === ''
					? $property . ':' . $declaration['value'] . ';'
					: $property . ':' . $declaration['value'] . '!important;';
			}
			return true;
		}

		$indent = self::indent($depth);
		$first = true;
		foreach ($declarations as $property => $declaration) {
			$prefix = ($first && !$leadingNewline) ? $indent : "\n" . $indent;
			$first = false;
			$out[] = $declaration['priority'] === ''
				? $prefix . $property . ': ' . $declaration['value'] . ';'
				: $prefix . $property . ': ' . $declaration['value'] . ' !important;';
		}

		return true;
	}

	private static function normalizeMinifiedWhitespace(string $text, bool $collapseTopLevelCommas): string
	{
		$trimmed = CSSSyntaxScanner::trimCssWhitespace($text);
		if ($trimmed === '') {
			return '';
		}
		if (
			strpbrk($trimmed, "\r\n\t\f") === false
			&& !str_contains($trimmed, '  ')
			&& (!$collapseTopLevelCommas || (!str_contains($trimmed, ' ,') && !str_contains($trimmed, ', ')))
		) {
			return $trimmed;
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
				$hexLen = strspn($trimmed, '0123456789abcdefABCDEF', $offset + 1, 6);
				if ($hexLen > 0) {
					$endEsc = $offset + 1 + $hexLen;
					$hasTerminatingSpace = false;
					if ($endEsc < $length) {
						$ws = $trimmed[$endEsc];
						if ($ws === "\r" && ($trimmed[$endEsc + 1] ?? '') === "\n") {
							$endEsc += 2;
							$hasTerminatingSpace = true;
						} elseif ($ws === ' ' || $ws === "\t" || $ws === "\n" || $ws === "\r" || $ws === "\f") {
							$endEsc++;
							$hasTerminatingSpace = true;
						}
					}
					$output .= substr($trimmed, $offset, 1 + $hexLen) . ($hasTerminatingSpace ? ' ' : '');
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
