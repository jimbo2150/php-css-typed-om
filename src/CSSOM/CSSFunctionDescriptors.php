<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

/**
 * @property string $result
 */
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

	public function __isset(string $name): bool
	{
		if ($name === 'result') {
			return true;
		}
		return parent::__isset($name);
	}
}
