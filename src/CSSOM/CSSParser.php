<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;

/**
 * Single-pass cursor-driven parser for standalone CSS stylesheets, rules, and declaration blocks.
 *
 * `parseStylesheet()` recovers from malformed sections and reports byte-offset diagnostics,
 * whereas `parseSingleRule()`, `parseSingleNestedRule()`, `parseSingleKeyframeRule()`,
 * `parseSingleMarginRule()`, and `parseDeclarationBlock()` validate strictly and throw
 * `InvalidArgumentException` (or `CSSParserLimitException`) on malformed or over-limit input.
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
	 *
	 * @throws CSSParserLimitException When $throwOnLimit is true and any configured parser limit is exceeded
	 */
	public static function parseStylesheet(
		string $cssText,
		?CSSParserOptions $options = null,
		bool $throwOnLimit = false,
	): CSSStyleSheet {
		$options ??= CSSParserOptions::default();
		$sheet = new CSSStyleSheet();
		$sheet->configureLimitsInternal($options);
		$sheet->configureErrorLimit($options->maxErrors);

		$length = strlen($cssText);
		if ($options->maxBytes !== null && $length > $options->maxBytes) {
			$message = sprintf('CSS input exceeds maximum size of %d bytes.', $options->maxBytes);
			if ($throwOnLimit) {
				throw new CSSParserLimitException($message);
			}
			$sheet->recordParseError($message, 0);
			return $sheet;
		}

		$offset = str_starts_with($cssText, "\xEF\xBB\xBF") ? 3 : 0;
		$ruleCount = 0;
		$declarationCount = 0;
		$declLimitRecorded = false;
		self::parseRuleList(
			$cssText,
			$offset,
			$length,
			$sheet->getCssRules(),
			$sheet,
			false,
			0,
			$options,
			$ruleCount,
			$declarationCount,
			$throwOnLimit,
			false,
			null,
			$declLimitRecorded,
		);

		return $sheet;
	}

	/**
	 * Parses a CSS declaration block strictly.
	 *
	 * @throws InvalidArgumentException When any declaration is malformed or exceeds options limits
	 */
	public static function parseDeclarationBlock(string $cssText, ?CSSParserOptions $options = null): CSSStyleDeclaration
	{
		return CSSStyleDeclaration::parse($cssText, true, $diagnostics, $options);
	}

	/**
	 * Parses exactly one top-level CSS rule strictly.
	 *
	 * @throws InvalidArgumentException When the input has 0 rules, >1 rules, parse errors, or exceeds limits
	 */
	public static function parseSingleRule(
		string $cssText,
		?CSSParserOptions $options = null,
		int $initialDepth = 0,
		int $initialRuleCount = 0,
		int $initialDeclarationCount = 0,
	): CSSRule {
		self::assertNonNegativeInitialCounters($initialDepth, $initialRuleCount, $initialDeclarationCount);
		$options ??= CSSParserOptions::default();
		if ($options->maxBytes !== null && strlen($cssText) > $options->maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS rule exceeds maximum size of %d bytes.', $options->maxBytes));
		}

		$sheet = new CSSStyleSheet();
		$sheet->configureLimitsInternal($options);
		$length = strlen($cssText);
		$offset = str_starts_with($cssText, "\xEF\xBB\xBF") ? 3 : 0;
		$ruleCount = $initialRuleCount;
		$declarationCount = $initialDeclarationCount;

		self::parseRuleList(
			$cssText,
			$offset,
			$length,
			$sheet->getCssRules(),
			$sheet,
			false,
			$initialDepth,
			$options,
			$ruleCount,
			$declarationCount,
			true,
		);

		if ($sheet->getParseErrors() !== [] || count($sheet->getCssRules()) !== 1) {
			$message = $sheet->getParseErrors()[0]['message'] ?? 'Exactly one valid CSS rule is required.';
			throw new InvalidArgumentException($message);
		}

		$rule = $sheet->getCssRules()->getRulesRaw()[0];
		$rule->attach(null, null);

		return $rule;
	}

	/**
	 * Parses a single rule valid inside a style or grouping rule block (including nested rules).
	 *
	 * @throws InvalidArgumentException When the input is malformed or exceeds parser limits
	 */
	public static function parseSingleNestedRule(
		string $cssText,
		?CSSParserOptions $options = null,
		int $initialDepth = 0,
		int $initialRuleCount = 0,
		int $initialDeclarationCount = 0,
		bool $functionMode = false,
	): CSSRule {
		self::assertNonNegativeInitialCounters($initialDepth, $initialRuleCount, $initialDeclarationCount);
		$options ??= CSSParserOptions::default();
		if ($options->maxBytes !== null && strlen($cssText) > $options->maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS rule exceeds maximum size of %d bytes.', $options->maxBytes));
		}

		$sheet = new CSSStyleSheet();
		$sheet->configureLimitsInternal($options);
		$ruleCount = $initialRuleCount;
		$declarationCount = $initialDeclarationCount;
		$length = strlen($cssText);
		$offset = self::skipTrivia($cssText, str_starts_with($cssText, "\xEF\xBB\xBF") ? 3 : 0, $length, $sheet, false);
		if ($sheet->getParseErrors() !== []) {
			throw new InvalidArgumentException($sheet->getParseErrors()[0]['message']);
		}
		if ($offset >= $length) {
			throw new InvalidArgumentException('Exactly one valid nested CSS rule is required.');
		}

		$rule = self::parseSingleNestedRuleInternal(
			$cssText,
			$offset,
			$length,
			$sheet,
			$initialDepth,
			$options,
			$ruleCount,
			$declarationCount,
			$functionMode,
		);
		$trailing = self::skipTrivia($cssText, $offset, $length, $sheet, false);
		if ($trailing < $length) {
			throw new InvalidArgumentException('Unexpected trailing content in nested CSS rule.');
		}
		self::assertNoSheetParseErrors($sheet);

		$rule->attach($sheet, null);
		$rule->attach(null, null);

		return $rule;
	}

	/**
	 * Parses a single keyframe rule strictly, rejecting nested blocks.
	 *
	 * @throws InvalidArgumentException When the input is not a valid single keyframe rule or exceeds parser limits
	 */
	public static function parseSingleKeyframeRule(
		string $cssText,
		?CSSParserOptions $options = null,
		int $initialDepth = 0,
		int $initialRuleCount = 0,
		int $initialDeclarationCount = 0,
	): CSSKeyframeRule {
		self::assertNonNegativeInitialCounters($initialDepth, $initialRuleCount, $initialDeclarationCount);
		$options ??= CSSParserOptions::default();
		if ($options->maxBytes !== null && strlen($cssText) > $options->maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS keyframe rule exceeds maximum size of %d bytes.', $options->maxBytes));
		}

		$sheet = new CSSStyleSheet();
		$sheet->configureLimitsInternal($options);
		$ruleCount = $initialRuleCount;
		$declarationCount = $initialDeclarationCount;
		$offset = str_starts_with($cssText, "\xEF\xBB\xBF") ? 3 : 0;

		self::parseRuleList(
			$cssText,
			$offset,
			strlen($cssText),
			$sheet->getCssRules(),
			$sheet,
			true,
			$initialDepth,
			$options,
			$ruleCount,
			$declarationCount,
			true,
		);
		if ($sheet->getParseErrors() !== [] || count($sheet->getCssRules()) !== 1) {
			$message = $sheet->getParseErrors()[0]['message'] ?? 'Exactly one valid CSS keyframe rule is required.';
			throw new InvalidArgumentException($message);
		}
		$rule = $sheet->getCssRules()->getRulesRaw()[0];
		if (!$rule instanceof CSSKeyframeRule) {
			throw new InvalidArgumentException('A keyframe rule requires a keyframe selector and declaration block.');
		}
		$rule->attach(null, null);

		return $rule;
	}

	/**
	 * Parses a single `@page` margin rule strictly.
	 *
	 * @throws InvalidArgumentException When the input is not a valid `@margin` rule or exceeds parser limits
	 */
	public static function parseSingleMarginRule(
		string $cssText,
		?CSSParserOptions $options = null,
		int $initialDepth = 0,
		int $initialRuleCount = 0,
		int $initialDeclarationCount = 0,
	): CSSMarginRule {
		self::assertNonNegativeInitialCounters($initialDepth, $initialRuleCount, $initialDeclarationCount);
		$options ??= CSSParserOptions::default();
		if ($options->maxBytes !== null && strlen($cssText) > $options->maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS margin rule exceeds maximum size of %d bytes.', $options->maxBytes));
		}
		if ($options->maxDepth !== null && $initialDepth > $options->maxDepth) {
			throw new CSSParserLimitException(sprintf('Maximum CSS nesting depth of %d exceeded.', $options->maxDepth));
		}
		if ($options->maxRules !== null && $initialRuleCount >= $options->maxRules) {
			throw new CSSParserLimitException(sprintf('Maximum CSS rule limit of %d exceeded.', $options->maxRules));
		}

		$sheet = new CSSStyleSheet();
		$sheet->configureLimitsInternal($options);
		$length = strlen($cssText);
		$offset = self::skipTrivia($cssText, str_starts_with($cssText, "\xEF\xBB\xBF") ? 3 : 0, $length, $sheet, false);
		if ($sheet->getParseErrors() !== []) {
			throw new InvalidArgumentException($sheet->getParseErrors()[0]['message']);
		}
		$delimiter = CSSSyntaxScanner::findNextTopLevelDelimiterBytes($cssText, '{;}', $offset, $length);
		if ($delimiter === null || $delimiter['delimiter'] !== '{') {
			throw new InvalidArgumentException('Malformed page margin rule.');
		}

		$prelude = self::extractPrelude($cssText, $offset, $delimiter['offset'], $sheet);
		if ($prelude === null) {
			self::assertNoSheetParseErrors($sheet);
			throw new InvalidArgumentException('CSS margin rule prelude contains disallowed control characters.');
		}
		[$name, $extra] = self::splitAtRulePrelude($prelude);
		if ($extra !== '') {
			throw new InvalidArgumentException('Unexpected prelude in page margin rule.');
		}

		$braceOffset = $delimiter['offset'];
		$offset = $braceOffset + 1;
		$style = new CSSStyleDeclaration();
		$style->setLimitsInternal($options->maxBytes, $options->maxDeclarations);
		$ruleCount = $initialRuleCount;
		$declarationCount = $initialDeclarationCount;

		$closed = self::parseStyleBlock(
			$cssText,
			$offset,
			$length,
			$style,
			null,
			$sheet,
			$initialDepth + 1,
			$options,
			$ruleCount,
			$declarationCount,
			true,
			false,
			false,
			false,
			$braceOffset,
		);
		if (!$closed) {
			throw new InvalidArgumentException('Unclosed page margin rule block.');
		}
		if (self::skipTrivia($cssText, $offset, $length, $sheet, false) < $length) {
			throw new InvalidArgumentException('Unexpected trailing content in page margin rule.');
		}
		self::assertNoSheetParseErrors($sheet);

		return new CSSMarginRule($name, $style, $options->maxBytes);
	}

	private static function assertNoSheetParseErrors(CSSStyleSheet $sheet): void
	{
		$errors = $sheet->getParseErrors();
		if ($errors !== []) {
			throw new InvalidArgumentException($errors[0]['message']);
		}
	}

	private static function parseRuleList(
		string $source,
		int &$offset,
		int $end,
		CSSRuleList $rules,
		CSSStyleSheet $sheet,
		bool $keyframes,
		int $depth,
		CSSParserOptions $options,
		int &$ruleCount,
		int &$declarationCount,
		bool $throwOnLimit,
		bool $stopOnClosingBrace = false,
		?int $openBraceOffset = null,
		bool &$declLimitRecorded = false,
	): bool {
		if ($options->maxDepth !== null && $depth > $options->maxDepth) {
			$message = sprintf('Maximum CSS nesting depth of %d exceeded.', $options->maxDepth);
			if ($throwOnLimit) {
				throw new CSSParserLimitException($message);
			}
			$sheet->recordParseError($message, $openBraceOffset ?? $offset);
			return false;
		}

		$allowCdoCdc = $depth === 0 && !$stopOnClosingBrace;
		$limitReached = false;

		while ($offset < $end) {
			$offset = self::skipTrivia($source, $offset, $end, $sheet, $allowCdoCdc);
			if ($offset >= $end) {
				break;
			}

			$delimiter = CSSSyntaxScanner::findNextTopLevelDelimiterBytes($source, '{;}', $offset, $end);
			if ($delimiter === null) {
				if ($stopOnClosingBrace) {
					$sheet->recordParseError('Unclosed CSS rule block.', $openBraceOffset ?? $offset);
					$offset = $end;
					return false;
				}
				$sheet->recordParseError('Unterminated CSS rule.', $offset);
				$offset = $end;
				return true;
			}

			$ruleStart = $offset;
			$delimChar = $delimiter['delimiter'];
			$delimOffset = $delimiter['offset'];

			if ($delimChar === '}') {
				if ($stopOnClosingBrace) {
					if ($ruleStart < $delimOffset) {
						$sheet->recordParseError('Unexpected trailing tokens before closing brace.', $ruleStart);
					}
					$offset = $delimOffset + 1;
					return true;
				}
				$sheet->recordParseError('Unexpected closing brace.', $delimOffset);
				$offset = $delimOffset + 1;
				continue;
			}

			if ($limitReached || !self::canRecordRule($options, $ruleCount, $sheet, $ruleStart, $throwOnLimit)) {
				$limitReached = true;
				if ($delimChar === ';') {
					$offset = $delimOffset + 1;
					continue;
				}
				$close = CSSSyntaxScanner::findMatchingBlockEnd($source, $delimOffset, $end);
				if ($close === null || $close >= $end) {
					if ($stopOnClosingBrace) {
						$sheet->recordParseError('Unclosed CSS rule block.', $openBraceOffset ?? $delimOffset);
						$offset = $end;
						return false;
					}
					$offset = $end;
					return true;
				}
				$offset = $close + 1;
				continue;
			}

			$prelude = self::extractPrelude($source, $ruleStart, $delimOffset, $sheet);
			if ($prelude === null) {
				if ($delimChar === ';') {
					$offset = $delimOffset + 1;
				} else {
					self::skipMalformedBlock($source, $delimOffset, $offset, $end, $sheet);
				}
				continue;
			}

			if ($delimChar === ';') {
				$offset = $delimOffset + 1;
				if ($keyframes) {
					$sheet->recordParseError(
						str_starts_with($prelude, '@')
							? 'Nested at-rules are not allowed inside @keyframes.'
							: 'Stray declaration or statement is not allowed directly inside @keyframes.',
						$ruleStart,
					);
					continue;
				}
				if (!str_starts_with($prelude, '@')) {
					$sheet->recordParseError('A qualified rule requires a block.', $ruleStart);
					continue;
				}
				try {
					[$name] = self::splitAtRulePrelude($prelude);
					if (isset(self::STATEMENT_AT_RULES[$name])) {
						$stmt = self::parseAtRulePrelude($prelude, $options->maxBytes);
						$stopOnClosingBrace ? $rules->appendDetached($stmt) : $rules->appendParsedInternal($stmt);
						$ruleCount++;
					} else {
						$raw = substr($source, $ruleStart, $offset - $ruleStart);
						$opaque = self::createValidatedOpaqueRule($raw, $sheet, $ruleStart, $options->maxBytes);
						if ($opaque !== null) {
							$stopOnClosingBrace ? $rules->appendDetached($opaque) : $rules->appendParsedInternal($opaque);
							$ruleCount++;
						}
					}
				} catch (InvalidArgumentException $exception) {
					$sheet->recordParseError($exception->getMessage(), $ruleStart);
				}
				continue;
			}

			// $delimChar === '{'
			if ($prelude === '') {
				$sheet->recordParseError('CSS rule prelude cannot be empty.', $ruleStart);
				$close = CSSSyntaxScanner::findMatchingBlockEnd($source, $delimOffset, $end);
				if ($close === null || $close >= $end) {
					$sheet->recordParseError('Unclosed CSS rule block.', $delimOffset);
					$offset = $end;
					return !$stopOnClosingBrace;
				}
				$offset = $close + 1;
				continue;
			}

			if ($keyframes && str_starts_with($prelude, '@')) {
				$sheet->recordParseError('Nested at-rules are not allowed inside @keyframes.', $ruleStart);
				$close = CSSSyntaxScanner::findMatchingBlockEnd($source, $delimOffset, $end);
				if ($close === null || $close >= $end) {
					$sheet->recordParseError('Unclosed CSS rule block.', $delimOffset);
					$offset = $end;
					return !$stopOnClosingBrace;
				}
				$offset = $close + 1;
				continue;
			}

			if (str_starts_with($prelude, '@')) {
				$prepared = self::prepareBlockAtRule(
					$prelude,
					$source,
					$ruleStart,
					$delimOffset,
					$offset,
					$end,
					$sheet,
					false,
					$depth + 1,
					$options,
					$ruleCount,
					$declarationCount,
					$throwOnLimit,
					$declLimitRecorded,
				);
				if ($prepared !== null) {
					$mode = $prepared['mode'];
					$rule = $prepared['rule'];
					if ($mode === 'done') {
						$closed = true;
					} elseif ($mode === 'style') {
						/** @var CSSGroupingRule $rule */
						/** @var CSSStyleDeclaration $preparedStyle */
						$preparedStyle = $prepared['style'];
						$closed = self::parseStyleBlock(
							$source,
							$offset,
							$end,
							$preparedStyle,
							$rule,
							$sheet,
							$depth + 1,
							$options,
							$ruleCount,
							$declarationCount,
							$throwOnLimit,
							$prepared['allowNested'] ?? true,
							$prepared['pageMode'] ?? false,
							$prepared['functionMode'] ?? false,
							$delimOffset,
							$declLimitRecorded,
						);
					} elseif ($mode === 'ffv') {
						/** @var CSSFontFeatureValuesRule $rule */
						$closed = self::parseFontFeatureValuesBody(
							$rule,
							$source,
							$offset,
							$end,
							$sheet,
							$depth + 1,
							$options,
							$ruleCount,
							$declarationCount,
							$throwOnLimit,
							$delimOffset,
							$declLimitRecorded,
						);
					} else {
						/** @var CSSGroupingRule $rule */
						$closed = self::parseRuleList(
							$source,
							$offset,
							$end,
							$rule->getCssRules(),
							$sheet,
							$rule instanceof CSSKeyframesRule,
							$depth + 1,
							$options,
							$ruleCount,
							$declarationCount,
							$throwOnLimit,
							true,
							$delimOffset,
							$declLimitRecorded,
						);
					}
					if ($closed) {
						$stopOnClosingBrace ? $rules->appendDetached($rule) : $rules->appendParsedInternal($rule);
					}
				}
				continue;
			}

			if ($options->maxDepth !== null && ($depth + 1) > $options->maxDepth) {
				$message = sprintf('Maximum CSS nesting depth of %d exceeded.', $options->maxDepth);
				if ($throwOnLimit) {
					throw new CSSParserLimitException($message);
				}
				$sheet->recordParseError($message, $delimOffset + 1);
				$close = CSSSyntaxScanner::findMatchingBlockEnd($source, $delimOffset, $end);
				if ($close === null || $close >= $end) {
					$offset = $end;
					return !$stopOnClosingBrace;
				}
				$offset = $close + 1;
				continue;
			}

			try {
				$rule = $keyframes
					? new CSSKeyframeRule($prelude, new CSSStyleProperties(), $options->maxBytes)
					: new CSSStyleRule($prelude, new CSSStyleProperties(), $options->maxBytes);
			} catch (InvalidArgumentException $exception) {
				$sheet->recordParseError($exception->getMessage(), $ruleStart);
				$close = CSSSyntaxScanner::findMatchingBlockEnd($source, $delimOffset, $end);
				if ($close === null || $close >= $end) {
					$sheet->recordParseError('Unclosed CSS rule block.', $delimOffset);
					$offset = $end;
					return !$stopOnClosingBrace;
				}
				$offset = $close + 1;
				continue;
			}

			$ruleCount++;

			$offset = $delimOffset + 1;
			$closed = self::parseStyleBlock(
				$source,
				$offset,
				$end,
				$rule->getStyle(),
				$rule instanceof CSSGroupingRule ? $rule : null,
				$sheet,
				$depth + 1,
				$options,
				$ruleCount,
				$declarationCount,
				$throwOnLimit,
				!$keyframes,
				false,
				false,
				$delimOffset,
				$declLimitRecorded,
			);
			if ($closed) {
				$stopOnClosingBrace ? $rules->appendDetached($rule) : $rules->appendParsedInternal($rule);
			}
		}

		if ($stopOnClosingBrace) {
			$sheet->recordParseError('Unclosed CSS rule block.', $openBraceOffset ?? $offset);
			return false;
		}

		return true;
	}

	private static function parseAtRulePrelude(string $prelude, ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES): CSSAtRule
	{
		[$name, $condition] = self::splitAtRulePrelude($prelude);
		return match ($name) {
			'charset' => new CSSCharsetRule($condition, $maxBytes),
			'import' => new CSSImportRule($condition, $maxBytes),
			'namespace' => new CSSNamespaceRule($condition, $maxBytes),
			'layer' => new CSSLayerStatementRule($condition, $maxBytes),
			default => new CSSAtRule($name, $condition, null, false, $maxBytes),
		};
	}

	/**
	 * Parses the at-rule header (without recursing into grouping/page/font-feature-values bodies)
	 * so callers keep a strict 1-frame-per-nesting-level stack profile.
	 *
	 * @return array{mode: string, rule: CSSRule, allowNested?: bool, style?: CSSStyleDeclaration, pageMode?: bool, functionMode?: bool}|null
	 */
	private static function prepareBlockAtRule(
		string $prelude,
		string $source,
		int $ruleStart,
		int $braceOffset,
		int &$offset,
		int $end,
		CSSStyleSheet $sheet,
		bool $allowDeclarations,
		int $depth,
		CSSParserOptions $options,
		int &$ruleCount,
		int &$declarationCount,
		bool $throwOnLimit,
		bool &$declLimitRecorded = false,
		bool $functionMode = false,
	): ?array {
		try {
			[$name, $condition] = self::splitAtRulePrelude($prelude);
		} catch (InvalidArgumentException $exception) {
			$sheet->recordParseError($exception->getMessage(), $ruleStart);
			self::skipMalformedBlock($source, $braceOffset, $offset, $end, $sheet);
			return null;
		}

		if ($options->maxDepth !== null && $depth > $options->maxDepth) {
			$message = sprintf('Maximum CSS nesting depth of %d exceeded.', $options->maxDepth);
			if ($throwOnLimit) {
				throw new CSSParserLimitException($message);
			}
			$sheet->recordParseError($message, $braceOffset + 1);
			self::skipMalformedBlock($source, $braceOffset, $offset, $end, $sheet);
			return null;
		}

		$isKnown = ($name === 'page'
			|| isset(self::GROUPING_AT_RULES[$name])
			|| isset(self::DECLARATION_AT_RULES[$name]))
			&& !($allowDeclarations && ($name === 'keyframes' || $name === '-webkit-keyframes'));

		if (!$isKnown) {
			$close = CSSSyntaxScanner::findMatchingBlockEnd($source, $braceOffset, $end);
			if ($close === null || $close >= $end) {
				$sheet->recordParseError('Unclosed CSS rule block.', $braceOffset);
				$offset = $end;
				return null;
			}
			$raw = substr($source, $ruleStart, $close - $ruleStart + 1);
			$offset = $close + 1;
			$opaque = self::createValidatedOpaqueRule($raw, $sheet, $ruleStart, $options->maxBytes);
			if ($opaque === null) {
				return null;
			}
			$ruleCount++;

			return ['mode' => 'done', 'rule' => $opaque];
		}

		if ($name === 'page') {
			try {
				$pageRule = new CSSPageRule($condition, new CSSPageDescriptors(), [], $options->maxBytes);
			} catch (InvalidArgumentException $exception) {
				$sheet->recordParseError($exception->getMessage(), $ruleStart);
				self::skipMalformedBlock($source, $braceOffset, $offset, $end, $sheet);
				return null;
			}
			$ruleCount++;
			$offset = $braceOffset + 1;

			return [
				'mode' => 'style',
				'rule' => $pageRule,
				'style' => $pageRule->getStyle(),
				'allowNested' => true,
				'pageMode' => true,
				'functionMode' => false,
			];
		}

		if ($name === 'font-feature-values') {
			try {
				$ffvRule = new CSSFontFeatureValuesRule($condition, $options->maxBytes);
			} catch (InvalidArgumentException $exception) {
				$sheet->recordParseError($exception->getMessage(), $ruleStart);
				self::skipMalformedBlock($source, $braceOffset, $offset, $end, $sheet);
				return null;
			}
			$ruleCount++;
			$offset = $braceOffset + 1;

			return ['mode' => 'ffv', 'rule' => $ffvRule];
		}

		if (($name === 'starting-style' || $name === 'font-face' || $name === 'view-transition') && $condition !== '') {
			$sheet->recordParseError(sprintf('@%s does not accept a prelude.', $name), $ruleStart);
			self::skipMalformedBlock($source, $braceOffset, $offset, $end, $sheet);
			return null;
		}

		if (isset(self::GROUPING_AT_RULES[$name])) {
			try {
				$rule = match ($name) {
					'media' => new CSSMediaRule($condition, $options->maxBytes),
					'supports' => new CSSSupportsRule($condition, $options->maxBytes),
					'container' => new CSSContainerRule($condition, $options->maxBytes),
					'scope' => new CSSScopeRule($condition, $options->maxBytes),
					'layer' => new CSSLayerBlockRule($condition, $options->maxBytes),
					'keyframes', '-webkit-keyframes' => new CSSKeyframesRule($condition, $name, $options->maxBytes),
					'starting-style' => new CSSStartingStyleRule($options->maxBytes),
					'function' => CSSFunctionRule::fromPrelude($condition, $options->maxBytes),
					default => new CSSGroupingRule($name, $condition, $options->maxBytes),
				};
			} catch (InvalidArgumentException $exception) {
				$sheet->recordParseError($exception->getMessage(), $ruleStart);
				self::skipMalformedBlock($source, $braceOffset, $offset, $end, $sheet);
				return null;
			}

			$ruleCount++;
			$offset = $braceOffset + 1;

			if (($allowDeclarations || $name === 'function') && !$rule instanceof CSSKeyframesRule) {
				$isFunction = $functionMode || $rule instanceof CSSFunctionRule;
				return [
					'mode' => 'style',
					'rule' => $rule,
					'style' => $isFunction ? new CSSFunctionDescriptors() : new CSSStyleProperties(),
					'allowNested' => true,
					'pageMode' => false,
					'functionMode' => $isFunction,
				];
			}

			return ['mode' => 'list', 'rule' => $rule];
		}

		// DECLARATION_AT_RULES
		$declarationClass = match ($name) {
			'font-face' => CSSFontFaceDescriptors::class,
			'position-try' => CSSPositionTryDescriptors::class,
			default => CSSStyleDeclaration::class,
		};
		$style = new $declarationClass();
		$offset = $braceOffset + 1;
		$sawEmptyInitialValue = false;
		$closed = self::parseStyleBlock(
			$source,
			$offset,
			$end,
			$style,
			null,
			$sheet,
			$depth,
			$options,
			$ruleCount,
			$declarationCount,
			$throwOnLimit,
			false,
			false,
			false,
			$braceOffset,
			$declLimitRecorded,
			$name === 'property',
			$sawEmptyInitialValue,
		);
		if (!$closed) {
			return null;
		}

		try {
			$rule = match ($name) {
				'font-face' => new CSSFontFaceRule($style, $options->maxBytes),
				'property' => new CSSPropertyRule($condition, $style, $sawEmptyInitialValue, $options->maxBytes),
				'counter-style' => new CSSCounterStyleRule($condition, $style, $options->maxBytes),
				'font-palette-values' => new CSSFontPaletteValuesRule($condition, $style, $options->maxBytes),
				'position-try' => new CSSPositionTryRule($condition, $style, $options->maxBytes),
				'view-transition' => new CSSViewTransitionRule($style, $options->maxBytes),
				default => new CSSAtRule($name, $condition, $style, true, $options->maxBytes),
			};
		} catch (InvalidArgumentException $exception) {
			$sheet->recordParseError($exception->getMessage(), $ruleStart);
			return null;
		}

		$ruleCount++;

		return ['mode' => 'done', 'rule' => $rule];
	}

	/**
	 * Inline body dispatch helper so callers incur 1 PHP frame per nesting level (never 2).
	 *
	 * @param array{mode: string, rule: CSSRule, allowNested?: bool, style?: CSSStyleDeclaration, pageMode?: bool, functionMode?: bool} $prepared
	 */
	private static function finishPreparedAtRuleBody(
		array $prepared,
		string $source,
		int $braceOffset,
		int &$offset,
		int $end,
		CSSStyleSheet $sheet,
		int $childDepth,
		CSSParserOptions $options,
		int &$ruleCount,
		int &$declarationCount,
		bool $throwOnLimit,
		bool &$declLimitRecorded = false,
	): ?CSSRule {
		$mode = $prepared['mode'];
		if ($mode === 'done') {
			return $prepared['rule'];
		}

		if ($mode === 'style') {
			/** @var CSSGroupingRule $rule */
			$rule = $prepared['rule'];
			/** @var CSSStyleDeclaration $style */
			$style = $prepared['style'];
			$closed = self::parseStyleBlock(
				$source,
				$offset,
				$end,
				$style,
				$rule,
				$sheet,
				$childDepth,
				$options,
				$ruleCount,
				$declarationCount,
				$throwOnLimit,
				$prepared['allowNested'] ?? true,
				$prepared['pageMode'] ?? false,
				$prepared['functionMode'] ?? false,
				$braceOffset,
				$declLimitRecorded,
			);
			return $closed ? $rule : null;
		}

		if ($mode === 'ffv') {
			/** @var CSSFontFeatureValuesRule $rule */
			$rule = $prepared['rule'];
			$closed = self::parseFontFeatureValuesBody(
				$rule,
				$source,
				$offset,
				$end,
				$sheet,
				$childDepth,
				$options,
				$ruleCount,
				$declarationCount,
				$throwOnLimit,
				$braceOffset,
				$declLimitRecorded,
			);
			return $closed ? $rule : null;
		}

		/** @var CSSGroupingRule $rule */
		$rule = $prepared['rule'];
		$closed = self::parseRuleList(
			$source,
			$offset,
			$end,
			$rule->getCssRules(),
			$sheet,
			$rule instanceof CSSKeyframesRule,
			$childDepth,
			$options,
			$ruleCount,
			$declarationCount,
			$throwOnLimit,
			true,
			$braceOffset,
			$declLimitRecorded,
		);
		return $closed ? $rule : null;
	}

	private static function parseStyleBlock(
		string $source,
		int &$offset,
		int $end,
		CSSStyleDeclaration $style,
		?CSSGroupingRule $ownerRule,
		CSSStyleSheet $sheet,
		int $depth,
		CSSParserOptions $options,
		int &$ruleCount,
		int &$declarationCount,
		bool $throwOnLimit,
		bool $allowNestedRules,
		bool $pageMode,
		bool $functionMode,
		int $openBraceOffset,
		bool &$declLimitRecorded = false,
		bool $allowEmptyInitialValue = false,
		bool &$sawEmptyInitialValue = false,
	): bool {
		if ($options->maxDepth !== null && $depth > $options->maxDepth) {
			$message = sprintf('Maximum CSS nesting depth of %d exceeded.', $options->maxDepth);
			if ($throwOnLimit) {
				throw new CSSParserLimitException($message);
			}
			$sheet->recordParseError($message, $openBraceOffset + 1);
			self::skipMalformedBlock($source, $openBraceOffset, $offset, $end, $sheet);
			return false;
		}

		$diagnostics = [];
		$currentStyle = $style;
		$hasNestedRule = false;
		$isGroupingWithDecls = $ownerRule !== null && !$ownerRule instanceof CSSStyleRule && !$pageMode;

		while ($offset < $end) {
			$offset = self::skipTrivia($source, $offset, $end, $sheet, false);
			if ($offset >= $end) {
				break;
			}

			$delimiter = CSSSyntaxScanner::findNextTopLevelDelimiterBytes($source, ';{}', $offset, $end);
			if ($delimiter === null) {
				if (!$declLimitRecorded) {
					$declLimitRecorded = !$currentStyle->parseDeclarationSlice(
						$source,
						$offset,
						$end,
						false,
						$diagnostics,
						$options,
						$declarationCount,
						$throwOnLimit,
						true,
						$allowEmptyInitialValue,
						$sawEmptyInitialValue,
					);
				}
				self::flushDiagnostics($sheet, $diagnostics);
				$sheet->recordParseError('Unclosed CSS rule block.', $openBraceOffset);
				$offset = $end;
				return false;
			}

			$delimChar = $delimiter['delimiter'];
			$delimOffset = $delimiter['offset'];

			if ($delimChar === ';') {
				if (!$declLimitRecorded) {
					$declLimitRecorded = !$currentStyle->parseDeclarationSlice(
						$source,
						$offset,
						$delimOffset,
						false,
						$diagnostics,
						$options,
						$declarationCount,
						$throwOnLimit,
						true,
						$allowEmptyInitialValue,
						$sawEmptyInitialValue,
					);
				}
				$offset = $delimOffset + 1;
				continue;
			}

			if ($delimChar === '}') {
				if ($delimOffset > $offset && !$declLimitRecorded) {
					$declLimitRecorded = !$currentStyle->parseDeclarationSlice(
						$source,
						$offset,
						$delimOffset,
						false,
						$diagnostics,
						$options,
						$declarationCount,
						$throwOnLimit,
						true,
						$allowEmptyInitialValue,
						$sawEmptyInitialValue,
					);
				}
				self::flushDiagnostics($sheet, $diagnostics);
				if ($ownerRule !== null && !$pageMode && ($hasNestedRule || $isGroupingWithDecls) && count($currentStyle) > 0) {
					if (self::canRecordRule($options, $ruleCount, $sheet, $delimOffset, $throwOnLimit)) {
						$declarations = $functionMode && $currentStyle instanceof CSSFunctionDescriptors
							? new CSSFunctionDeclarations($currentStyle, $options->maxBytes)
							: new CSSNestedDeclarations($currentStyle, $options->maxBytes);
						$ownerRule->getCssRules()->appendDetached($declarations);
						$ruleCount++;
					}
				}
				$offset = $delimOffset + 1;
				return true;
			}

			// $delimChar === '{'
			if (!$allowNestedRules) {
				$sheet->recordParseError(
					$ownerRule === null
						? 'Nested rules are not allowed inside a @keyframes or declaration block.'
						: 'Nested rules are not allowed inside a @keyframes block.',
					$offset,
				);
				$close = CSSSyntaxScanner::findMatchingBlockEnd($source, $delimOffset, $end);
				if ($close === null || $close >= $end) {
					$sheet->recordParseError('Unclosed CSS rule block.', $delimOffset);
					self::flushDiagnostics($sheet, $diagnostics);
					$offset = $end;
					return false;
				}
				$offset = $close + 1;
				continue;
			}

			self::flushDiagnostics($sheet, $diagnostics);

			if ($pageMode && $ownerRule !== null) {
				$prelude = self::extractPrelude($source, $offset, $delimOffset, $sheet);
				if ($prelude === null || !self::canRecordRule($options, $ruleCount, $sheet, $offset, $throwOnLimit)) {
					self::skipMalformedBlock($source, $delimOffset, $offset, $end, $sheet);
					continue;
				}
				$marginStyle = new CSSStyleDeclaration();
				$ruleStart = $offset;
				$offset = $delimOffset + 1;
				$diagnostics = [];
				$closed = self::parseStyleBlock(
					$source,
					$offset,
					$end,
					$marginStyle,
					null,
					$sheet,
					$depth + 1,
					$options,
					$ruleCount,
					$declarationCount,
					$throwOnLimit,
					false,
					false,
					false,
					$delimOffset,
					$declLimitRecorded,
				);
				if ($closed) {
					try {
						[$marginName, $extra] = self::splitAtRulePrelude($prelude);
						if ($extra !== '') {
							throw new InvalidArgumentException('Unexpected prelude in page margin rule.');
						}
						$ownerRule->getCssRules()->appendDetached(new CSSMarginRule($marginName, $marginStyle, $options->maxBytes));
						$ruleCount++;
					} catch (InvalidArgumentException $exception) {
						$sheet->recordParseError($exception->getMessage(), $ruleStart);
					}
				}
				continue;
			}

			// Style rule / grouping rule / @function nested rule
			if ($ownerRule !== null && ($hasNestedRule || $isGroupingWithDecls) && count($currentStyle) > 0) {
				if (self::canRecordRule($options, $ruleCount, $sheet, $offset, $throwOnLimit)) {
					$declarations = $functionMode && $currentStyle instanceof CSSFunctionDescriptors
						? new CSSFunctionDeclarations($currentStyle, $options->maxBytes)
						: new CSSNestedDeclarations($currentStyle, $options->maxBytes);
					$ownerRule->getCssRules()->appendDetached($declarations);
					$ruleCount++;
				}
			}

			$ruleStart = $offset;
			$prelude = self::extractPrelude($source, $ruleStart, $delimOffset, $sheet);

			if ($prelude === null || !self::canRecordRule($options, $ruleCount, $sheet, $ruleStart, $throwOnLimit)) {
				self::skipMalformedBlock($source, $delimOffset, $offset, $end, $sheet);
				$hasNestedRule = true;
				$currentStyle = $functionMode ? new CSSFunctionDescriptors() : new CSSStyleProperties();
				continue;
			}

			if ($prelude === '') {
				$sheet->recordParseError('CSS rule prelude cannot be empty.', $ruleStart);
				self::skipMalformedBlock($source, $delimOffset, $offset, $end, $sheet);
				$hasNestedRule = true;
				$currentStyle = $functionMode ? new CSSFunctionDescriptors() : new CSSStyleProperties();
				continue;
			}

			if (str_starts_with($prelude, '@')) {
				$prepared = self::prepareBlockAtRule(
					$prelude,
					$source,
					$ruleStart,
					$delimOffset,
					$offset,
					$end,
					$sheet,
					true,
					$depth + 1,
					$options,
					$ruleCount,
					$declarationCount,
					$throwOnLimit,
					$declLimitRecorded,
					$functionMode,
				);
				if ($prepared !== null) {
					$mode = $prepared['mode'];
					$nested = $prepared['rule'];
					if ($mode === 'done') {
						$nestedClosed = true;
					} elseif ($mode === 'style') {
						/** @var CSSGroupingRule $nested */
						/** @var CSSStyleDeclaration $preparedStyle */
						$preparedStyle = $prepared['style'];
						$nestedClosed = self::parseStyleBlock(
							$source,
							$offset,
							$end,
							$preparedStyle,
							$nested,
							$sheet,
							$depth + 1,
							$options,
							$ruleCount,
							$declarationCount,
							$throwOnLimit,
							$prepared['allowNested'] ?? true,
							$prepared['pageMode'] ?? false,
							$prepared['functionMode'] ?? false,
							$delimOffset,
							$declLimitRecorded,
						);
					} elseif ($mode === 'ffv') {
						/** @var CSSFontFeatureValuesRule $nested */
						$nestedClosed = self::parseFontFeatureValuesBody(
							$nested,
							$source,
							$offset,
							$end,
							$sheet,
							$depth + 1,
							$options,
							$ruleCount,
							$declarationCount,
							$throwOnLimit,
							$delimOffset,
							$declLimitRecorded,
						);
					} else {
						/** @var CSSGroupingRule $nested */
						$nestedClosed = self::parseRuleList(
							$source,
							$offset,
							$end,
							$nested->getCssRules(),
							$sheet,
							$nested instanceof CSSKeyframesRule,
							$depth + 1,
							$options,
							$ruleCount,
							$declarationCount,
							$throwOnLimit,
							true,
							$delimOffset,
							$declLimitRecorded,
						);
					}
					if ($nestedClosed && $ownerRule !== null) {
						$ownerRule->getCssRules()->appendDetached($nested);
					}
				}
			} elseif ($options->maxDepth !== null && ($depth + 1) > $options->maxDepth) {
				$message = sprintf('Maximum CSS nesting depth of %d exceeded.', $options->maxDepth);
				if ($throwOnLimit) {
					throw new CSSParserLimitException($message);
				}
				$sheet->recordParseError($message, $delimOffset + 1);
				self::skipMalformedBlock($source, $delimOffset, $offset, $end, $sheet);
			} else {
				$nestedStyleRule = null;
				try {
					$nestedStyleRule = new CSSStyleRule($prelude, new CSSStyleProperties(), $options->maxBytes);
				} catch (InvalidArgumentException $exception) {
					$sheet->recordParseError($exception->getMessage(), $ruleStart);
					self::skipMalformedBlock($source, $delimOffset, $offset, $end, $sheet);
				}
				if ($nestedStyleRule !== null) {
					$ruleCount++;
					$offset = $delimOffset + 1;
					$nestedClosed = self::parseStyleBlock(
						$source,
						$offset,
						$end,
						$nestedStyleRule->getStyle(),
						$nestedStyleRule,
						$sheet,
						$depth + 1,
						$options,
						$ruleCount,
						$declarationCount,
						$throwOnLimit,
						true,
						false,
						false,
						$delimOffset,
						$declLimitRecorded,
					);
					if ($nestedClosed && $ownerRule !== null) {
						$ownerRule->getCssRules()->appendDetached($nestedStyleRule);
					}
				}
			}

			$hasNestedRule = true;
			$currentStyle = $functionMode ? new CSSFunctionDescriptors() : new CSSStyleProperties();
		}

		self::flushDiagnostics($sheet, $diagnostics);
		$sheet->recordParseError('Unclosed CSS rule block.', $openBraceOffset);
		return false;
	}

	private static function parseFontFeatureValuesBody(
		CSSFontFeatureValuesRule $rule,
		string $source,
		int &$offset,
		int $end,
		CSSStyleSheet $sheet,
		int $depth,
		CSSParserOptions $options,
		int &$ruleCount,
		int &$declarationCount,
		bool $throwOnLimit,
		int $openBraceOffset,
		bool &$declLimitRecorded = false,
	): bool {
		while ($offset < $end) {
			$offset = self::skipTrivia($source, $offset, $end, $sheet, false);
			if ($offset >= $end) {
				break;
			}

			$ruleStart = $offset;
			$delimiter = CSSSyntaxScanner::findNextTopLevelDelimiterBytes($source, '{;}', $offset, $end);
			if ($delimiter === null) {
				$sheet->recordParseError('Unterminated rule in @font-feature-values.', $offset);
				$offset = $end;
				return false;
			}

			$delimChar = $delimiter['delimiter'];
			$delimOffset = $delimiter['offset'];

			if ($delimChar === '}') {
				if ($ruleStart < $delimOffset) {
					$sheet->recordParseError('Unexpected trailing tokens before closing brace.', $ruleStart);
				}
				$offset = $delimOffset + 1;
				return true;
			}

			if ($delimChar !== '{') {
				$sheet->recordParseError('Unexpected token in @font-feature-values.', $delimOffset);
				$offset = $delimOffset + 1;
				continue;
			}

			if ($options->maxDepth !== null && ($depth + 1) > $options->maxDepth) {
				$message = sprintf('Maximum CSS nesting depth of %d exceeded.', $options->maxDepth);
				if ($throwOnLimit) {
					throw new CSSParserLimitException($message);
				}
				$sheet->recordParseError($message, $delimOffset + 1);
				self::skipMalformedBlock($source, $delimOffset, $offset, $end, $sheet);
				continue;
			}

			$prelude = self::extractPrelude($source, $ruleStart, $delimOffset, $sheet);
			if ($prelude === null) {
				self::skipMalformedBlock($source, $delimOffset, $offset, $end, $sheet);
				continue;
			}
			try {
				[$featureType, $extra] = self::splitAtRulePrelude($prelude);
			} catch (InvalidArgumentException $exception) {
				$sheet->recordParseError($exception->getMessage(), $ruleStart);
				self::skipMalformedBlock($source, $delimOffset, $offset, $end, $sheet);
				continue;
			}

			if ($extra === '' && isset(self::FONT_FEATURE_CATEGORIES[$featureType])) {
				$close = CSSSyntaxScanner::findMatchingBlockEnd($source, $delimOffset, $end);
				if ($close === null || $close >= $end) {
					$sheet->recordParseError('Unclosed @font-feature-values feature block.', $delimOffset);
					$offset = $end;
					return false;
				}
				$blockStart = $delimOffset + 1;
				$offset = $close + 1;
				$map = $rule->getFeatureMap($featureType);

				$declOffset = $blockStart;
				while ($declOffset < $close) {
					$declOffset = self::skipTrivia($source, $declOffset, $close, $sheet, false);
					if ($declOffset >= $close) {
						break;
					}
					$next = CSSSyntaxScanner::findNextTopLevelDelimiterBytes($source, ';', $declOffset, $close);
					$segEnd = $next !== null ? $next['offset'] : $close;
					$segStart = $declOffset;
					$declOffset = $next !== null ? $next['offset'] + 1 : $close;

					if ($declLimitRecorded) {
						break;
					}

					if (CSSSyntaxScanner::hasControlCharactersInSlice($source, $segStart, $segEnd - $segStart)) {
						$sheet->recordParseError('CSS font feature declaration contains disallowed control characters.', $segStart);
						continue;
					}

					$colon = CSSSyntaxScanner::findTopLevel($source, ':', $segStart, $segEnd);
					if ($colon === null) {
						if (CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($source, $segStart, $segEnd)) !== '') {
							$sheet->recordParseError('Invalid CSS font feature declaration (missing colon).', $segStart);
						}
						continue;
					}

					$rawName = CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($source, $segStart, $colon));
					$decodedName = $rawName !== '' ? CSSSyntaxScanner::decodeIdentAt($rawName, 0, strlen($rawName)) : null;
					if ($decodedName === null || $decodedName['end'] !== strlen($rawName)) {
						$sheet->recordParseError('Invalid font feature value name.', $segStart);
						continue;
					}

					if (CSSSyntaxScanner::findLastTopLevelImportant($source, $colon + 1, $segEnd) !== null) {
						$sheet->recordParseError('!important is not allowed in @font-feature-values.', $segStart);
						continue;
					}

					if ($options->maxDeclarations !== null && $declarationCount >= $options->maxDeclarations) {
						$declLimitRecorded = true;
						$message = sprintf('Maximum CSS declaration limit of %d exceeded.', $options->maxDeclarations);
						if ($throwOnLimit) {
							throw new CSSParserLimitException($message);
						}
						$sheet->recordParseError($message, $segStart);
						break;
					}
					$declarationCount++;

					$rawVal = CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($source, $colon + 1, $segEnd));
					$tokens = $rawVal === ''
						? []
						: (preg_split('/[ \t\n\r\f]+/', $rawVal, CSSStyleValue::MAX_LIST_ITEMS + 2) ?: []);
					if (count($tokens) > CSSStyleValue::MAX_LIST_ITEMS) {
						$message = sprintf('CSS font feature value list exceeds maximum limit of %d items.', CSSStyleValue::MAX_LIST_ITEMS);
						if ($throwOnLimit) {
							throw new CSSParserLimitException($message);
						}
						$sheet->recordParseError($message, $segStart);
						continue;
					}
					$ints = [];
					$valid = true;
					foreach ($tokens as $token) {
						if ($token === '' || !preg_match('/^\d+$/D', $token)) {
							$valid = false;
							break;
						}
						$stripped = ltrim($token, '0');
						$intVal = (int) $token;
						if ($stripped !== '' && (string) $intVal !== $stripped) {
							$valid = false;
							break;
						}
						$ints[] = $intVal;
					}
					if (!$valid || $ints === []) {
						$sheet->recordParseError('Font feature values must be non-empty integer lists.', $segStart);
						continue;
					}
					try {
						$map->setParsedInternal($rawName, $ints, $options->maxBytes);
					} catch (CSSParserLimitException $exception) {
						if ($throwOnLimit) {
							throw $exception;
						}
						$sheet->recordParseError($exception->getMessage(), $segStart);
					} catch (InvalidArgumentException $exception) {
						$sheet->recordParseError($exception->getMessage(), $segStart);
					}
				}
			} elseif (self::canRecordRule($options, $ruleCount, $sheet, $ruleStart, $throwOnLimit)) {
				$close = CSSSyntaxScanner::findMatchingBlockEnd($source, $delimOffset, $end);
				if ($close === null || $close >= $end) {
					$sheet->recordParseError('Unclosed @font-feature-values feature block.', $delimOffset);
					$offset = $end;
					return false;
				}
				$raw = substr($source, $ruleStart, $close - $ruleStart + 1);
				$offset = $close + 1;
				$opaque = self::createValidatedOpaqueRule($raw, $sheet, $ruleStart, $options->maxBytes);
				if ($opaque !== null) {
					$rule->getCssRules()->appendDetached($opaque);
					$ruleCount++;
				}
			} else {
				self::skipMalformedBlock($source, $delimOffset, $offset, $end, $sheet);
			}
		}

		$sheet->recordParseError('Unclosed @font-feature-values block.', $openBraceOffset);
		return false;
	}

	private static function parseSingleNestedRuleInternal(
		string $source,
		int &$offset,
		int $end,
		CSSStyleSheet $sheet,
		int $depth,
		CSSParserOptions $options,
		int &$ruleCount,
		int &$declarationCount,
		bool $functionMode = false,
	): CSSRule {
		if ($options->maxDepth !== null && $depth > $options->maxDepth) {
			throw new CSSParserLimitException(sprintf('Maximum CSS nesting depth of %d exceeded.', $options->maxDepth));
		}

		$delimiter = CSSSyntaxScanner::findNextTopLevelDelimiterBytes($source, '{;}', $offset, $end);
		if ($delimiter === null) {
			throw new InvalidArgumentException('Unterminated nested CSS rule.');
		}
		$ruleStart = $offset;
		$delimOffset = $delimiter['offset'];
		$prelude = self::extractPrelude($source, $ruleStart, $delimOffset, $sheet);
		if ($prelude === null) {
			throw new InvalidArgumentException($sheet->getParseErrors()[0]['message'] ?? 'CSS rule prelude contains disallowed control characters.');
		}

		if ($delimiter['delimiter'] === ';') {
			if (!str_starts_with($prelude, '@')) {
				throw new InvalidArgumentException('A qualified rule requires a block.');
			}
			[$name] = self::splitAtRulePrelude($prelude);
			if (isset(self::STATEMENT_AT_RULES[$name])) {
				throw new InvalidArgumentException(sprintf('Statement @%s rule is not allowed inside a nested rule.', $name));
			}
			$rule = self::parseSingleRule(substr($source, $ruleStart, $delimOffset - $ruleStart + 1), $options, $depth, $ruleCount);
			$offset = $delimOffset + 1;
			return $rule;
		}
		if ($delimiter['delimiter'] !== '{') {
			throw new InvalidArgumentException('Invalid nested CSS rule.');
		}
		if ($prelude === '') {
			throw new InvalidArgumentException('CSS rule prelude cannot be empty.');
		}
		if ($options->maxRules !== null && $ruleCount >= $options->maxRules) {
			throw new CSSParserLimitException(sprintf('Maximum CSS rule limit of %d exceeded.', $options->maxRules));
		}

		if (str_starts_with($prelude, '@')) {
			$declLimitRecorded = false;
			$prepared = self::prepareBlockAtRule(
				$prelude,
				$source,
				$ruleStart,
				$delimOffset,
				$offset,
				$end,
				$sheet,
				true,
				$depth + 1,
				$options,
				$ruleCount,
				$declarationCount,
				true,
				$declLimitRecorded,
				$functionMode,
			);
			$rule = $prepared === null ? null : self::finishPreparedAtRuleBody(
				$prepared,
				$source,
				$delimOffset,
				$offset,
				$end,
				$sheet,
				$depth + 1,
				$options,
				$ruleCount,
				$declarationCount,
				true,
				$declLimitRecorded,
			);
			if ($rule === null) {
				$message = $sheet->getParseErrors()[0]['message'] ?? 'Malformed nested CSS rule.';
				throw new InvalidArgumentException($message);
			}
			return $rule;
		}

		if ($options->maxDepth !== null && ($depth + 1) > $options->maxDepth) {
			throw new CSSParserLimitException(sprintf('Maximum CSS nesting depth of %d exceeded.', $options->maxDepth));
		}

		$rule = new CSSStyleRule($prelude, new CSSStyleProperties(), $options->maxBytes);
		$ruleCount++;
		$offset = $delimOffset + 1;

		$closed = self::parseStyleBlock(
			$source,
			$offset,
			$end,
			$rule->getStyle(),
			$rule,
			$sheet,
			$depth + 1,
			$options,
			$ruleCount,
			$declarationCount,
			true,
			true,
			false,
			false,
			$delimOffset,
		);
		if (!$closed) {
			throw new InvalidArgumentException('Malformed nested CSS rule block.');
		}

		return $rule;
	}

	/** @return array{string, string} */
	private static function splitAtRulePrelude(string $prelude): array
	{
		$prelude = CSSSyntaxScanner::trimCssWhitespace($prelude);
		$length = strlen($prelude);
		if ($length < 2 || $prelude[0] !== '@') {
			throw new InvalidArgumentException('Malformed at-rule prelude: ' . $prelude);
		}

		$pos = CSSSyntaxScanner::scanIdentEnd($prelude, 1, $length);
		if ($pos === null) {
			throw new InvalidArgumentException('Malformed at-rule prelude: ' . $prelude);
		}

		$rawName = substr($prelude, 1, $pos - 1);
		if (str_contains($rawName, '\\')) {
			$rawName = CSSSyntaxScanner::decodeCssIdentEscapes($rawName);
			if (CSSSyntaxScanner::hasControlCharacters($rawName)) {
				throw new InvalidArgumentException('Malformed at-rule prelude: ' . $prelude);
			}
		}

		$name = strtolower($rawName);
		if ($pos >= $length) {
			return [$name, ''];
		}

		return [$name, CSSSyntaxScanner::trimCssWhitespace(substr($prelude, $pos))];
	}

	private static function extractPrelude(string $source, int $start, int $end, ?CSSStyleSheet $sheet = null): ?string
	{
		$len = $end - $start;
		if ($len > 0 && CSSSyntaxScanner::hasControlCharactersInSlice($source, $start, $len)) {
			$sheet?->recordParseError('CSS rule prelude contains disallowed control characters.', $start);
			return null;
		}
		$raw = substr($source, $start, $len);
		if (str_contains($raw, '/*')) {
			return CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($raw));
		}

		return CSSSyntaxScanner::trimCssWhitespace($raw);
	}

	private static function createValidatedOpaqueRule(
		string $raw,
		CSSStyleSheet $sheet,
		int $offset,
		?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES,
	): ?CSSOpaqueRule {
		try {
			return new CSSOpaqueRule($raw, $maxBytes);
		} catch (InvalidArgumentException $exception) {
			$sheet->recordParseError($exception->getMessage(), $offset);
			return null;
		}
	}

	private static function skipMalformedBlock(
		string $source,
		int $braceOffset,
		int &$offset,
		int $end,
		CSSStyleSheet $sheet,
	): void {
		$close = CSSSyntaxScanner::findMatchingBlockEnd($source, $braceOffset, $end);
		if ($close === null || $close >= $end) {
			$sheet->recordParseError('Unclosed CSS rule block.', $braceOffset);
			$offset = $end;
			return;
		}
		$offset = $close + 1;
	}

	/** @param list<array{message: string, offset: int}> $diagnostics */
	private static function flushDiagnostics(CSSStyleSheet $sheet, array &$diagnostics): void
	{
		foreach ($diagnostics as $error) {
			$sheet->recordParseError($error['message'], $error['offset']);
		}
		$diagnostics = [];
	}

	private static function skipTrivia(
		string $source,
		int $offset,
		int $end,
		?CSSStyleSheet $sheet = null,
		bool $allowCdoCdc = false,
	): int {
		while ($offset < $end) {
			$offset += strspn($source, " \t\n\r\f", $offset, $end - $offset);
			if ($offset >= $end) {
				break;
			}
			$ch = $source[$offset];
			if ($ch === '/' && $offset + 1 < $end && $source[$offset + 1] === '*') {
				$commentEnd = strpos($source, '*/', $offset + 2);
				if ($commentEnd === false || $commentEnd + 1 >= $end) {
					if (CSSSyntaxScanner::hasControlCharactersInSlice($source, $offset, $end - $offset)) {
						$sheet?->recordParseError('CSS comment contains disallowed control characters.', $offset);
					}
					$sheet?->recordParseError('Unclosed CSS comment.', $offset);
					return $end;
				}
				if (CSSSyntaxScanner::hasControlCharactersInSlice($source, $offset + 2, $commentEnd - ($offset + 2))) {
					$sheet?->recordParseError('CSS comment contains disallowed control characters.', $offset);
				}
				$offset = $commentEnd + 2;
				continue;
			}
			if ($allowCdoCdc) {
				if ($ch === '-' && $offset + 3 <= $end && substr_compare($source, '-->', $offset, 3) === 0) {
					$offset += 3;
					continue;
				}
				if ($ch === '<' && $offset + 4 <= $end && substr_compare($source, '<!--', $offset, 4) === 0) {
					$offset += 4;
					continue;
				}
			}
			break;
		}

		return $offset;
	}

	private static function assertNonNegativeInitialCounters(
		int $initialDepth,
		int $initialRuleCount,
		int $initialDeclarationCount,
	): void {
		if ($initialDepth < 0 || $initialDepth === PHP_INT_MAX) {
			throw new InvalidArgumentException('Nesting depth cannot be negative or overflow integer bounds.');
		}
		if ($initialRuleCount < 0 || $initialRuleCount === PHP_INT_MAX) {
			throw new InvalidArgumentException('Initial rule count cannot be negative or overflow integer bounds.');
		}
		if ($initialDeclarationCount < 0 || $initialDeclarationCount === PHP_INT_MAX) {
			throw new InvalidArgumentException('Initial declaration count cannot be negative or overflow integer bounds.');
		}
	}

	private static function canRecordRule(
		CSSParserOptions $options,
		int $ruleCount,
		CSSStyleSheet $sheet,
		int $offset,
		bool $throwOnLimit = false,
	): bool {
		if ($options->maxRules !== null && $ruleCount >= $options->maxRules) {
			$message = sprintf('Maximum CSS rule limit of %d exceeded.', $options->maxRules);
			if ($throwOnLimit) {
				throw new CSSParserLimitException($message);
			}
			$sheet->recordParseError($message, $offset);
			return false;
		}

		return true;
	}
}
