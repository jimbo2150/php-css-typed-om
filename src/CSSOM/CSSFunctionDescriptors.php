<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

final class CSSFunctionDescriptors extends CSSStyleDeclaration
{
	public function getResult(): string
	{
		return $this->getPropertyValue('result');
	}

	public function __get(string $name): mixed
	{
		return $name === 'result' ? $this->getResult() : parent::__get($name);
	}
}
