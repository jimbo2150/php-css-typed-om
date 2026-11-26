<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use ArrayAccess;
use ArrayIterator;
use Countable;
use Error;
use InvalidArgumentException;
use IteratorAggregate;
use Jimbo2150\PhpCssTypedOm\CSS;
use Jimbo2150\PhpCssTypedOm\CSSOM\Shorthand\CSSShorthandExpander;
use Jimbo2150\PhpCssTypedOm\CSSOM\Shorthand\CSSShorthandRegistry;
use Jimbo2150\PhpCssTypedOm\CSSOM\Shorthand\CSSShorthandSerializer;
use Jimbo2150\PhpCssTypedOm\Grammar\CSSPropertyGrammarRegistry;
use Jimbo2150\PhpCssTypedOm\Grammar\CSSValueComponentParser;
use Jimbo2150\PhpCssTypedOm\Grammar\CSSValueValidator;
use Jimbo2150\PhpCssTypedOm\Parser\CSSInputPreprocessor;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSourceLocationIndex;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Jimbo2150\PhpCssTypedOm\Parser\CSSTokenizer;
use Jimbo2150\PhpCssTypedOm\Parser\CSSTokenType;
use Traversable;

/**
 * Mutable, ordered CSS declaration block.
 *
 * Explicit setters validate against structural delimiters (`;`, `{`, `}`, unbalanced quotes/comments)
 * and leave state untouched on failure.
 *
 * @phpstan-consistent-constructor
 *
 * @property string $cssText
 * @property-read int $length
 * @property-read ?CSSRule $parentRule
 *
 * @implements ArrayAccess<string, string>
 * @implements IteratorAggregate<string, array{value: string, priority: string}>
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSStyleDeclaration
 */
class CSSStyleDeclaration implements ArrayAccess, Countable, IteratorAggregate
{
	private const string LOWER_ASCII_NAME_CHARS = 'abcdefghijklmnopqrstuvwxyz0123456789_-';
	private const string FAST_PATH_UNSAFE_BYTES = "\\\"'[]{}<;\r\f" . CSSSyntaxScanner::CONTROL_BYTES;

	/** @var array<string, array{value: string, priority: string}> */
	private array $declarations = [];

	/** @var array<string, CSSSourceSpan> */
	private array $sourceSpans = [];

	private ?CSSSourceSpan $parsedBlockSpan = null;

	/** @var array<string, array{shorthand: string, value: string, id: int, kind: 'substitution'|'system-font'}> */
	private array $pendingShorthands = [];

	/** @var array<int, array{bytes: int, refs: int}> */
	private array $pendingPayloadAccounting = [];

	private int $nextPendingId = 1;
	private bool $hasShorthandLonghand = false;
	private int $physicalGroupMask = 0;
	private int $logicalGroupMask = 0;

	/** @var ?list<string> */
	private ?array $cachedKeys = null;

	private ?CSSRule $ownerRule = null;
	private bool $exemptFromPropertyGrammar = false;
	private bool $ownerExemptFromPropertyGrammar = false;
	private ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES;
	private ?int $maxDeclarations = CSSParserOptions::DEFAULT_MAX_DECLARATIONS;
	private ?int $maxDepth = CSSParserOptions::DEFAULT_MAX_DEPTH;
	private ?int $maxMathDepth = CSSParserOptions::DEFAULT_MAX_MATH_DEPTH;
	private int $contentBytes = 0;
	private bool $hasCachedValidationOptions = false;
	private ?CSSParserOptions $cachedValidationOptions = null;

	private const int MAX_ACCESSOR_CACHE = 512;

	/** @var array<string, string> */
	private static array $accessorCache = ['cssFloat' => 'float'];

	/**
	 * @throws InvalidArgumentException When $cssText contains invalid declarations or exceeds options limits
	 */
	public function __construct(string $cssText = '', ?CSSParserOptions $options = null)
	{
		if ($options !== null) {
			$this->maxBytes = $options->maxBytes;
			$this->maxDeclarations = $options->maxDeclarations;
			$this->maxDepth = $options->maxDepth;
			$this->maxMathDepth = $options->maxMathDepth;
		}
		if ($cssText !== '') {
			$this->setCssText($cssText, $options);
		}
	}

	public function __clone(): void
	{
		$owner = $this->ownerRule;
		if ($owner !== null) {
			$ownerMaxBytes = $owner->resolveMaxBytesInternal();
			if ($ownerMaxBytes !== null && ($this->maxBytes === null || $ownerMaxBytes < $this->maxBytes)) {
				$this->maxBytes = $ownerMaxBytes;
			}
			$sheet = $owner->getParentStyleSheet();
			if ($sheet !== null) {
				$sheetMaxDecl = $sheet->getMaxDeclarationsInternal();
				if ($sheetMaxDecl !== null && ($this->maxDeclarations === null || $sheetMaxDecl < $this->maxDeclarations)) {
					$this->maxDeclarations = $sheetMaxDecl;
				}
				$sheetMaxDepth = $sheet->getMaxDepthInternal();
				if ($sheetMaxDepth !== null && ($this->maxDepth === null || $sheetMaxDepth < $this->maxDepth)) {
					$this->maxDepth = $sheetMaxDepth;
				}
				$sheetMaxMathDepth = $sheet->getMaxMathDepthInternal();
				if ($sheetMaxMathDepth !== null && ($this->maxMathDepth === null || $sheetMaxMathDepth < $this->maxMathDepth)) {
					$this->maxMathDepth = $sheetMaxMathDepth;
				}
			}
		}
		$this->exemptFromPropertyGrammar = !$this->shouldValidatePropertyValuesInternal();
		$this->ownerExemptFromPropertyGrammar = false;
		$this->ownerRule = null;
		$this->cachedKeys = null;
		$this->hasCachedValidationOptions = false;
		$this->cachedValidationOptions = null;
	}

	/** @internal Adopts a matching-subclass declaration block (cloning if owned) or converts a generic block with limit clamping. */
	public static function adoptOrConvert(?self $style, ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES): static
	{
		if ($style instanceof static) {
			$adopted = $style->getParentRule() !== null ? clone $style : $style;
			$adopted->clampLimitsInternal($maxBytes, null, null, null, false);
			return $adopted;
		}

		$target = new static();
		if ($style !== null) {
			$target->setLimitsInternal(
				CSSRule::minLimitInternal($maxBytes, $style->resolveMaxBytesInternal()),
				$style->resolveMaxDeclarationsInternal(),
				$style->resolveMaxDepthInternal(),
				$style->resolveMaxMathDepthInternal(),
				true,
			);
			$target->copyDeclarationsFromInternal($style);
		} else {
			$target->setMaxBytesInternal($maxBytes);
		}

		return $target;
	}

	/** @internal */
	public function resolveMaxBytesInternal(): ?int
	{
		$ownerBytes = $this->ownerRule !== null ? $this->ownerRule->resolveMaxBytesInternal() : null;
		if ($ownerBytes === null) {
			return $this->maxBytes;
		}
		if ($this->maxBytes === null) {
			return $ownerBytes;
		}

		return $this->maxBytes < $ownerBytes ? $this->maxBytes : $ownerBytes;
	}

	/** @internal */
	public function resolveMaxDeclarationsInternal(): ?int
	{
		$sheet = $this->ownerRule?->getParentStyleSheet();

		return CSSRule::minLimitInternal($this->maxDeclarations, $sheet?->getMaxDeclarationsInternal());
	}

	/** @internal */
	public function resolveMaxDepthInternal(): ?int
	{
		$sheet = $this->ownerRule?->getParentStyleSheet();

		return CSSRule::minLimitInternal($this->maxDepth, $sheet?->getMaxDepthInternal());
	}

	/** @internal */
	public function resolveMaxMathDepthInternal(): ?int
	{
		$sheet = $this->ownerRule?->getParentStyleSheet();

		return CSSRule::minLimitInternal($this->maxMathDepth, $sheet?->getMaxMathDepthInternal());
	}

	/** @internal */
	public function setLimitsInternal(
		?int $maxBytes,
		?int $maxDeclarations,
		?int $maxDepth = null,
		?int $maxMathDepth = null,
		bool $updateDepths = false,
	): void {
		$this->maxBytes = $maxBytes;
		$this->maxDeclarations = $maxDeclarations;
		if ($updateDepths) {
			$this->maxDepth = $maxDepth;
			$this->maxMathDepth = $maxMathDepth;
		}
		$this->hasCachedValidationOptions = false;
		$this->cachedValidationOptions = null;
	}

	/** @internal */
	public function clampLimitsInternal(
		?int $maxBytes,
		?int $maxDeclarations,
		?int $maxDepth = null,
		?int $maxMathDepth = null,
		bool $syncDepths = false,
	): void {
		if ($maxBytes !== null && ($this->maxBytes === null || $maxBytes < $this->maxBytes)) {
			$this->maxBytes = $maxBytes;
			$this->hasCachedValidationOptions = false;
			$this->cachedValidationOptions = null;
		}
		if ($maxDeclarations !== null && ($this->maxDeclarations === null || $maxDeclarations < $this->maxDeclarations)) {
			$this->maxDeclarations = $maxDeclarations;
			$this->hasCachedValidationOptions = false;
			$this->cachedValidationOptions = null;
		}
		if ($syncDepths) {
			if ($maxDepth !== null && ($this->maxDepth === null || $maxDepth < $this->maxDepth)) {
				$this->maxDepth = $maxDepth;
				$this->hasCachedValidationOptions = false;
				$this->cachedValidationOptions = null;
			}
			if ($maxMathDepth !== null && ($this->maxMathDepth === null || $maxMathDepth < $this->maxMathDepth)) {
				$this->maxMathDepth = $maxMathDepth;
				$this->hasCachedValidationOptions = false;
				$this->cachedValidationOptions = null;
			}
		}
	}

	/** @internal */
	public function setMaxBytesInternal(?int $maxBytes): void
	{
		$this->maxBytes = $maxBytes;
		$this->hasCachedValidationOptions = false;
		$this->cachedValidationOptions = null;
	}

	/** @internal */
	public function getContentBytesInternal(): int
	{
		return $this->contentBytes;
	}

	/** @internal */
	public function assertContentBytesWithinInternal(?int $maxBytes): void
	{
		if ($maxBytes !== null && $this->contentBytes > $maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS declaration block exceeds maximum size of %d bytes.', $maxBytes));
		}
	}

	/**
	 * Parses a CSS declaration block.
	 *
	 * @param list<string>|null $diagnostics Populated with error messages in non-strict mode
	 * @throws InvalidArgumentException In strict mode when a declaration is invalid or limits are exceeded
	 */
	public static function parse(
		string $cssText,
		bool $strict = true,
		?array &$diagnostics = null,
		?CSSParserOptions $options = null,
	): static {
		$detailed = [];
		$style = static::parseWithOffsets($cssText, $strict, $detailed, 0, $options);
		$diagnostics ??= [];
		foreach ($detailed as $entry) {
			$diagnostics[] = $entry['message'];
		}

		return $style;
	}

	/**
	 * Parses a declaration block while recording exact byte offsets for diagnostics.
	 *
	 * @param list<array{message: string, offset: int}>|null $diagnostics
	 */
	public static function parseWithOffsets(
		string $cssText,
		bool $strict = true,
		?array &$diagnostics = null,
		int $baseOffset = 0,
		?CSSParserOptions $options = null,
	): static {
		$style = new static();
		if ($options !== null) {
			$style->setLimitsInternal(
				$options->maxBytes,
				$options->maxDeclarations,
				$options->maxDepth,
				$options->maxMathDepth,
				true,
			);
		}
		$diagnostics ??= [];
		$style->parseInto($cssText, $strict, $diagnostics, $baseOffset, $options);

		return $style;
	}

