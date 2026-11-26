# W3C Web Platform Tests (WPT) Conformance Fixtures

This directory contains self-contained conformance fixture vectors derived from the official [W3C Web Platform Tests (`web-platform-tests/wpt`)](https://github.com/web-platform-tests/wpt) repository, pinned in `manifest.json` to commit `1dcf57d2b9a6eb07162eef4daa32d0754de38650`.

## License & Attribution

Test vectors derived from `web-platform-tests/wpt` are licensed under the **W3C 3-Clause BSD License** (see [`tests/WPT/LICENSE`](LICENSE)).

## Covered Specifications & Dimensions

Six core specification modules are tested across four conformance categories (`parsing`, `serialization`, `canonicalization`, `error-recovery`):

- `css/css-typed-om` — `CSSStyleValue`, `CSSNumericValue`, `CSSTransformValue`, `CSSPositionValue`, `CSSUnparsedValue`, `StylePropertyMap`, `CSS.*` unit factories
- `css/cssom` — `CSS::escape()`, `CSS::supports()`, `CSSStyleSheet`, `CSSStyleDeclaration`, `MediaList`, rule mutation & serialization
- `css/css-syntax` — CSS escaping/decoding, error recovery on malformed rules/declarations, EOF handling, comments/strings/URLs
- `css/css-values` — `calc()`, `min()`, `max()`, `clamp()`, CSS Values Level 4 math functions, simplification, unit canonicalization, `e`/`pi`/`infinity`/`-infinity`/`NaN`
- `css/css-color` — `#hex`, `rgb()`, `hsl()`, `hwb()`, `lab()`, `lch()`, `oklab()`, `oklch()`, `color()`, color conversions, invalid syntax rejection
- `css/geometry` — `DOMMatrixReadOnly`, `DOMMatrix`, constructors, transform string parsing, operations, `invertSelf()`, serialization, error handling

## Fixture Schema (`schemaVersion: 1`)

Each JSON file under `tests/WPT/fixtures/<spec>/` declares:

- `schemaVersion` (`1`), `spec`, `wptCommit`, `upstreamFiles`, and `cases`.
- Each case has:
  - `id`: globally unique identifier (`<spec-short>/<area>/<name>`)
  - `upstream`: normalized relative path inside `web-platform-tests/wpt` (e.g. `css/cssom/escape.html`)
  - `category`: `parsing` | `serialization` | `canonicalization` | `error-recovery`
  - `operation`: closed allowlist key defined in `WptSchema::OPERATIONS_BY_SPEC`
  - `input` / `expected`: structured test payload
  - `status`: `pass` | `xfail`
  - When `status === "xfail"`: `xfailReason`, `divergenceKind` (`security-invariant` | `out-of-scope` | `api-tradeoff` | `spec-gap`), and `specRef` (`https://...`) are required.
  - When `status === "pass"`: `xfailReason` and `divergenceKind` must not be present.

## Strict `xfail` / `XPASS` Policy

PHPUnit runs with `failOnSkipped="true"` and `failOnIncomplete="true"`, so expected divergences are **never** skipped. Instead, `WptContractTestCase` executes `xfail` vectors and requires them to fail with a specification assertion or domain exception (while always propagating `CSSParserLimitException`, `Error`, `TypeError`, and `LogicException`). If an `xfail` vector unexpectedly passes (`XPASS`), the test fails and prompts promoting the vector to `"status": "pass"`.

## Commands

```bash
composer test-wpt                                  # Run WPT PHPUnit conformance suite
composer wpt-audit                                 # Offline schema, manifest & orphan check
php tools/wpt-sync.php --audit --json              # Machine-readable audit report
php tools/wpt-sync.php --verify-upstream=/path/wpt # Verify pinned commit & upstream paths against local WPT clone
```
