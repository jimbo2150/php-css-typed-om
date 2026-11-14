<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

final class CSSNamespaceRule extends CSSAtRule
{
	public function __construct(string $prelude)
	{
		parent::__construct('namespace', $prelude);
	}

	public function getPrefix(): string
	{
		$parts = preg_split('/\s+/', trim($this->prelude), 2) ?: [];
		if (count($parts) < 2) {
			return '';
		}
		return $parts[0];
	}

	public function getNamespaceURI(): string
	{
		$prefix = $this->getPrefix();
		$uri = $prefix === '' ? trim($this->prelude) : trim(substr(trim($this->prelude), strlen($prefix)));
		if (preg_match('/^url\((.*)\)$/is', $uri, $match)) {
			$uri = trim($match[1]);
		}
		return trim($uri, "\"'");
	}

	public function getType(): int
	{
		return self::NAMESPACE_RULE;
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'prefix' => $this->getPrefix(),
			'namespaceURI' => $this->getNamespaceURI(),
			default => parent::__get($name),
		};
	}
}
