<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm;

use BadMethodCallException;
use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSPropertyRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStandardProperties;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleDeclaration;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitEnum;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;

/**
 * CSS namespace utility class: typed CSS unit factories, `escape()`, `supports()`, and `registerProperty()`.
 *
 * @method static CSSUnitValue percent(string|int|float $value) Create a percentage unit value
 * @method static CSSUnitValue number(string|int|float $value) Create a dimensionless number unit value
 * @method static CSSUnitValue flex(string|int|float $value) Create a flex unit value
 * @method static CSSUnitValue em(string|int|float $value) Create an em unit value
 * @method static CSSUnitValue ex(string|int|float $value) Create an ex unit value
 * @method static CSSUnitValue ch(string|int|float $value) Create a ch unit value
 * @method static CSSUnitValue ic(string|int|float $value) Create an ic unit value
 * @method static CSSUnitValue rem(string|int|float $value) Create a rem unit value
 * @method static CSSUnitValue lh(string|int|float $value) Create an lh unit value
 * @method static CSSUnitValue rlh(string|int|float $value) Create an rlh unit value
 * @method static CSSUnitValue vw(string|int|float $value) Create a vw unit value
 * @method static CSSUnitValue vh(string|int|float $value) Create a vh unit value
 * @method static CSSUnitValue vi(string|int|float $value) Create a vi unit value
 * @method static CSSUnitValue vb(string|int|float $value) Create a vb unit value
 * @method static CSSUnitValue vmin(string|int|float $value) Create a vmin unit value
 * @method static CSSUnitValue vmax(string|int|float $value) Create a vmax unit value
 * @method static CSSUnitValue cm(string|int|float $value) Create a cm unit value
 * @method static CSSUnitValue mm(string|int|float $value) Create a mm unit value
 * @method static CSSUnitValue Q(string|int|float $value) Create a Q unit value
 * @method static CSSUnitValue in(string|int|float $value) Create an in unit value
 * @method static CSSUnitValue pt(string|int|float $value) Create a pt unit value
 * @method static CSSUnitValue pc(string|int|float $value) Create a pc unit value
 * @method static CSSUnitValue px(string|int|float $value) Create a px unit value
 * @method static CSSUnitValue deg(string|int|float $value) Create a deg unit value
 * @method static CSSUnitValue grad(string|int|float $value) Create a grad unit value
 * @method static CSSUnitValue rad(string|int|float $value) Create a rad unit value
 * @method static CSSUnitValue turn(string|int|float $value) Create a turn unit value
 * @method static CSSUnitValue s(string|int|float $value) Create an s unit value
 * @method static CSSUnitValue ms(string|int|float $value) Create an ms unit value
 * @method static CSSUnitValue hz(string|int|float $value) Create an Hz unit value
 * @method static CSSUnitValue khz(string|int|float $value) Create a kHz unit value
 * @method static CSSUnitValue dpi(string|int|float $value) Create a dpi unit value
 * @method static CSSUnitValue dpcm(string|int|float $value) Create a dpcm unit value
 * @method static CSSUnitValue dppx(string|int|float $value) Create a dppx unit value
 * @method static CSSUnitValue x(string|int|float $value) Create an x resolution unit value
 * @method static CSSUnitValue fr(string|int|float $value) Create a fr unit value
 * @method static CSSUnitValue cap(string|int|float $value) Create a cap unit value
 * @method static CSSUnitValue rcap(string|int|float $value) Create a rcap unit value
 * @method static CSSUnitValue cqb(string|int|float $value) Create a cqb unit value
 * @method static CSSUnitValue cqh(string|int|float $value) Create a cqh unit value
 * @method static CSSUnitValue cqw(string|int|float $value) Create a cqw unit value
 * @method static CSSUnitValue cqi(string|int|float $value) Create a cqi unit value
 * @method static CSSUnitValue cqmax(string|int|float $value) Create a cqmax unit value
 * @method static CSSUnitValue cqmin(string|int|float $value) Create a cqmin unit value
 * @method static CSSUnitValue dvb(string|int|float $value) Create a dvb unit value
 * @method static CSSUnitValue dvh(string|int|float $value) Create a dvh unit value
 * @method static CSSUnitValue dvi(string|int|float $value) Create a dvi unit value
 * @method static CSSUnitValue dvmax(string|int|float $value) Create a dvmax unit value
 * @method static CSSUnitValue dvmin(string|int|float $value) Create a dvmin unit value
 * @method static CSSUnitValue dvw(string|int|float $value) Create a dvw unit value
 * @method static CSSUnitValue lvb(string|int|float $value) Create a lvb unit value
 * @method static CSSUnitValue lvh(string|int|float $value) Create a lvh unit value
 * @method static CSSUnitValue lvi(string|int|float $value) Create a lvi unit value
 * @method static CSSUnitValue lvmax(string|int|float $value) Create a lvmax unit value
 * @method static CSSUnitValue lvmin(string|int|float $value) Create a lvmin unit value
 * @method static CSSUnitValue lvw(string|int|float $value) Create a lvw unit value
 * @method static CSSUnitValue rch(string|int|float $value) Create an rch unit value
 * @method static CSSUnitValue rex(string|int|float $value) Create a rex unit value
 * @method static CSSUnitValue ric(string|int|float $value) Create a ric unit value
 * @method static CSSUnitValue svb(string|int|float $value) Create a svb unit value
 * @method static CSSUnitValue svh(string|int|float $value) Create a svh unit value
 * @method static CSSUnitValue svi(string|int|float $value) Create a svi unit value
 * @method static CSSUnitValue svmax(string|int|float $value) Create a svmax unit value
 * @method static CSSUnitValue svmin(string|int|float $value) Create a svmin unit value
 * @method static CSSUnitValue svw(string|int|float $value) Create a svw unit value
 */
