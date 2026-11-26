<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use LogicException;
use OutOfBoundsException;

/**
 * Base class for CSS rules that contain a nested CSSRuleList.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSGroupingRule
 */
/**
 * @property-read CSSRuleList $cssRules
 */
class CSSGroupingRule extends CSSRule
{
	protected string $ruleName;
	protected string $prelude;
	private CSSRuleList $cssRulesList;
	private static bool $inIterativeClone = false;

	/**
	 * @throws InvalidArgumentException When $name or $prelude contains invalid syntax
	 */
	public function __construct(
		string $name,
		string $prelude = '',
		?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES,
	) {
		self::assertValidMaxBytesInternal($maxBytes);
		if ($maxBytes !== null && (strlen($name) > $maxBytes || strlen($prelude) > $maxBytes)) {
			throw new CSSParserLimitException(sprintf('CSS grouping rule exceeds maximum size of %d bytes.', $maxBytes));
		}
		$this->maxBytes = $maxBytes;
		$trimmedName = CSSSyntaxScanner::trimCssWhitespace($name);
		if (!preg_match('/^-?[a-zA-Z_][a-zA-Z0-9_-]*$/D', $trimmedName)) {
			throw new InvalidArgumentException('Invalid CSS grouping rule name: ' . $name);
		}
		$this->ruleName = $trimmedName;

		$this->prelude = CSSSyntaxScanner::trimCssWhitespace($prelude);
		if ($this->prelude !== '') {
			CSSSyntaxScanner::assertValidFragment($this->prelude, 'CSS rule prelude', [';', '{', '}']);
		}

		$this->cssRulesList = new CSSRuleList(null, $this);
	}

	/** @internal Initializes already-validated rule names and preludes without regex or fragment rescanning. */
	protected function initGroupingRuleInternal(
		string $ruleName,
		string $prelude = '',
		?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES,
	): void {
		self::assertValidMaxBytesInternal($maxBytes);
		if ($maxBytes !== null && (strlen($ruleName) > $maxBytes || strlen($prelude) > $maxBytes)) {
			throw new CSSParserLimitException(sprintf('CSS grouping rule exceeds maximum size of %d bytes.', $maxBytes));
		}
		$this->maxBytes = $maxBytes;
		$this->ruleName = $ruleName;
		$this->prelude = $prelude;
		$this->cssRulesList = new CSSRuleList(null, $this);
	}

	public function getType(): int
	{
		return self::UNKNOWN_RULE;
	}

	public function getName(): string
	{
		return $this->ruleName;
	}

	public function getPrelude(): string
	{
		return $this->prelude;
	}

	public function getCssRules(): CSSRuleList
	{
		return $this->cssRulesList;
	}

	/**
	 * Validates and inserts a rule into this grouping rule's list atomically.
	 *
	 * @throws LogicException When the grouping rule is detached from a stylesheet
	 * @throws OutOfBoundsException When $index is outside [0, count]
	 * @throws InvalidArgumentException When $ruleText is malformed or exceeds parser limits
	 */
	public function insertRule(string $ruleText, int $index = 0, ?CSSParserOptions $options = null): int
	{
		$sheet = $this->getParentStyleSheet();
		if ($sheet === null) {
			throw new LogicException($this->detachedInsertErrorMessage());
		}
		if ($index < 0 || $index > count($this->cssRulesList)) {
			throw new OutOfBoundsException('Rule index is outside the CSSRuleList.');
		}

		$effectiveOptions = $sheet->resolveParserOptionsInternal($options);
		$rule = $this->parseChildRuleForInsert($ruleText, $effectiveOptions, $sheet);
		$this->cssRulesList->insertWithOptionsInternal($index, $rule, $effectiveOptions);

		return $index;
	}

	protected function detachedInsertErrorMessage(): string
	{
		return 'A detached grouping rule cannot insert rules.';
	}