	/**
	 * Returns the list of allowed descriptor names if restricted by a subclass, or null if unrestricted.
	 *
	 * @return list<string>|null
	 */
	public static function getAllowedDescriptors(): ?array
	{
		$allowed = static::allowedDescriptors();
		return $allowed === null ? null : array_keys($allowed);
	}

	/**
	 * Override in descriptor subclasses to enforce a strict whitelist of descriptor names.
	 *
	 * @return array<string, true>|null
	 */
	protected static function allowedDescriptors(): ?array
	{
		return null;
	}

	protected static function allowsCustomProperties(): bool
	{
		return true;
	}

	protected static function allowsImportant(): bool
	{
		return true;
	}

	protected static function validatesPropertyValues(): bool
	{
		return true;
	}

	/** @internal */
	public function shouldValidatePropertyValuesInternal(): bool
	{
		return !$this->exemptFromPropertyGrammar
			&& !$this->ownerExemptFromPropertyGrammar
			&& static::validatesPropertyValues();
	}

	/** @internal */
	public function shouldExpandShorthandsInternal(): bool
	{
		return $this->shouldValidatePropertyValuesInternal();
	}

	/**
	 * @internal
	 * @return list<array{property: string, value: string, priority: string}>
	 */
	public function getSerializedDeclarationsInternal(): array
	{
		if ($this->declarations === []) {
			return [];
		}

		if (!$this->shouldExpandShorthandsInternal()) {
			$out = [];
			foreach ($this->declarations as $property => $declaration) {
				$out[] = [
					'property' => $property,
					'value' => $declaration['value'],
					'priority' => $declaration['priority'],
				];
			}

			return $out;
		}

		return CSSShorthandSerializer::serializeDeclarationBlock(
			$this->declarations,
			$this->pendingShorthands,
			$this->hasShorthandLonghand,
			$this->resolveValidationOptionsInternal(),
		);
	}

	/**
	 * @internal Copies declarations and pending shorthand metadata while enforcing descriptor/important policies.
	 */
	public function copyDeclarationsFromInternal(self $source): void
	{
		if ($source->declarations === []) {
			return;
		}

		$pendingIdMap = [];
		$pendingCommit = [];

		foreach ($source->declarations as $property => $declaration) {
			$pending = $source->pendingShorthands[$property] ?? null;
			if ($pending !== null && $this->shouldExpandShorthandsInternal()) {
				$oldId = $pending['id'];
				if (!isset($pendingIdMap[$oldId])) {
					$pendingIdMap[$oldId] = $this->nextPendingId++;
				}
				$newId = $pendingIdMap[$oldId];
				$pendingCommit[$newId] ??= [
					'shorthand' => $pending['shorthand'],
					'value' => $pending['value'],
					'kind' => $pending['kind'],
					'priority' => $declaration['priority'],
					'longhands' => [],
					'spans' => [],
				];
				$pendingCommit[$newId]['longhands'][] = $property;
				if (isset($source->sourceSpans[$property])) {
					$pendingCommit[$newId]['spans'][$property] = $source->sourceSpans[$property];
				}
				continue;
			}

			$this->setValidatedProperty($property, $declaration['value'], $declaration['priority']);
			if (isset($source->sourceSpans[$property], $this->declarations[$property])) {
				$this->sourceSpans[$property] = $source->sourceSpans[$property];
			}
		}

		foreach ($pendingCommit as $group) {
			$this->commitExpandedResult(
				[
					'kind' => 'pending',
					'pendingKind' => $group['kind'],
					'shorthand' => $group['shorthand'],
					'value' => $group['value'],
					'longhands' => $group['longhands'],
				],
				$group['priority'],
				false,
				true,
				null,
			);
			foreach ($group['spans'] as $lh => $span) {
				if (isset($this->declarations[$lh])) {
					$this->sourceSpans[$lh] = $span;
				}
			}
		}
	}

	/**
	 * Returns the source span for a parsed declaration (or shared shorthand span), or `null` if unavailable.
	 *
	 * @throws InvalidArgumentException When $property is malformed
	 */
	public function getPropertySourceSpan(string $property): ?CSSSourceSpan
	{
		$name = self::normalizePropertyName($property);
		if ($this->shouldExpandShorthandsInternal() && CSSShorthandRegistry::isShorthand($name)) {
			$longhands = CSSShorthandRegistry::getLonghands($name);
			if ($longhands !== null && $longhands !== []) {
				$sharedSpan = null;
				foreach ($longhands as $lh) {
					if (!isset($this->declarations[$lh], $this->sourceSpans[$lh])) {
						return null;
					}
					$lhSpan = $this->sourceSpans[$lh];
					if ($sharedSpan === null) {
						$sharedSpan = $lhSpan;
					} elseif ($lhSpan !== $sharedSpan) {
						return null;
					}
				}

				return $sharedSpan;
			}
		}

		return $this->sourceSpans[$name] ?? null;
	}

	/**
	 * @internal Returns the cumulative source span of parsed declarations (including overridden declarations).
	 */
	public function getParsedBlockSpanInternal(): ?CSSSourceSpan
	{
		return $this->parsedBlockSpan;
	}

	private function recordParsedSpanExtent(CSSSourceSpan $span): void
	{
		if ($this->parsedBlockSpan === null) {
			$this->parsedBlockSpan = $span;
			return;
		}

		$cur = $this->parsedBlockSpan;
		$startOffset = $cur->startOffset;
		$startLine = $cur->startLine;
		$startColumn = $cur->startColumn;
		if ($span->startOffset < $startOffset) {
			$startOffset = $span->startOffset;
			$startLine = $span->startLine;
			$startColumn = $span->startColumn;
		}
		$endOffset = $cur->endOffset;
		$endLine = $cur->endLine;
		$endColumn = $cur->endColumn;
		if ($span->endOffset > $endOffset) {
			$endOffset = $span->endOffset;
			$endLine = $span->endLine;
			$endColumn = $span->endColumn;
		}
		if ($startOffset !== $cur->startOffset || $endOffset !== $cur->endOffset) {
			$this->parsedBlockSpan = new CSSSourceSpan(
				$startOffset,
				$endOffset,
				$startLine,
				$startColumn,
				$endLine,
				$endColumn,
			);
		}
	}

	/**
	 * Returns tracked source spans keyed by canonical longhand/custom property name.
	 *
	 * @return array<string, CSSSourceSpan>
	 */
	public function getSourceSpans(): array
	{
		return $this->sourceSpans;
	}

	private function resolveValidationOptionsInternal(): ?CSSParserOptions
	{
		if ($this->hasCachedValidationOptions) {
			return $this->cachedValidationOptions;
		}

		$maxBytes = $this->resolveMaxBytesInternal();
		$maxDepth = $this->resolveMaxDepthInternal();
		$maxMathDepth = $this->resolveMaxMathDepthInternal();
		$this->hasCachedValidationOptions = true;
		if ($maxDepth === CSSParserOptions::DEFAULT_MAX_DEPTH
			&& $maxMathDepth === CSSParserOptions::DEFAULT_MAX_MATH_DEPTH
			&& $maxBytes === CSSParserOptions::DEFAULT_MAX_BYTES
		) {
			return $this->cachedValidationOptions = null;
		}

		return $this->cachedValidationOptions = new CSSParserOptions(
			maxBytes: $maxBytes,
			maxDepth: $maxDepth,
			maxMathDepth: $maxMathDepth,
			maxDeclarations: $this->resolveMaxDeclarationsInternal(),
		);
	}

	/**
	 * @internal Resolves effective parser options from this declaration (and optionally its owning stylesheet).
	 */
	public function resolveParserOptionsInternal(bool $includeSheetLimits = false): CSSParserOptions
	{
		$resolvedMaxBytes = $this->resolveMaxBytesInternal();
		$resolvedMaxDecls = $this->resolveMaxDeclarationsInternal();
		$resolvedMaxDepth = $this->resolveMaxDepthInternal();
		$resolvedMaxMathDepth = $this->resolveMaxMathDepthInternal();
		$sheet = $includeSheetLimits ? $this->ownerRule?->getParentStyleSheet() : null;
		if ($sheet !== null) {
			if ($resolvedMaxBytes === $sheet->getMaxBytesInternal()
				&& $resolvedMaxDecls === $sheet->getMaxDeclarationsInternal()
				&& $resolvedMaxDepth === $sheet->getMaxDepthInternal()
				&& $resolvedMaxMathDepth === $sheet->getMaxMathDepthInternal()
			) {
				return $sheet->resolveParserOptionsInternal(null);
			}

			return new CSSParserOptions(
				maxBytes: $resolvedMaxBytes,
				maxDepth: $resolvedMaxDepth,
				maxMathDepth: $resolvedMaxMathDepth,
				maxRules: $sheet->getMaxRulesInternal(),
				maxDeclarations: $resolvedMaxDecls,
				maxErrors: $sheet->getMaxErrorsInternal(),
			);
		}
		if ($resolvedMaxBytes !== CSSParserOptions::DEFAULT_MAX_BYTES
			|| $resolvedMaxDecls !== CSSParserOptions::DEFAULT_MAX_DECLARATIONS
			|| $resolvedMaxDepth !== CSSParserOptions::DEFAULT_MAX_DEPTH
			|| $resolvedMaxMathDepth !== CSSParserOptions::DEFAULT_MAX_MATH_DEPTH
		) {
			return new CSSParserOptions(
				maxBytes: $resolvedMaxBytes,
				maxDepth: $resolvedMaxDepth,
				maxMathDepth: $resolvedMaxMathDepth,
				maxDeclarations: $resolvedMaxDecls,
			);
		}

		return CSSParserOptions::default();
	}

	/**
	 * @throws InvalidArgumentException When $name is not permitted on this declaration block
	 */
	protected static function assertAllowedDescriptor(string $name): void
	{
		if (str_starts_with($name, '--')) {
			if (!static::allowsCustomProperties()) {
				throw new InvalidArgumentException(sprintf('Custom property "%s" is not allowed in %s.', $name, static::class));
			}
			return;
		}

		$allowed = static::allowedDescriptors();
		if ($allowed !== null && !isset($allowed[$name])) {
			throw new InvalidArgumentException(sprintf('Descriptor "%s" is not allowed in %s.', $name, static::class));
		}
	}

	public function getParentRule(): ?CSSRule
	{
		return $this->ownerRule;
	}

	/** @internal Links this declaration block to its owning CSSRule. */
	public function setParentRule(?CSSRule $parentRule): void
	{
		$this->ownerRule = $parentRule;
		$this->ownerExemptFromPropertyGrammar = $parentRule !== null && !$parentRule->validatesDescriptorPropertyValuesInternal();
		$this->hasCachedValidationOptions = false;
		$this->cachedValidationOptions = null;
	}

	public function getLength(): int
	{
		return count($this->declarations);
	}

	public function item(int $index): string
	{
		if ($index < 0 || $index >= count($this->declarations)) {
			return '';
		}

		$this->cachedKeys ??= array_keys($this->declarations);
		return $this->cachedKeys[$index];
	}

