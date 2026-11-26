# Project interfaces and public API

This catalog covers the types declared under `src/`: public classes (including abstract/base classes), enums, and traits. The package declares no PHP `interface` types; the word “interface” here means the library's public API types. Each linked page documents the type's public methods, parameters, return behavior, and usage examples, plus its public properties, constants, or enum cases where applicable. Methods inherited from parent classes are included on the relevant API pages.

The documented names and signatures follow the PHP source. Trait pages describe the public methods contributed when a consuming class uses that trait. Internal/private helpers are not part of the public API.

## Root utilities

- [`CSS`](interfaces/CSS.md) — typed CSS unit factories (including `x`), CSS identifier escaping, standalone `supports()`, and `registerProperty()`.

## Geometry

- [`DOMMatrixReadOnly`](interfaces/Geometry/DOMMatrixReadOnly.md) — read-only 4x4 column-major transformation matrix.
- [`DOMMatrix`](interfaces/Geometry/DOMMatrix.md) — mutable 4x4 transformation matrix.

## CSS Object Model (CSSOM)

### Stylesheets, parsing, and rendering

- [`CSSParser`](interfaces/CSSOM/CSSParser.md) — stylesheet and single-rule parsing entry points.
- [`CSSParserLimitException`](interfaces/CSSOM/CSSParserLimitException.md) — exception thrown when parser or renderer resource limits are exceeded.
- [`CSSParserOptions`](interfaces/CSSOM/CSSParserOptions.md) — parser resource-limit configuration.
- [`CSSRenderer`](interfaces/CSSOM/CSSRenderer.md) — pretty and compact CSS rendering.
- [`CSSRenderMode`](interfaces/CSSOM/CSSRenderMode.md) — renderer output mode enum.
- [`CSSRenderOptions`](interfaces/CSSOM/CSSRenderOptions.md) — renderer callbacks and formatting options.
- [`CSSStyleSheet`](interfaces/CSSOM/CSSStyleSheet.md) — mutable parsed stylesheet.
- [`StyleSheet`](interfaces/CSSOM/StyleSheet.md) — base stylesheet metadata API.
- [`StyleSheetList`](interfaces/CSSOM/StyleSheetList.md) — ordered collection of stylesheets.

### Rules and rule collections

- [`CSSRule`](interfaces/CSSOM/CSSRule.md) — base rule type and rule constants.
- [`CSSRuleList`](interfaces/CSSOM/CSSRuleList.md) — ordered mutable rule collection.
- [`CSSAtRule`](interfaces/CSSOM/CSSAtRule.md) — base at-rule type.
- [`CSSGroupingRule`](interfaces/CSSOM/CSSGroupingRule.md) — base rule containing nested rules.
- [`CSSConditionRule`](interfaces/CSSOM/CSSConditionRule.md) — base conditional grouping rule.
- [`CSSStyleRule`](interfaces/CSSOM/CSSStyleRule.md) — selector and declaration rule.
- [`CSSMediaRule`](interfaces/CSSOM/CSSMediaRule.md) — `@media` rule.
- [`CSSSupportsRule`](interfaces/CSSOM/CSSSupportsRule.md) — `@supports` rule.
- [`CSSContainerRule`](interfaces/CSSOM/CSSContainerRule.md) — `@container` rule.
- [`CSSScopeRule`](interfaces/CSSOM/CSSScopeRule.md) — `@scope` rule.
- [`CSSLayerBlockRule`](interfaces/CSSOM/CSSLayerBlockRule.md) — block-form `@layer` rule.
- [`CSSLayerStatementRule`](interfaces/CSSOM/CSSLayerStatementRule.md) — statement-form `@layer` rule.
- [`CSSStartingStyleRule`](interfaces/CSSOM/CSSStartingStyleRule.md) — `@starting-style` rule.
- [`CSSKeyframesRule`](interfaces/CSSOM/CSSKeyframesRule.md) — `@keyframes` container.
- [`CSSKeyframeRule`](interfaces/CSSOM/CSSKeyframeRule.md) — individual keyframe rule.
- [`CSSNestedDeclarations`](interfaces/CSSOM/CSSNestedDeclarations.md) — declarations nested in a style rule.
- [`CSSOpaqueRule`](interfaces/CSSOM/CSSOpaqueRule.md) — retained rule syntax without a structured model.
- [`CSSCharsetRule`](interfaces/CSSOM/CSSCharsetRule.md) — `@charset` rule.
- [`CSSImportRule`](interfaces/CSSOM/CSSImportRule.md) — `@import` rule and media metadata.
- [`CSSNamespaceRule`](interfaces/CSSOM/CSSNamespaceRule.md) — `@namespace` rule.
- [`CSSPageRule`](interfaces/CSSOM/CSSPageRule.md) — `@page` rule.
- [`CSSMarginRule`](interfaces/CSSOM/CSSMarginRule.md) — page margin-box rule.
- [`CSSFontFaceRule`](interfaces/CSSOM/CSSFontFaceRule.md) — `@font-face` rule.
- [`CSSFontFeatureValuesRule`](interfaces/CSSOM/CSSFontFeatureValuesRule.md) — `@font-feature-values` rule.
- [`CSSFontFeatureValuesMap`](interfaces/CSSOM/CSSFontFeatureValuesMap.md) — map of font-feature-values entries.
- [`CSSFontPaletteValuesRule`](interfaces/CSSOM/CSSFontPaletteValuesRule.md) — `@font-palette-values` rule.
- [`CSSCounterStyleRule`](interfaces/CSSOM/CSSCounterStyleRule.md) — `@counter-style` rule.
- [`CSSPropertyRule`](interfaces/CSSOM/CSSPropertyRule.md) — registered `@property` rule.
- [`CSSPositionTryRule`](interfaces/CSSOM/CSSPositionTryRule.md) — `@position-try` rule.
- [`CSSViewTransitionRule`](interfaces/CSSOM/CSSViewTransitionRule.md) — `@view-transition` rule.
- [`CSSFunctionRule`](interfaces/CSSOM/CSSFunctionRule.md) — CSS function rule.
- [`CSSFunctionDeclarations`](interfaces/CSSOM/CSSFunctionDeclarations.md) — function rule declaration data.

