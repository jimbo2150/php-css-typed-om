<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use LogicException;
use OutOfBoundsException;

/**
 * Standalone, mutable CSS stylesheet model.
 *
 * Serialization outputs normalized CSS and is not an HTML `<style>` sanitizer;
 * callers embedding serialized CSS into HTML must apply HTML-context escaping
 * (or enable `CSSRenderOptions::$escapeHtmlStyleTag` when embedding inside `<style>`).
 *
 * @property-read CSSRuleList $cssRules
 * @property-read string $cssText
 * @property-read list<array{message: string, offset: int}> $parseErrors
 * @property-read ?string $baseURL
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSStyleSheet
 */
final class CSSStyleSheet extends StyleSheet
{
	private CSSRuleList $cssRulesList;

	/** @var list<array{message: string, offset: int}> */
	private array $parseErrorsList = [];

	private readonly ?string $baseURLValue;

	private int $totalRuleCount = 0;

	private int $totalDeclarationCount = 0;

	private ?int $maxRules = CSSParserOptions::DEFAULT_MAX_RULES;

	private ?int $maxDepth = CSSParserOptions::DEFAULT_MAX_DEPTH;

	private ?int $maxDeclarations = CSSParserOptions::DEFAULT_MAX_DECLARATIONS;

	private ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES;

	private ?int $maxMathDepth = CSSParserOptions::DEFAULT_MAX_MATH_DEPTH;

	private ?int $maxErrors = CSSParserOptions::DEFAULT_MAX_ERRORS;

	private bool $browserErrorRecovery = false;

	private bool $trackSpans = false;

	private bool $errorsTruncated = false;

	private ?CSSParserOptions $cachedParserOptions = null;

	public function __construct(
		?string $href = null,
		string $title = '',
		MediaList|string|null $media = null,
		?CSSRule $ownerRule = null,
		?StyleSheet $parentStyleSheet = null,
		bool $disabled = false,
		?string $baseURL = null,
	) {
		if ($baseURL !== null && CSSSyntaxScanner::hasControlCharacters($baseURL)) {
			throw new InvalidArgumentException('CSSStyleSheet baseURL cannot contain control characters.');
		}
		parent::__construct($href, $title, $media, $ownerRule, $parentStyleSheet, $disabled);
		$this->baseURLValue = $baseURL;
		$this->cssRulesList = new CSSRuleList($this);
		$this->getMedia()->setOwnerInternal($this);
		$mediaLimit = $media instanceof MediaList
			? CSSRule::minLimitInternal($this->maxBytes, $media->getMaxBytesInternal())
			: $this->maxBytes;
		$this->getMedia()->setMaxBytesInternal($mediaLimit);
	}

	public function getBaseURL(): ?string
	{
		return $this->baseURLValue;
	}

	/**
	 * Parses CSS text into a stylesheet, recovering from malformed rules and recording diagnostics.
	 */
	public static function parse(string $cssText, ?CSSParserOptions $options = null): self
	{
		return CSSParser::parseStylesheet($cssText, $options);
	}

	public function getCssRules(): CSSRuleList
	{
		return $this->cssRulesList;
	}

	/** @return list<array{message: string, offset: int}> */
	public function getParseErrors(): array
	{
		return $this->parseErrorsList;
	}

	/**
	 * @internal
	 */
	public function configureErrorLimit(?int $maxErrors): void
	{
		$this->maxErrors = $maxErrors;
		$this->errorsTruncated = false;
		$this->cachedParserOptions = null;
	}

	/**
	 * @internal
	 */
	public function configureLimitsInternal(CSSParserOptions $options): void
	{
		$this->maxBytes = $options->maxBytes;
		$this->maxRules = $options->maxRules;
		$this->maxDepth = $options->maxDepth;
		$this->maxMathDepth = $options->maxMathDepth;
		$this->maxDeclarations = $options->maxDeclarations;
		$this->maxErrors = $options->maxErrors;
		$this->browserErrorRecovery = $options->browserErrorRecovery;
		$this->trackSpans = $options->trackSpans;
		$this->cachedParserOptions = null;
		$this->getMedia()->setMaxBytesInternal($options->maxBytes);
	}

	/**
	 * @internal Resolves explicit $override options or defaults to this stylesheet's configured resource limits.
	 */
	public function resolveParserOptionsInternal(?CSSParserOptions $override): CSSParserOptions
	{
		return $override ?? ($this->cachedParserOptions ??= new CSSParserOptions(
			maxBytes: $this->maxBytes,
			maxDepth: $this->maxDepth,
			maxMathDepth: $this->maxMathDepth,
			maxRules: $this->maxRules,
			maxDeclarations: $this->maxDeclarations,
			maxErrors: $this->maxErrors,
			browserErrorRecovery: $this->browserErrorRecovery,
			trackSpans: $this->trackSpans,
		));
	}

