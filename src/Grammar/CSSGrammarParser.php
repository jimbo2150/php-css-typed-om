<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Grammar;

use InvalidArgumentException;

/**
 * Iterative (non-recursive) parser for the W3C CSS Values & Units Level 4
 * Value Definition Syntax (VDS).
 *
 * Enforces CSS Values 4 §2.3 precedence:
 *   Atom / Group / Function / SquareBracketBlock (+ postfix `!` and multipliers)
 *   > Juxtaposition (`Sequence`) > `&&` (`AllOrder`) > `||` (`AnyOrder`) > `|` (`Alternative`)
 *
 * @internal
 */
final class CSSGrammarParser
{
	public const int MAX_GRAMMAR_DEPTH = 64;
	public const int MAX_GRAMMAR_NODES = 2_048;

	private const int TOK_IDENT = 1;
	private const int TOK_FUNCTION_OPEN = 2;
	private const int TOK_LBRACKET = 3;
	private const int TOK_RBRACKET = 4;
	private const int TOK_RPAREN = 5;
	private const int TOK_SQ_LBRACKET = 6;
	private const int TOK_SQ_RBRACKET = 7;
	private const int TOK_DATA_TYPE = 8;
	private const int TOK_PROP_REF = 9;
	private const int TOK_DELIM = 10;
	private const int TOK_ZERO = 11;
	private const int TOK_COMB_ALL = 12;
	private const int TOK_COMB_ANY = 13;
	private const int TOK_COMB_ALT = 14;
	private const int TOK_BANG = 15;
	private const int TOK_MULT = 16;

	/** Monotonic counter across all parsed grammars to keep node IDs globally unique. */
	private static int $nextNodeId = 1;

