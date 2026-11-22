<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;

/**
 * Parser for standalone CSS stylesheets, rules, and declaration blocks.
 *
 * `parseStylesheet()` recovers from malformed sections and reports byte-offset diagnostics,
 * whereas `parseSingleRule()`, `parseSingleNestedRule()`, and `parseDeclarationBlock()`
 * validate strictly and throw `InvalidArgumentException` on malformed or over-limit input.
 */
final class CSSParser
{
	/** @var array<string, true> */
	private const array GROUPING_AT_RULES = [
		'container' => true,
		'document' => true,
		'keyframes' => true,
		'-webkit-keyframes' => true,
		'layer' => true,
		'media' => true,
		'scope' => true,
		'starting-style' => true,
		'supports' => true,
		'font-feature-values' => true,
		'function' => true,
	];

	/** @var array<string, true> */
	private const array DECLARATION_AT_RULES = [
		'counter-style' => true,
		'font-face' => true,
		'font-palette-values' => true,
		'position-try' => true,
		'property' => true,
		'view-transition' => true,
	];

	/** @var array<string, true> */
	private const array STATEMENT_AT_RULES = [
		'charset' => true,
		'import' => true,
		'layer' => true,
		'namespace' => true,
	];

	/** @var array<string, true> */
	private const array FONT_FEATURE_CATEGORIES = [
		'annotation' => true,
		'ornaments' => true,
		'stylistic' => true,
		'styleset' => true,
		'character-variant' => true,
		'swash' => true,
		'historical-forms' => true,
	];

	/**
	 * Parses a CSS stylesheet into a mutable CSSStyleSheet, recovering from malformed rules.
	 */
	public static function parseStylesheet(string $cssText, ?CSSParserOptions $options = null): CSSStyleSheet
	{
		$options ??= new CSSParserOptions();
		$sheet = new CSSStyleSheet();
		$length = strlen($cssText);
		if ($options->maxBytes !== null && $length > $options->maxBytes) {
			$sheet->recordParseError(sprintf('CSS input exceeds maximum size of %d bytes.', $options->maxBytes), 0);
			return $sheet;
		}

		$ruleCount = 0;
		self::parseRuleList($cssText, 0, $length, $sheet->getCssRules(), $sheet, false, 0, $options, $ruleCount);

		return $sheet;
	}

	/**
	 * Parses a CSS declaration block strictly.
	 *
	 * @throws InvalidArgumentException When any declaration is malformed
	 */
	public static function parseDeclarationBlock(string $cssText): CSSStyleDeclaration
	{
		return CSSStyleDeclaration::parse($cssText);
	}

	/**
	 * Parses exactly one top-level CSS rule strictly.
	 *
	 * @throws InvalidArgumentException When the input has 0 rules, >1 rules, parse errors, or exceeds limits
	 */
	public static function parseSingleRule(string $cssText, ?CSSParserOptions $options = null): CSSRule
	{
		$options ??= new CSSParserOptions();
		if ($options->maxBytes !== null && strlen($cssText) > $options->maxBytes) {
			throw new InvalidArgumentException(sprintf('CSS rule exceeds maximum size of %d bytes.', $options->maxBytes));
		}

		$sheet = self::parseStylesheet($cssText, $options);
		if ($sheet->getParseErrors() !== [] || count($sheet->getCssRules()) !== 1) {
			$message = $sheet->getParseErrors()[0]['message'] ?? 'Exactly one valid CSS rule is required.';
			throw new InvalidArgumentException($message);
		}

		return $sheet->getCssRules()[0];
	}

	/**
	 * Parses a single rule valid inside a style or grouping rule block (including nested rules).
	 *
	 * @throws InvalidArgumentException When the input is malformed or exceeds parser limits
	 */
	public static function parseSingleNestedRule(string $cssText, ?CSSParserOptions $options = null): CSSRule
	{
		$options ??= new CSSParserOptions();
		if ($options->maxBytes !== null && strlen($cssText) > $options->maxBytes) {
			throw new InvalidArgumentException(sprintf('CSS rule exceeds maximum size of %d bytes.', $options->maxBytes));
		}

		$sheet = new CSSStyleSheet();
		$ruleCount = 0;
		$length = strlen($cssText);
		$start = self::skipTrivia($cssText, 0, $length, $sheet);
		$rule = self::parseNestedRule($cssText, $start, $length, $sheet, 0, $options, $ruleCount);
		if ($sheet->getParseErrors() !== []) {
			throw new InvalidArgumentException($sheet->getParseErrors()[0]['message']);
		}

		return $rule;
	}

