<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\WPT\Harness;

use FilesystemIterator;
use JsonException;
use LogicException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Loads and validates WPT conformance manifests and JSON fixture suites.
 */
final class WptFixtureLoader
{
	public static function defaultRootDir(): string
	{
		return dirname(__DIR__);
	}

	/**
	 * @return array<string, array{WptFixtureCase}>
	 */
	public static function loadDomain(string $spec, ?string $rootDir = null): array
	{
		if (!in_array($spec, WptSchema::SPECS, true)) {
			throw new LogicException(sprintf('Unknown WPT spec "%s".', $spec));
		}

		$cases = [];
		$errors = [];
		self::scan($rootDir ?? self::defaultRootDir(), $spec, $cases, $errors);

		if ($errors !== []) {
			throw new LogicException("Invalid WPT fixtures for '{$spec}':\n- " . implode("\n- ", $errors));
		}
		if ($cases === []) {
			throw new LogicException(sprintf('No WPT fixture cases found for spec "%s".', $spec));
		}

		$provided = [];
		foreach ($cases as $case) {
			$provided[$case->id] = [$case];
		}

		return $provided;
	}

	/**
	 * @return list<WptFixtureCase>
	 */
	public static function loadAll(?string $rootDir = null): array
	{
		$cases = [];
		$errors = [];
		self::scan($rootDir ?? self::defaultRootDir(), null, $cases, $errors);

		if ($errors !== []) {
			throw new LogicException("Invalid WPT fixtures:\n- " . implode("\n- ", $errors));
		}

		return $cases;
	}

	public static function audit(?string $rootDir = null): WptAuditReport
	{
		$cases = [];
		$errors = [];
		$meta = self::scan($rootDir ?? self::defaultRootDir(), null, $cases, $errors);

		$countsBySpec = [];
		foreach (WptSchema::SPECS as $spec) {
			$countsBySpec[$spec] = [
				'total' => 0,
				'pass' => 0,
				'xfail' => 0,
				'categories' => array_fill_keys(WptSchema::CATEGORIES, 0),
			];
		}
		$countsByCategory = array_fill_keys(WptSchema::CATEGORIES, 0);
		$passCount = 0;
		$xfailCount = 0;
		$upstreams = [];

		foreach ($cases as $case) {
			$countsBySpec[$case->spec]['total']++;
			if ($case->status === 'pass') {
				$passCount++;
				$countsBySpec[$case->spec]['pass']++;
			} else {
				$xfailCount++;
				$countsBySpec[$case->spec]['xfail']++;
			}
			$countsBySpec[$case->spec]['categories'][$case->category]++;
			$countsByCategory[$case->category]++;
			$upstreams[$case->upstream] = true;
		}

		foreach ($countsBySpec as $spec => $specStats) {
			foreach (WptSchema::CATEGORIES as $category) {
				if ($specStats['categories'][$category] === 0) {
					$errors[] = sprintf('Spec "%s" is missing coverage for category "%s".', $spec, $category);
				}
			}
		}

		$upstreamFiles = array_keys($upstreams);
		sort($upstreamFiles);

		return new WptAuditReport(
			wptCommit: $meta['wptCommit'],
			totalCases: count($cases),
			passCount: $passCount,
			xfailCount: $xfailCount,
			countsBySpec: $countsBySpec,
			countsByCategory: $countsByCategory,
			upstreamFiles: $upstreamFiles,
			errors: $errors,
		);
	}