	/**
	 * @throws InvalidArgumentException When the VDS grammar string is invalid or exceeds limits
	 */
	public static function parse(string $grammar, bool $caseSensitiveKeywords = false): CSSGrammarNode
	{
		$tokens = self::tokenizeVds($grammar, $caseSensitiveKeywords);
		if ($tokens === []) {
			throw new InvalidArgumentException('CSS grammar string cannot be empty.');
		}

		$nodeCount = 0;
		$stack = [
			[
				'kind' => 'root',
				'fnName' => null,
				'alts' => [],
				'any' => [],
				'all' => [],
				'seq' => [],
			],
		];

		$tokenCount = count($tokens);
		$i = 0;

		while ($i < $tokenCount) {
			$tok = $tokens[$i];
			$kind = $tok['type'];
			$topIdx = count($stack) - 1;

			if ($kind === self::TOK_COMB_ALL) {
				$seqNode = self::reduceSequence($stack[$topIdx]['seq'], $nodeCount, $grammar);
				$stack[$topIdx]['seq'] = [];
				$stack[$topIdx]['all'][] = $seqNode;
				$i++;
				continue;
			}

			if ($kind === self::TOK_COMB_ANY) {
				$seqNode = self::reduceSequence($stack[$topIdx]['seq'], $nodeCount, $grammar);
				$stack[$topIdx]['seq'] = [];
				$stack[$topIdx]['all'][] = $seqNode;
				$allNode = self::reduceAllOrder($stack[$topIdx]['all'], $nodeCount);
				$stack[$topIdx]['all'] = [];
				$stack[$topIdx]['any'][] = $allNode;
				$i++;
				continue;
			}

			if ($kind === self::TOK_COMB_ALT) {
				$seqNode = self::reduceSequence($stack[$topIdx]['seq'], $nodeCount, $grammar);
				$stack[$topIdx]['seq'] = [];
				$stack[$topIdx]['all'][] = $seqNode;
				$allNode = self::reduceAllOrder($stack[$topIdx]['all'], $nodeCount);
				$stack[$topIdx]['all'] = [];
				$stack[$topIdx]['any'][] = $allNode;
				$anyNode = self::reduceAnyOrder($stack[$topIdx]['any'], $nodeCount);
				$stack[$topIdx]['any'] = [];
				$stack[$topIdx]['alts'][] = $anyNode;
				$i++;
				continue;
			}

			if ($kind === self::TOK_LBRACKET) {
				if (count($stack) >= self::MAX_GRAMMAR_DEPTH) {
					throw new InvalidArgumentException('CSS grammar exceeds maximum nesting depth.');
				}
				$stack[] = [
					'kind' => 'group',
					'fnName' => null,
					'alts' => [],
					'any' => [],
					'all' => [],
					'seq' => [],
				];
				$i++;
				continue;
			}

			if ($kind === self::TOK_SQ_LBRACKET) {
				if (count($stack) >= self::MAX_GRAMMAR_DEPTH) {
					throw new InvalidArgumentException('CSS grammar exceeds maximum nesting depth.');
				}
				$stack[] = [
					'kind' => 'square',
					'fnName' => null,
					'alts' => [],
					'any' => [],
					'all' => [],
					'seq' => [],
				];
				$i++;
				continue;
			}

			if ($kind === self::TOK_FUNCTION_OPEN) {
				if (count($stack) >= self::MAX_GRAMMAR_DEPTH) {
					throw new InvalidArgumentException('CSS grammar exceeds maximum nesting depth.');
				}
				$stack[] = [
					'kind' => 'function',
					'fnName' => (string) $tok['value'],
					'alts' => [],
					'any' => [],
					'all' => [],
					'seq' => [],
				];
				$i++;
				continue;
			}

			if ($kind === self::TOK_RBRACKET || $kind === self::TOK_RPAREN || $kind === self::TOK_SQ_RBRACKET) {
				if (count($stack) <= 1) {
					throw new InvalidArgumentException('Unmatched closing delimiter in CSS grammar: ' . $grammar);
				}
				$frame = array_pop($stack);
				$expectedClose = match ($frame['kind']) {
					'group' => self::TOK_RBRACKET,
					'function' => self::TOK_RPAREN,
					'square' => self::TOK_SQ_RBRACKET,
					default => throw new InvalidArgumentException('Unexpected group kind in CSS grammar.'),
				};
				if ($kind !== $expectedClose) {
					throw new InvalidArgumentException('Mismatched delimiter in CSS grammar: ' . $grammar);
				}

				$allowEmpty = $frame['kind'] === 'function' || $frame['kind'] === 'square';
				$innerNode = self::reduceFrame($frame, $nodeCount, $grammar, $allowEmpty);
				$i++;

				$node = match ($frame['kind']) {
					'group' => $innerNode,
					'function' => self::allocNode($nodeCount, CSSGrammarNodeType::FunctionCall, $frame['fnName'], [$innerNode]),
					'square' => self::allocNode($nodeCount, CSSGrammarNodeType::SquareBracketBlock, null, [$innerNode]),
				};

				if ($frame['kind'] === 'group' && $i < $tokenCount && $tokens[$i]['type'] === self::TOK_BANG) {
					$node = self::allocNode($nodeCount, CSSGrammarNodeType::RequiredGroup, null, [$node]);
					$i++;
				}

				if ($i < $tokenCount && $tokens[$i]['type'] === self::TOK_MULT) {
					$node = self::wrapMultiplier($node, $tokens[$i], $nodeCount);
					$i++;
				}

				$stack[count($stack) - 1]['seq'][] = $node;
				continue;
			}

			$atom = match ($kind) {
				self::TOK_IDENT => self::allocNode($nodeCount, CSSGrammarNodeType::Keyword, (string) $tok['value'], [], 1, 1, false, null, null, [], $caseSensitiveKeywords),
				self::TOK_DELIM => self::allocNode($nodeCount, CSSGrammarNodeType::Delim, (string) $tok['value']),
				self::TOK_ZERO => self::allocNode($nodeCount, CSSGrammarNodeType::DataType, 'zero'),
				self::TOK_PROP_REF => self::allocNode($nodeCount, CSSGrammarNodeType::NonTerminal, '@prop:' . (string) $tok['value']),
				self::TOK_DATA_TYPE => self::buildAngleBracketNode($tok, $nodeCount),
				default => throw new InvalidArgumentException('Unexpected token in CSS grammar: ' . $grammar),
			};
			$i++;

			if ($i < $tokenCount && $tokens[$i]['type'] === self::TOK_MULT) {
				$atom = self::wrapMultiplier($atom, $tokens[$i], $nodeCount);
				$i++;
			}

			$stack[$topIdx]['seq'][] = $atom;
		}

		if (count($stack) !== 1) {
			throw new InvalidArgumentException('Unclosed group or function in CSS grammar: ' . $grammar);
		}

		return self::reduceFrame($stack[0], $nodeCount, $grammar, false);
	}