	/**
	 * Parses a single keyframe rule strictly, rejecting nested blocks.
	 *
	 * @throws InvalidArgumentException When the input is not a valid single keyframe rule or exceeds parser limits
	 */
	public static function parseSingleKeyframeRule(string $cssText, ?CSSParserOptions $options = null): CSSKeyframeRule
	{
		$options ??= new CSSParserOptions();
		if ($options->maxBytes !== null && strlen($cssText) > $options->maxBytes) {
			throw new InvalidArgumentException(sprintf('CSS keyframe rule exceeds maximum size of %d bytes.', $options->maxBytes));
		}

		$sheet = new CSSStyleSheet();
		$ruleCount = 0;
		self::parseRuleList($cssText, 0, strlen($cssText), $sheet->getCssRules(), $sheet, true, 0, $options, $ruleCount);
		if ($sheet->getParseErrors() !== [] || count($sheet->getCssRules()) !== 1) {
			$message = $sheet->getParseErrors()[0]['message'] ?? 'Exactly one valid CSS keyframe rule is required.';
			throw new InvalidArgumentException($message);
		}
		$rule = $sheet->getCssRules()[0];
		if (!$rule instanceof CSSKeyframeRule) {
			throw new InvalidArgumentException('A keyframe rule requires a keyframe selector and declaration block.');
		}

		return $rule;
	}

	private static function parseRuleList(
		string $source,
		int $start,
		int $end,
		CSSRuleList $rules,
		CSSStyleSheet $sheet,
		bool $keyframes,
		int $depth,
		CSSParserOptions $options,
		int &$ruleCount,
	): void {
		if ($options->maxDepth !== null && $depth > $options->maxDepth) {
			$sheet->recordParseError(sprintf('Maximum CSS nesting depth of %d exceeded.', $options->maxDepth), $start);
			return;
		}

		$offset = $start;
		while ($offset < $end) {
			$offset = self::skipTrivia($source, $offset, $end, $sheet);
			if ($offset >= $end) {
				break;
			}
			if (!self::canRecordRule($options, $ruleCount, $sheet, $offset)) {
				break;
			}

			$delimiter = CSSSyntaxScanner::findNextTopLevelDelimiter($source, ['{', ';', '}'], $offset, $end);
			if ($delimiter === null) {
				$sheet->recordParseError('Unterminated CSS rule.', $offset);
				break;
			}

			$ruleStart = $offset;
			$prelude = trim(CSSSyntaxScanner::removeComments($source, $offset, $delimiter['offset']));
			switch ($delimiter['delimiter']) {
				case '}':
					$sheet->recordParseError('Unexpected closing brace.', $delimiter['offset']);
					$offset = $delimiter['offset'] + 1;
					continue 2;

				case ';':
					$offset = $delimiter['offset'] + 1;
					if (!str_starts_with($prelude, '@')) {
						$sheet->recordParseError('A qualified rule requires a block.', $ruleStart);
						continue 2;
					}
					try {
						[$name] = self::splitAtRulePrelude($prelude);
						if (isset(self::STATEMENT_AT_RULES[$name])) {
							$rules->append(self::parseAtRulePrelude($prelude));
						} else {
							$rules->append(new CSSOpaqueRule(substr($source, $ruleStart, $offset - $ruleStart)));
						}
						$ruleCount++;
					} catch (InvalidArgumentException $exception) {
						$sheet->recordParseError($exception->getMessage(), $ruleStart);
					}
					continue 2;

				case '{':
					$close = CSSSyntaxScanner::findMatchingDelimiter($source, $delimiter['offset'], $end);
					if ($close === null || $close >= $end) {
						$sheet->recordParseError('Unclosed CSS rule block.', $delimiter['offset']);
						break 2;
					}
					$bodyStart = $delimiter['offset'] + 1;
					$offset = $close + 1;
					if ($prelude === '') {
						$sheet->recordParseError('CSS rule prelude cannot be empty.', $ruleStart);
						continue 2;
					}

					if (str_starts_with($prelude, '@')) {
						try {
							$atRule = self::createBlockAtRule(
								$prelude,
								$source,
								$bodyStart,
								$close,
								$sheet,
								false,
								$depth + 1,
								$options,
								$ruleCount,
							);
						} catch (InvalidArgumentException $exception) {
							$sheet->recordParseError($exception->getMessage(), $ruleStart);
							$atRule = null;
						}
						if ($atRule !== null) {
							$rules->append($atRule);
							$ruleCount++;
							continue 2;
						}
						$rules->append(new CSSOpaqueRule(substr($source, $ruleStart, $offset - $ruleStart)));
						$ruleCount++;
						continue 2;
					}

					[$style, $nestedContent, $declarationErrors] = self::parseStyleBlock(
						$source,
						$bodyStart,
						$close,
						$sheet,
						$depth + 1,
						$options,
						$ruleCount,
					);
					foreach ($declarationErrors as $error) {
						$sheet->recordParseError($error['message'], $error['offset']);
					}
					try {
						if ($keyframes) {
							if ($nestedContent !== []) {
								$sheet->recordParseError('Nested rules are not allowed inside a @keyframes block.', $nestedContent[0]['offset']);
							}
							$rule = new CSSKeyframeRule($prelude, $style);
							$rules->append($rule);
							$ruleCount++;
						} else {
							$rule = new CSSStyleRule($prelude, $style);
							$rules->append($rule);
							$ruleCount++;
							self::appendNestedContent($rule, $nestedContent, $source, $sheet, $depth + 1, $options, $ruleCount);
						}
					} catch (InvalidArgumentException $exception) {
						$sheet->recordParseError($exception->getMessage(), $ruleStart);
					}
					continue 2;
			}
		}
	}

