<?php

declare(strict_types=1);

$xdebugModes = function_exists('xdebug_info') ? xdebug_info('mode') : (
	extension_loaded('xdebug') && !in_array((string) ini_get('xdebug.mode'), ['', 'off'], true) ? ['active'] : []
);
if ($xdebugModes !== [] || extension_loaded('pcov')) {
	fwrite(STDERR, "ERROR: Benchmarks must run without active Xdebug or PCOV (use XDEBUG_MODE=off or -d xdebug.mode=off).\n");
	exit(1);
}

require __DIR__ . '/../vendor/autoload.php';

use Jimbo2150\PhpCssTypedOm\CSS;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSMediaRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderer;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderMode;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderOptions;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleDeclaration;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleSheet;
use Jimbo2150\PhpCssTypedOm\Parser\CSSCalcParser;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSColorValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSTransformValue;

$cliArgv = is_array($GLOBALS['argv'] ?? null) ? $GLOBALS['argv'] : [];
$jsonOutput = false;
$iterations = 25;
$warmup = 5;
foreach ($cliArgv as $idx => $arg) {
	if ($idx === 0) {
		continue;
	}
	if ($arg === '--json') {
		$jsonOutput = true;
		continue;
	}
	if (str_starts_with($arg, '--iterations=')) {
		$raw = substr($arg, 13);
		if (!ctype_digit($raw) || (int) $raw < 1) {
			fwrite(STDERR, "ERROR: --iterations must be a positive integer.\n");
			exit(1);
		}
		$iterations = (int) $raw;
		continue;
	}
	if (str_starts_with($arg, '--warmup=')) {
		$raw = substr($arg, 9);
		if (!ctype_digit($raw)) {
			fwrite(STDERR, "ERROR: --warmup must be a non-negative integer.\n");
			exit(1);
		}
		$warmup = (int) $raw;
		continue;
	}
	fwrite(STDERR, "ERROR: Unknown option: $arg\n");
	exit(1);
}

/**
 * @return array<string, string>
 */
