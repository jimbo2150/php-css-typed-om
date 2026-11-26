<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM\Shorthand;

use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\Grammar\CSSValueComponentParser;
use Jimbo2150\PhpCssTypedOm\Parser\CSSTokenType;

/**
 * Expands and canonically serializes `background`, `background-position`, and `mask` shorthands.
 *
 * @phpstan-import-type ParsedComponent from CSSValueComponentParser
 * @internal
 */
final class BackgroundAndMaskShorthandCodec
{
	private const array ATTACHMENT_KEYWORDS = [
		'scroll' => true,
		'fixed' => true,
		'local' => true,
	];

	private const array REPEAT_KEYWORDS = [
		'repeat-x' => true,
		'repeat-y' => true,
		'repeat' => true,
		'space' => true,
		'round' => true,
		'no-repeat' => true,
	];

	private const array VISUAL_BOX_KEYWORDS = [
		'border-box' => true,
		'padding-box' => true,
		'content-box' => true,
	];

	private const array BG_CLIP_ONLY_KEYWORDS = [
		'text' => true,
		'border-area' => true,
		'no-clip' => true,
	];

	private const array GEOMETRY_BOX_KEYWORDS = [
		'border-box' => true,
		'padding-box' => true,
		'content-box' => true,
		'margin-box' => true,
		'fill-box' => true,
		'stroke-box' => true,
		'view-box' => true,
	];

	private const array MASK_COMPOSITE_KEYWORDS = [
		'add' => true,
		'subtract' => true,
		'intersect' => true,
		'exclude' => true,
	];

	private const array MASK_MODE_KEYWORDS = [
		'alpha' => true,
		'luminance' => true,
		'match-source' => true,
	];

	private const array POS_HORIZ_KEYWORDS = [
		'left' => true,
		'right' => true,
		'x-start' => true,
		'x-end' => true,
	];

	private const array POS_VERT_KEYWORDS = [
		'top' => true,
		'bottom' => true,
		'y-start' => true,
		'y-end' => true,
	];

	/**
	 * @param list<ParsedComponent> $components
	 * @return ?array<string, string>
	 */
	public static function expand(
		string $shorthand,
		string $source,
		bool $hasComments,
		array $components,
		CSSParserOptions $options,
	): ?array {
		return match ($shorthand) {
			'background-position' => self::expandBackgroundPosition($source, $hasComments, $components),
			'background' => self::expandBackground($source, $hasComments, $components, $options),
			'mask' => self::expandMask($source, $hasComments, $components, $options),
			default => null,
		};
	}

	/**
	 * @param array<string, string> $values
	 */
	public static function serialize(string $shorthand, array $values, CSSParserOptions $options): string
	{
		return match ($shorthand) {
			'background-position' => self::serializeBackgroundPosition($values, $options),
			'background' => self::serializeBackground($values, $options),
			'mask' => self::serializeMask($values, $options),
			default => '',
		};
	}

	/**
	 * @param list<ParsedComponent> $components
	 * @return ?array<string, string>
	 */
	private static function expandBackgroundPosition(string $source, bool $hasComments, array $components): ?array
	{
		$layers = ShorthandCodecHelper::splitByDelimiter($components, $source, CSSTokenType::Comma);
		if ($layers === null) {
			return null;
		}
		$xs = [];
		$ys = [];
		foreach ($layers as $layer) {
			$pair = self::splitBgPositionToXY($layer, $source, $hasComments);
			if ($pair === null) {
				return null;
			}
			$xs[] = $pair[0];
			$ys[] = $pair[1];
		}

		return [
			'background-position-x' => implode(', ', $xs),
			'background-position-y' => implode(', ', $ys),
		];
	}