	/**
	 * @internal
	 */
	public function getMaxBytesInternal(): ?int
	{
		return $this->maxBytes;
	}

	/**
	 * @internal
	 */
	public function getMaxRulesInternal(): ?int
	{
		return $this->maxRules;
	}

	/**
	 * @internal
	 */
	public function getMaxDepthInternal(): ?int
	{
		return $this->maxDepth;
	}

	/**
	 * @internal
	 */
	public function getMaxDeclarationsInternal(): ?int
	{
		return $this->maxDeclarations;
	}

	/**
	 * @internal
	 */
	public function getMaxMathDepthInternal(): ?int
	{
		return $this->maxMathDepth;
	}

	/**
	 * @internal
	 */
	public function getMaxErrorsInternal(): ?int
	{
		return $this->maxErrors;
	}

	/**
	 * @internal
	 */
	public function getTotalRuleCount(): int
	{
		return $this->totalRuleCount;
	}

	/**
	 * @internal
	 */
	public function adjustTotalRuleCount(int $delta): void
	{
		$this->totalRuleCount = max(0, $this->totalRuleCount + $delta);
	}

	/**
	 * @internal
	 */
	public function getTotalDeclarationCount(): int
	{
		return $this->totalDeclarationCount;
	}

	/**
	 * @internal
	 */
	public function adjustTotalDeclarationCount(int $delta): void
	{
		$this->totalDeclarationCount = max(0, $this->totalDeclarationCount + $delta);
	}

	public function getCssText(): string
	{
		$rules = $this->cssRulesList->getRulesRaw();
		if ($rules === []) {
			return '';
		}

		$lines = [];
		$visited = [];
		foreach ($rules as $rule) {
			$id = spl_object_id($rule);
			if (isset($visited[$id])) {
				throw new LogicException('Cannot serialize a cyclic or DAG-shared CSS rule tree.');
			}
			if ($rule instanceof CSSGroupingRule && $rule->getCssRules()->getRulesRaw() !== [] && CSSGroupingRule::usesCoreSerialization($rule)) {
				$lines[] = CSSGroupingRule::serializeTreeIteratively($rule, $visited);
			} else {
				$visited[$id] = true;
				$lines[] = $rule->getCssText();
			}
		}

		return implode("\n", $lines);
	}

	/**
	 * Validates and inserts a single CSS rule at $index without mutating on failure.
	 *
	 * @throws OutOfBoundsException When $index is out of bounds
	 * @throws InvalidArgumentException When $ruleText is malformed, violates CSSOM rule hierarchy, or exceeds parser limits
	 */
	public function insertRule(string $ruleText, int $index = 0, ?CSSParserOptions $options = null): int
	{
		if ($index < 0 || $index > count($this->cssRulesList)) {
			throw new OutOfBoundsException('Rule index is outside the CSSRuleList.');
		}

		$effectiveOptions = $this->resolveParserOptionsInternal($options);
		$rule = CSSParser::parseSingleRule(
			$ruleText,
			$effectiveOptions,
			0,
			$this->totalRuleCount,
			$this->totalDeclarationCount,
		);
		$this->assertValidInsertHierarchyInternal($rule, $index);
		$this->cssRulesList->insertWithOptionsInternal($index, $rule, $effectiveOptions);

		return $index;
	}

	/**
	 * @internal Validates CSSOM §6.4.1 top-level rule insertion hierarchy before mutating the stylesheet.
	 *
	 * @throws InvalidArgumentException When the rule type or position violates CSSOM rule ordering
	 */
	public function assertValidInsertHierarchyInternal(CSSRule $rule, int $index): void
	{
		if ($rule instanceof CSSCharsetRule) {
			throw new InvalidArgumentException('@charset rules cannot be inserted via insertRule().');
		}
		if ($rule instanceof CSSStyleRule && $rule->hasLeadingCombinatorInternal()) {
			throw new InvalidArgumentException('Top-level style rules cannot start with a relative combinator.');
		}

		$rules = $this->cssRulesList->getRulesRaw();
		$count = count($rules);

		if ($rule instanceof CSSImportRule) {
			for ($i = 0; $i < $index; $i++) {
				$existing = $rules[$i];
				if (!$existing instanceof CSSCharsetRule
					&& !$existing instanceof CSSImportRule
					&& !$existing instanceof CSSLayerStatementRule
				) {
					throw new InvalidArgumentException('@import rule cannot be inserted after non-import rules.');
				}
			}
			for ($i = $index; $i < $count; $i++) {
				if ($rules[$i] instanceof CSSCharsetRule) {
					throw new InvalidArgumentException('@import rule cannot be inserted before @charset.');
				}
			}
			return;
		}

		if ($rule instanceof CSSNamespaceRule) {
			foreach ($rules as $existing) {
				if (!$existing instanceof CSSCharsetRule
					&& !$existing instanceof CSSImportRule
					&& !$existing instanceof CSSNamespaceRule
					&& !$existing instanceof CSSLayerStatementRule
				) {
					throw new InvalidArgumentException('@namespace rule cannot be inserted when stylesheet contains non-prelude rules.');
				}
			}
			for ($i = $index; $i < $count; $i++) {
				$existing = $rules[$i];
				if ($existing instanceof CSSCharsetRule || $existing instanceof CSSImportRule) {
					throw new InvalidArgumentException('@namespace rule cannot be inserted before @charset or @import.');
				}
			}
			return;
		}

		if ($rule instanceof CSSLayerStatementRule) {
			for ($i = $index; $i < $count; $i++) {
				if ($rules[$i] instanceof CSSCharsetRule) {
					throw new InvalidArgumentException('@layer statement rule cannot be inserted before @charset.');
				}
			}
			return;
		}

		for ($i = $index; $i < $count; $i++) {
			$existing = $rules[$i];
			if ($existing instanceof CSSCharsetRule
				|| $existing instanceof CSSImportRule
				|| $existing instanceof CSSNamespaceRule
			) {
				throw new InvalidArgumentException('CSS rule cannot be inserted before @charset, @import, or @namespace.');
			}
		}
	}

