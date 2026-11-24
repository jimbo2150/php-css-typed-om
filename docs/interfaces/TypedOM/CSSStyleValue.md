# CSSStyleValue

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue`  
**Source:** [`src/TypedOM/Values/CSSStyleValue.php`](../../../src/TypedOM/Values/CSSStyleValue.php)  
**Purpose:** Abstract base for CSS Typed OM values. It parses generic/property-specific CSS text into numeric, keyword, image, transform, position, or unparsed value objects.

## Public API

Declares `public const int MAX_LIST_ITEMS = 4_096;` (enforced by `parseAll()`). The class is abstract and implements `Stringable`.

| Method | Signature and behavior |
|---|---|
| `__construct` | `public function __construct()`; no-op base constructor. Example: subclasses commonly call `parent::__construct()` when initializing their own state. |
| `parse` | `public static function parse(string $propertyOrCssText, ?string $cssText = null, ?CSSParserOptions $options = null): CSSStyleValue`; parses standalone CSS with one argument, or a property's value with two arguments (enforcing `$options->maxBytes` and `CSSStyleDeclaration::normalizePropertyName()` on the property name). Recognizes CSS-wide keywords, custom properties, supported transforms, supported images, object/background positions, colors, numeric values, and known property keywords, falling back to `CSSUnparsedValue`. Throws `InvalidArgumentException` for empty/structurally invalid property names or values, or `CSSParserLimitException` on limit exhaustion. Example: `$value = CSSStyleValue::parse('width', '12px');`. |
| `parseAll` | `public static function parseAll(string $property, string $cssText, ?CSSParserOptions $options = null): array`; validates `$property` (size + `CSSStyleDeclaration::normalizePropertyName()`), splits up to `MAX_LIST_ITEMS` top-level comma segments, parses non-empty members, and returns `list<CSSStyleValue>`. Throws `InvalidArgumentException` / `CSSParserLimitException` for invalid property names, exceeded list/byte limits, structurally invalid text, or when no non-empty member remains; empty comma-separated members are skipped. Example: `$values = CSSStyleValue::parseAll('box-shadow', '1px 2px, 3px 4px');`. |
| `__toString` | `abstract public function __toString(): string`; subclasses serialize their value as CSS text. Example: `$css = (string) $value;`. |
| `clone` | `abstract public function clone(): static`; subclasses return an independent typed-value copy. Example: `$copy = $value->clone();`. |

## Parse behavior

The parser rejects empty fragments and the disallowed declaration delimiters `;`, `{`, and `}` before classification. CSS-wide keywords are returned as `CSSKeywordValue`. A custom-property name routes to `CSSUnparsedValue`; supported transform/image/position syntax is attempted only for matching property/text cases. Unknown or unsupported syntax is preserved as unparsed text rather than interpreted based on its name.