	/**
	 * @param array<string, string> $values
	 */
	private static function serializeBackgroundPosition(array $values, CSSParserOptions $options): string
	{
		$parsedX = ShorthandCodecHelper::parseValue($values['background-position-x'], $options);
		$parsedY = ShorthandCodecHelper::parseValue($values['background-position-y'], $options);
		if ($parsedX === null || $parsedY === null) {
			return '';
		}
		$layersX = ShorthandCodecHelper::splitByDelimiter($parsedX['components'], $parsedX['source'], CSSTokenType::Comma);
		$layersY = ShorthandCodecHelper::splitByDelimiter($parsedY['components'], $parsedY['source'], CSSTokenType::Comma);
		if ($layersX === null || $layersY === null || count($layersX) !== count($layersY)) {
			return '';
		}

		$out = [];
		$count = count($layersX);
		for ($i = 0; $i < $count; $i++) {
			$pos = self::combineBgPositionFromXY(
				$layersX[$i],
				$parsedX['source'],
				$parsedX['hasComments'],
				$layersY[$i],
				$parsedY['source'],
				$parsedY['hasComments'],
				$options,
			);
			if ($pos === null) {
				return '';
			}
			$out[] = $pos;
		}

		return implode(', ', $out);
	}

	/**
	 * @param list<ParsedComponent> $components
	 * @return ?array{string, string}
	 */
	private static function splitBgPositionToXY(array $components, string $source, bool $hasComments): ?array
	{
		$count = count($components);
		if ($count < 1 || $count > 4) {
			return null;
		}

		if ($count === 1) {
			$t = ShorthandCodecHelper::componentText($source, $components[0], $hasComments);
			$lower = ShorthandCodecHelper::identLower($components[0]);
			if ($lower !== null && isset(self::POS_VERT_KEYWORDS[$lower])) {
				return ['center', $t];
			}

			return [$t, 'center'];
		}

		if ($count === 2) {
			$l0 = ShorthandCodecHelper::identLower($components[0]);
			$l1 = ShorthandCodecHelper::identLower($components[1]);
			$t0 = ShorthandCodecHelper::componentText($source, $components[0], $hasComments);
			$t1 = ShorthandCodecHelper::componentText($source, $components[1], $hasComments);

			$isVert0 = $l0 !== null && isset(self::POS_VERT_KEYWORDS[$l0]);
			$isHoriz1 = $l1 !== null && (isset(self::POS_HORIZ_KEYWORDS[$l1]) || $l1 === 'center');
			if ($isVert0 && $isHoriz1) {
				return [$t1, $t0];
			}

			return [$t0, $t1];
		}

		// 3 or 4 components: edge-keyword offset pairs
		$firstLower = ShorthandCodecHelper::identLower($components[0]);
		$isVertFirst = $firstLower !== null && isset(self::POS_VERT_KEYWORDS[$firstLower]);

		if ($count === 4) {
			$p0 = ShorthandCodecHelper::componentText($source, $components[0], $hasComments)
				. ' ' . ShorthandCodecHelper::componentText($source, $components[1], $hasComments);
			$p1 = ShorthandCodecHelper::componentText($source, $components[2], $hasComments)
				. ' ' . ShorthandCodecHelper::componentText($source, $components[3], $hasComments);

			return $isVertFirst ? [$p1, $p0] : [$p0, $p1];
		}

		// 3 components: either [horiz len vert], [horiz vert len], [vert len horiz], [vert horiz len]
		$secondLower = ShorthandCodecHelper::identLower($components[1]);
		$isSecondKw = $secondLower !== null && (isset(self::POS_HORIZ_KEYWORDS[$secondLower]) || isset(self::POS_VERT_KEYWORDS[$secondLower]) || $secondLower === 'center');
		if ($isSecondKw) {
			$p0 = ShorthandCodecHelper::componentText($source, $components[0], $hasComments);
			$p1 = ShorthandCodecHelper::componentText($source, $components[1], $hasComments)
				. ' ' . ShorthandCodecHelper::componentText($source, $components[2], $hasComments);

			return ($isVertFirst || (isset(self::POS_HORIZ_KEYWORDS[$secondLower]))) ? [$p1, $p0] : [$p0, $p1];
		}

		$p0 = ShorthandCodecHelper::componentText($source, $components[0], $hasComments)
			. ' ' . ShorthandCodecHelper::componentText($source, $components[1], $hasComments);
		$p1 = ShorthandCodecHelper::componentText($source, $components[2], $hasComments);

		return $isVertFirst ? [$p1, $p0] : [$p0, $p1];
	}

