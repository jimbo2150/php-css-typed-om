#!/usr/bin/env php
<?php

declare(strict_types=1);

use Jimbo2150\PhpCssTypedOm\Tests\WPT\Harness\WptFixtureLoader;
use Jimbo2150\PhpCssTypedOm\Tests\WPT\Harness\WptSchema;

$autoloadPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (!is_file($autoloadPath)) {
	fwrite(STDERR, "vendor/autoload.php not found. Run 'composer install' first.\n");
	exit(1);
}
require $autoloadPath;

if (!class_exists(WptFixtureLoader::class)) {
	fwrite(STDERR, "WPT harness classes are unavailable (autoload-dev is required; do not install with --no-dev).\n");
	exit(1);
}

$audit = false;
$json = false;
$verifyUpstream = null;

/** @var list<string> $cliArgs */
$cliArgs = array_values(array_map(static fn (mixed $v): string => (string) $v, (array) ($_SERVER['argv'] ?? [])));

foreach (array_slice($cliArgs, 1) as $arg) {
	if ($arg === '--help' || $arg === '-h') {
		echo <<<'TEXT'
Usage: php tools/wpt-sync.php [--audit] [--verify-upstream=<dir>] [--json]

Options:
  --audit                  Validate manifest.json, JSON schemas, and orphan fixture files (offline).
  --verify-upstream=<dir>  Verify pinned commit SHA and all referenced upstream paths in a local WPT clone.
  --json                   Emit machine-readable JSON output.
  --help, -h               Show this help message.

TEXT;
		exit(0);
	}
	if ($arg === '--audit') {
		$audit = true;
		continue;
	}
	if ($arg === '--json') {
		$json = true;
		continue;
	}
	if (str_starts_with($arg, '--verify-upstream=')) {
		$verifyUpstream = substr($arg, strlen('--verify-upstream='));
		if ($verifyUpstream === '') {
			fwrite(STDERR, "--verify-upstream requires a non-empty directory path.\n");
			exit(1);
		}
		continue;
	}

	fwrite(STDERR, sprintf("Unknown option: %s\nRun with --help for usage.\n", $arg));
	exit(1);
}

if (!$audit && $verifyUpstream === null) {
	$audit = true;
}

$report = WptFixtureLoader::audit();
$errors = $report->errors;
$upstreamCheck = null;

if ($verifyUpstream !== null) {
	$realDir = realpath($verifyUpstream);
	if ($realDir === false || !is_dir($realDir)) {
		$errors[] = sprintf('Upstream directory "%s" does not exist or is not a directory.', $verifyUpstream);
	} else {
		$headCommit = null;
		$proc = proc_open(
			['git', '-C', $realDir, 'rev-parse', 'HEAD'],
			[1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
			$pipes,
		);
		if (!is_resource($proc)) {
			$errors[] = 'Failed to invoke git rev-parse HEAD on upstream directory.';
		} else {
			$stdout = stream_get_contents($pipes[1]);
			$stderr = stream_get_contents($pipes[2]);
			fclose($pipes[1]);
			fclose($pipes[2]);
			$exitCode = proc_close($proc);
			if ($exitCode !== 0 || !is_string($stdout)) {
				$errors[] = sprintf('git rev-parse HEAD failed: %s', trim((string) $stderr));
			} else {
				$headCommit = trim($stdout);
				if ($headCommit !== $report->wptCommit) {
					$errors[] = sprintf(
						'Upstream WPT HEAD (%s) does not match manifest wptCommit (%s).',
						$headCommit,
						$report->wptCommit,
					);
				}
			}
		}

		$missingFiles = [];
		$prefix = $realDir . DIRECTORY_SEPARATOR;
		foreach ($report->upstreamFiles as $relUpstream) {
			if (!WptSchema::isValidUpstreamPath($relUpstream)) {
				$missingFiles[] = $relUpstream;
				continue;
			}
			$candidate = $prefix . str_replace('/', DIRECTORY_SEPARATOR, $relUpstream);
			$realTarget = realpath($candidate);
			if ($realTarget === false || !is_file($realTarget) || !str_starts_with($realTarget, $prefix)) {
				$missingFiles[] = $relUpstream;
			}
		}

		foreach ($missingFiles as $missing) {
			$errors[] = sprintf('Referenced upstream WPT file missing from "%s": %s', $realDir, $missing);
		}

		$upstreamCheck = [
			'directory' => $realDir,
			'headCommit' => $headCommit,
			'checkedFiles' => count($report->upstreamFiles),
			'missingFiles' => $missingFiles,
		];
	}
}

$payload = $report->toArray();
$payload['errors'] = $errors;
if ($upstreamCheck !== null) {
	$payload['upstreamCheck'] = $upstreamCheck;
}

if ($json) {
	echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
} else {
	echo sprintf(
		"WPT Conformance Fixtures Audit (commit %s)\n",
		$report->wptCommit !== '' ? $report->wptCommit : '<unset>',
	);
	echo sprintf(
		"Total: %d | Pass: %d | XFail: %d | Upstream files: %d\n",
		$report->totalCases,
		$report->passCount,
		$report->xfailCount,
		count($report->upstreamFiles),
	);
	foreach ($report->countsBySpec as $spec => $stats) {
		echo sprintf(
			"  %-18s total=%3d pass=%3d xfail=%2d (P:%d S:%d C:%d E:%d)\n",
			$spec,
			$stats['total'],
			$stats['pass'],
			$stats['xfail'],
			$stats['categories']['parsing'],
			$stats['categories']['serialization'],
			$stats['categories']['canonicalization'],
			$stats['categories']['error-recovery'],
		);
	}
	if ($upstreamCheck !== null) {
		echo sprintf(
			"Upstream clone check (%s): HEAD=%s, checked=%d, missing=%d\n",
			$upstreamCheck['directory'],
			$upstreamCheck['headCommit'] ?? '<error>',
			$upstreamCheck['checkedFiles'],
			count($upstreamCheck['missingFiles']),
		);
	}
	if ($errors !== []) {
		fwrite(STDERR, "Audit errors:\n- " . implode("\n- ", $errors) . "\n");
	} else {
		echo "Status: OK\n";
	}
}

exit($errors === [] ? 0 : 1);
