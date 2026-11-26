# CSSNumericValue

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue`  
**Source:** [`src/TypedOM/Values/Numeric/CSSNumericValue.php`](../../../src/TypedOM/Values/Numeric/CSSNumericValue.php)  
**Purpose:** Abstract base for numeric CSS values and math expressions; provides parsing, dimension typing, unit conversion, arithmetic, comparison, and inherited style-value parsing.

## Public properties and constants

| Name | Type and access |
|---|---|
| `MAX_MATH_NODES` | Public `int` constant (`100_000`); global node/token/operand ceiling enforced across parsing, serialization, cloning, conversion, `toSum()`, comparison, and variadic/math constructors. |
| `value` | Inherited `SimpleValueTrait` property; public read, protected write; generic mixed value slot used by numeric math nodes. |
| `unit` | Inherited `TypeableUnitTrait` property; public read-only `string`, the canonical Typed OM unit name (e.g., `'number'`, `'percent'`, `'px'`), or `''` if unset. |
| `length` | Inherited `LengthTrait` property; public read-only `?int`, count of a `values` collection when one exists, otherwise `null`. |

The class is abstract and inherits `parseAll`, `__toString`, and `clone` from its parents/traits. It implements the string/clone methods using `SimpleValueTrait`.

## Public API

| Method | Signature and behavior |
|---|---|
| `__construct` | `public function __construct()`; empty base constructor. Example: subclasses may call `parent::__construct();`. |
| `parse` | `public static function parse(string $propertyOrCssText, ?string $cssText = null, ?CSSParserOptions $options = null, int $depth = 0): self`; parses numeric text or a supported math expression (rejecting negative or `PHP_INT_MAX` `$depth`). With `$cssText`, validates `$propertyOrCssText` (size + `CSSStyleDeclaration::normalizePropertyName()`), propagates `$depth` / `maxMathDepth` into `CSSStyleValue::parse()`, and rejects nonnumeric results; otherwise parses numeric literals, `calc`, `min`, `max`, `clamp`, and CSS Values Level 4 math functions/constants with §10.10 simplification. Throws `InvalidArgumentException` for invalid/nonfinite/incompatible expressions or `CSSParserLimitException` on limit exhaustion. Example: `$number = CSSNumericValue::parse('calc(2em + 3px)');`. |
| `simplify` | `public function simplify(?CSSContext $context = null): self`; simplifies the calculation tree per CSS Values Level 4 §10.10 (constant folding, canonical unit conversion, unit cancellation, and canonical term/factor sorting). When `$context` is supplied, relative length units also convert to `px`. Example: `$simplified = $number->simplify($context);`. |
| `clampToRange` | `public function clampToRange(?float $min = null, ?float $max = null, ?CSSContext $context = null): self`; simplifies and clamps numeric values and single-unit degenerate constants (`infinity`, `-infinity`, `NaN`) to `[min, max]` per CSS Values Level 4 §10.8. Throws `InvalidArgumentException` for non-finite bounds or `$min > $max`. Example: `$bounded = $number->clampToRange(0.0, 500.0);`. |
| `from` | `public function from(string $cssText): self`; instance method parsing a numeric CSS expression. Example: `$number = $existing->from('4em');`. |
| `type` / `getType` / `getNumericType` | `public function type(): CSSNumericType`; `public function getType(): CSSNumericType`; `public function getNumericType(): CSSNumericType`; return the Typed OM dimensional exponent map. `getNumericType()` combines math operand types and can throw `InvalidArgumentException` for malformed internal math operands. Example: `$type = $number->type();`. |
| `getNumericCategory` | `public function getNumericCategory(): string`; returns `number`, a simple dimension, `inverse-<dimension>`, or `derived`, based on the exponent map. Example: `$category = $number->getNumericCategory();`. |
| `to` | `public function to(string $unit, ?CSSContext $context = null): self`; converts unit values and single-unit `CSSMathProduct` operands to a compatible unit (preserving math node structure on products) using the supplied/default context; mixed percentage expressions and unsupported/incompatible conversions throw `InvalidArgumentException`. Example: `$pixels = $number->to('px', $context);`. |
| `toSum` | `public function toSum(string ...$units): CSSMathSum`; converts/evaluates concrete numeric expressions and groups terms into a sum over requested units with shared `MAX_MATH_NODES` traversal tracking; no units wraps the value in a one-term sum. Incompatible units throw `InvalidArgumentException`. Example: `$sum = $number->toSum('px', 'em');`. |
| `add` | `public function add(CSSNumericValue|int|float ...$values): self`; adds one or more values (numbers become unitless values), checks additive dimensional compatibility and `MAX_MATH_NODES`, and returns `CSSMathSum`. At least one operand is required. Example: `$sum = $number->add(CSSNumericValue::parse('2px'));`. |
| `sub` | `public function sub(CSSNumericValue|int|float ...$values): self`; subtracts one or more operands by constructing a sum with negated operands; checks compatible dimensions and `MAX_MATH_NODES`. At least one operand is required. Example: `$difference = $number->sub(2);`. |
| `mul` | `public function mul(CSSNumericValue|int|float ...$values): CSSMathProduct`; multiplies this value by one or more operands (rejecting conflicting `percentHint` dimensions and enforcing `MAX_MATH_NODES`); numeric scalars become unitless values. At least one operand is required. Example: `$product = $number->mul(2);`. |
| `div` | `public function div(CSSNumericValue|int|float ...$values): CSSMathProduct`; divides by operands by representing each denominator as an inverse in a product (enforcing `MAX_MATH_NODES`). At least one operand is required. Example: `$quotient = $number->div(2);`. |
| `min` | `public function min(CSSNumericArray|CSSNumericValue|int|float ...$values): CSSMathMin`; returns a minimum expression over this value and flattened supplied operands (capped at `MAX_MATH_NODES`); requires at least one extra operand and compatible additive types. Example: `$minimum = $number->min(CSSNumericValue::parse('10px'));`. |
| `max` | `public function max(CSSNumericArray|CSSNumericValue|int|float ...$values): CSSMathMax`; returns a maximum expression over this value and flattened supplied operands (capped at `MAX_MATH_NODES`); requires at least one extra operand and compatible additive types. Example: `$maximum = $number->max(10);`. |
| `equals` | `public function equals(self ...$numericValues): bool`; true when every supplied value equals this value; with no arguments returns true. Identity `$left === $right` short-circuits; unit values compare directly or after compatible conversion; math nodes compare iteratively with `MAX_MATH_NODES`/`MAX_MATH_TREE_DEPTH` guards. Example: `$same = $number->equals($other);`. |
| `mergeAdditiveTypes` | `public static function mergeAdditiveTypes(array $values): CSSNumericType`; merges numeric types in a `list<CSSNumericValue>` using Typed OM percentage-hint rules; an empty list yields a unitless numeric type and incompatible dimensions throw `InvalidArgumentException`. Example: `$type = CSSNumericValue::mergeAdditiveTypes([$left, $right]);`. |
| `unitType` | `public function unitType(): ?string`; alias of the unit trait's `type()` and returns the unit's category when a unit is set; throws `LogicException` if no unit enum is set, while a unit whose enum has no category (such as `'number'`) returns `null`. Example: `$dimension = $number->unitType();`. |
| `__get` | `public function __get(string $name): mixed`; exposes magic `type` as `CSSNumericType`; other names throw `Error`. Example: `$type = $number->type;`. |
| `__set` | `public function __set(string $name, mixed $value): void`; rejects all magic writes with `Error`; declared hooked properties remain governed by their own property visibility. Example: `$number->type = $type; // Error`. |
| `__toString` | `public function __toString(): string`; from `SimpleValueTrait`, returns the string form of the trait's `value` slot; numeric subclasses may override. Example: `$css = (string) $number;`. |
| `clone` | `public function clone(): static`; from `SimpleValueTrait`, shallow-clones the current object; math subclasses override for deep cloning. Example: `$copy = $number->clone();`. |

## Inherited static API

`public static function parseAll(string $property, string $cssText, ?CSSParserOptions $options = null): array` is inherited from `CSSStyleValue`; it returns `list<CSSStyleValue>` by splitting top-level comma-separated values. Example: `$parts = CSSNumericValue::parseAll('width', '2px, 3px');` (the result type is style values; numeric content is not guaranteed by this method itself).

## Source caveats

The trait's `value` and `length` properties are shared implementation slots and are not a universal numeric magnitude or operand count on every subclass. `unitType()` can return null for unitless values; `type()` itself is the numeric type-map method on this class.