	/**
	 * @param list<ParsedComponent> $xComps
	 * @param list<ParsedComponent> $yComps
	 */
	private static function combineBgPositionFromXY(
		array $xComps,
		string $xSource,
		bool $xComments,
		array $yComps,
		string $ySource,
		bool $yComments,
		CSSParserOptions $options,
	): ?string {
		$xStr = ShorthandCodecHelper::joinComponents($xSource, $xComps, $xComments);
		$yStr = ShorthandCodecHelper::joinComponents($ySource, $yComps, $yComments);

		if (count($xComps) === 1 && count($yComps) === 1) {
			$xLower = ShorthandCodecHelper::identLower($xComps[0]);
			$yLower = ShorthandCodecHelper::identLower($yComps[0]);
			if ($yLower === 'center') {
				return $xStr;
			}
			if ($xLower === 'center' && $yLower !== null && isset(self::POS_VERT_KEYWORDS[$yLower])) {
				return $yStr;
			}

			return $xStr . ' ' . $yStr;
		}

		$candidate = $xStr . ' ' . $yStr;
		$parsed = ShorthandCodecHelper::parseValue($candidate, $options);
		if ($parsed === null || !ShorthandCodecHelper::matchesNonTerminal('bg-position', $parsed['source'], $parsed['hasComments'], $parsed['components'], $options)) {
			return null;
		}

		return $candidate;
	}

	/**
	 * @param list<ParsedComponent> $components
	 * @return ?array<string, string>
	 */
	private static function expandBackground(
		string $source,
		bool $hasComments,
		array $components,
		CSSParserOptions $options,
	): ?array {
		$layers = ShorthandCodecHelper::splitByDelimiter($components, $source, CSSTokenType::Comma);
		if ($layers === null) {
			return null;
		}

		$images = [];
		$posXs = [];
		$posYs = [];
		$sizes = [];
		$repeats = [];
		$attachments = [];
		$origins = [];
		$clips = [];
		$color = 'transparent';

		$layerCount = count($layers);
		for ($idx = 0; $idx < $layerCount; $idx++) {
			$isFinal = $idx === $layerCount - 1;
			$parsedLayer = self::parseBackgroundLayer($layers[$idx], $source, $hasComments, $isFinal, $options);
			if ($parsedLayer === null) {
				return null;
			}
			$images[] = $parsedLayer['image'];
			$posXs[] = $parsedLayer['posX'];
			$posYs[] = $parsedLayer['posY'];
			$sizes[] = $parsedLayer['size'];
			$repeats[] = $parsedLayer['repeat'];
			$attachments[] = $parsedLayer['attachment'];
			$origins[] = $parsedLayer['origin'];
			$clips[] = $parsedLayer['clip'];
			if ($isFinal) {
				$color = $parsedLayer['color'];
			}
		}

		return [
			'background-image' => implode(', ', $images),
			'background-position-x' => implode(', ', $posXs),
			'background-position-y' => implode(', ', $posYs),
			'background-size' => implode(', ', $sizes),
			'background-repeat' => implode(', ', $repeats),
			'background-attachment' => implode(', ', $attachments),
			'background-origin' => implode(', ', $origins),
			'background-clip' => implode(', ', $clips),
			'background-color' => $color,
		];
	}

