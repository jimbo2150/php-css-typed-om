<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\WebCore\css\parser;

enum CSSParserTokenType
{
	case IdentToken;
	case FunctionToken;
	case AtKeywordToken;
	case HashToken;
	case UrlToken;
	case BadUrlToken;
	case DelimiterToken;
	case NumberToken;
	case PercentageToken;
	case DimensionToken;
	case IncludeMatchToken;
	case DashMatchToken;
	case PrefixMatchToken;
	case SuffixMatchToken;
	case SubstringMatchToken;
	case ColumnToken;
	case NonNewlineWhitespaceToken;
	case NewlineToken;
	case CDOToken;
	case CDCToken;
	case ColonToken;
	case SemicolonToken;
	case CommaToken;
	case LeftParenthesisToken;
	case RightParenthesisToken;
	case LeftBracketToken;
	case RightBracketToken;
	case LeftBraceToken;
	case RightBraceToken;
	case StringToken;
	case BadStringToken;
	case EOFToken;
	case CommentToken;
	case LastCSSParserTokenType; // Equal to CommentToken

	public function get(): CSSParserToken
	{
		return new CSSParserToken($this);
	}
}
