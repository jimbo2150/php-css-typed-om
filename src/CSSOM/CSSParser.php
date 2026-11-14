<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;

/** Parser for standalone CSS stylesheets and declaration blocks. */
final class CSSParser
{
	/** @var list<string> */
	private const GROUPING_AT_RULES = [
		'container', 'document', 'keyframes', '-webkit-keyframes', 'layer', 'media', 'scope',
		'starting-style', 'supports', 'font-feature-values', 'function',
	];

	/** @var list<string> */
	private const DECLARATION_AT_RULES = ['counter-style', 'font-face', 'font-palette-values', 'page', 'position-try', 'property'];

	public static function parseStylesheet(string $cssText): CSSStyleSheet
	{
		$sheet = new CSSStyleSheet();
		self::parseRuleList($cssText, 0, strlen($cssText), $sheet->getCssRules(), $sheet);

		return $sheet;
	}

	public static function parseDeclarationBlock(string $cssText): CSSStyleDeclaration
	{
		return CSSStyleDeclaration::parse($cssText);
	}

	public static function parseSingleRule(string $cssText): CSSRule
	{
		$sheet = self::parseStylesheet($cssText);
		if ($sheet->getParseErrors() !== [] || count($sheet->getCssRules()) !== 1) {
			$message = $sheet->getParseErrors()[0]['message'] ?? 'Exactly one valid CSS rule is required.';
			throw new InvalidArgumentException($message);
		}

		return $sheet->getCssRules()[0];
	}

	public static function parseSingleNestedRule(string $cssText): CSSRule
	{
		$sheet = new CSSStyleSheet();
		$rule = self::parseNestedRule($cssText, $sheet, 0);
		if ($sheet->getParseErrors() !== []) {
			throw new InvalidArgumentException($sheet->getParseErrors()[0]['message']);
		}
		return $rule;
	}

	private static function parseRuleList(
		string $source,
		int $start,
		int $end,
		CSSRuleList $rules,
		CSSStyleSheet $sheet,
		bool $keyframes = false,
		int $baseOffset = 0,
	): void {
		$offset = $start;
		while ($offset < $end) {
			$offset = self::skipTrivia($source, $offset, $end);
			if ($offset >= $end) {
				break;
			}

			$delimiter = CSSSyntaxScanner::findNextTopLevelDelimiter($source, ['{', ';', '}'], $offset);
			if ($delimiter === null || $delimiter['offset'] >= $end) {
				$sheet->recordParseError('Unterminated CSS rule.', $baseOffset + $offset);
				break;
			}

			$ruleStart = $offset;
			$prelude = trim(substr($source, $offset, $delimiter['offset'] - $offset));
			switch ($delimiter['delimiter']) {
				case '}':
					$sheet->recordParseError('Unexpected closing brace.', $baseOffset + $delimiter['offset']);
					$offset = $delimiter['offset'] + 1;
					continue 2;

				case ';':
					$offset = $delimiter['offset'] + 1;
					if (!str_starts_with($prelude, '@')) {
						$sheet->recordParseError('A qualified rule requires a block.', $baseOffset + $ruleStart);
						continue 2;
					}
					try {
						[$name] = self::splitAtRulePrelude($prelude);
						if (in_array($name, ['charset', 'import', 'layer', 'namespace'], true)) {
							$rules->append(self::parseAtRulePrelude($prelude));
						} else {
							$rules->append(new CSSOpaqueRule(substr($source, $ruleStart, $offset - $ruleStart)));
						}
					} catch (InvalidArgumentException $exception) {
						$sheet->recordParseError($exception->getMessage(), $baseOffset + $ruleStart);
					}
					continue 2;

				case '{':
					$close = CSSSyntaxScanner::findMatchingDelimiter($source, $delimiter['offset']);
					if ($close === null || $close >= $end) {
						$sheet->recordParseError('Unclosed CSS rule block.', $baseOffset + $delimiter['offset']);
						break 2;
					}
					$bodyStart = $delimiter['offset'] + 1;
					$bodyOffset = $baseOffset + $bodyStart;
					$body = substr($source, $bodyStart, $close - $bodyStart);
					$offset = $close + 1;
					if ($prelude === '') {
						$sheet->recordParseError('CSS rule prelude cannot be empty.', $baseOffset + $ruleStart);
						continue 2;
					}

					if (str_starts_with($prelude, '@')) {
						try {
							$atRule = self::createBlockAtRule($prelude, $body, $sheet, $bodyOffset);
						} catch (InvalidArgumentException $exception) {
							$sheet->recordParseError($exception->getMessage(), $baseOffset + $ruleStart);
							$atRule = null;
						}
						if ($atRule !== null) {
							$rules->append($atRule);
							continue 2;
						}
						$rules->append(new CSSOpaqueRule(substr($source, $ruleStart, $offset - $ruleStart)));
						continue 2;
					}

					[$style, $nestedRules, $declarationErrors] = self::parseStyleBlock($body, $bodyOffset);
					foreach ($declarationErrors as $error) {
						$sheet->recordParseError($error['message'], $error['offset']);
					}
					try {
						$rule = $keyframes ? new CSSKeyframeRule($prelude, $style) : new CSSStyleRule($prelude, $style);
						$rules->append($rule);
						self::appendNestedContent($rule, $nestedRules, $sheet);
					} catch (InvalidArgumentException $exception) {
						$sheet->recordParseError($exception->getMessage(), $baseOffset + $ruleStart);
					}
					continue 2;
			}
		}
	}

