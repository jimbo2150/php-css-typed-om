<?php

$finder = (new PhpCsFixer\Finder())->
	in([
		__DIR__ . '/src',
		__DIR__ . '/tests',
		__DIR__ . '/benchmarks',
	]);

return (new PhpCsFixer\Config())->
	setRules([
		'array_syntax' => ['syntax' => 'short'],
		'declare_strict_types' => true,
		'indentation_type' => true,
		'no_extra_blank_lines' => ['tokens' => ['extra', 'use']],
		'no_leading_import_slash' => true,
		'no_trailing_whitespace' => true,
		'no_unused_imports' => true,
		'no_whitespace_in_blank_line' => true,
		'ordered_imports' => ['sort_algorithm' => 'alpha'],
		'single_blank_line_at_eof' => true,
	])->
	setRiskyAllowed(true)->
	setFinder($finder)->
	setIndent("\t");
