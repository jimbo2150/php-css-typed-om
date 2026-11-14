<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

final class CSSCounterStyleRule extends CSSAtRule
{
	public function __construct(string $name, CSSStyleDeclaration $descriptors)
	{
		parent::__construct('counter-style', $name, $descriptors, true);
	}

	public function getNameText(): string { return $this->prelude; }
	public function getDescriptor(string $name): string { return $this->style?->getPropertyValue($name) ?? ''; }

	public function __get(string $name): mixed
	{
		if ($name === 'name') {
			return $this->getNameText();
		}
		$descriptor = strtolower(preg_replace('/[A-Z]/', '-$0', $name) ?? $name);
		return $this->getDescriptor($descriptor);
	}
}
