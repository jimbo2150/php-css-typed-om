<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

final class CSSPositionTryRule extends CSSAtRule
{
	public const int POSITION_TRY_RULE = 21;

	public function __construct(string $name, CSSStyleDeclaration $style)
	{
		$descriptors = new CSSPositionTryDescriptors();
		foreach ($style as $property => $declaration) {
			$descriptors->setProperty($property, $declaration['value'], $declaration['priority']);
		}
		parent::__construct('position-try', $name, $descriptors, true);
	}

	public function getNameText(): string
	{
		return $this->prelude;
	}

	public function getStyle(): ?CSSPositionTryDescriptors
	{
		return $this->style;
	}

	public function getType(): int
	{
		return self::POSITION_TRY_RULE;
	}

	public function __get(string $name): mixed
	{
		return $name === 'name' ? $this->getNameText() : parent::__get($name);
	}
}
