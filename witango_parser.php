<?php

/*

Go back to string-based parsing
need to be able to peek('and') for boolean operators

add boolean operators:

term = boolean {('*' | '/') boolean} 
boolean = factor {('and' | 'or')} factor}

--------------------------
 
 TEXT/STRINGS:
 
 Presentation
 - whitespace not skipped
 - everything that is not witango code is outputted
 - code is wrapped in php tags, everything else is passthrough
 
 Tag Attr
 - whitespace not skipped
 - everything that is not witango code is a string passed to the function
 - code and strings are concatenated together
 
 Attr Expressions
 - whitespace skipped
 - quotes are delimiters
 - quotes can be used as part of the expression?
 - quotes can be backslash escaped
 
 Strings
 - anything that doesn't eval to an array or number is a string.
 - string directly included in expression must be surrounded by single quotes if it is a single letter, starts with a digit, contains special char or space.
 
 @Calc
 - let doThis = "1 + 2"
 - evals its expr: <@calc expr="<@arg doThis>"> returns the result of the expression in doThis.
 - what does this do: <@calc expr="<@arg doThis> + 5"> ?
 - strategy
    1. treat expr as a string, then eval it
    2. don't support this craziness.
 
 BLOCK TAGS:
 
 <@rows>
 blah
 </@rows>
 
 <@if expr="5=5">equal<@else>not equal</@if>
 
 ------------------------------
 
 @CALC
 
 Calculation variables
 - single letter vars within an expr
 - example: h := 5
 
 Ternary: (cond) ? expr1 : expr2
 
 Arrays used in expressions are treated as the number of rows in the array
 - no way to statically know the type of a variable.
 - create a WitangoArray class with a toString that returns number of rows?
 
 Functions
 - Len()
 
 Operators
 - beginswith, contains, etc.
 
 ------------------------------
 
 Positional attrs
 <@if "5=5">
 
 ------------------------------
 
 Optional Quoting:
 <@assign name=request$bam value=<@calc expr="5+5">>
 
 ----------------------------
*/

class Node
{
	public $name;
	public $value;
	public $line = 0;
	public function __construct() {}
	public function text($level = 0)
	{
		return str_repeat("\t", $level) . get_class($this) . ' [' . $this->name . '] ' . $this->value . "\n";
	}
}

class UnaryNode extends Node
{
	public $child;
	
}

class BinaryNode extends Node
{
	public $left;
	public $right;
	public function text($level = 0)
	{
		return str_repeat("\t", $level) . get_class($this) . ' [' . $this->name . '] ' . $this->value . "\n" .
			str_repeat("\t", $level) . "- Left:\n" .
			$this->left->text($level + 1) .
			str_repeat("\t", $level) . "- Right:\n";
			$this->right->text($level + 1);
	}
}

class ListNode extends Node
{
	public $list = array();
	public function text($level = 0)
	{
		$str = str_repeat("\t", $level) . get_class($this) . ' [' . $this->name . '] ' . $this->value . "\n";
		foreach ($this->list as $node) {
			$str .= $node->text($level + 1);
		}
		return $str;
	}
}

class FragmentNode extends ListNode
{
}
    
    
class QuotedExpressionNode extends ListNode
{
}

class ConditionNode extends BinaryNode
{
}

class OpNode extends BinaryNode
{
}

class MetaTagNode extends ListNode
{
	public $name;
}

class VariableNode extends Node
{
	public $name;
	public $scope;
}

class NumberNode extends Node
{
}

class ParenNode extends UnaryNode
{
}

class TextNode extends Node
{
}


class WitangoParser extends GenericParser
{
	public $tokens = array();
	private $quote_stack = array();
	
	public function get_current_quote()
	{
		return @$this->quote_stack[count($this->quote_stack) - 1];
	}
	
	public function get_inversed_quote()
	{
		if ($this->get_current_quote() === ' ') {
			return ' ';
		} else {
			return ($this->get_current_quote() == '"') ? "'" : '"';
		}
	}
	