	protected function parseChildRuleForInsert(
		string $ruleText,
		CSSParserOptions $effectiveOptions,
		CSSStyleSheet $sheet,
	): CSSRule {
		[$useNested, $functionMode] = $this->resolveNestedInsertMode();

		return $useNested
			? CSSParser::parseSingleNestedRule(
				$ruleText,
				$effectiveOptions,
				$this->getNestingDepth(),
				$sheet->getTotalRuleCount(),
				$sheet->getTotalDeclarationCount(),
				$functionMode,
			)
			: CSSParser::parseSingleRule(
				$ruleText,
				$effectiveOptions,
				$this->getNestingDepth(),
				$sheet->getTotalRuleCount(),
				$sheet->getTotalDeclarationCount(),
			);
	}

	/**
	 * @internal Rejects top-level-only at-rules from being inserted into nested grouping rules.
	 *
	 * @throws InvalidArgumentException When $rule is restricted to stylesheet top-level
	 */
	public static function assertValidChildRuleInternal(CSSRule $rule): void
	{
		if ($rule instanceof CSSCharsetRule
			|| $rule instanceof CSSImportRule
			|| $rule instanceof CSSNamespaceRule
			|| $rule instanceof CSSCustomMediaRule
		) {
			$ruleKind = match (true) {
				$rule instanceof CSSCharsetRule => '@charset',
				$rule instanceof CSSImportRule => '@import',
				$rule instanceof CSSNamespaceRule => '@namespace',
				default => '@custom-media',
			};
			throw new InvalidArgumentException(sprintf('%s rules cannot be inserted inside a grouping rule.', $ruleKind));
		}
	}

	/**
	 * Deletes the rule at $index.
	 *
	 * @throws LogicException When this grouping rule is detached from a stylesheet
	 * @throws OutOfBoundsException When $index is invalid
	 */
	public function deleteRule(int $index): void
	{
		if ($this->getParentStyleSheet() === null) {
			throw new LogicException('A detached grouping rule cannot delete rules.');
		}
		$this->cssRulesList->remove($index);
	}

	public function getCssText(): string
	{
		if ($this->cssRulesList->getRulesRaw() === [] && self::usesCoreSerialization($this)) {
			$prelude = $this->prelude === '' ? '' : ' ' . $this->prelude;
			return '@' . $this->ruleName . $prelude . ' {  }';
		}

		return self::serializeTreeIteratively($this);
	}

	/**
	 * @internal Iteratively serializes a rule subtree while enforcing CSSRenderer::MAX_RENDER_DEPTH and rejecting DAG/cyclic graphs.
	 * @param array<int, true> $visited
	 */
	public static function serializeTreeIteratively(self $root, array &$visited = []): string
	{
		$resultsStack = [];
		$workStack = [[0, $root, 0]];

		while ($workStack !== []) {
			[$phase, $node, $depth] = array_pop($workStack);
			if ($phase === 0) {
				$nodeId = spl_object_id($node);
				if (isset($visited[$nodeId])) {
					throw new LogicException('Cannot serialize a cyclic or DAG-shared CSS rule tree.');
				}
				$visited[$nodeId] = true;
				if ($depth > CSSRenderer::MAX_RENDER_DEPTH) {
					throw new CSSParserLimitException(sprintf('Maximum CSS serialization depth of %d exceeded.', CSSRenderer::MAX_RENDER_DEPTH));
				}
				$children = $node->getCssRules()->getRulesRaw();
				$childCount = count($children);
				if ($childCount === 0) {
					$resultsStack[] = self::formatGroupingNode($node, null, []);
					continue;
				}
				$workStack[] = [1, $node, $childCount];
				for ($i = $childCount - 1; $i >= 0; $i--) {
					$child = $children[$i];
					if ($child instanceof self && self::usesCoreSerialization($child)) {
						$workStack[] = [0, $child, $depth + 1];
					} elseif ($depth + 1 > CSSRenderer::MAX_RENDER_DEPTH) {
						throw new CSSParserLimitException(sprintf('Maximum CSS serialization depth of %d exceeded.', CSSRenderer::MAX_RENDER_DEPTH));
					} else {
						$workStack[] = [2, $child, 0];
					}
				}
				continue;
			}

			if ($phase === 2) {
				$nodeId = spl_object_id($node);
				if (isset($visited[$nodeId])) {
					throw new LogicException('Cannot serialize a cyclic or DAG-shared CSS rule tree.');
				}
				$visited[$nodeId] = true;
				$resultsStack[] = $node->getCssText();
				continue;
			}

			$childCount = $depth;
			$childTexts = $childCount === 1 ? [array_pop($resultsStack)] : array_splice($resultsStack, -$childCount);
			$resultsStack[] = self::formatGroupingNode($node, $childTexts, $childTexts);
		}

		return $resultsStack[0] ?? '';
	}