	/**
	 * @param array{kind: string, fnName: ?string, alts: list<CSSGrammarNode>, any: list<CSSGrammarNode>, all: list<CSSGrammarNode>, seq: list<CSSGrammarNode>} $frame
	 */
	private static function reduceFrame(array $frame, int &$nodeCount, string $grammar, bool $allowEmpty): CSSGrammarNode
	{
		if ($frame['seq'] === [] && $frame['all'] === [] && $frame['any'] === [] && $frame['alts'] === []) {
			if ($allowEmpty) {
				return self::allocNode($nodeCount, CSSGrammarNodeType::Sequence, null, []);
			}
			throw new InvalidArgumentException('Empty group in CSS grammar: ' . $grammar);
		}

		$seqNode = self::reduceSequence($frame['seq'], $nodeCount, $grammar);
		$all = $frame['all'];
		$all[] = $seqNode;
		$allNode = self::reduceAllOrder($all, $nodeCount);

		$any = $frame['any'];
		$any[] = $allNode;
		$anyNode = self::reduceAnyOrder($any, $nodeCount);

		$alts = $frame['alts'];
		$alts[] = $anyNode;

		return self::reduceAlternative($alts, $nodeCount);
	}

	/** @param list<CSSGrammarNode> $nodes */
	private static function reduceSequence(array $nodes, int &$nodeCount, string $grammar): CSSGrammarNode
	{
		if ($nodes === []) {
			throw new InvalidArgumentException('Expected term before combinator in CSS grammar: ' . $grammar);
		}
		if (count($nodes) === 1) {
			return $nodes[0];
		}

		return self::allocNode($nodeCount, CSSGrammarNodeType::Sequence, null, $nodes);
	}

	/** @param list<CSSGrammarNode> $nodes */
	private static function reduceAllOrder(array $nodes, int &$nodeCount): CSSGrammarNode
	{
		if (count($nodes) === 1) {
			return $nodes[0];
		}

		return self::allocNode($nodeCount, CSSGrammarNodeType::AllOrder, null, $nodes);
	}

	/** @param list<CSSGrammarNode> $nodes */
	private static function reduceAnyOrder(array $nodes, int &$nodeCount): CSSGrammarNode
	{
		if (count($nodes) === 1) {
			return $nodes[0];
		}

		return self::allocNode($nodeCount, CSSGrammarNodeType::AnyOrder, null, $nodes);
	}

	/** @param list<CSSGrammarNode> $nodes */
	private static function reduceAlternative(array $nodes, int &$nodeCount): CSSGrammarNode
	{
		if (count($nodes) === 1) {
			return $nodes[0];
		}

		return self::allocNode($nodeCount, CSSGrammarNodeType::Alternative, null, $nodes);
	}

	/**
	 * @param array{type: int, value?: string, min?: int, max?: ?int, comma?: bool, rangeMin?: ?float, rangeMax?: ?float, excluded?: list<string>} $tok
	 */
	private static function wrapMultiplier(CSSGrammarNode $child, array $tok, int &$nodeCount): CSSGrammarNode
	{
		return self::allocNode(
			$nodeCount,
			CSSGrammarNodeType::Repeat,
			null,
			[$child],
			$tok['min'] ?? 1,
			$tok['max'] ?? null,
			$tok['comma'] ?? false,
		);
	}

