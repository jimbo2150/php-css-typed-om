<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Css;

enum ClassType
{
	case Primitive;

	// Image classes.
	case Image;
	case ImageSetOption;
	case CursorImage;
	// Image generator classes.
	case Canvas;
	case PaintImage;
	case NamedImage;
	case Crossfade;
	case FilterImage;
	case Gradient;

	// Other non-list classes.
	case AppleColorFilterProperty;
	case AspectRatio;
	case Attr;
	case BackgroundRepeat;
	case BasicShape;
	case BorderImageSlice;
	case BorderImageWidth;
	case BoxShadowProperty;
	case Calculation;
	case Color;
	case ColorScheme;
	case ContentDistribution;
	case Counter;
	case CustomProperty;
	case DynamicRangeLimit;
	case EasingFunction;
	case FilterProperty;
	case Font;
	case FontFaceSrcLocal;
	case FontFaceSrcResource;
	case FontFeature;
	case FontPaletteValuesOverrideColors;
	case FontStyleRange;
	case FontStyleWithAngle;
	case FontVariantAlternates;
	case FontVariation;
	case GridLineNames;
	case GridLineValue;
	case GridTemplateAreas;
	case LineBoxContain;
	case OffsetRotate;
	case Path;
	case PendingSubstitutionValue;
	case Quad;
	case Ray;
	case Rect;
	case Reflect;
	case Scroll;
	case TextShadowProperty;
	case UnicodeRange;
	case ValuePair;
	case VariableReference;
	case View;

	// Classes that contain vectors, which derive from CSSValueContainingVector.
	case ValueList;
	case Function;
	case GridAutoRepeat;
	case GridIntegerRepeat;
	case ImageSet;
	case Subgrid;
	case TransformList;
	// Do not append classes here unless they derive from CSSValueContainingVector.
}