	/**
	 * @param list<ParsedComponent> $layer
	 * @return ?array{image: string, posX: string, posY: string, size: string, repeat: string, attachment: string, origin: string, clip: string, color: string}
	 */
	private static function parseBackgroundLayer(
		array $layer,
		string $source,
		bool $hasComments,
		bool $allowColor,
		CSSParserOptions $options,
	): ?array {
		$slashSplit = self::extractPositionSlashSize($layer, $source, $hasComments, 'bg-position', true, $options);
		if ($slashSplit === false) {
			return null;
		}
		[$posX, $posY, $size, $remaining] = $slashSplit;
		$hasPos = $posX !== null;

		$image = null;
		$repeat = null;
		$attachment = null;
		$boxes = [];
		$color = null;

		$rCount = count($remaining);
		$i = 0;
		while ($i < $rCount) {
			$comp = $remaining[$i];
			$lower = ShorthandCodecHelper::identLower($comp);

			if ($image === null && ($lower === 'none' || ShorthandCodecHelper::matchesNonTerminal('image', $source, $hasComments, [$comp], $options))) {
				$image = ShorthandCodecHelper::componentText($source, $comp, $hasComments);
				$i++;
				continue;
			}
			if ($attachment === null && $lower !== null && isset(self::ATTACHMENT_KEYWORDS[$lower])) {
				$attachment = $lower;
				$i++;
				continue;
			}
			if ($repeat === null && $lower !== null && isset(self::REPEAT_KEYWORDS[$lower])) {
				[$repeat, $i] = self::consumeRepeatTokens($remaining, $i, $rCount, $lower);
				continue;
			}
			if ($lower !== null && (isset(self::VISUAL_BOX_KEYWORDS[$lower]) || isset(self::BG_CLIP_ONLY_KEYWORDS[$lower]))) {
				if (count($boxes) >= 2) {
					return null;
				}
				$boxes[] = $lower;
				$i++;
				continue;
			}
			if ($allowColor && $color === null && ShorthandCodecHelper::matchesColor($source, $hasComments, $comp, $options)) {
				$color = ShorthandCodecHelper::componentText($source, $comp, $hasComments);
				$i++;
				continue;
			}
			if (!$hasPos) {
				$matchedPos = false;
				for ($len = min(4, $rCount - $i); $len >= 1; $len--) {
					$cand = array_slice($remaining, $i, $len);
					if (ShorthandCodecHelper::matchesNonTerminal('bg-position', $source, $hasComments, $cand, $options)) {
						$xy = self::splitBgPositionToXY($cand, $source, $hasComments);
						if ($xy !== null) {
							[$posX, $posY] = $xy;
							$hasPos = true;
							$i += $len;
							$matchedPos = true;
							break;
						}
					}
				}
				if ($matchedPos) {
					continue;
				}
			}

			return null;
		}

		$resolvedBoxes = self::resolveOriginAndClipBoxes($boxes, self::VISUAL_BOX_KEYWORDS, 'padding-box', 'border-box');
		if ($resolvedBoxes === null) {
			return null;
		}
		[$origin, $clip] = $resolvedBoxes;

		return [
			'image' => $image ?? 'none',
			'posX' => $posX ?? '0%',
			'posY' => $posY ?? '0%',
			'size' => $size ?? 'auto',
			'repeat' => $repeat ?? 'repeat',
			'attachment' => $attachment ?? 'scroll',
			'origin' => $origin,
			'clip' => $clip,
			'color' => $color ?? 'transparent',
		];
	}

