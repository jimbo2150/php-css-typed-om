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

	public static function px(string|int|float|null $value = null): CSSUnitValue { return self::unitFactory($value, CSSUnitEnum::LENGTH_px, 'px'); }
	public static function em(string|int|float|null $value = null): CSSUnitValue { return self::unitFactory($value, CSSUnitEnum::LENGTH_em, 'em'); }
	public static function rem(string|int|float|null $value = null): CSSUnitValue { return self::unitFactory($value, CSSUnitEnum::LENGTH_rem, 'rem'); }
	public static function percent(string|int|float|null $value = null): CSSUnitValue { return self::unitFactory($value, CSSUnitEnum::PERCENT, 'percent'); }
	public static function number(string|int|float|null $value = null): CSSUnitValue { return self::unitFactory($value, CSSUnitEnum::NUMBER, 'number'); }
	public static function deg(string|int|float|null $value = null): CSSUnitValue { return self::unitFactory($value, CSSUnitEnum::ANGLE_deg, 'deg'); }
	public static function s(string|int|float|null $value = null): CSSUnitValue { return self::unitFactory($value, CSSUnitEnum::TIME_s, 's'); }
	public static function ms(string|int|float|null $value = null): CSSUnitValue { return self::unitFactory($value, CSSUnitEnum::TIME_ms, 'ms'); }
	public static function vh(string|int|float|null $value = null): CSSUnitValue { return self::unitFactory($value, CSSUnitEnum::LENGTH_vh, 'vh'); }
	public static function vw(string|int|float|null $value = null): CSSUnitValue { return self::unitFactory($value, CSSUnitEnum::LENGTH_vw, 'vw'); }
	public static function fr(string|int|float|null $value = null): CSSUnitValue { return self::unitFactory($value, CSSUnitEnum::FLEX_fr, 'fr'); }

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

		return self::unitFactory($arguments[0] ?? null, $unit, $name);
	}

	private static function unitFactory(mixed $rawValue, CSSUnitEnum $unit, string $methodName): CSSUnitValue
	{
		if ($rawValue === null || !(is_string($rawValue) || is_int($rawValue) || is_float($rawValue))) {
			throw new InvalidArgumentException('Static method ' . $methodName . ' requires 1 numeric parameter.');
		}
		if (is_string($rawValue) && CSSSyntaxScanner::hasControlCharacters($rawValue)) {
			throw new InvalidArgumentException('CSS numeric value contains disallowed control characters.');
		}
		if (!is_numeric($rawValue)) {
			throw new InvalidArgumentException('Static method ' . $methodName . ' requires 1 numeric parameter.');
		}
		$value = (float) $rawValue;
		if (!is_finite($value)) {
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

		$syntax = CSSSyntaxScanner::trimCssWhitespace($syntax);
		self::validatePropertySyntax($syntax);

		if ($syntax !== '*' && ($initialValue === null || CSSSyntaxScanner::trimCssWhitespace($initialValue) === '')) {
			throw new InvalidArgumentException('Non-universal custom property syntax requires a non-empty initialValue.');
		}

		if ($initialValue !== null) {
			$trimmedInitial = CSSSyntaxScanner::trimCssWhitespace($initialValue);
			if ($trimmedInitial !== '') {
				CSSStyleDeclaration::validatePropertyValue($trimmedInitial);
				if (self::hasVarReference($trimmedInitial)) {
					throw new InvalidArgumentException('Registered custom property initialValue cannot contain var() references.');
				}
			}
			$initialValue = $trimmedInitial;
		}

		$descriptors = new CSSStyleDeclaration();
		$descriptors->setProperty('syntax', '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $syntax) . '"');
		$descriptors->setProperty('inherits', $inherits ? 'true' : 'false');
		$hasEmptyInitialValue = $initialValue === '';
		if ($initialValue !== null && !$hasEmptyInitialValue) {
			$descriptors->setProperty('initial-value', $initialValue);
		}

		$rule = new CSSPropertyRule($name, $descriptors, $hasEmptyInitialValue);
		self::$registeredProperties[$name] = $rule;

		return $rule;
	}

	public static function getRegisteredProperty(string $name): ?CSSPropertyRule
	{
		return self::$registeredProperties[CSSSyntaxScanner::trimCssWhitespace($name)] ?? null;
	}

	/** @return array<string, CSSPropertyRule> */
	public static function getRegisteredProperties(): array
	{
		return self::$registeredProperties;
	}

	public static function unregisterProperty(string $name): bool
	{
		$key = CSSSyntaxScanner::trimCssWhitespace($name);
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
		$options ??= CSSParserOptions::default();
		if ($options->maxBytes !== null) {
			if (strlen($propertyOrCondition) > $options->maxBytes || ($value !== null && strlen($value) > $options->maxBytes)) {
				return false;
			}
		}

		if ($value !== null) {
			return self::supportsDeclaration($propertyOrCondition, $value, false);
		}

		$rawCondition = CSSSyntaxScanner::trimCssWhitespace($propertyOrCondition);
		if ($rawCondition === '' || CSSSyntaxScanner::hasControlCharacters($rawCondition) || !CSSSyntaxScanner::isBalanced($rawCondition)) {
			return false;
		}
		$condition = CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($rawCondition));
		if ($condition === '') {
			return false;
		}

		return self::evaluateSupportsConditionIterative($condition, $options->maxDepth) === true;
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
			$component = CSSSyntaxScanner::trimCssWhitespace($component);
			if ($component === '') {
				throw new InvalidArgumentException('Invalid custom property syntax definition: ' . $syntax);
			}
			$base = rtrim($component, '+#');
			$multiplierCount = strlen($component) - strlen($base);
			if ($multiplierCount > 1) {
				throw new InvalidArgumentException('Invalid custom property syntax multiplier: ' . $component);
			}
			if (str_starts_with($base, '<') && str_ends_with($base, '>')) {
				$lowerBase = strtolower($base);
				if (!isset(self::PROPERTY_SYNTAX_TYPES[$lowerBase])) {
					throw new InvalidArgumentException('Unsupported custom property syntax type: ' . $base);
				}
				if ($multiplierCount > 0 && $lowerBase === '<transform-list>') {
					throw new InvalidArgumentException('CSS syntax <transform-list> cannot have a multiplier: ' . $component);
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

		$rawValue = CSSSyntaxScanner::trimCssWhitespace($value);
		if ($rawValue !== '' && (CSSSyntaxScanner::hasControlCharacters($rawValue) || !CSSSyntaxScanner::isBalanced($rawValue))) {
			return false;
		}
		$trimmed = CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($rawValue));
		if ($allowImportant) {
			$bang = CSSSyntaxScanner::findLastTopLevelImportant($trimmed);
			if ($bang !== null) {
				$trimmed = rtrim(substr($trimmed, 0, $bang), CSSSyntaxScanner::CSS_WHITESPACE);
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

	/** @internal */
	public static function hasVarReference(string $source): bool
	{
		$clean = CSSSyntaxScanner::removeComments($source);
		$length = strlen($clean);
		$offset = 0;

		while ($offset < $length) {
			$char = $clean[$offset];
			if ($char === '"' || $char === "'") {
				$quote = $char;
				$offset++;
				while ($offset < $length) {
					$step = $clean[$offset];
					if ($step === '\\') {
						$offset += ($offset + 1 < $length && $clean[$offset + 1] === "\r" && ($clean[$offset + 2] ?? '') === "\n") ? 3 : 2;
						continue;
					}
					$offset++;
					if ($step === $quote) {
						break;
					}
				}
				continue;
			}

			// Scan identifier/function name including CSS escapes
			$isIdentStart = ($char >= 'a' && $char <= 'z')
				|| ($char >= 'A' && $char <= 'Z')
				|| $char === '_'
				|| $char === '-'
				|| $char === '\\'
				|| ord($char) >= 0x80;

			if ($isIdentStart) {
				$start = $offset;
				while ($offset < $length) {
					$c = $clean[$offset];
					if ($c === '\\') {
						$hexLen = strspn($clean, '0123456789abcdefABCDEF', $offset + 1, 6);
						if ($hexLen > 0) {
							$offset += 1 + $hexLen;
							if ($offset < $length) {
								$ws = $clean[$offset];
								if ($ws === "\r" && ($clean[$offset + 1] ?? '') === "\n") {
									$offset += 2;
								} elseif ($ws === ' ' || $ws === "\t" || $ws === "\n" || $ws === "\r" || $ws === "\f") {
									$offset++;
								}
							}
							continue;
						}
						$offset += ($offset + 1 < $length && $clean[$offset + 1] === "\r" && ($clean[$offset + 2] ?? '') === "\n") ? 3 : 2;
						continue;
					}
					if (($c >= 'a' && $c <= 'z') || ($c >= 'A' && $c <= 'Z') || ($c >= '0' && $c <= '9') || $c === '_' || $c === '-' || ord($c) >= 0x80) {
						$offset++;
						continue;
					}
					break;
				}
				if ($offset < $length && $clean[$offset] === '(') {
					$ident = substr($clean, $start, $offset - $start);
					$decoded = str_contains($ident, '\\') ? CSSSyntaxScanner::decodeCssIdentEscapes($ident) : $ident;
					if (strcasecmp($decoded, 'var') === 0) {
						return true;
					}
				}
				continue;
			}

			$offset++;
		}

		return false;
	}

	/**
	 * Iterative slice-based evaluator for `@supports` conditions (O(1) PHP stack frames, zero per-level substr).
	 */
	private static function evaluateSupportsConditionIterative(string $source, ?int $maxDepth): ?bool
	{
		// Frame modes:
		// 0 = EVAL_CONDITION(start, end, depth, targetKind, targetIdx)
		// 1 = COMBINATOR_STEP(operator: 'and'|'or', clauses: list<array{int,int}>, nextIndex, depth, targetKind, targetIdx)
		// 2 = APPLY_NOT(sourceValIdx, targetKind, targetIdx)
		$slots = [null]; // index 0 = root result (?bool)
		$stack = [[0, 0, strlen($source), 0, 0]]; // mode, start, end, depth, slotIdx

		while ($stack !== []) {
			$frame = array_pop($stack);
			$mode = $frame[0];

			if ($mode === 2) {
				[, $srcSlot, $destSlot] = $frame;
				$val = $slots[$srcSlot];
				$slots[$destSlot] = $val === null ? null : !$val;
				continue;
			}

			if ($mode === 1) {
				[, $op, $clauses, $idx, $depth, $childSlot, $destSlot] = $frame;
				$childVal = $slots[$childSlot];
				if ($childVal === null) {
					$slots[$destSlot] = null;
					continue;
				}
				if ($op === 'and' && !$childVal) {
					$slots[$destSlot] = false;
					continue;
				}
				if ($op === 'or' && $childVal) {
					$slots[$destSlot] = true;
					continue;
				}
				if ($idx >= count($clauses)) {
					$slots[$destSlot] = $op === 'and';
					continue;
				}
				[$cStart, $cEnd] = $clauses[$idx];
				$nextSlot = count($slots);
				$slots[] = null;
				$stack[] = [1, $op, $clauses, $idx + 1, $depth, $nextSlot, $destSlot];
				self::pushSupportsInParens($source, $cStart, $cEnd, $depth, $nextSlot, $maxDepth, $slots, $stack);
				continue;
			}

			// mode === 0: EVAL_CONDITION
			[, $start, $end, $depth, $destSlot] = $frame;
			if ($maxDepth !== null && $depth > $maxDepth) {
				$slots[$destSlot] = null;
				continue;
			}
			self::trimSlice($source, $start, $end);
			if ($start >= $end) {
				$slots[$destSlot] = false;
				continue;
			}

			$c0 = $source[$start];
			$startsNot = $end - $start >= 4 && strncasecmp(substr($source, $start, 3), 'not', 3) === 0
				&& str_contains(CSSSyntaxScanner::CSS_WHITESPACE, $source[$start + 3]);
			$startsSelector = $end - $start >= 9 && substr_compare($source, 'selector(', $start, 9, true) === 0;

			if ($c0 !== '(' && !$startsNot && !$startsSelector) {
				$colon = CSSSyntaxScanner::findTopLevel($source, ':', $start, $end);
				if ($colon !== null) {
					$slots[$destSlot] = self::supportsDeclaration(
						substr($source, $start, $colon - $start),
						substr($source, $colon + 1, $end - ($colon + 1)),
						true,
					);
				} else {
					$slots[$destSlot] = false;
				}
				continue;
			}

			if ($startsNot) {
				$opStart = $start + 4;
				$opEnd = $end;
				self::trimSlice($source, $opStart, $opEnd);
				if (!self::isSupportsInParensSlice($source, $opStart, $opEnd)) {
					$slots[$destSlot] = false;
					continue;
				}
				$innerSlot = count($slots);
				$slots[] = null;
				$stack[] = [2, $innerSlot, $destSlot];
				self::pushSupportsInParens($source, $opStart, $opEnd, $depth, $innerSlot, $maxDepth, $slots, $stack);
				continue;
			}

			$tokens = self::splitSupportsClausesSlice($source, $start, $end);
			if ($tokens === null || $tokens === []) {
				$slots[$destSlot] = false;
				continue;
			}

			if (count($tokens) === 1) {
				if (!is_array($tokens[0])) {
					$slots[$destSlot] = false;
					continue;
				}
				self::pushSupportsInParens($source, $tokens[0][0], $tokens[0][1], $depth, $destSlot, $maxDepth, $slots, $stack);
				continue;
			}

			$tokenCount = count($tokens);
			if ($tokenCount % 2 === 0) {
				$slots[$destSlot] = false;
				continue;
			}

			$operator = null;
			$clauses = [];
			$validCombo = true;
			for ($i = 0; $i < $tokenCount; $i++) {
				if ($i % 2 === 0) {
					if (!is_array($tokens[$i])) {
						$validCombo = false;
						break;
					}
					$clauses[] = $tokens[$i];
				} else {
					if (!is_string($tokens[$i])) {
						$validCombo = false;
						break;
					}
					$op = $tokens[$i];
					if ($operator !== null && $operator !== $op) {
						$validCombo = false;
						break;
					}
					$operator = $op;
				}
			}

			if (!$validCombo || $operator === null || $clauses === []) {
				$slots[$destSlot] = false;
				continue;
			}

			$firstSlot = count($slots);
			$slots[] = null;
			$stack[] = [1, $operator, $clauses, 1, $depth, $firstSlot, $destSlot];
			self::pushSupportsInParens($source, $clauses[0][0], $clauses[0][1], $depth, $firstSlot, $maxDepth, $slots, $stack);
		}

		return $slots[0];
	}

	/**
	 * @param array<int, ?bool> $slots
	 * @param list<array<int, mixed>> $stack
	 */
	private static function pushSupportsInParens(
		string $source,
		int $start,
		int $end,
		int $depth,
		int $destSlot,
		?int $maxDepth,
		array &$slots,
		array &$stack,
	): void {
		if ($maxDepth !== null && $depth > $maxDepth) {
			$slots[$destSlot] = null;
			return;
		}
		self::trimSlice($source, $start, $end);
		if ($start >= $end) {
			$slots[$destSlot] = false;
			return;
		}
		if ($end - $start >= 9 && substr_compare($source, 'selector(', $start, 9, true) === 0) {
			$open = $start + 8;
			$close = CSSSyntaxScanner::findMatchingDelimiter($source, $open, $end);
			if ($close !== $end - 1) {
				$slots[$destSlot] = false;
				return;
			}
			$innerStart = $open + 1;
			$innerEnd = $close;
			self::trimSlice($source, $innerStart, $innerEnd);
			if ($innerStart >= $innerEnd) {
				$slots[$destSlot] = false;
				return;
			}
			try {
				CSSSyntaxScanner::assertValidFragment(substr($source, $innerStart, $innerEnd - $innerStart), 'CSS selector', [';', '{', '}']);
			} catch (InvalidArgumentException) {
				$slots[$destSlot] = false;
				return;
			}
			$slots[$destSlot] = true;
			return;
		}

		if ($source[$start] !== '(' || $source[$end - 1] !== ')') {
			$slots[$destSlot] = false;
			return;
		}
		$innerStart = $start + 1;
		$innerEnd = $end - 1;
		self::trimSlice($source, $innerStart, $innerEnd);
		if ($innerStart >= $innerEnd) {
			$slots[$destSlot] = false;
			return;
		}

		$isGroupOrNot = $source[$innerStart] === '('
			|| ($innerEnd - $innerStart >= 4 && strncasecmp(substr($source, $innerStart, 3), 'not', 3) === 0 && str_contains(CSSSyntaxScanner::CSS_WHITESPACE, $source[$innerStart + 3]))
			|| ($innerEnd - $innerStart >= 9 && substr_compare($source, 'selector(', $innerStart, 9, true) === 0);

		if ($isGroupOrNot) {
			$stack[] = [0, $innerStart, $innerEnd, $depth + 1, $destSlot];
			return;
		}

		if ($maxDepth !== null && ($depth + 1) > $maxDepth) {
			$slots[$destSlot] = null;
			return;
		}

		$colon = CSSSyntaxScanner::findTopLevel($source, ':', $innerStart, $innerEnd);
		if ($colon !== null) {
			$slots[$destSlot] = self::supportsDeclaration(
				substr($source, $innerStart, $colon - $innerStart),
				substr($source, $colon + 1, $innerEnd - ($colon + 1)),
				true,
			);
			return;
		}

		$slots[$destSlot] = false;
	}

	private static function trimSlice(string $source, int &$start, int &$end): void
	{
		while ($start < $end && str_contains(CSSSyntaxScanner::CSS_WHITESPACE, $source[$start])) {
			$start++;
		}
		while ($end > $start && str_contains(CSSSyntaxScanner::CSS_WHITESPACE, $source[$end - 1])) {
			$end--;
		}
	}

	private static function isSupportsInParensSlice(string $source, int $start, int $end): bool
	{
		self::trimSlice($source, $start, $end);
		if ($start >= $end) {
			return false;
		}
		if ($source[$start] === '(') {
			return CSSSyntaxScanner::findMatchingDelimiter($source, $start, $end) === $end - 1;
		}
		if ($end - $start >= 9 && substr_compare($source, 'selector(', $start, 9, true) === 0) {
			$open = $start + 8;
			return CSSSyntaxScanner::findMatchingDelimiter($source, $open, $end) === $end - 1;
		}

		return false;
	}

	/**
	 * Splits top-level `@supports` clauses and combinators (`and` / `or`) over a byte slice.
	 *
	 * @return list<array{0: int, 1: int}|string>|null
	 */
	private static function splitSupportsClausesSlice(string $source, int $start, int $end): ?array
	{
		$tokens = [];
		$offset = $start;

		while ($offset < $end) {
			$offset += strspn($source, CSSSyntaxScanner::CSS_WHITESPACE, $offset, $end - $offset);
			if ($offset >= $end) {
				break;
			}

			if ($source[$offset] === '(') {
				$close = CSSSyntaxScanner::findMatchingDelimiter($source, $offset, $end);
				if ($close === null || $close >= $end) {
					return null;
				}
				$tokens[] = [$offset, $close + 1];
				$offset = $close + 1;
				continue;
			}

			if ($end - $offset >= 9 && substr_compare($source, 'selector(', $offset, 9, true) === 0) {
				$open = $offset + 8;
				$close = CSSSyntaxScanner::findMatchingDelimiter($source, $open, $end);
				if ($close === null || $close >= $end) {
					return null;
				}
				$tokens[] = [$offset, $close + 1];
				$offset = $close + 1;
				continue;
			}

			if ($end - $offset >= 4 && substr_compare($source, 'and', $offset, 3, true) === 0
				&& str_contains(CSSSyntaxScanner::CSS_WHITESPACE, $source[$offset + 3])
			) {
				$tokens[] = 'and';
				$offset += 3;
				continue;
			}
			if ($end - $offset >= 3 && substr_compare($source, 'or', $offset, 2, true) === 0
				&& str_contains(CSSSyntaxScanner::CSS_WHITESPACE, $source[$offset + 2])
			) {
				$tokens[] = 'or';
				$offset += 2;
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
