<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSInputPreprocessor;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSourceLocationIndex;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Jimbo2150\PhpCssTypedOm\Parser\CSSTokenizer;
use Jimbo2150\PhpCssTypedOm\Parser\CSSTokenType;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;

/**
 * Iterative, token-driven CSS Syntax Level 3 parser for standalone stylesheets, rules, and declaration blocks.
 *
 * `parseStylesheet()` recovers from malformed sections and reports byte-offset diagnostics,
 * whereas `parseSingleRule()`, `parseSingleNestedRule()`, `parseSingleKeyframeRule()`,
 * `parseSingleMarginRule()`, and `parseDeclarationBlock()` validate strictly and throw
 * `InvalidArgumentException` (or `CSSParserLimitException`) on malformed or over-limit input.
 */
final class CSSParser
{
	private const int FRAME_RULE_LIST = 1;
	private const int FRAME_STYLE_BLOCK = 2;
	private const int FRAME_FFV_BODY = 3;

	private const string FAST_DECL_UNSAFE_BYTES = "\\\"'[]{}<;\r\f\x00\x01\x02\x03\x04\x05\x06\x07\x08\x0B\x0E\x0F\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F\x7F";
	private const string CONTROL_BYTES = "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x0B\x0E\x0F\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F\x7F";

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
		'color-profile' => true,
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
		'custom-media' => true,
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

		$browserMode = $options->browserErrorRecovery;
		if ($browserMode) {
			try {
				$source = CSSInputPreprocessor::preprocess($cssText, $options);
			} catch (CSSParserLimitException $exception) {
				if ($throwOnLimit) {
					throw $exception;
				}
				$sheet->recordParseError($exception->getMessage(), 0);
				return $sheet;
			}
			$startOffset = 0;
			$checkControlBytes = false;
		} else {
			$length = strlen($cssText);
			if ($options->maxBytes !== null && $length > $options->maxBytes) {
				$message = sprintf('CSS input exceeds maximum size of %d bytes.', $options->maxBytes);
				if ($throwOnLimit) {
					throw new CSSParserLimitException($message);
				}
				$sheet->recordParseError($message, 0);
				return $sheet;
			}
			$source = $cssText;
			$startOffset = str_starts_with($source, "\xEF\xBB\xBF") ? 3 : 0;
			$checkControlBytes = true;
		}

		$ruleCount = 0;
		$declarationCount = 0;
		$declLimitRecorded = false;
		$spanIndex = $options->trackSpans ? new CSSSourceLocationIndex($source, $startOffset) : null;

		$rootFrame = [
			'kind' => self::FRAME_RULE_LIST,
			'rules' => $sheet->getCssRules(),
			'keyframes' => false,
			'allowRelativeSelectors' => false,
			'depth' => 0,
			'stopOnClosingBrace' => false,
			'openBraceOffset' => null,
			'allowEofClose' => true,
			'allowCdoCdc' => true,
			'limitReached' => false,
			'pendingRule' => null,
			'parentRules' => null,
			'parentStopOnBrace' => false,
			'enforceRuleOrder' => true,
			'seenAnyRule' => false,
			'seenNonImportPrelude' => false,
			'seenRegularRule' => false,
		];