	/**
	 * @param array{type: int, value?: string, rangeMin?: ?float, rangeMax?: ?float, excluded?: list<string>} $tok
	 */
	private static function buildAngleBracketNode(array $tok, int &$nodeCount): CSSGrammarNode
	{
		$name = (string) ($tok['value'] ?? '');
		if (CSSDataTypeMatcher::isPrimitiveDataType($name)) {
			return self::allocNode(
				$nodeCount,
				CSSGrammarNodeType::DataType,
				$name,
				[],
				1,
				1,
				false,
				$tok['rangeMin'] ?? null,
				$tok['rangeMax'] ?? null,
				$tok['excluded'] ?? [],
			);
		}

		if (($tok['rangeMin'] ?? null) !== null || ($tok['rangeMax'] ?? null) !== null) {
			throw new InvalidArgumentException(sprintf('Numeric range bounds cannot be applied to non-terminal <%s>.', $name));
		}

		return self::allocNode(
			$nodeCount,
			CSSGrammarNodeType::NonTerminal,
			$name,
			[],
			1,
			1,
			false,
			null,
			null,
			$tok['excluded'] ?? [],
		);
	}

	/**
	 * @param list<CSSGrammarNode> $children
	 * @param list<string> $excludedKeywords
	 */
	private static function allocNode(
		int &$nodeCount,
		CSSGrammarNodeType $type,
		?string $value = null,
		array $children = [],
		int $min = 1,
		?int $max = 1,
		bool $commaSeparated = false,
		?float $rangeMin = null,
		?float $rangeMax = null,
		array $excludedKeywords = [],
		bool $caseSensitive = false,
	): CSSGrammarNode {
		if (++$nodeCount > self::MAX_GRAMMAR_NODES) {
			throw new InvalidArgumentException('CSS grammar exceeds maximum AST node count.');
		}

		return new CSSGrammarNode(
			self::$nextNodeId++,
			$type,
			$value,
			$children,
			$min,
			$max,
			$commaSeparated,
			$rangeMin,
			$rangeMax,
			$excludedKeywords,
			$caseSensitive,
		);
	}

