# PHP CSS Typed Object Model (OM) and CSS OM

A standalone PHP model for parsing and manipulating CSS stylesheets, declarations, and CSS Typed OM values. The package targets PHP 8.4+ and currently has no runtime dependencies.

See the [API interfaces and methods reference](docs/interfaces.md) for a catalog of the public types and their methods.

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

`CSSStyleSheet` supports `parse()`, `cssRules`, `insertRule()`, `deleteRule()`, `replaceSync()`, `cssText`, `baseURL`, constructor `media` (`MediaList|string`), `disabled`, and parse diagnostics. Rule lists and declarations are ordered and mutable. Declarations implement W3C CSSOM §6.7 shorthand ↔ longhand expansion and canonical shortest-form serialization (expanding shorthands like `margin`, `padding`, `inset`, `border*`, `background`, `flex`, `gap`, `font`, `grid*`, `transition`, `animation`, `mask*`, `offset`, `place-*`, `white-space`, and `all` into constituent longhands, reconstructing canonical shorthands on `getPropertyValue()` / `cssText` / `CSSRenderer`, handling `!important` conflicts, pending-substitution `var()` values, and logical/physical cascade barriers). Declarations support `getPropertyValue()`, `getPropertyPriority()`, `setProperty()`, `removeProperty()`, `item()`, `length`, `parentRule`, and `cssText`. Strict descriptor subclasses (`CSSFontFaceDescriptors`, `CSSPageDescriptors`, and `CSSPositionTryDescriptors`) enforce their W3C descriptor whitelists (and disallow `!important` on `@font-face` and `@position-try`), while stylesheet parsing recovers gracefully with byte-offset diagnostics. `MediaList`, `StyleSheet`, `StyleSheetList`, and standalone `FontFace` provide standalone equivalents for media queries, stylesheet metadata, and font-face descriptors.

Use `CSSRenderer` when a readable or compact rendering is needed without changing the existing `cssText` serialization:

```php
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderMode;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderer;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderOptions;

echo CSSRenderer::render($sheet); // Pretty output (two-space indentation)
echo CSSRenderer::render($sheet, new CSSRenderOptions(CSSRenderMode::Minified));
```

`CSSRenderOptions` accepts an optional `ruleCallback` closure with the signature `function (CSSRule $rule, string $renderedText): string`. It runs after each rule's children are rendered, so transformed child text is included in its parent. This makes it suitable for intercepting and altering output:

```php
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderMode;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderer;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderOptions;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleRule;

// Append a banner comment to every rendered rule.
$banner = new CSSRenderOptions(
    mode: CSSRenderMode::Minified,
    ruleCallback: static function (CSSRule $rule, string $text): string {
        return "/* {$rule->getType()} */ $text";
    },
);
echo CSSRenderer::render($sheet, $banner);

// Prefix only top-level style selectors during pretty rendering.
$prefixed = new CSSRenderOptions(
    ruleCallback: static function (CSSRule $rule, string $text): string {
        if ($rule instanceof CSSStyleRule && $rule->getParentRule() === null) {
            $text = preg_replace(
                '/' . preg_quote($rule->getSelectorText(), '/') . '/',
                '.theme ' . $rule->getSelectorText(),
                $text,
                1,
            ) ?? $text;
        }
        return $text;
    },
);
echo CSSRenderer::render($sheet, $prefixed);
```

Set `keepComments` to `false` to strip `/* ... */` comments from `CSSOpaqueRule` output. Comments inside structured declarations are stripped during parsing and cannot be restored by the renderer; they only survive in opaque rules whose syntax is not understood.

```php
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderMode;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderer;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderOptions;

// Retain opaque-rule comments (default).
echo CSSRenderer::render($opaqueRule);

// Strip opaque-rule comments.
echo CSSRenderer::render($opaqueRule, new CSSRenderOptions(keepComments: false));
```

`CSSRenderOptions` defaults to pretty mode with comments retained. Unknown `CSSOpaqueRule` source is preserved verbatim in either mode because its syntax cannot be safely reformatted; minification is best-effort for such rules. Pass `escapeHtmlStyleTag: true` to escape `</style` closing sequences as `<\/style` when embedding CSS inside HTML `<style>` elements. Existing `getCssText()` and `cssText` output is unchanged.

Stylesheet parsing recovers at malformed declarations/rules and reports `message` and byte `offset` entries through `getParseErrors()`. Direct declaration replacement and rule insertion validate atomically before mutating and throw `InvalidArgumentException` on malformed or structurally unsafe input. Structured rules serialize in normalized form; unknown at-rules are retained as opaque source.