function buildFixtures(): array
{
	// 1. small-component (~1.3 KB)
	$small = '';
	for ($i = 0; $i < 8; $i++) {
		$small .= ".btn-$i { display: inline-flex; padding: 8px 16px; width: calc(100% - 16px); color: oklch(62% 0.21 255 / 0.9); --bg: var(--surface, #fff); &:hover { opacity: 0.9; } }\n";
	}

	// 2. medium-app (~35 KB)
	$mediumParts = [
		"@layer reset, components, utilities;\n",
		"@font-face { font-family: \"Inter\"; src: url(\"inter.woff2\") format(\"woff2\"); font-display: swap; }\n",
		"@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }\n",
		"@page :first { size: A4; margin: 2cm; @top-left { content: \"Report\"; } }\n",
	];
	for ($i = 0; $i < 130; $i++) {
		$mediumParts[] = "/* Card $i */ .card-$i, .card-$i > .header { display: flex; position: relative; width: calc(100% - 24px); padding: 12px 16px; color: rgba(34, 51, 68, 0.9); background-color: #f8fafc; border-radius: 6px; transform: translate(10px, 20px) scale(1.05); --accent: #3b82f6; }\n";
	}
	for ($i = 0; $i < 12; $i++) {
		$inner = '';
		for ($j = 0; $j < 5; $j++) {
			$inner .= "  .grid-$i-$j { width: 50%; font-size: 1.125rem; line-height: 1.5; z-index: 10; }\n";
		}
		$mediumParts[] = '@media screen and (min-width: ' . (480 + $i * 40) . "px) {\n$inner}\n";
	}
	$mediumParts[] = "@supports (display: grid) { @container sidebar (width > 300px) { @scope (.panel) to (.footer) { .item { display: grid; gap: 1rem; } } } }\n";
	$medium = implode('', $mediumParts);

	// 3. large-pretty (~330 KB)
	$largePrettyParts = [];
	for ($i = 0; $i < 1250; $i++) {
		$largePrettyParts[] = ".comp-$i, .comp-$i:hover > .inner {\n  display: flex;\n  position: relative;\n  width: calc(100% - 24px);\n  padding: 12px 16px;\n  margin: 0 auto;\n  color: #1e293b;\n  background-color: #ffffff;\n  border: 1px solid #cbd5e1;\n  border-radius: 8px;\n  --token-$i: var(--base-size, 16px);\n}\n";
	}
	$largePretty = implode('', $largePrettyParts);

	// 4. large-minified (~200 KB) with space-free @media(...) and data URI url(...)
	$largeMinParts = [];
	for ($i = 0; $i < 600; $i++) {
		$largeMinParts[] = ".m-$i:is(.active,.open):has(> .icon){display:grid;width:calc(50% - 8px);color:rgb(15 23 42 / 0.95);background-image:url(data:image/svg+xml;utf8,%3Csvg%20xmlns%3D%27http%3A//www.w3.org/2000/svg%27%3E%3C/svg%3E);transform:translate3d(4px,8px,0) rotate(15deg);padding:4px 8px 12px 16px;}";
	}
	for ($i = 0; $i < 60; $i++) {
		$inner = '';
		for ($j = 0; $j < 8; $j++) {
			$inner .= ".mq-$i-$j{margin:0;padding:8px;opacity:0.9;font-weight:600;color:#0f172a;}";
		}
		$largeMinParts[] = '@media(min-width:' . (320 + $i * 15) . "px){{$inner}}";
	}
	$largeMinified = implode('', $largeMinParts);

	// 5. deep-nested (~23 KB, depth 64)
	$deepPrefix = '';
	$deepSuffix = '';
	$filler = '';
	for ($j = 0; $j < 6; $j++) {
		$filler .= ".pad-$j { margin: 4px; padding: 8px; color: #334155; border-color: #94a3b8; }\n";
	}
	for ($d = 0; $d < 64; $d++) {
		$deepPrefix .= "@media screen {\n" . $filler;
		$deepSuffix = "}\n" . $deepSuffix;
	}
	$deepNested = $deepPrefix . ".leaf { color: red; width: 100px; }\n" . $deepSuffix;

	// 6. opaque-comments (~77 KB)
	$opaqueParts = [];
	for ($i = 0; $i < 700; $i++) {
		$opaqueParts[] = "/* block comment $i with metadata */ @custom-unknown-$i foo bar /* inner */ baz { alpha: 1; beta: 2; /* nested */ gamma: \"/* preserved */\"; }\n";
	}
	$opaqueComments = implode('', $opaqueParts);

	return [
		'small-component' => $small,
		'medium-app' => $medium,
		'large-pretty' => $largePretty,
		'large-minified' => $largeMinified,
		'deep-nested' => $deepNested,
		'opaque-comments' => $opaqueComments,
	];
}

/**
 * @return array{median_ms: float, p95_ms: float, min_ms: float, mem_delta_kb: float, post_gc_delta_kb: float, peak_delta_kb: float, ops_per_sec: float, ops_unit: string, bytes_processed: ?int, bytes_basis: ?string, mb_per_sec: ?float}
 */
function measure(
	callable $fn,
	int $warmup,
	int $iterations,
	?int $bytesProcessed = null,
	int $opsPerIter = 1,
	?callable $setup = null,
	string $opsUnit = 'ops',
	?string $bytesBasis = null,
): array {
	for ($i = 0; $i < $warmup; $i++) {
		$state = $setup !== null ? $setup($i) : null;
		$setup !== null ? $fn($state) : $fn();
		unset($state);
	}

	gc_collect_cycles();
	$times = [];
	for ($i = 0; $i < $iterations; $i++) {
		gc_collect_cycles();
		$state = $setup !== null ? $setup($i) : null;
		$t0 = hrtime(true);
		$result = $setup !== null ? $fn($state) : $fn();
		$t1 = hrtime(true);
		unset($result, $state);
		$times[] = ($t1 - $t0) / 1e6;
	}

	gc_collect_cycles();
	$memBaseline = memory_get_usage(false);
	$memState = $setup !== null ? $setup(0) : null;
	trackSuitePeakSlab(true);
	$memBefore = memory_get_usage(false);
	$memSample = $setup !== null ? $fn($memState) : $fn();
	$retainedKb = round((memory_get_usage(false) - $memBefore) / 1024.0, 1);
	$peakDeltaKb = round((memory_get_peak_usage(false) - $memBefore) / 1024.0, 1);
	unset($memSample, $memState);
	gc_collect_cycles();
	$postGcDeltaKb = round((memory_get_usage(false) - $memBaseline) / 1024.0, 1);

	sort($times);
	$count = count($times);
	$mid = intdiv($count, 2);
	$median = ($count % 2 === 1) ? $times[$mid] : ($times[$mid - 1] + $times[$mid]) / 2.0;
	$p95Idx = min($count - 1, (int) ceil(0.95 * $count) - 1);
	$p95 = $times[$p95Idx];
	$min = $times[0];

	$medianSec = max($median / 1000.0, 1e-9);
	$mbPerSec = ($bytesProcessed !== null && $bytesProcessed > 0) ? round(($bytesProcessed / (1024 * 1024)) / $medianSec, 2) : null;

	return [
		'median_ms' => round($median, 3),
		'p95_ms' => round($p95, 3),
		'min_ms' => round($min, 3),
		'mem_delta_kb' => $retainedKb,
		'post_gc_delta_kb' => $postGcDeltaKb,
		'peak_delta_kb' => $peakDeltaKb,
		'ops_per_sec' => round($opsPerIter / $medianSec, 1),
		'ops_unit' => $opsUnit,
		'bytes_processed' => $bytesProcessed,
		'bytes_basis' => $bytesBasis,
		'mb_per_sec' => $mbPerSec,
	];
}

