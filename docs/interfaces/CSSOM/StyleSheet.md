# `StyleSheet`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\StyleSheet`
- **Source:** [`src/CSSOM/StyleSheet.php`](../../../src/CSSOM/StyleSheet.php)
- **Purpose:** Abstract base for standalone stylesheet metadata and disabled state.

## Properties

Constructor metadata `href`, `title`, `ownerRule`, and `parentStyleSheet` are private readonly. Magic properties `type`, `href`, `title`, `disabled`, `media`, `ownerRule`, and `parentStyleSheet` expose getters; `disabled` can be assigned a boolean. No public constants or properties.

## Public Methods

| Signature | Parameters / behavior | Example |
|---|---|---|
| `__construct(?string $href = null, string $title = '', ?MediaList $media = null, ?CSSRule $ownerRule = null, ?StyleSheet $parentStyleSheet = null)` | Initializes metadata; uses an empty `MediaList` if none is supplied. | `$sheet = new CSSStyleSheet('/css/site.css', 'Site');` |
| `getType(): string` | Returns `'text/css'`. | `$sheet->getType();` |
| `getHref(): ?string` | Returns configured href or `null`. | `$sheet->getHref();` |
| `getTitle(): string` | Returns configured title. | `$sheet->getTitle();` |
| `isDisabled(): bool` | Returns current disabled state; initially false. | `$sheet->isDisabled();` |
| `setDisabled(bool $disabled): void` | Updates disabled state. | `$sheet->setDisabled(true);` |
| `getMedia(): MediaList` | Returns the sheet media list. | `$sheet->getMedia()->appendMedium('print');` |
| `getOwnerRule(): ?CSSRule` | Returns owning rule or `null`. | `$sheet->getOwnerRule();` |
| `getParentStyleSheet(): ?StyleSheet` | Returns parent sheet or `null`. | `$sheet->getParentStyleSheet();` |
| `__get(string $name): mixed` | Maps the seven virtual properties above; unknown properties throw `Error`. | `$sheet->media;` |
| `__set(string $name, mixed $value): void` | Boolean `disabled` assignment calls setter; every other assignment throws `Error`. | `$sheet->disabled = true;` |

The class has no public abstract methods, but is abstract and cannot be directly instantiated. `CSSStyleSheet` supplies rule-specific behavior.