abstract class CSS
{
	/** @var array<string, string> Map of unit names to symbols */
	const array UNIT_MAP = [
		'percent' => '%',
		'number' => '',
		'flex' => 'fr',
	];

	/** @var array<string, true> Supported CSS Properties & Values Level 1 data types */
	private const array PROPERTY_SYNTAX_TYPES = [
		'<length>' => true,
		'<number>' => true,
		'<percentage>' => true,
		'<length-percentage>' => true,
		'<color>' => true,
		'<image>' => true,
		'<url>' => true,
		'<integer>' => true,
		'<angle>' => true,
		'<time>' => true,
		'<resolution>' => true,
		'<transform-function>' => true,
		'<transform-list>' => true,
		'<custom-ident>' => true,
		'<string>' => true,
	];

	/** @var array<string, CSSPropertyRule> Standalone custom property registry */
	private static array $registeredProperties = [];

	/**
	 * Magic method for static calls to create CSS unit values.
	 *
	 * @param string $name The unit name
	 * @param array<int, mixed> $arguments The value
	 * @return CSSUnitValue The created unit value
	 * @throws BadMethodCallException If the unit is not supported
	 * @throws InvalidArgumentException If the value is invalid
	 */
	public static function __callStatic(string $name, array $arguments): CSSUnitValue
	{
		$unit = CSSUnitEnum::tryFromUnitName($name);

		if ($unit === null) {
			throw new BadMethodCallException('Static method ' . $name . ' does not exist.');
		}
		if (!isset($arguments[0]) || !(is_string($arguments[0]) || is_int($arguments[0]) || is_float($arguments[0])) || !is_numeric($arguments[0])) {
			throw new InvalidArgumentException('Static method ' . $name . ' requires 1 numeric parameter.');
		}
		$value = is_string($arguments[0]) ? $arguments[0] : (float) $arguments[0];
		if (!is_finite((float) $value)) {
			throw new InvalidArgumentException('CSS numeric value must be finite.');
		}

		return new CSSUnitValue($value, $unit);
	}

	/**
	 * Translates a unit name to its corresponding symbol if mapped, otherwise returns the name unchanged.
	 */
	public static function translateUnit(string $name): string
	{
		return self::UNIT_MAP[$name] ?? $name;
	}