$fixtures = buildFixtures();
foreach ($fixtures as $fixtureName => $fixtureCss) {
	$checkSheet = CSSStyleSheet::parse($fixtureCss);
	if ($checkSheet->getParseErrors() !== []) {
		fwrite(STDERR, "ERROR: Benchmark fixture '$fixtureName' produced parse errors.\n");
		exit(1);
	}
	unset($checkSheet);
}
$opcacheCli = ini_get('opcache.enable_cli');
$jitConfig = ini_get('opcache.jit');
$opcacheStatus = function_exists('opcache_get_status') ? opcache_get_status(false) : false;
$jitEnabled = is_array($opcacheStatus) && (($opcacheStatus['jit']['enabled'] ?? false) === true);
$jitStatus = $jitEnabled ? (($jitConfig === false || $jitConfig === '') ? 'on' : $jitConfig) : 'off';
$results = [
	'meta' => [
		'php_version' => PHP_VERSION,
		'opcache_cli' => ($opcacheCli === false || $opcacheCli === '') ? 'off' : $opcacheCli,
		'jit' => $jitStatus,
		'iterations' => $iterations,
		'warmup' => $warmup,
		'fixture_bytes' => array_map('strlen', $fixtures),
	],
	'benchmarks' => [],
];

// Micro benchmarks
$scannerBlob = $fixtures['medium-app'];
$results['benchmarks']['micro.scanner_split_top_level'] = measure(
	static fn () => CSSSyntaxScanner::splitTopLevel($scannerBlob, ';'),
	$warmup,
	$iterations,
	strlen($scannerBlob),
	1,
	null,
	'splits',
	'input',
);

$assertValInput = '1px solid rgba(10, 20, 30, 0.5)';
$results['benchmarks']['micro.scanner_assert_valid'] = measure(
	static function () use ($assertValInput) {
		for ($i = 0; $i < 1000; $i++) {
			CSSSyntaxScanner::assertValidFragment($assertValInput, 'val', [';', '{', '}']);
		}
		return true;
	},
	$warmup,
	$iterations,
	1000 * strlen($assertValInput),
	1000,
	null,
	'validations',
	'input',
);

$declBlock = 'display: flex; position: relative; width: calc(100% - 24px); padding: 12px 16px; color: #1e293b; background-color: #fff; border-radius: 6px; opacity: 0.95 !important';
$results['benchmarks']['micro.decl_parse_and_render'] = measure(
	static function () use ($declBlock) {
		$last = '';
		for ($i = 0; $i < 500; $i++) {
			$d = CSSStyleDeclaration::parse($declBlock);
			$d->setProperty('margin', '8px');
			$last = $d->getCssText();
		}
		return $last;
	},
	$warmup,
	$iterations,
	500 * strlen($declBlock),
	500,
	null,
	'decls',
	'input',
);

$calcSample = 'calc((100% - 24px) * 2 + 10px / 2)';
$results['benchmarks']['micro.calc_parse'] = measure(
	static function () use ($calcSample) {
		$last = null;
		for ($i = 0; $i < 500; $i++) {
			$last = CSSCalcParser::parse($calcSample);
		}
		return $last;
	},
	$warmup,
	$iterations,
	500 * strlen($calcSample),
	500,
	null,
	'exprs',
	'input',
);

