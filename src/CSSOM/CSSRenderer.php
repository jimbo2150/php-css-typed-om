<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;

/**
 * Renders CSSOM stylesheets and rules with deterministic formatting.
 *
 * Opaque rules retain their source verbatim because their syntax is not understood.
 */
final class CSSRenderer
{
	private const string INDENT = '  ';

	public static function render(CSSStyleSheet|CSSRule $source, ?CSSRenderOptions $options = null): string
	{
		$options ??= new CSSRenderOptions();
		$renderer = new self();

		if ($source instanceof CSSStyleSheet) {
			$rules = [];
			foreach ($source->getCssRules() as $rule) {
				$rules[] = $renderer->renderRule($rule, $options, 0);
			}

			return implode(CSSRenderMode::Pretty === $options->mode ? "\n" : '', $rules);
		}

		return $renderer->renderRule($source, $options, 0);
	}

	private function renderRule(CSSRule $rule, CSSRenderOptions $options, int $depth): string
	{
		$serialized = match (true) {
			$rule instanceof CSSOpaqueRule => $options->keepComments ?
				$rule->getCssText() :
				CSSSyntaxScanner::removeComments($rule->getCssText()),
			$rule instanceof CSSKeyframesRule => $this->renderGroupingRule($rule, $options, $depth),
			$rule instanceof CSSPageRule => $this->renderPageRule($rule, $options, $depth),
			$rule instanceof CSSStyleRule => $this->renderStyleRule($rule, $options, $depth),
			$rule instanceof CSSFontFeatureValuesRule => $this->renderFontFeatureValuesRule($rule, $options, $depth),
			$rule instanceof CSSGroupingRule => $this->renderGroupingRule($rule, $options, $depth),
			$rule instanceof CSSAtRule => $this->renderAtRule($rule, $options, $depth),
			$rule instanceof CSSNestedDeclarations => $this->renderDeclarations($rule->getStyle(), $options, $depth),
			$rule instanceof CSSFunctionDeclarations => $this->renderDeclarations($rule->getStyle(), $options, $depth),
			default => $this->indent($depth, $options).$rule->getCssText(),
		};

		if (null !== $options->ruleCallback) {
			$serialized = ($options->ruleCallback)($rule, $serialized);
		}

		return $serialized;
	}

	private function renderStyleRule(CSSStyleRule $rule, CSSRenderOptions $options, int $depth): string
	{
		$body = [];
		$declarations = $this->renderDeclarations($rule->getStyle(), $options, $depth + 1);
		if ('' !== $declarations) {
			$body[] = $declarations;
		}

		foreach ($rule->getCssRules() as $nestedRule) {
			$body[] = $this->renderRule($nestedRule, $options, $depth + 1);
		}

		return $this->renderBlock($this->indent($depth, $options).$rule->getSelectorText(), $body, $depth, $options);
	}

	private function renderPageRule(CSSPageRule $rule, CSSRenderOptions $options, int $depth): string
	{
		$body = [];
		$declarations = $this->renderDeclarations($rule->getStyle(), $options, $depth + 1);
		if ('' !== $declarations) {
			$body[] = $declarations;
		}

		foreach ($rule->getCssRules() as $nestedRule) {
			$body[] = $this->renderRule($nestedRule, $options, $depth + 1);
		}

		$prelude = $rule->getSelectorText();
		$header = $this->indent($depth, $options).'@page'.('' === $prelude ? '' : ' '.$prelude);

		return $this->renderBlock($header, $body, $depth, $options);
	}

	private function renderGroupingRule(CSSGroupingRule $rule, CSSRenderOptions $options, int $depth): string
	{
		$body = [];
		foreach ($rule->getCssRules() as $nestedRule) {
			$body[] = $this->renderRule($nestedRule, $options, $depth + 1);
		}

		$prelude = $rule->getPrelude();
		$header = $this->indent($depth, $options).'@'.$rule->getName().('' === $prelude ? '' : ' '.$prelude);

		return $this->renderBlock($header, $body, $depth, $options);
	}

	private function renderAtRule(CSSAtRule $rule, CSSRenderOptions $options, int $depth): string
	{
		$prelude = $rule->getPrelude();
		$header = $this->indent($depth, $options).'@'.$rule->getName().('' === $prelude ? '' : ' '.$prelude);

		if (!$rule->hasBlock()) {
			return $header.';';
		}

		$body = [];
		if (null !== $rule->getStyle()) {
			$declarations = $this->renderDeclarations($rule->getStyle(), $options, $depth + 1);
			if ('' !== $declarations) {
				$body[] = $declarations;
			}
		}

		return $this->renderBlock($header, $body, $depth, $options);
	}

	private function renderFontFeatureValuesRule(CSSFontFeatureValuesRule $rule, CSSRenderOptions $options, int $depth): string
	{
		$body = [];
		foreach ($rule->getFeatureMaps() as $category => $map) {
			if (0 === count($map)) {
				continue;
			}

			$declarations = [];
			foreach ($map->entries() as [$name, $values]) {
				$declaration = $name.':'.(CSSRenderMode::Pretty === $options->mode ? ' ' : '').implode(' ', $values).';';
				$declarations[] = $this->indent($depth + 2, $options).$declaration;
			}
			$body[] = $this->renderBlock(
				$this->indent($depth + 1, $options).'@'.$category,
				$declarations,
				$depth + 1,
				$options,
			);
		}

		foreach ($rule->getCssRules() as $nestedRule) {
			$body[] = $this->renderRule($nestedRule, $options, $depth + 1);
		}

		$header = $this->indent($depth, $options).'@font-feature-values '.$rule->getFontFamily();

		return $this->renderBlock($header, $body, $depth, $options);
	}

	private function renderDeclarations(CSSStyleDeclaration $style, CSSRenderOptions $options, int $depth): string
	{
		$declarations = [];
		foreach ($style as $property => $declaration) {
			$priority = '' === $declaration['priority'] ? '' : (CSSRenderMode::Pretty === $options->mode ? ' !important' : '!important');
			$separator = CSSRenderMode::Pretty === $options->mode ? ': ' : ':';
			$serialized = $property.$separator.$declaration['value'].$priority.';';
			$declarations[] = CSSRenderMode::Pretty === $options->mode ? $this->indent($depth, $options).$serialized : $serialized;
		}

		return implode(CSSRenderMode::Pretty === $options->mode ? "\n" : '', $declarations);
	}

	/** @param list<string> $body */
	private function renderBlock(string $header, array $body, int $depth, CSSRenderOptions $options): string
	{
		if (CSSRenderMode::Minified === $options->mode) {
			$header = trim($header);
			$header = str_replace(["\r\n", "\r", "\n"], '', $header);

			return $header.'{'.implode('', $body).'}';
		}

		$indent = $this->indent($depth, $options);

		return $header." {\n".implode("\n", $body)."\n".$indent.'}';
	}

	private function indent(int $depth, CSSRenderOptions $options): string
	{
		return CSSRenderMode::Pretty === $options->mode ? str_repeat(self::INDENT, $depth) : '';
	}
}