	/**
	 * @param list<WptFixtureCase> $cases
	 * @param list<string> $errors
	 * @return array{wptCommit: string}
	 */
	private static function scan(string $rootDir, ?string $onlySpec, array &$cases, array &$errors): array
	{
		$manifestPath = $rootDir . DIRECTORY_SEPARATOR . 'manifest.json';
		if (!is_file($manifestPath)) {
			$errors[] = sprintf('Missing WPT manifest at "%s".', $manifestPath);
			return ['wptCommit' => ''];
		}

		try {
			$rawManifest = file_get_contents($manifestPath);
			if ($rawManifest === false) {
				$errors[] = sprintf('Failed reading WPT manifest "%s".', $manifestPath);
				return ['wptCommit' => ''];
			}
			$manifest = json_decode($rawManifest, true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			$errors[] = sprintf('Malformed JSON in manifest.json: %s', $e->getMessage());
			return ['wptCommit' => ''];
		}

		if (!is_array($manifest)) {
			$errors[] = 'manifest.json must decode to a JSON object.';
			return ['wptCommit' => ''];
		}

		if (($manifest['schemaVersion'] ?? null) !== WptSchema::SCHEMA_VERSION) {
			$errors[] = sprintf('manifest.json schemaVersion must equal %d.', WptSchema::SCHEMA_VERSION);
		}

		$wptCommit = $manifest['wptCommit'] ?? null;
		if (!is_string($wptCommit) || preg_match('/^[0-9a-f]{40}$/', $wptCommit) !== 1) {
			$errors[] = 'manifest.json wptCommit must be a 40-character lowercase SHA-1 hex string.';
			$wptCommit = is_string($wptCommit) ? $wptCommit : '';
		}

		if (($manifest['license'] ?? null) !== WptSchema::EXPECTED_LICENSE) {
			$errors[] = sprintf('manifest.json license must be "%s".', WptSchema::EXPECTED_LICENSE);
		}

		$suites = $manifest['suites'] ?? null;
		if (!is_array($suites)) {
			$errors[] = 'manifest.json suites must be an object mapping spec names to fixture lists.';
			return ['wptCommit' => $wptCommit];
		}

		foreach (WptSchema::SPECS as $requiredSpec) {
			if (!array_key_exists($requiredSpec, $suites)) {
				$errors[] = sprintf('manifest.json is missing required spec "%s".', $requiredSpec);
			}
		}

		$seenFixtureRelPaths = [];
		$registeredRealPaths = [];
		$seenCaseIds = [];

		foreach ($suites as $spec => $fixtureList) {
			if (!is_string($spec) || !in_array($spec, WptSchema::SPECS, true)) {
				$errors[] = sprintf('manifest.json declares unsupported spec "%s".', (string) $spec);
				continue;
			}
			if (!is_array($fixtureList) || $fixtureList === []) {
				$errors[] = sprintf('manifest.json suite "%s" must be a non-empty list of fixture paths.', $spec);
				continue;
			}

			foreach ($fixtureList as $relPath) {
				if (!is_string($relPath) || !WptSchema::isValidUpstreamPath($relPath)) {
					$errors[] = sprintf('manifest.json suite "%s" contains invalid fixture path "%s".', $spec, is_string($relPath) ? $relPath : get_debug_type($relPath));
					continue;
				}
				if (isset($seenFixtureRelPaths[$relPath])) {
					$errors[] = sprintf('manifest.json registers duplicate fixture path "%s".', $relPath);
					continue;
				}
				$seenFixtureRelPaths[$relPath] = true;

				$fullPath = $rootDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
				if ($onlySpec !== null && $spec !== $onlySpec) {
					continue;
				}
				if (!is_file($fullPath)) {
					$errors[] = sprintf('Registered fixture "%s" does not exist on disk.', $relPath);
					continue;
				}
				$real = realpath($fullPath);
				if ($real !== false) {
					$registeredRealPaths[$real] = true;
				}

				self::loadFixtureFile($fullPath, $relPath, $spec, $wptCommit, $cases, $seenCaseIds, $errors);
			}
		}

		if ($onlySpec === null) {
			self::detectOrphanFixtures($rootDir . DIRECTORY_SEPARATOR . 'fixtures', $registeredRealPaths, $errors);
		}

		return ['wptCommit' => $wptCommit];
	}

	/**
	 * @param list<WptFixtureCase> $cases
	 * @param array<string, string> $seenCaseIds
	 * @param list<string> $errors
	 */
	private static function loadFixtureFile(
		string $fullPath,
		string $relPath,
		string $expectedSpec,
		string $expectedCommit,
		array &$cases,
		array &$seenCaseIds,
		array &$errors,
	): void {
		$raw = file_get_contents($fullPath);
		if ($raw === false) {
			$errors[] = sprintf('Unable to read fixture "%s".', $relPath);
			return;
		}

		try {
			$doc = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			$errors[] = sprintf('Malformed JSON in "%s": %s', $relPath, $e->getMessage());
			return;
		}

		if (!is_array($doc)) {
			$errors[] = sprintf('Fixture "%s" must decode to a JSON object.', $relPath);
			return;
		}

		if (($doc['schemaVersion'] ?? null) !== WptSchema::SCHEMA_VERSION) {
			$errors[] = sprintf('Fixture "%s" schemaVersion must equal %d.', $relPath, WptSchema::SCHEMA_VERSION);
		}
		if (($doc['spec'] ?? null) !== $expectedSpec) {
			$errors[] = sprintf('Fixture "%s" spec mismatch (expected "%s").', $relPath, $expectedSpec);
		}
		if (($doc['wptCommit'] ?? null) !== $expectedCommit) {
			$errors[] = sprintf('Fixture "%s" wptCommit must match manifest commit "%s".', $relPath, $expectedCommit);
		}

		$upstreamFiles = $doc['upstreamFiles'] ?? null;
		$declaredUpstreams = [];
		if (!is_array($upstreamFiles) || $upstreamFiles === [] || !array_is_list($upstreamFiles)) {
			$errors[] = sprintf('Fixture "%s" upstreamFiles must be a non-empty list.', $relPath);
		} else {
			foreach ($upstreamFiles as $u) {
				if (!is_string($u) || !WptSchema::isValidUpstreamPath($u)) {
					$errors[] = sprintf('Fixture "%s" declares invalid upstreamFiles path "%s".', $relPath, is_string($u) ? $u : get_debug_type($u));
				} else {
					$declaredUpstreams[$u] = true;
				}
			}
		}

		$rawCases = $doc['cases'] ?? null;
		if (!is_array($rawCases) || $rawCases === [] || !array_is_list($rawCases)) {
			$errors[] = sprintf('Fixture "%s" must contain a non-empty cases list.', $relPath);
			return;
		}

		$allowedOps = WptSchema::OPERATIONS_BY_SPEC[$expectedSpec] ?? [];

		foreach ($rawCases as $index => $entry) {
			if (!is_array($entry)) {
				$errors[] = sprintf('Fixture "%s" case #%d must be an object.', $relPath, $index);
				continue;
			}

			$id = $entry['id'] ?? null;
			if (!is_string($id) || $id === '') {
				$errors[] = sprintf('Fixture "%s" case #%d has missing/empty id.', $relPath, $index);
				continue;
			}
			if (isset($seenCaseIds[$id])) {
				$errors[] = sprintf('Duplicate WPT case id "%s" in "%s" (already defined in "%s").', $id, $relPath, $seenCaseIds[$id]);
				continue;
			}
			$seenCaseIds[$id] = $relPath;

			$upstream = $entry['upstream'] ?? null;
			if (!is_string($upstream) || !WptSchema::isValidUpstreamPath($upstream)) {
				$errors[] = sprintf('Case "%s" (%s) has invalid upstream path.', $id, $relPath);
				continue;
			}
			if ($declaredUpstreams !== [] && !isset($declaredUpstreams[$upstream])) {
				$errors[] = sprintf('Case "%s" (%s) references upstream "%s" not listed in upstreamFiles.', $id, $relPath, $upstream);
			}

			$category = $entry['category'] ?? null;
			if (!is_string($category) || !in_array($category, WptSchema::CATEGORIES, true)) {
				$errors[] = sprintf('Case "%s" (%s) has invalid category "%s".', $id, $relPath, is_string($category) ? $category : get_debug_type($category));
				continue;
			}

			$operation = $entry['operation'] ?? null;
			if (!is_string($operation) || !in_array($operation, $allowedOps, true)) {
				$errors[] = sprintf('Case "%s" (%s) has invalid operation "%s" for spec "%s".', $id, $relPath, is_string($operation) ? $operation : get_debug_type($operation), $expectedSpec);
				continue;
			}

			$input = $entry['input'] ?? null;
			$expected = $entry['expected'] ?? null;
			if (!is_array($input) || !is_array($expected)) {
				$errors[] = sprintf('Case "%s" (%s) must provide object input and expected fields.', $id, $relPath);
				continue;
			}

			$status = $entry['status'] ?? null;
			if (!is_string($status) || !in_array($status, WptSchema::STATUSES, true)) {
				$errors[] = sprintf('Case "%s" (%s) has invalid status.', $id, $relPath);
				continue;
			}

			$xfailReason = $entry['xfailReason'] ?? null;
			$divergenceKind = $entry['divergenceKind'] ?? null;
			$specRef = $entry['specRef'] ?? null;
			$tolerance = $entry['tolerance'] ?? null;

			if ($status === 'xfail') {
				if (!is_string($xfailReason) || trim($xfailReason) === '') {
					$errors[] = sprintf('xfail case "%s" (%s) requires non-empty xfailReason.', $id, $relPath);
				}
				if (!is_string($divergenceKind) || !in_array($divergenceKind, WptSchema::DIVERGENCE_KINDS, true)) {
					$errors[] = sprintf('xfail case "%s" (%s) requires valid divergenceKind.', $id, $relPath);
				}
				if (!is_string($specRef) || !str_starts_with($specRef, 'https://')) {
					$errors[] = sprintf('xfail case "%s" (%s) requires an https:// specRef.', $id, $relPath);
				}
			} else {
				if (array_key_exists('xfailReason', $entry) && $xfailReason !== null) {
					$errors[] = sprintf('pass case "%s" (%s) must not define xfailReason.', $id, $relPath);
				}
				if (array_key_exists('divergenceKind', $entry) && $divergenceKind !== null) {
					$errors[] = sprintf('pass case "%s" (%s) must not define divergenceKind.', $id, $relPath);
				}
			}

			$resolvedTolerance = null;
			if ($tolerance !== null) {
				if ((!is_int($tolerance) && !is_float($tolerance)) || !is_finite((float) $tolerance) || (float) $tolerance <= 0.0) {
					$errors[] = sprintf('Case "%s" (%s) tolerance must be a positive finite number.', $id, $relPath);
					continue;
				}
				$resolvedTolerance = (float) $tolerance;
			}

			/** @var array<string, mixed> $typedInput */
			$typedInput = $input;
			/** @var array<string, mixed> $typedExpected */
			$typedExpected = $expected;

			$cases[] = new WptFixtureCase(
				id: $id,
				spec: $expectedSpec,
				upstream: $upstream,
				category: $category,
				operation: $operation,
				input: $typedInput,
				expected: $typedExpected,
				status: $status,
				xfailReason: is_string($xfailReason) ? $xfailReason : null,
				divergenceKind: is_string($divergenceKind) ? $divergenceKind : null,
				specRef: is_string($specRef) ? $specRef : null,
				tolerance: $resolvedTolerance,
				fixtureFile: $relPath,
			);
		}
	}

	/**
	 * @param array<string, true> $registeredRealPaths
	 * @param list<string> $errors
	 */
	private static function detectOrphanFixtures(string $fixturesDir, array $registeredRealPaths, array &$errors): void
	{
		if (!is_dir($fixturesDir)) {
			$errors[] = sprintf('Missing fixtures directory "%s".', $fixturesDir);
			return;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($fixturesDir, FilesystemIterator::SKIP_DOTS),
		);

		foreach ($iterator as $file) {
			if (!$file instanceof SplFileInfo || !$file->isFile()) {
				continue;
			}
			if (strtolower($file->getExtension()) !== 'json') {
				continue;
			}
			$real = $file->getRealPath();
			if ($real === false || !isset($registeredRealPaths[$real])) {
				$errors[] = sprintf('Orphan fixture file not registered in manifest.json: "%s".', $file->getPathname());
			}
		}
	}
}