	private static function parseAtRulePrelude(string $prelude): CSSAtRule
	{
		[$name, $condition] = self::splitAtRulePrelude($prelude);
		return match ($name) {
			'charset' => new CSSCharsetRule($condition),
			'import' => new CSSImportRule($condition),
			'namespace' => new CSSNamespaceRule($condition),
			'layer' => new CSSLayerStatementRule($condition),
			default => new CSSAtRule($name, $condition),
		};
	}

	/**
	 * @param class-string<CSSStyleDeclaration> $declarationClass
	 * @return array{
	 *     CSSStyleDeclaration,
	 *     list<array{start?: int, end?: int, style?: CSSStyleDeclaration, offset: int}>,
	 *     list<array{message: string, offset: int}>
	 * }
	 */
	private static function parseStyleBlock(
		string $source,
		int $start,
		int $end,
		CSSStyleSheet $sheet,
		int $depth,
		CSSParserOptions $options,
		int &$ruleCount,
		string $declarationClass = CSSStyleDeclaration::class,
	): array {
		$style = new $declarationClass();
		if ($options->maxDepth !== null && $depth > $options->maxDepth) {
			return [$style, [], [['message' => sprintf('Maximum CSS nesting depth of %d exceeded.', $options->maxDepth), 'offset' => $start]]];
		}

		$currentStyle = $style;
		$nestedContent = [];
		$diagnostics = [];
		$offset = $start;
		$hasNestedRule = false;

		while ($offset < $end) {
			$offset = self::skipTrivia($source, $offset, $end, $sheet);
			if ($offset >= $end) {
				break;
			}
			$delimiter = CSSSyntaxScanner::findNextTopLevelDelimiter($source, [';', '{', '}'], $offset, $end);
			if ($delimiter === null) {
				self::mergeDeclarations(substr($source, $offset, $end - $offset), $currentStyle, $diagnostics, $offset, $declarationClass);
				break;
			}

			if ($delimiter['delimiter'] === ';') {
				self::mergeDeclarations(
					substr($source, $offset, $delimiter['offset'] - $offset),
					$currentStyle,
					$diagnostics,
					$offset,
					$declarationClass,
				);
				$offset = $delimiter['offset'] + 1;
				continue;
			}

			if ($delimiter['delimiter'] === '{') {
				$close = CSSSyntaxScanner::findMatchingDelimiter($source, $delimiter['offset'], $end);
				if ($close === null || $close >= $end) {
					$diagnostics[] = ['message' => 'Unclosed nested CSS rule.', 'offset' => $delimiter['offset']];
					break;
				}
				if (!self::canRecordRule($options, $ruleCount, $sheet, $offset)) {
					break;
				}
				if ($hasNestedRule && count($currentStyle) > 0) {
					$nestedContent[] = ['style' => $currentStyle, 'offset' => $offset];
				}
				$nestedContent[] = ['start' => $offset, 'end' => $close + 1, 'offset' => $offset];
				$hasNestedRule = true;
				$currentStyle = new $declarationClass();
				$offset = $close + 1;
				continue;
			}

			$diagnostics[] = ['message' => 'Unexpected closing brace in CSS declaration block.', 'offset' => $delimiter['offset']];
			$offset = $delimiter['offset'] + 1;
		}

		if ($hasNestedRule && count($currentStyle) > 0) {
			$nestedContent[] = ['style' => $currentStyle, 'offset' => $offset];
		}

		return [$style, $nestedContent, $diagnostics];
	}