### Declaration blocks and descriptors

- [`CSSStyleDeclaration`](interfaces/CSSOM/CSSStyleDeclaration.md) — mutable CSS declaration block.
- [`CSSStyleProperties`](interfaces/CSSOM/CSSStyleProperties.md) — style properties declaration block.
- [`CSSFontFaceDescriptors`](interfaces/CSSOM/CSSFontFaceDescriptors.md) — font-face descriptors.
- [`CSSPageDescriptors`](interfaces/CSSOM/CSSPageDescriptors.md) — page descriptors.
- [`CSSPositionTryDescriptors`](interfaces/CSSOM/CSSPositionTryDescriptors.md) — position-try descriptors.
- [`CSSFunctionDescriptors`](interfaces/CSSOM/CSSFunctionDescriptors.md) — function parameter descriptors.
- [`MediaList`](interfaces/CSSOM/MediaList.md) — ordered mutable media-query list.
- [`FontFace`](interfaces/CSSOM/FontFace.md) — standalone font-face metadata and descriptor model.

## Parser utilities

- [`CSSCalcParser`](interfaces/Parser/CSSCalcParser.md) — parser for CSS `calc()` arithmetic.
- [`CSSSyntaxScanner`](interfaces/Parser/CSSSyntaxScanner.md) — top-level splitting, delimiter matching, and CSS-fragment validation.

## Typed Object Model (Typed OM)

### Property maps and context

- [`CSSContext`](interfaces/TypedOM/CSSContext.md) — environment values used for relative-unit conversion.
- [`StylePropertyMap`](interfaces/TypedOM/StylePropertyMap.md) — mutable property-to-value map.
- [`StylePropertyMapReadOnly`](interfaces/TypedOM/StylePropertyMapReadOnly.md) — read-only property-to-value map API.

### Style values and transforms

- [`CSSStyleValue`](interfaces/TypedOM/CSSStyleValue.md) — base class and parsing entry points for style values.
- [`CSSKeywordValue`](interfaces/TypedOM/CSSKeywordValue.md) — CSS keyword value.
- [`CSSImageValue`](interfaces/TypedOM/CSSImageValue.md) — CSS image value.
- [`CSSPositionValue`](interfaces/TypedOM/CSSPositionValue.md) — CSS position value.
- [`CSSUnparsedValue`](interfaces/TypedOM/CSSUnparsedValue.md) — mutable sequence of unparsed CSS tokens.
- [`CSSVariableReferenceValue`](interfaces/TypedOM/CSSVariableReferenceValue.md) — `var()` reference and fallback.
- [`CSSTransformComponent`](interfaces/TypedOM/CSSTransformComponent.md) — base transform component type.
- [`CSSTransformValue`](interfaces/TypedOM/CSSTransformValue.md) — transform-list value and matrix conversion.
- [`CSSMatrixComponent`](interfaces/TypedOM/CSSMatrixComponent.md) — matrix transform component.
- [`CSSPerspective`](interfaces/TypedOM/CSSPerspective.md) — perspective transform component.
- [`CSSRotate`](interfaces/TypedOM/CSSRotate.md) — rotate transform component.
- [`CSSScale`](interfaces/TypedOM/CSSScale.md) — scale transform component.
- [`CSSSkew`](interfaces/TypedOM/CSSSkew.md) — two-axis skew transform component.
- [`CSSSkewX`](interfaces/TypedOM/CSSSkewX.md) — horizontal skew transform component.
- [`CSSSkewY`](interfaces/TypedOM/CSSSkewY.md) — vertical skew transform component.
- [`CSSTranslate`](interfaces/TypedOM/CSSTranslate.md) — translate transform component.