	public function getPropertyValue(string $property): string
	{
		$name = self::normalizePropertyName($property);
		if ($this->shouldExpandShorthandsInternal() && CSSShorthandRegistry::isShorthand($name)) {
			return CSSShorthandSerializer::serializeShorthand(
				$name,
				$this->declarations,
				$this->pendingShorthands,
				$this->resolveValidationOptionsInternal(),
			);
		}

		return $this->declarations[$name]['value'] ?? '';
	}

	public function getPropertyPriority(string $property): string
	{
		$name = self::normalizePropertyName($property);
		if ($this->shouldExpandShorthandsInternal() && CSSShorthandRegistry::isShorthand($name)) {
			$longhands = CSSShorthandRegistry::getLonghands($name);
			if ($longhands === null || $longhands === []) {
				return '';
			}
			foreach ($longhands as $lh) {
				if (!isset($this->declarations[$lh]) || $this->declarations[$lh]['priority'] !== 'important') {
					return '';
				}
			}

			return 'important';
		}

		return $this->declarations[$name]['priority'] ?? '';
	}

	/**
	 * @internal Direct array access for hot internal loops without ArrayIterator overhead.
	 * @return array<string, array{value: string, priority: string}>
	 */
	public function getDeclarationsRaw(): array
	{
		return $this->declarations;
	}

	/**
	 * Sets or removes a property declaration atomically.
	 *
	 * @throws InvalidArgumentException When the property name, value, priority, or size limit is invalid
	 */
	public function setProperty(string $property, string $value, string $priority = ''): void
	{
		$maxBytes = $this->resolveMaxBytesInternal();
		if ($maxBytes !== null && strlen($property) > $maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS property name exceeds maximum size of %d bytes.', $maxBytes));
		}
		if ($maxBytes !== null && strlen($value) > $maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS property value exceeds maximum size of %d bytes.', $maxBytes));
		}

		$name = self::normalizePropertyName($property);
		static::assertAllowedDescriptor($name);
		$priority = strtolower(trim($priority, CSSSyntaxScanner::CSS_WHITESPACE));
		if ($priority !== '' && $priority !== 'important') {
			throw new InvalidArgumentException('Priority must be empty or "important".');
		}
		if ($priority === 'important' && !static::allowsImportant()) {
			throw new InvalidArgumentException(sprintf('!important is not allowed in %s.', static::class));
		}

		$value = str_contains($value, '\\')
			? CSSSyntaxScanner::trimCssWhitespace($value)
			: trim($value, CSSSyntaxScanner::CSS_WHITESPACE);
		$expandShorthand = $this->shouldExpandShorthandsInternal() && CSSShorthandRegistry::isShorthand($name);
		if ($value === '') {
			if ($expandShorthand) {
				$this->removeShorthandLonghandsInternal($name);
				return;
			}
			$this->removeSingleLonghandInternal($name);
			return;
		}

		self::validatePropertyValue($value);
		if (!str_starts_with($name, '--') && str_contains($value, '/*') && CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($value)) === '') {
			throw new InvalidArgumentException('CSS property value cannot be empty.');
		}
		$parsed = null;
		$validationOptions = null;
		if ($this->shouldValidatePropertyValuesInternal()) {
			$validationOptions = $this->resolveValidationOptionsInternal();
			if (CSSPropertyGrammarRegistry::isKnown($name)) {
				if (!CSSValueValidator::validateStandardProperty($name, $value, $validationOptions, $parsed)) {
					throw new InvalidArgumentException(sprintf('Invalid value "%s" for CSS property "%s".', $value, $name));
				}
			} elseif (!CSSValueValidator::validatePropertyValue($name, $value, $validationOptions)) {
				throw new InvalidArgumentException(sprintf('Invalid value "%s" for CSS property "%s".', $value, $name));
			}
		}

		if ($expandShorthand) {
			$expanded = CSSShorthandExpander::expand($name, $value, $validationOptions ?? $this->resolveValidationOptionsInternal(), $parsed);
			if ($expanded === null) {
				throw new InvalidArgumentException(sprintf('Invalid value "%s" for CSS property "%s".', $value, $name));
			}
			$this->commitExpandedResult($expanded, $priority, false, true, null);
			return;
		}

		$oldEntry = $this->declarations[$name] ?? null;
		$oldBytes = $this->computeRemovedEntryBytes($name);
		$newBytes = strlen($name) + strlen($value);
		$nextContentBytes = $this->contentBytes - $oldBytes + $newBytes;
		if ($maxBytes !== null && ($newBytes > $maxBytes || $nextContentBytes > $maxBytes)) {
			throw new CSSParserLimitException(sprintf('CSS property declaration exceeds maximum size of %d bytes.', $maxBytes));
		}