	/**
	 * Tokenizes a W3C VDS grammar string.
	 *
	 * @return list<array{type: int, value?: string, min?: int, max?: ?int, comma?: bool, rangeMin?: ?float, rangeMax?: ?float, excluded?: list<string>}>
	 */
	private static function tokenizeVds(string $grammar, bool $caseSensitiveKeywords = false): array
	{
		$len = strlen($grammar);
		$pos = 0;
		$tokens = [];

		while ($pos < $len) {
			$pos += strspn($grammar, " \t\n\r\f", $pos, $len - $pos);
			if ($pos >= $len) {
				break;
			}

			$ch = $grammar[$pos];

			if ($ch === '&' && ($pos + 1 < $len) && $grammar[$pos + 1] === '&') {
				$tokens[] = ['type' => self::TOK_COMB_ALL];
				$pos += 2;
				continue;
			}

			if ($ch === '|') {
				if ($pos + 1 < $len && $grammar[$pos + 1] === '|') {
					$tokens[] = ['type' => self::TOK_COMB_ANY];
					$pos += 2;
				} else {
					$tokens[] = ['type' => self::TOK_COMB_ALT];
					$pos++;
				}
				continue;
			}

			if ($ch === '[') {
				$tokens[] = ['type' => self::TOK_LBRACKET];
				$pos++;
				continue;
			}
			if ($ch === ']') {
				$tokens[] = ['type' => self::TOK_RBRACKET];
				$pos++;
				self::consumeOptionalPostfixModifiers($grammar, $pos, $len, $tokens, true);
				continue;
			}
			if ($ch === ')') {
				$tokens[] = ['type' => self::TOK_RPAREN];
				$pos++;
				self::consumeOptionalPostfixModifiers($grammar, $pos, $len, $tokens, false);
				continue;
			}
			if ($ch === '/' || $ch === ',') {
				$tokens[] = ['type' => self::TOK_DELIM, 'value' => $ch];
				$pos++;
				self::consumeOptionalPostfixModifiers($grammar, $pos, $len, $tokens, false);
				continue;
			}
			if ($ch === "'") {
				$endQuote = strpos($grammar, "'", $pos + 1);
				if ($endQuote === false) {
					throw new InvalidArgumentException('Unclosed single quote in CSS grammar: ' . $grammar);
				}
				$lit = substr($grammar, $pos + 1, $endQuote - $pos - 1);
				$pos = $endQuote + 1;
				if ($lit === '[') {
					$tokens[] = ['type' => self::TOK_SQ_LBRACKET];
					continue;
				}
				if ($lit === ']') {
					$tokens[] = ['type' => self::TOK_SQ_RBRACKET];
					self::consumeOptionalPostfixModifiers($grammar, $pos, $len, $tokens, false);
					continue;
				}
				if ($lit === '') {
					throw new InvalidArgumentException('Empty quoted literal in CSS grammar: ' . $grammar);
				}
				if (strlen($lit) === 1 && ($lit === '/' || $lit === ',' || $lit === '+' || $lit === '*')) {
					$tokens[] = ['type' => self::TOK_DELIM, 'value' => $lit];
				} else {
					$tokens[] = ['type' => self::TOK_IDENT, 'value' => strtolower($lit)];
				}
				self::consumeOptionalPostfixModifiers($grammar, $pos, $len, $tokens, false);
				continue;
			}

			if ($ch === '<') {
				$tokens[] = self::parseAngleToken($grammar, $pos, $len);
				self::consumeOptionalPostfixModifiers($grammar, $pos, $len, $tokens, false);
				continue;
			}

			if ($ch === '0' && ($pos + 1 >= $len || strpbrk($grammar[$pos + 1], " \t\n\r\f]|&?*+#{") !== false)) {
				$tokens[] = ['type' => self::TOK_ZERO];
				$pos++;
				self::consumeOptionalPostfixModifiers($grammar, $pos, $len, $tokens, false);
				continue;
			}

			if (preg_match('/\G([a-zA-Z_-][a-zA-Z0-9_-]*)/', $grammar, $m, 0, $pos) === 1) {
				$rawIdent = $m[1];
				$pos += strlen($rawIdent);
				if ($pos < $len && $grammar[$pos] === '(') {
					$tokens[] = ['type' => self::TOK_FUNCTION_OPEN, 'value' => strtolower($rawIdent)];
					$pos++;
				} else {
					$tokens[] = ['type' => self::TOK_IDENT, 'value' => $caseSensitiveKeywords ? $rawIdent : strtolower($rawIdent)];
					self::consumeOptionalPostfixModifiers($grammar, $pos, $len, $tokens, false);
				}
				continue;
			}

			throw new InvalidArgumentException(sprintf('Unexpected character "%s" at offset %d in CSS grammar: %s', $ch, $pos, $grammar));
		}

		return $tokens;
	}

	/**
	 * @param list<array{type: int, value?: string, min?: int, max?: ?int, comma?: bool, rangeMin?: ?float, rangeMax?: ?float, excluded?: list<string>}> $tokens
	 */
	private static function consumeOptionalPostfixModifiers(
		string $grammar,
		int &$pos,
		int $len,
		array &$tokens,
		bool $allowBang,
	): void {
		if ($allowBang && $pos < $len && $grammar[$pos] === '!') {
			$tokens[] = ['type' => self::TOK_BANG];
			$pos++;
		}
		if ($pos >= $len) {
			return;
		}
		$next = $grammar[$pos];
		if ($next === '?') {
			$tokens[] = ['type' => self::TOK_MULT, 'min' => 0, 'max' => 1, 'comma' => false];
			$pos++;
			return;
		}
		if ($next === '*') {
			$tokens[] = ['type' => self::TOK_MULT, 'min' => 0, 'max' => null, 'comma' => false];
			$pos++;
			return;
		}
		if ($next === '+') {
			$tokens[] = ['type' => self::TOK_MULT, 'min' => 1, 'max' => null, 'comma' => false];
			$pos++;
			return;
		}
		if ($next === '#') {
			$pos++;
			if ($pos < $len && $grammar[$pos] === '?') {
				$tokens[] = ['type' => self::TOK_MULT, 'min' => 0, 'max' => null, 'comma' => true];
				$pos++;
				return;
			}
			if ($pos < $len && $grammar[$pos] === '{') {
				[$min, $max] = self::parseBraceQuantifier($grammar, $pos, $len);
				$tokens[] = ['type' => self::TOK_MULT, 'min' => $min, 'max' => $max, 'comma' => true];
				return;
			}
			$tokens[] = ['type' => self::TOK_MULT, 'min' => 1, 'max' => null, 'comma' => true];
			return;
		}
		if ($next === '{') {
			[$min, $max] = self::parseBraceQuantifier($grammar, $pos, $len);
			$tokens[] = ['type' => self::TOK_MULT, 'min' => $min, 'max' => $max, 'comma' => false];
		}
	}