		self::runIterativeEngine(
			$source,
			$startOffset,
			null,
			$rootFrame,
			$sheet,
			$options,
			$ruleCount,
			$declarationCount,
			$throwOnLimit,
			$declLimitRecorded,
			$browserMode,
			$checkControlBytes,
			$spanIndex,
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
		$startOffset = str_starts_with($cssText, "\xEF\xBB\xBF") ? 3 : 0;
		$ruleCount = $initialRuleCount;
		$declarationCount = $initialDeclarationCount;
		$declLimitRecorded = false;
		$spanIndex = $options->trackSpans ? new CSSSourceLocationIndex($cssText, $startOffset) : null;

		$rootFrame = [
			'kind' => self::FRAME_RULE_LIST,
			'rules' => $sheet->getCssRules(),
			'keyframes' => false,
			'depth' => $initialDepth,
			'stopOnClosingBrace' => false,
			'openBraceOffset' => null,
			'allowEofClose' => false,
			'allowCdoCdc' => $initialDepth === 0,
			'limitReached' => false,
			'pendingRule' => null,
			'parentRules' => null,
			'parentStopOnBrace' => false,
		];

		self::runIterativeEngine(
			$cssText,
			$startOffset,
			null,
			$rootFrame,
			$sheet,
			$options,
			$ruleCount,
			$declarationCount,
			true,
			$declLimitRecorded,
			false,
			true,
			$spanIndex,
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
		if ($options->maxDepth !== null && $initialDepth > $options->maxDepth) {
			throw new CSSParserLimitException(sprintf('Maximum CSS nesting depth of %d exceeded.', $options->maxDepth));
		}

		$sheet = new CSSStyleSheet();
		$sheet->configureLimitsInternal($options);
		$ruleCount = $initialRuleCount;
		$declarationCount = $initialDeclarationCount;
		$startOffset = str_starts_with($cssText, "\xEF\xBB\xBF") ? 3 : 0;
		$tokenizer = CSSTokenizer::forParserInternal($cssText, $options, false, true, $startOffset);

		$firstType = self::advanceNonTrivia($tokenizer, $sheet, true, false);
		self::assertNoSheetParseErrors($sheet);
		if ($firstType === CSSTokenType::Eof) {
			throw new InvalidArgumentException('Exactly one valid nested CSS rule is required.');
		}

		$collector = new CSSRuleList(null);
		$ruleStart = $tokenizer->tokenStart;
		$declLimitRecorded = false;
		$spanIndex = $options->trackSpans ? new CSSSourceLocationIndex($cssText, $startOffset) : null;

		$stmt = self::consumeStatementOrPrelude($cssText, $tokenizer, $firstType, false, true, false);
		if ($stmt['controlOffset'] !== null) {
			throw new InvalidArgumentException('CSS rule prelude contains disallowed control characters.');
		}
		if ($stmt['unclosedCommentOffset'] !== null) {
			throw new InvalidArgumentException('Unclosed CSS comment.');
		}
		if ($stmt['badTokenMessage'] !== null) {
			throw new InvalidArgumentException($stmt['badTokenMessage']);
		}
		if ($stmt['delimiter'] === CSSTokenType::Eof) {
			throw new InvalidArgumentException('Unterminated nested CSS rule.');
		}

		$delimOffset = $stmt['delimOffset'];
		$prelude = $firstType === CSSTokenType::AtKeyword
			? self::buildCleanPrelude($cssText, $ruleStart, $delimOffset, $stmt['hasComments'], $sheet)
			: self::buildRawSelectorPrelude($cssText, $ruleStart, $delimOffset, $stmt['hasComments'], false, $sheet);
		if ($prelude === null) {
			self::assertNoSheetParseErrors($sheet);
			throw new InvalidArgumentException('CSS rule prelude contains disallowed control characters.');
		}

		if ($stmt['delimiter'] === CSSTokenType::Semicolon) {
			if ($firstType !== CSSTokenType::AtKeyword) {
				throw new InvalidArgumentException('A qualified rule requires a block.');
			}
			[$name] = self::splitAtRulePrelude($prelude);
			if (isset(self::STATEMENT_AT_RULES[$name])) {
				throw new InvalidArgumentException(sprintf('Statement @%s rule is not allowed inside a nested rule.', $name));
			}
			$rule = self::parseSingleRule(substr($cssText, $ruleStart, $delimOffset - $ruleStart + 1), $options, $initialDepth, $ruleCount);
			if ($spanIndex !== null) {
				$rule->setSourceSpanInternal($spanIndex->span($ruleStart, $delimOffset + 1));
			}
		} elseif ($stmt['delimiter'] === CSSTokenType::LeftCurlyBracket) {
			if ($prelude === '') {
				throw new InvalidArgumentException('CSS rule prelude cannot be empty.');
			}
			if ($options->maxRules !== null && $ruleCount >= $options->maxRules) {
				throw new CSSParserLimitException(sprintf('Maximum CSS rule limit of %d exceeded.', $options->maxRules));
			}

			if ($firstType === CSSTokenType::AtKeyword) {
				$childDepth = self::nextDepth($initialDepth);
				$atKeywordEnd = $stmt['atKeywordEnd'];
				$cursorOffset = $tokenizer->getOffsetInternal();
				$preparedTokenizer = $tokenizer;
				$frame = self::prepareBlockAtRuleFrame(
					$prelude,
					$cssText,
					$ruleStart,
					$atKeywordEnd,
					$delimOffset,
					$cursorOffset,
					$preparedTokenizer,
					$sheet,
					true,
					$childDepth,
					$options,
					$ruleCount,
					true,
					$functionMode,
					false,
					$collector,
					true,
					$spanIndex,
				);
				if ($frame === null || $frame === false) {
					self::assertNoSheetParseErrors($sheet);
					throw new InvalidArgumentException('Malformed nested CSS rule.');
				}
				if (is_array($frame)) {
					self::runIterativeEngine(
						$cssText,
						$cursorOffset,
						$preparedTokenizer,
						$frame,
						$sheet,
						$options,
						$ruleCount,
						$declarationCount,
						true,
						$declLimitRecorded,
						false,
						true,
						$spanIndex,
					);
				}
			} else {
				$childDepth = self::nextDepth($initialDepth);
				if ($options->maxDepth !== null && $childDepth > $options->maxDepth) {
					throw new CSSParserLimitException(sprintf('Maximum CSS nesting depth of %d exceeded.', $options->maxDepth));
				}
				$nonDefaultDeclLimits = self::hasNonDefaultDeclLimits($options);
				$nestedStyleRule = CSSStyleRule::createParsedInternal(
					$prelude,
					$nonDefaultDeclLimits ? new CSSStyleProperties('', $options) : null,
					$options,
					true,
					false,
				);
				self::incrementCounter($ruleCount, 'CSS rule count');
				$frame = [
					'kind' => self::FRAME_STYLE_BLOCK,
					'currentStyle' => $nestedStyleRule->getStyle(),
					'ownerRule' => $nestedStyleRule,
					'depth' => $childDepth,
					'openBraceOffset' => $delimOffset,
					'ruleStartOffset' => $ruleStart,
					'allowNestedRules' => true,
					'pageMode' => false,
					'functionMode' => false,
					'validatePropertyGrammar' => true,
					'hasNestedRule' => false,
					'isGroupingWithDecls' => false,
					'allowEmptyInitialValue' => false,
					'sawEmptyInitialValue' => false,
					'allowEofClose' => false,
					'diagnostics' => [],
					'pendingRule' => $nestedStyleRule,
					'declAtRuleName' => null,
					'declAtRuleCondition' => null,
					'parentRules' => $collector,
					'parentStopOnBrace' => true,
				];
				self::runIterativeEngine(
					$cssText,
					$tokenizer->getOffsetInternal(),
					$tokenizer,
					$frame,
					$sheet,
					$options,
					$ruleCount,
					$declarationCount,
					true,
					$declLimitRecorded,
					false,
					true,
					$spanIndex,
				);
			}

			self::assertNoSheetParseErrors($sheet);
			if (count($collector) !== 1) {
				throw new InvalidArgumentException('Malformed nested CSS rule block.');
			}
			$rule = $collector->getRulesRaw()[0];
		} else {
			throw new InvalidArgumentException('Invalid nested CSS rule.');
		}

		$trailing = self::advanceNonTrivia($tokenizer, $sheet, true, false);
		self::assertNoSheetParseErrors($sheet);
		if ($trailing !== CSSTokenType::Eof) {
			throw new InvalidArgumentException('Unexpected trailing content in nested CSS rule.');
		}

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
		$startOffset = str_starts_with($cssText, "\xEF\xBB\xBF") ? 3 : 0;
		$ruleCount = $initialRuleCount;
		$declarationCount = $initialDeclarationCount;
		$declLimitRecorded = false;
		$spanIndex = $options->trackSpans ? new CSSSourceLocationIndex($cssText, $startOffset) : null;

		$rootFrame = [
			'kind' => self::FRAME_RULE_LIST,
			'rules' => $sheet->getCssRules(),
			'keyframes' => true,
			'allowRelativeSelectors' => false,
			'depth' => $initialDepth,
			'stopOnClosingBrace' => false,
			'openBraceOffset' => null,
			'allowEofClose' => false,
			'allowCdoCdc' => false,
			'limitReached' => false,
			'pendingRule' => null,
			'parentRules' => null,
			'parentStopOnBrace' => false,
		];

		self::runIterativeEngine(
			$cssText,
			$startOffset,
			null,
			$rootFrame,
			$sheet,
			$options,
			$ruleCount,
			$declarationCount,
			true,
			$declLimitRecorded,
			false,
			true,
			$spanIndex,
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
		$startOffset = str_starts_with($cssText, "\xEF\xBB\xBF") ? 3 : 0;
		$tokenizer = CSSTokenizer::forParserInternal($cssText, $options, false, true, $startOffset);

		$firstType = self::advanceNonTrivia($tokenizer, $sheet, true, false);
		self::assertNoSheetParseErrors($sheet);
		if ($firstType !== CSSTokenType::AtKeyword) {
			throw new InvalidArgumentException('Malformed page margin rule.');
		}
		$ruleStart = $tokenizer->tokenStart;
		$stmt = self::consumeStatementOrPrelude($cssText, $tokenizer, $firstType, false, true, false);
		if ($stmt['controlOffset'] !== null) {
			throw new InvalidArgumentException('CSS margin rule prelude contains disallowed control characters.');
		}
		if ($stmt['unclosedCommentOffset'] !== null) {
			throw new InvalidArgumentException('Unclosed CSS comment.');
		}
		if ($stmt['badTokenMessage'] !== null) {
			throw new InvalidArgumentException($stmt['badTokenMessage']);
		}
		if ($stmt['delimiter'] !== CSSTokenType::LeftCurlyBracket) {
			throw new InvalidArgumentException('Malformed page margin rule.');
		}

		$braceOffset = $stmt['delimOffset'];
		$prelude = self::buildCleanPrelude($cssText, $ruleStart, $braceOffset, $stmt['hasComments'], $sheet);
		if ($prelude === null) {
			self::assertNoSheetParseErrors($sheet);
			throw new InvalidArgumentException('CSS margin rule prelude contains disallowed control characters.');
		}
		[$name, $extra] = self::splitAtRulePrelude($prelude);
		if ($extra !== '') {
			throw new InvalidArgumentException('Unexpected prelude in page margin rule.');
		}

		$collector = new CSSRuleList(null);
		$style = new CSSStyleDeclaration('', $options);
		$ruleCount = $initialRuleCount;
		$declarationCount = $initialDeclarationCount;
		$declLimitRecorded = false;
		$spanIndex = $options->trackSpans ? new CSSSourceLocationIndex($cssText, $startOffset) : null;

		$frame = [
			'kind' => self::FRAME_STYLE_BLOCK,
			'currentStyle' => $style,
			'ownerRule' => null,
			'depth' => self::nextDepth($initialDepth),
			'openBraceOffset' => $braceOffset,
			'ruleStartOffset' => $ruleStart,
			'allowNestedRules' => false,
			'pageMode' => false,
			'functionMode' => false,
			'validatePropertyGrammar' => true,
			'hasNestedRule' => false,
			'isGroupingWithDecls' => false,
			'allowEmptyInitialValue' => false,
			'sawEmptyInitialValue' => false,
			'allowEofClose' => false,
			'diagnostics' => [],
			'pendingRule' => null,
			'declAtRuleName' => '@margin',
			'declAtRuleCondition' => $name,
			'parentRules' => $collector,
			'parentStopOnBrace' => true,
		];

		self::runIterativeEngine(
			$cssText,
			$tokenizer->getOffsetInternal(),
			$tokenizer,
			$frame,
			$sheet,
			$options,
			$ruleCount,
			$declarationCount,
			true,
			$declLimitRecorded,
			false,
			true,
			$spanIndex,
		);

		self::assertNoSheetParseErrors($sheet);
		if (count($collector) !== 1) {
			throw new InvalidArgumentException('Unclosed page margin rule block.');
		}
		$trailing = self::advanceNonTrivia($tokenizer, $sheet, true, false);
		self::assertNoSheetParseErrors($sheet);
		if ($trailing !== CSSTokenType::Eof) {
			throw new InvalidArgumentException('Unexpected trailing content in page margin rule.');
		}

		$marginRule = $collector->getRulesRaw()[0];
		assert($marginRule instanceof CSSMarginRule);
		$marginRule->attach(null, null);

		return $marginRule;
	}

	/**
	 * Iterative CSS Syntax L3 §5 state machine over `CSSTokenizer` frames.
	 *
	 * @param array<string, mixed> $initialFrame
	 */
	private static function runIterativeEngine(
		string $source,
		int $cursorOffset,
		?CSSTokenizer $tokenizer,
		array $initialFrame,
		CSSStyleSheet $sheet,
		CSSParserOptions $options,
		int &$ruleCount,
		int &$declarationCount,
		bool $throwOnLimit,
		bool &$declLimitRecorded,
		bool $browserMode,
		bool $checkControlBytes,
		?CSSSourceLocationIndex $spanIndex = null,
	): void {
		$length = strlen($source);
		/** @var list<array<string, mixed>> $stack */
		$stack = [$initialFrame];

		while ($stack !== []) {
			$topIdx = count($stack) - 1;
			$kind = $stack[$topIdx]['kind'];

			if ($kind === self::FRAME_RULE_LIST) {
				/** @var CSSRuleList $rules */
				$rules = $stack[$topIdx]['rules'];
				$keyframes = (bool) $stack[$topIdx]['keyframes'];
				$allowRelativeSelectors = (bool) ($stack[$topIdx]['allowRelativeSelectors'] ?? false);
				$depth = (int) $stack[$topIdx]['depth'];
				$stopOnBrace = (bool) $stack[$topIdx]['stopOnClosingBrace'];
				/** @var ?int $openBraceOffset */
				$openBraceOffset = $stack[$topIdx]['openBraceOffset'];
				$allowEofClose = (bool) $stack[$topIdx]['allowEofClose'];
				$allowCdoCdc = (bool) $stack[$topIdx]['allowCdoCdc'];
				$limitReached = (bool) $stack[$topIdx]['limitReached'];

				if ($options->maxDepth !== null && $depth > $options->maxDepth) {
					$message = sprintf('Maximum CSS nesting depth of %d exceeded.', $options->maxDepth);
					if ($throwOnLimit) {
						throw new CSSParserLimitException($message);
					}
					$sheet->recordParseError($message, $openBraceOffset ?? $cursorOffset);
					if ($stopOnBrace) {
						$activeTokenizer = self::ensureTokenizer($source, $options, $browserMode, $checkControlBytes, $cursorOffset, $tokenizer);
						self::skipRemainingBlock($activeTokenizer, $sheet, $checkControlBytes);
						$cursorOffset = $activeTokenizer->getOffsetInternal();
					} else {
						$cursorOffset = $length;
						$tokenizer?->setOffsetInternal($length);
					}
					array_pop($stack);
					continue;
				}

				// C-level clean ASCII qualified-rule and @-rule fast paths (guarded on maxRules to avoid duplicate error recording).
				if (!$limitReached && ($options->maxRules === null || $ruleCount < $options->maxRules)) {
					$fastPos = $cursorOffset;
					while ($fastPos < $length) {
						$fastPos += strspn($source, " \t\n\r\f", $fastPos, $length - $fastPos);
						if ($fastPos + 1 < $length && $source[$fastPos] === '/' && $source[$fastPos + 1] === '*') {
							$cEnd = strpos($source, '*/', $fastPos + 2);
							if ($cEnd === false || $cEnd + 1 >= $length) {
								break;
							}
							if ($checkControlBytes && CSSSyntaxScanner::hasControlCharactersInSlice($source, $fastPos + 2, $cEnd - ($fastPos + 2))) {
								break;
							}
							$fastPos = $cEnd + 2;
							continue;
						}
						break;
					}
					if ($fastPos >= $length) {
						$cursorOffset = $length;
						$tokenizer?->setOffsetInternal($length);
						if ($stopOnBrace) {
							$sheet->recordParseError('Unclosed CSS rule block.', $openBraceOffset ?? $length);
							if ($allowEofClose) {
								self::commitClosedRuleFrame($stack, $topIdx, $cursorOffset, $spanIndex);
							}
						}
						array_pop($stack);
						continue;
					}
					if ($source[$fastPos] === '}' && $stopOnBrace) {
						$cursorOffset = $fastPos + 1;
						$tokenizer?->setOffsetInternal($cursorOffset);
						self::commitClosedRuleFrame($stack, $topIdx, $cursorOffset, $spanIndex);
						array_pop($stack);
						continue;
					}
					if (!$keyframes && $source[$fastPos] === '@') {
						$fastAt = self::scanCleanAsciiAtRuleBlockPrelude($source, $fastPos, $length, $checkControlBytes);
						if ($fastAt !== null) {
							$braceOffset = $fastAt['braceOffset'];
							$fastAtName = strtolower(substr($source, $fastPos + 1, $fastAt['atKeywordEnd'] - ($fastPos + 1)));
							$fastAtIsKnown = $fastAtName === 'page'
								|| isset(self::GROUPING_AT_RULES[$fastAtName])
								|| isset(self::DECLARATION_AT_RULES[$fastAtName]);
							$prelude = $fastAtIsKnown
								? self::buildCleanPrelude($source, $fastPos, $braceOffset, $fastAt['hasComments'], $sheet)
								: '@' . $fastAtName;
							if ($prelude !== null) {
								$cursorOffset = $braceOffset + 1;
								$tokenizer?->setOffsetInternal($cursorOffset);
								$childDepth = self::nextDepth($depth);
								$prepared = self::prepareBlockAtRuleFrame(
									$prelude,
									$source,
									$fastPos,
									$fastAt['atKeywordEnd'],
									$braceOffset,
									$cursorOffset,
									$tokenizer,
									$sheet,
									false,
									$childDepth,
									$options,
									$ruleCount,
									$throwOnLimit,
									false,
									$browserMode,
									$rules,
									$stopOnBrace,
									$spanIndex,
								);
								if ($prepared === false) {
									if ($stopOnBrace && $allowEofClose) {
										self::commitClosedRuleFrame($stack, $topIdx, $cursorOffset, $spanIndex);
									}
									array_pop($stack);
								} elseif (is_array($prepared)) {
									$prepared['allowEofClose'] = $allowEofClose;
									if ($prepared['kind'] === self::FRAME_RULE_LIST) {
										$prepared['allowRelativeSelectors'] = ($prepared['allowRelativeSelectors'] ?? false) || $allowRelativeSelectors;
									}
									$stack[] = $prepared;
								} elseif ($prepared === true) {
									self::markTopLevelRegularRuleCommitted($stack[$topIdx]);
								}
								continue;
							}
						}
					} elseif ($source[$fastPos] !== '@' && $source[$fastPos] !== '<' && $source[$fastPos] !== '}' && $source[$fastPos] !== '-' && !($source[$fastPos] === '/' && $fastPos + 1 < $length && $source[$fastPos + 1] === '*')) {
						$fastSpan = strcspn($source, self::FAST_DECL_UNSAFE_BYTES, $fastPos, $length - $fastPos);
						$fastStop = $fastPos + $fastSpan;
						if ($fastStop < $length
							&& $source[$fastStop] === '{'
							&& ($cleanSlice = CSSStyleDeclaration::extractCleanAsciiSliceInternal($source, $fastPos, $fastStop, $options->maxDepth)) !== null
						) {
							$prelude = trim($cleanSlice, " \t\n");
							if ($prelude !== '') {
								$childDepth = self::nextDepth($depth);
								if ($options->maxDepth !== null && $childDepth > $options->maxDepth) {
									$message = sprintf('Maximum CSS nesting depth of %d exceeded.', $options->maxDepth);
									if ($throwOnLimit) {
										throw new CSSParserLimitException($message);
									}
									$sheet->recordParseError($message, $fastStop + 1);
									$activeTokenizer = self::ensureTokenizer($source, $options, $browserMode, $checkControlBytes, $fastStop + 1, $tokenizer);
									$closed = self::skipRemainingBlock($activeTokenizer, $sheet, $checkControlBytes);
									$cursorOffset = $activeTokenizer->getOffsetInternal();
									if (!$closed) {
										if ($stopOnBrace && $allowEofClose) {
											self::commitClosedRuleFrame($stack, $topIdx, $cursorOffset, $spanIndex);
										}
										array_pop($stack);
									}
									continue;
								}
								$nonDefaultDeclLimits = self::hasNonDefaultDeclLimits($options);
								$fastRule = null;
								try {
									$initialStyle = $nonDefaultDeclLimits ? new CSSStyleProperties('', $options) : null;
									$fastRule = $keyframes
										? new CSSKeyframeRule($prelude, $initialStyle, $options->maxBytes)
										: CSSStyleRule::createParsedInternal($prelude, $initialStyle, $options, $allowRelativeSelectors, $browserMode);
								} catch (InvalidArgumentException $exception) {
									if ($throwOnLimit && $exception instanceof CSSParserLimitException) {
										throw $exception;
									}
									$sheet->recordParseError($exception->getMessage(), $fastPos);
									$activeTokenizer = self::ensureTokenizer($source, $options, $browserMode, $checkControlBytes, $fastStop + 1, $tokenizer);
									$closed = self::skipRemainingBlock($activeTokenizer, $sheet, $checkControlBytes);
									$cursorOffset = $activeTokenizer->getOffsetInternal();
									if (!$closed) {
										$sheet->recordParseError('Unclosed CSS rule block.', $fastStop);
										if ($stopOnBrace && $allowEofClose) {
											self::commitClosedRuleFrame($stack, $topIdx, $cursorOffset, $spanIndex);
										}
										array_pop($stack);
									}
									continue;
								}
								self::incrementCounter($ruleCount, 'CSS rule count');
								$cursorOffset = $fastStop + 1;
								$tokenizer?->setOffsetInternal($cursorOffset);
								$stack[] = [
									'kind' => self::FRAME_STYLE_BLOCK,
									'currentStyle' => $fastRule->getStyle(),
									'ownerRule' => $fastRule instanceof CSSGroupingRule ? $fastRule : null,
									'depth' => $childDepth,
									'openBraceOffset' => $fastStop,
									'ruleStartOffset' => $fastPos,
									'allowNestedRules' => !$keyframes,
									'pageMode' => false,
									'functionMode' => false,
									'validatePropertyGrammar' => true,
									'hasNestedRule' => false,
									'isGroupingWithDecls' => false,
									'allowEmptyInitialValue' => false,
									'sawEmptyInitialValue' => false,
									'allowEofClose' => $allowEofClose,
									'diagnostics' => [],
									'pendingRule' => $fastRule,
									'declAtRuleName' => null,
									'declAtRuleCondition' => null,
									'parentRules' => $rules,
									'parentStopOnBrace' => $stopOnBrace,
								];
								continue;
							}
						}
					}
				}

				$activeTokenizer = self::ensureTokenizer($source, $options, $browserMode, $checkControlBytes, $cursorOffset, $tokenizer);
				$firstType = self::advanceNonTrivia($activeTokenizer, $sheet, $checkControlBytes, $allowCdoCdc);
				$cursorOffset = $activeTokenizer->getOffsetInternal();

				if ($firstType === CSSTokenType::Eof) {
					if ($stopOnBrace) {
						$sheet->recordParseError('Unclosed CSS rule block.', $openBraceOffset ?? $activeTokenizer->tokenStart);
						if ($allowEofClose) {
							self::commitClosedRuleFrame($stack, $topIdx, $cursorOffset, $spanIndex);
						}
					}
					array_pop($stack);
					continue;
				}

				if ($firstType === CSSTokenType::RightCurlyBracket) {
					if ($stopOnBrace) {
						self::commitClosedRuleFrame($stack, $topIdx, $cursorOffset, $spanIndex);
						array_pop($stack);
					} else {
						$sheet->recordParseError('Unexpected closing brace.', $activeTokenizer->tokenStart);
					}
					continue;
				}

				$ruleStart = $activeTokenizer->tokenStart;
				$isAtRule = $firstType === CSSTokenType::AtKeyword;
				$stmt = self::consumeStatementOrPrelude($source, $activeTokenizer, $firstType, $browserMode, $checkControlBytes, $isAtRule);
				$cursorOffset = $activeTokenizer->getOffsetInternal();
				$delimType = $stmt['delimiter'];
				$delimOffset = $stmt['delimOffset'];

				if ($delimType === CSSTokenType::Eof) {
					if ($stmt['controlOffset'] !== null) {
						$sheet->recordParseError('CSS rule prelude contains disallowed control characters.', $ruleStart);
					}
					if ($stmt['unclosedCommentOffset'] !== null) {
						$sheet->recordParseError('Unclosed CSS comment.', $stmt['unclosedCommentOffset']);
					}
					if ($stopOnBrace) {
						$sheet->recordParseError('Unclosed CSS rule block.', $openBraceOffset ?? $ruleStart);
						if ($allowEofClose) {
							self::commitClosedRuleFrame($stack, $topIdx, $cursorOffset, $spanIndex);
						}
					} else {
						$sheet->recordParseError('Unterminated CSS rule.', $ruleStart);
					}
					array_pop($stack);
					continue;
				}

				if ($delimType === CSSTokenType::RightCurlyBracket) {
					if ($stmt['controlOffset'] !== null) {
						$sheet->recordParseError('CSS rule prelude contains disallowed control characters.', $ruleStart);
					}
					if ($stopOnBrace) {
						$sheet->recordParseError('Unexpected trailing tokens before closing brace.', $ruleStart);
						self::commitClosedRuleFrame($stack, $topIdx, $cursorOffset, $spanIndex);
						array_pop($stack);
					} else {
						$sheet->recordParseError('Unexpected closing brace.', $delimOffset);
					}
					continue;
				}

				if ($limitReached || !self::canRecordRule($options, $ruleCount, $sheet, $ruleStart, $throwOnLimit)) {
					$stack[$topIdx]['limitReached'] = true;
					if ($delimType === CSSTokenType::LeftCurlyBracket) {
						$closed = self::skipRemainingBlock($activeTokenizer, $sheet, $checkControlBytes);
						$cursorOffset = $activeTokenizer->getOffsetInternal();
						if (!$closed) {
							if ($stopOnBrace) {
								$sheet->recordParseError('Unclosed CSS rule block.', $openBraceOffset ?? $delimOffset);
								if ($allowEofClose) {
									self::commitClosedRuleFrame($stack, $topIdx, $cursorOffset, $spanIndex);
								}
							}
							array_pop($stack);
						}
					}
					continue;
				}

				if ($stmt['controlOffset'] !== null || $stmt['badTokenMessage'] !== null) {
					$msg = $stmt['controlOffset'] !== null
						? 'CSS rule prelude contains disallowed control characters.'
						: (string) $stmt['badTokenMessage'];
					$sheet->recordParseError($msg, $ruleStart);
					if ($delimType === CSSTokenType::LeftCurlyBracket) {
						$closed = self::skipRemainingBlock($activeTokenizer, $sheet, $checkControlBytes);
						$cursorOffset = $activeTokenizer->getOffsetInternal();
						if (!$closed) {
							$sheet->recordParseError('Unclosed CSS rule block.', $delimOffset);
							if ($stopOnBrace && $allowEofClose) {
								self::commitClosedRuleFrame($stack, $topIdx, $cursorOffset, $spanIndex);
							}
							array_pop($stack);
						}
					}
					continue;
				}

				$prelude = ($isAtRule || $keyframes)
					? self::buildCleanPrelude($source, $ruleStart, $delimOffset, $stmt['hasComments'], $sheet)
					: self::buildRawSelectorPrelude($source, $ruleStart, $delimOffset, $stmt['hasComments'], $browserMode, $sheet);
				if ($prelude === null) {
					if ($delimType === CSSTokenType::LeftCurlyBracket) {
						$closed = self::skipRemainingBlock($activeTokenizer, $sheet, $checkControlBytes);
						$cursorOffset = $activeTokenizer->getOffsetInternal();
						if (!$closed) {
							$sheet->recordParseError('Unclosed CSS rule block.', $delimOffset);
							if ($stopOnBrace && $allowEofClose) {
								self::commitClosedRuleFrame($stack, $topIdx, $cursorOffset, $spanIndex);
							}
							array_pop($stack);
						}
					}
					continue;
				}

				if ($delimType === CSSTokenType::Semicolon) {
					if ($keyframes) {
						$sheet->recordParseError(
							$isAtRule
								? 'Nested at-rules are not allowed inside @keyframes.'
								: 'Stray declaration or statement is not allowed directly inside @keyframes.',
							$ruleStart,
						);
						continue;
					}
					if (!$isAtRule) {
						$sheet->recordParseError('A qualified rule requires a block.', $ruleStart);
						continue;
					}
					try {
						[$name] = self::splitAtRulePrelude($prelude);
						if (isset(self::STATEMENT_AT_RULES[$name])) {
							if (($stopOnBrace || $depth > 0) && $name !== 'layer') {
								$sheet->recordParseError(
									sprintf('@%s rules are only allowed at the top level of a stylesheet.', $name),
									$ruleStart,
								);
								continue;
							}
							$stmtRule = self::parseAtRulePrelude($prelude, $options->maxBytes, $browserMode);
							$ruleEnd = $delimOffset + 1;
							if (!self::commitRuleToFrame($stack[$topIdx], $stmtRule, $stopOnBrace, $sheet, $ruleStart, $ruleEnd, $spanIndex)) {
								continue;
							}
							self::incrementCounter($ruleCount, 'CSS rule count');
						} else {
							$raw = substr($source, $ruleStart, $delimOffset - $ruleStart + 1);
							$opaque = self::createValidatedOpaqueRule($raw, $sheet, $ruleStart, $options->maxBytes, $throwOnLimit, $browserMode);
							if ($opaque !== null) {
								$ruleEnd = $delimOffset + 1;
								self::commitRuleToFrame($stack[$topIdx], $opaque, $stopOnBrace, $sheet, $ruleStart, $ruleEnd, $spanIndex);
								self::incrementCounter($ruleCount, 'CSS rule count');
							}
						}
					} catch (InvalidArgumentException $exception) {
						if ($throwOnLimit && $exception instanceof CSSParserLimitException) {
							throw $exception;
						}
						$sheet->recordParseError($exception->getMessage(), $ruleStart);
					}
					continue;
				}

				// $delimType === CSSTokenType::LeftCurlyBracket
				if ($prelude === '') {
					$sheet->recordParseError('CSS rule prelude cannot be empty.', $ruleStart);
					$closed = self::skipRemainingBlock($activeTokenizer, $sheet, $checkControlBytes);
					$cursorOffset = $activeTokenizer->getOffsetInternal();
					if (!$closed) {
						$sheet->recordParseError('Unclosed CSS rule block.', $delimOffset);
						if ($stopOnBrace && $allowEofClose) {
							self::commitClosedRuleFrame($stack, $topIdx, $cursorOffset, $spanIndex);
						}
						array_pop($stack);
					}
					continue;
				}

				if ($keyframes && $isAtRule) {
					$sheet->recordParseError('Nested at-rules are not allowed inside @keyframes.', $ruleStart);
					$closed = self::skipRemainingBlock($activeTokenizer, $sheet, $checkControlBytes);
					$cursorOffset = $activeTokenizer->getOffsetInternal();
					if (!$closed) {
						$sheet->recordParseError('Unclosed CSS rule block.', $delimOffset);
						if ($stopOnBrace && $allowEofClose) {
							self::commitClosedRuleFrame($stack, $topIdx, $cursorOffset, $spanIndex);
						}
						array_pop($stack);
					}
					continue;
				}

				if ($isAtRule) {
					$childDepth = self::nextDepth($depth);
					$prepared = self::prepareBlockAtRuleFrame(
						$prelude,
						$source,
						$ruleStart,
						$stmt['atKeywordEnd'],
						$delimOffset,
						$cursorOffset,
						$tokenizer,
						$sheet,
						false,
						$childDepth,
						$options,
						$ruleCount,
						$throwOnLimit,
						false,
						$browserMode,
						$rules,
						$stopOnBrace,
						$spanIndex,
					);
					if ($prepared === false) {
						// Unclosed block at EOF during skip
						if ($stopOnBrace && $allowEofClose) {
							self::commitClosedRuleFrame($stack, $topIdx, $cursorOffset, $spanIndex);
						}
						array_pop($stack);
					} elseif (is_array($prepared)) {
						$prepared['allowEofClose'] = $allowEofClose;
						if ($prepared['kind'] === self::FRAME_RULE_LIST) {
							$prepared['allowRelativeSelectors'] = ($prepared['allowRelativeSelectors'] ?? false) || $allowRelativeSelectors;
						}
						$stack[] = $prepared;
					} elseif ($prepared === true) {
						self::markTopLevelRegularRuleCommitted($stack[$topIdx]);
					}
					continue;
				}

				$childDepth = self::nextDepth($depth);
				if ($options->maxDepth !== null && $childDepth > $options->maxDepth) {
					$message = sprintf('Maximum CSS nesting depth of %d exceeded.', $options->maxDepth);
					if ($throwOnLimit) {
						throw new CSSParserLimitException($message);
					}
					$sheet->recordParseError($message, $delimOffset + 1);
					$closed = self::skipRemainingBlock($activeTokenizer, $sheet, $checkControlBytes);
					$cursorOffset = $activeTokenizer->getOffsetInternal();
					if (!$closed) {
						if ($stopOnBrace && $allowEofClose) {
							self::commitClosedRuleFrame($stack, $topIdx, $cursorOffset, $spanIndex);
						}
						array_pop($stack);
					}
					continue;
				}

				$nonDefaultDeclLimits = self::hasNonDefaultDeclLimits($options);
				try {
					$initialStyle = $nonDefaultDeclLimits ? new CSSStyleProperties('', $options) : null;
					$rule = $keyframes
						? new CSSKeyframeRule($prelude, $initialStyle, $options->maxBytes)
						: CSSStyleRule::createParsedInternal($prelude, $initialStyle, $options, $allowRelativeSelectors, $browserMode);
				} catch (InvalidArgumentException $exception) {
					if ($throwOnLimit && $exception instanceof CSSParserLimitException) {
						throw $exception;
					}
					$sheet->recordParseError($exception->getMessage(), $ruleStart);
					$closed = self::skipRemainingBlock($activeTokenizer, $sheet, $checkControlBytes);
					$cursorOffset = $activeTokenizer->getOffsetInternal();
					if (!$closed) {
						$sheet->recordParseError('Unclosed CSS rule block.', $delimOffset);
						if ($stopOnBrace && $allowEofClose) {
							self::commitClosedRuleFrame($stack, $topIdx, $cursorOffset, $spanIndex);
						}
						array_pop($stack);
					}
					continue;
				}

				self::incrementCounter($ruleCount, 'CSS rule count');
				$stack[] = [
					'kind' => self::FRAME_STYLE_BLOCK,
					'currentStyle' => $rule->getStyle(),
					'ownerRule' => $rule instanceof CSSGroupingRule ? $rule : null,
					'depth' => $childDepth,
					'openBraceOffset' => $delimOffset,
					'ruleStartOffset' => $ruleStart,
					'allowNestedRules' => !$keyframes,
					'pageMode' => false,
					'functionMode' => false,
					'validatePropertyGrammar' => true,
					'hasNestedRule' => false,
					'isGroupingWithDecls' => false,
					'allowEmptyInitialValue' => false,
					'sawEmptyInitialValue' => false,
					'allowEofClose' => $allowEofClose,
					'diagnostics' => [],
					'pendingRule' => $rule,
					'declAtRuleName' => null,
					'declAtRuleCondition' => null,
					'parentRules' => $rules,
					'parentStopOnBrace' => $stopOnBrace,
				];
				continue;
			}

			if ($kind === self::FRAME_STYLE_BLOCK) {
				$depth = (int) $stack[$topIdx]['depth'];
				/** @var int $openBraceOffset */
				$openBraceOffset = $stack[$topIdx]['openBraceOffset'];
				$allowEofClose = (bool) $stack[$topIdx]['allowEofClose'];

				if ($options->maxDepth !== null && $depth > $options->maxDepth) {
					$message = sprintf('Maximum CSS nesting depth of %d exceeded.', $options->maxDepth);
					if ($throwOnLimit) {
						throw new CSSParserLimitException($message);
					}
					$sheet->recordParseError($message, $openBraceOffset + 1);
					$activeTokenizer = self::ensureTokenizer($source, $options, $browserMode, $checkControlBytes, $cursorOffset, $tokenizer);
					self::skipRemainingBlock($activeTokenizer, $sheet, $checkControlBytes);
					$cursorOffset = $activeTokenizer->getOffsetInternal();
					array_pop($stack);
					continue;
				}

				$allowNestedRules = (bool) $stack[$topIdx]['allowNestedRules'];
				$pageMode = (bool) $stack[$topIdx]['pageMode'];
				$functionMode = (bool) $stack[$topIdx]['functionMode'];
				$validatePropertyGrammar = (bool) ($stack[$topIdx]['validatePropertyGrammar'] ?? true);
				$allowEmptyInitialValue = (bool) $stack[$topIdx]['allowEmptyInitialValue'];

				// C-level clean ASCII declaration fast path (only when terminating at ';' or '}', never '{').
				$pos = $cursorOffset;
				if ($pos < $length) {
					$span = strcspn($source, self::FAST_DECL_UNSAFE_BYTES, $pos, $length - $pos);
					$stop = $pos + $span;
					if ($stop < $length
						&& ($source[$stop] === ';' || $source[$stop] === '}')
						&& ($cleanSlice = CSSStyleDeclaration::extractCleanAsciiSliceInternal($source, $pos, $stop, $options->maxDepth)) !== null
					) {
						/** @var CSSStyleDeclaration $currentStyle */
						$currentStyle = $stack[$topIdx]['currentStyle'];
						/** @var list<array{message: string, offset: int}> $diags */
						$diags = $stack[$topIdx]['diagnostics'];
						$sawEmpty = (bool) $stack[$topIdx]['sawEmptyInitialValue'];

						if ($stop > $pos && !$declLimitRecorded) {
							$declLimitRecorded = !$currentStyle->parseCleanAsciiDeclarationInternal(
								$cleanSlice,
								$pos,
								false,
								$diags,
								$options,
								$declarationCount,
								$throwOnLimit,
								true,
								$allowEmptyInitialValue,
								$sawEmpty,
								$validatePropertyGrammar,
								$spanIndex,
							);
							$stack[$topIdx]['diagnostics'] = $diags;
							$stack[$topIdx]['sawEmptyInitialValue'] = $sawEmpty;
						}

						if ($source[$stop] === ';') {
							$cursorOffset = $stop + 1;
							$tokenizer?->setOffsetInternal($cursorOffset);
							continue;
						}

						// $source[$stop] === '}'
						self::flushDiagnostics($sheet, $diags);
						$stack[$topIdx]['diagnostics'] = [];
						$cursorOffset = $stop + 1;
						$tokenizer?->setOffsetInternal($cursorOffset);
						self::closeStyleBlockFrame($stack, $topIdx, $sheet, $options, $ruleCount, $throwOnLimit, $cursorOffset, $spanIndex);
						array_pop($stack);
						if ($stack !== [] && $stack[count($stack) - 1]['kind'] === self::FRAME_STYLE_BLOCK) {
							$parentIdx = count($stack) - 1;
							if (!(bool) $stack[$parentIdx]['pageMode']) {
								$stack[$parentIdx]['hasNestedRule'] = true;
								$pFunc = (bool) $stack[$parentIdx]['functionMode'];
								$stack[$parentIdx]['currentStyle'] = $pFunc ? new CSSFunctionDescriptors('', $options) : new CSSStyleProperties('', $options);
							}
						}
						continue;
					}
				}

				$activeTokenizer = self::ensureTokenizer($source, $options, $browserMode, $checkControlBytes, $cursorOffset, $tokenizer);
				$firstType = self::advanceNonTrivia($activeTokenizer, $sheet, $checkControlBytes, false);
				$cursorOffset = $activeTokenizer->getOffsetInternal();

				if ($firstType === CSSTokenType::Eof) {
					/** @var list<array{message: string, offset: int}> $diags */
					$diags = $stack[$topIdx]['diagnostics'];
					self::flushDiagnostics($sheet, $diags);
					$stack[$topIdx]['diagnostics'] = [];
					if ($allowEofClose) {
						self::closeStyleBlockFrame($stack, $topIdx, $sheet, $options, $ruleCount, $throwOnLimit, $cursorOffset, $spanIndex);
					}
					$sheet->recordParseError('Unclosed CSS rule block.', $openBraceOffset);
					array_pop($stack);
					continue;
				}

				if ($firstType === CSSTokenType::RightCurlyBracket) {
					/** @var list<array{message: string, offset: int}> $diags */
					$diags = $stack[$topIdx]['diagnostics'];
					self::flushDiagnostics($sheet, $diags);
					$stack[$topIdx]['diagnostics'] = [];
					self::closeStyleBlockFrame($stack, $topIdx, $sheet, $options, $ruleCount, $throwOnLimit, $cursorOffset, $spanIndex);
					array_pop($stack);
					if ($stack !== [] && $stack[count($stack) - 1]['kind'] === self::FRAME_STYLE_BLOCK) {
						$parentIdx = count($stack) - 1;
						if (!(bool) $stack[$parentIdx]['pageMode']) {
							$stack[$parentIdx]['hasNestedRule'] = true;
							$pFunc = (bool) $stack[$parentIdx]['functionMode'];
							$stack[$parentIdx]['currentStyle'] = $pFunc ? new CSSFunctionDescriptors('', $options) : new CSSStyleProperties('', $options);
						}
					}
					continue;
				}

				if ($firstType === CSSTokenType::Semicolon) {
					continue;
				}

				// Consume statement tokens for either a declaration or a nested rule.
				$stmtStart = $activeTokenizer->tokenStart;
				$collected = self::consumeStyleStatementTokens($source, $activeTokenizer, $firstType, $checkControlBytes, $browserMode, $options->maxDepth);
				$cursorOffset = $activeTokenizer->getOffsetInternal();
				$delimType = $collected['delimiter'];
				$delimOffset = $collected['delimOffset'];

				if ($delimType === CSSTokenType::LeftCurlyBracket) {
					/** @var list<array{message: string, offset: int}> $diags */
					$diags = $stack[$topIdx]['diagnostics'];
					self::flushDiagnostics($sheet, $diags);
					$stack[$topIdx]['diagnostics'] = [];

					/** @var ?CSSGroupingRule $ownerRule */
					$ownerRule = $stack[$topIdx]['ownerRule'];

					if (!$allowNestedRules) {
						$sheet->recordParseError(
							$ownerRule === null
								? 'Nested rules are not allowed inside a @keyframes or declaration block.'
								: 'Nested rules are not allowed inside a @keyframes block.',
							$stmtStart,
						);
						$closed = self::skipRemainingBlock($activeTokenizer, $sheet, $checkControlBytes);
						$cursorOffset = $activeTokenizer->getOffsetInternal();
						if (!$closed) {
							$sheet->recordParseError('Unclosed CSS rule block.', $delimOffset);
							if ($allowEofClose) {
								self::closeStyleBlockFrame($stack, $topIdx, $sheet, $options, $ruleCount, $throwOnLimit, $cursorOffset, $spanIndex);
							}
							array_pop($stack);
						}
						continue;
					}

					if ($pageMode && $ownerRule !== null) {
						$prelude = ($collected['controlOffset'] === null && $collected['badTokenMessage'] === null)
							? self::buildCleanPrelude($source, $stmtStart, $delimOffset, $collected['hasComments'], $sheet)
							: null;
						if ($collected['controlOffset'] !== null) {
							$sheet->recordParseError('CSS rule prelude contains disallowed control characters.', $stmtStart);
						} elseif ($collected['badTokenMessage'] !== null) {
							$sheet->recordParseError($collected['badTokenMessage'], $stmtStart);
						}
						if ($prelude === null || !self::canRecordRule($options, $ruleCount, $sheet, $stmtStart, $throwOnLimit)) {
							$closed = self::skipRemainingBlock($activeTokenizer, $sheet, $checkControlBytes);
							$cursorOffset = $activeTokenizer->getOffsetInternal();
							if (!$closed) {
								$sheet->recordParseError('Unclosed CSS rule block.', $delimOffset);
								if ($allowEofClose) {
									self::closeStyleBlockFrame($stack, $topIdx, $sheet, $options, $ruleCount, $throwOnLimit, $cursorOffset, $spanIndex);
								}
								array_pop($stack);
							}
							continue;
						}
						try {
							[$marginName, $extra] = self::splitAtRulePrelude($prelude);
							if ($extra !== '') {
								throw new InvalidArgumentException('Unexpected prelude in page margin rule.');
							}
						} catch (InvalidArgumentException $exception) {
							if ($throwOnLimit && $exception instanceof CSSParserLimitException) {
								throw $exception;
							}
							$sheet->recordParseError($exception->getMessage(), $stmtStart);
							$closed = self::skipRemainingBlock($activeTokenizer, $sheet, $checkControlBytes);
							$cursorOffset = $activeTokenizer->getOffsetInternal();
							if (!$closed) {
								$sheet->recordParseError('Unclosed CSS rule block.', $delimOffset);
								if ($allowEofClose) {
									self::closeStyleBlockFrame($stack, $topIdx, $sheet, $options, $ruleCount, $throwOnLimit, $cursorOffset, $spanIndex);
								}
								array_pop($stack);
							}
							continue;
						}
						$stack[] = [
							'kind' => self::FRAME_STYLE_BLOCK,
							'currentStyle' => new CSSStyleDeclaration('', $options),
							'ownerRule' => null,
							'depth' => self::nextDepth($depth),
							'openBraceOffset' => $delimOffset,
							'ruleStartOffset' => $stmtStart,
							'allowNestedRules' => false,
							'pageMode' => false,
							'functionMode' => false,
							'validatePropertyGrammar' => true,
							'hasNestedRule' => false,
							'isGroupingWithDecls' => false,
							'allowEmptyInitialValue' => false,
							'sawEmptyInitialValue' => false,
							'allowEofClose' => $allowEofClose,
							'diagnostics' => [],
							'pendingRule' => null,
							'declAtRuleName' => '@margin',
							'declAtRuleCondition' => $marginName,
							'parentRules' => $ownerRule->getCssRules(),
							'parentStopOnBrace' => true,
						];
						continue;
					}

					// Nested style rule, grouping at-rule, or @function body rule
					/** @var CSSStyleDeclaration $currentStyle */
					$currentStyle = $stack[$topIdx]['currentStyle'];
					self::flushTrailingNestedDeclarations(
						$currentStyle,
						$ownerRule,
						$sheet,
						$options,
						$ruleCount,
						$throwOnLimit,
						$pageMode,
						$functionMode,
						(bool) $stack[$topIdx]['hasNestedRule'],
						(bool) $stack[$topIdx]['isGroupingWithDecls'],
						$stmtStart,
						$spanIndex !== null,
					);

					$prelude = null;
					if ($collected['controlOffset'] !== null) {
						$sheet->recordParseError('CSS rule prelude contains disallowed control characters.', $stmtStart);
					} elseif ($collected['badTokenMessage'] !== null) {
						$sheet->recordParseError($collected['badTokenMessage'], $stmtStart);
					} else {
						$prelude = $firstType === CSSTokenType::AtKeyword
							? self::buildCleanPrelude($source, $stmtStart, $delimOffset, $collected['hasComments'], $sheet)
							: self::buildRawSelectorPrelude($source, $stmtStart, $delimOffset, $collected['hasComments'], $browserMode, $sheet);
					}

					if ($prelude === null || !self::canRecordRule($options, $ruleCount, $sheet, $stmtStart, $throwOnLimit)) {
						$closed = self::skipRemainingBlock($activeTokenizer, $sheet, $checkControlBytes);
						$cursorOffset = $activeTokenizer->getOffsetInternal();
						if (!$closed) {
							$sheet->recordParseError('Unclosed CSS rule block.', $delimOffset);
							if ($allowEofClose) {
								self::closeStyleBlockFrame($stack, $topIdx, $sheet, $options, $ruleCount, $throwOnLimit, $cursorOffset, $spanIndex);
							}
							array_pop($stack);
							continue;
						}
						$stack[$topIdx]['hasNestedRule'] = true;
						$stack[$topIdx]['currentStyle'] = $functionMode ? new CSSFunctionDescriptors('', $options) : new CSSStyleProperties('', $options);
						continue;
					}

					if ($prelude === '') {
						$sheet->recordParseError('CSS rule prelude cannot be empty.', $stmtStart);
						$closed = self::skipRemainingBlock($activeTokenizer, $sheet, $checkControlBytes);
						$cursorOffset = $activeTokenizer->getOffsetInternal();
						if (!$closed) {
							$sheet->recordParseError('Unclosed CSS rule block.', $delimOffset);
							if ($allowEofClose) {
								self::closeStyleBlockFrame($stack, $topIdx, $sheet, $options, $ruleCount, $throwOnLimit, $cursorOffset, $spanIndex);
							}
							array_pop($stack);
							continue;
						}
						$stack[$topIdx]['hasNestedRule'] = true;
						$stack[$topIdx]['currentStyle'] = $functionMode ? new CSSFunctionDescriptors('', $options) : new CSSStyleProperties('', $options);
						continue;
					}

					if ($firstType === CSSTokenType::AtKeyword) {
						$childDepth = self::nextDepth($depth);
						$prepared = self::prepareBlockAtRuleFrame(
							$prelude,
							$source,
							$stmtStart,
							$collected['atKeywordEnd'],
							$delimOffset,
							$cursorOffset,
							$tokenizer,
							$sheet,
							true,
							$childDepth,
							$options,
							$ruleCount,
							$throwOnLimit,
							$functionMode,
							$browserMode,
							$ownerRule?->getCssRules(),
							true,
							$spanIndex,
						);
						if ($prepared === false) {
							$sheet->recordParseError('Unclosed CSS rule block.', $delimOffset);
							if ($allowEofClose) {
								self::closeStyleBlockFrame($stack, $topIdx, $sheet, $options, $ruleCount, $throwOnLimit, $cursorOffset, $spanIndex);
							}
							array_pop($stack);
							continue;
						}
						if (is_array($prepared)) {
							$prepared['allowEofClose'] = $allowEofClose;
							$stack[] = $prepared;
						} else {
							$stack[$topIdx]['hasNestedRule'] = true;
							$stack[$topIdx]['currentStyle'] = $functionMode ? new CSSFunctionDescriptors('', $options) : new CSSStyleProperties('', $options);
						}
						continue;
					}

					// Nested style rule
					$childDepth = self::nextDepth($depth);
					if ($options->maxDepth !== null && $childDepth > $options->maxDepth) {
						$message = sprintf('Maximum CSS nesting depth of %d exceeded.', $options->maxDepth);
						if ($throwOnLimit) {
							throw new CSSParserLimitException($message);
						}
						$sheet->recordParseError($message, $delimOffset + 1);
						$closed = self::skipRemainingBlock($activeTokenizer, $sheet, $checkControlBytes);
						$cursorOffset = $activeTokenizer->getOffsetInternal();
						if (!$closed) {
							if ($allowEofClose) {
								self::closeStyleBlockFrame($stack, $topIdx, $sheet, $options, $ruleCount, $throwOnLimit, $cursorOffset, $spanIndex);
							}
							array_pop($stack);
							continue;
						}
						$stack[$topIdx]['hasNestedRule'] = true;
						$stack[$topIdx]['currentStyle'] = $functionMode ? new CSSFunctionDescriptors('', $options) : new CSSStyleProperties('', $options);
						continue;
					}

					$nonDefaultDeclLimits = self::hasNonDefaultDeclLimits($options);
					try {
						$nestedStyleRule = CSSStyleRule::createParsedInternal(
							$prelude,
							$nonDefaultDeclLimits ? new CSSStyleProperties('', $options) : null,
							$options,
							true,
							$browserMode,
						);
					} catch (InvalidArgumentException $exception) {
						if ($throwOnLimit && $exception instanceof CSSParserLimitException) {
							throw $exception;
						}
						$sheet->recordParseError($exception->getMessage(), $stmtStart);
						$closed = self::skipRemainingBlock($activeTokenizer, $sheet, $checkControlBytes);
						$cursorOffset = $activeTokenizer->getOffsetInternal();
						if (!$closed) {
							$sheet->recordParseError('Unclosed CSS rule block.', $delimOffset);
							if ($allowEofClose) {
								self::closeStyleBlockFrame($stack, $topIdx, $sheet, $options, $ruleCount, $throwOnLimit, $cursorOffset, $spanIndex);
							}
							array_pop($stack);
							continue;
						}
						$stack[$topIdx]['hasNestedRule'] = true;
						$stack[$topIdx]['currentStyle'] = $functionMode ? new CSSFunctionDescriptors('', $options) : new CSSStyleProperties('', $options);
						continue;
					}

					self::incrementCounter($ruleCount, 'CSS rule count');
					$stack[] = [
						'kind' => self::FRAME_STYLE_BLOCK,
						'currentStyle' => $nestedStyleRule->getStyle(),
						'ownerRule' => $nestedStyleRule,
						'depth' => $childDepth,
						'openBraceOffset' => $delimOffset,
						'ruleStartOffset' => $stmtStart,
						'allowNestedRules' => true,
						'pageMode' => false,
						'functionMode' => false,
						'validatePropertyGrammar' => true,
						'hasNestedRule' => false,
						'isGroupingWithDecls' => false,
						'allowEmptyInitialValue' => false,
						'sawEmptyInitialValue' => false,
						'allowEofClose' => $allowEofClose,
						'diagnostics' => [],
						'pendingRule' => $nestedStyleRule,
						'declAtRuleName' => null,
						'declAtRuleCondition' => null,
						'parentRules' => $ownerRule?->getCssRules(),
						'parentStopOnBrace' => true,
					];
					continue;
				}

				// Declaration terminated by ';', '}', or Eof
				/** @var CSSStyleDeclaration $currentStyle */
				$currentStyle = $stack[$topIdx]['currentStyle'];
				/** @var list<array{message: string, offset: int}> $diags */
				$diags = $stack[$topIdx]['diagnostics'];
				$sawEmpty = (bool) $stack[$topIdx]['sawEmptyInitialValue'];

				// Wait: what if statement is a nested statement @-rule (e.g. `@unknown foo;`) inside a block?
				// Per existing CSSParser / CSS Style Blocks, only block at-rules are structured; `@foo;` falls through as invalid declaration (or in CSSOM `@unknown;` inside style block records error).
				if ($collected['limitMessage'] !== null) {
					if ($throwOnLimit) {
						throw new CSSParserLimitException($collected['limitMessage']);
					}
					$sheet->recordParseError($collected['limitMessage'], $collected['limitOffset'] ?? $stmtStart);
				} elseif (!$declLimitRecorded) {
					$declLimitRecorded = !$currentStyle->parseDeclarationFromTokensInternal(
						$source,
						$stmtStart,
						$delimOffset,
						$collected['tokens'],
						$collected['hasComments'],
						$collected['controlOffset'],
						$collected['badTokenOffset'],
						$collected['badTokenMessage'],
						$collected['hasUnclosedConstruct'],
						$collected['unclosedBlocks'],
						$collected['hasTopLevelBraces'],
						false,
						$diags,
						$options,
						$declarationCount,
						$throwOnLimit,
						true,
						$allowEmptyInitialValue,
						$sawEmpty,
						$browserMode,
						$validatePropertyGrammar,
						$spanIndex,
					);
					$stack[$topIdx]['diagnostics'] = $diags;
					$stack[$topIdx]['sawEmptyInitialValue'] = $sawEmpty;
				}

				if ($delimType === CSSTokenType::Semicolon) {
					continue;
				}

				self::flushDiagnostics($sheet, $diags);
				$stack[$topIdx]['diagnostics'] = [];

				if ($delimType === CSSTokenType::RightCurlyBracket) {
					self::closeStyleBlockFrame($stack, $topIdx, $sheet, $options, $ruleCount, $throwOnLimit, $cursorOffset, $spanIndex);
					array_pop($stack);
					if ($stack !== [] && $stack[count($stack) - 1]['kind'] === self::FRAME_STYLE_BLOCK) {
						$parentIdx = count($stack) - 1;
						if (!(bool) $stack[$parentIdx]['pageMode']) {
							$stack[$parentIdx]['hasNestedRule'] = true;
							$pFunc = (bool) $stack[$parentIdx]['functionMode'];
							$stack[$parentIdx]['currentStyle'] = $pFunc ? new CSSFunctionDescriptors('', $options) : new CSSStyleProperties('', $options);
						}
					}
					continue;
				}

				// $delimType === CSSTokenType::Eof
				if ($allowEofClose) {
					self::closeStyleBlockFrame($stack, $topIdx, $sheet, $options, $ruleCount, $throwOnLimit, $cursorOffset, $spanIndex);
				}
				$sheet->recordParseError('Unclosed CSS rule block.', $openBraceOffset);
				array_pop($stack);
				continue;
			}

			// self::FRAME_FFV_BODY
			$activeTokenizer = self::ensureTokenizer($source, $options, $browserMode, $checkControlBytes, $cursorOffset, $tokenizer);
			self::stepFontFeatureValuesFrame($stack, $topIdx, $source, $cursorOffset, $activeTokenizer, $sheet, $options, $ruleCount, $declarationCount, $throwOnLimit, $browserMode, $checkControlBytes, $spanIndex);
		}
	}

	/**
	 * @param array<string, mixed> $parentFrame
	 */
	private static function commitRuleToFrame(
		array &$parentFrame,
		CSSRule $rule,
		bool $stopOnBrace,
		CSSStyleSheet $sheet,
		int $ruleOffset,
		int $ruleEnd = 0,
		?CSSSourceLocationIndex $spanIndex = null,
	): bool {
		if (($parentFrame['enforceRuleOrder'] ?? false) && !$stopOnBrace) {
			$seenAny = (bool) ($parentFrame['seenAnyRule'] ?? false);
			$seenNonImport = (bool) ($parentFrame['seenNonImportPrelude'] ?? false);
			$seenRegular = (bool) ($parentFrame['seenRegularRule'] ?? false);

			if ($rule instanceof CSSCharsetRule && $seenAny) {
				$sheet->recordParseError('@charset rule must be the first rule in the stylesheet.', $ruleOffset);
				return false;
			}
			if ($rule instanceof CSSImportRule && $seenNonImport) {
				$sheet->recordParseError('@import rules must precede all non-prelude rules except @charset and statement @layer rules.', $ruleOffset);
				return false;
			}
			if ($rule instanceof CSSNamespaceRule && $seenRegular) {
				$sheet->recordParseError('@namespace rules must precede all regular rules.', $ruleOffset);
				return false;
			}

			$parentFrame['seenAnyRule'] = true;
			if (!$rule instanceof CSSCharsetRule && !$rule instanceof CSSImportRule && !$rule instanceof CSSLayerStatementRule) {
				$parentFrame['seenNonImportPrelude'] = true;
			}
			if (!$rule instanceof CSSCharsetRule
				&& !$rule instanceof CSSImportRule
				&& !$rule instanceof CSSNamespaceRule
				&& !$rule instanceof CSSLayerStatementRule
			) {
				$parentFrame['seenRegularRule'] = true;
			}
		}

		if ($spanIndex !== null) {
			$rule->setSourceSpanInternal($spanIndex->span($ruleOffset, $ruleEnd));
		}

		/** @var CSSRuleList $rules */
		$rules = $parentFrame['rules'];
		$stopOnBrace ? $rules->appendDetached($rule) : $rules->appendParsedInternal($rule);

		return true;
	}

	/**
	 * @param array<string, mixed> $frame
	 */
	private static function markTopLevelRegularRuleCommitted(array &$frame): void
	{
		if ($frame['enforceRuleOrder'] ?? false) {
			$frame['seenAnyRule'] = true;
			$frame['seenNonImportPrelude'] = true;
			$frame['seenRegularRule'] = true;
		}
	}

	/**
	 * @param list<array<string, mixed>> $stack
	 */
	private static function commitClosedRuleFrame(
		array &$stack,
		int $topIdx,
		int $endOffset = 0,
		?CSSSourceLocationIndex $spanIndex = null,
	): void {
		$frame = $stack[$topIdx];
		/** @var ?CSSRule $pendingRule */
		$pendingRule = $frame['pendingRule'];
		/** @var ?CSSRuleList $parentRules */
		$parentRules = $frame['parentRules'];
		if ($pendingRule === null || $parentRules === null) {
			return;
		}
		if ($spanIndex !== null) {
			$pendingRule->setSourceSpanInternal($spanIndex->span((int) ($frame['ruleStartOffset'] ?? 0), $endOffset));
		}
		if ((bool) $frame['parentStopOnBrace']) {
			$parentRules->appendDetached($pendingRule);
		} else {
			$parentRules->appendParsedInternal($pendingRule);
			if ($topIdx > 0) {
				self::markTopLevelRegularRuleCommitted($stack[$topIdx - 1]);
			}
		}
	}

	/**
	 * @param list<array<string, mixed>> $stack
	 */
	private static function closeStyleBlockFrame(
		array &$stack,
		int $topIdx,
		CSSStyleSheet $sheet,
		CSSParserOptions $options,
		int &$ruleCount,
		bool $throwOnLimit,
		int $endOffset = 0,
		?CSSSourceLocationIndex $spanIndex = null,
	): void {
		$frame = $stack[$topIdx];
		/** @var CSSStyleDeclaration $currentStyle */
		$currentStyle = $frame['currentStyle'];
		/** @var ?CSSGroupingRule $ownerRule */
		$ownerRule = $frame['ownerRule'];
		$pageMode = (bool) $frame['pageMode'];
		$functionMode = (bool) $frame['functionMode'];

		self::flushTrailingNestedDeclarations(
			$currentStyle,
			$ownerRule,
			$sheet,
			$options,
			$ruleCount,
			$throwOnLimit,
			$pageMode,
			$functionMode,
			(bool) $frame['hasNestedRule'],
			(bool) $frame['isGroupingWithDecls'],
			(int) $frame['openBraceOffset'],
			$spanIndex !== null,
		);

		/** @var ?CSSRuleList $parentRules */
		$parentRules = $frame['parentRules'];
		if ($parentRules === null) {
			return;
		}
		$parentStop = (bool) $frame['parentStopOnBrace'];
		$ruleStartOffset = (int) $frame['ruleStartOffset'];

		/** @var ?string $declAtName */
		$declAtName = $frame['declAtRuleName'];
		if ($declAtName !== null) {
			$condition = (string) ($frame['declAtRuleCondition'] ?? '');
			$sawEmptyInitialValue = (bool) $frame['sawEmptyInitialValue'];

			try {
				if ($declAtName === '@margin') {
					$rule = new CSSMarginRule($condition, $currentStyle, $options->maxBytes);
				} else {
					$rule = match ($declAtName) {
						'color-profile' => new CSSColorProfileRule($condition, $currentStyle, $options->maxBytes),
						'font-face' => new CSSFontFaceRule($currentStyle, $options->maxBytes),
						'property' => new CSSPropertyRule($condition, $currentStyle, $sawEmptyInitialValue, $options->maxBytes),
						'counter-style' => new CSSCounterStyleRule($condition, $currentStyle, $options->maxBytes),
						'font-palette-values' => new CSSFontPaletteValuesRule($condition, $currentStyle, $options->maxBytes),
						'position-try' => new CSSPositionTryRule($condition, $currentStyle, $options->maxBytes),
						'view-transition' => new CSSViewTransitionRule($currentStyle, $options->maxBytes),
						default => new CSSAtRule($declAtName, $condition, $currentStyle, true, $options->maxBytes),
					};
				}
			} catch (InvalidArgumentException $exception) {
				if ($throwOnLimit && $exception instanceof CSSParserLimitException) {
					throw $exception;
				}
				$sheet->recordParseError($exception->getMessage(), $ruleStartOffset);
				return;
			}

			self::incrementCounter($ruleCount, 'CSS rule count');
			if ($spanIndex !== null) {
				$rule->setSourceSpanInternal($spanIndex->span($ruleStartOffset, $endOffset));
			}
			if ($parentStop) {
				$parentRules->appendDetached($rule);
			} else {
				$parentRules->appendParsedInternal($rule);
				if ($topIdx > 0) {
					self::markTopLevelRegularRuleCommitted($stack[$topIdx - 1]);
				}
			}
			return;
		}

		/** @var ?CSSRule $pendingRule */
		$pendingRule = $frame['pendingRule'];
		if ($pendingRule !== null) {
			if ($spanIndex !== null) {
				$pendingRule->setSourceSpanInternal($spanIndex->span($ruleStartOffset, $endOffset));
			}
			if ($parentStop) {
				$parentRules->appendDetached($pendingRule);
			} else {
				$parentRules->appendParsedInternal($pendingRule);
				if ($topIdx > 0) {
					self::markTopLevelRegularRuleCommitted($stack[$topIdx - 1]);
				}
			}
		}
	}

	/**
	 * @param list<array<string, mixed>> $stack
	 */
	private static function stepFontFeatureValuesFrame(
		array &$stack,
		int $topIdx,
		string $source,
		int &$cursorOffset,
		CSSTokenizer $tokenizer,
		CSSStyleSheet $sheet,
		CSSParserOptions $options,
		int &$ruleCount,
		int &$declarationCount,
		bool $throwOnLimit,
		bool $browserMode,
		bool $checkControlBytes,
		?CSSSourceLocationIndex $spanIndex = null,
	): void {
		/** @var CSSFontFeatureValuesRule $rule */
		$rule = $stack[$topIdx]['rule'];
		$depth = (int) $stack[$topIdx]['depth'];
		$openBraceOffset = (int) $stack[$topIdx]['openBraceOffset'];
		$allowEofClose = (bool) $stack[$topIdx]['allowEofClose'];

		$firstType = self::advanceNonTrivia($tokenizer, $sheet, $checkControlBytes, false);
		$cursorOffset = $tokenizer->getOffsetInternal();
		if ($firstType === CSSTokenType::Eof) {
			$sheet->recordParseError('Unclosed @font-feature-values block.', $openBraceOffset);
			if ($allowEofClose) {
				self::commitClosedRuleFrame($stack, $topIdx, $cursorOffset, $spanIndex);
			}
			array_pop($stack);
			return;
		}
		if ($firstType === CSSTokenType::RightCurlyBracket) {
			self::commitClosedRuleFrame($stack, $topIdx, $cursorOffset, $spanIndex);
			array_pop($stack);
			return;
		}

		$ruleStart = $tokenizer->tokenStart;
		$stmt = self::consumeStatementOrPrelude($source, $tokenizer, $firstType, false, $checkControlBytes, $firstType === CSSTokenType::AtKeyword);
		$cursorOffset = $tokenizer->getOffsetInternal();
		$delimType = $stmt['delimiter'];
		$delimOffset = $stmt['delimOffset'];

		if ($delimType === CSSTokenType::Eof) {
			$sheet->recordParseError('Unterminated rule in @font-feature-values.', $ruleStart);
			if ($allowEofClose) {
				self::commitClosedRuleFrame($stack, $topIdx, $cursorOffset, $spanIndex);
			}
			array_pop($stack);
			return;
		}
		if ($delimType === CSSTokenType::RightCurlyBracket) {
			$sheet->recordParseError('Unexpected trailing tokens before closing brace.', $ruleStart);
			self::commitClosedRuleFrame($stack, $topIdx, $cursorOffset, $spanIndex);
			array_pop($stack);
			return;
		}
		if ($delimType !== CSSTokenType::LeftCurlyBracket) {
			$sheet->recordParseError('Unexpected token in @font-feature-values.', $delimOffset);
			return;
		}

		$childDepth = self::nextDepth($depth);
		if ($options->maxDepth !== null && $childDepth > $options->maxDepth) {
			$message = sprintf('Maximum CSS nesting depth of %d exceeded.', $options->maxDepth);
			if ($throwOnLimit) {
				throw new CSSParserLimitException($message);
			}
			$sheet->recordParseError($message, $delimOffset + 1);
			$closed = self::skipRemainingBlock($tokenizer, $sheet, $checkControlBytes);
			$cursorOffset = $tokenizer->getOffsetInternal();
			if (!$closed) {
				if ($allowEofClose) {
					self::commitClosedRuleFrame($stack, $topIdx, $cursorOffset, $spanIndex);
				}
				array_pop($stack);
			}
			return;
		}

		if ($stmt['controlOffset'] !== null || $stmt['badTokenMessage'] !== null) {
			$msg = $stmt['controlOffset'] !== null
				? 'CSS rule prelude contains disallowed control characters.'
				: (string) $stmt['badTokenMessage'];
			$sheet->recordParseError($msg, $ruleStart);
			$closed = self::skipRemainingBlock($tokenizer, $sheet, $checkControlBytes);
			$cursorOffset = $tokenizer->getOffsetInternal();
			if (!$closed) {
				$sheet->recordParseError('Unclosed @font-feature-values feature block.', $delimOffset);
				array_pop($stack);
			}
			return;
		}

		$prelude = self::buildCleanPrelude($source, $ruleStart, $delimOffset, $stmt['hasComments'], $sheet);
		if ($prelude === null) {
			$closed = self::skipRemainingBlock($tokenizer, $sheet, $checkControlBytes);
			$cursorOffset = $tokenizer->getOffsetInternal();
			if (!$closed) {
				$sheet->recordParseError('Unclosed @font-feature-values feature block.', $delimOffset);
				array_pop($stack);
			}
			return;
		}

		try {
			[$featureType, $extra] = self::splitAtRulePrelude($prelude);
		} catch (InvalidArgumentException $exception) {
			if ($throwOnLimit && $exception instanceof CSSParserLimitException) {
				throw $exception;
			}
			$sheet->recordParseError($exception->getMessage(), $ruleStart);
			$closed = self::skipRemainingBlock($tokenizer, $sheet, $checkControlBytes);
			$cursorOffset = $tokenizer->getOffsetInternal();
			if (!$closed) {
				$sheet->recordParseError('Unclosed @font-feature-values feature block.', $delimOffset);
				array_pop($stack);
			}
			return;
		}

		if ($extra === '' && isset(self::FONT_FEATURE_CATEGORIES[$featureType])) {
			$map = $rule->getFeatureMap($featureType);
			$closed = self::parseFontFeatureCategoryBlock(
				$source,
				$tokenizer,
				$map,
				$sheet,
				$options,
				$delimOffset,
				$declarationCount,
				$throwOnLimit,
				$browserMode,
				$checkControlBytes,
			);
			$cursorOffset = $tokenizer->getOffsetInternal();
			if (!$closed) {
				array_pop($stack);
			}
		} elseif (self::canRecordRule($options, $ruleCount, $sheet, $ruleStart, $throwOnLimit)) {
			$close = self::findBlockEndAndSync($source, $delimOffset, $cursorOffset, $tokenizer);
			if ($close === null) {
				$sheet->recordParseError('Unclosed @font-feature-values feature block.', $delimOffset);
				array_pop($stack);
				return;
			}
			$raw = substr($source, $ruleStart, $close - $ruleStart + 1);
			$opaque = self::createValidatedOpaqueRule($raw, $sheet, $ruleStart, $options->maxBytes, $throwOnLimit, $browserMode);
			if ($opaque !== null) {
				$rule->getCssRules()->appendDetached($opaque);
				self::incrementCounter($ruleCount, 'CSS rule count');
			}
		} else {
			$closed = self::skipRemainingBlock($tokenizer, $sheet, $checkControlBytes);
			$cursorOffset = $tokenizer->getOffsetInternal();
			if (!$closed) {
				$sheet->recordParseError('Unclosed @font-feature-values feature block.', $delimOffset);
				array_pop($stack);
			}
		}
	}

	private static function parseFontFeatureCategoryBlock(
		string $source,
		CSSTokenizer $tokenizer,
		CSSFontFeatureValuesMap $map,
		CSSStyleSheet $sheet,
		CSSParserOptions $options,
		int $blockOpenOffset,
		int &$declarationCount,
		bool $throwOnLimit,
		bool $browserMode,
		bool $checkControlBytes,
	): bool {
		while (true) {
			$firstDeclType = self::advanceNonTrivia($tokenizer, $sheet, $checkControlBytes, false);
			if ($firstDeclType === CSSTokenType::Eof) {
				$sheet->recordParseError('Unclosed @font-feature-values feature block.', $blockOpenOffset);
				return false;
			}
			if ($firstDeclType === CSSTokenType::RightCurlyBracket) {
				return true;
			}
			if ($firstDeclType === CSSTokenType::Semicolon) {
				continue;
			}

			$segStart = $tokenizer->tokenStart;
			$collected = self::consumeFfvDeclarationTokens($source, $tokenizer, $firstDeclType, $checkControlBytes, $browserMode);
			$declDelim = $collected['delimiter'];
			if ($declDelim === CSSTokenType::Eof) {
				$sheet->recordParseError('Unclosed @font-feature-values feature block.', $blockOpenOffset);
				return false;
			}

			$hitDeclLimit = false;
			self::applyFfvDeclaration(
				$source,
				$segStart,
				$collected,
				$map,
				$sheet,
				$options,
				$declarationCount,
				$throwOnLimit,
				$browserMode,
				$hitDeclLimit,
			);

			if ($hitDeclLimit) {
				if ($declDelim !== CSSTokenType::RightCurlyBracket && !self::skipRemainingBlock($tokenizer, $sheet, $checkControlBytes)) {
					$sheet->recordParseError('Unclosed @font-feature-values feature block.', $blockOpenOffset);
					return false;
				}
				return true;
			}

			if ($declDelim === CSSTokenType::RightCurlyBracket) {
				return true;
			}
		}
	}

	/**
	 * @return array{
	 *   delimiter: CSSTokenType,
	 *   tokens: list<array{type: CSSTokenType, start: int, end: int, hasEscapes: bool, closedAtEof: bool, closedQuote: string, trailingBsAtEof: bool}>,
	 *   controlOffset: ?int,
	 *   badTokenMessage: ?string,
	 *   hasUnclosedConstruct: bool,
	 *   hasUnbalancedBlocks: bool
	 * }
	 */
	private static function consumeFfvDeclarationTokens(
		string $source,
		CSSTokenizer $tokenizer,
		CSSTokenType $firstType,
		bool $checkControlBytes,
		bool $browserMode,
	): array {
		$tokens = [];
		$blockStack = [];
		$hasComments = $tokenizer->tokenHadLeadingComment;
		$controlOffset = $tokenizer->tokenControlByteOffset;
		$badTokenMessage = null;
		$hasUnclosedConstruct = false;
		$hasUnbalancedBlocks = false;
		$stmtStart = $tokenizer->tokenStart;
		$stmtEnd = strlen($source);
		$type = $firstType;

		while (true) {
			if ($tokenizer->tokenHadLeadingComment) {
				$hasComments = true;
			}
			if ($checkControlBytes && $tokenizer->commentControlErrorOffsets !== []) {
				$controlOffset ??= $tokenizer->commentControlErrorOffsets[0];
			}
			if ($tokenizer->tokenUnclosedCommentOffset !== null) {
				$hasUnclosedConstruct = true;
			}
			if ($tokenizer->tokenControlByteOffset !== null) {
				$controlOffset ??= $tokenizer->tokenControlByteOffset;
			}

			if ($type === CSSTokenType::Eof) {
				$delimType = CSSTokenType::Eof;
				break;
			}

			if ($blockStack === [] && ($type === CSSTokenType::Semicolon || $type === CSSTokenType::RightCurlyBracket)) {
				$stmtEnd = $tokenizer->tokenStart;
				$delimType = $type;
				break;
			}

			if ($type === CSSTokenType::BadString || $type === CSSTokenType::BadUrl) {
				$badTokenMessage ??= $type === CSSTokenType::BadString
					? 'Invalid CSS string token in @font-feature-values.'
					: 'Invalid CSS url() token in @font-feature-values.';
			}
			if ($tokenizer->tokenClosedAtEof) {
				$hasUnclosedConstruct = true;
			}

			if ($type === CSSTokenType::LeftParenthesis || $type === CSSTokenType::Function) {
				$blockStack[] = ')';
			} elseif ($type === CSSTokenType::LeftSquareBracket) {
				$blockStack[] = ']';
			} elseif ($type === CSSTokenType::LeftCurlyBracket) {
				$blockStack[] = '}';
			} elseif ($type === CSSTokenType::RightParenthesis || $type === CSSTokenType::RightSquareBracket || $type === CSSTokenType::RightCurlyBracket) {
				$closer = match ($type) {
					CSSTokenType::RightParenthesis => ')',
					CSSTokenType::RightSquareBracket => ']',
					CSSTokenType::RightCurlyBracket => '}',
				};
				if ($blockStack !== [] && end($blockStack) === $closer) {
					array_pop($blockStack);
				} else {
					$hasUnbalancedBlocks = true;
				}
			}

			$tokens[] = [
				'type' => $type,
				'start' => $tokenizer->tokenStart,
				'end' => $tokenizer->tokenEnd,
				'hasEscapes' => $tokenizer->tokenHasEscapes,
				'closedAtEof' => $tokenizer->tokenClosedAtEof,
				'closedQuote' => $tokenizer->tokenClosedQuote,
				'trailingBsAtEof' => $tokenizer->tokenTrailingBackslashAtEof,
			];

			$type = $tokenizer->advanceInternal();
		}

		if ($browserMode && $controlOffset === null && $stmtEnd > $stmtStart) {
			if (!$hasComments) {
				if (CSSSyntaxScanner::hasControlCharactersInSlice($source, $stmtStart, $stmtEnd - $stmtStart)) {
					$controlOffset = $stmtStart;
				}
			} else {
				foreach ($tokens as $tok) {
					$tokLen = $tok['end'] - $tok['start'];
					if ($tokLen > 0 && CSSSyntaxScanner::hasControlCharactersInSlice($source, $tok['start'], $tokLen)) {
						$controlOffset = $tok['start'];
						break;
					}
				}
			}
		}

		return [
			'delimiter' => $delimType,
			'tokens' => $tokens,
			'controlOffset' => $controlOffset,
			'badTokenMessage' => $badTokenMessage,
			'hasUnclosedConstruct' => $hasUnclosedConstruct,
			'hasUnbalancedBlocks' => $hasUnbalancedBlocks || $blockStack !== [],
		];
	}

	/**
	 * @param array{
	 *   delimiter: CSSTokenType,
	 *   tokens: list<array{type: CSSTokenType, start: int, end: int, hasEscapes: bool, closedAtEof: bool, closedQuote: string, trailingBsAtEof: bool}>,
	 *   controlOffset: ?int,
	 *   badTokenMessage: ?string,
	 *   hasUnclosedConstruct: bool,
	 *   hasUnbalancedBlocks: bool
	 * } $collected
	 */
	private static function applyFfvDeclaration(
		string $source,
		int $segStart,
		array $collected,
		CSSFontFeatureValuesMap $map,
		CSSStyleSheet $sheet,
		CSSParserOptions $options,
		int &$declarationCount,
		bool $throwOnLimit,
		bool $browserMode,
		bool &$hitDeclLimit,
	): void {
		$hitDeclLimit = false;
		if ($collected['controlOffset'] !== null) {
			$sheet->recordParseError('CSS font feature declaration contains disallowed control characters.', $segStart);
			return;
		}
		if ($collected['badTokenMessage'] !== null) {
			$sheet->recordParseError($collected['badTokenMessage'], $segStart);
			return;
		}
		if ((!$browserMode && $collected['hasUnclosedConstruct']) || $collected['hasUnbalancedBlocks']) {
			$sheet->recordParseError('Invalid CSS font feature declaration.', $segStart);
			return;
		}

		$tokens = $collected['tokens'];
		$tokenCount = count($tokens);
		$colonIdx = null;
		$nest = 0;
		for ($i = 0; $i < $tokenCount; $i++) {
			$t = $tokens[$i]['type'];
			if ($t === CSSTokenType::LeftParenthesis || $t === CSSTokenType::Function || $t === CSSTokenType::LeftSquareBracket || $t === CSSTokenType::LeftCurlyBracket) {
				$nest++;
			} elseif ($t === CSSTokenType::RightParenthesis || $t === CSSTokenType::RightSquareBracket || $t === CSSTokenType::RightCurlyBracket) {
				if ($nest > 0) {
					$nest--;
				}
			} elseif ($nest === 0 && $t === CSSTokenType::Colon) {
				$colonIdx = $i;
				break;
			}
		}

		if ($colonIdx === null) {
			$sheet->recordParseError('Invalid CSS font feature declaration (missing colon).', $segStart);
			return;
		}

		$nameStartIdx = 0;
		while ($nameStartIdx < $colonIdx && $tokens[$nameStartIdx]['type'] === CSSTokenType::Whitespace) {
			$nameStartIdx++;
		}
		$nameEndIdx = $colonIdx - 1;
		while ($nameEndIdx >= $nameStartIdx && $tokens[$nameEndIdx]['type'] === CSSTokenType::Whitespace) {
			$nameEndIdx--;
		}
		if ($nameStartIdx !== $nameEndIdx || $tokens[$nameStartIdx]['type'] !== CSSTokenType::Ident) {
			$sheet->recordParseError('Invalid font feature value name.', $segStart);
			return;
		}
		$nameTok = $tokens[$nameStartIdx];
		$rawName = substr($source, $nameTok['start'], $nameTok['end'] - $nameTok['start']);
		if ($nameTok['hasEscapes'] && CSSSyntaxScanner::hasControlCharacters(CSSSyntaxScanner::decodeCssIdentEscapes($rawName))) {
			$sheet->recordParseError('Invalid font feature value name.', $segStart);
			return;
		}

		$valStartIdx = $colonIdx + 1;
		while ($valStartIdx < $tokenCount && $tokens[$valStartIdx]['type'] === CSSTokenType::Whitespace) {
			$valStartIdx++;
		}
		$valEndIdx = $tokenCount - 1;
		while ($valEndIdx >= $valStartIdx && $tokens[$valEndIdx]['type'] === CSSTokenType::Whitespace) {
			$valEndIdx--;
		}

		if (CSSStyleDeclaration::matchTrailingImportantTokensInternal($source, $tokens, $valStartIdx, $valEndIdx) !== null) {
			$sheet->recordParseError('!important is not allowed in @font-feature-values.', $segStart);
			return;
		}

		if ($options->maxDeclarations !== null && $declarationCount >= $options->maxDeclarations) {
			$message = sprintf('Maximum CSS declaration limit of %d exceeded.', $options->maxDeclarations);
			if ($throwOnLimit) {
				throw new CSSParserLimitException($message);
			}
			$sheet->recordParseError($message, $segStart);
			$hitDeclLimit = true;
			return;
		}
		self::incrementCounter($declarationCount, 'CSS declaration count');

		$ints = [];
		$valid = true;
		for ($i = $valStartIdx; $i <= $valEndIdx; $i++) {
			$tok = $tokens[$i];
			if ($tok['type'] === CSSTokenType::Whitespace) {
				continue;
			}
			if ($tok['type'] !== CSSTokenType::Number) {
				$valid = false;
				break;
			}
			$rawNum = substr($source, $tok['start'], $tok['end'] - $tok['start']);
			if (preg_match('/^\d+$/D', $rawNum) !== 1) {
				$valid = false;
				break;
			}
			if (count($ints) >= CSSStyleValue::MAX_LIST_ITEMS) {
				$message = sprintf('CSS font feature value list exceeds maximum limit of %d items.', CSSStyleValue::MAX_LIST_ITEMS);
				if ($throwOnLimit) {
					throw new CSSParserLimitException($message);
				}
				$sheet->recordParseError($message, $segStart);
				return;
			}
			$stripped = ltrim($rawNum, '0');
			$intVal = (int) $rawNum;
			if ($stripped !== '' && (string) $intVal !== $stripped) {
				$valid = false;
				break;
			}
			$ints[] = $intVal;
		}

		if (!$valid || $ints === []) {
			$sheet->recordParseError('Font feature values must be non-empty integer lists.', $segStart);
			return;
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

	/**
	 * @return array<string, mixed>|bool|null Frame array to push, `true` if handled synchronously, `null` on recoverable error, `false` if unclosed EOF block during skip.
	 */
	private static function prepareBlockAtRuleFrame(
		string $prelude,
		string $source,
		int $ruleStart,
		int $atKeywordEnd,
		int $braceOffset,
		int &$cursorOffset,
		?CSSTokenizer &$tokenizer,
		CSSStyleSheet $sheet,
		bool $allowDeclarations,
		int $depth,
		CSSParserOptions $options,
		int &$ruleCount,
		bool $throwOnLimit,
		bool $functionMode,
		bool $browserMode,
		?CSSRuleList $parentRules,
		bool $parentStopOnBrace,
		?CSSSourceLocationIndex $spanIndex = null,
	): array|bool|null {
		$checkControlBytes = !$browserMode;

		try {
			[$name, $condition] = self::splitAtRulePrelude($prelude);
		} catch (InvalidArgumentException $exception) {
			if ($throwOnLimit && $exception instanceof CSSParserLimitException) {
				throw $exception;
			}
			$sheet->recordParseError($exception->getMessage(), $ruleStart);
			if (!self::skipBlockAndSyncCursor($source, $options, $browserMode, $checkControlBytes, $cursorOffset, $tokenizer, $sheet)) {
				$sheet->recordParseError('Unclosed CSS rule block.', $braceOffset);
				return false;
			}
			return null;
		}

		if ($options->maxDepth !== null && $depth > $options->maxDepth) {
			$message = sprintf('Maximum CSS nesting depth of %d exceeded.', $options->maxDepth);
			if ($throwOnLimit) {
				throw new CSSParserLimitException($message);
			}
			$sheet->recordParseError($message, $braceOffset + 1);
			if (!self::skipBlockAndSyncCursor($source, $options, $browserMode, $checkControlBytes, $cursorOffset, $tokenizer, $sheet)) {
				return false;
			}
			return null;
		}

		$isKnown = ($name === 'page'
			|| isset(self::GROUPING_AT_RULES[$name])
			|| isset(self::DECLARATION_AT_RULES[$name]))
			&& !($allowDeclarations && ($name === 'keyframes' || $name === '-webkit-keyframes'));

		if (!$isKnown) {
			$close = self::findBlockEndAndSync($source, $braceOffset, $cursorOffset, $tokenizer);
			if ($close === null) {
				$sheet->recordParseError('Unclosed CSS rule block.', $braceOffset);
				return false;
			}
			$raw = substr($source, $ruleStart, $close - $ruleStart + 1);
			$opaque = self::createValidatedOpaqueRule($raw, $sheet, $ruleStart, $options->maxBytes, $throwOnLimit, $browserMode);
			if ($opaque === null) {
				return null;
			}
			self::incrementCounter($ruleCount, 'CSS rule count');
			if ($spanIndex !== null) {
				$opaque->setSourceSpanInternal($spanIndex->span($ruleStart, $close + 1));
			}
			if ($parentRules !== null) {
				$parentStopOnBrace ? $parentRules->appendDetached($opaque) : $parentRules->appendParsedInternal($opaque);
			}
			return true;
		}

		if ($name === 'page') {
			try {
				$pageRule = new CSSPageRule($condition, new CSSPageDescriptors('', $options), [], $options->maxBytes);
			} catch (InvalidArgumentException $exception) {
				if ($throwOnLimit && $exception instanceof CSSParserLimitException) {
					throw $exception;
				}
				$sheet->recordParseError($exception->getMessage(), $ruleStart);
				if (!self::skipBlockAndSyncCursor($source, $options, $browserMode, $checkControlBytes, $cursorOffset, $tokenizer, $sheet)) {
					$sheet->recordParseError('Unclosed CSS rule block.', $braceOffset);
					return false;
				}
				return null;
			}
			self::incrementCounter($ruleCount, 'CSS rule count');

			return [
				'kind' => self::FRAME_STYLE_BLOCK,
				'currentStyle' => $pageRule->getStyle(),
				'ownerRule' => $pageRule,
				'depth' => $depth,
				'openBraceOffset' => $braceOffset,
				'ruleStartOffset' => $ruleStart,
				'allowNestedRules' => true,
				'pageMode' => true,
				'functionMode' => false,
				'validatePropertyGrammar' => true,
				'hasNestedRule' => false,
				'isGroupingWithDecls' => false,
				'allowEmptyInitialValue' => false,
				'sawEmptyInitialValue' => false,
				'allowEofClose' => false,
				'diagnostics' => [],
				'pendingRule' => $pageRule,
				'declAtRuleName' => null,
				'declAtRuleCondition' => null,
				'parentRules' => $parentRules,
				'parentStopOnBrace' => $parentStopOnBrace,
			];
		}

		if ($name === 'font-feature-values') {
			try {
				$ffvRule = new CSSFontFeatureValuesRule($condition, $options->maxBytes);
				$ffvRule->setDetachedMaxDeclarationsInternal($options->maxDeclarations);
			} catch (InvalidArgumentException $exception) {
				if ($throwOnLimit && $exception instanceof CSSParserLimitException) {
					throw $exception;
				}
				$sheet->recordParseError($exception->getMessage(), $ruleStart);
				if (!self::skipBlockAndSyncCursor($source, $options, $browserMode, $checkControlBytes, $cursorOffset, $tokenizer, $sheet)) {
					$sheet->recordParseError('Unclosed CSS rule block.', $braceOffset);
					return false;
				}
				return null;
			}
			self::incrementCounter($ruleCount, 'CSS rule count');

			return [
				'kind' => self::FRAME_FFV_BODY,
				'rule' => $ffvRule,
				'depth' => $depth,
				'openBraceOffset' => $braceOffset,
				'ruleStartOffset' => $ruleStart,
				'allowEofClose' => false,
				'pendingRule' => $ffvRule,
				'parentRules' => $parentRules,
				'parentStopOnBrace' => $parentStopOnBrace,
			];
		}

		if (($name === 'starting-style' || $name === 'font-face' || $name === 'view-transition') && $condition !== '') {
			$sheet->recordParseError(sprintf('@%s does not accept a prelude.', $name), $ruleStart);
			if (!self::skipBlockAndSyncCursor($source, $options, $browserMode, $checkControlBytes, $cursorOffset, $tokenizer, $sheet)) {
				$sheet->recordParseError('Unclosed CSS rule block.', $braceOffset);
				return false;
			}
			return null;
		}

		if (isset(self::GROUPING_AT_RULES[$name])) {
			try {
				$rule = match ($name) {
					'media' => new CSSMediaRule($condition, $options->maxBytes, $browserMode),
					'supports' => new CSSSupportsRule($condition, $options->maxBytes),
					'container' => new CSSContainerRule($condition, $options->maxBytes),
					'scope' => new CSSScopeRule($condition, $options->maxBytes),
					'layer' => new CSSLayerBlockRule($condition, $options->maxBytes),
					'keyframes', '-webkit-keyframes' => new CSSKeyframesRule($condition, $name, $options->maxBytes),
					'starting-style' => new CSSStartingStyleRule($options->maxBytes),
					'function' => CSSFunctionRule::fromPrelude(
						$atKeywordEnd > $ruleStart ? substr($source, $atKeywordEnd, $braceOffset - $atKeywordEnd) : $condition,
						$options->maxBytes,
					),
					default => new CSSGroupingRule($name, $condition, $options->maxBytes),
				};
			} catch (InvalidArgumentException $exception) {
				if ($throwOnLimit && $exception instanceof CSSParserLimitException) {
					throw $exception;
				}
				$sheet->recordParseError($exception->getMessage(), $ruleStart);
				if (!self::skipBlockAndSyncCursor($source, $options, $browserMode, $checkControlBytes, $cursorOffset, $tokenizer, $sheet)) {
					$sheet->recordParseError('Unclosed CSS rule block.', $braceOffset);
					return false;
				}
				return null;
			}

			self::incrementCounter($ruleCount, 'CSS rule count');

			if (($allowDeclarations || $name === 'function') && !$rule instanceof CSSKeyframesRule) {
				$isFunction = $functionMode || $rule instanceof CSSFunctionRule;
				return [
					'kind' => self::FRAME_STYLE_BLOCK,
					'currentStyle' => $isFunction ? new CSSFunctionDescriptors('', $options) : new CSSStyleProperties('', $options),
					'ownerRule' => $rule,
					'depth' => $depth,
					'openBraceOffset' => $braceOffset,
					'ruleStartOffset' => $ruleStart,
					'allowNestedRules' => true,
					'pageMode' => false,
					'functionMode' => $isFunction,
					'validatePropertyGrammar' => !$isFunction,
					'hasNestedRule' => false,
					'isGroupingWithDecls' => true,
					'allowEmptyInitialValue' => false,
					'sawEmptyInitialValue' => false,
					'allowEofClose' => false,
					'diagnostics' => [],
					'pendingRule' => $rule,
					'declAtRuleName' => null,
					'declAtRuleCondition' => null,
					'parentRules' => $parentRules,
					'parentStopOnBrace' => $parentStopOnBrace,
				];
			}

			return [
				'kind' => self::FRAME_RULE_LIST,
				'rules' => $rule->getCssRules(),
				'keyframes' => $rule instanceof CSSKeyframesRule,
				'allowRelativeSelectors' => $rule instanceof CSSScopeRule,
				'depth' => $depth,
				'stopOnClosingBrace' => true,
				'openBraceOffset' => $braceOffset,
				'ruleStartOffset' => $ruleStart,
				'allowEofClose' => false,
				'allowCdoCdc' => false,
				'limitReached' => false,
				'pendingRule' => $rule,
				'parentRules' => $parentRules,
				'parentStopOnBrace' => $parentStopOnBrace,
			];
		}

		// DECLARATION_AT_RULES (depth counts as 1 level, already passed in $depth)
		$declarationClass = match ($name) {
			'font-face' => CSSFontFaceDescriptors::class,
			'position-try' => CSSPositionTryDescriptors::class,
			default => CSSStyleDeclaration::class,
		};
		$style = new $declarationClass('', $options);

		return [
			'kind' => self::FRAME_STYLE_BLOCK,
			'currentStyle' => $style,
			'ownerRule' => null,
			'depth' => $depth,
			'openBraceOffset' => $braceOffset,
			'ruleStartOffset' => $ruleStart,
			'allowNestedRules' => false,
			'pageMode' => false,
			'functionMode' => false,
			'validatePropertyGrammar' => !in_array($name, ['color-profile', 'property', 'counter-style', 'font-palette-values', 'view-transition', 'font-face'], true),
			'hasNestedRule' => false,
			'isGroupingWithDecls' => false,
			'allowEmptyInitialValue' => $name === 'property',
			'sawEmptyInitialValue' => false,
			'allowEofClose' => false,
			'diagnostics' => [],
			'pendingRule' => null,
			'declAtRuleName' => $name,
			'declAtRuleCondition' => $condition,
			'parentRules' => $parentRules,
			'parentStopOnBrace' => $parentStopOnBrace,
		];
	}

	/**
	 * Advances `$tokenizer` to the first non-whitespace (and optionally non-CDO/CDC) token,
	 * flushing any leading comment diagnostics onto `$sheet`.
	 */
	private static function advanceNonTrivia(
		CSSTokenizer $tokenizer,
		CSSStyleSheet $sheet,
		bool $checkControlBytes,
		bool $allowCdoCdc,
	): CSSTokenType {
		while (true) {
			$type = $tokenizer->advanceInternal();
			if ($checkControlBytes && $tokenizer->commentControlErrorOffsets !== []) {
				foreach ($tokenizer->commentControlErrorOffsets as $errOffset) {
					$sheet->recordParseError('CSS comment contains disallowed control characters.', $errOffset);
				}
			}
			if ($tokenizer->tokenUnclosedCommentOffset !== null) {
				$sheet->recordParseError('Unclosed CSS comment.', $tokenizer->tokenUnclosedCommentOffset);
			}
			if ($type === CSSTokenType::Whitespace) {
				continue;
			}
			if ($allowCdoCdc && ($type === CSSTokenType::Cdo || $type === CSSTokenType::Cdc)) {
				continue;
			}

			return $type;
		}
	}

	/**
	 * Consumes a rule prelude starting from `$firstType` until top-level `{`, `}`, `;` (optional), or `Eof`.
	 *
	 * @return array{delimiter: CSSTokenType, delimOffset: int, atKeywordEnd: int, hasComments: bool, controlOffset: ?int, unclosedCommentOffset: ?int, badTokenMessage: ?string}
	 */
	private static function consumeStatementOrPrelude(
		string $source,
		CSSTokenizer $tokenizer,
		CSSTokenType $firstType,
		bool $browserMode,
		bool $checkControlBytes,
		bool $isAtRule,
	): array {
		$length = strlen($source);
		$atKeywordEnd = $isAtRule ? $tokenizer->tokenEnd : 0;
		$hasComments = $tokenizer->tokenHadLeadingComment;
		$controlOffset = $tokenizer->tokenControlByteOffset;
		$unclosedCommentOffset = $tokenizer->tokenUnclosedCommentOffset;
		$badTokenMessage = null;
		$blockStack = [];
		$stopOnSemicolon = $isAtRule || !$browserMode;

		$type = $firstType;
		while (true) {
			if ($tokenizer->tokenHadLeadingComment) {
				$hasComments = true;
			}
			if ($checkControlBytes && $tokenizer->commentControlErrorOffsets !== []) {
				$controlOffset ??= $tokenizer->commentControlErrorOffsets[0];
			}
			if ($tokenizer->tokenUnclosedCommentOffset !== null) {
				$unclosedCommentOffset ??= $tokenizer->tokenUnclosedCommentOffset;
			}
			if ($tokenizer->tokenControlByteOffset !== null) {
				$controlOffset ??= $tokenizer->tokenControlByteOffset;
			}

			if ($type === CSSTokenType::Eof) {
				return [
					'delimiter' => CSSTokenType::Eof,
					'delimOffset' => $length,
					'atKeywordEnd' => $atKeywordEnd,
					'hasComments' => $hasComments,
					'controlOffset' => $controlOffset,
					'unclosedCommentOffset' => $unclosedCommentOffset,
					'badTokenMessage' => $badTokenMessage,
				];
			}

			if ($blockStack === []) {
				if ($type === CSSTokenType::LeftCurlyBracket) {
					return [
						'delimiter' => CSSTokenType::LeftCurlyBracket,
						'delimOffset' => $tokenizer->tokenStart,
						'atKeywordEnd' => $atKeywordEnd,
						'hasComments' => $hasComments,
						'controlOffset' => $controlOffset,
						'unclosedCommentOffset' => $unclosedCommentOffset,
						'badTokenMessage' => $badTokenMessage,
					];
				}
				if ($type === CSSTokenType::RightCurlyBracket) {
					return [
						'delimiter' => CSSTokenType::RightCurlyBracket,
						'delimOffset' => $tokenizer->tokenStart,
						'atKeywordEnd' => $atKeywordEnd,
						'hasComments' => $hasComments,
						'controlOffset' => $controlOffset,
						'unclosedCommentOffset' => $unclosedCommentOffset,
						'badTokenMessage' => $badTokenMessage,
					];
				}
				if ($stopOnSemicolon && $type === CSSTokenType::Semicolon) {
					return [
						'delimiter' => CSSTokenType::Semicolon,
						'delimOffset' => $tokenizer->tokenStart,
						'atKeywordEnd' => $atKeywordEnd,
						'hasComments' => $hasComments,
						'controlOffset' => $controlOffset,
						'unclosedCommentOffset' => $unclosedCommentOffset,
						'badTokenMessage' => $badTokenMessage,
					];
				}
			}

			if ($type === CSSTokenType::BadString || $type === CSSTokenType::BadUrl) {
				$badTokenMessage ??= $type === CSSTokenType::BadString
					? 'Invalid CSS string token in rule prelude.'
					: 'Invalid CSS url() token in rule prelude.';
			} elseif (!$browserMode && $tokenizer->tokenClosedAtEof) {
				$badTokenMessage ??= 'Unclosed string or url() token in rule prelude.';
			}

			if ($type === CSSTokenType::LeftParenthesis || $type === CSSTokenType::Function) {
				$blockStack[] = ')';
			} elseif ($type === CSSTokenType::LeftSquareBracket) {
				$blockStack[] = ']';
			} elseif ($type === CSSTokenType::LeftCurlyBracket) {
				$blockStack[] = '}';
			} elseif ($type === CSSTokenType::RightParenthesis || $type === CSSTokenType::RightSquareBracket || $type === CSSTokenType::RightCurlyBracket) {
				$closer = match ($type) {
					CSSTokenType::RightParenthesis => ')',
					CSSTokenType::RightSquareBracket => ']',
					CSSTokenType::RightCurlyBracket => '}',
				};
				if ($blockStack !== [] && end($blockStack) === $closer) {
					array_pop($blockStack);
				}
			}

			$type = $tokenizer->advanceInternal();
		}
	}

	/**
	 * Consumes tokens inside a `STYLE_BLOCK` statement starting from `$firstType` until top-level `;`, `}`, `{`, or `Eof`.
	 *
	 * @return array{
	 *   delimiter: CSSTokenType,
	 *   delimOffset: int,
	 *   atKeywordEnd: int,
	 *   tokens: list<array{type: CSSTokenType, start: int, end: int, hasEscapes: bool, closedAtEof: bool, closedQuote: string, trailingBsAtEof: bool}>,
	 *   hasComments: bool,
	 *   controlOffset: ?int,
	 *   badTokenOffset: ?int,
	 *   badTokenMessage: ?string,
	 *   limitOffset: ?int,
	 *   limitMessage: ?string,
	 *   hasUnclosedConstruct: bool,
	 *   unclosedBlocks: list<string>,
	 *   hasTopLevelBraces: bool
	 * }
	 */
	private static function consumeStyleStatementTokens(
		string $source,
		CSSTokenizer $tokenizer,
		CSSTokenType $firstType,
		bool $checkControlBytes,
		bool $browserMode,
		?int $maxDepth = null,
	): array {
		$length = strlen($source);
		$atKeywordEnd = $firstType === CSSTokenType::AtKeyword ? $tokenizer->tokenEnd : 0;
		$tokens = [];
		$blockStack = [];
		$hasComments = $tokenizer->tokenHadLeadingComment;
		$controlOffset = $tokenizer->tokenControlByteOffset;
		$badTokenOffset = null;
		$badTokenMessage = null;
		$limitOffset = null;
		$limitMessage = null;
		$hasUnclosedConstruct = false;
		$hasTopLevelBraces = false;
		$stmtStart = $tokenizer->tokenStart;

		$type = $firstType;
		while (true) {
			if ($tokenizer->tokenHadLeadingComment) {
				$hasComments = true;
			}
			if ($checkControlBytes && $tokenizer->commentControlErrorOffsets !== []) {
				$controlOffset ??= $tokenizer->commentControlErrorOffsets[0];
			}
			if ($tokenizer->tokenUnclosedCommentOffset !== null) {
				$hasUnclosedConstruct = true;
			}
			if ($tokenizer->tokenControlByteOffset !== null) {
				$controlOffset ??= $tokenizer->tokenControlByteOffset;
			}

			if ($type === CSSTokenType::Eof) {
				$delimOffset = $length;
				$delimType = CSSTokenType::Eof;
				break;
			}

			if ($blockStack === [] && ($type === CSSTokenType::Semicolon || $type === CSSTokenType::LeftCurlyBracket || $type === CSSTokenType::RightCurlyBracket)) {
				$delimOffset = $tokenizer->tokenStart;
				$delimType = $type;
				break;
			}

			if ($type === CSSTokenType::BadString || $type === CSSTokenType::BadUrl) {
				$badTokenOffset ??= $tokenizer->tokenStart;
				$badTokenMessage ??= $type === CSSTokenType::BadString
					? 'Invalid CSS string token.'
					: 'Invalid CSS url() token.';
			} elseif (!$browserMode && $tokenizer->tokenParseErrorMessage !== null) {
				$badTokenOffset ??= $tokenizer->tokenStart;
				$badTokenMessage ??= $tokenizer->tokenParseErrorMessage;
			}
			if ($tokenizer->tokenClosedAtEof) {
				$hasUnclosedConstruct = true;
			}

			if ($type === CSSTokenType::LeftParenthesis || $type === CSSTokenType::Function) {
				$blockStack[] = ')';
				if ($maxDepth !== null && count($blockStack) > $maxDepth && $limitMessage === null) {
					$limitOffset = $tokenizer->tokenStart;
					$limitMessage = sprintf('Maximum CSS nesting depth of %d exceeded.', $maxDepth);
				}
			} elseif ($type === CSSTokenType::LeftSquareBracket) {
				$blockStack[] = ']';
				if ($maxDepth !== null && count($blockStack) > $maxDepth && $limitMessage === null) {
					$limitOffset = $tokenizer->tokenStart;
					$limitMessage = sprintf('Maximum CSS nesting depth of %d exceeded.', $maxDepth);
				}
			} elseif ($type === CSSTokenType::LeftCurlyBracket) {
				$blockStack[] = '}';
				if ($maxDepth !== null && count($blockStack) > $maxDepth && $limitMessage === null) {
					$limitOffset = $tokenizer->tokenStart;
					$limitMessage = sprintf('Maximum CSS nesting depth of %d exceeded.', $maxDepth);
				}
			} elseif ($type === CSSTokenType::RightParenthesis || $type === CSSTokenType::RightSquareBracket || $type === CSSTokenType::RightCurlyBracket) {
				$closer = match ($type) {
					CSSTokenType::RightParenthesis => ')',
					CSSTokenType::RightSquareBracket => ']',
					CSSTokenType::RightCurlyBracket => '}',
				};
				if ($blockStack !== [] && end($blockStack) === $closer) {
					array_pop($blockStack);
				} else {
					$hasTopLevelBraces = true;
				}
			}

			$tokens[] = [
				'type' => $type,
				'start' => $tokenizer->tokenStart,
				'end' => $tokenizer->tokenEnd,
				'hasEscapes' => $tokenizer->tokenHasEscapes,
				'closedAtEof' => $tokenizer->tokenClosedAtEof,
				'closedQuote' => $tokenizer->tokenClosedQuote,
				'trailingBsAtEof' => $tokenizer->tokenTrailingBackslashAtEof,
			];

			$type = $tokenizer->advanceInternal();
		}

		// In browser mode, enforce CSSOM non-NUL control-byte invariant on actual non-comment tokens.
		if ($browserMode && $controlOffset === null && $delimOffset > $stmtStart) {
			if (!$hasComments) {
				if (CSSSyntaxScanner::hasControlCharactersInSlice($source, $stmtStart, $delimOffset - $stmtStart)) {
					$controlOffset = $stmtStart;
				}
			} else {
				foreach ($tokens as $tok) {
					$tokLen = $tok['end'] - $tok['start'];
					if ($tokLen > 0 && CSSSyntaxScanner::hasControlCharactersInSlice($source, $tok['start'], $tokLen)) {
						$controlOffset = $tok['start'];
						break;
					}
				}
			}
		}

		return [
			'delimiter' => $delimType,
			'delimOffset' => $delimOffset,
			'atKeywordEnd' => $atKeywordEnd,
			'tokens' => $tokens,
			'hasComments' => $hasComments,
			'controlOffset' => $controlOffset,
			'badTokenOffset' => $badTokenOffset,
			'badTokenMessage' => $badTokenMessage,
			'limitOffset' => $limitOffset,
			'limitMessage' => $limitMessage,
			'hasUnclosedConstruct' => $hasUnclosedConstruct,
			'unclosedBlocks' => $blockStack,
			'hasTopLevelBraces' => $hasTopLevelBraces,
		];
	}

	/**
	 * Skips tokens inside an already-opened `{` block until its matching `}` (ignoring stray `)`/`]`), returning false on EOF.
	 */
	private static function skipRemainingBlock(CSSTokenizer $tokenizer, CSSStyleSheet $sheet, bool $checkControlBytes): bool
	{
		$stack = ['}'];
		while (true) {
			$type = $tokenizer->advanceInternal();
			if ($checkControlBytes && $tokenizer->commentControlErrorOffsets !== []) {
				foreach ($tokenizer->commentControlErrorOffsets as $errOffset) {
					$sheet->recordParseError('CSS comment contains disallowed control characters.', $errOffset);
				}
			}
			if ($tokenizer->tokenUnclosedCommentOffset !== null) {
				$sheet->recordParseError('Unclosed CSS comment.', $tokenizer->tokenUnclosedCommentOffset);
			}
			if ($type === CSSTokenType::Eof) {
				return false;
			}
			if ($type === CSSTokenType::LeftParenthesis || $type === CSSTokenType::Function) {
				$stack[] = ')';
			} elseif ($type === CSSTokenType::LeftSquareBracket) {
				$stack[] = ']';
			} elseif ($type === CSSTokenType::LeftCurlyBracket) {
				$stack[] = '}';
			} elseif ($type === CSSTokenType::RightParenthesis || $type === CSSTokenType::RightSquareBracket) {
				$closer = $type === CSSTokenType::RightParenthesis ? ')' : ']';
				if (end($stack) === $closer) {
					array_pop($stack);
				}
			} elseif ($type === CSSTokenType::RightCurlyBracket) {
				$top = end($stack);
				if ($top === '}') {
					array_pop($stack);
					if ($stack === []) {
						return true;
					}
				} elseif (count($stack) > 1) {
					do {
						array_pop($stack);
					} while ($stack !== [] && end($stack) !== '}');
					if ($stack !== [] && end($stack) === '}') {
						array_pop($stack);
						if ($stack === []) {
							return true;
						}
					}
				}
			}
		}
	}

	/**
	 * @param-out CSSTokenizer $tokenizer
	 */
	private static function ensureTokenizer(
		string $source,
		CSSParserOptions $options,
		bool $browserMode,
		bool $checkControlBytes,
		int $cursorOffset,
		?CSSTokenizer &$tokenizer,
	): CSSTokenizer {
		if ($tokenizer === null) {
			$tokenizer = CSSTokenizer::forParserInternal($source, $options, $browserMode, $checkControlBytes, $cursorOffset);
		} else {
			$tokenizer->setOffsetInternal($cursorOffset);
		}

		return $tokenizer;
	}

	/**
	 * @param-out CSSTokenizer $tokenizer
	 */
	private static function skipBlockAndSyncCursor(
		string $source,
		CSSParserOptions $options,
		bool $browserMode,
		bool $checkControlBytes,
		int &$cursorOffset,
		?CSSTokenizer &$tokenizer,
		CSSStyleSheet $sheet,
	): bool {
		$activeTokenizer = self::ensureTokenizer($source, $options, $browserMode, $checkControlBytes, $cursorOffset, $tokenizer);
		$closed = self::skipRemainingBlock($activeTokenizer, $sheet, $checkControlBytes);
		$cursorOffset = $activeTokenizer->getOffsetInternal();

		return $closed;
	}

	private static function findBlockEndAndSync(
		string $source,
		int $openBraceOffset,
		int &$cursorOffset,
		?CSSTokenizer $tokenizer,
	): ?int {
		$len = strlen($source);
		$close = CSSSyntaxScanner::findMatchingBlockEnd($source, $openBraceOffset, $len);
		if ($close !== null) {
			$cursorOffset = $close + 1;
			$tokenizer?->setOffsetInternal($cursorOffset);
			return $close;
		}
		$cursorOffset = $len;
		$tokenizer?->setOffsetInternal($len);

		return null;
	}

	/**
	 * Scans a clean ASCII `@`-rule block prelude ending at top-level `{`, allowing balanced `()` and `/* ... *\/` comments.
	 *
	 * @return ?array{braceOffset: int, atKeywordEnd: int, hasComments: bool}
	 */
	private static function scanCleanAsciiAtRuleBlockPrelude(
		string $source,
		int $start,
		int $length,
		bool $checkControlBytes,
	): ?array {
		$identStart = $start + 1;
		if ($identStart >= $length) {
			return null;
		}
		$kwLen = strspn($source, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-_', $identStart, $length - $identStart);
		if ($kwLen === 0) {
			return null;
		}
		$firstCh = $source[$identStart];
		if ($firstCh === '-') {
			if ($kwLen < 2) {
				return null;
			}
			$secondCh = $source[$identStart + 1];
			if ($secondCh !== '-' && $secondCh !== '_' && !($secondCh >= 'a' && $secondCh <= 'z') && !($secondCh >= 'A' && $secondCh <= 'Z')) {
				return null;
			}
		} elseif ($firstCh !== '_' && !($firstCh >= 'a' && $firstCh <= 'z') && !($firstCh >= 'A' && $firstCh <= 'Z')) {
			return null;
		}

		$atKeywordEnd = $identStart + $kwLen;
		if ($atKeywordEnd < $length && ($source[$atKeywordEnd] === '\\' || ord($source[$atKeywordEnd]) >= 0x80)) {
			return null;
		}

		$p = $atKeywordEnd;
		$parenDepth = 0;
		$hasComments = false;

		while ($p < $length) {
			$span = strcspn($source, self::FAST_DECL_UNSAFE_BYTES, $p, $length - $p);
			$stop = $p + $span;
			$commentPos = null;
			if ($span > 1) {
				$scanPos = $p;
				$scanLimit = $stop - 1;
				while ($scanPos < $scanLimit) {
					$slashPos = $scanPos + strcspn($source, '/', $scanPos, $scanLimit - $scanPos);
					if ($slashPos >= $scanLimit) {
						break;
					}
					if ($source[$slashPos + 1] === '*') {
						$commentPos = $slashPos;
						break;
					}
					$scanPos = $slashPos + 1;
				}
			}

			if ($commentPos !== null) {
				if (!self::validateCleanAsciiPreludeSegment($source, $p, $commentPos, $parenDepth)) {
					return null;
				}
				$commentEnd = strpos($source, '*/', $commentPos + 2);
				if ($commentEnd === false) {
					return null;
				}
				if ($checkControlBytes && CSSSyntaxScanner::hasControlCharactersInSlice($source, $commentPos + 2, $commentEnd - ($commentPos + 2))) {
					return null;
				}
				$hasComments = true;
				$p = $commentEnd + 2;
				continue;
			}

			if (!self::validateCleanAsciiPreludeSegment($source, $p, $stop, $parenDepth)) {
				return null;
			}
			if ($stop < $length && $source[$stop] === '{' && $parenDepth === 0) {
				return [
					'braceOffset' => $stop,
					'atKeywordEnd' => $atKeywordEnd,
					'hasComments' => $hasComments,
				];
			}

			return null;
		}

		return null;
	}

	private static function validateCleanAsciiPreludeSegment(string $source, int $start, int $end, int &$parenDepth): bool
	{
		$len = $end - $start;
		if ($len <= 0 || strcspn($source, '()-', $start, $len) === $len) {
			return true;
		}
		$seg = substr($source, $start, $len);
		if (str_contains($seg, '-->') || stripos($seg, 'url(') !== false) {
			return false;
		}
		if (!str_contains($seg, '(') && !str_contains($seg, ')')) {
			return true;
		}
		$pos = 0;
		while ($pos < $len) {
			$pos += strcspn($seg, '()', $pos, $len - $pos);
			if ($pos >= $len) {
				break;
			}
			if ($seg[$pos] === '(') {
				$parenDepth++;
			} else {
				$parenDepth--;
				if ($parenDepth < 0) {
					return false;
				}
			}
			$pos++;
		}

		return true;
	}

	private static function buildRawSelectorPrelude(
		string $source,
		int $start,
		int $end,
		bool $hasComments,
		bool $browserMode,
		?CSSStyleSheet $sheet = null,
	): ?string {
		$len = $end - $start;
		if ($len <= 0) {
			return '';
		}
		if (!$hasComments && CSSSyntaxScanner::hasControlCharactersInSlice($source, $start, $len)) {
			$sheet?->recordParseError('CSS rule prelude contains disallowed control characters.', $start);
			return null;
		}
		if ($hasComments) {
			$stripped = CSSSyntaxScanner::removeComments($source, $start, $end);
			if (CSSSyntaxScanner::hasControlCharacters($stripped)) {
				$sheet?->recordParseError('CSS rule prelude contains disallowed control characters.', $start);
				return null;
			}
		}

		$raw = substr($source, $start, $len);
		return str_contains($raw, '\\')
			? CSSSyntaxScanner::trimCssWhitespace($raw)
			: trim($raw, CSSSyntaxScanner::CSS_WHITESPACE);
	}

	private static function buildCleanPrelude(
		string $source,
		int $start,
		int $end,
		bool $hasComments,
		?CSSStyleSheet $sheet = null,
	): ?string {
		$len = $end - $start;
		if ($hasComments) {
			$stripped = CSSSyntaxScanner::removeComments($source, $start, $end);
			if (CSSSyntaxScanner::hasControlCharacters($stripped)) {
				$sheet?->recordParseError('CSS rule prelude contains disallowed control characters.', $start);
				return null;
			}
			return CSSSyntaxScanner::trimCssWhitespace($stripped);
		}
		if ($len > 0 && CSSSyntaxScanner::hasControlCharactersInSlice($source, $start, $len)) {
			$sheet?->recordParseError('CSS rule prelude contains disallowed control characters.', $start);
			return null;
		}

		$raw = substr($source, $start, $len);
		return str_contains($raw, '\\')
			? CSSSyntaxScanner::trimCssWhitespace($raw)
			: trim($raw, CSSSyntaxScanner::CSS_WHITESPACE);
	}

	private static function hasNonDefaultDeclLimits(CSSParserOptions $options): bool
	{
		return $options->maxBytes !== CSSParserOptions::DEFAULT_MAX_BYTES
			|| $options->maxDeclarations !== CSSParserOptions::DEFAULT_MAX_DECLARATIONS
			|| $options->maxDepth !== CSSParserOptions::DEFAULT_MAX_DEPTH
			|| $options->maxMathDepth !== CSSParserOptions::DEFAULT_MAX_MATH_DEPTH;
	}

	private static function assertNoSheetParseErrors(CSSStyleSheet $sheet): void
	{
		$errors = $sheet->getParseErrors();
		if ($errors !== []) {
			throw new InvalidArgumentException($errors[0]['message']);
		}
	}

	private static function parseAtRulePrelude(
		string $prelude,
		?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES,
		bool $browserMode = false,
	): CSSAtRule {
		[$name, $condition] = self::splitAtRulePrelude($prelude);
		return match ($name) {
			'charset' => new CSSCharsetRule($condition, $maxBytes),
			'custom-media' => new CSSCustomMediaRule($condition, $maxBytes, $browserMode),
			'import' => new CSSImportRule($condition, $maxBytes, $browserMode),
			'namespace' => new CSSNamespaceRule($condition, $maxBytes),
			'layer' => new CSSLayerStatementRule($condition, $maxBytes),
			default => new CSSAtRule($name, $condition, null, false, $maxBytes),
		};
	}

	private static function flushTrailingNestedDeclarations(
		CSSStyleDeclaration $currentStyle,
		?CSSGroupingRule $ownerRule,
		CSSStyleSheet $sheet,
		CSSParserOptions $options,
		int &$ruleCount,
		bool $throwOnLimit,
		bool $pageMode,
		bool $functionMode,
		bool $hasNestedRule,
		bool $isGroupingWithDecls,
		int $offset,
		bool $trackSpans = false,
	): void {
		if ($ownerRule === null || $pageMode || (!$hasNestedRule && !$isGroupingWithDecls) || count($currentStyle) === 0) {
			return;
		}
		if (!self::canRecordRule($options, $ruleCount, $sheet, $offset, $throwOnLimit)) {
			return;
		}
		try {
			$declarations = $functionMode && $currentStyle instanceof CSSFunctionDescriptors
				? new CSSFunctionDeclarations($currentStyle, $options->maxBytes)
				: new CSSNestedDeclarations($currentStyle, $options->maxBytes);
			if ($trackSpans) {
				$blockSpan = $currentStyle->getParsedBlockSpanInternal();
				if ($blockSpan !== null) {
					$declarations->setSourceSpanInternal($blockSpan);
				} else {
					$declSpans = $declarations->getStyle()->getSourceSpans();
					if ($declSpans !== []) {
						$minSpan = null;
						$maxSpan = null;
						foreach ($declSpans as $span) {
							if ($minSpan === null || $span->startOffset < $minSpan->startOffset) {
								$minSpan = $span;
							}
							if ($maxSpan === null || $span->endOffset > $maxSpan->endOffset) {
								$maxSpan = $span;
							}
						}
						if ($minSpan !== null && $maxSpan !== null) {
							$declarations->setSourceSpanInternal(new CSSSourceSpan(
								$minSpan->startOffset,
								$maxSpan->endOffset,
								$minSpan->startLine,
								$minSpan->startColumn,
								$maxSpan->endLine,
								$maxSpan->endColumn,
							));
						}
					}
				}
			}
			$ownerRule->getCssRules()->appendDetached($declarations);
			self::incrementCounter($ruleCount, 'CSS rule count');
		} catch (InvalidArgumentException $exception) {
			if ($throwOnLimit && $exception instanceof CSSParserLimitException) {
				throw $exception;
			}
			$sheet->recordParseError($exception->getMessage(), $offset);
		}
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

	private static function createValidatedOpaqueRule(
		string $raw,
		CSSStyleSheet $sheet,
		int $offset,
		?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES,
		bool $throwOnLimit = false,
		bool $browserMode = false,
	): ?CSSOpaqueRule {
		if ($browserMode && strcspn($raw, self::CONTROL_BYTES) !== strlen($raw)) {
			$sheet->recordParseError('CSS opaque rule contains disallowed control characters.', $offset);
			return null;
		}
		try {
			return CSSOpaqueRule::createParsedInternal($raw, $maxBytes);
		} catch (InvalidArgumentException $exception) {
			if ($throwOnLimit && $exception instanceof CSSParserLimitException) {
				throw $exception;
			}
			$sheet->recordParseError($exception->getMessage(), $offset);
			return null;
		}
	}

	/** @param list<array{message: string, offset: int}> $diagnostics */
	private static function flushDiagnostics(CSSStyleSheet $sheet, array &$diagnostics): void
	{
		foreach ($diagnostics as $error) {
			$sheet->recordParseError($error['message'], $error['offset']);
		}
		$diagnostics = [];
	}

	private static function nextDepth(int $depth): int
	{
		if ($depth >= PHP_INT_MAX) {
			throw new InvalidArgumentException('Nesting depth cannot be negative or overflow integer bounds.');
		}

		return $depth + 1;
	}

	private static function incrementCounter(int &$counter, string $label): void
	{
		if ($counter >= PHP_INT_MAX) {
			throw new InvalidArgumentException($label . ' overflows integer bounds.');
		}
		++$counter;
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