	/**
	 * @param ?list<string> $childTexts
	 * @param list<string> $childBlocks
	 */
	private static function formatGroupingNode(self $node, ?array $childTexts, array $childBlocks): string
	{
		if ($node instanceof CSSStyleRule) {
			if ($childTexts === null) {
				return $node->getSelectorText() . ' { ' . $node->getStyle()->getCssText() . ' }';
			}
			$parts = [];
			$decls = $node->getStyle()->getCssText();
			if ($decls !== '') {
				$parts[] = $decls;
			}
			foreach ($childTexts as $text) {
				$parts[] = $text;
			}
			return $node->getSelectorText() . ' { ' . implode(' ', $parts) . ' }';
		}

		if ($node instanceof CSSPageRule) {
			$prelude = $node->getPrelude() === '' ? '' : ' ' . $node->getPrelude();
			if ($childTexts === null) {
				return '@page' . $prelude . ' { ' . $node->getStyle()->getCssText() . ' }';
			}
			$parts = [];
			$decls = $node->getStyle()->getCssText();
			if ($decls !== '') {
				$parts[] = $decls;
			}
			foreach ($childTexts as $text) {
				$parts[] = $text;
			}
			return '@page' . $prelude . ' { ' . implode(' ', $parts) . ' }';
		}

		if ($node instanceof CSSFontFeatureValuesRule) {
			$blocks = [];
			foreach ($node->getFeatureMaps() as $category => $map) {
				if (count($map) === 0) {
					continue;
				}
				$declarations = [];
				foreach ($map->getEntriesRaw() as $name => $values) {
					$declarations[] = $name . ': ' . implode(' ', $values) . ';';
				}
				$blocks[] = '@' . $category . ' { ' . implode(' ', $declarations) . ' }';
			}
			foreach ($childBlocks as $text) {
				$blocks[] = $text;
			}
			return '@font-feature-values ' . $node->getFontFamily() . ' { ' . implode(' ', $blocks) . ' }';
		}

		$prelude = $node->getPrelude() === '' ? '' : ' ' . $node->getPrelude();
		if ($childTexts === null) {
			return '@' . $node->getName() . $prelude . ' {  }';
		}

		return '@' . $node->getName() . $prelude . ' { ' . implode("\n", $childTexts) . ' }';
	}

	/** @internal */
	public static function usesCoreSerialization(self $rule): bool
	{
		return match ($rule::class) {
			self::class,
			CSSMediaRule::class,
			CSSSupportsRule::class,
			CSSContainerRule::class,
			CSSScopeRule::class,
			CSSLayerBlockRule::class,
			CSSStartingStyleRule::class,
			CSSFunctionRule::class,
			CSSKeyframesRule::class,
			CSSStyleRule::class,
			CSSPageRule::class,
			CSSFontFeatureValuesRule::class => true,
			default => false,
		};
	}