	/**
	 * @return array{int, ?int}
	 */
	private static function parseBraceQuantifier(string $grammar, int &$pos, int $len): array
	{
		$close = strpos($grammar, '}', $pos + 1);
		if ($close === false) {
			throw new InvalidArgumentException('Unclosed {A,B} quantifier in CSS grammar: ' . $grammar);
		}
		$inside = substr($grammar, $pos + 1, $close - $pos - 1);
		$pos = $close + 1;

		if (preg_match('/^\s*(\d+)\s*(?:,\s*(\d*)\s*)?$/', $inside, $m) !== 1) {
			throw new InvalidArgumentException('Invalid {A,B} quantifier in CSS grammar: {' . $inside . '}');
		}
		$min = (int) $m[1];
		if (!array_key_exists(2, $m)) {
			$max = $min;
		} elseif ($m[2] === '') {
			$max = null;
		} else {
			$max = (int) $m[2];
			if ($max < $min) {
				throw new InvalidArgumentException('Invalid {A,B} quantifier where B < A: {' . $inside . '}');
			}
		}

		return [$min, $max];
	}

	/**
	 * @return array{type: int, value: string, rangeMin?: ?float, rangeMax?: ?float, excluded?: list<string>}
	 */
	private static function parseAngleToken(string $grammar, int &$pos, int $len): array
	{
		$cursor = $pos + 1;
		$bracketDepth = 0;
		while ($cursor < $len) {
			$ch = $grammar[$cursor];
			if ($ch === '[') {
				$bracketDepth++;
			} elseif ($ch === ']') {
				$bracketDepth = max(0, $bracketDepth - 1);
			} elseif ($ch === '>' && $bracketDepth === 0) {
				break;
			}
			$cursor++;
		}
		if ($cursor >= $len) {
			throw new InvalidArgumentException('Unclosed <...> reference in CSS grammar: ' . $grammar);
		}

		$inside = trim(substr($grammar, $pos + 1, $cursor - $pos - 1));
		$pos = $cursor + 1;
		if ($inside === '') {
			throw new InvalidArgumentException('Empty <> reference in CSS grammar.');
		}

		// Property reference <'property-name'>
		if ($inside[0] === "'" && str_ends_with($inside, "'") && strlen($inside) >= 3) {
			$propName = strtolower(trim(substr($inside, 1, -1)));
			if (preg_match('/^[a-z0-9-]+$/', $propName) !== 1) {
				throw new InvalidArgumentException('Invalid property reference <\'' . $propName . '\'> in CSS grammar.');
			}

			return ['type' => self::TOK_PROP_REF, 'value' => $propName];
		}

		// Optional exclusions syntax <custom-ident!foo,bar>
		$excluded = [];
		$bangPos = strpos($inside, '!');
		if ($bangPos !== false) {
			$exclStr = substr($inside, $bangPos + 1);
			$inside = trim(substr($inside, 0, $bangPos));
			foreach (explode(',', $exclStr) as $ex) {
				$exLower = strtolower(trim($ex));
				if ($exLower !== '') {
					$excluded[] = $exLower;
				}
			}
		}

		// Optional bracketed range [min,max]
		$rangeMin = null;
		$rangeMax = null;
		$lBracket = strpos($inside, '[');
		if ($lBracket !== false) {
			if (!str_ends_with($inside, ']')) {
				throw new InvalidArgumentException('Malformed bracketed range in CSS grammar: <' . $inside . '>');
			}
			$typeName = strtolower(trim(substr($inside, 0, $lBracket)));
			$rangeBody = substr($inside, $lBracket + 1, -1);
			$parts = explode(',', $rangeBody);
			if (count($parts) !== 2) {
				throw new InvalidArgumentException('Bracketed range must have [min,max] in CSS grammar: <' . $inside . '>');
			}
			$rangeMin = self::parseRangeBound(trim($parts[0]), $typeName, true);
			$rangeMax = self::parseRangeBound(trim($parts[1]), $typeName, false);
			if ($rangeMin !== null && $rangeMax !== null && $rangeMin > $rangeMax) {
				throw new InvalidArgumentException('Invalid bracketed range where min > max: <' . $inside . '>');
			}

			return [
				'type' => self::TOK_DATA_TYPE,
				'value' => $typeName,
				'rangeMin' => $rangeMin,
				'rangeMax' => $rangeMax,
				'excluded' => $excluded,
			];
		}

		return [
			'type' => self::TOK_DATA_TYPE,
			'value' => strtolower($inside),
			'excluded' => $excluded,
		];
	}

