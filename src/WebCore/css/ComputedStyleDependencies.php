<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\WebCore\css;

use Jimbo2150\PhpCssTypedOm\Css\CSSPropertyID;

class ComputedStyleDependencies
{
	/** @var array<CSSPropertyID> */
	protected array $properties;
	/** @var array<CSSPropertyID> */
	protected array $rootProperties;

	protected bool $containerDimensions = false;
	protected bool $viewportDimensions = false;
	protected bool $anchors = false;

	public function isComputationallyIndependent(): bool
	{
		return empty($this->properties) && empty($this->rootProperties) &&
		!$this->containerDimensions && !$this->anchors;
	}

	// Checks to see if the provided conversion data is sufficient to resolve the provided dependencies.
	public function canResolveDependenciesWithConversionData(
		CSSToLengthConversionData &$conversionData,
	): bool {
		if (!empty($this->rootProperties) && !$conversionData->rootStyle()) {
			return false;
		}

		if (!empty($this->properties) && !$conversionData->style()) {
			return false;
		}

		if (
			$this->containerDimensions &&
			!$conversionData->elementForContainerUnitResolution()
		) {
			return false;
		}

		if ($this->viewportDimensions && !$conversionData->renderView()) {
			return false;
		}

		return true;
	}
}
