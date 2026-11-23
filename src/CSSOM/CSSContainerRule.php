<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;

/**
 * Represents a CSS `@container` rule.
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

	public function __construct(string $conditionText)
	{
		parent::__construct('container', $conditionText);
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

		$tokens = CSSSyntaxScanner::splitTopLevelWhitespace($prelude);
		if ($tokens === []) {
			return $this->parsedPrelude = ['', ''];
		}

		$first = $tokens[0];
		if (!self::isValidContainerName($first)) {
			return $this->parsedPrelude = ['', $prelude];
		}

		if (count($tokens) === 1) {
			return $this->parsedPrelude = [$first, ''];
		}

		$rest = ltrim(substr($prelude, strlen($first)), CSSSyntaxScanner::CSS_WHITESPACE);
		return $this->parsedPrelude = [$first, $rest];
	}

	private static function isValidContainerName(string $token): bool
	{
		if ($token === '' || str_contains($token, '(') || str_contains($token, ')')) {
			return false;
		}
		if (preg_match('/^(?:--|-?(?:[_a-zA-Z]|[^\x00-\x7F]|' . CSSSyntaxScanner::IDENT_ESCAPE_REGEX . '))(?:[-_a-zA-Z0-9]|[^\x00-\x7F]|' . CSSSyntaxScanner::IDENT_ESCAPE_REGEX . ')*$/uD', $token) !== 1) {
			return false;
		}
		$decoded = str_contains($token, '\\') ? CSSSyntaxScanner::decodeCssIdentEscapes($token) : $token;

		return !isset(self::RESERVED_KEYWORDS[strtolower($decoded)]);
	}
}
