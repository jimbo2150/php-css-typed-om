<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Parser;

/**
 * Enumeration of all 25 CSS Syntax Module Level 3 §4.2 token types.
 *
 * @see https://drafts.csswg.org/css-syntax-3/#tokenization
 */
enum CSSTokenType: string
{
	case Ident = 'ident-token';
	case Function = 'function-token';
	case AtKeyword = 'at-keyword-token';
	case Hash = 'hash-token';
	case String = 'string-token';
	case BadString = 'bad-string-token';
	case Url = 'url-token';
	case BadUrl = 'bad-url-token';
	case Delim = 'delim-token';
	case Number = 'number-token';
	case Percentage = 'percentage-token';
	case Dimension = 'dimension-token';
	case Whitespace = 'whitespace-token';
	case Cdo = 'CDO-token';
	case Cdc = 'CDC-token';
	case Colon = 'colon-token';
	case Semicolon = 'semicolon-token';
	case Comma = 'comma-token';
	case LeftSquareBracket = '[-token';
	case RightSquareBracket = ']-token';
	case LeftParenthesis = '(-token';
	case RightParenthesis = ')-token';
	case LeftCurlyBracket = '{-token';
	case RightCurlyBracket = '}-token';
	case Eof = 'EOF-token';
}
