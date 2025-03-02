<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\WebCore\css\values;

abstract class SerializationContext
{
	/**
	 * @param UncheckedKeyHashMap<string,string> $hm
	 * @param UncheckedKeyHashMap<CSSStyleSheet> $hmStyleSheet
	 */
	abstract public function __construct(
		UncheckedKeyHashMap $hm,
		UncheckedKeyHashMap $hmStyleSheet,
		bool $bool,
	);

	/** @var UncheckedKeyHashMap<string,string> */
	protected UncheckedKeyHashMap $replacementURLStrings;

	// UncheckedKeyHashMap<Ref<CSSStyleSheet>, String>
	/** @var UncheckedKeyHashMap<CSSStyleSheet,string> */
	protected $replacementURLStringsForCSSStyleSheet;
	protected bool $shouldUseResolvedURLInCSSText = false;
}