	/**
	 * @internal Validates CSSOM §6.4.2 rule deletion constraints before removing from the stylesheet.
	 *
	 * @throws OutOfBoundsException When $index is out of bounds
	 * @throws LogicException When deleting an @namespace rule while non-prelude rules exist
	 */
	public function assertValidDeleteHierarchyInternal(int $index): void
	{
		$rules = $this->cssRulesList->getRulesRaw();
		if (!isset($rules[$index])) {
			throw new OutOfBoundsException('Rule index is outside the CSSRuleList.');
		}
		if (!$rules[$index] instanceof CSSNamespaceRule) {
			return;
		}
		foreach ($rules as $existing) {
			if (!$existing instanceof CSSCharsetRule
				&& !$existing instanceof CSSImportRule
				&& !$existing instanceof CSSNamespaceRule
				&& !$existing instanceof CSSLayerStatementRule
			) {
				throw new LogicException('@namespace rule cannot be deleted when stylesheet contains non-prelude rules.');
			}
		}
	}

	/**
	 * Deletes the rule at $index.
	 *
	 * @throws OutOfBoundsException When $index is out of bounds
	 * @throws LogicException When deleting an @namespace rule while non-prelude rules exist
	 */
	public function deleteRule(int $index = 0): void
	{
		$this->assertValidDeleteHierarchyInternal($index);
		$this->cssRulesList->remove($index);
	}

	/**
	 * Replaces stylesheet rules with the parsed contents of $cssText.
	 *
	 * @throws InvalidArgumentException When $cssText exceeds configured parser resource limits
	 */
	public function replaceSync(string $cssText, ?CSSParserOptions $options = null): void
	{
		$effectiveOptions = $this->resolveParserOptionsInternal($options);
		$replacement = CSSParser::parseStylesheet($cssText, $effectiveOptions, true);
		$this->cssRulesList->replaceAllWithOptionsInternal($replacement->getCssRules(), $effectiveOptions, $options !== null);
		$this->parseErrorsList = $replacement->getParseErrors();
		$this->errorsTruncated = $replacement->errorsTruncated;
	}

	public function recordParseError(string $message, int $offset): void
	{
		if ($this->maxErrors !== null && count($this->parseErrorsList) >= $this->maxErrors) {
			if (!$this->errorsTruncated) {
				$this->errorsTruncated = true;
				$this->parseErrorsList[] = [
					'message' => sprintf('Maximum parse error limit of %d exceeded; further diagnostics suppressed.', $this->maxErrors),
					'offset' => $offset,
				];
			}

			return;
		}

		$this->parseErrorsList[] = ['message' => $message, 'offset' => $offset];
	}

	public function __toString(): string
	{
		return $this->getCssText();
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'cssRules' => $this->getCssRules(),
			'cssText' => $this->getCssText(),
			'parseErrors' => $this->getParseErrors(),
			'baseURL' => $this->getBaseURL(),
			default => parent::__get($name),
		};
	}

	public function __isset(string $name): bool
	{
		return match ($name) {
			'cssRules', 'cssText', 'parseErrors' => true,
			'baseURL' => $this->baseURLValue !== null,
			default => parent::__isset($name),
		};
	}

	public function __clone(): void
	{
		parent::__clone();
		$this->cachedParserOptions = null;
		$this->getMedia()->setOwnerInternal($this);
		$origRules = $this->cssRulesList->getRulesRaw();
		$this->cssRulesList = new CSSRuleList($this);
		$this->totalRuleCount = 0;
		$this->totalDeclarationCount = 0;
		if ($origRules !== []) {
			CSSGroupingRule::cloneRulesIntoList($origRules, $this->cssRulesList, $this, null);
		}
	}
}
