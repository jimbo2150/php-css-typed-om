<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Grammar;

/**
 * AST node kinds for the CSS Values & Units Level 4 Value Definition Syntax (VDS).
 *
 * @internal
 */
enum CSSGrammarNodeType: string
{
	case Keyword = 'keyword';
	case Delim = 'delim';
	case DataType = 'data-type';
	case NonTerminal = 'non-terminal';
	case FunctionCall = 'function-call';
	case SquareBracketBlock = 'square-bracket-block';
	case Sequence = 'sequence';
	case AllOrder = 'all-order';
	case AnyOrder = 'any-order';
	case Alternative = 'alternative';
	case Repeat = 'repeat';
	case RequiredGroup = 'required-group';
}