	/**
	 * @param array<string, string> $values
	 */
	private static function serializeBackground(array $values, CSSParserOptions $options): string
	{
		$hasPos = $values['background-position-x'] !== '0%' || $values['background-position-y'] !== '0%';
		$split = ShorthandCodecHelper::splitCommaLayers([
			'image' => $values['background-image'],
			'posX' => $values['background-position-x'],
			'posY' => $values['background-position-y'],
			'size' => $values['background-size'],
			'repeat' => $values['background-repeat'],
			'attachment' => $values['background-attachment'],
			'origin' => $values['background-origin'],
			'clip' => $values['background-clip'],
		], $options, $hasPos);
		if ($split === null) {
			return '';
		}

		$layerCount = $split['count'];
		$layersOut = [];
		for ($i = 0; $i < $layerCount; $i++) {
			$image = $split['text']['image'][$i];
			$posXStr = $split['text']['posX'][$i];
			$posYStr = $split['text']['posY'][$i];
			$size = $split['text']['size'][$i];
			$repeat = $split['text']['repeat'][$i];
			$attachment = $split['text']['attachment'][$i];
			$origin = $split['text']['origin'][$i];
			$clip = $split['text']['clip'][$i];
			$color = ($i === $layerCount - 1) ? $values['background-color'] : 'transparent';

			$parts = [];
			if ($image !== 'none') {
				$parts[] = $image;
			}
			$hasNonInitialPos = $posXStr !== '0%' || $posYStr !== '0%';
			if ($size !== 'auto') {
				$posStr = $hasNonInitialPos
					? self::combineBgPositionFromXY(
						$split['comps']['posX'][$i],
						$split['parsed']['posX']['source'],
						$split['parsed']['posX']['hasComments'],
						$split['comps']['posY'][$i],
						$split['parsed']['posY']['source'],
						$split['parsed']['posY']['hasComments'],
						$options,
					)
					: '0% 0%';
				if ($posStr === null) {
					return '';
				}
				$parts[] = $posStr . ' / ' . $size;
			} elseif ($hasNonInitialPos) {
				$posStr = self::combineBgPositionFromXY(
					$split['comps']['posX'][$i],
					$split['parsed']['posX']['source'],
					$split['parsed']['posX']['hasComments'],
					$split['comps']['posY'][$i],
					$split['parsed']['posY']['source'],
					$split['parsed']['posY']['hasComments'],
					$options,
				);
				if ($posStr === null) {
					return '';
				}
				$parts[] = $posStr;
			}
			if ($repeat !== 'repeat') {
				$parts[] = $repeat;
			}
			if ($attachment !== 'scroll') {
				$parts[] = $attachment;
			}
			if ($origin !== 'padding-box' || $clip !== 'border-box') {
				$parts[] = $origin === $clip ? $origin : $origin . ' ' . $clip;
			}
			if ($i === $layerCount - 1 && $color !== 'transparent') {
				$parts[] = $color;
			}

			$layersOut[] = $parts === [] ? 'none' : implode(' ', $parts);
		}

		return implode(', ', $layersOut);
	}

	/**
	 * @param list<ParsedComponent> $components
	 * @return ?array<string, string>
	 */
	private static function expandMask(
		string $source,
		bool $hasComments,
		array $components,
		CSSParserOptions $options,
	): ?array {
		$layers = ShorthandCodecHelper::splitByDelimiter($components, $source, CSSTokenType::Comma);
		if ($layers === null) {
			return null;
		}

		$images = [];
		$positions = [];
		$sizes = [];
		$repeats = [];
		$origins = [];
		$clips = [];
		$composites = [];
		$modes = [];

		foreach ($layers as $layer) {
			$parsedLayer = self::parseMaskLayer($layer, $source, $hasComments, $options);
			if ($parsedLayer === null) {
				return null;
			}
			$images[] = $parsedLayer['image'];
			$positions[] = $parsedLayer['position'];
			$sizes[] = $parsedLayer['size'];
			$repeats[] = $parsedLayer['repeat'];
			$origins[] = $parsedLayer['origin'];
			$clips[] = $parsedLayer['clip'];
			$composites[] = $parsedLayer['composite'];
			$modes[] = $parsedLayer['mode'];
		}

		return [
			'mask-image' => implode(', ', $images),
			'mask-position' => implode(', ', $positions),
			'mask-size' => implode(', ', $sizes),
			'mask-repeat' => implode(', ', $repeats),
			'mask-origin' => implode(', ', $origins),
			'mask-clip' => implode(', ', $clips),
			'mask-composite' => implode(', ', $composites),
			'mask-mode' => implode(', ', $modes),
		];
	}

