# PHP CSS Typed Object Model (OM) and CSS OM

A standalone PHP model for parsing and manipulating CSS stylesheets, declarations, and CSS Typed OM values. The package targets PHP 8.4+ and currently has no runtime dependencies.

## Installation

```bash
composer require jimbo2150/php-css-typed-om
```

## CSSOM

Parse stylesheets into mutable `CSSStyleSheet`, `CSSRuleList`, `CSSStyleRule`, grouping-rule, and declaration objects:

```php
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleSheet;

$sheet = CSSStyleSheet::parse(<<<'CSS'
@media (min-width: 40rem) {
    .card { width: calc(100% - 2rem); }
}
CSS);

$mediaRule = $sheet->cssRules[0];
$styleRule = $mediaRule->cssRules[0];
$styleRule->style->setProperty('color', 'rebeccapurple');
$sheet->insertRule('.notice { display: block }', count($sheet->cssRules));

echo $sheet->cssText;
```

`CSSStyleSheet` supports `parse()`, `cssRules`, `insertRule()`, `deleteRule()`, `replaceSync()`, `cssText`, and parse diagnostics. Rule lists and declarations are ordered and mutable. Declarations support `getPropertyValue()`, `getPropertyPriority()`, `setProperty()`, `removeProperty()`, `item()`, `length`, `parentRule`, and `cssText`. `MediaList`, `StyleSheet`, and `StyleSheetList` provide standalone equivalents for media queries and stylesheet metadata.

Use `CSSRenderer` when a readable or compact rendering is needed without changing the existing `cssText` serialization:

```php
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderMode;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderer;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderOptions;

echo CSSRenderer::render($sheet); // Pretty output (two-space indentation)
echo CSSRenderer::render($sheet, new CSSRenderOptions(CSSRenderMode::Minified));
```

`CSSRenderOptions` accepts an optional `ruleCallback` closure with the signature `function (CSSRule $rule, string $renderedText): string`. It runs after each rule's children are rendered, so transformed child text is included in its parent. Unknown `CSSOpaqueRule` source is preserved verbatim in either mode because its syntax cannot be safely reformatted; minification is best-effort for such rules. Existing `getCssText()` and `cssText` output is unchanged.

Stylesheet parsing recovers at malformed declarations/rules and reports `message` and byte `offset` entries through `getParseErrors()`. Direct declaration replacement and rule insertion validate atomically before mutating and throw `InvalidArgumentException` on malformed or structurally unsafe input. Structured rules serialize in normalized form; unknown at-rules are retained as opaque source.

`CSSParserOptions` enforces configurable resource limits by default (`maxBytes`: 16 MiB, `maxDepth`: 256 nested rule levels, `maxRules`: 100,000 rules, `maxMathDepth`: 256 nested math levels). Stylesheet parsing records a diagnostic and stops safely at boundaries; strict entry points (`insertRule()`, `replaceSync()`, `CSSParser::parseSingleRule()`, and `CSSNumericValue::parse()`) throw `InvalidArgumentException` without mutating existing state. Pass a custom `CSSParserOptions` instance or `CSSParserOptions::unlimited()` to override or disable limits.

The parser structures qualified and nested style rules; media, supports, container, scope, layer, keyframes, starting-style, font-feature-values, and other grouping rules; charset, import, and namespace rules; page rules with nested margin boxes; counter-style, font-face, font-palette-values, position-try, property, and view-transition rules; and CSS function rules with parameter and declaration objects. Selector and condition matching is not performed. Import rules retain their URL, layer, supports condition, and `MediaList` without fetching external sheets.

## Typed OM Values

### Numeric Values

```php
use Jimbo2150\PhpCssTypedOm\CSS;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;

$width = CSSNumericValue::parse('12px');
echo $width->value;        // 12
echo $width->unit;         // px
echo $width->type->length; // 1

$minimum = CSSNumericValue::parse('clamp(10px, 5vw, 100px)');
echo $minimum; // clamp(10px, 5vw, 100px)

$height = CSS::em(2);
```

Numeric values expose `type()` / `$value->type` returning `CSSNumericType` dimension exponent maps (including `percentHint` when mixing percentages with a dimensional unit), variadic arithmetic, `calc()`, `min()`, `max()`, and `clamp()`, and read-only `CSSNumericArray` collections. Same-dimension units convert using exact CSS absolute factors (`72pt == 96px`, `2.54cm == 96px`, `25.4mm == 96px`) and a supplied `CSSContext` for relative units; cross-dimension and non-finite inputs are rejected. `CSSContext` uses its supplied font size and viewport dimensions, with estimates for font-metric-relative units and viewport fallbacks for container/dynamic units. These conversions cannot reflect a browser's actual element or font metrics. Unit factories accept finite numeric strings, integers, and floats. `CSS::escape()` implements single-backslash CSSOM identifier escaping.

### Property Values and Maps

```php
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;

$width = CSSStyleValue::parse('width', '12px');
$display = CSSStyleValue::parse('display', 'grid');
$backgrounds = CSSStyleValue::parseAll('background-image', 'url("a.png"), linear-gradient(red, blue)');

$rule = $sheet->cssRules[1];
$rule->styleMap->set('width', $width);
```

Property parsing returns typed numeric, keyword, image, position, and transform values where recognized. Transform values/components are iterable and mutable; `CSSTransformValue::toMatrix()` returns a 16-number, column-major 4x4 matrix. Custom properties are represented as mutable `CSSUnparsedValue` sequences, with `var()` references exposed as `CSSVariableReferenceValue`. Unsupported or property-specific grammars remain available as unparsed source instead of being silently discarded. `StylePropertyMap` and `StylePropertyMapReadOnly` operate on standalone declaration blocks (`entries()`, `keys()`, `values()`, `forEach()`, variadic `set()`, and `append()`).

## Scope, Security, and Limitations

- This package models CSS source and objects; it is not a browser engine.
- Mutation APIs validate against structural CSS injection (rejecting top-level `;`, `{`, `}`, unbalanced quotes/comments, control bytes, and disallowed rule preludes before mutating state), while preserving valid nested blocks and quoted strings.
- **Not an HTML sanitizer:** Serialized CSS (`cssText`, `CSSOpaqueRule`, and raw unparsed fragments) is normalized CSS source and is **not** safe to interpolate directly into HTML `<style>` elements or attributes without HTML-context escaping.
- It does not parse HTML, associate stylesheets with elements, match selectors, calculate the cascade/computed styles, or implement layout/rendering.
- CSSOM View, viewport/screen geometry, DOM event APIs, and browser-owned objects are out of scope.
- The parser validates CSS structure, not every property grammar. Unknown valid rules and values are retained; malformed syntax may be recovered with diagnostics.
- CSS color syntax currently uses the generic unparsed-value fallback.
- CSS serialization of structured objects is normalized and is not a byte-for-byte stylesheet formatter.

## Development

Run the test suite with:

```bash
composer test
```

The test suite covers scanner nesting/escaping, numeric and Typed OM values, declaration and rule mutation, malformed-input recovery, opaque syntax retention, and serialization round trips.

## References

- [CSS Typed OM API](https://developer.mozilla.org/en-US/docs/Web/API/CSS_Typed_OM_API)
- [CSS Object Model](https://developer.mozilla.org/en-US/docs/Web/API/CSS_Object_Model)
- [CSSOM specification](https://drafts.csswg.org/cssom/)
- [CSS Typed OM Level 1](https://drafts.css-houdini.org/css-typed-om/)