	public function fragment(&$tree)
	{
		$tree = new FragmentNode();
		$text_node = new TextNode();
		$meta_node = null;
		$more = true;
		do {
			if ($this->meta_tag($meta_node) || $this->variable($meta_node)) {
			} elseif ($this->char === $this->get_current_quote()) {
				// Break if we are in quotes and found matching quote.
				// Do not advance input.
				$more = false;
			} elseif ($this->get_current_quote() === ' ' && $this->is_whitespace()) {
				// Break if we are in a quotable section that isn't quoted, and we hit whitespace.
				// Do not advance input.
				$more = false;
			} elseif ($this->is_eof()) {
				$more = false;
			} else {
				$text_node->value .= $this->char;
				$this->next_raw();
			}
			
			if ($meta_node !== null || !$more) {
				if (strlen($text_node->value)) {
					$tree->list[] = $text_node;
					$text_node = new TextNode();
				}
			}
			
			if ($meta_node !== null) {
				$tree->list[] = $meta_node;
				$meta_node = null;
			}
		} while ($more);
		
		if (count($tree->list) === 1) {
			// If there's only one node in the list, replace the whole fragment node list with that node.
			// Just to save on space.
			$tree = $tree->list[0];
		} elseif (count($tree->list) === 0) {
			return false;
		}
		
		return true;
	}
	
	// condition = expression ('=' '!=' '<' '<=' '>' >=') expression
	function condition(&$tree)
	{
		$left = null;
		if ($this->expression($left) && in_array($this->char, array('=', '!=', '<', '<=', '>', '>='))) {
			$tree = new ConditionNode();
			$tree->value = $this->char;
			$tree->left = $left;
			$this->next();
			$this->expression($tree->right);
			return true;
		}
		return false;
	}
	
	// quoted_expression = ('"' | "'") expression ('"' | "'")
	public function quoted_expression(&$tree)
	{
		//echo "EXPRESSION ->\n";
		$tree = new QuotedExpressionNode();
		
		if (count($this->quote_stack)) {
			$last_quote = $this->quote_stack[count($this->quote_stack) - 1];
			$quote = ($last_quote == '"') ? "'" : '"';
			if (!$this->peek($quote)) {
				return false;
			}
		} else {
			if ($this->peek('"')) {
				$quote = '"';
			} elseif ($this->peek("'")) {
				$quote = "'";
			} else {
				return false;
			}
		}
		array_push($this->quote_stack, $quote);
		while (!$this->peek($quote)) {
			if ($this->expression($node)) {
				$tree->list[] = $node;
			} else {
				die ("no match?\n");
			}
		}
		
		array_pop($this->quote_stack);
		//echo "<- EXPRESSION\n";
		return true;
	}

	// expression = term {('+' | '-') term}
	function expression(&$tree)
	{
		if ($this->term($tree)) {
			$this->whitespace();
			while ($this->char === '+' || $this->char === '-') {
				$node = new OpNode();
				$node->value = $this->char;
				$node->left = $tree;
				$this->next();
				if (!$this->term($node->right)) {
					$this->error('Expected term on right');
				}
				$this->whitespace();
				$tree = $node;
			}
			return true;
		}
		return false;
	}

	// term = factor {('*' | '/') factor}
	function term(&$tree)
	{
		if ($this->factor($tree)) {
			$this->whitespace();
			while ($this->char === '*' || $this->char === '/') {
				$node = new OpNode();
				$node->value = $this->char;
				$node->left = $tree;
				$this->next();
				if (!$this->term($node->right)) {
					$this->error('Expected term on right');
				}
				$this->whitespace();
				$tree = $node;
			}
			return true;
		}
		return false;
	}

	// factor = meta_tag | variable | number | quoted_expression | parens
	function factor(&$tree)
	{
		return $this->meta_tag($tree) || $this->variable($tree) || $this->number($tree) || $this->quoted_expression($tree) || $this->parens($tree);
	}

	function meta_tag(&$tree)
	{
		if ($this->peek('<@')) {
			$tree = new MetaTagNode();
			$tree->name = $this->read_ident();
			if (!strlen($tree->name)) {
				$this->error('Expected meta tag name');
			}

			// Consume any whitespace.
			$this->whitespace();

			while (false !== $attr = $this->read_ident()) {
				//echo 'ATTR: ' . $attr . "\n";
				$this->expect('=');
				
				if (count($this->quote_stack)) {
					$quote = $this->get_inversed_quote();
					if ($quote !== ' ' && !$this->peek($quote)) {
						$quote = ' ';
					}
				} else {
					if ($this->peek('"')) {
						$quote = '"';
					} elseif ($this->peek("'")) {
						$quote = "'";
					} else {
						$quote = ' ';
					}
				}
				
				array_push($this->quote_stack, $quote);
				if (strtolower($attr) === 'expr') {
					// Note: this is not good enough detection, since the tag could be written:
					// <@calc "1 + 2"> instead. In reality witango eval()s the contents of expr at
					// runtime, so maybe this should be processed as a fragment also.
					$this->expression($tree->list[$attr]);
				} else {
					$this->fragment($tree->list[$attr]);
				}
				$this->expect(array_pop($this->quote_stack));
			}
			
			$this->expect('>');
			return true;
		}
		return false;
	}
	
