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
	public $parent;
	public $name;
	public $value;
	public $body = false;
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
	
	public function text($level = 0)
	{
		return str_repeat("\t", $level) . get_class($this) . ' [' . $this->name . '] ' . $this->value . "\n" .
			str_repeat("\t", $level) . "- Child:\n" .
			$this->child->text($level + 1);
	}
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
			(($this->right instanceof Node) ?
				str_repeat("\t", $level) . "- Right:\n" .
				$this->right->text($level + 1)
			: '');
	}
}

class ListNode extends Node
{
	public $list = array();
	
	public function insert_before($node)
	{
		if ($this->parent instanceof ListNode) {
			//$this->parent->list[$this->index]
		} else {
			throw new Exception('Not in a node list');
		}
	}
	
	public function text($level = 0)
	{
		$str = str_repeat("\t", $level) . get_class($this) . ' [' . $this->name . '] ' . $this->value . "\n";
		foreach ($this->list as $node) {
			$str .= $node->text($level + 1);
		}
		return $str;
	}
}

class NoopNode extends Node
{
}

class FragmentNode extends ListNode
{
	public $statement = false;
}
    
    
class QuotedExpressionNode extends ListNode
{
}

class ConditionalNode extends BinaryNode
{
	public $expr;
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

class AssignmentNode extends UnaryNode
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

class CommentNode extends Node
{
}

	/*
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
	*/

class WitangoParser extends GenericParser
{
	private static $block_start_tags = array('if', 'elseif', 'else', 'for', 'rows');
	private static $block_end_tags = array('elseif', 'else', '/if', '/for', '/rows');
	private $quote_stack = array();
	public $tokens = array();
	
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
			if ($this->meta_comment($meta_node) || $this->meta_tag($meta_node) || $this->variable($meta_node)) {
			} elseif ($this->char === $this->get_current_quote()) {
				// Break if we are in quotes and found matching quote.
				// Do not advance input.
				$more = false;
			} elseif ($this->get_current_quote() === ' ' && ($this->is_whitespace() || $this->char === '>')) {
				// Break if we are in a quotable section that isn't quoted, and
				// we hit whitespace or end of tag.
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
				if (in_array($meta_node->name, self::$block_end_tags)) {
					echo "found END TAG\n";
					$more = false;
				}
				$meta_node = null;
			}
		} while ($more);
		
		if (count($tree->list) === 1) {
			// If there's only one node in the list, replace the whole fragment node list with that node.
			// Just to save on space.
			
			// Disabled -- translator has fragment handling var assignment and output, can't bypass it.
			//$tree = $tree->list[0];
			return true;
		} elseif (count($tree->list) === 0) {
			return false;
		}
		
