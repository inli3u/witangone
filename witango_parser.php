<?php

/*

Go back to string-based parsing
need to be able to peek('and') for boolean operators

add boolean operators:

term = boolean {('*' | '/') boolean} 
boolean = factor {('and' | 'or')} factor}

add string handling:

translate MetaTag into CallFunction
- substring == substr, etc.



*/

class Node
{
	public $value;
	public $line = 0;
	public function __construct() {}
	public function text($level = 0)
	{
		return str_repeat("\t", $level) . get_class($this) . "\n";
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
		return str_repeat("\t", $level) . get_class($this) . ' ' . $this->value . "\n" .
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
		$str = str_repeat("\t", $level) . get_class($this) . ' ' . $this->value . "\n";
		foreach ($this->list as $node) {
			$str .= $node->text($level + 1);
		}
		return $str;
	}
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

class MetaTagNode extends Node
{
	public $name;
	public $attr_list = array();
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



class WitangoParser extends GenericParser
{
	public $tokens = array();
	private $quote_stack = array();
	
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
				$this->quoted_expression($tree->attr_list[$attr]);
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
		while (false !== strpos(" \t\r\n", $this->char)) {
			$this->next();
		}
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


/*
$expr = <<<EOL
('<@datediff date1="<@ARG initiation>" date2="1/1/<@currentdate format='datetime:%Y'>">'<'0') and ('@@user\$adminlevel'>'-2')
EOL;

//$expr = '@@user$adminlevel@@request$test';

$expr = <<<EOL
<@calc expr='<@datediff date1="<@var name='<@currow>'>" format="<@getFormat>">'>
EOL;

$expr = '"(2 + @@request$test) * <@calc expr=\'9 + 9\'>" != 5 - 2';

$src = new WitangoParser($expr);

$tree = null;
//$src->try_expr($tree);
$src->condition($tree);
echo $tree->text();
//print_r($tree);
*/


