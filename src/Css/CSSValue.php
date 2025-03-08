<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Css;

use Jimbo2150\PhpCssTypedOm\WebCore\css\ComputedStyleDependencies;
use Jimbo2150\PhpCssTypedOm\WTF\wtf\IterationStatus;

class CSSValue
{
	protected const int ClassTypeBits = 7;
	protected const int ValueSeparatorBits = 2;
	protected int $m_primitiveUnitType = 7; // CSSUnitType
	protected int $m_hasCachedCSSText = 1;
	protected int $m_isImplicitInitialValue = 1;

	// CSSValueList and CSSValuePair:
	protected int $m_valueSeparator = 0;
	private int $m_refCount;
	private ClassType $m_classType;

	public function classType(): ClassType
	{
		return $this->m_classType;
	}

	public function __construct(ClassType $classType)
	{
		$this->m_classType = $classType;
	}

	public function visitDerived(callable $visitor)
	{
		return $visitor($this);
	}

	/**
	 * @param callable<bool(CachedResource&)>|null $cached
	 */
	public function customTraverseSubresources(?callable &$cached = null): bool
	{
		return false;
	}

	/**
	 * @param callable<CachedResource> $handler
	 */
	public function traverseSubresources(callable &$handler): bool
	{
		return $this->visitDerived(function (self $value) use (&$handler) {
			return $value->customTraverseSubresources($handler);
		});
	}

	/**
	 * @param callable<IterationStatus(self)> $func
	 */
	public function visitChildren(callable &$func): IterationStatus
	{
		return $this->visitDerived(function (self $value) use (&$func) {
			return $value->customVisitChildren($func);
		});
	}

	/**
	 * @param callable<IterationStatus(CSSValue)> $callable
	 */
	public function customVisitChildren(callable &$callable): IterationStatus
	{
		return IterationStatus::Continue;
	}

	public function mayDependOnBaseURL(): bool
	{
		return $this->visitDerived(function (self $value) {
			return $value->customMayDependOnBaseURL();
		});
	}

	public function customMayDependOnBaseURL(): bool
	{
		return false;
	}

	public function computedStyleDependencies(): ComputedStyleDependencies
	{
		/** @var ComputedStyleDependencies $dependencies */
		$this->collectComputedStyleDependencies($dependencies);

		return $dependencies;
	}

	public function collectComputedStyleDependencies(
		ComputedStyleDependencies &$dependencies,
	): void {
		// FIXME: Unclear why it's OK that we do not cover CSSValuePair, CSSQuadValue, CSSRectValue, CSSBorderImageSliceValue, CSSBorderImageWidthValue, and others here. Probably should use visitDerived unless they don't allow the primitive values that can have dependencies. May want to base this on a traverseValues or forEachValue function instead.
		// FIXME: Consider a non-recursive algorithm for walking this tree of dependencies.
		if ($asList = $this) {
			foreach ($asList as $listValue) {
				$listValue->collectComputedStyleDependencies($dependencies);
			}

			return;
		}
		/** @var CSSPrimativeValue */
		if ($asPrimitiveValue = $this) {
			$asPrimitiveValue->collectComputedStyleDependencies($dependencies);
		}
	}

	public function canResolveDependenciesWithConversionData(
		CSSToLengthConversionData &$conversionData,
	): bool {
		return $this->computedStyleDependencies()->
			canResolveDependenciesWithConversionData($conversionData);
	}

	public function equals(CSSValue &$other): bool
	{
		if ($this->classType() == $other->classType()) {
			return $this->visitDerived(
				function (ValueType &$typedThis) use (&$other): bool {
					return $typedThis->equals($other);
				}
			);
		}
		/** @var CSSValueList */
		if ($thisList = $this) {
			return $thisList->containsSingleEqualItem($other);
		}
		if ($otherList = $other) {
			return $otherList->containsSingleEqualItem($this);
		}

		return false;
	}

	public function addHash(Hasher &$hasher): bool
	{
		// To match equals() a single item list could have the same hash as the item.
		// FIXME: Some Style::Builder functions can only handle list values.

		$this->add($hasher, $this->classType());

		return $this->visitDerived(function (self $typedThis) {
			return $typedThis->addDerivedHash($hasher);
		});
	}

	// FIXME: Add custom hash functions for all derived classes and remove this function.
	public function addDerivedHash(Hasher &$hasher): bool
	{
		$this->add($hasher, $this);

		return false;
	}

	public function isCSSLocalURL(StringView $relativeURL): bool
	{
		return $relativeURL->isEmpty() || $relativeURL->startsWith('#');
	}

	public function cssText(?SerializationContext &$context = null): string
	{
		return $this->visitDerived(
			function (self $value) use (&$context): string {
				return $value->customCSSText($context);
			}
		);
	}

	public function separatorCSSText(ValueSeparator $separator): string
	{
		switch ($separator) {
			case ValueSeparator::SpaceSeparator:
				return ' ';
			case ValueSeparator::CommaSeparator:
				return ', ';
			case ValueSeparator::SlashSeparator:
				return ' / ';
		}

		return ' ';
	}

	public function add(Hasher &$hasher, CSSValue &$value): void
	{
		$value->addHash($hasher);
	}
}