$colorSample = 'oklch(62% 0.21 255 / 0.85)';
$results['benchmarks']['micro.color_parse_and_convert'] = measure(
	static function () use ($colorSample): int {
		$sink = 0;
		for ($i = 0; $i < 250; $i++) {
			$c = CSSColorValue::parse($colorSample);
			$rgb = $c->toRGB();
			$hsl = $c->toHSL();
			$sink ^= spl_object_id($rgb) ^ spl_object_id($hsl);
		}
		return $sink;
	},
	$warmup,
	$iterations,
	250 * strlen($colorSample),
	250,
	null,
	'colors',
	'input',
);

$transformSample = 'translate(10px, 20px) rotate(45deg) scale(1.5, 2)';
$results['benchmarks']['micro.transform_and_matrix'] = measure(
	static function () use ($transformSample) {
		$last = '';
		for ($i = 0; $i < 250; $i++) {
			$t = CSSTransformValue::fromCssText($transformSample);
			$m = $t->toMatrix();
			$last = (string) $m;
		}
		return $last;
	},
	$warmup,
	$iterations,
	250 * strlen($transformSample),
	250,
	null,
	'transforms',
	'input',
);

$supportsSampleBytes = strlen('(display: grid) and (not (color: oklch(50% 0.2 200)))') + strlen('section#main.card[data-x="1"]');
$results['benchmarks']['micro.css_supports_and_escape'] = measure(
	static function () {
		$ok = false;
		$escaped = '';
		for ($i = 0; $i < 300; $i++) {
			$ok = CSS::supports('(display: grid) and (not (color: oklch(50% 0.2 200)))');
			$escaped = CSS::escape('section#main.card[data-x="1"]');
		}
		return $ok && $escaped !== '';
	},
	$warmup,
	$iterations,
	300 * $supportsSampleBytes,
	600,
	null,
	'checks',
	'input',
);

$mixedSampleBytes = strlen('display') + strlen('flex')
	+ strlen('width') + strlen('120px')
	+ strlen('margin') + strlen('10px 20px')
	+ strlen('color') + strlen('#336699')
	+ strlen('font-family') + strlen('Inter, sans-serif');
$results['benchmarks']['micro.style_value_parse_mixed'] = measure(
	static function (): int {
		$sink = 0;
		for ($i = 0; $i < 300; $i++) {
			$sink ^= spl_object_id(CSSStyleValue::parse('display', 'flex'));
			$sink ^= spl_object_id(CSSStyleValue::parse('width', '120px'));
			$sink ^= spl_object_id(CSSStyleValue::parse('margin', '10px 20px'));
			$sink ^= spl_object_id(CSSStyleValue::parse('color', '#336699'));
			$sink ^= spl_object_id(CSSStyleValue::parse('font-family', 'Inter, sans-serif'));
		}
		return $sink;
	},
	$warmup,
	$iterations,
	300 * $mixedSampleBytes,
	1500,
	null,
	'values',
	'input',
);

// Macro Parse benchmarks
foreach ($fixtures as $name => $css) {
	$results['benchmarks']["macro.parse.$name"] = measure(
		static fn () => CSSStyleSheet::parse($css),
		$warmup,
		$iterations,
		strlen($css),
		1,
		null,
		'sheets',
		'input',
	);
}
unset($scannerBlob);

$mutateMediumSheet = static function (CSSStyleSheet $sheet): CSSStyleSheet {
	foreach ($sheet->cssRules as $rule) {
		if ($rule instanceof CSSStyleRule) {
			$style = $rule->getStyle();
			$style->setProperty('opacity', '0.85');
			$style->setProperty('color', '#112233', 'important');
			$style->removeProperty('border-radius');
		} elseif ($rule instanceof CSSMediaRule) {
			$rule->getMedia()->appendMedium('print');
		}
	}
	for ($k = 0; $k < 50; $k++) {
		$sheet->insertRule(".dyn-$k { color: red; }", $sheet->cssRules->count());
	}
	for ($k = 0; $k < 50; $k++) {
		$sheet->deleteRule($sheet->cssRules->count() - 1);
	}
	return $sheet;
};

