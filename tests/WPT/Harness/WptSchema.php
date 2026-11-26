<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\WPT\Harness;

/**
 * Closed schema allowlists and path invariants for WPT conformance fixtures.
 */
final class WptSchema
{
	public const int SCHEMA_VERSION = 1;

	public const string EXPECTED_LICENSE = 'BSD-3-Clause';

	/** @var list<string> */
	public const array SPECS = [
		'css/css-typed-om',
		'css/cssom',
		'css/css-syntax',
		'css/css-values',
		'css/css-color',
		'css/geometry',
	];

	/** @var list<string> */
	public const array CATEGORIES = [
		'parsing',
		'serialization',
		'canonicalization',
		'error-recovery',
	];

	/** @var list<string> */
	public const array STATUSES = [
		'pass',
		'xfail',
	];

	/** @var list<string> */
	public const array DIVERGENCE_KINDS = [
		'security-invariant',
		'out-of-scope',
		'api-tradeoff',
		'spec-gap',
	];

	/** @var array<string, list<string>> */
	public const array OPERATIONS_BY_SPEC = [
		'css/css-typed-om' => [
			'typed-om.stylevalue.parse',
			'typed-om.stylevalue.parseAll',
			'typed-om.numeric.parse',
			'typed-om.numeric.arithmetic',
			'typed-om.numeric.to',
			'typed-om.numeric.toSum',
			'typed-om.numeric.type',
			'typed-om.numeric.equals',
			'typed-om.transform.parse',
			'typed-om.transform.toMatrix',
			'typed-om.position.parse',
			'typed-om.property-map.ops',
			'typed-om.unit-factory',
		],
		'css/cssom' => [
			'css.escape',
			'css.supports',
			'cssom.stylesheet.parse',
			'cssom.stylesheet.insertRule',
			'cssom.stylesheet.deleteRule',
			'cssom.stylesheet.replaceSync',
			'cssom.declaration.setProperty',
			'cssom.declaration.cssText',
			'cssom.mediaList',
		],
		'css/css-syntax' => [
			'syntax.stylesheet.recover',
			'syntax.declaration.recover',
			'syntax.scanner.split',
			'syntax.ident.escape',
			'syntax.eof',
			'syntax.preprocess',
			'syntax.tokenize',
		],
		'css/css-values' => [
			'values.calc.parse',
			'values.calc.simplify',
			'values.calc.serialize',
			'values.calc.invalid',
			'values.math.eval',
			'values.clamp-range',
		],
		'css/css-color' => [
			'color.parse.valid',
			'color.parse.invalid',
			'color.serialize',
			'color.convert',
			'color.stylevalue.parse',
		],
		'css/geometry' => [
			'geometry.matrix.construct',
			'geometry.matrix.fromMatrix',
			'geometry.matrix.fromFloatArray',
			'geometry.matrix.ops',
			'geometry.matrix.serialize',
			'geometry.matrix.transformPoint',
			'geometry.matrix.invalid',
		],
	];

	public static function isValidUpstreamPath(string $path): bool
	{
		if ($path === '' || str_contains($path, "\0") || str_contains($path, '\\') || str_contains($path, '://')) {
			return false;
		}
		if (str_starts_with($path, '/') || str_ends_with($path, '/')) {
			return false;
		}
		foreach (explode('/', $path) as $segment) {
			if ($segment === '' || $segment === '.' || $segment === '..') {
				return false;
			}
		}

		return true;
	}
}
