<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;

/**
 * Represents a CSS `@container` rule.
 *
 * @property-read string $containerName
 * @property-read string $containerQuery
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSContainerRule
 */
final class CSSContainerRule extends CSSConditionRule
{
	/** @var array<string, true> Keywords disallowed as `<container-name>` per CSS Containment 3 */
	private const array RESERVED_KEYWORDS = [
		'none' => true,
		'and' => true,
		'not' => true,
		'or' => true,
		'initial' => true,
		'inherit' => true,
		'unset' => true,
		'revert' => true,
		'revert-layer' => true,
		'default' => true,
	];

	/** @var ?array{string, string} */
	private ?array $parsedPrelude = null;

	public function __construct(string $conditionText, ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES)
	{
		$this->initConditionRuleInternal('container', $conditionText, $maxBytes, true);
	}

	protected function requiresNonEmptyCondition(): bool
	{
		return true;
	}

	public function getType(): int
	{
		return self::CONTAINER_RULE;
	}

	public function setConditionText(string $conditionText): void
	{
		parent::setConditionText($conditionText);
		$this->parsedPrelude = null;
	}

	public function getContainerName(): string
	{
		[$name] = $this->splitPrelude();
		return $name;
	}

	public function getContainerQuery(): string
	{
		[, $query] = $this->splitPrelude();
		return $query;
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'containerName' => $this->getContainerName(),
			'containerQuery' => $this->getContainerQuery(),
			default => parent::__get($name),
		};
	}

	public function __isset(string $name): bool
	{
		return match ($name) {
			'containerName', 'containerQuery' => true,
			default => parent::__isset($name),
		};
	}

	/** @return array{string, string} */
	private function splitPrelude(): array
	{
		if ($this->parsedPrelude !== null) {
			return $this->parsedPrelude;
		}

		$raw = CSSSyntaxScanner::trimCssWhitespace($this->prelude);
		$prelude = str_contains($raw, '/*')
			? CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($raw))
			: $raw;
		if ($prelude === '') {
			return $this->parsedPrelude = ['', ''];
		}

		$length = strlen($prelude);
		$decoded = CSSSyntaxScanner::decodeIdentAt($prelude, 0, $length);
		if ($decoded === null
			|| ($decoded['end'] !== $length && !str_contains(CSSSyntaxScanner::CSS_WHITESPACE, $prelude[$decoded['end']]))
			|| CSSSyntaxScanner::hasControlCharacters($decoded['ident'])
			|| isset(self::RESERVED_KEYWORDS[strtolower($decoded['ident'])])
		) {
			return $this->parsedPrelude = ['', $prelude];
		}

		$first = substr($prelude, 0, $decoded['end']);
		$rest = $decoded['end'] === $length
			? ''
			: CSSSyntaxScanner::trimCssWhitespace(substr($prelude, $decoded['end']));

		return $this->parsedPrelude = [$first, $rest];
	}
}