	private static function parseAtRulePrelude(string $prelude): CSSAtRule
	{
		[$name, $condition] = self::splitAtRulePrelude($prelude);
		return match ($name) {
			'import' => new CSSImportRule($condition),
			'namespace' => new CSSNamespaceRule($condition),
			'layer' => new CSSLayerStatementRule($condition),
			default => new CSSAtRule($name, $condition),
		};
	}

	/** @return array{CSSStyleDeclaration, list<array{source?: string, style?: CSSStyleDeclaration, offset: int}>, list<array{message: string, offset: int}>} */
	private static function parseStyleBlock(string $body, int $baseOffset): array
	{
		$style = new CSSStyleDeclaration();
		$currentStyle = $style;
		$nestedRules = [];
		$diagnostics = [];
		$offset = 0;
		$length = strlen($body);
		$hasNestedRule = false;

		while ($offset < $length) {
			$offset = self::skipTrivia($body, $offset, $length);
			if ($offset >= $length) {
				break;
			}
			$delimiter = CSSSyntaxScanner::findNextTopLevelDelimiter($body, [';', '{', '}'], $offset);
			if ($delimiter === null) {
				self::mergeDeclarations(substr($body, $offset), $currentStyle, $diagnostics, $baseOffset + $offset);
				break;
			}

			$segment = substr($body, $offset, $delimiter['offset'] - $offset);
			if ($delimiter['delimiter'] === ';') {
				self::mergeDeclarations($segment, $currentStyle, $diagnostics, $baseOffset + $offset);
				$offset = $delimiter['offset'] + 1;
				continue;
			}

			if ($delimiter['delimiter'] === '{') {
				$close = CSSSyntaxScanner::findMatchingDelimiter($body, $delimiter['offset']);
				if ($close === null) {
					$diagnostics[] = ['message' => 'Unclosed nested CSS rule.', 'offset' => $baseOffset + $delimiter['offset']];
					break;
				}
				if ($hasNestedRule && count($currentStyle) > 0) {
					$nestedRules[] = ['style' => $currentStyle, 'offset' => $baseOffset + $offset];
				}
				$nestedRules[] = ['source' => substr($body, $offset, $close - $offset + 1), 'offset' => $baseOffset + $offset];
				$hasNestedRule = true;
				$currentStyle = new CSSStyleDeclaration();
				$offset = $close + 1;
				continue;
			}

			$diagnostics[] = ['message' => 'Unexpected closing brace in CSS declaration block.', 'offset' => $baseOffset + $delimiter['offset']];
			$offset = $delimiter['offset'] + 1;
		}

		if ($hasNestedRule && count($currentStyle) > 0) {
			$nestedRules[] = ['style' => $currentStyle, 'offset' => $baseOffset + $offset];
		}

		return [$style, $nestedRules, $diagnostics];
	}

	/** @param list<array{source?: string, style?: CSSStyleDeclaration, offset: int}> $content */
	private static function appendNestedContent(CSSGroupingRule $parent, array $content, CSSStyleSheet $sheet): void
	{
		foreach ($content as $item) {
			if (isset($item['style'])) {
				$declarations = $parent instanceof CSSFunctionRule
					? new CSSFunctionDeclarations(self::asFunctionDescriptors($item['style']))
					: new CSSNestedDeclarations($item['style']);
				$parent->getCssRules()->append($declarations);
				continue;
			}
			try {
				$parent->getCssRules()->append(self::parseNestedRule($item['source'] ?? '', $sheet, $item['offset']));
			} catch (InvalidArgumentException $exception) {
				$sheet->recordParseError($exception->getMessage(), $item['offset']);
			}
		}
	}

