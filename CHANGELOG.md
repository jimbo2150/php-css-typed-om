# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.0.0] - Unreleased

### Added
- **CSS Values and Units Level 4 Math Functions & Constants:** Added `CSSMathFunction` and `CSSMathFunctionEnum` covering `round()`, `mod()`, `rem()`, trigonometric functions (`sin`, `cos`, `tan`, `asin`, `acos`, `atan`, `atan2`), exponential functions (`pow`, `sqrt`, `hypot`, `log`, `exp`), and sign functions (`abs`, `sign`), along with `CSSRoundingStrategy`, `CSSMathConstant`, `CSSMathSimplifier`, and `CSSContext` dimensional simplification/evaluation.
- **CSS Color Module Level 4 & Level 5 Completeness:** Added `color()`, `lab()`, `lch()`, `oklab()`, `oklch()`, `hwb()`, `color-mix()`, relative color syntax, `none` missing-component propagation across conversions, `CSSColorGamutMapper` binary-search OKLCH chroma gamut mapping, and `CSSDeferredColorException`.
- **CSSOM Parser, Grammar & Source Location Spans:** Added `CSSInputPreprocessor`, `CSSTokenizer`, `CSSValueComponentParser`, `CSSValueValidator`, `CSSGrammarParser`, `CSSGrammarMatcher`, `@color-profile` (`CSSColorProfileRule`), `@custom-media` (`CSSCustomMediaRule`), and optional source location tracking (`CSSSourceLocationIndex`, `CSSSourceSpan`, `CSSParserOptions::$trackSpans`).

### Changed
- **PHP 8.4+ Language Modernization:** Adopted PHP 8.4 typed property hooks, asymmetric visibility (`protected(set)`), backed enums (`CSSUnitEnum`, `CSSMathFunctionEnum`), and strict types across the codebase.
- **Internal Consolidation & Refactoring:** Consolidated duplicated shorthand codecs (`ShorthandCodecHelper`, `LayeredTimingShorthandCodec`, `BoxAndLineShorthandCodec`), three-channel color internals (`ThreeChannelColorTrait`), matrix fast paths (`DOMMatrix`), grammar registry caches (`CSSPropertyGrammarRegistry`), and declaration state flags (`CSSStyleDeclaration`), yielding significant speedups across rendering, color parsing/conversion, DOM/CSSOM mutation, and matrix operations.
- **Explicit `@internal` Boundaries:** Marked internal enums, factory helpers, scanner constants, and validation hooks with `@internal` to keep the documented public W3C CSSOM / Typed OM API surface clean (`docs/interfaces.md`).

### Security
- **Iterative AST & Rule Traversals:** Enforced explicit iterative work stacks across parsers, serializers, cloners, and math simplifiers to prevent recursion exhaustion on hostile inputs.
- **Resource Limits & Injection Defenses:** Enforced `CSSParserOptions` budgets (`maxBytes`, `maxDepth`, `maxRules`, `maxMathDepth`, `maxErrors`, `maxDeclarations`), AST node/allocation caps (`MAX_MATH_NODES`, `MAX_MATH_TREE_DEPTH`), upfront control-byte rejection, unbalanced delimiter/string/comment/URL rejection, and atomic validation before state mutation.