		return true;
	}
	
	// expression = operand {('and' | 'or') operand}
	function expression(&$tree)
	{
		if ($this->operand($tree)) {
			$this->whitespace();
			
			while (true) {
				if ($this->peek('and')) {
					$op = '&&';
				} elseif ($this->peek('or')) {
					$op = '||';
				} else {
					break;
				}
				
				$node = new OpNode();
				$node->value = $op;
				$node->left = $tree;
				if (!$this->operand($node->right)) {
					$this->error('Expected operand on right');
				}
				$this->whitespace();
				$tree = $node;
			}
			return true;
		}
		return false;
	}
	
	// operand = math_expression [('=' '!=' '<' '<=' '>' >=') math_expression]
	function operand(&$tree)
	{
		$left = null;
		if ($this->math_expression($left)) {
			$found = false;
			foreach (array('=', '!=', '<', '<=', '>', '>=') as $symbol) {
				if ($this->peek($symbol))
				$found = true;
				break;
			}
			if ($found) {
				$tree = new OpNode();
				$tree->value = ($symbol === '=') ? '==' : $symbol;
				$tree->left = $left;
				$this->next();
				$this->math_expression($tree->right);
			} else {
				$tree = $left;
			}
			return true;
		}
		return false;
	}

	// math_expression = term {('+' | '-') term}
	function math_expression(&$tree)
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
				if (!$this->factor($node->right)) {
					$this->error('Expected factor on right');
				}
				$this->whitespace();
				$tree = $node;
			}
			return true;
		}
		return false;
	}
	
	/*
	// factor = operand {('*' | '/') operand}
	function factor(&$tree)
	{
		if ($this->operand($tree)) {
			$this->whitespace();
			while ($this->char === '*' || $this->char === '/') {
				$node = new OpNode();
				$node->value = $this->char;
				$node->left = $tree;
				$this->next();
				if (!$this->operand($node->right)) {
					$this->error('Expected factor on right');
				}
				$this->whitespace();
				$tree = $node;
			}
			return true;
		}
		return false;
	}
	*/

	// operand = meta_tag | variable | number | parens
	function factor(&$tree)
	{
		return $this->meta_tag($tree) || $this->variable($tree) || $this->number($tree) || $this->parens($tree);
	}

	function meta_tag(&$tree)
	{
		$closing = false;
		if ($this->peek('<@') || ($closing = $this->peek('</@'))) {
			$tree = new MetaTagNode();
			$tree->name = $this->read_ident();
			
			if (!strlen($tree->name)) {
				$this->error('Expected meta tag name');
			}
			if ($closing) {
				$tree->name = '/' . $tree->name;
			}
			echo "Tag: " . $tree->name . "\n";

			// Consume any whitespace.
			$this->whitespace();

			while (false !== $attr = $this->read_ident()) {
				// Attributes don't require a value.
				if ($this->peek('=')) {
				
					// Expecting an optional begining quote.
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
					
					if ($quote !== ' ') {
						var_dump($this->char);
						$this->expect(array_pop($this->quote_stack));
					}
				} else {
					// No value.
					$tree->list[$attr] = new NoopNode();
				}
			}
			
			$this->expect('>');
			
			// Rewrite the node as a more specific type of tag if needed.
			$this->meta_assign($tree) || $this->meta_if($tree) || $this->meta_for($tree) || $this->meta_rows($tree);
			return true;
		}
		return false;
	}

	function meta_assign(&$tree)
	{
		if ($tree->name === 'assign') {
			echo "found ASSIGN\n";
			$node = new AssignmentNode();
			if (!isset($tree->list['name'])) {
				$this->error('Expecting name attribute for <@assign>');
			} elseif (!isset($tree->list['value'])) {
				$tree->error('Expecting value attribute for <@assign>');
			}
			
			$name_node = @$tree->list['name']->list[0];
			if ($name_node instanceof TextNode) {
				// Handle the case of name being a simple TextNode.
				// Extract name and scope from meta tag.
				$parts = explode('$', $name_node->value);
				if (count($parts) === 2) {
					// "name" contains scope and name.
					$node->scope = $parts[0];
					$node->name = $parts[1];
				} elseif (count($parts) === 1) {
					// "name" only contains name. Check "scope" for scope.
					if (!isset($tree->list['scope'])) {
						$this->error('Expecting scope attribute');
					}
					$node->name = $parts[0];
					$node->scope = $tree->list['scope']->list[0]->value;
				} else {
					$this->error('Invalid format for name attribute');
				}
			} else {
				// Handle the case of name being a FragmentNode (expression).
				$this->error('Not implemented: dynamic assignment');
			}
			$node->child = $tree->list['value'];
			$tree = $node;
			return true;
		}
		return false;
	}
	
	// Grammar should be: if [{elseif}] [else]
	function meta_if(&$tree)
	{
		if ($tree->name === 'if') {
			echo "found IF\n";
			$expr = $tree->list['expr'];
			$tree = new ConditionalNode();
			$tree->expr = $expr;
			
			$this->fragment($tree->left);
			$tree->left->body = true;
			$tree->right = array_pop($tree->left->list);
			if ($tree->right->name === '/if' || $this->meta_elseif($tree->right) || $this->meta_else($tree->right)) {
				return true;
			} else {
				$this->error('Expecting end of conditional');
			}
		}
		return false;
	}
	
	// This is left recursive, not good.
	function meta_elseif(&$tree)
	{
		// Temporary solution -- nest the else if:
		// else { if (expr) { } }
		$tree->name = 'if';
		return $this->meta_if($tree);
		
//		if ($tree->name === 'elseif') {
//			echo "found ELSEIF\n";
//			return true;
//		}
//		return false;
	}
	
	function meta_else(&$tree)
	{
		if ($tree->name === 'else') {
			echo "found ELSE\n";
			$this->fragment($tree);
			$tree->body = true;
			$end = array_pop($tree->list);
			if ($end->name === '/if') {
				return true;
			} else {
				$this->error('Expecting end of conditional');
			}
		}
		return false;
	}
	
	function meta_for(&$tree)
	{
		if ($tree->name === 'for') {
			return true;
		}
		return false;
	}
	
	function meta_rows(&$tree)
	{
		if ($tree->name === 'rows') {
			return true;
		}
		return false;
	}
	
	function meta_comment(&$tree) {
		if ($this->peek('<@!')) {
			$tree = new CommentNode();
			while ($this->char !== '>') {
				$tree->value .= $this->char;
				$this->next_raw();
			}
			$this->expect('>');
			$tree->value = trim($tree->value);
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
			echo "OPEN PARENS\n";
			$tree = new ParenNode();
			$this->expression($tree->child);
			$this->expect(')');
			echo "CLOSE PARENS\n";
			var_dump($this->char);
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
		return false !== @strpos(" \t\r\n", $this->char);
	}
}


class GenericParser
{
	public $code = '';
	public $pos = 0;
	
	public function __construct($code)
	{
		//preg_match_all('#(?:@@|<@|</@|<=|>=|!=|.)#', $code, $list);
		$this->code = $code;
		$this->pos = -1;
		$this->next_raw();
	}
	
	public function is_eof()
	{
		return $this->pos >= strlen($this->code);
	}
	
	function next_raw()
	{
		return $this->char = @$this->code[++$this->pos];
	}

	function next($distance = 1)
	{
		$i = 0;
		// Advance given distance, plus past any ending whitespace.
		while ($i++ < $distance || false !== @strpos(" \t\r\n", $this->code[$this->pos])) {
			$this->pos++;
		}
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
	
	public function peek($str)
	{
		$len = strlen($str);
		if ($str === substr($this->code, $this->pos, $len)) {
			$this->next($len);
			return true;
		}
		return false;
	}
	
	public function expect($str)
	{
		if (!$this->peek($str)) {
			$this->error('Expected ' . $str);
		}
	}
	
	public function error($msg)
	{
		$lines = explode("\n", substr($this->code, 0, $this->pos + 1));
		
		throw new ParseError($this, $msg . ' on line ' . count($lines) . ': ' . $lines[count($lines) - 1]);
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