### Color values

- [`CSSColorValue`](interfaces/TypedOM/Color/CSSColorValue.md) — abstract base class for CSS Typed OM colors, parsing, and CSS Color 4 conversions.
- [`CSSRGB`](interfaces/TypedOM/Color/CSSRGB.md) — sRGB color value using W3C CSS Color 5 percentage channels.
- [`CSSHSL`](interfaces/TypedOM/Color/CSSHSL.md) — HSL color value.
- [`CSSHWB`](interfaces/TypedOM/Color/CSSHWB.md) — HWB color value.
- [`CSSLab`](interfaces/TypedOM/Color/CSSLab.md) — CIE Lab color value.
- [`CSSLCH`](interfaces/TypedOM/Color/CSSLCH.md) — CIE LCH color value.
- [`CSSOKLab`](interfaces/TypedOM/Color/CSSOKLab.md) — OKLab color value.
- [`CSSOKLCH`](interfaces/TypedOM/Color/CSSOKLCH.md) — OKLCH color value.
- [`CSSColor`](interfaces/TypedOM/Color/CSSColor.md) — generic `color(<colorspace> ...)` value.

### Numeric values and math

- [`CSSNumericValue`](interfaces/TypedOM/CSSNumericValue.md) — base numeric value parsing and arithmetic API.
- [`CSSUnitValue`](interfaces/TypedOM/CSSUnitValue.md) — scalar CSS number with a unit.
- [`CSSNumericType`](interfaces/TypedOM/CSSNumericType.md) — numeric dimension-exponent map.
- [`CSSNumericArray`](interfaces/TypedOM/CSSNumericArray.md) — read-only sequence of numeric values.
- [`CSSUnitEnum`](interfaces/TypedOM/CSSUnitEnum.md) — supported CSS unit symbols and metadata.
- [`CSSUnitTypeEnum`](interfaces/TypedOM/CSSUnitTypeEnum.md) — CSS unit category enum.
- [`CSSMathValue`](interfaces/TypedOM/CSSMathValue.md) — base CSS math expression type.
- [`CSSMathSum`](interfaces/TypedOM/CSSMathSum.md) — sum expression.
- [`CSSMathProduct`](interfaces/TypedOM/CSSMathProduct.md) — product expression.
- [`CSSMathMin`](interfaces/TypedOM/CSSMathMin.md) — `min()` expression.
- [`CSSMathMax`](interfaces/TypedOM/CSSMathMax.md) — `max()` expression.
- [`CSSMathClamp`](interfaces/TypedOM/CSSMathClamp.md) — `clamp()` expression.
- [`CSSMathNegate`](interfaces/TypedOM/CSSMathNegate.md) — negated numeric expression.
- [`CSSMathInvert`](interfaces/TypedOM/CSSMathInvert.md) — reciprocal numeric expression.
- [`CSSMathFunction`](interfaces/TypedOM/CSSMathFunction.md) — CSS Values Level 4 math function (`round`, `mod`, `rem`, trigonometric, exponential, sign).
- [`CSSMathFunctionEnum`](interfaces/TypedOM/CSSMathFunctionEnum.md) — CSS Values Level 4 math function identifier enum.
- [`CSSRoundingStrategy`](interfaces/TypedOM/CSSRoundingStrategy.md) — `round()` rounding strategy enum.
- [`CSSMathConstant`](interfaces/TypedOM/CSSMathConstant.md) — CSS math constant (`e`, `pi`, `infinity`, `-infinity`, `NaN`).
- [`CSSMathConstantEnum`](interfaces/TypedOM/CSSMathConstantEnum.md) — CSS math constant identifier enum.

### Public traits

Traits are listed because their methods become available on classes that use them; their pages describe the methods they contribute and their expectations of the consuming class.

- [`LengthTrait`](interfaces/TypedOM/LengthTrait.md)
- [`MultiValueArrayTrait`](interfaces/TypedOM/MultiValueArrayTrait.md)
- [`MultiValueTrait`](interfaces/TypedOM/MultiValueTrait.md)
- [`SimpleValueTrait`](interfaces/TypedOM/SimpleValueTrait.md)
- [`TypeableUnitTrait`](interfaces/TypedOM/TypeableUnitTrait.md)