	/**
	 * Parses a numeric/dimensional range bound and canonicalizes dimensional bounds
	 * (`deg` for angle, `s` for time, `dppx` for resolution, `px` for length).
	 */
	private static function parseRangeBound(string $raw, string $typeName, bool $isMin): ?float
	{
		if ($raw === '-∞' || strcasecmp($raw, '-infinity') === 0) {
			if (!$isMin) {
				throw new InvalidArgumentException('Upper range bound cannot be -infinity.');
			}
			return null;
		}
		if ($raw === '∞' || strcasecmp($raw, 'infinity') === 0 || strcasecmp($raw, '+infinity') === 0) {
			if ($isMin) {
				throw new InvalidArgumentException('Lower range bound cannot be +infinity.');
			}
			return null;
		}

		if (preg_match('/^([+-]?(?:\d+(?:\.\d+)?|\.\d+)(?:[eE][+-]?\d+)?)([a-zA-Z%]*)$/', $raw, $m) !== 1) {
			throw new InvalidArgumentException(sprintf('Invalid range bound "%s" in <%s>.', $raw, $typeName));
		}

		$num = (float) $m[1];
		$unit = strtolower($m[2]);

		if ($unit === '' || $unit === '%') {
			return $num;
		}

		return self::canonicalizeDimension($num, $unit, $typeName);
	}

	/**
	 * Converts a literal value with unit into the canonical unit for its dimension category:
	 * - angle -> `deg`
	 * - time -> `s`
	 * - resolution -> `dppx`
	 * - length -> `px` (absolute units; returns null for relative units unless $value === 0.0)
	 *
	 * @internal Shared with CSSDataTypeMatcher
	 */
	public static function canonicalizeDimension(float $value, string $unitLower, string $typeName): ?float
	{
		if ($value === 0.0) {
			return 0.0;
		}

		return match (true) {
			str_contains($typeName, 'angle') || $typeName === 'hue' => match ($unitLower) {
				'deg' => $value,
				'rad' => $value * (180.0 / M_PI),
				'grad' => $value * 0.9,
				'turn' => $value * 360.0,
				default => null,
			},
			str_contains($typeName, 'time') => match ($unitLower) {
				's' => $value,
				'ms' => $value * 0.001,
				default => null,
			},
			$typeName === 'resolution' => match ($unitLower) {
				'dppx', 'x' => $value,
				'dpi' => $value / 96.0,
				'dpcm' => ($value * 2.54) / 96.0,
				default => null,
			},
			str_contains($typeName, 'length') => match ($unitLower) {
				'px' => $value,
				'cm' => $value * (96.0 / 2.54),
				'mm' => $value * (96.0 / 25.4),
				'q' => $value * (96.0 / 101.6),
				'in' => $value * 96.0,
				'pc' => $value * 16.0,
				'pt' => $value * (96.0 / 72.0),
				default => null,
			},
			default => null,
		};
	}
}