	private static function asFunctionDescriptors(CSSStyleDeclaration $style): CSSFunctionDescriptors
	{
		$descriptors = new CSSFunctionDescriptors();
		foreach ($style as $property => $declaration) {
			$descriptors->setProperty($property, $declaration['value'], $declaration['priority']);
		}
		return $descriptors;
	}

	/** @param list<array{message: string, offset: int}> $diagnostics */
	private static function mergeDeclarations(string $source, CSSStyleDeclaration $target, array &$diagnostics, int $sourceOffset): void
	{
		$leadingWhitespace = strlen($source) - strlen(ltrim($source));
		$source = trim($source);
		if ($source === '') {
			return;
		}

		$parsed = CSSStyleDeclaration::parse($source, false, $errors);
		foreach ($errors ?? [] as $error) {
			$diagnostics[] = ['message' => $error, 'offset' => $sourceOffset + $leadingWhitespace];
		}
		foreach ($parsed as $property => $declaration) {
			$target->setProperty($property, $declaration['value'], $declaration['priority']);
		}
	}

	private static function createBlockAtRule(
		string $prelude,
		string $body,
		CSSStyleSheet $sheet,
		int $bodyOffset,
		bool $allowDeclarations = false,
	): ?CSSRule
	{
		[$name, $condition] = self::splitAtRulePrelude($prelude);
		$name = strtolower($name);
		if ($name === 'page') {
			return self::createPageRule($condition, $body, $sheet, $bodyOffset);
		}

		if (in_array($name, self::GROUPING_AT_RULES, true)) {
			$rule = match ($name) {
				'media' => new CSSMediaRule($condition),
				'supports' => new CSSSupportsRule($condition),
				'layer' => new CSSLayerBlockRule($condition),
				'keyframes', '-webkit-keyframes' => new CSSKeyframesRule($condition, $name),
				'starting-style' => new CSSStartingStyleRule(),
				'font-feature-values' => new CSSFontFeatureValuesRule($condition),
				'function' => CSSFunctionRule::fromPrelude($condition),
				default => new CSSGroupingRule($name, $condition),
			};
			$rule->initializeRules($sheet);
			if ($allowDeclarations || $name === 'function') {
				[$style, $nestedRules, $errors] = self::parseStyleBlock($body, $bodyOffset);
				foreach ($errors as $error) {
					$sheet->recordParseError($error['message'], $error['offset']);
				}
				if (count($style) > 0) {
					$declarations = $rule instanceof CSSFunctionRule
						? new CSSFunctionDeclarations(self::asFunctionDescriptors($style))
						: new CSSNestedDeclarations($style);
					$rule->getCssRules()->append($declarations);
				}
				self::appendNestedContent($rule, $nestedRules, $sheet);
			} else {
				self::parseRuleList($body, 0, strlen($body), $rule->getCssRules(), $sheet, $rule instanceof CSSKeyframesRule, $bodyOffset);
			}
			return $rule;
		}

		if (in_array($name, self::DECLARATION_AT_RULES, true)) {
			$declarationErrors = [];
			$style = CSSStyleDeclaration::parse($body, false, $declarationErrors);
			foreach ($declarationErrors as $error) {
				$sheet->recordParseError($error, $bodyOffset);
			}
			return match ($name) {
				'font-face' => new CSSFontFaceRule($style),
				'page' => new CSSPageRule($condition, $style),
				'property' => new CSSPropertyRule($condition, $style),
				'counter-style' => new CSSCounterStyleRule($condition, $style),
				'font-palette-values' => new CSSFontPaletteValuesRule($condition, $style),
				'position-try' => new CSSPositionTryRule($condition, $style),
				default => new CSSAtRule($name, $condition, $style, true),
			};
		}

		return null;
	}

	private static function createPageRule(string $selector, string $body, CSSStyleSheet $sheet, int $bodyOffset): CSSPageRule
	{
		[$style, $content, $errors] = self::parseStyleBlock($body, $bodyOffset);
		foreach ($errors as $error) {
			$sheet->recordParseError($error['message'], $error['offset']);
		}
		$marginRules = [];
		foreach ($content as $item) {
			if (isset($item['style'])) {
				self::mergeStyle($style, $item['style']);
				continue;
			}
			try {
				$marginRules[] = self::parseMarginRule($item['source'] ?? '', $sheet, $item['offset']);
			} catch (InvalidArgumentException $exception) {
				$sheet->recordParseError($exception->getMessage(), $item['offset']);
			}
		}
		return new CSSPageRule($selector, $style, $marginRules);
	}

