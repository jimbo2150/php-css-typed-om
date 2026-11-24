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

	private ?int $maxErrors = null;

	private bool $errorsTruncated = false;

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
	}

	/**
	 * @internal Resolves explicit $override options or defaults to this stylesheet's configured resource limits.
	 */
	public function resolveParserOptionsInternal(?CSSParserOptions $override): CSSParserOptions
	{
		return $override ?? new CSSParserOptions(
			maxBytes: $this->maxBytes,
			maxDepth: $this->maxDepth,
			maxMathDepth: $this->maxMathDepth,
			maxRules: $this->maxRules,
			maxDeclarations: $this->maxDeclarations,
			maxErrors: $this->maxErrors,
		);
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
	 * @throws InvalidArgumentException When $ruleText is malformed or exceeds parser limits
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
		$this->cssRulesList->insertWithOptionsInternal($index, $rule, $effectiveOptions);

		return $index;
	}

	/**
	 * Deletes the rule at $index.
	 *
	 * @throws OutOfBoundsException When $index is out of bounds
	 */
	public function deleteRule(int $index = 0): void
	{
		if ($index < 0 || $index >= count($this->cssRulesList)) {
			throw new OutOfBoundsException('Rule index is outside the CSSRuleList.');
		}
		$this->cssRulesList->remove($index);
	}

	/**
	 * Replaces stylesheet rules with the parsed contents of $cssText.
	 *
	 * @throws InvalidArgumentException When $cssText exceeds configured parser resource limits
	 */
	public function replaceSync(string $cssText, ?CSSParserOptions $options = null): void
	{
		$options ??= CSSParserOptions::default();
		$replacement = CSSParser::parseStylesheet($cssText, $options, true);
		$this->cssRulesList->replaceAllWithOptionsInternal($replacement->getCssRules(), $options);
		$this->configureLimitsInternal($options);
		$this->configureErrorLimit($options->maxErrors);
		$this->parseErrorsList = $replacement->getParseErrors();
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
		$origRules = $this->cssRulesList->getRulesRaw();
		$this->cssRulesList = new CSSRuleList($this);
		$this->totalRuleCount = 0;
		$this->totalDeclarationCount = 0;
		if ($origRules !== []) {
			CSSGroupingRule::cloneRulesIntoList($origRules, $this->cssRulesList, $this, null);
		}
	}
}