	/**
	 * @param list<ParsedComponent> $layer
	 * @return ?array{image: string, position: string, size: string, repeat: string, origin: string, clip: string, composite: string, mode: string}
	 */
	private static function parseMaskLayer(
		array $layer,
		string $source,
		bool $hasComments,
		CSSParserOptions $options,
	): ?array {
		$slashSplit = self::extractPositionSlashSize($layer, $source, $hasComments, 'position', false, $options);
		if ($slashSplit === false) {
			return null;
		}
		[$position, , $size, $remaining] = $slashSplit;

		$image = null;
		$repeat = null;
		$boxes = [];
		$composite = null;
		$mode = null;

		$rCount = count($remaining);
		$i = 0;
		while ($i < $rCount) {
			$comp = $remaining[$i];
			$lower = ShorthandCodecHelper::identLower($comp);

			if ($image === null && ($lower === 'none' || ShorthandCodecHelper::matchesNonTerminal('mask-reference', $source, $hasComments, [$comp], $options))) {
				$image = ShorthandCodecHelper::componentText($source, $comp, $hasComments);
				$i++;
				continue;
			}
			if ($composite === null && $lower !== null && isset(self::MASK_COMPOSITE_KEYWORDS[$lower])) {
				$composite = $lower;
				$i++;
				continue;
			}
			if ($mode === null && $lower !== null && isset(self::MASK_MODE_KEYWORDS[$lower])) {
				$mode = $lower;
				$i++;
				continue;
			}
			if ($repeat === null && $lower !== null && isset(self::REPEAT_KEYWORDS[$lower])) {
				[$repeat, $i] = self::consumeRepeatTokens($remaining, $i, $rCount, $lower);
				continue;
			}
			if ($lower !== null && (isset(self::GEOMETRY_BOX_KEYWORDS[$lower]) || $lower === 'no-clip')) {
				if (count($boxes) >= 2) {
					return null;
				}
				$boxes[] = $lower;
				$i++;
				continue;
			}
			if ($position === null) {
				$matchedPos = false;
				for ($len = min(4, $rCount - $i); $len >= 1; $len--) {
					$cand = array_slice($remaining, $i, $len);
					if (ShorthandCodecHelper::matchesNonTerminal('position', $source, $hasComments, $cand, $options)) {
						$position = ShorthandCodecHelper::joinComponents($source, $cand, $hasComments);
						$i += $len;
						$matchedPos = true;
						break;
					}
				}
				if ($matchedPos) {
					continue;
				}
			}

			return null;
		}

		$resolvedBoxes = self::resolveOriginAndClipBoxes($boxes, self::GEOMETRY_BOX_KEYWORDS, 'border-box', 'border-box');
		if ($resolvedBoxes === null) {
			return null;
		}
		[$origin, $clip] = $resolvedBoxes;

		return [
			'image' => $image ?? 'none',
			'position' => $position ?? '0% 0%',
			'size' => $size ?? 'auto',
			'repeat' => $repeat ?? 'repeat',
			'origin' => $origin,
			'clip' => $clip,
			'composite' => $composite ?? 'add',
			'mode' => $mode ?? 'match-source',
		];
	}

	/**
	 * @param list<ParsedComponent> $layer
	 * @return false|array{0: ?string, 1: ?string, 2: ?string, 3: list<ParsedComponent>}
	 */
	private static function extractPositionSlashSize(
		array $layer,
		string $source,
		bool $hasComments,
		string $positionNonTerminal,
		bool $splitBgXY,
		CSSParserOptions $options,
	): false|array {
		$slashIdx = null;
		$count = count($layer);
		for ($i = 0; $i < $count; $i++) {
			if (ShorthandCodecHelper::isDelim($layer[$i], $source, '/')) {
				if ($slashIdx !== null) {
					return false;
				}
				$slashIdx = $i;
			}
		}
		if ($slashIdx === null) {
			return [null, null, null, $layer];
		}

		$before = array_slice($layer, 0, $slashIdx);
		$after = array_slice($layer, $slashIdx + 1);
		if ($before === [] || $after === []) {
			return false;
		}

		$posStart = null;
		$posA = null;
		$posB = null;
		$bCount = count($before);
		for ($len = min(4, $bCount); $len >= 1; $len--) {
			$cand = array_slice($before, $bCount - $len);
			if (ShorthandCodecHelper::matchesNonTerminal($positionNonTerminal, $source, $hasComments, $cand, $options)) {
				if ($splitBgXY) {
					$xy = self::splitBgPositionToXY($cand, $source, $hasComments);
					if ($xy !== null) {
						$posStart = $bCount - $len;
						[$posA, $posB] = $xy;
						break;
					}
				} else {
					$posStart = $bCount - $len;
					$posA = ShorthandCodecHelper::joinComponents($source, $cand, $hasComments);
					break;
				}
			}
		}
		if ($posStart === null || $posA === null) {
			return false;
		}

		$sizeEnd = null;
		$size = null;
		$aCount = count($after);
		for ($len = min(2, $aCount); $len >= 1; $len--) {
			$cand = array_slice($after, 0, $len);
			if (ShorthandCodecHelper::matchesNonTerminal('bg-size', $source, $hasComments, $cand, $options)) {
				$sizeEnd = $len;
				$size = ShorthandCodecHelper::joinComponents($source, $cand, $hasComments);
				break;
			}
		}
		if ($sizeEnd === null || $size === null) {
			return false;
		}

		$remaining = array_merge(
			array_slice($before, 0, $posStart),
			array_slice($after, $sizeEnd),
		);

		return [$posA, $posB, $size, $remaining];
	}