	/**
	 * Registers a custom CSS property in the standalone registry (CSS Properties and Values API).
	 *
	 * @param array{name: string, syntax?: string, inherits: bool, initialValue?: ?string}|CSSPropertyRule $definition
	 * @throws InvalidArgumentException When the definition is invalid or already registered
	 */
	public static function registerProperty(array|CSSPropertyRule $definition): CSSPropertyRule
	{
		if ($definition instanceof CSSPropertyRule) {
			$name = $definition->getNameText();
			$syntax = $definition->getSyntax();
			$rawInherits = strtolower(trim($definition->getStyle()?->getPropertyValue('inherits') ?? ''));
			if ($rawInherits !== 'true' && $rawInherits !== 'false') {
				throw new InvalidArgumentException('CSSPropertyRule must specify inherits as "true" or "false".');
			}
			$inherits = $rawInherits === 'true';
			$initialValue = $definition->getInitialValue();
		} else {
			if (!isset($definition['name']) || !is_string($definition['name'])) {
				throw new InvalidArgumentException('Property registration requires a custom property "name".');
			}
			if (!array_key_exists('inherits', $definition) || !is_bool($definition['inherits'])) {
				throw new InvalidArgumentException('Property registration requires a boolean "inherits" flag.');
			}
			$name = CSSStyleDeclaration::normalizePropertyName($definition['name']);
			if (!str_starts_with($name, '--')) {
				throw new InvalidArgumentException('Registered property name must begin with "--".');
			}
			$syntax = $definition['syntax'] ?? '*';
			if (!is_string($syntax)) {
				throw new InvalidArgumentException('Registered property "syntax" must be a string.');
			}
			$inherits = $definition['inherits'];
			$initialValue = $definition['initialValue'] ?? null;
			if ($initialValue !== null && !is_string($initialValue)) {
				throw new InvalidArgumentException('Registered property "initialValue" must be a string or null.');
			}
		}

		if (isset(self::$registeredProperties[$name])) {
			throw new InvalidArgumentException(sprintf('Custom property "%s" is already registered.', $name));
		}

		$syntax = trim($syntax);
		self::validatePropertySyntax($syntax);

		if ($syntax !== '*' && ($initialValue === null || trim($initialValue) === '')) {
			throw new InvalidArgumentException('Non-universal custom property syntax requires a non-empty initialValue.');
		}

		if ($initialValue !== null) {
			$trimmedInitial = trim($initialValue);
			if ($trimmedInitial !== '') {
				CSSStyleDeclaration::validatePropertyValue($trimmedInitial);
				if (preg_match('/(?:^|[^a-zA-Z0-9_-])var\s*\(/i', $trimmedInitial) === 1) {
					throw new InvalidArgumentException('Registered custom property initialValue cannot contain var() references.');
				}
			}
			$initialValue = $trimmedInitial;
		}

		$descriptors = new CSSStyleDeclaration();
		$descriptors->setProperty('syntax', '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $syntax) . '"');
		$descriptors->setProperty('inherits', $inherits ? 'true' : 'false');
		if ($initialValue !== null) {
			$descriptors->setProperty('initial-value', $initialValue);
		}

		$rule = new CSSPropertyRule($name, $descriptors);
		self::$registeredProperties[$name] = $rule;

		return $rule;
	}

	public static function getRegisteredProperty(string $name): ?CSSPropertyRule
	{
		return self::$registeredProperties[trim($name)] ?? null;
	}

	/** @return array<string, CSSPropertyRule> */
	public static function getRegisteredProperties(): array
	{
		return self::$registeredProperties;
	}

	public static function unregisterProperty(string $name): bool
	{
		$key = trim($name);
		if (!isset(self::$registeredProperties[$key])) {
			return false;
		}
		unset(self::$registeredProperties[$key]);

		return true;
	}

	public static function clearRegisteredProperties(): void
	{
		self::$registeredProperties = [];
	}

	/**
	 * Evaluates CSS property/value or `@supports` condition syntax in standalone (non-DOM) mode.
	 *
	 * Without a browser rendering engine, this validates CSS declaration syntax, standard/custom
	 * property recognition, `selector(...)` syntax, and `@supports` boolean combinators (`not`, `and`, `or`).
	 */
	public static function supports(
		string $propertyOrCondition,
		?string $value = null,
		?CSSParserOptions $options = null,
	): bool {
		$options ??= new CSSParserOptions();
		if ($options->maxBytes !== null) {
			if (strlen($propertyOrCondition) > $options->maxBytes || ($value !== null && strlen($value) > $options->maxBytes)) {
				return false;
			}
		}

		if ($value !== null) {
			return self::supportsDeclaration($propertyOrCondition, $value, false);
		}

		$condition = trim($propertyOrCondition);
		if ($condition === '' || CSSSyntaxScanner::hasControlCharacters($condition) || !CSSSyntaxScanner::isBalanced($condition)) {
			return false;
		}

		return self::evaluateSupportsCondition($condition, 0, $options->maxDepth ?? CSSParserOptions::DEFAULT_MAX_DEPTH);
	}

	/**
	 * Escapes a string as a CSS identifier according to the CSSOM specification.
	 */
	public static function escape(string|int|float|null $value): string
	{
		if ($value === null) {
			return "\u{FFFD}";
		}

		$source = (string) $value;
		$length = strlen($source);
		if ($length === 0) {
			return '';
		}

		$output = '';
		$charIndex = 0;
		$firstIsDash = false;
		$offset = 0;

		while ($offset < $length) {
			$byte = ord($source[$offset]);
			if ($byte < 0x80) {
				$codepoint = $byte;
				$char = $source[$offset];
				$offset++;
			} else {
				[$codepoint, $char, $byteLength] = self::decodeUtf8($source, $offset, $length);
				$offset += $byteLength;
			}

			if ($charIndex === 0) {
				$firstIsDash = $codepoint === 0x2D;
			}

			if ($codepoint === 0x00) {
				$output .= "\u{FFFD}";
			} elseif (($codepoint >= 0x01 && $codepoint <= 0x1F) || $codepoint === 0x7F) {
				$output .= '\\' . dechex($codepoint) . ' ';
			} elseif ($charIndex === 0 && $codepoint >= 0x30 && $codepoint <= 0x39) {
				$output .= '\\' . dechex($codepoint) . ' ';
			} elseif ($charIndex === 1 && $firstIsDash && $codepoint >= 0x30 && $codepoint <= 0x39) {
				$output .= '\\' . dechex($codepoint) . ' ';
			} elseif ($charIndex === 0 && $firstIsDash && $offset === $length) {
				$output .= '\\-';
			} elseif (
				$codepoint >= 0x80
				|| $codepoint === 0x2D
				|| $codepoint === 0x5F
				|| ($codepoint >= 0x30 && $codepoint <= 0x39)
				|| ($codepoint >= 0x41 && $codepoint <= 0x5A)
				|| ($codepoint >= 0x61 && $codepoint <= 0x7A)
			) {
				$output .= $char;
			} else {
				$output .= '\\' . $char;
			}

			$charIndex++;
		}

		return $output;
	}

	private static function validatePropertySyntax(string $syntax): void
	{
		if ($syntax === '*') {
			return;
		}
		if ($syntax === '' || CSSSyntaxScanner::hasControlCharacters($syntax)) {
			throw new InvalidArgumentException('Invalid custom property syntax definition.');
		}

		foreach (explode('|', $syntax) as $component) {
			$component = trim($component);
			if ($component === '') {
				throw new InvalidArgumentException('Invalid custom property syntax definition: ' . $syntax);
			}
			$base = rtrim($component, '+#');
			if (strlen($component) - strlen($base) > 1) {
				throw new InvalidArgumentException('Invalid custom property syntax multiplier: ' . $component);
			}
			if (str_starts_with($base, '<') && str_ends_with($base, '>')) {
				if (!isset(self::PROPERTY_SYNTAX_TYPES[strtolower($base)])) {
					throw new InvalidArgumentException('Unsupported custom property syntax type: ' . $base);
				}
				continue;
			}
			if (preg_match('/^-?[a-zA-Z_][a-zA-Z0-9_-]*$/D', $base) !== 1) {
				throw new InvalidArgumentException('Invalid custom property syntax component: ' . $component);
			}
		}
	}

	private static function supportsDeclaration(string $property, string $value, bool $allowImportant): bool
	{
		try {
			$normalized = CSSStyleDeclaration::normalizePropertyName($property);
		} catch (InvalidArgumentException) {
			return false;
		}

		$trimmed = trim(CSSSyntaxScanner::removeComments($value));
		if ($allowImportant) {
			$bang = CSSSyntaxScanner::findTopLevel($trimmed, '!');
			if ($bang !== null) {
				$tail = trim(CSSSyntaxScanner::removeComments(substr($trimmed, $bang + 1)));
				if (strcasecmp($tail, 'important') === 0) {
					$trimmed = rtrim(substr($trimmed, 0, $bang));
				}
			}
		}

		if ($trimmed === '' && !str_starts_with($normalized, '--')) {
			return false;
		}

		try {
			if ($trimmed !== '') {
				CSSStyleDeclaration::validatePropertyValue($trimmed);
			}
		} catch (InvalidArgumentException) {
			return false;
		}

		if (str_starts_with($normalized, '--')) {
			return true;
		}

		return CSSStandardProperties::isKnown($normalized);
	}

	private static function evaluateSupportsCondition(string $condition, int $depth, int $maxDepth): bool
	{
		if ($depth > $maxDepth) {
			return false;
		}
		$condition = trim(CSSSyntaxScanner::removeComments($condition));
		if ($condition === '') {
			return false;
		}

		// Bare declaration form: CSS.supports('display: grid')
		if ($condition[0] !== '(' && stripos($condition, 'not ') !== 0 && stripos($condition, 'selector(') !== 0) {
			$colon = CSSSyntaxScanner::findTopLevel($condition, ':');
			if ($colon !== null) {
				return self::supportsDeclaration(
					substr($condition, 0, $colon),
					substr($condition, $colon + 1),
					true,
				);
			}
			return false;
		}

		// Unary negation: `not <supports-in-parens>` (whitespace required after `not`)
		if (preg_match('/^not\s+(.+)$/isD', $condition, $match) === 1) {
			$operand = trim($match[1]);
			if (!self::isSupportsInParens($operand)) {
				return false;
			}
			return !self::evaluateSupportsInParens($operand, $depth + 1, $maxDepth);
		}

		$tokens = self::splitSupportsClauses($condition);
		if ($tokens === null || $tokens === []) {
			return false;
		}

		if (count($tokens) === 1) {
			return self::evaluateSupportsInParens($tokens[0], $depth + 1, $maxDepth);
		}

		$operator = null;
		$clauses = [];
		for ($i = 0; $i < count($tokens); $i++) {
			if ($i % 2 === 0) {
				$clauses[] = $tokens[$i];
			} else {
				$op = strtolower($tokens[$i]);
				if ($op !== 'and' && $op !== 'or') {
					return false;
				}
				if ($operator !== null && $operator !== $op) {
					// CSS Conditional Rules forbids mixing `and` and `or` at the same parenthetical level.
					return false;
				}
				$operator = $op;
			}
		}

		if (count($tokens) % 2 === 0 || $operator === null) {
			return false;
		}

		foreach ($clauses as $clause) {
			if (!self::isSupportsInParens($clause)) {
				return false;
			}
		}

		if ($operator === 'and') {
			foreach ($clauses as $clause) {
				if (!self::evaluateSupportsInParens($clause, $depth + 1, $maxDepth)) {
					return false;
				}
			}
			return true;
		}

		foreach ($clauses as $clause) {
			if (self::evaluateSupportsInParens($clause, $depth + 1, $maxDepth)) {
				return true;
			}
		}
		return false;
	}

	private static function isSupportsInParens(string $token): bool
	{
		$token = trim($token);
		if ($token === '') {
			return false;
		}
		if ($token[0] === '(') {
			return CSSSyntaxScanner::findMatchingDelimiter($token, 0) === strlen($token) - 1;
		}
		if (preg_match('/^selector\s*\(/i', $token) === 1) {
			$open = strpos($token, '(');
			return $open !== false && CSSSyntaxScanner::findMatchingDelimiter($token, $open) === strlen($token) - 1;
		}

		return false;
	}

	private static function evaluateSupportsInParens(string $token, int $depth, int $maxDepth): bool
	{
		if ($depth > $maxDepth || !self::isSupportsInParens($token)) {
			return false;
		}

		$token = trim($token);
		if (preg_match('/^selector\s*\(/i', $token) === 1) {
			$open = strpos($token, '(');
			$inner = trim(substr($token, $open + 1, -1));
			if ($inner === '') {
				return false;
			}
			try {
				CSSSyntaxScanner::assertValidFragment($inner, 'CSS selector', [';', '{', '}']);
			} catch (InvalidArgumentException) {
				return false;
			}
			return true;
		}

		$inner = trim(substr($token, 1, -1));
		if ($inner === '') {
			return false;
		}

		// If inner starts with a grouping condition or selector(...) or `not `, evaluate recursively.
		if ($inner[0] === '(' || preg_match('/^(?:not\s+|selector\s*\()/i', $inner) === 1) {
			return self::evaluateSupportsCondition($inner, $depth + 1, $maxDepth);
		}

		$colon = CSSSyntaxScanner::findTopLevel($inner, ':');
		if ($colon !== null) {
			return self::supportsDeclaration(
				substr($inner, 0, $colon),
				substr($inner, $colon + 1),
				true,
			);
		}

		return false;
	}

	/**
	 * Splits top-level `@supports` clauses and combinators (`and` / `or`).
	 *
	 * @return list<string>|null
	 */
	private static function splitSupportsClauses(string $condition): ?array
	{
		$tokens = [];
		$length = strlen($condition);
		$offset = 0;

		while ($offset < $length) {
			while ($offset < $length && ctype_space($condition[$offset])) {
				$offset++;
			}
			if ($offset >= $length) {
				break;
			}

			if ($condition[$offset] === '(') {
				$close = CSSSyntaxScanner::findMatchingDelimiter($condition, $offset, $length);
				if ($close === null) {
					return null;
				}
				$tokens[] = substr($condition, $offset, $close - $offset + 1);
				$offset = $close + 1;
				continue;
			}

			if (preg_match('/\Gselector\s*\(/i', $condition, $match, 0, $offset) === 1) {
				$open = $offset + strlen($match[0]) - 1;
				$close = CSSSyntaxScanner::findMatchingDelimiter($condition, $open, $length);
				if ($close === null) {
					return null;
				}
				$tokens[] = substr($condition, $offset, $close - $offset + 1);
				$offset = $close + 1;
				continue;
			}

			if (preg_match('/\G(and|or)(?=\s|\()/i', $condition, $match, 0, $offset) === 1) {
				$after = $offset + strlen($match[1]);
				// CSS Conditional Rules requires whitespace around `and`/`or` keywords.
				if ($after >= $length || !ctype_space($condition[$after])) {
					return null;
				}
				$tokens[] = strtolower($match[1]);
				$offset = $after;
				continue;
			}

			return null;
		}

		return $tokens;
	}

	/** @return array{int, string, int} */
	private static function decodeUtf8(string $source, int $offset, int $length): array
	{
		$b0 = ord($source[$offset]);
		if ($b0 >= 0xC2 && $b0 <= 0xDF && $offset + 1 < $length) {
			$b1 = ord($source[$offset + 1]);
			if (($b1 & 0xC0) === 0x80) {
				return [(($b0 & 0x1F) << 6) | ($b1 & 0x3F), substr($source, $offset, 2), 2];
			}
		} elseif ($b0 >= 0xE0 && $b0 <= 0xEF && $offset + 2 < $length) {
			$b1 = ord($source[$offset + 1]);
			$b2 = ord($source[$offset + 2]);
			$validRange = ($b0 !== 0xE0 || $b1 >= 0xA0) && ($b0 !== 0xED || $b1 <= 0x9F);
			if ($validRange && ($b1 & 0xC0) === 0x80 && ($b2 & 0xC0) === 0x80) {
				return [(($b0 & 0x0F) << 12) | (($b1 & 0x3F) << 6) | ($b2 & 0x3F), substr($source, $offset, 3), 3];
			}
		} elseif ($b0 >= 0xF0 && $b0 <= 0xF4 && $offset + 3 < $length) {
			$b1 = ord($source[$offset + 1]);
			$b2 = ord($source[$offset + 2]);
			$b3 = ord($source[$offset + 3]);
			$validRange = ($b0 !== 0xF0 || $b1 >= 0x90) && ($b0 !== 0xF4 || $b1 <= 0x8F);
			if ($validRange && ($b1 & 0xC0) === 0x80 && ($b2 & 0xC0) === 0x80 && ($b3 & 0xC0) === 0x80) {
				return [
					(($b0 & 0x07) << 18) | (($b1 & 0x3F) << 12) | (($b2 & 0x3F) << 6) | ($b3 & 0x3F),
					substr($source, $offset, 4),
					4,
				];
			}
		}

		return [0xFFFD, "\u{FFFD}", 1];
	}
}