// Pure Macro Modify CSSOM (sheet parsed in setup outside timing)
$results['benchmarks']['macro.modify_cssom_medium'] = measure(
	$mutateMediumSheet,
	$warmup,
	$iterations,
	null,
	1,
	static fn (): CSSStyleSheet => CSSStyleSheet::parse($fixtures['medium-app']),
	'sheets',
);

// Macro Clone (deep iterative clone on medium-app)
$parsedMediumForClone = CSSStyleSheet::parse($fixtures['medium-app']);
$results['benchmarks']['macro.clone_medium'] = measure(
	static fn (): CSSStyleSheet => clone $parsedMediumForClone,
	$warmup,
	$iterations,
	null,
	1,
	null,
	'clones',
);
unset($parsedMediumForClone);

// Combined Parse + Modify CSSOM (on medium-app)
$results['benchmarks']['macro.parse_and_modify_cssom_medium'] = measure(
	static fn (): CSSStyleSheet => $mutateMediumSheet(CSSStyleSheet::parse($fixtures['medium-app'])),
	$warmup,
	$iterations,
	strlen($fixtures['medium-app']),
	1,
	null,
	'sheets',
	'input',
);

// Macro Modify TypedOM (fresh sheet parsed per iteration in setup)
$results['benchmarks']['macro.typed_om_medium'] = measure(
	static function (CSSStyleSheet $sheet): int {
		$ops = 0;
		$sink = 0;
		foreach ($sheet->cssRules as $rule) {
			if ($rule instanceof CSSStyleRule) {
				$map = $rule->getStyleMap();
				$sink ^= ($map->get('width') !== null ? 1 : 0);
				$sink ^= ($map->get('display') !== null ? 2 : 0);
				$sink ^= ($map->get('color') !== null ? 4 : 0);
				$map->set('padding', '16px');
				$ops++;
			}
		}
		return $ops + ($sink & 0);
	},
	$warmup,
	$iterations,
	null,
	1,
	static fn (): CSSStyleSheet => CSSStyleSheet::parse($fixtures['medium-app']),
	'sheets',
);

// Macro Render benchmarks (on large-pretty & opaque-comments)
$parsedLarge = CSSStyleSheet::parse($fixtures['large-pretty']);
$parsedOpaque = CSSStyleSheet::parse($fixtures['opaque-comments']);
$prettyOpts = new CSSRenderOptions(CSSRenderMode::Pretty);
$miniOpts = new CSSRenderOptions(CSSRenderMode::Minified);
$escapeMiniOpts = new CSSRenderOptions(CSSRenderMode::Minified, null, true, true);
$stripCommentsOpts = new CSSRenderOptions(CSSRenderMode::Minified, null, false);
$callbackOpts = new CSSRenderOptions(
	CSSRenderMode::Minified,
	static fn (CSSRule $r, string $s): string => $r instanceof CSSStyleRule ? '/* r */' . $s : $s,
);

$largeWithStyleClose = str_replace(
	'padding: 12px 16px;',
	'padding: 12px 16px; content: "</style>"; /* </style> */',
	$fixtures['large-pretty'],
) . "\n@custom-opaque { content: \"x\"; /* </style> */ }\n";
$parsedLargeWithStyleClose = CSSStyleSheet::parse($largeWithStyleClose);
if ($parsedLargeWithStyleClose->getParseErrors() !== []) {
	fwrite(STDERR, "ERROR: Benchmark fixture 'large-pretty-style-close' produced parse errors.\n");
	exit(1);
}
$results['meta']['fixture_bytes']['large-pretty-style-close'] = strlen($largeWithStyleClose);
$escapeStyleOutBytes = strlen(CSSRenderer::render($parsedLargeWithStyleClose, $escapeMiniOpts));
$opaqueStripOutBytes = strlen(CSSRenderer::render($parsedOpaque, $stripCommentsOpts));
$prettyOutBytes = strlen(CSSRenderer::render($parsedLarge, $prettyOpts));
$miniOutBytes = strlen(CSSRenderer::render($parsedLarge, $miniOpts));
$cssTextOutBytes = strlen($parsedLarge->getCssText());

