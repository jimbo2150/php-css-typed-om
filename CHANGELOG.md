# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.0.0] - Unreleased

### Added
- **CSS Values and Units Level 4 Math Functions & Constants:** Added `CSSMathFunction` and `CSSMathFunctionEnum` covering `round()`, `mod()`, `rem()`, trigonometric functions (`sin`, `cos`, `tan`, `asin`, `acos`, `atan`, `atan2`), exponential functions (`pow`, `sqrt`, `hypot`, `log`, `exp`), and sign functions (`abs`, `sign`), along with `CSSRoundingStrategy`, `CSSMathConstant`, `CSSMathConstantEnum`, `CSSMathSimplifier`, and `CSSContext` dimensional simplification/evaluation.
- **CSS Color Module Level 4 & Level 5 Completeness:** Added `color()`, `lab()`, `lch()`, `oklab()`, `oklch()`, `hwb()`, `color-mix()`, Relative Color Syntax (`from <color>`), context-resolved `light-dark()`, `none` missing-component propagation across conversions, `CSSColorGamutMapper` binary-search OKLCH chroma gamut mapping (`inGamut()`, `toGamut()`), and `CSSDeferredColorException`.
- **Geometry Interfaces Level 1 Completeness:** Added `DOMPointReadOnly`, `DOMPoint`, `DOMRectReadOnly`, `DOMRect`, and `DOMQuad` alongside hardened `DOMMatrixReadOnly` / `DOMMatrix`.
- **CSS Syntax 3, Property Grammars & Source Spans:** Added `CSSInputPreprocessor`, `CSSTokenizer`, `CSSToken`, `CSSTokenType`, `CSSSelectorList`, `CSSMediaQueryParser`, `CSSValueComponentParser`, `CSSValueValidator`, `CSSGrammarParser`, `CSSGrammarMatcher`, `@color-profile` (`CSSColorProfileRule`), `@custom-media` (`CSSCustomMediaRule`), `CSSParserOptions::$browserErrorRecovery`, and optional source location tracking (`CSSSourceLocationIndex`, `CSSSourceSpan`, `CSSParserOptions::$trackSpans`).

### Changed
- **PHP 8.4+ Language Modernization:** Raised baseline expectations cleanly to PHP 8.4+, adopting typed property hooks, asymmetric visibility (`protected(set)`), backed enums (`CSSUnitEnum`, `CSSMathFunctionEnum`, `CSSMathConstantEnum`, `CSSRoundingStrategy`), and `declare(strict_types=1);`.
- **Internal Consolidation & Performance:** Consolidated shorthand codecs (`ShorthandCodecHelper`, `LayeredTimingShorthandCodec`, `BoxAndLineShorthandCodec`, `BackgroundAndMaskShorthandCodec`, `FontShorthandCodec`, `GridShorthandCodec`), three-channel color internals (`ThreeChannelColorTrait`), matrix fast paths (`DOMMatrix`), grammar caches (`CSSPropertyGrammarRegistry`), single-pass fragment validation (`CSSSyntaxScanner`), and compact declaration state flags (`CSSStyleDeclaration`), delivering major speedups across rendering, color parsing/conversion, CSSOM mutations, transforms/matrices, and scanner validation.
- **Explicit `@internal` Boundaries:** Marked internal enums, factory helpers, scanner constants, and validation hooks with `@internal` to keep the documented public W3C CSSOM / Typed OM API surface clean ([docs/interfaces.md](docs/interfaces.md)).

### Removed
- Removed internal helper trait files under `src/TypedOM/Traits/` (`LengthTrait`, `MultiValueArrayTrait`, `MultiValueTrait`, `SimpleValueTrait`, `TypeableUnitTrait`) and `src/CSSOM/CSSStandardProperties.php` (replaced by `CSSPropertyGrammarRegistry` and direct class methods).

### Security
- **Iterative AST & Rule Traversals:** Enforced explicit iterative work stacks across parsers, serializers, cloners, and math simplifiers to prevent stack exhaustion on hostile inputs.
- **Resource Limits & Injection Defenses:** Enforced `CSSParserOptions` budgets (`maxBytes`, `maxDepth`, `maxRules`, `maxMathDepth`, `maxErrors`, `maxDeclarations`), AST node/allocation caps (`MAX_MATH_NODES`, `MAX_MATH_TREE_DEPTH`), upfront control-byte rejection, unbalanced delimiter/string/comment/URL rejection, and atomic validation before state mutation.
