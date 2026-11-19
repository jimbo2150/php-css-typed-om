# `CSSRenderer`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderer`
- **Source:** [`src/CSSOM/CSSRenderer.php`](../../../src/CSSOM/CSSRenderer.php)
- **Purpose:** Deterministically renders CSS stylesheets and individual rules.
- **Constants:** `INDENT` is private; no public constants or instance properties.

## Public Methods

| Signature | Parameters / behavior | Example |
|---|---|---|
| `render(CSSStyleSheet|CSSRule $source, ?CSSRenderOptions $options = null): string` (static) | Renders a stylesheet's rules or one rule. Defaults to `CSSRenderOptions`; pretty output separates stylesheet rules by newline. Opaque rules retain source unless `keepComments` is false. The optional callback transforms each rendered rule after its children. | `$css = CSSRenderer::render($sheet, new CSSRenderOptions(CSSRenderMode::Minified));` |

No public constructor or other public methods are declared.