	private static function parseMarginRule(string $source, CSSStyleSheet $sheet, int $sourceOffset): CSSMarginRule
	{
		$delimiter = CSSSyntaxScanner::findNextTopLevelDelimiter($source, ['{']);
		if ($delimiter === null) {
			throw new InvalidArgumentException('Malformed page margin rule.');
		}
		[$name] = self::splitAtRulePrelude(trim(substr($source, 0, $delimiter['offset'])));
		if (!preg_match('/^(?:top|bottom|left|right)-(?:left|center|right|top|middle|bottom)(?:-corner)?$/', $name)) {
			throw new InvalidArgumentException('Unknown page margin box: ' . $name);
		}
		$close = CSSSyntaxScanner::findMatchingDelimiter($source, $delimiter['offset']);
		if ($close !== strlen($source) - 1) {
			throw new InvalidArgumentException('Malformed page margin rule block.');
		}
		$bodyOffset = $sourceOffset + $delimiter['offset'] + 1;
		$body = substr($source, $delimiter['offset'] + 1, $close - $delimiter['offset'] - 1);
		$errors = [];
		$style = CSSStyleDeclaration::parse($body, false, $errors);
		foreach ($errors as $error) {
			$sheet->recordParseError($error, $bodyOffset);
		}
		return new CSSMarginRule($name, $style);
	}

	private static function mergeStyle(CSSStyleDeclaration $target, CSSStyleDeclaration $source): void
	{
		foreach ($source as $property => $declaration) {
			$target->setProperty($property, $declaration['value'], $declaration['priority']);
		}
	}

	private static function parseNestedRule(string $source, CSSStyleSheet $sheet, int $sourceOffset): CSSRule
	{
		$delimiter = CSSSyntaxScanner::findNextTopLevelDelimiter($source, ['{', ';', '}']);
		if ($delimiter === null) {
			throw new InvalidArgumentException('Unterminated nested CSS rule.');
		}
		$prelude = trim(substr($source, 0, $delimiter['offset']));
		if ($delimiter['delimiter'] === ';') {
			return self::parseSingleRule($source);
		}
		if ($delimiter['delimiter'] !== '{') {
			throw new InvalidArgumentException('Invalid nested CSS rule.');
		}
		$close = CSSSyntaxScanner::findMatchingDelimiter($source, $delimiter['offset']);
		if ($close !== strlen($source) - 1) {
			throw new InvalidArgumentException('Malformed nested CSS rule block.');
		}
		$bodyOffset = $sourceOffset + $delimiter['offset'] + 1;
		$body = substr($source, $delimiter['offset'] + 1, $close - $delimiter['offset'] - 1);

		if (str_starts_with($prelude, '@')) {
			[$name] = self::splitAtRulePrelude($prelude);
			if (in_array($name, self::GROUPING_AT_RULES, true)) {
				return self::createBlockAtRule($prelude, $body, $sheet, $bodyOffset, true)
					?? new CSSOpaqueRule($source);
			}
			return self::createBlockAtRule($prelude, $body, $sheet, $bodyOffset)
				?? new CSSOpaqueRule($source);
		}

		[$style, $nestedRules, $errors] = self::parseStyleBlock($body, $bodyOffset);
		foreach ($errors as $error) {
			$sheet->recordParseError($error['message'], $error['offset']);
		}
		$rule = new CSSStyleRule($prelude, $style);
		$rule->attach($sheet, null);
		self::appendNestedContent($rule, $nestedRules, $sheet);
		return $rule;
	}

	/** @return array{string, string} */
	private static function splitAtRulePrelude(string $prelude): array
	{
		if (!preg_match('/^@([a-zA-Z_-][a-zA-Z0-9_-]*)(?:\s+(.*))?$/s', trim($prelude), $matches)) {
			throw new InvalidArgumentException('Malformed at-rule prelude: ' . $prelude);
		}

		return [strtolower($matches[1]), trim($matches[2] ?? '')];
	}

	private static function skipTrivia(string $source, int $offset, int $end): int
	{
		while ($offset < $end) {
			if (ctype_space($source[$offset])) {
				$offset++;
				continue;
			}
			if (($source[$offset] ?? '') === '/' && ($source[$offset + 1] ?? '') === '*') {
				$commentEnd = strpos($source, '*/', $offset + 2);
				if ($commentEnd === false || $commentEnd >= $end) {
					return $end;
				}
				$offset = $commentEnd + 2;
				continue;
			}
			break;
		}

		return $offset;
	}
}