$results['benchmarks']['macro.render_pretty_large'] = measure(
	static fn () => CSSRenderer::render($parsedLarge, $prettyOpts),
	$warmup,
	$iterations,
	$prettyOutBytes,
	1,
	null,
	'renders',
	'output',
);
$results['benchmarks']['macro.render_minified_large'] = measure(
	static fn () => CSSRenderer::render($parsedLarge, $miniOpts),
	$warmup,
	$iterations,
	$miniOutBytes,
	1,
	null,
	'renders',
	'output',
);
$results['benchmarks']['macro.render_escape_style_large'] = measure(
	static fn () => CSSRenderer::render($parsedLargeWithStyleClose, $escapeMiniOpts),
	$warmup,
	$iterations,
	$escapeStyleOutBytes,
	1,
	null,
	'renders',
	'output',
);
$results['benchmarks']['macro.get_css_text_large'] = measure(
	static fn () => $parsedLarge->getCssText(),
	$warmup,
	$iterations,
	$cssTextOutBytes,
	1,
	null,
	'renders',
	'output',
);
$results['benchmarks']['macro.render_opaque_strip_comments'] = measure(
	static fn () => CSSRenderer::render($parsedOpaque, $stripCommentsOpts),
	$warmup,
	$iterations,
	$opaqueStripOutBytes,
	1,
	null,
	'renders',
	'output',
);
unset($parsedLarge, $parsedLargeWithStyleClose, $largeWithStyleClose, $parsedOpaque);
$parsedMediumForRender = CSSStyleSheet::parse($fixtures['medium-app']);
$callbackOutBytes = strlen(CSSRenderer::render($parsedMediumForRender, $callbackOpts));
$results['benchmarks']['macro.render_callback_medium'] = measure(
	static fn () => CSSRenderer::render($parsedMediumForRender, $callbackOpts),
	$warmup,
	$iterations,
	$callbackOutBytes,
	1,
	null,
	'renders',
	'output',
);
unset($parsedMediumForRender);

// End-to-End: Parse -> Mutate 5% -> Render Minified
$results['benchmarks']['macro.e2e_parse_mutate_render'] = measure(
	static function () use ($fixtures, $miniOpts) {
		$sheet = CSSStyleSheet::parse($fixtures['medium-app']);
		$i = 0;
		foreach ($sheet->cssRules as $rule) {
			if ($rule instanceof CSSStyleRule && ($i++ % 20) === 0) {
				$rule->getStyle()->setProperty('color', '#000000');
			}
		}
		return CSSRenderer::render($sheet, $miniOpts);
	},
	$warmup,
	$iterations,
	strlen($fixtures['medium-app']),
	1,
	null,
	'runs',
	'input',
);

$results['meta']['peak_slab_mb'] = round(trackSuitePeakSlab() / (1024 * 1024), 2);

if ($jsonOutput) {
	echo json_encode($results, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
	exit(0);
}

echo sprintf("PHP %s | opcache.enable_cli=%s | jit=%s | iters=%d\n", PHP_VERSION, $results['meta']['opcache_cli'], $results['meta']['jit'], $iterations);
echo str_repeat('-', 118) . "\n";
echo sprintf("%-42s %9s %9s %10s %10s %-11s %10s %9s\n", 'Benchmark', 'Median', 'P95', 'MB/s', 'Ops/s', 'Unit', 'PeakΔ', 'PostGC');
echo str_repeat('-', 118) . "\n";
foreach ($results['benchmarks'] as $label => $m) {
	echo sprintf(
		"%-42s %7.3fms %7.3fms %10s %10.1f %-11s %7.1fKB %7.1fKB\n",
		$label,
		$m['median_ms'],
		$m['p95_ms'],
		$m['mb_per_sec'] !== null ? sprintf('%.2f', $m['mb_per_sec']) : '-',
		$m['ops_per_sec'],
		$m['ops_unit'],
		$m['peak_delta_kb'],
		$m['post_gc_delta_kb'],
	);
}
echo str_repeat('-', 118) . "\n";
echo sprintf("Peak Slab: %.2fMB\n", $results['meta']['peak_slab_mb']);

function trackSuitePeakSlab(bool $reset = false): int
{
	static $maxSlab = 0;
	$maxSlab = max($maxSlab, memory_get_peak_usage(true));
	if ($reset && function_exists('memory_reset_peak_usage')) {
		memory_reset_peak_usage();
	}

	return $maxSlab;
}