	/**
	 * @param list<ParsedComponent> $remaining
	 * @return array{0: string, 1: int}
	 */
	private static function consumeRepeatTokens(array $remaining, int $idx, int $count, string $lower): array
	{
		if ($lower === 'repeat-x' || $lower === 'repeat-y') {
			return [$lower, $idx + 1];
		}
		$nextLower = ($idx + 1 < $count) ? ShorthandCodecHelper::identLower($remaining[$idx + 1]) : null;
		if ($nextLower !== null && isset(self::REPEAT_KEYWORDS[$nextLower]) && $nextLower !== 'repeat-x' && $nextLower !== 'repeat-y') {
			return [$lower . ' ' . $nextLower, $idx + 2];
		}

		return [$lower, $idx + 1];
	}

	/**
	 * @param list<string> $boxes
	 * @param array<string, true> $primaryBoxes
	 * @return ?array{0: string, 1: string}
	 */
	private static function resolveOriginAndClipBoxes(array $boxes, array $primaryBoxes, string $defaultOrigin, string $defaultClip): ?array
	{
		if ($boxes === []) {
			return [$defaultOrigin, $defaultClip];
		}
		if (!isset($primaryBoxes[$boxes[0]])) {
			return null;
		}

		return count($boxes) === 1 ? [$boxes[0], $boxes[0]] : [$boxes[0], $boxes[1]];
	}

	/**
	 * @param array<string, string> $values
	 */
	private static function serializeMask(array $values, CSSParserOptions $options): string
	{
		$split = ShorthandCodecHelper::splitCommaLayers([
			'image' => $values['mask-image'],
			'position' => $values['mask-position'],
			'size' => $values['mask-size'],
			'repeat' => $values['mask-repeat'],
			'origin' => $values['mask-origin'],
			'clip' => $values['mask-clip'],
			'composite' => $values['mask-composite'],
			'mode' => $values['mask-mode'],
		], $options, false);
		if ($split === null) {
			return '';
		}

		$layerCount = $split['count'];
		$layersOut = [];
		for ($i = 0; $i < $layerCount; $i++) {
			$image = $split['text']['image'][$i];
			$position = $split['text']['position'][$i];
			$size = $split['text']['size'][$i];
			$repeat = $split['text']['repeat'][$i];
			$origin = $split['text']['origin'][$i];
			$clip = $split['text']['clip'][$i];
			$composite = $split['text']['composite'][$i];
			$mode = $split['text']['mode'][$i];

			$parts = [];
			if ($image !== 'none') {
				$parts[] = $image;
			}
			if ($size !== 'auto') {
				$parts[] = $position . ' / ' . $size;
			} elseif ($position !== '0% 0%') {
				$parts[] = $position;
			}
			if ($repeat !== 'repeat') {
				$parts[] = $repeat;
			}
			if ($origin !== 'border-box' || $clip !== 'border-box') {
				$parts[] = $origin === $clip ? $origin : $origin . ' ' . $clip;
			}
			if ($composite !== 'add') {
				$parts[] = $composite;
			}
			if ($mode !== 'match-source') {
				$parts[] = $mode;
			}

			$layersOut[] = $parts === [] ? 'none' : implode(' ', $parts);
		}

		return implode(', ', $layersOut);
	}
}