		$delta = $oldEntry !== null ? 0 : 1;
		$this->assertSheetDeclarationGrowth($delta, null);
		$this->clearPendingEntryInternal($name);
		if ($oldEntry !== null && $this->shouldReappendSingleLonghand($name)) {
			unset($this->declarations[$name]);
		}
		unset($this->sourceSpans[$name]);
		$this->declarations[$name] = ['value' => $value, 'priority' => $priority];
		if (!$this->hasShorthandLonghand && CSSShorthandRegistry::isShorthandLonghand($name)) {
			$this->hasShorthandLonghand = true;
		}
		$this->recordLogicalGroupMask($name);
		$this->contentBytes = $nextContentBytes;
		$this->cachedKeys = null;
		$this->notifySheetMutation($delta, $name === 'initial-value');
	}

	/**
	 * @internal Sets an already-normalized and validated property while still enforcing descriptor/important policies.
	 */
	public function setValidatedProperty(
		string $normalizedName,
		string $trimmedValue,
		string $priority = '',
		bool $respectExistingImportant = false,
		bool $enforceMaxBytes = true,
	): void {
		static::assertAllowedDescriptor($normalizedName);
		if ($priority === 'important' && !static::allowsImportant()) {
			throw new InvalidArgumentException(sprintf('!important is not allowed in %s.', static::class));
		}
		$expandShorthand = $this->shouldExpandShorthandsInternal() && CSSShorthandRegistry::isShorthand($normalizedName);
		if ($trimmedValue === '' && !str_starts_with($normalizedName, '--')) {
			if ($expandShorthand) {
				$this->removeShorthandLonghandsInternal($normalizedName);
				return;
			}
			$this->removeSingleLonghandInternal($normalizedName);
			return;
		}
		if ($expandShorthand) {
			$expanded = CSSShorthandExpander::expand($normalizedName, $trimmedValue, $this->resolveValidationOptionsInternal());
			if ($expanded === null) {
				throw new InvalidArgumentException(sprintf('Invalid value "%s" for CSS property "%s".', $trimmedValue, $normalizedName));
			}
			$this->commitExpandedResult($expanded, $priority, $respectExistingImportant, $enforceMaxBytes, null);
			return;
		}

		$current = $this->declarations[$normalizedName] ?? null;
		if ($respectExistingImportant && $current !== null && $current['priority'] === 'important' && $priority !== 'important') {
			return;
		}

		$oldBytes = $this->computeRemovedEntryBytes($normalizedName);
		$newBytes = strlen($normalizedName) + strlen($trimmedValue);
		$nextContentBytes = $this->contentBytes - $oldBytes + $newBytes;
		if ($enforceMaxBytes) {
			$maxBytes = $this->resolveMaxBytesInternal();
			if ($maxBytes !== null && $nextContentBytes > $maxBytes) {
				throw new CSSParserLimitException(sprintf('CSS declaration block exceeds maximum size of %d bytes.', $maxBytes));
			}
		}

		$delta = $current !== null ? 0 : 1;
		$this->assertSheetDeclarationGrowth($delta, null);
		$this->clearPendingEntryInternal($normalizedName);
		if ($current !== null && $this->shouldReappendSingleLonghand($normalizedName)) {
			unset($this->declarations[$normalizedName]);
		}
		unset($this->sourceSpans[$normalizedName]);
		$this->declarations[$normalizedName] = ['value' => $trimmedValue, 'priority' => $priority];
		if (!$this->hasShorthandLonghand && CSSShorthandRegistry::isShorthandLonghand($normalizedName)) {
			$this->hasShorthandLonghand = true;
		}
		$this->recordLogicalGroupMask($normalizedName);
		$this->contentBytes = $nextContentBytes;
		$this->cachedKeys = null;
		$this->notifySheetMutation($delta, $normalizedName === 'initial-value');
	}

	public function removeProperty(string $property): string
	{
		$name = self::normalizePropertyName($property);
		if ($this->shouldExpandShorthandsInternal() && CSSShorthandRegistry::isShorthand($name)) {
			$old = $this->getPropertyValue($name);
			$this->removeShorthandLonghandsInternal($name);
			return $old;
		}
		$oldEntry = $this->declarations[$name] ?? null;
		$value = $oldEntry['value'] ?? '';
		$this->removeSingleLonghandInternal($name);

		return $value;
	}

	public function clear(): void
	{
		$delta = -count($this->declarations);
		$this->resetState();
		$this->notifySheetMutation($delta, true);
	}

	private function resetState(): void
	{
		$this->declarations = [];
		$this->sourceSpans = [];
		$this->parsedBlockSpan = null;
		$this->pendingShorthands = [];
		$this->pendingPayloadAccounting = [];
		$this->hasShorthandLonghand = false;
		$this->physicalGroupMask = 0;
		$this->logicalGroupMask = 0;
		$this->nextPendingId = 1;
		$this->contentBytes = 0;
		$this->cachedKeys = null;
	}

	public function getCssText(): string
	{
		if ($this->declarations === []) {
			return '';
		}

		$entries = $this->getSerializedDeclarationsInternal();
		if ($entries === []) {
			return '';
		}

		$serialized = [];
		foreach ($entries as $entry) {
			$serialized[] = $entry['priority'] === ''
				? $entry['property'] . ': ' . $entry['value'] . ';'
				: $entry['property'] . ': ' . $entry['value'] . ' !important;';
		}

		return implode(' ', $serialized);
	}

	/**
	 * Atomically replaces all declarations with the parsed contents of $cssText.
	 *
	 * @throws InvalidArgumentException When any declaration is invalid or limits are exceeded
	 */
	public function setCssText(string $cssText, ?CSSParserOptions $options = null): void
	{
		if ($options !== null) {
			$effectiveOptions = $options;
		} else {
			$resolved = $this->resolveParserOptionsInternal(true);
			if ($resolved->trackSpans) {
				$effectiveOptions = new CSSParserOptions(
					maxBytes: $resolved->maxBytes,
					maxDepth: $resolved->maxDepth,
					maxRules: $resolved->maxRules,
					maxMathDepth: $resolved->maxMathDepth,
					maxErrors: $resolved->maxErrors,
					maxDeclarations: $resolved->maxDeclarations,
					browserErrorRecovery: $resolved->browserErrorRecovery,
					trackSpans: false,
				);
			} else {
				$effectiveOptions = $resolved === CSSParserOptions::default() ? null : $resolved;
			}
		}
		$allowEmptyInitialValue = $this->ownerRule instanceof CSSPropertyRule;
		$validatePropertyGrammar = $this->shouldValidatePropertyValuesInternal();
		$sawEmptyInitialValue = false;
		$replacement = new static();
		$detailed = [];
		$replacement->parseInto($cssText, true, $detailed, 0, $effectiveOptions, $allowEmptyInitialValue, $sawEmptyInitialValue, $validatePropertyGrammar);
		$delta = count($replacement->declarations) - count($this->declarations);
		$this->assertSheetDeclarationGrowth($delta, $effectiveOptions);
		$this->declarations = $replacement->declarations;
		$this->sourceSpans = $replacement->sourceSpans;
		$this->parsedBlockSpan = $replacement->parsedBlockSpan;
		$this->pendingShorthands = $replacement->pendingShorthands;
		$this->pendingPayloadAccounting = $replacement->pendingPayloadAccounting;
		$this->hasShorthandLonghand = $replacement->hasShorthandLonghand;
		$this->physicalGroupMask = $replacement->physicalGroupMask;
		$this->logicalGroupMask = $replacement->logicalGroupMask;
		$this->nextPendingId = $replacement->nextPendingId;
		$this->contentBytes = $replacement->contentBytes;
		$this->cachedKeys = null;
		$this->notifySheetMutation($delta, true);
		if ($this->ownerRule instanceof CSSPropertyRule) {
			$this->ownerRule->setEmptyInitialValueInternal($sawEmptyInitialValue);
		}
	}

	private function assertSheetDeclarationGrowth(int $delta, ?CSSParserOptions $overrideOptions): void
	{
		if ($delta <= 0) {
			return;
		}
		$sheet = $this->ownerRule?->getParentStyleSheet();
		if ($overrideOptions !== null) {
			if ($overrideOptions->maxDeclarations !== null && (count($this->declarations) + $delta) > $overrideOptions->maxDeclarations) {
				throw new CSSParserLimitException(sprintf('Maximum CSS declaration limit of %d exceeded.', $overrideOptions->maxDeclarations));
			}
			if ($sheet !== null && $overrideOptions->maxDeclarations !== null && ($sheet->getTotalDeclarationCount() + $delta) > $overrideOptions->maxDeclarations) {
				throw new CSSParserLimitException(sprintf('Maximum CSS declaration limit of %d exceeded.', $overrideOptions->maxDeclarations));
			}

			return;
		}
		$maxDeclarations = $this->resolveMaxDeclarationsInternal();
		if ($maxDeclarations !== null && (count($this->declarations) + $delta) > $maxDeclarations) {
			throw new CSSParserLimitException(sprintf('Maximum CSS declaration limit of %d exceeded.', $maxDeclarations));
		}
		if ($sheet !== null) {
			$sheetMaxDecls = $sheet->getMaxDeclarationsInternal();
			if ($sheetMaxDecls !== null && ($sheet->getTotalDeclarationCount() + $delta) > $sheetMaxDecls) {
				throw new CSSParserLimitException(sprintf('Maximum CSS declaration limit of %d exceeded.', $sheetMaxDecls));
			}
		}
	}

	private function notifySheetMutation(int $delta, bool $clearEmptyInitialValue): void
	{
		if ($delta !== 0) {
			$this->ownerRule?->getParentStyleSheet()?->adjustTotalDeclarationCount($delta);
		}
		if ($clearEmptyInitialValue && $this->ownerRule instanceof CSSPropertyRule) {
			$this->ownerRule->clearEmptyInitialValueInternal();
		}
	}

	/** @return Traversable<string, array{value: string, priority: string}> */
	public function getIterator(): Traversable
	{
		return new ArrayIterator($this->declarations);
	}

	public function count(): int
	{
		return $this->getLength();
	}

	public function offsetExists(mixed $offset): bool
	{
		if (!is_string($offset)) {
			return false;
		}
		$name = self::normalizePropertyName($offset);
		if ($this->shouldExpandShorthandsInternal() && CSSShorthandRegistry::isShorthand($name)) {
			return $this->getPropertyValue($name) !== '';
		}

		return isset($this->declarations[$name]);
	}

	public function offsetGet(mixed $offset): string
	{
		return is_string($offset) ? $this->getPropertyValue($offset) : '';
	}

	public function offsetSet(mixed $offset, mixed $value): void
	{
		if (!is_string($offset) || !is_string($value)) {
			throw new InvalidArgumentException('A CSS property name and string value are required.');
		}

		$this->setProperty($offset, $value);
	}

	public function offsetUnset(mixed $offset): void
	{
		if (is_string($offset)) {
			$this->removeProperty($offset);
		}
	}

	public function __toString(): string
	{
		return $this->getCssText();
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'cssText' => $this->getCssText(),
			'length' => $this->getLength(),
			'parentRule' => $this->getParentRule(),
			default => $this->getPropertyValue(self::propertyNameFromAccessor($name)),
		};
	}

	public function __isset(string $name): bool
	{
		if ($name === 'cssText' || $name === 'length') {
			return true;
		}
		if ($name === 'parentRule') {
			return $this->ownerRule !== null;
		}
		$propName = self::normalizePropertyName(self::propertyNameFromAccessor($name));
		if ($this->shouldExpandShorthandsInternal() && CSSShorthandRegistry::isShorthand($propName)) {
			return $this->getPropertyValue($propName) !== '';
		}

		return isset($this->declarations[$propName]);
	}

	public function __set(string $name, mixed $value): void
	{
		if ($name === 'cssText' && is_string($value)) {
			$this->setCssText($value);
			return;
		}
		if ($name === 'length' || $name === 'parentRule') {
			throw new Error('Cannot set read-only property ' . static::class . '::$' . $name);
		}
		if (is_float($value) && !is_finite($value)) {
			throw new InvalidArgumentException('CSS numeric property value must be finite.');
		}
		if (is_string($value) || is_int($value) || is_float($value)) {
			$this->setProperty(self::propertyNameFromAccessor($name), (string) $value);
			return;
		}
		throw new Error('Cannot set property ' . static::class . '::$' . $name);
	}

	/**
	 * Validates and normalizes a CSS property or custom property name.
	 *
	 * @throws InvalidArgumentException When $property is not a valid CSS identifier
	 */
	public static function normalizePropertyName(string $property): string
	{
		if ($property !== '') {
			$first = $property[0];
			if ((($first >= 'a' && $first <= 'z') || $first === '_')
				&& strspn($property, self::LOWER_ASCII_NAME_CHARS) === strlen($property)
			) {
				return $property;
			}
		}

		$hasBackslash = str_contains($property, '\\');
		$property = $hasBackslash
			? CSSSyntaxScanner::trimCssWhitespace($property)
			: trim($property, CSSSyntaxScanner::CSS_WHITESPACE);
		if (CSSSyntaxScanner::hasControlCharacters($property)) {
			throw new InvalidArgumentException('Invalid CSS property name.');
		}
		if ($hasBackslash && preg_match('//u', $property) !== 1) {
			throw new InvalidArgumentException('Invalid CSS property name: ' . $property);
		}
		if (str_starts_with($property, '--')) {
			if ($hasBackslash) {
				$decoded = CSSSyntaxScanner::decodeIdentAt($property, 0, strlen($property));
				if ($decoded === null
					|| $decoded['end'] !== strlen($property)
					|| strlen($decoded['ident']) <= 2
					|| preg_match('/[\x00-\x1F\x7F]/', $decoded['ident']) === 1
				) {
					throw new InvalidArgumentException('Invalid CSS custom property name: ' . $property);
				}

				return CSS::escape($decoded['ident']);
			} elseif (!preg_match('/^--(?:[-_a-zA-Z0-9]|[^\x00-\x7F])+$/uD', $property)) {
				throw new InvalidArgumentException('Invalid CSS custom property name: ' . $property);
			}

			return $property;
		}

		if ($hasBackslash) {
			$decoded = CSSSyntaxScanner::decodeIdentAt($property, 0, strlen($property));
			if ($decoded === null || $decoded['end'] !== strlen($property)) {
				throw new InvalidArgumentException('Invalid CSS property name: ' . $property);
			}
			if (str_starts_with($decoded['ident'], '--')) {
				if (strlen($decoded['ident']) <= 2
					|| preg_match('/[\x00-\x1F\x7F]/', $decoded['ident']) === 1
				) {
					throw new InvalidArgumentException('Invalid CSS custom property name: ' . $property);
				}

				return CSS::escape($decoded['ident']);
			}
			if (!preg_match('/^-?[a-zA-Z_][a-zA-Z0-9_-]*$/D', $decoded['ident'])) {
				throw new InvalidArgumentException('Invalid CSS property name: ' . $property);
			}

			return strtolower($decoded['ident']);
		}

		if (!preg_match('/^-?[a-zA-Z_][a-zA-Z0-9_-]*$/D', $property)) {
			throw new InvalidArgumentException('Invalid CSS property name: ' . $property);
		}

		return strtolower($property);
	}

	/**
	 * Validates a CSS property value for structural CSS injection and syntax balance.
	 *
	 * @throws InvalidArgumentException When the value contains disallowed structure or !important
	 */
	public static function validatePropertyValue(string $value): void
	{
		CSSSyntaxScanner::assertValidFragment($value, 'CSS property value', [';', '{', '}']);

		if (str_contains($value, '!') && CSSSyntaxScanner::findLastTopLevelImportant($value) !== null) {
			throw new InvalidArgumentException('Pass CSS priority separately instead of including !important in the value.');
		}
	}

	/**
	 * @internal Extracts a slice without quotes/escapes/comments/brackets/control-bytes if parens are balanced (and within `$maxDepth`) and free of `url(`/`-->`.
	 */
	public static function extractCleanAsciiSliceInternal(string $source, int $start, int $end, ?int $maxDepth = null): ?string
	{
		$len = $end - $start;
		if ($len <= 0) {
			return '';
		}
		$slice = substr($source, $start, $len);
		if (str_contains($slice, '/*') || str_contains($slice, '-->')) {
			return null;
		}
		if (!str_contains($slice, '(') && !str_contains($slice, ')')) {
			return $slice;
		}
		if (stripos($slice, 'url(') !== false) {
			return null;
		}
		$depth = 0;
		$pos = 0;
		while ($pos < $len) {
			$pos += strcspn($slice, '()', $pos, $len - $pos);
			if ($pos >= $len) {
				break;
			}
			if ($slice[$pos] === '(') {
				$depth++;
				if ($maxDepth !== null && $depth > $maxDepth) {
					return null;
				}
			} else {
				$depth--;
				if ($depth < 0) {
					return null;
				}
			}
			$pos++;
		}

		return $depth === 0 ? $slice : null;
	}

	/**
	 * @internal Fast path for an already-extracted clean ASCII declaration `$slice` starting at `$start`.
	 * @param list<array{message: string, offset: int}> $diagnostics
	 */
	public function parseCleanAsciiDeclarationInternal(
		string $slice,
		int $start,
		bool $strict,
		array &$diagnostics,
		?CSSParserOptions $options = null,
		int &$declarationCount = 0,
		bool $throwOnLimit = false,
		bool $allowSentinelSlot = false,
		bool $allowEmptyInitialValue = false,
		bool &$sawEmptyInitialValue = false,
		bool $validatePropertyGrammar = true,
		?CSSSourceLocationIndex $locationIndex = null,
	): bool {
		$declaration = trim($slice, " \t\n");
		if ($declaration === '') {
			return true;
		}
		$segmentOffset = $start + strspn($slice, " \t\n");
		$segmentEndOffset = $locationIndex !== null ? ($segmentOffset + strlen($declaration)) : 0;
		$colon = strpos($declaration, ':');
		if ($colon === false) {
			self::handleInvalidDeclaration('Missing colon in declaration: ' . $declaration, $segmentOffset, $strict, $diagnostics, $options, $allowSentinelSlot);
			return true;
		}
		$property = trim(substr($declaration, 0, $colon), " \t\n");
		$value = trim(substr($declaration, $colon + 1), " \t\n");

		try {
			$name = self::normalizePropertyName($property);
			static::assertAllowedDescriptor($name);
		} catch (InvalidArgumentException $exception) {
			self::handleInvalidDeclaration($exception->getMessage(), $segmentOffset, $strict, $diagnostics, $options, $allowSentinelSlot);
			return true;
		}

		$priority = '';
		if ($value !== '' && str_contains($value, '!')) {
			$important = CSSSyntaxScanner::findLastTopLevelImportant($value);
			if ($important !== null) {
				if (!static::allowsImportant()) {
					self::handleInvalidDeclaration(
						sprintf('!important is not allowed in %s (%s).', static::class, $name),
						$segmentOffset,
						$strict,
						$diagnostics,
						$options,
						$allowSentinelSlot,
					);
					return true;
				}
				$priority = 'important';
				$value = rtrim(substr($value, 0, $important), " \t\n");
				if ($value !== '' && str_contains($value, '!') && CSSSyntaxScanner::findLastTopLevelImportant($value) !== null) {
					self::handleInvalidDeclaration('Duplicate !important in CSS declaration (' . $name . ').', $segmentOffset, $strict, $diagnostics, $options, $allowSentinelSlot);
					return true;
				}
			}
		}

		return $this->commitParsedDeclaration(
			$name,
			$value,
			$priority,
			$segmentOffset,
			$strict,
			$diagnostics,
			$options,
			$declarationCount,
			$throwOnLimit,
			$allowSentinelSlot,
			$allowEmptyInitialValue,
			$sawEmptyInitialValue,
			$validatePropertyGrammar,
			$segmentEndOffset,
			$locationIndex,
		);
	}

	/**
	 * @internal Parses a single declaration from scanned CSS Syntax L3 tokens in [$start, $end).
	 * @param list<array{type: CSSTokenType, start: int, end: int, hasEscapes: bool, closedAtEof: bool, closedQuote: string, trailingBsAtEof: bool}> $tokens
	 * @param list<string> $unclosedBlocks
	 * @param list<array{message: string, offset: int}> $diagnostics
	 */
	public function parseDeclarationFromTokensInternal(
		string $source,
		int $start,
		int $end,
		array $tokens,
		bool $hasComments,
		?int $controlByteOffset,
		?int $badTokenOffset,
		?string $badTokenMessage,
		bool $hasUnclosedConstruct,
		array $unclosedBlocks,
		bool $hasTopLevelBraces,
		bool $strict,
		array &$diagnostics,
		?CSSParserOptions $options = null,
		int &$declarationCount = 0,
		bool $throwOnLimit = false,
		bool $allowSentinelSlot = false,
		bool $allowEmptyInitialValue = false,
		bool &$sawEmptyInitialValue = false,
		bool $browserMode = false,
		bool $validatePropertyGrammar = true,
		?CSSSourceLocationIndex $locationIndex = null,
	): bool {
		$segmentOffset = $tokens !== [] ? $tokens[0]['start'] : $start;

		if ($controlByteOffset !== null) {
			self::handleInvalidDeclaration('Disallowed control characters in CSS declaration.', $segmentOffset, $strict, $diagnostics, $options, $allowSentinelSlot);
			return true;
		}
		if ($badTokenOffset !== null) {
			self::handleInvalidDeclaration($badTokenMessage ?? 'Malformed token in CSS declaration.', $segmentOffset, $strict, $diagnostics, $options, $allowSentinelSlot);
			return true;
		}
		if ($hasTopLevelBraces) {
			self::handleInvalidDeclaration('Disallowed structural delimiter or unbalanced syntax in CSS declaration.', $segmentOffset, $strict, $diagnostics, $options, $allowSentinelSlot);
			return true;
		}
		if ($hasUnclosedConstruct && !$browserMode) {
			self::handleInvalidDeclaration('Unclosed comment or string in CSS declaration.', $segmentOffset, $strict, $diagnostics, $options, $allowSentinelSlot);
			return true;
		}
		if (!$browserMode && $unclosedBlocks !== []) {
			self::handleInvalidDeclaration('Unbalanced syntax in CSS declaration.', $segmentOffset, $strict, $diagnostics, $options, $allowSentinelSlot);
			return true;
		}

		// Find first two non-whitespace tokens: must be <ident-token> followed by <colon-token>.
		$tokenCount = count($tokens);
		$idx = 0;
		while ($idx < $tokenCount && $tokens[$idx]['type'] === CSSTokenType::Whitespace) {
			$idx++;
		}
		if ($idx >= $tokenCount) {
			return true;
		}
		$nameTok = $tokens[$idx];
		if ($nameTok['type'] !== CSSTokenType::Ident) {
			$rawDecl = $hasComments
				? CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($source, $start, $end))
				: CSSSyntaxScanner::trimCssWhitespace(substr($source, $start, $end - $start));
			self::handleInvalidDeclaration('Missing colon in declaration: ' . $rawDecl, $segmentOffset, $strict, $diagnostics, $options, $allowSentinelSlot);
			return true;
		}
		$idx++;
		while ($idx < $tokenCount && $tokens[$idx]['type'] === CSSTokenType::Whitespace) {
			$idx++;
		}
		if ($idx >= $tokenCount || $tokens[$idx]['type'] !== CSSTokenType::Colon) {
			$rawDecl = $hasComments
				? CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($source, $start, $end))
				: CSSSyntaxScanner::trimCssWhitespace(substr($source, $start, $end - $start));
			self::handleInvalidDeclaration('Missing colon in declaration: ' . $rawDecl, $segmentOffset, $strict, $diagnostics, $options, $allowSentinelSlot);
			return true;
		}

		$rawProp = substr($source, $nameTok['start'], $nameTok['end'] - $nameTok['start']);
		try {
			$name = self::normalizePropertyName($rawProp);
			static::assertAllowedDescriptor($name);
		} catch (InvalidArgumentException $exception) {
			self::handleInvalidDeclaration($exception->getMessage(), $segmentOffset, $strict, $diagnostics, $options, $allowSentinelSlot);
			return true;
		}

		$segmentOffset = $nameTok['start'];
		$colonEnd = $tokens[$idx]['end'];
		$valStartIdx = $idx + 1;
		while ($valStartIdx < $tokenCount && $tokens[$valStartIdx]['type'] === CSSTokenType::Whitespace) {
			$valStartIdx++;
		}
		$valEndIdx = $tokenCount - 1;
		while ($valEndIdx >= $valStartIdx && $tokens[$valEndIdx]['type'] === CSSTokenType::Whitespace) {
			$valEndIdx--;
		}

		$segmentEndOffset = $locationIndex !== null
			? ($valStartIdx > $valEndIdx ? $colonEnd : max($segmentOffset, $tokens[$valEndIdx]['end']))
			: 0;

		if ($valStartIdx > $valEndIdx) {
			return $this->commitParsedDeclaration(
				$name,
				'',
				'',
				$segmentOffset,
				$strict,
				$diagnostics,
				$options,
				$declarationCount,
				$throwOnLimit,
				$allowSentinelSlot,
				$allowEmptyInitialValue,
				$sawEmptyInitialValue,
				$validatePropertyGrammar,
				$segmentEndOffset,
				$locationIndex,
			);
		}

		// Detect trailing !important on token stream only at top level ($unclosedBlocks === []), allowing whitespace/comments between '!' and 'important'.
		$priority = '';
		$valueSliceEnd = $tokens[$valEndIdx]['end'];
		$bangIdx = ($unclosedBlocks === [] && $valEndIdx - $valStartIdx >= 1)
			? self::matchTrailingImportantTokensInternal($source, $tokens, $valStartIdx, $valEndIdx)
			: null;
		if ($bangIdx !== null) {
			if (!static::allowsImportant()) {
				self::handleInvalidDeclaration(
					sprintf('!important is not allowed in %s (%s).', static::class, $name),
					$segmentOffset,
					$strict,
					$diagnostics,
					$options,
					$allowSentinelSlot,
				);
				return true;
			}
			$priority = 'important';
			$valEndIdx = $bangIdx - 1;
			while ($valEndIdx >= $valStartIdx && $tokens[$valEndIdx]['type'] === CSSTokenType::Whitespace) {
				$valEndIdx--;
			}
			if (self::matchTrailingImportantTokensInternal($source, $tokens, $valStartIdx, $valEndIdx) !== null) {
				self::handleInvalidDeclaration('Duplicate !important in CSS declaration (' . $name . ').', $segmentOffset, $strict, $diagnostics, $options, $allowSentinelSlot);
				return true;
			}
			$valueSliceEnd = $valEndIdx >= $valStartIdx ? $tokens[$valEndIdx]['end'] : $colonEnd;
		}

		if ($valStartIdx > $valEndIdx) {
			$value = '';
		} else {
			$valSliceStart = $tokens[$valStartIdx]['start'];
			$rawSlice = $hasComments
				? CSSSyntaxScanner::removeComments($source, $valSliceStart, $valueSliceEnd)
				: substr($source, $valSliceStart, $valueSliceEnd - $valSliceStart);
			$value = str_contains($rawSlice, '\\')
				? CSSSyntaxScanner::trimCssWhitespace($rawSlice)
				: trim($rawSlice, CSSSyntaxScanner::CSS_WHITESPACE);

			if ($browserMode && $priority === '') {
				$lastValTok = $tokens[$valEndIdx];
				$suffix = '';
				if ($lastValTok['closedAtEof']) {
					if ($lastValTok['type'] === CSSTokenType::String) {
						if ($lastValTok['trailingBsAtEof'] && str_ends_with($value, '\\')) {
							$value = substr($value, 0, -1);
						}
						$suffix .= $lastValTok['closedQuote'];
					} elseif ($lastValTok['type'] === CSSTokenType::Url) {
						if (self::endsWithUnescapedTrailingBackslash($value)) {
							$value = substr($value, 0, -1) . "\u{FFFD}";
						}
						$suffix .= ')';
					}
				} elseif (self::endsWithUnescapedTrailingBackslash($value)) {
					$value = substr($value, 0, -1) . "\u{FFFD}";
				}
				for ($b = count($unclosedBlocks) - 1; $b >= 0; $b--) {
					$suffix .= $unclosedBlocks[$b];
				}
				if ($suffix !== '') {
					$value .= $suffix;
				}
			}
		}

		if ($value !== '' && $options !== null && $options->maxBytes !== null && (strlen($name) + strlen($value)) > $options->maxBytes) {
			$message = sprintf('CSS property declaration exceeds maximum size of %d bytes.', $options->maxBytes);
			if ($strict || $throwOnLimit) {
				throw new CSSParserLimitException($message);
			}
			self::recordDiagnostic($message, $segmentOffset, $diagnostics, $options, $allowSentinelSlot);
			return true;
		}

		return $this->commitParsedDeclaration(
			$name,
			$value,
			$priority,
			$segmentOffset,
			$strict,
			$diagnostics,
			$options,
			$declarationCount,
			$throwOnLimit,
			$allowSentinelSlot,
			$allowEmptyInitialValue,
			$sawEmptyInitialValue,
			$validatePropertyGrammar,
			$segmentEndOffset,
			$locationIndex,
		);
	}

	/**
	 * @internal Matches trailing `!important` (allowing intervening whitespace tokens) in `$tokens[$valStartIdx..$valEndIdx]`.
	 * @param list<array{type: CSSTokenType, start: int, end: int, hasEscapes: bool, closedAtEof: bool, closedQuote: string, trailingBsAtEof: bool}> $tokens
	 */
	public static function matchTrailingImportantTokensInternal(
		string $source,
		array $tokens,
		int $valStartIdx,
		int $valEndIdx,
	): ?int {
		if ($valEndIdx - $valStartIdx < 1) {
			return null;
		}
		$lastTok = $tokens[$valEndIdx];
		if ($lastTok['type'] !== CSSTokenType::Ident || !self::tokenIdentEqualsLower($source, $lastTok, 'important')) {
			return null;
		}
		$bangIdx = $valEndIdx - 1;
		while ($bangIdx >= $valStartIdx && $tokens[$bangIdx]['type'] === CSSTokenType::Whitespace) {
			$bangIdx--;
		}
		if ($bangIdx < $valStartIdx) {
			return null;
		}
		$bangTok = $tokens[$bangIdx];
		if ($bangTok['type'] === CSSTokenType::Delim
			&& ($bangTok['end'] - $bangTok['start']) === 1
			&& $source[$bangTok['start']] === '!'
		) {
			return $bangIdx;
		}

		return null;
	}

	/**
	 * @param array{type: CSSTokenType, start: int, end: int, hasEscapes: bool, closedAtEof: bool, closedQuote: string, trailingBsAtEof: bool} $token
	 */
	private static function tokenIdentEqualsLower(string $source, array $token, string $expectedLower): bool
	{
		$rawLen = $token['end'] - $token['start'];
		if (!$token['hasEscapes']) {
			return $rawLen === strlen($expectedLower)
				&& substr_compare($source, $expectedLower, $token['start'], $rawLen, true) === 0;
		}

		return strtolower(CSSSyntaxScanner::decodeCssIdentEscapes(substr($source, $token['start'], $rawLen))) === $expectedLower;
	}

	private static function endsWithUnescapedTrailingBackslash(string $value): bool
	{
		$len = strlen($value);
		$count = 0;
		for ($i = $len - 1; $i >= 0 && $value[$i] === '\\'; $i--) {
			$count++;
		}

		return ($count & 1) === 1;
	}

	/**
	 * @param list<array{message: string, offset: int}> $diagnostics
	 */
	private function commitParsedDeclaration(
		string $name,
		string $value,
		string $priority,
		int $segmentOffset,
		bool $strict,
		array &$diagnostics,
		?CSSParserOptions $options,
		int &$declarationCount,
		bool $throwOnLimit,
		bool $allowSentinelSlot,
		bool $allowEmptyInitialValue,
		bool &$sawEmptyInitialValue,
		bool $validatePropertyGrammar = true,
		int $segmentEndOffset = 0,
		?CSSSourceLocationIndex $locationIndex = null,
	): bool {
		if ($value === '' && !str_starts_with($name, '--')) {
			if ($allowEmptyInitialValue && $name === 'initial-value' && $priority === '') {
				if ($options !== null && $options->maxDeclarations !== null && $declarationCount >= $options->maxDeclarations) {
					$message = sprintf('Maximum CSS declaration limit of %d exceeded.', $options->maxDeclarations);
					if ($strict || $throwOnLimit) {
						throw new CSSParserLimitException($message);
					}
					self::recordDiagnostic($message, $segmentOffset, $diagnostics, $options, $allowSentinelSlot);
					return false;
				}
				$current = $this->declarations['initial-value'] ?? null;
				if ($current !== null && $current['priority'] === 'important') {
					return true;
				}
				if ($declarationCount >= PHP_INT_MAX) {
					throw new InvalidArgumentException('CSS declaration count overflows integer bounds.');
				}
				$declarationCount++;
				if ($current !== null) {
					$this->contentBytes -= strlen('initial-value') + strlen($current['value']);
				}
				unset($this->declarations['initial-value']);
				unset($this->sourceSpans['initial-value']);
				$this->cachedKeys = null;
				$sawEmptyInitialValue = true;
				return true;
			}
			self::handleInvalidDeclaration('CSS declaration value cannot be empty: ' . $name, $segmentOffset, $strict, $diagnostics, $options, $allowSentinelSlot);
			return true;
		}

		$parsed = null;
		if ($validatePropertyGrammar && CSSPropertyGrammarRegistry::isKnown($name)) {
			try {
				$validGrammar = CSSValueValidator::validateStandardProperty($name, $value, $options, $parsed);
			} catch (CSSParserLimitException $exception) {
				if ($strict || $throwOnLimit) {
					throw $exception;
				}
				self::recordDiagnostic($exception->getMessage(), $segmentOffset, $diagnostics, $options, $allowSentinelSlot);
				return true;
			}
			if (!$validGrammar) {
				self::handleInvalidDeclaration(
					sprintf('Invalid value "%s" for CSS property "%s".', $value, $name),
					$segmentOffset,
					$strict,
					$diagnostics,
					$options,
					$allowSentinelSlot,
				);
				return true;
			}
		} elseif ($validatePropertyGrammar && $options !== null && $options->maxMathDepth !== null && str_contains($value, '(')) {
			try {
				$parsed = CSSValueComponentParser::parse($value, $options, str_starts_with($name, '--'));
			} catch (CSSParserLimitException $exception) {
				if ($strict || $throwOnLimit) {
					throw $exception;
				}
				self::recordDiagnostic($exception->getMessage(), $segmentOffset, $diagnostics, $options, $allowSentinelSlot);
				return true;
			}
		}

		if ($validatePropertyGrammar && CSSShorthandRegistry::isShorthand($name)) {
			$expanded = CSSShorthandExpander::expand($name, $value, $options, $parsed);
			if ($expanded === null) {
				self::handleInvalidDeclaration(
					sprintf('Invalid value "%s" for CSS property "%s".', $value, $name),
					$segmentOffset,
					$strict,
					$diagnostics,
					$options,
					$allowSentinelSlot,
				);
				return true;
			}

			return $this->commitParsedExpandedDeclaration(
				$expanded,
				$priority,
				$segmentOffset,
				$strict,
				$diagnostics,
				$options,
				$declarationCount,
				$throwOnLimit,
				$allowSentinelSlot,
				$segmentEndOffset,
				$locationIndex,
			);
		}

		if ($options !== null && $options->maxDeclarations !== null && $declarationCount >= $options->maxDeclarations) {
			$message = sprintf('Maximum CSS declaration limit of %d exceeded.', $options->maxDeclarations);
			if ($strict || $throwOnLimit) {
				throw new CSSParserLimitException($message);
			}
			self::recordDiagnostic($message, $segmentOffset, $diagnostics, $options, $allowSentinelSlot);
			return false;
		}

		if ($declarationCount >= PHP_INT_MAX) {
			throw new InvalidArgumentException('CSS declaration count overflows integer bounds.');
		}
		$declarationCount++;

		$current = $this->declarations[$name] ?? null;
		if ($current !== null && $current['priority'] === 'important' && $priority !== 'important') {
			if ($locationIndex !== null) {
				$this->recordParsedSpanExtent($locationIndex->span($segmentOffset, $segmentEndOffset));
			}
			return true;
		}
		if ($name === 'initial-value') {
			$sawEmptyInitialValue = false;
		}
		$oldBytes = $this->computeRemovedEntryBytes($name);
		$this->clearPendingEntryInternal($name);
		if ($current !== null) {
			unset($this->declarations[$name]);
		}
		$this->contentBytes += strlen($name) + strlen($value) - $oldBytes;
		$this->declarations[$name] = ['value' => $value, 'priority' => $priority];
		if ($locationIndex !== null) {
			$declSpan = $locationIndex->span($segmentOffset, $segmentEndOffset);
			$this->sourceSpans[$name] = $declSpan;
			$this->recordParsedSpanExtent($declSpan);
		} else {
			unset($this->sourceSpans[$name]);
		}
		if (!$this->hasShorthandLonghand && CSSShorthandRegistry::isShorthandLonghand($name)) {
			$this->hasShorthandLonghand = true;
		}
		$this->recordLogicalGroupMask($name);
		$this->cachedKeys = null;

		return true;
	}

	/**
	 * @param array{kind: 'expanded', longhands: array<string, string>}|array{kind: 'pending', pendingKind: 'substitution'|'system-font', shorthand: string, value: string, longhands: list<string>} $expanded
	 * @param list<array{message: string, offset: int}> $diagnostics
	 */
	private function commitParsedExpandedDeclaration(
		array $expanded,
		string $priority,
		int $segmentOffset,
		bool $strict,
		array &$diagnostics,
		?CSSParserOptions $options,
		int &$declarationCount,
		bool $throwOnLimit,
		bool $allowSentinelSlot,
		int $segmentEndOffset = 0,
		?CSSSourceLocationIndex $locationIndex = null,
	): bool {
		$isPending = $expanded['kind'] === 'pending';
		$longhandMap = $isPending
			? array_fill_keys($expanded['longhands'], '')
			: $expanded['longhands'];
		$expandedCount = count($longhandMap);

		if ($options !== null && $options->maxDeclarations !== null && ($declarationCount + $expandedCount) > $options->maxDeclarations) {
			$message = sprintf('Maximum CSS declaration limit of %d exceeded.', $options->maxDeclarations);
			if ($strict || $throwOnLimit) {
				throw new CSSParserLimitException($message);
			}
			self::recordDiagnostic($message, $segmentOffset, $diagnostics, $options, $allowSentinelSlot);
			return false;
		}

		foreach ($longhandMap as $lh => $_val) {
			try {
				static::assertAllowedDescriptor($lh);
			} catch (InvalidArgumentException $exception) {
				self::handleInvalidDeclaration($exception->getMessage(), $segmentOffset, $strict, $diagnostics, $options, $allowSentinelSlot);
				return true;
			}
		}

		$planned = $this->planExpandedCommit($expanded, $longhandMap, $priority, true);
		if ($options !== null && $options->maxBytes !== null && $planned['nextContentBytes'] > $options->maxBytes) {
			$message = sprintf('CSS declaration block exceeds maximum size of %d bytes.', $options->maxBytes);
			if ($strict || $throwOnLimit) {
				throw new CSSParserLimitException($message);
			}
			self::recordDiagnostic($message, $segmentOffset, $diagnostics, $options, $allowSentinelSlot);
			return false;
		}

		if ($declarationCount > PHP_INT_MAX - $expandedCount) {
			throw new InvalidArgumentException('CSS declaration count overflows integer bounds.');
		}
		$declarationCount += $expandedCount;

		if ($planned['toCommit'] === []) {
			if ($locationIndex !== null) {
				$this->recordParsedSpanExtent($locationIndex->span($segmentOffset, $segmentEndOffset));
			}
			return true;
		}

		$sourceSpan = $locationIndex?->span($segmentOffset, $segmentEndOffset);
		if ($sourceSpan !== null) {
			$this->recordParsedSpanExtent($sourceSpan);
		}
		$this->applyPlannedCommit($expanded, $planned['toCommit'], $priority, $planned['nextContentBytes'], true, $sourceSpan);

		return true;
	}

	/**
	 * @param array{kind: 'expanded', longhands: array<string, string>}|array{kind: 'pending', pendingKind: 'substitution'|'system-font', shorthand: string, value: string, longhands: list<string>} $expanded
	 */
	private function commitExpandedResult(
		array $expanded,
		string $priority,
		bool $respectExistingImportant,
		bool $enforceMaxBytes,
		?CSSParserOptions $overrideOptions,
	): void {
		$isPending = $expanded['kind'] === 'pending';
		$longhandMap = $isPending
			? array_fill_keys($expanded['longhands'], '')
			: $expanded['longhands'];

		foreach ($longhandMap as $lh => $_val) {
			static::assertAllowedDescriptor($lh);
		}

		$planned = $this->planExpandedCommit($expanded, $longhandMap, $priority, $respectExistingImportant);
		if ($enforceMaxBytes) {
			$maxBytes = $overrideOptions !== null ? $overrideOptions->maxBytes : $this->resolveMaxBytesInternal();
			if ($maxBytes !== null && $planned['nextContentBytes'] > $maxBytes) {
				throw new CSSParserLimitException(sprintf('CSS declaration block exceeds maximum size of %d bytes.', $maxBytes));
			}
		}

		$delta = $planned['newKeysCount'];
		$this->assertSheetDeclarationGrowth($delta, $overrideOptions);
		if ($planned['toCommit'] === []) {
			return;
		}

		$reappendAll = $delta > 0 || $this->shouldReappendTargetLonghands($planned['toCommit']);
		$this->applyPlannedCommit($expanded, $planned['toCommit'], $priority, $planned['nextContentBytes'], $reappendAll);
		$this->notifySheetMutation($delta, false);
	}

	/**
	 * @param array{kind: 'expanded', longhands: array<string, string>}|array{kind: 'pending', pendingKind: 'substitution'|'system-font', shorthand: string, value: string, longhands: list<string>} $expanded
	 * @param array<string, string> $longhandMap
	 * @return array{toCommit: array<string, string>, newKeysCount: int, nextContentBytes: int}
	 */
	private function planExpandedCommit(
		array $expanded,
		array $longhandMap,
		string $priority,
		bool $respectExistingImportant,
	): array {
		$isPending = $expanded['kind'] === 'pending';
		$nextContentBytes = $this->contentBytes;
		$tempRefDecrements = [];
		$toCommit = [];
		$newKeysCount = 0;

		foreach ($longhandMap as $lh => $val) {
			$current = $this->declarations[$lh] ?? null;
			if ($respectExistingImportant && $current !== null && $current['priority'] === 'important' && $priority !== 'important') {
				continue;
			}
			$toCommit[$lh] = $val;
			if ($current === null) {
				$newKeysCount++;
			} else {
				$oldPending = $this->pendingShorthands[$lh] ?? null;
				if ($oldPending !== null) {
					$nextContentBytes -= strlen($lh);
					$oldId = $oldPending['id'];
					$tempRefDecrements[$oldId] = ($tempRefDecrements[$oldId] ?? 0) + 1;
					if ($tempRefDecrements[$oldId] === $this->pendingPayloadAccounting[$oldId]['refs']) {
						$nextContentBytes -= $this->pendingPayloadAccounting[$oldId]['bytes'];
					}
				} else {
					$nextContentBytes -= strlen($lh) + strlen($current['value']);
				}
			}
			$nextContentBytes += $isPending ? strlen($lh) : (strlen($lh) + strlen($val));
		}

		if ($isPending && $toCommit !== []) {
			$nextContentBytes += strlen($expanded['shorthand']) + strlen($expanded['value']);
		}

		return [
			'toCommit' => $toCommit,
			'newKeysCount' => $newKeysCount,
			'nextContentBytes' => $nextContentBytes,
		];
	}

	/**
	 * @param array{kind: 'expanded', longhands: array<string, string>}|array{kind: 'pending', pendingKind: 'substitution'|'system-font', shorthand: string, value: string, longhands: list<string>} $expanded
	 * @param array<string, string> $toCommit
	 */
	private function applyPlannedCommit(
		array $expanded,
		array $toCommit,
		string $priority,
		int $nextContentBytes,
		bool $reappendAll,
		?CSSSourceSpan $sourceSpan = null,
	): void {
		$isPending = $expanded['kind'] === 'pending';
		$newPendingId = null;
		if ($isPending) {
			$newPendingId = $this->nextPendingId++;
			$this->pendingPayloadAccounting[$newPendingId] = [
				'bytes' => strlen($expanded['shorthand']) + strlen($expanded['value']),
				'refs' => count($toCommit),
			];
		}

		if ($reappendAll) {
			foreach ($toCommit as $lh => $_val) {
				$this->clearPendingEntryInternal($lh);
				unset($this->declarations[$lh]);
			}
		}

		if ($isPending && $toCommit !== []) {
			$this->hasShorthandLonghand = true;
		}
		foreach ($toCommit as $lh => $val) {
			if (!$reappendAll) {
				$this->clearPendingEntryInternal($lh);
			}
			$this->declarations[$lh] = ['value' => $val, 'priority' => $priority];
			if ($sourceSpan !== null) {
				$this->sourceSpans[$lh] = $sourceSpan;
			} else {
				unset($this->sourceSpans[$lh]);
			}
			if ($isPending && $newPendingId !== null) {
				$this->pendingShorthands[$lh] = [
					'shorthand' => $expanded['shorthand'],
					'value' => $expanded['value'],
					'id' => $newPendingId,
					'kind' => $expanded['pendingKind'],
				];
			}
			if (!$this->hasShorthandLonghand && CSSShorthandRegistry::isShorthandLonghand($lh)) {
				$this->hasShorthandLonghand = true;
			}
			$this->recordLogicalGroupMask($lh);
		}

		$this->contentBytes = $nextContentBytes;
		$this->cachedKeys = null;
	}

	private function removeSingleLonghandInternal(string $name): void
	{
		$oldEntry = $this->declarations[$name] ?? null;
		$delta = $oldEntry !== null ? -1 : 0;
		if ($oldEntry !== null) {
			$this->contentBytes -= $this->computeRemovedEntryBytes($name);
			$this->clearPendingEntryInternal($name);
			unset($this->declarations[$name]);
			unset($this->sourceSpans[$name]);
		}
		$this->cachedKeys = null;
		$this->notifySheetMutation($delta, $name === 'initial-value');
	}

	private function removeShorthandLonghandsInternal(string $shorthand): void
	{
		$longhands = CSSShorthandRegistry::getLonghands($shorthand);
		if ($longhands === null || $longhands === []) {
			return;
		}

		$delta = 0;
		foreach ($longhands as $lh) {
			if (!isset($this->declarations[$lh])) {
				continue;
			}
			$delta--;
			$this->contentBytes -= $this->computeRemovedEntryBytes($lh);
			$this->clearPendingEntryInternal($lh);
			unset($this->declarations[$lh]);
			unset($this->sourceSpans[$lh]);
		}
		if ($delta === 0) {
			return;
		}
		$this->cachedKeys = null;
		$this->notifySheetMutation($delta, false);
	}

	private function computeRemovedEntryBytes(string $name): int
	{
		$current = $this->declarations[$name] ?? null;
		if ($current === null) {
			return 0;
		}
		$oldPending = $this->pendingShorthands[$name] ?? null;
		if ($oldPending === null) {
			return strlen($name) + strlen($current['value']);
		}

		$bytes = strlen($name);
		$id = $oldPending['id'];
		if (($this->pendingPayloadAccounting[$id]['refs'] ?? 0) === 1) {
			$bytes += $this->pendingPayloadAccounting[$id]['bytes'];
		}

		return $bytes;
	}

	private function clearPendingEntryInternal(string $name): void
	{
		$oldPending = $this->pendingShorthands[$name] ?? null;
		if ($oldPending === null) {
			return;
		}
		$id = $oldPending['id'];
		unset($this->pendingShorthands[$name]);
		if (isset($this->pendingPayloadAccounting[$id])) {
			$this->pendingPayloadAccounting[$id]['refs']--;
			if ($this->pendingPayloadAccounting[$id]['refs'] <= 0) {
				unset($this->pendingPayloadAccounting[$id]);
			}
		}
	}

	private function recordLogicalGroupMask(string $name): void
	{
		$info = CSSShorthandRegistry::getLogicalGroup($name);
		if ($info !== null) {
			$bit = 1 << ($info['group'] - 1);
			if ($info['logical']) {
				$this->logicalGroupMask |= $bit;
			} else {
				$this->physicalGroupMask |= $bit;
			}
		}
	}

	private function shouldReappendSingleLonghand(string $name): bool
	{
		$groupInfo = CSSShorthandRegistry::getLogicalGroup($name);
		if ($groupInfo === null) {
			return false;
		}
		$bit = 1 << ($groupInfo['group'] - 1);
		$oppositeMask = $groupInfo['logical'] ? $this->physicalGroupMask : $this->logicalGroupMask;
		if (($oppositeMask & $bit) === 0) {
			return false;
		}

		$seenSelf = false;
		foreach ($this->declarations as $prop => $_entry) {
			if ($prop === $name) {
				$seenSelf = true;
				continue;
			}
			if (!$seenSelf) {
				continue;
			}
			$otherGroup = CSSShorthandRegistry::getLogicalGroup($prop);
			if ($otherGroup !== null
				&& $otherGroup['group'] === $groupInfo['group']
				&& $otherGroup['logical'] !== $groupInfo['logical']
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array<string, string> $targetLonghands
	 */
	private function shouldReappendTargetLonghands(array $targetLonghands): bool
	{
		if ($this->physicalGroupMask === 0 && $this->logicalGroupMask === 0) {
			return false;
		}

		$needOppositeFromPhysical = 0;
		$needOppositeFromLogical = 0;
		$targetGroups = [];
		foreach ($targetLonghands as $lh => $_val) {
			$info = CSSShorthandRegistry::getLogicalGroup($lh);
			if ($info !== null) {
				$bit = 1 << ($info['group'] - 1);
				if ($info['logical']) {
					$needOppositeFromPhysical |= $bit;
				} else {
					$needOppositeFromLogical |= $bit;
				}
				$targetGroups[$info['group']] = $info['logical'];
			}
		}
		if ($targetGroups === []
			|| (($needOppositeFromPhysical & $this->physicalGroupMask) === 0
				&& ($needOppositeFromLogical & $this->logicalGroupMask) === 0)
		) {
			return false;
		}

		$seenFirstTarget = false;
		foreach ($this->declarations as $prop => $_entry) {
			if (isset($targetLonghands[$prop])) {
				$seenFirstTarget = true;
				continue;
			}
			if (!$seenFirstTarget) {
				continue;
			}
			$otherGroup = CSSShorthandRegistry::getLogicalGroup($prop);
			if ($otherGroup !== null
				&& isset($targetGroups[$otherGroup['group']])
				&& $targetGroups[$otherGroup['group']] !== $otherGroup['logical']
			) {
				return true;
			}
		}

		return false;
	}

	private static function propertyNameFromAccessor(string $name): string
	{
		if (isset(self::$accessorCache[$name])) {
			return self::$accessorCache[$name];
		}

		$converted = strtolower((string) preg_replace('/[A-Z]/', '-$0', $name));
		if (str_starts_with($converted, 'webkit-')
			|| str_starts_with($converted, 'moz-')
			|| str_starts_with($converted, 'ms-')
			|| str_starts_with($converted, 'o-')
		) {
			$converted = '-' . $converted;
		}
		if (count(self::$accessorCache) >= self::MAX_ACCESSOR_CACHE) {
			self::$accessorCache = ['cssFloat' => 'float'];
		}

		return self::$accessorCache[$name] = $converted;
	}

	/** @param list<array{message: string, offset: int}> $diagnostics */
	private function parseInto(
		string $cssText,
		bool $strict,
		array &$diagnostics,
		int $baseOffset,
		?CSSParserOptions $options = null,
		bool $allowEmptyInitialValue = false,
		bool &$sawEmptyInitialValue = false,
		?bool $validatePropertyGrammar = null,
	): void {
		$validatePropertyGrammar ??= $this->shouldValidatePropertyValuesInternal();
		if ($baseOffset < 0) {
			throw new InvalidArgumentException('Base offset cannot be negative.');
		}
		$options ??= CSSParserOptions::default();
		$browserMode = !$strict && $options->browserErrorRecovery;

		if ($browserMode) {
			try {
				$source = CSSInputPreprocessor::preprocess($cssText, $options);
			} catch (CSSParserLimitException $exception) {
				$diagnostics[] = ['message' => $exception->getMessage(), 'offset' => $baseOffset];
				return;
			}
			$startOffset = 0;
			$checkControlBytes = false;
		} else {
			if ($options->maxBytes !== null && strlen($cssText) > $options->maxBytes) {
				$message = sprintf('CSS declaration block exceeds maximum size of %d bytes.', $options->maxBytes);
				if ($strict) {
					throw new CSSParserLimitException($message);
				}
				$diagnostics[] = ['message' => $message, 'offset' => $baseOffset];
				return;
			}
			$source = $cssText;
			$startOffset = str_starts_with($source, "\xEF\xBB\xBF") ? 3 : 0;
			$checkControlBytes = true;
		}

		$this->resetState();
		$length = strlen($source);
		$declarationCount = 0;
		$initialDiagCount = count($diagnostics);
		$sawEmptyInitialValue = false;
		$locationIndex = $options->trackSpans ? new CSSSourceLocationIndex($source, $startOffset) : null;

		$tokenizer = null;
		$pos = $startOffset;

		while ($pos < $length) {
			$span = strcspn($source, self::FAST_PATH_UNSAFE_BYTES, $pos, $length - $pos);
			$stop = $pos + $span;

			if (($stop >= $length || $source[$stop] === ';')
				&& ($cleanSlice = self::extractCleanAsciiSliceInternal($source, $pos, $stop, $options->maxDepth)) !== null
			) {
				$ok = $this->parseCleanAsciiDeclarationInternal(
					$cleanSlice,
					$pos,
					$strict,
					$diagnostics,
					$options,
					$declarationCount,
					$strict,
					false,
					$allowEmptyInitialValue,
					$sawEmptyInitialValue,
					$validatePropertyGrammar,
					$locationIndex,
				);
				if (!$ok) {
					break;
				}
				$pos = $stop < $length ? $stop + 1 : $length;
				$tokenizer?->setOffsetInternal($pos);
				continue;
			}

			$tokenizer ??= CSSTokenizer::forParserInternal($source, $options, $browserMode, $checkControlBytes, $pos, $length);
			$tokenizer->setOffsetInternal($pos);

			// Token-driven CSS Syntax L3 §5 declaration slice consumption
			$tokens = [];
			$blockStack = [];
			$hasComments = false;
			$controlByteOffset = null;
			$badTokenOffset = null;
			$badTokenMessage = null;
			$hasUnclosedConstruct = false;
			$hasTopLevelBraces = false;
			$maxDepth = $options->maxDepth;
			$limitErrorOffset = null;
			$limitErrorMessage = null;
			$sliceStart = $pos;
			$sliceEnd = $length;

			while (true) {
				$type = $tokenizer->advanceInternal();
				if ($tokenizer->tokenHadLeadingComment) {
					$hasComments = true;
				}
				if ($checkControlBytes && $tokenizer->commentControlErrorOffsets !== []) {
					$controlByteOffset ??= $tokenizer->commentControlErrorOffsets[0];
				}
				if ($tokenizer->tokenUnclosedCommentOffset !== null) {
					$hasUnclosedConstruct = true;
				}
				if ($tokenizer->tokenControlByteOffset !== null) {
					$controlByteOffset ??= $tokenizer->tokenControlByteOffset;
				}
				if ($type === CSSTokenType::Eof) {
					$sliceEnd = $length;
					break;
				}
				if ($type === CSSTokenType::Semicolon && $blockStack === []) {
					$sliceEnd = $tokenizer->tokenStart;
					break;
				}
				if ($type === CSSTokenType::BadString || $type === CSSTokenType::BadUrl) {
					$badTokenOffset ??= $tokenizer->tokenStart;
					$badTokenMessage ??= $type === CSSTokenType::BadString
						? 'Invalid CSS string token.'
						: 'Invalid CSS url() token.';
				}
				if ($tokenizer->tokenClosedAtEof) {
					$hasUnclosedConstruct = true;
				}
				if (!$browserMode && $tokenizer->tokenParseErrorMessage !== null) {
					$badTokenOffset ??= $tokenizer->tokenStart;
					$badTokenMessage ??= $tokenizer->tokenParseErrorMessage;
				}

				$openedCloser = null;
				if ($type === CSSTokenType::LeftParenthesis || $type === CSSTokenType::Function) {
					$openedCloser = ')';
				} elseif ($type === CSSTokenType::LeftSquareBracket) {
					$openedCloser = ']';
				} elseif ($type === CSSTokenType::LeftCurlyBracket) {
					if ($blockStack === []) {
						$hasTopLevelBraces = true;
					}
					$openedCloser = '}';
				}
				if ($openedCloser !== null) {
					$blockStack[] = $openedCloser;
					if ($maxDepth !== null && count($blockStack) > $maxDepth) {
						$limitMsg = sprintf('Maximum CSS nesting depth of %d exceeded.', $maxDepth);
						if ($strict) {
							throw new CSSParserLimitException($limitMsg);
						}
						$limitErrorOffset ??= $tokenizer->tokenStart;
						$limitErrorMessage ??= $limitMsg;
					}
				} elseif ($type === CSSTokenType::RightParenthesis
					|| $type === CSSTokenType::RightSquareBracket
					|| $type === CSSTokenType::RightCurlyBracket
				) {
					$closer = match ($type) {
						CSSTokenType::RightParenthesis => ')',
						CSSTokenType::RightSquareBracket => ']',
						CSSTokenType::RightCurlyBracket => '}',
					};
					if ($blockStack === []) {
						$hasTopLevelBraces = true;
					} elseif (end($blockStack) === $closer) {
						array_pop($blockStack);
						if ($blockStack === [] && $closer === '}') {
							$hasTopLevelBraces = true;
						}
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
			}

			if ($limitErrorMessage !== null) {
				self::recordDiagnostic($limitErrorMessage, $limitErrorOffset ?? $sliceStart, $diagnostics, $options, false);
				$pos = $tokenizer->getOffsetInternal();
				continue;
			}

			// In default mode, catch any raw control bytes inside the slice; in browser mode, check only non-comment token spans.
			if (!$browserMode && $controlByteOffset === null && CSSSyntaxScanner::hasControlCharactersInSlice($source, $sliceStart, $sliceEnd - $sliceStart)) {
				$controlByteOffset = $sliceStart;
			} elseif ($browserMode && $controlByteOffset === null) {
				if (!$hasComments) {
					if (CSSSyntaxScanner::hasControlCharactersInSlice($source, $sliceStart, $sliceEnd - $sliceStart)) {
						$controlByteOffset = $sliceStart;
					}
				} else {
					foreach ($tokens as $tok) {
						$tokLen = $tok['end'] - $tok['start'];
						if ($tokLen > 0 && CSSSyntaxScanner::hasControlCharactersInSlice($source, $tok['start'], $tokLen)) {
							$controlByteOffset = $tok['start'];
							break;
						}
					}
				}
			}

			if ($tokens === []) {
				if ($controlByteOffset !== null) {
					self::handleInvalidDeclaration('Disallowed control characters in CSS declaration.', $sliceStart, $strict, $diagnostics, $options, false);
				} elseif ($hasUnclosedConstruct && !$browserMode) {
					self::handleInvalidDeclaration('Unclosed comment or string in CSS declaration.', $sliceStart, $strict, $diagnostics, $options, false);
				}
				$pos = $tokenizer->getOffsetInternal();
				continue;
			}

			$ok = $this->parseDeclarationFromTokensInternal(
				$source,
				$sliceStart,
				$sliceEnd,
				$tokens,
				$hasComments,
				$controlByteOffset,
				$badTokenOffset,
				$badTokenMessage,
				$hasUnclosedConstruct,
				$blockStack,
				$hasTopLevelBraces,
				$strict,
				$diagnostics,
				$options,
				$declarationCount,
				$strict,
				false,
				$allowEmptyInitialValue,
				$sawEmptyInitialValue,
				$browserMode,
				$validatePropertyGrammar,
				$locationIndex,
			);
			if (!$ok) {
				break;
			}
			$pos = $tokenizer->getOffsetInternal();
		}

		if ($baseOffset !== 0) {
			$totalDiag = count($diagnostics);
			for ($i = $initialDiagCount; $i < $totalDiag; $i++) {
				if ($diagnostics[$i]['offset'] > PHP_INT_MAX - $baseOffset) {
					throw new InvalidArgumentException('Diagnostic offset overflows integer bounds.');
				}
				$diagnostics[$i] = [
					'message' => $diagnostics[$i]['message'],
					'offset' => $baseOffset + $diagnostics[$i]['offset'],
				];
			}
			if ($locationIndex !== null) {
				$this->shiftTrackedSpansForBaseOffset($baseOffset);
			}
		}
	}

	private function shiftTrackedSpansForBaseOffset(int $baseOffset): void
	{
		$shiftedCache = [];
		foreach ($this->sourceSpans as $prop => $span) {
			$id = spl_object_id($span);
			if (!isset($shiftedCache[$id])) {
				if ($span->endOffset > PHP_INT_MAX - $baseOffset) {
					throw new InvalidArgumentException('Source span offset overflows integer bounds.');
				}
				$shiftedCache[$id] = new CSSSourceSpan(
					$baseOffset + $span->startOffset,
					$baseOffset + $span->endOffset,
					$span->startLine,
					$span->startColumn,
					$span->endLine,
					$span->endColumn,
				);
			}
			$this->sourceSpans[$prop] = $shiftedCache[$id];
		}
		if ($this->parsedBlockSpan !== null) {
			$curSpan = $this->parsedBlockSpan;
			if ($curSpan->endOffset > PHP_INT_MAX - $baseOffset) {
				throw new InvalidArgumentException('Source span offset overflows integer bounds.');
			}
			$this->parsedBlockSpan = new CSSSourceSpan(
				$baseOffset + $curSpan->startOffset,
				$baseOffset + $curSpan->endOffset,
				$curSpan->startLine,
				$curSpan->startColumn,
				$curSpan->endLine,
				$curSpan->endColumn,
			);
		}
	}

	/** @param list<array{message: string, offset: int}> $diagnostics */
	private static function handleInvalidDeclaration(
		string $message,
		int $offset,
		bool $strict,
		array &$diagnostics,
		?CSSParserOptions $options = null,
		bool $allowSentinelSlot = false,
	): void {
		self::recordDiagnostic($message, $offset, $diagnostics, $options, $allowSentinelSlot);
		if ($strict) {
			throw new InvalidArgumentException($message);
		}
	}

	/** @param list<array{message: string, offset: int}> $diagnostics */
	private static function recordDiagnostic(
		string $message,
		int $offset,
		array &$diagnostics,
		?CSSParserOptions $options,
		bool $allowSentinelSlot = false,
	): void {
		$maxErrors = $options?->maxErrors;
		if ($maxErrors !== null && ($allowSentinelSlot ? count($diagnostics) > $maxErrors : count($diagnostics) >= $maxErrors)) {
			return;
		}
		$diagnostics[] = ['message' => $message, 'offset' => $offset];
	}
}
