# PHP CSS Typed OM and CSSOM

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

`CSSStyleSheet` supports `parse()`, `cssRules`, `insertRule()`, `deleteRule()`, `replaceSync()`, `cssText`, and parse diagnostics. Rule lists and declarations are ordered and mutable. Declarations support `getPropertyValue()`, `getPropertyPriority()`, `setProperty()`, `removeProperty()`, `item()`, `length`, and `cssText`. `MediaList`, `StyleSheet`, and `StyleSheetList` provide standalone equivalents for media queries and stylesheet metadata.

Stylesheet parsing recovers at malformed declarations/rules and reports `message` and byte `offset` entries through `getParseErrors()`. Direct declaration replacement and rule insertion validate before mutating and throw `InvalidArgumentException` on malformed input. Structured rules serialize in normalized form; unknown at-rules are retained as opaque source.

The parser structures qualified and nested style rules; media, supports, layer, keyframes, starting-style, and other grouping rules; import/namespace rules; page margin boxes; position-try rules; and CSS function rules with parameter and declaration objects. Selector and condition matching is not performed. Import rules retain their URL and media text but do not fetch or attach an imported sheet.

## Typed OM Values

### Numeric Values

```php
use Jimbo2150\PhpCssTypedOm\CSS;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;

$width = CSSNumericValue::parse('12px');
echo $width->value; // 12
echo $width->unit;  // px

$minimum = CSSNumericValue::parse('clamp(10px, 5vw, 100px)');
echo $minimum; // clamp(10px, 5vw, 100px)

$height = CSS::em(2);
```

Numeric values include unit/type enums and exponent vectors, variadic arithmetic, `calc()`, `min()`, `max()`, and `clamp()`. Same-dimension units convert through a supplied `CSSContext`; cross-dimension conversions are rejected. `CSSContext` uses its supplied font size and viewport dimensions, with estimates for font-metric-relative units and viewport fallbacks for container/dynamic units. These conversions cannot reflect a browser's actual element or font metrics. Unit factories accept numeric strings, integers, and floats.

### Property Values and Maps

```php
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;

$width = CSSStyleValue::parse('width', '12px');
$display = CSSStyleValue::parse('display', 'grid');
$backgrounds = CSSStyleValue::parseAll('background-image', 'url("a.png"), linear-gradient(red, blue)');

$rule = $sheet->cssRules[1];
$rule->styleMap->set('width', $width);
```

Property parsing returns typed numeric, keyword, image, position, and transform values where recognized. Transform values/components are iterable and mutable; `CSSTransformValue::toMatrix()` returns a 16-number, column-major 4x4 matrix. Custom properties are represented as mutable `CSSUnparsedValue` sequences, with `var()` references exposed as `CSSVariableReferenceValue`. Unsupported or property-specific grammars remain available as unparsed source instead of being silently discarded. `StylePropertyMap` and `StylePropertyMapReadOnly` operate on standalone declaration blocks.

## Scope and Limitations

- This package models CSS source and objects; it is not a browser engine.
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