	/** @param list<array{start?: int, end?: int, style?: CSSStyleDeclaration, offset: int}> $content */
	private static function appendNestedContent(
		CSSGroupingRule $parent,
		array $content,
		string $source,
		CSSStyleSheet $sheet,
		int $depth,
		CSSParserOptions $options,
		int &$ruleCount,
	): void {
		foreach ($content as $item) {
			if (!self::canRecordRule($options, $ruleCount, $sheet, $item['offset'])) {
				break;
			}
			if (isset($item['style'])) {
				$declarations = $parent instanceof CSSFunctionRule
					? new CSSFunctionDeclarations(self::asFunctionDescriptors($item['style']))
					: new CSSNestedDeclarations($item['style']);
				$parent->getCssRules()->append($declarations);
				$ruleCount++;
				continue;
			}

			try {
				$nested = self::parseNestedRule(
					$source,
					$item['start'] ?? 0,
					$item['end'] ?? 0,
					$sheet,
					$depth,
					$options,
					$ruleCount,
				);
				$parent->getCssRules()->append($nested);
				$ruleCount++;
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

	/**
	 * @param list<array{message: string, offset: int}> $diagnostics
	 * @param class-string<CSSStyleDeclaration> $declarationClass
	 */
	private static function mergeDeclarations(
		string $source,
		CSSStyleDeclaration $target,
		array &$diagnostics,
		int $sourceOffset,
		string $declarationClass = CSSStyleDeclaration::class,
	): void {
		if (trim($source) === '') {
			return;
		}

		$errors = [];
		$parsed = $declarationClass::parseWithOffsets($source, false, $errors, $sourceOffset);
		foreach ($errors as $error) {
			$diagnostics[] = $error;
		}
		foreach ($parsed as $property => $declaration) {
			$target->setProperty($property, $declaration['value'], $declaration['priority']);
		}
	}

	private static function createBlockAtRule(
		string $prelude,
		string $source,
		int $bodyStart,
		int $bodyEnd,
		CSSStyleSheet $sheet,
		bool $allowDeclarations,
		int $depth,
		CSSParserOptions $options,
		int &$ruleCount,
	): ?CSSRule {
		if ($options->maxDepth !== null && $depth > $options->maxDepth) {
			$sheet->recordParseError(sprintf('Maximum CSS nesting depth of %d exceeded.', $options->maxDepth), $bodyStart);
			return null;
		}

		[$name, $condition] = self::splitAtRulePrelude($prelude);
		$name = strtolower($name);
		if ($name === 'page') {
			return self::createPageRule($condition, $source, $bodyStart, $bodyEnd, $sheet, $depth, $options, $ruleCount);
		}
		if ($name === 'font-feature-values') {
			return self::createFontFeatureValuesRule($condition, $source, $bodyStart, $bodyEnd, $sheet, $options, $ruleCount);
		}

		if (isset(self::GROUPING_AT_RULES[$name])) {
			$rule = match ($name) {
				'media' => new CSSMediaRule($condition),
				'supports' => new CSSSupportsRule($condition),
				'container' => new CSSContainerRule($condition),
				'scope' => new CSSScopeRule($condition),
				'layer' => new CSSLayerBlockRule($condition),
				'keyframes', '-webkit-keyframes' => new CSSKeyframesRule($condition, $name),
				'starting-style' => new CSSStartingStyleRule(),
				'function' => CSSFunctionRule::fromPrelude($condition),
				default => new CSSGroupingRule($name, $condition),
			};
			$rule->initializeRules($sheet);
			if ($allowDeclarations || $name === 'function') {
				[$style, $nestedRules, $errors] = self::parseStyleBlock($source, $bodyStart, $bodyEnd, $sheet, $depth, $options, $ruleCount);
				foreach ($errors as $error) {
					$sheet->recordParseError($error['message'], $error['offset']);
				}
				if (count($style) > 0) {
					$declarations = $rule instanceof CSSFunctionRule
						? new CSSFunctionDeclarations(self::asFunctionDescriptors($style))
						: new CSSNestedDeclarations($style);
					$rule->getCssRules()->append($declarations);
					$ruleCount++;
				}
				self::appendNestedContent($rule, $nestedRules, $source, $sheet, $depth, $options, $ruleCount);
			} else {
				self::parseRuleList($source, $bodyStart, $bodyEnd, $rule->getCssRules(), $sheet, $rule instanceof CSSKeyframesRule, $depth, $options, $ruleCount);
			}
			return $rule;
		}

		if (isset(self::DECLARATION_AT_RULES[$name])) {
			$declarationErrors = [];
			$body = substr($source, $bodyStart, $bodyEnd - $bodyStart);
			$declarationClass = match ($name) {
				'font-face' => CSSFontFaceDescriptors::class,
				'position-try' => CSSPositionTryDescriptors::class,
				default => CSSStyleDeclaration::class,
			};
			$style = $declarationClass::parseWithOffsets($body, false, $declarationErrors, $bodyStart);
			foreach ($declarationErrors as $error) {
				$sheet->recordParseError($error['message'], $error['offset']);
			}
			return match ($name) {
				'font-face' => new CSSFontFaceRule($style),
				'property' => new CSSPropertyRule($condition, $style),
				'counter-style' => new CSSCounterStyleRule($condition, $style),
				'font-palette-values' => new CSSFontPaletteValuesRule($condition, $style),
				'position-try' => new CSSPositionTryRule($condition, $style),
				'view-transition' => new CSSViewTransitionRule($style),
				default => new CSSAtRule($name, $condition, $style, true),
			};
		}

		return null;
	}

	private static function createPageRule(
		string $selector,
		string $source,
		int $bodyStart,
		int $bodyEnd,
		CSSStyleSheet $sheet,
		int $depth,
		CSSParserOptions $options,
		int &$ruleCount,
	): CSSPageRule {
		[$style, $content, $errors] = self::parseStyleBlock(
			$source,
			$bodyStart,
			$bodyEnd,
			$sheet,
			$depth,
			$options,
			$ruleCount,
			CSSPageDescriptors::class,
		);
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
				$marginRules[] = self::parseMarginRule($source, $item['start'] ?? 0, $item['end'] ?? 0, $sheet);
			} catch (InvalidArgumentException $exception) {
				$sheet->recordParseError($exception->getMessage(), $item['offset']);
			}
		}

		$pageRule = new CSSPageRule($selector, $style);
		$pageRule->initializeRules($sheet);
		foreach ($marginRules as $marginRule) {
			$pageRule->getCssRules()->append($marginRule);
			$ruleCount++;
		}

		return $pageRule;
	}

	private static function createFontFeatureValuesRule(
		string $fontFamily,
		string $source,
		int $bodyStart,
		int $bodyEnd,
		CSSStyleSheet $sheet,
		CSSParserOptions $options,
		int &$ruleCount,
	): CSSFontFeatureValuesRule {
		$rule = new CSSFontFeatureValuesRule($fontFamily);
		$rule->initializeRules($sheet);

		$offset = $bodyStart;
		while ($offset < $bodyEnd) {
			$offset = self::skipTrivia($source, $offset, $bodyEnd, $sheet);
			if ($offset >= $bodyEnd) {
				break;
			}

			$delimiter = CSSSyntaxScanner::findNextTopLevelDelimiter($source, ['{', ';', '}'], $offset, $bodyEnd);
			if ($delimiter === null) {
				$sheet->recordParseError('Unterminated rule in @font-feature-values.', $offset);
				break;
			}
			if ($delimiter['delimiter'] !== '{') {
				$sheet->recordParseError('Unexpected token in @font-feature-values.', $delimiter['offset']);
				$offset = $delimiter['offset'] + 1;
				continue;
			}

			$close = CSSSyntaxScanner::findMatchingDelimiter($source, $delimiter['offset'], $bodyEnd);
			if ($close === null || $close >= $bodyEnd) {
				$sheet->recordParseError('Unclosed @font-feature-values feature block.', $delimiter['offset']);
				break;
			}

			$prelude = trim(CSSSyntaxScanner::removeComments($source, $offset, $delimiter['offset']));
			$blockStart = $delimiter['offset'] + 1;
			$blockBody = substr($source, $blockStart, $close - $blockStart);
			$ruleStart = $offset;
			$offset = $close + 1;

			try {
				[$featureType, $extra] = self::splitAtRulePrelude($prelude);
			} catch (InvalidArgumentException $exception) {
				$sheet->recordParseError($exception->getMessage(), $ruleStart);
				continue;
			}

			if ($extra === '' && isset(self::FONT_FEATURE_CATEGORIES[$featureType])) {
				$errors = [];
				$declarations = CSSStyleDeclaration::parseWithOffsets($blockBody, false, $errors, $blockStart);
				foreach ($errors as $error) {
					$sheet->recordParseError($error['message'], $error['offset']);
				}
				$map = $rule->getFeatureMap($featureType);
				foreach ($declarations as $featureName => $declaration) {
					$ints = [];
					$valid = true;
					foreach (preg_split('/\s+/', trim($declaration['value'])) ?: [] as $token) {
						if ($token === '' || !preg_match('/^\d+$/D', $token)) {
							$valid = false;
							break;
						}
						$ints[] = (int) $token;
					}
					if (!$valid || $ints === []) {
						$sheet->recordParseError('Font feature values must be non-empty integer lists.', $blockStart);
						continue;
					}
					$map->set($featureName, $ints);
				}
			} elseif (self::canRecordRule($options, $ruleCount, $sheet, $ruleStart)) {
				$rule->getCssRules()->append(new CSSOpaqueRule(substr($source, $ruleStart, $offset - $ruleStart)));
				$ruleCount++;
			}
		}

		return $rule;
	}

	private static function parseMarginRule(string $source, int $start, int $end, CSSStyleSheet $sheet): CSSMarginRule
	{
		$delimiter = CSSSyntaxScanner::findNextTopLevelDelimiter($source, ['{'], $start, $end);
		if ($delimiter === null) {
			throw new InvalidArgumentException('Malformed page margin rule.');
		}
		[$name, $extra] = self::splitAtRulePrelude(trim(CSSSyntaxScanner::removeComments($source, $start, $delimiter['offset'])));
		if ($extra !== '') {
			throw new InvalidArgumentException('Unexpected prelude in page margin rule.');
		}
		$close = CSSSyntaxScanner::findMatchingDelimiter($source, $delimiter['offset'], $end);
		if ($close !== $end - 1) {
			throw new InvalidArgumentException('Malformed page margin rule block.');
		}

		$bodyOffset = $delimiter['offset'] + 1;
		$body = substr($source, $bodyOffset, $close - $bodyOffset);
		$errors = [];
		$style = CSSStyleDeclaration::parseWithOffsets($body, false, $errors, $bodyOffset);
		foreach ($errors as $error) {
			$sheet->recordParseError($error['message'], $error['offset']);
		}

		return new CSSMarginRule($name, $style);
	}

	private static function mergeStyle(CSSStyleDeclaration $target, CSSStyleDeclaration $source): void
	{
		foreach ($source as $property => $declaration) {
			$target->setProperty($property, $declaration['value'], $declaration['priority']);
		}
	}

	private static function parseNestedRule(
		string $source,
		int $start,
		int $end,
		CSSStyleSheet $sheet,
		int $depth,
		CSSParserOptions $options,
		int &$ruleCount,
	): CSSRule {
		if ($options->maxDepth !== null && $depth > $options->maxDepth) {
			throw new InvalidArgumentException(sprintf('Maximum CSS nesting depth of %d exceeded.', $options->maxDepth));
		}

		$delimiter = CSSSyntaxScanner::findNextTopLevelDelimiter($source, ['{', ';', '}'], $start, $end);
		if ($delimiter === null) {
			throw new InvalidArgumentException('Unterminated nested CSS rule.');
		}
		$prelude = trim(CSSSyntaxScanner::removeComments($source, $start, $delimiter['offset']));
		if ($delimiter['delimiter'] === ';') {
			return self::parseSingleRule(substr($source, $start, $end - $start), $options);
		}
		if ($delimiter['delimiter'] !== '{') {
			throw new InvalidArgumentException('Invalid nested CSS rule.');
		}
		$close = CSSSyntaxScanner::findMatchingDelimiter($source, $delimiter['offset'], $end);
		if ($close !== $end - 1) {
			throw new InvalidArgumentException('Malformed nested CSS rule block.');
		}
		if ($prelude === '') {
			throw new InvalidArgumentException('CSS rule prelude cannot be empty.');
		}

		$bodyStart = $delimiter['offset'] + 1;
		if (str_starts_with($prelude, '@')) {
			[$name] = self::splitAtRulePrelude($prelude);
			if (isset(self::GROUPING_AT_RULES[$name])) {
				return self::createBlockAtRule($prelude, $source, $bodyStart, $close, $sheet, true, $depth + 1, $options, $ruleCount)
					?? new CSSOpaqueRule(substr($source, $start, $end - $start));
			}
			return self::createBlockAtRule($prelude, $source, $bodyStart, $close, $sheet, false, $depth + 1, $options, $ruleCount)
				?? new CSSOpaqueRule(substr($source, $start, $end - $start));
		}

		[$style, $nestedRules, $errors] = self::parseStyleBlock($source, $bodyStart, $close, $sheet, $depth + 1, $options, $ruleCount);
		foreach ($errors as $error) {
			$sheet->recordParseError($error['message'], $error['offset']);
		}
		$rule = new CSSStyleRule($prelude, $style);
		$rule->attach($sheet, null);
		self::appendNestedContent($rule, $nestedRules, $source, $sheet, $depth + 1, $options, $ruleCount);

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

	private static function skipTrivia(string $source, int $offset, int $end, ?CSSStyleSheet $sheet = null): int
	{
		while ($offset < $end) {
			if (ctype_space($source[$offset])) {
				$offset++;
				continue;
			}
			if ($source[$offset] === '/' && ($offset + 1 < $end) && $source[$offset + 1] === '*') {
				$commentEnd = strpos($source, '*/', $offset + 2);
				if ($commentEnd === false || $commentEnd + 1 >= $end) {
					$sheet?->recordParseError('Unclosed CSS comment.', $offset);
					return $end;
				}
				$offset = $commentEnd + 2;
				continue;
			}
			break;
		}

		return $offset;
	}

	private static function canRecordRule(CSSParserOptions $options, int $ruleCount, CSSStyleSheet $sheet, int $offset): bool
	{
		if ($options->maxRules !== null && $ruleCount >= $options->maxRules) {
			$sheet->recordParseError(sprintf('Maximum CSS rule limit of %d exceeded.', $options->maxRules), $offset);
			return false;
		}

		return true;
	}
}
