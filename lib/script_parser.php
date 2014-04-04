<?php

/*

Go back to string-based parsing
need to be able to advance('and') for boolean operators

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

require_once(PATH . 'lib/generic_parser.php');
require_once(PATH . 'lib/nodes.php');

class ScriptParser extends GenericParser
{
	const WHITESPACE = 1;

	private static $block_start_tags = array('comment', 'debug', 'if', 'ifequal', 'ifempty', 'ifnotempty', 'elseif', 'else', 'for', 'rows');
	private static $block_end_tags = array('elseif', 'else', '/comment', '/debug', '/if', '/for', '/rows');
	private $paired_brace_stack = array();
	private $breakpoint_stack = array();
	private $deferred_meta_node = null;
	public $tokens = array();
	public $debug = false;
	
	private function get_current_quote()
	{
		for ($i = count($this->paired_brace_stack) - 1; $i >= 0; $i--) {
			$str = $this->paired_brace_stack[$i];
			if ($str == '"' || $str == "'") {
				return $str;
			}
		}

		return null;
	}
	
	private function get_inversed_quote()
	{
		$quote = $this->get_current_quote();
		if ($quote !== null) {
			return ($quote == '"') ? "'" : '"';
		}

		return null;
	}

	private function push_paired_brace($str)
	{
		array_push($this->paired_brace_stack, $str);
	}

	private function pop_paired_brace()
	{
		return array_pop($this->paired_brace_stack);
	}

	private function is_paired_brace()
	{
		if (!count($this->paired_brace_stack)) {
			return false;
		}

		$str = $this->paired_brace_stack[count($this->paired_brace_stack) - 1];
		if ($str === null) {
			return false;
		}

		return $this->peek($str);
	}

	private function push_breakpoint($breakpoint)
	{
		$list = is_array($breakpoint) ? $breakpoint : [$breakpoint];
		array_push($this->breakpoint_stack, $list);
	}

	private function pop_breakpoint()
	{
		return array_pop($this->breakpoint_stack);
	}

	/**
	 * Check if we've hit the breakpoint on top of the stack.
	 */
	private function is_breakpoint()
	{
		if (!count($this->breakpoint_stack)) {
			return false;
		}

		$list = $this->breakpoint_stack[count($this->breakpoint_stack) - 1];
		if ($list === null) {
			return false;
		}

		foreach ($list as $breakpoint) {
			if (is_string($breakpoint)) {
				if ($this->peek($breakpoint)) {
					return true;
				}
			} else {
				if ($breakpoint === self::WHITESPACE && $this->is_whitespace()) {
					return true;
				}
			}
		}

		return false;
	}



	// A few convenience methods
	public static function get_expression($code)
	{
		$parser = new ScriptParser($code);
		$parser->expression($tree);
		return $tree;
	}

	public static function get_fragment($code)
	{
		$parser = new ScriptParser($code);
		$parser->fragment($tree);
		return $tree;
	}

	public static function get_variable_ident($code)
	{
		$parser = new ScriptParser($code);
		$parser->variable_ident($tree);
		return $tree;
	}

	
	public function fragment(&$tree)
	{
		$tree = new FragmentNode();
		$text_node = new TextNode();
		$meta_node = null;
		$more = true;
		do {
			if ($this->deferred_meta_node !== null) {
				$tmp = $this->deferred_meta_node;
				$this->deferred_meta_node = null;
				$this->fragment($tmp->child);
				$tree->list[] = $tmp;
			}
			
			if ($this->meta_comment($meta_node) || $this->meta_tag($meta_node) || $this->variable($meta_node)) {
				// Noop.
			} elseif ($this->is_breakpoint() || $this->is_eof()) {
				// Break due to matching quotes/braces, a breakpoint, or EOF.
				// Do not advance input.
				$more = false;
			} else {
				// Non-code goes into a text node.
				$text_node->value .= $this->char();
				$this->next_raw();
			}

			if ($meta_node !== null || !$more) {
				if (strlen($text_node->value)) {
					$tree->list[] = $text_node;
					$text_node = new TextNode();
				}
			}

			if ($meta_node !== null) {
                $this->info('TAG: ' . $meta_node->name);
				if (in_array($meta_node->name, self::$block_end_tags)) {
					$this->info("found END TAG {$meta_node->name}\n");
					$more = false;
				} else {
					// Only add meta tags that are not end tags.
					$tree->list[] = $meta_node;
				}

				if (in_array($meta_node->name, self::$block_start_tags)) {
					if (!in_array($meta_node->name, self::$block_end_tags)) {
						// Parse the child code fragment
						$this->fragment($meta_node->child);
					} else {
						// End tag is also a start tag. We have to exit out
						// of this loop and let the parent loop parse its
						// code fragment.
						$this->deferred_meta_node = $meta_node;
					}
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
	
	// meta_tag = ('<@' | '</@') name {[attr_name '='] ['"'] fragment ['"']} '>'
	function meta_tag(&$tree)
	{
        if ($this->advance('</@')) {
            // This should actually be a stand alone bit of grammer.
            $tag_name = $this->read_ident();
            $this->info('Tag: Peeked start of an end tag "' . $tag_name . '"');
            $this->whitespace();
            $this->expect('>');
            
            $tree = new BlockMetaTagNode();
            $tree->name = '/' . $tag_name;
            
            return true;

        } elseif ($this->advance('<@')) {
			
			$tag_name = $this->read_ident();
			$missing = false;
			
			if (!strlen($tag_name)) {
				$this->syntax_error('Expected meta tag name');
			}
			
            $tree = new BlockMetaTagNode();
			if (!$tree->handles_tag($tag_name)) {
				$tree = new MetaTagNode();
                if (!$tree->handles_tag($tag_name)) {
                	if (ALLOW_MISSING_SYMBOLS) {
                		Witangone::track_missing_meta_tag($tag_name);
                		$missing = true;
                	} else {
                    	$this->unknown_symbol_error('Unknown meta tag "' . $tag_name . '"');
                    }
                }
			}

			$tree->name = $tag_name;
            $attr_defs = $tree->get_attr_defs();
            if (!$attr_defs) {
            	$attr_defs = [];
            }
			$this->info("Tag: " . $tree->name . "\n");
			
			// Consume any whitespace.
			$this->whitespace();

			// Consume attributes.
			$attr_pos = 0;
			while ($this->char() !== '>' && $this->meta_tag_attr($tree, $attr_defs, $attr_pos)) {
				$attr_pos++;
				$this->whitespace();
			}
			
			$this->expect('>');
			
			// Check required attributes.
			foreach ($attr_defs as $name => $props) {
				if (@$props[2] === MetaTagNode::REQUIRED && !isset($tree->list[$name])) {
					$this->syntax_error('Meta tag missing required attribute "' . $name . '"');
				}
			}
			
			// Check if we just parsed a starting tag and if so parse its body.
			// Fragment already knows to return on ending tags.
//			if (in_array($tag_name, self::$block_start_tags)) {
//				//die('starting tag');
//				$this->fragment($tree->child);
//			}
			// Rewrite the node as a more specific type of tag if needed.
			//$this->meta_assign($tree) || $this->meta_if($tree) || $this->meta_for($tree) || $this->meta_rows($tree);
			return true;
		}
		return false;
	}
	
    // TODO: this is having trouble with the following code <@assign request$var 'text'>
	function meta_tag_attr(&$tree, $attr_defs, $attr_pos = 0)
	{
		if ($this->char() === '>') {
			return false;
		}
		
		// Attributes don't require a name.
		$attr_name = null;

		// Optional attribute name.
		$this->set_rewind_point();
		$this->whitespace();
		$this->read_tag_attr_name($attr_name);

		if (!$this->advance_ws('=')) {
			// We were incorrect in the assumption that we were reading an attr
			// name.
			$this->rewind();
			$attr_name = null;

			// lookup name from position.
			foreach ($attr_defs as $name => $props) {
				if ($attr_pos === @$props[1]) {
					$attr_name = $name;
				}
			}
		}
        
		if (!strlen($attr_name)) {
			// Maybe there's no attribute here, haven't even checked for a value yet.
			$this->syntax_error('Unnamed attribute not allowed at this position');
		}

		$this->whitespace();

		// Expecting an optional begining quote.
		$char = $this->char();
		if ($char == '"' || $char == "'") {
			// Quoted value.

			// I thought alternating nested quotes were enforced, but apparently Witango does not care.
			// $inversed = $this->get_inversed_quote();
			// if (strlen($inversed) && $char != $inversed) {
			// 	$this->syntax_error('Unexpected ' . $char);
			// }

			$this->push_breakpoint($char);
			$this->move(1);
			$quote_char = $char;
		} else {
			// Unquoted value.
			$this->push_breakpoint([self::WHITESPACE, '>']);
			$quote_char = null;
		}

		// Followed by a value.
		// Lookup expected node type.
		$parse_func = @$attr_defs[$attr_name][0];
		if ($parse_func === null) {
			$parse_func = 'fragment';
		}
		$val = null;
		if ($parse_func === 'fragment') {
			$this->fragment($val);
		} elseif ($parse_func === 'expression') {
			$this->expression($val);
		} elseif ($parse_func === 'variable_ident') {
			// Witango seems to allow the '@@scope$name' syntax in attributes that call for just 'scope$name'.

			$this->variable_ident($val);

			if (!$val) {
				if ($this->variable($val)) {
					// Extract VariableIdentNode from VariableNode.
					$val = $val->child;
				}
			}

			if (!$val) {
				$this->syntax_error('Expected variable name');
			}
		} else {
			$this->error("Unknown parse function '$parse_func'");
		}
        
		//call_user_func(array($this, $parse_func), $a);
		if ($attr_name == 'encoding') {
			$tree->encoding = $val;
		} else {
			$tree->list[$attr_name] = $val;
		}

		$this->pop_breakpoint();

		// End with matching quote, if quoted.
		if ($quote_char !== null) {
			$this->expect($quote_char);
		}
		
		return true;
	}
	
	function read_tag_attr_name(&$attr)
	{
		$tmp = $this->read_ident();
		if (strlen($tmp)) {
			$attr = $tmp;
			return true;
		}
		return false;
	}
	
	// Expression func
	// expression = operand {('and' | 'or') operand}
	function expression(&$tree)
	{
		$this->info('enter expression');
		$this->whitespace();
		if ($this->operand($tree)) {
			while (true) {
				$this->whitespace();
				if ($this->advance('and') || $this->advance('&&')) {
					$op = '&&';
				} elseif ($this->advance('or') || $this->advance('||')) {
					$op = '||';
				} else {
					break;
				}
				
				$this->whitespace();
				$node = new OpNode();
				$node->value = $op;
				$node->left = $tree;
				if (!$this->operand($node->right)) {
					$this->syntax_error('Expected operand on right');
				}
				$tree = $node;
			}
			$this->info('exit expression true');
			return true;
		}
		$this->info('exit expression false');
		return false;
	}
	
	// Expression func
	// operand = math_expression [('=' '!=' '<' '<=' '>' >=') math_expression]
	function operand(&$tree)
	{
		$this->whitespace();
		$left = null;
		if ($this->math_expression($left) || $this->string($left)) {
			$this->whitespace();
			$found = false;
			// It's important to advance() longer strings first, in case a shorter
			// string actually matches the beginning of a longer one as with
			// '<' and '<='.
			foreach (array('!=', '<=', '>=', '=', '<', '>', 'contains', 'beginswith', 'endswith') as $symbol) {
				if ($this->advance($symbol)) {
					$found = true;
					break;
				}
			}
			$this->whitespace();
			if ($found) {
				$tree = new OpNode();
				$tree->value = ($symbol === '=') ? '==' : $symbol;
				$tree->left = $left;
				$this->math_expression($tree->right) || $this->string($tree->right);
			} else {
				$tree = $left;
			}
			return true;
		}
		return false;
	}

	// Expression func
	// math_expression = term {('+' | '-') term}
	function math_expression(&$tree)
	{
		$this->whitespace();
		if ($this->term($tree)) {
			$this->whitespace();
			while ($this->char() === '+' || $this->char() === '-') {
				$node = new OpNode();
				$node->value = $this->char();
				$this->move(1);
				$node->left = $tree;
				$this->whitespace();
				if (!$this->term($node->right)) {
					$this->syntax_error('Expected term on right');
				}
				$this->whitespace();
				$tree = $node;
			}
			return true;
		}
		return false;
	}

	// Expression func
	// term = factor {('*' | '/') factor}
	function term(&$tree)
	{
		$this->whitespace();
		if ($this->power($tree)) {
			$this->whitespace();
			while ($this->char() === '*' || $this->char() === '/' || $this->char() === '%') {
				$node = new OpNode();
				$node->value = $this->char();
				$this->move(1);
				$node->left = $tree;
				$this->whitespace();
				if (!$this->power($node->right)) {
					$this->syntax_error('Expected power on right');
				}
				$this->whitespace();
				$tree = $node;
			}
			return true;
		}
		return false;
	}

	// Expression func
	// term = factor {('*' | '/') factor}
	function power(&$tree)
	{
		$this->whitespace();
		if ($this->factor($tree)) {
			$this->whitespace();
			while ($this->char() === '^') {
				$node = new OpNode();
				$node->value = $this->char();
				$this->move(1);
				$node->left = $tree;
				$this->whitespace();
				if (!$this->factor($node->right)) {
					$this->syntax_error('Expected factor on right');
				}
				$this->whitespace();
				$tree = $node;
			}
			return true;
		}
		return false;
	}

	// Expression func
	// factor = meta_tag | variable | expression_func | number | string | parens
	function factor(&$tree)
	{
		$this->info('enter factor');
		if ($this->advance_ws('!')) {
			$tree = new PrefixOpNode();
			$tree->value = '!';
			// Same as below, minus string().
			$n = null;
			$this->whitespace();
			$status = $this->meta_tag($n) || $this->variable($n) || $this->expression_func($n) || $this->number($n) || $this->parens($n);
			$tree->child = $n;
			$this->info('exit factor');
			return $status;
		} else {
			$this->info('exit factor');
			$this->whitespace();
			return $this->meta_tag($tree) || $this->variable($tree) || $this->expression_func($tree) || $this->number($tree) || $this->filter_variable($tree) || $this->parens($tree);
		}
	}

	// meta_comment = '<@!' {char} '>'
	function meta_comment(&$tree) {
		if ($this->advance('<@!')) {
			$tree = new CommentNode();
			while ($this->char() !== '>') {
				$tree->value .= $this->char();
				$this->next_raw();
			}
			$this->expect('>');
			$tree->value = trim($tree->value);
			return true;
		}
		return false;
	}
	
	// '@@' variable_ident 
	function variable(&$tree)
	{
		if ($this->advance('@@')) {
			$tree = new VariableNode();
			$this->variable_ident($tree->child);
			return true;
		}
		return false;
	}
	
	// [scope '$'] ident [array_accessor]
	function variable_ident(&$tree)
	{
		$ident = strtolower($this->read_ident());
		if (strlen($ident)) {
			if ($this->advance('$')) {
				$scope = $ident;
				$name = $this->read_ident();
				if (!strlen($name)) {
					$this->syntax_error('Expected variable name after scope');
				}
			} else {
				$scope = '';
				$name = $ident;
			}
			
			$tree = new VariableIdentNode();
			$tree->name = $name;
			$tree->scope = $scope;
			$this->array_accessor($tree->array_accessor);
			return true;
		}
		return false;
	}
	
	// '[' number {',' number } ']'
	function array_accessor(&$tree)
	{
		if ($this->advance('[')) {
			$tree = new ArrayAccessorNode();

            $this->push_breakpoint([']', ',']);
            $this->whitespace();
			$this->fragment($tree->list[0]);
			$i = 1;
			while ($this->advance_ws(',')) {
				$this->whitespace();
				$this->fragment($tree->list[$i++]);
			}
			$this->pop_breakpoint();
			$this->expect_ws(']');
			return true;
		}
		return false;
	}
	
	// Expression func
	function expression_func(&$tree)
	{
		$this->info('enter expression_func');
		if ($this->advance_ws('len(')) {
			$tree = new ExpressionFuncNode();
			$tree->name = 'len';
			$this->expression($tree->child);
			$this->expect_ws(')');
			
			$this->info('exit expression_func true');
			return true;
		}
		$this->info('exit expression_func false');
		return false;
	}
	
	// Expression func
	function number(&$tree)
	{
		$this->whitespace();
		$value = $this->read_number();
		if ($value !== false) {
			$tree = new NumberNode();
			$tree->value = (int)$value;
			return true;
		}
		return false;
	}
	
	// Expression func
	// Strings are awfully similar to fragments... except:
	// - they can't handle block tags. Do they need to?
	// - they detect quoting.
	//
	// Strings cannot start with a digit.
	function string(&$tree)
	{
		$this->info('enter string');
		$this->whitespace();
		
		// Does string start with a quote?
		$quote = $this->get_inversed_quote();
		$is_quoted = (strlen($quote) && $this->advance($quote));
		$is_quoted = ($is_quoted || $this->advance($quote = '"'));
		$is_quoted = ($is_quoted || $this->advance($quote = "'"));
		
		$terminator = null;
		if ($is_quoted) {
			$terminator = $quote;
		} else {
			// Non-quoted string?
			if ($this->is_alpha($this->char())) {
				$terminator = ' ';
			}
		}

		// This is a string only if we have determined a terminator char.
		if ($terminator !== null) {
			$tree = new StringNode();
			$text = new TextNode();
			$n = null;
			
			while (true) {
				if ($terminator == ' ') {
					// Unquoted string. Break when special char/whitespace found.
					if ($this->is_whitespace() || $this->char() === '"' || $this->char() === "'" || $this->char() === '>') {
						break;
					}
				} else {
					// Quoted string. Break when ending quote found.
					if ($this->char() === $terminator) {
						break;
					}
				}

				//echo $this->char() . "\n";
				if ($this->variable($n) || $this->meta_tag($n)) {
					if (strlen($text->value)) {
						$tree->list[] = $text;
						$text = new TextNode();
					}
					$tree->list[] = $n;
				} else {
					$text->value .= $this->char();
					$this->next_raw();
				}
			}

            // If terminator is a quote it needs to be eaten. Otherwise
            // the terminator is part of another node and should be left.
            if ($terminator !== ' ') {
                $this->expect($terminator);
            }
			
			if (strlen($text->value)) {
				$tree->list[] = $text;
			}
			
			$this->info('exit string true');
			return true;
		}
		$this->info('exit string false');
		return false;
	}
	
	// Expression func
	function filter_variable(&$tree)
	{
		if ($this->advance_ws('#')) {
			$tree = new FilterVariableNode();
			$tree->name = $this->read_number();
			if (!strlen($tree->name)) {
				$this->syntax_error('Expected number');
			}
			return true;
		}
		return false;
	}
	
	// Expression func
	function parens(&$tree)
	{
		$this->info('enter parens');
		if ($this->advance_ws('(')) {
			$tree = new ParenNode();
			$this->expression($tree->child);
			$this->expect_ws(')');
			$this->info('exit parens true');
			return true;
		}
		$this->info('exit parens false');
		return false;
	}

	function read_number()
	{
		$num = false;
		while ($this->is_digit($char = $this->char())) {
			$num .= $char;
			$this->move(1);
		}
		return $num;
	}
	
	function read_ident()
	{
		$ident = '';
		while ($this->is_alpha_numeric($char = $this->char())) {
			$ident .= $char;
			$this->move(1);
		}
		return strtolower($ident);
	}
}