	public function attach(?CSSStyleSheet $sheet, ?CSSGroupingRule $parentRule): void
	{
		$this->setParentLinks($sheet, $parentRule);
		if ($this->cssRulesList->getSheetInternal() !== $sheet) {
			$this->cssRulesList->reparent($sheet);
		}
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'cssRules' => $this->getCssRules(),
			default => parent::__get($name),
		};
	}

	public function __isset(string $name): bool
	{
		return $name === 'cssRules' || parent::__isset($name);
	}

	public function __clone(): void
	{
		parent::__clone();
		$origRules = $this->cssRulesList->getRulesRaw();
		$this->cssRulesList = new CSSRuleList(null, $this);
		if (!self::$inIterativeClone && $origRules !== []) {
			self::cloneRulesIntoList($origRules, $this->cssRulesList, null, $this);
		}
	}

	/**
	 * @internal Iteratively clones source rules into $targetList without recursion (Xdebug-safe).
	 * @param list<CSSRule> $sourceRules
	 */
	public static function cloneRulesIntoList(
		array $sourceRules,
		CSSRuleList $targetList,
		?CSSStyleSheet $targetSheet,
		?self $targetOwner,
	): void {
		$wasCloning = self::$inIterativeClone;
		self::$inIterativeClone = true;
		$totalAdded = 0;
		$totalDeclsAdded = 0;
		$strictPreludeAdded = 0;
		$visited = [];
		try {
			/** @var list<array{CSSRule, CSSRuleList, ?self}> $stack */
			$stack = [];
			for ($i = count($sourceRules) - 1; $i >= 0; $i--) {
				$stack[] = [$sourceRules[$i], $targetList, $targetOwner];
			}

			while ($stack !== []) {
				[$src, $destList, $destOwner] = array_pop($stack);
				$srcId = spl_object_id($src);
				if (isset($visited[$srcId])) {
					throw new LogicException('Cannot clone a cyclic CSS rule tree.');
				}
				$visited[$srcId] = true;

				$srcChildren = $src instanceof self ? $src->getCssRules()->getRulesRaw() : [];
				$cloned = clone $src;
				$cloned->setParentLinks($targetSheet, $destOwner);
				$destList->appendClonedInternal($cloned);
				$totalAdded++;
				if ($destOwner === null && $targetSheet !== null && ($cloned instanceof CSSCharsetRule || $cloned instanceof CSSImportRule || $cloned instanceof CSSNamespaceRule)) {
					$strictPreludeAdded++;
				}
				$ownedStyle = $cloned->getOwnedStyleInternal();
				if ($ownedStyle !== null) {
					$totalDeclsAdded += count($ownedStyle->getDeclarationsRaw());
				} elseif ($cloned instanceof CSSFontFeatureValuesRule) {
					foreach ($cloned->getFeatureMaps() as $map) {
						$totalDeclsAdded += count($map);
					}
				}

				if ($cloned instanceof self) {
					$childDestList = $cloned->getCssRules();
					$childDestList->setSheetInternal($targetSheet);
					if ($srcChildren !== []) {
						for ($i = count($srcChildren) - 1; $i >= 0; $i--) {
							$stack[] = [$srcChildren[$i], $childDestList, $cloned];
						}
					}
				}
			}
		} finally {
			self::$inIterativeClone = $wasCloning;
		}
		$targetSheet?->adjustTotalRuleCount($totalAdded);
		$targetSheet?->adjustTotalDeclarationCount($totalDeclsAdded);
		if ($strictPreludeAdded > 0) {
			$targetSheet?->adjustStrictPreludeCountInternal($strictPreludeAdded);
		}
	}

	/** @internal */
	public function getNestingDepthInternal(): int
	{
		return $this->getNestingDepth();
	}

	protected function getNestingDepth(): int
	{
		$depth = 1;
		$parent = $this->getParentRule();
		$visited = [];
		while ($parent !== null) {
			$id = spl_object_id($parent);
			if (isset($visited[$id])) {
				throw new LogicException('Detected a cycle in CSSGroupingRule ancestor chain.');
			}
			$visited[$id] = true;
			$depth++;
			$parent = $parent->getParentRule();
		}

		return $depth;
	}

	/** @return array{0: bool, 1: bool} [$useNested, $functionMode] */
	private function resolveNestedInsertMode(): array
	{
		if ($this instanceof CSSFunctionRule) {
			return [true, true];
		}

		$parent = $this->getParentRule();
		$visited = [];
		while ($parent !== null) {
			if ($parent instanceof CSSFunctionRule) {
				return [true, true];
			}
			if ($parent instanceof CSSStyleRule) {
				return [true, false];
			}
			$id = spl_object_id($parent);
			if (isset($visited[$id])) {
				return [false, false];
			}
			$visited[$id] = true;
			$parent = $parent->getParentRule();
		}

		return [false, false];
	}
}
