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
class CSSGroupingRule extends CSSRule
{
	protected readonly string $name;
	protected string $prelude;
	private CSSRuleList $cssRules;
	private static bool $inIterativeClone = false;

	/**
	 * @throws InvalidArgumentException When $name or $prelude contains invalid syntax
	 */
	public function __construct(
		string $name,
		string $prelude = '',
	) {
		$trimmedName = CSSSyntaxScanner::trimCssWhitespace($name);
		if (!preg_match('/^-?[a-zA-Z_][a-zA-Z0-9_-]*$/D', $trimmedName)) {
			throw new InvalidArgumentException('Invalid CSS grouping rule name: ' . $name);
		}
		$this->name = $trimmedName;

		$this->prelude = CSSSyntaxScanner::trimCssWhitespace($prelude);
		if ($this->prelude !== '') {
			CSSSyntaxScanner::assertValidFragment($this->prelude, 'CSS rule prelude', [';', '{', '}']);
		}

		$this->cssRules = new CSSRuleList(null, $this);
	}

	public function getType(): int
	{
		return self::UNKNOWN_RULE;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function getPrelude(): string
	{
		return $this->prelude;
	}

	public function getCssRules(): CSSRuleList
	{
		return $this->cssRules;
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
			throw new LogicException('A detached grouping rule cannot insert rules.');
		}
		if ($index < 0 || $index > count($this->cssRules)) {
			throw new OutOfBoundsException('Rule index is outside the CSSRuleList.');
		}

		$rule = CSSParser::parseSingleRule(
			$ruleText,
			$options,
			$this->getNestingDepth(),
			$sheet->getTotalRuleCount(),
		);
		$this->cssRules->insert($index, $rule);

		return $index;
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
		$this->cssRules->remove($index);
	}

	public function getCssText(): string
	{
		if ($this->cssRules->getRulesRaw() === [] && self::usesCoreSerialization($this)) {
			$prelude = $this->prelude === '' ? '' : ' ' . $this->prelude;
			return '@' . $this->name . $prelude . ' {  }';
		}

		return self::serializeTreeIteratively($this);
	}

	/**
	 * @internal Iteratively serializes a rule subtree while enforcing CSSRenderer::MAX_RENDER_DEPTH.
	 */
	public static function serializeTreeIteratively(self $root): string
	{
		$resultsStack = [];
		$workStack = [[0, $root, 0]];

		while ($workStack !== []) {
			[$phase, $node, $depth] = array_pop($workStack);
			if ($phase === 0) {
				if ($depth > CSSRenderer::MAX_RENDER_DEPTH) {
					throw new CSSParserLimitException(sprintf('Maximum CSS serialization depth of %d exceeded.', CSSRenderer::MAX_RENDER_DEPTH));
				}
				$children = $node->getCssRules()->getRulesRaw();
				$childCount = count($children);
				if ($childCount === 0) {
					if ($node instanceof CSSStyleRule) {
						$resultsStack[] = $node->getSelectorText() . ' { ' . $node->getStyle()->getCssText() . ' }';
					} elseif ($node instanceof CSSPageRule) {
						$prelude = $node->getPrelude() === '' ? '' : ' ' . $node->getPrelude();
						$resultsStack[] = '@page' . $prelude . ' { ' . $node->getStyle()->getCssText() . ' }';
					} elseif ($node instanceof CSSFontFeatureValuesRule) {
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
						$resultsStack[] = '@font-feature-values ' . $node->getFontFamily() . ' { ' . implode(' ', $blocks) . ' }';
					} else {
						$prelude = $node->getPrelude() === '' ? '' : ' ' . $node->getPrelude();
						$resultsStack[] = '@' . $node->getName() . $prelude . ' {  }';
					}
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
				$resultsStack[] = $node->getCssText();
				continue;
			}

			$childCount = $depth;
			$childTexts = $childCount === 1 ? [array_pop($resultsStack)] : array_splice($resultsStack, -$childCount);
			if ($node instanceof CSSStyleRule) {
				$parts = [];
				$decls = $node->getStyle()->getCssText();
				if ($decls !== '') {
					$parts[] = $decls;
				}
				foreach ($childTexts as $text) {
					$parts[] = $text;
				}
				$resultsStack[] = $node->getSelectorText() . ' { ' . implode(' ', $parts) . ' }';
			} elseif ($node instanceof CSSPageRule) {
				$parts = [];
				$decls = $node->getStyle()->getCssText();
				if ($decls !== '') {
					$parts[] = $decls;
				}
				foreach ($childTexts as $text) {
					$parts[] = $text;
				}
				$prelude = $node->getPrelude() === '' ? '' : ' ' . $node->getPrelude();
				$resultsStack[] = '@page' . $prelude . ' { ' . implode(' ', $parts) . ' }';
			} elseif ($node instanceof CSSFontFeatureValuesRule) {
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
				foreach ($childTexts as $text) {
					$blocks[] = $text;
				}
				$resultsStack[] = '@font-feature-values ' . $node->getFontFamily() . ' { ' . implode(' ', $blocks) . ' }';
			} else {
				$prelude = $node->getPrelude() === '' ? '' : ' ' . $node->getPrelude();
				$resultsStack[] = '@' . $node->getName() . $prelude . ' { ' . implode("\n", $childTexts) . ' }';
			}
		}

		return $resultsStack[0] ?? '';
	}

	private static function usesCoreSerialization(self $rule): bool
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

	public function initializeRules(CSSStyleSheet $sheet): void
	{
		$this->attach($sheet, $this->getParentRule());
	}

	public function attach(?CSSStyleSheet $sheet, ?CSSGroupingRule $parentRule): void
	{
		$this->setParentLinks($sheet, $parentRule);
		if ($this->cssRules->getSheetInternal() !== $sheet) {
			$this->cssRules->reparent($sheet);
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
		$origRules = $this->cssRules->getRulesRaw();
		$this->cssRules = new CSSRuleList(null, $this);
		if (!self::$inIterativeClone && $origRules !== []) {
			self::cloneRulesIntoList($origRules, $this->cssRules, null, $this);
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

				if ($cloned instanceof self && $srcChildren !== []) {
					$childDestList = $cloned->getCssRules();
					$childDestList->setSheetInternal($targetSheet);
					for ($i = count($srcChildren) - 1; $i >= 0; $i--) {
						$stack[] = [$srcChildren[$i], $childDestList, $cloned];
					}
				}
			}
		} finally {
			self::$inIterativeClone = $wasCloning;
		}
		$targetSheet?->adjustTotalRuleCount($totalAdded);
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
}
