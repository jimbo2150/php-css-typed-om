<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSKeywordValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use ReflectionClass;

/**
 * Shared internal boilerplate for three-channel W3C Typed OM color classes.
 *
 * Classes using this trait MUST declare `private const string SPACE_NAME`.
 *
 * @internal
 */
trait ThreeChannelColorTrait
{
	private CSSNumericValue|CSSKeywordValue $c0Value;
	private CSSNumericValue|CSSKeywordValue $c1Value;
	private CSSNumericValue|CSSKeywordValue $c2Value;

	/**
	 * @internal Fast factory for already-validated, caller-owned channel objects.
	 */
	public static function fromValidated(
		CSSNumericValue|CSSKeywordValue $c0,
		CSSNumericValue|CSSKeywordValue $c1,
		CSSNumericValue|CSSKeywordValue $c2,
		CSSNumericValue|CSSKeywordValue $alpha,
	): self {
		/** @var ReflectionClass<self>|null $reflection */
		static $reflection = null;
		$reflection ??= new ReflectionClass(self::class);
		/** @var self $instance */
		$instance = $reflection->newInstanceWithoutConstructor();
		$instance->c0Value = $c0;
		$instance->c1Value = $c1;
		$instance->c2Value = $c2;
		$instance->alphaValue = $alpha;

		return $instance;
	}

	public function __clone(): void
	{
		$this->c0Value = $this->c0Value->clone();
		$this->c1Value = $this->c1Value->clone();
		$this->c2Value = $this->c2Value->clone();
		$this->alphaValue = $this->alphaValue->clone();
	}

	/** @internal */
	public function getSpaceNameInternal(): string
	{
		return self::SPACE_NAME;
	}

	/** @internal */
	public function getComponentValuesInternal(): array
	{
		return [$this->c0Value, $this->c1Value, $this->c2Value, $this->alphaValue];
	}

	/** @internal */
	public function toBoundedCssTextInternal(?int $maxBytes): string
	{
		return $this->serializeFunctionChannelsBounded(self::SPACE_NAME, [$this->c0Value, $this->c1Value, $this->c2Value], $maxBytes);
	}
}