`CSSParserOptions` enforces configurable resource limits by default (`maxBytes`: 16 MiB, `maxDepth`: 256 nested rule levels, `maxRules`: 100,000 rules, `maxMathDepth`: 256 nested math levels, `maxErrors`: 1,000 diagnostics, `maxDeclarations`: 500,000 declarations). Stylesheet parsing records a diagnostic and stops safely at boundaries; strict entry points (`insertRule()`, `replaceSync()`, `CSSParser::parseSingleRule()`, `CSSNumericValue::parse()`, etc.) throw `CSSParserLimitException` (a subclass of `InvalidArgumentException`) without mutating existing state. Pass a custom `CSSParserOptions` instance or `CSSParserOptions::unlimited()` to override or disable limits. Opt-in flags `$browserErrorRecovery` (enabling CSS Syntax Level 3 §3.3 preprocessing and §5 error recovery) and `$trackSpans` (populating `CSSSourceSpan` byte/line/column offsets on rules and declarations) are also configured via `CSSParserOptions`.

The parser structures qualified and nested style rules; media, supports, container, scope, layer, keyframes, starting-style, font-feature-values, and other grouping rules; charset, import, namespace, and custom-media (`CSSCustomMediaRule`) rules; page rules with nested margin boxes; color-profile (`CSSColorProfileRule`), counter-style, font-face, font-palette-values, position-try, property, and view-transition rules; and CSS function rules with parameter and declaration objects. Standalone CSS Syntax Level 3 utilities (`CSSInputPreprocessor`, `CSSTokenizer`, `CSSSelectorList`, and `CSSMediaQueryParser`) are also exposed for inspecting token streams, selectors, and media queries. Selector and condition matching is not performed. Import rules retain their URL, layer, supports condition, and `MediaList` without fetching external sheets.

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

Numeric values expose `type()` / `$value->type` returning `CSSNumericType` dimension exponent maps (including `percentHint` when mixing percentages with a dimensional unit), variadic arithmetic, `calc()`, `min()`, `max()`, `clamp()`, CSS Values Level 4 math functions via `CSSMathFunction` (`round()`, `mod()`, `rem()`, `sin()`, `cos()`, `tan()`, `asin()`, `acos()`, `atan()`, `atan2()`, `pow()`, `sqrt()`, `hypot()`, `log()`, `exp()`, `abs()`, `sign()`), math constants via `CSSMathConstant` (`e`, `pi`, `infinity`, `-infinity`, `NaN`), calculation-tree `simplify()` and `clampToRange()`, and standalone read-only `CSSNumericArray` collections (`entries()`, `keys()`, `values()`, `forEach()`). `CSSUnitValue::$unit` returns canonical Typed OM unit names (`'number'`, `'percent'`, `'px'`, `'x'`, etc.) while string serialization outputs standard CSS (`5`, `50%`, `12px`, `2x`). Same-dimension units convert using exact CSS absolute factors (`72pt == 96px`, `2.54cm == 96px`, `25.4mm == 96px`, `1x == 1dppx == 96dpi`) and a supplied `CSSContext` for relative units; cross-dimension and non-finite inputs are rejected. `CSSContext` uses its supplied font size and viewport dimensions, with estimates for font-metric-relative units and viewport fallbacks for container/dynamic units. These conversions cannot reflect a browser's actual element or font metrics. Unit factories accept finite numeric strings, integers, and floats. `CSS::escape()` implements single-backslash CSSOM identifier escaping, `CSS::registerProperty()` manages a standalone custom-property registry, and `CSS::supports()` evaluates standalone CSS declaration and `@supports` condition syntax.

### Property Values and Maps

```php
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;

$width = CSSStyleValue::parse('width', '12px');
$display = CSSStyleValue::parse('display', 'grid');
$backgrounds = CSSStyleValue::parseAll('background-image', 'url("a.png"), linear-gradient(red, blue)');

$rule = $sheet->cssRules[1];
$rule->styleMap->set('width', $width);
```