	function variable(&$tree)
	{
		if ($this->peek('@@')) {
			$tree = new VariableNode();

			$tree->scope = strtolower($this->read_ident());
			if (!strlen($tree->scope)) {
				$this->error('Expected variable scope');
			}

			$this->expect('$');
			$tree->name = $this->read_ident();
			if (!strlen($tree->name)) {
				$this->error('Expected variable name');
			}
			return true;
		}
		return false;
	}
	
	function number(&$tree)
	{
		if ($this->is_digit($this->char)) {
			$value = $this->char;
			while ($this->is_digit($this->next())) {
				$value .= $this->char;
			}
			$tree = new NumberNode();
			$tree->value = (int)$value;
			return true;
		}
		return false;
	}
	
	function parens(&$tree)
	{
		if ($this->peek('(')) {
			$tree = new ParenNode();
			$this->expression($tree->child);
			$this->expect(')');
			return true;
		}
		return false;
	}

	function read_ident()
	{
		$ident = false;
		while ($this->is_alpha_numeric($this->char)) {
			$ident .= $this->char;
			$this->next_raw();
		}
		return $ident;
	}

	function whitespace()
	{
		while ($this->is_whitespace()) {
			$this->next();
		}
	}
							  
							  function is_whitespace() {
								return false !== strpos(" \t\r\n", $this->char);
							  }
}


class GenericParser
{
	public $code = '';
	public $pos = 0;
	
	public function __construct($code)
	{
		preg_match_all('#(?:@@|<@|<=|>=|!=|.)#', $code, $list);
		$this->code = $list[0];
		$this->pos = -1;
		$this->next();
	}
	
	public function is_eof()
	{
		return $this->pos >= count($this->code);
	}
	
	function next_raw()
	{
		return $this->char = @$this->code[++$this->pos];
	}

	function next()
	{
		while (false !== strpos(" \t\r\n", @$this->code[++$this->pos])) {}
		return $this->char = @$this->code[$this->pos];
	}

	function look()
	{
		$pos = $this->pos;
		while (false !== strpos(" \t\r\n", @$this->code[++$pos])) {}
		return $this->code[$pos];
	}

	function look_raw()
	{
		return @$this->code[$this->pos + 1];
	}

	function is_digit($char)
	{
		return $char >= '0' && $char <= '9';
	}

	function is_alpha($char)
	{
		return ($char >= 'a' && $char <= 'z') || ($char >= 'A' && $char <= 'Z') || $char == '_';
	}

	function is_alpha_numeric($char)
	{
		return $this->is_alpha($char) || $this->is_digit($char);
	}
	
	public function peek($char)
	{
		if ($this->char === $char) {
			$this->next();
			return true;
		}
		return false;
	}
	
	public function expect($char)
	{
		if (!$this->peek($char)) {
			$this->error('Expected ' . $char);
		}
	}
	
	public function error($msg)
	{
		throw new ParseError($this, $msg);
	}
}

class ParseError extends Exception
{
	public function __construct($src, $error = '')
	{
		parent::__construct($error);
	}
}

//$expr = '@@user$adminlevel@@request$test';
//
//$expr = <<<EOL
//the date diff is: <@calc expr='<@datediff date1="<@var name='<@currow>'>" format="<@getFormat>">'> and @@request\$name said "@@request\$msg"
//EOL;
//
//$expr = '"(2 + @@request$test) * <@calc expr=\'9 + 9\'>" != 5 - 2';
//
//$src = new WitangoParser($expr);
//
//$tree = null;
//$src->try_expr($tree);
//$src->fragment($tree);
//echo $tree->text();
//print_r($tree);