Property parsing returns typed numeric, keyword, color (`CSSColorValue`: `CSSRGB`, `CSSHSL`, `CSSHWB`, `CSSLab`, `CSSLCH`, `CSSOKLab`, `CSSOKLCH`, `CSSColor`), image, position, and transform values where recognized. Color support covers hex, named colors (`transparent` and 148 standard names), `currentcolor`, all CSS Color 4 functional spaces, CSS Color 5 `color-mix()`, Relative Color Syntax (`from <color>`), context-resolved `light-dark()`, `none` missing-component propagation, cross-space conversions (`toRGB()`, `toHSL()`, `toHWB()`, `toLab()`, `toLCH()`, `toOKLab()`, `toOKLCH()`, `to()`), and OKLCH binary-search gamut mapping (`inGamut()`, `toGamut()`). Custom properties are represented as mutable `CSSUnparsedValue` sequences, with `var()` references exposed as `CSSVariableReferenceValue`. `var()` references and context-deferred expressions inside colors fall back to `CSSUnparsedValue` during property parsing (or throw `CSSDeferredColorException` on direct `CSSColorValue::parse()`). Transform values/components are iterable and mutable (`entries()`, `keys()`, `values()`, `forEach()`); Geometry Interfaces Level 1 types include `DOMMatrixReadOnly`, `DOMMatrix` (implementing `ArrayAccess` over column-major indices `0..15` and `toFloat64Array()`), `DOMPointReadOnly`, `DOMPoint`, `DOMRectReadOnly`, `DOMRect`, and `DOMQuad`. `StylePropertyMap` and `StylePropertyMapReadOnly` operate on standalone declaration blocks (`entries()`, `keys()`, `values()`, `forEach()`, variadic `set()`, and `append()`).

## Scope, Security, and Limitations

- This package models CSS source and objects; it is not a browser engine.
- Mutation APIs validate against structural CSS injection (rejecting top-level `;`, `{`, `}`, unbalanced quotes/comments, control bytes, and disallowed rule preludes before mutating state), while preserving valid nested blocks and quoted strings.
- **Not an HTML sanitizer:** Serialized CSS (`cssText`, `CSSOpaqueRule`, and raw unparsed fragments) is normalized CSS source and is **not** safe to interpolate directly into HTML `<style>` elements or attributes without HTML-context escaping (for inline `<style>` tags, `CSSRenderer` offers `escapeHtmlStyleTag: true` in `CSSRenderOptions`).
- It does not parse HTML, associate stylesheets with elements, match selectors, calculate the cascade/computed styles, or implement layout/rendering.
- CSSOM View, viewport/screen geometry, DOM event APIs, and browser-owned objects are out of scope.
- Standard properties are validated against W3C Value Definition Syntax grammars and registered `@property` syntaxes (`CSSPropertyRule` / `CSS::registerProperty()`), while unrecognized at-rules (`CSSOpaqueRule`), custom properties, and `var()` substitutions are retained cleanly; malformed syntax in stylesheets is recovered with byte-offset diagnostics.
- CSS color serialization follows W3C CSS Color 4/5 and Typed OM conventions (`CSSRGB` stores/serializes channels on the percentage scale; legacy comma syntax and out-of-gamut values convert and gamut-map deterministically); relative color expressions referencing `var()`, unconfigured `currentcolor`, or unresolved relative units fall back to `CSSUnparsedValue` in property parsing.
- CSS serialization of structured objects is normalized and is not a byte-for-byte stylesheet formatter.

## Development

```bash
composer test      # Full PHPUnit suite
composer test-wpt  # WPT compliance test suite
composer stan      # PHPStan Level 6 static analysis
composer cs-check  # PHP-CS-Fixer dry-run check
composer cs-fix    # Fix coding style
composer bench     # Standalone benchmark (XDEBUG_MODE=off + OPcache)
```

The test suite covers WPT fixtures, scanner nesting/escaping, Value Definition Syntax grammar matching, numeric/math/color/geometry Typed OM operations, declaration and rule mutation, malformed-input recovery, opaque syntax retention, and security/resource limit invariants.

See [CHANGELOG.md](CHANGELOG.md) and [v3.0.0 Release Notes](docs/releases/v3.0.0.md) for version history and upgrade details.

## References

- [CSS Typed OM API](https://developer.mozilla.org/en-US/docs/Web/API/CSS_Typed_OM_API)
- [CSS Object Model](https://developer.mozilla.org/en-US/docs/Web/API/CSS_Object_Model)
- [CSSOM specification](https://drafts.csswg.org/cssom/)
- [CSS Typed OM Level 1](https://drafts.css-houdini.org/css-typed-om/)
- [CSS Values and Units Level 4](https://drafts.csswg.org/css-values-4/)
- [CSS Color Module Level 4](https://drafts.csswg.org/css-color-4/) & [Level 5](https://drafts.csswg.org/css-color-5/)
- [CSS Syntax Module Level 3](https://drafts.csswg.org/css-syntax-3/)
- [Geometry Interfaces Module Level 1](https://drafts.fxtf.org/geometry/)
