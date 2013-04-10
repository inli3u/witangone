<?php

require_once('nodes.php');
require_once('ast_visitor.php');
require_once('output_target.php');


/*
visit_MetaTagNode($node)
{
	$result = parent::visit_MetaTagNode($node);
	$result->childNode
}

public function meta_ifempty($node)
{
	$result = parent::meta_ifempty($node);
	return "if ($result->valueNode == null)\n{\n$result->childNode}\n";
}

public function visit_MetaTagNode($node)
{
	$result = parent::meta_ifempty($node);
	foreach ($result->argNodes as $n) {
		if 
	}
}

At the fragment level:
option 1 - throw excpetion, rethrow until output is not Expression, exception contains complex node.
$node->extract_complex_expression
*/


class NestedComplexNodeError extends exception
{
	public function __construct($node)
	{
		parent::__construct();
		$this->node = $node;
	}
}


class ScriptTranslator extends AstVisitor
{
	private $target = array();
	private $extracted_complex_nodes = array();


	public function push_target(OutputTarget $target)
	{
		array_push($this->target, $target);
	}

	public function pop_target()
	{
		array_pop($this->target);
	}
    
    public function get_target()
    {
        $count = count($this->target);
        if ($count) {
            return $this->target[$count - 1];
        }
    }

	public function visit(ScriptNode $node, $target = null)
	{
		if ($target !== null) {
            $this->push_target($target);
        }
		
		$result = parent::visit($node);

		if ($target !== null) {
            $this->pop_target($target);
        }
		
		return $result;
	}

	public function format_for_output($translated_code, ScriptNode $node)
	{
		$target = $this->get_target();
		$result = '';

		if ($node->is_complex()) {

			// Complex statements (if, while, etc) don't need any extra formatting.
			// However if being passed as an argument they need to be assigned to a temp variable.

			if ($target->is_expression()) {
				//throw new NestedComplexNodeError($node);
			}

			$result = $translated_code;


		} else {

			// Non-complex statements need to be echoed, assigned to a variable, or passed as an argument.

			if ($target->is_stdout()) {
                $result = "echo $translated_code;\n";
            } elseif ($target->is_variable()) {
                $variable = $this->make_variable($target->variable_ident);
                if ($target->hits === 0) {
                    $result = "$variable = $translated_code;\n";
                } else {
                    $result = "$variable .= $translated_code;\n";
                }
                $target->hits++;
            } else {
            	$result = $translated_code;
            }
		}

		return $result;
	}

	public function visit_FragmentNode(FragmentNode $node)
	{
		$code = array();
        $target = $this->get_target();
		
		foreach ($node->list as $i => $child_node) {

			if ($target->is_expression() && $child_node->is_complex()) {

				if ('else' == substr($child_node->name, 0, 4)) {
					// Else statements should use same temp var name as previous if statement
					list($temp_ident_node) = $this->extracted_complex_nodes[count($this->extracted_complex_nodes) - 1];
					$replacement_node = new NoopNode();
				} else {
					// Non-else statements should make a new name.
					$temp_ident_node = new VariableIdentNode('temp' . (count($this->extracted_complex_nodes) + 1));
					$replacement_node = new VariableNode();
					$replacement_node->child = $temp_ident_node;
				}
				
				$this->extracted_complex_nodes[] = array($temp_ident_node, $child_node);

				$child_node = $replacement_node;
				$node->list[$i] = $child_node;
			}

			// Generate code from node.
			$result = $this->visit($child_node);
			if ($result === false) {
				// Translation resulted in no output, possibly because of a noop.
				continue;
			}

			//$result = $target->format_statement($result, $child_node);
			$result = $this->format_for_output($result, $child_node);

			if (!$target->is_expression() && count($this->extracted_complex_nodes)) {
				$extracted_complex_nodes = $this->extracted_complex_nodes;
				$this->extracted_complex_nodes = array();
				foreach ($extracted_complex_nodes as $item) {
					list($temp_ident_node, $complex_node) = $item;
					$complex_assignment = $this->visit($complex_node, OutputTarget::Variable($temp_ident_node));
					$code[] = $complex_assignment;
				}
			}

			$code[] = $result;
		}
        
        // Sibling statements must be joined
		if ($target->is_expression()) {
			// Concatenate if passing these as an argument
			return implode(' . ', $code);
		} else {
			// Otherwise these are separate lines of code, just join.
			return implode('', $code);
		}
	}
	
	public function visit_QuotedExpressionNode(QuotedExpressionNode $node)
	{
		if (count($node->list)) {
			return $this->visit($node->list[0]);
		} else {
			return 'null';
		}
	}
	
	public function visit_OpNode(OpNode $node)
	{
		return $this->visit($node->left) . ' ' . $node->value . ' ' . $this->visit($node->right);
	}
	
	public function visit_PrefixOpNode(PrefixOpNode $node)
	{
		return $node->value . $this->visit($node->child);
	}


	public function visit_BlockMetaTagNode(BlockMetaTagNode $node)
	{
		// First try special case meta tags.
		$meta_method = 'meta_' . $node->name;
		if (method_exists($this, $meta_method)) {
			return call_user_func(array($this, $meta_method), $node);
		}
	}

	public function visit_MetaTagNode(MetaTagNode $node)
	{
		// First try special case meta tags.
		$meta_method = 'meta_' . $node->name;
		if (method_exists($this, $meta_method)) {
			return call_user_func(array($this, $meta_method), $node);
		}

		// Then try a lib function.
		$list = array();
		foreach ($node->list as $arg_name => $arg) {
			$list[$arg_name] = $this->visit($arg, OutputTarget::Expression());
		}
		
		$args = array();
		switch ($node->name) {
            case 'addrows':
                $name = 'ws_addrows';
                $args[] = $list['array'];
                $args[] = $list['value'];
                if (array_key_exists('position', $list)) {
                    $args[] = $list['position'];
                }
                break;
			case 'appfile':
				$name = 'ws_appfile';
				break;
			case 'array':
				$name = 'ws_array';
				$args[] = strlen(@$list['rows']) ? $list['rows'] : 'null';
				$args[] = strlen(@$list['cols']) ? $list['cols'] : 'null';
				if (array_key_exists('value', $list)) { $args[] = $list['value']; }
				if (array_key_exists('cdelim', $list)) { $args[] = $list['cdelim']; }
				if (array_key_exists('rdelim', $list)) { $args[] = $list['rdelim']; }
				break;
			case 'cgi':
				$name = 'ws_cgi';
				break;
			case 'cgiparam':
				$name = 'ws_cgiparam';
				$args[] = $list['name'];
				break;
			case 'char':
				$name = 'chr';
				$args[] = $list['code'];
				break;
            case 'currentdate':
				$name = 'ws_currentdate';
				$args[] = strlen(@$list['format']) ? $list['format'] : null;
				break;
            case 'currenttime':
				$name = 'ws_currenttime';
				$args[] = strlen(@$list['format']) ? $list['format'] : null;
				break;
            case 'currenttimestamp':
				$name = 'ws_currenttimestamp';
				$args[] = strlen(@$list['format']) ? $list['format'] : null;
				break;
			case 'datediff':
				$name = 'ws_datediff';
				$args = array($list['date1'], $list['date2']);
				break;
			case 'httpattribute':
				$name = 'ws_cgiparam';
				$args[] = $list['name'];
				break;
			case 'keep':
				$name = 'ws_keep';
				$args[] = $list['str'];
				$args[] = $list['chars'];
				break;
			case 'left':
				$name = 'substr';
				$args[] = $list['str'];
                $args[] = 0;
				$args[] = $list['numchars'];
				break;
			case 'lower':
				$name = 'strtolower';
				$args[] = $list['str'];
				break;
			case 'numrows':
				$name = 'ws_numrows';
				$args = strlen(@$list['array']) ? array(@$list['array']) : array();
				break;
			case 'numcols':
				$name = 'ws_numcols';
				$args = strlen(@$list['array']) ? array(@$list['array']) : array();
				break;
			case 'omit':
				$name = 'ws_omit';
				$args[] = $list['str'];
				$args[] = $list['chars'];
				break;
			case 'substring':
				$name = 'substr';
				$args = array($list['str'], $list['start'], $list['numchars']);
				break;
			case 'random':
				$name = 'rand';
				$args[] = strlen(@$list['low']) ? $list['low'] : 0;
				$args[] = strlen(@$list['high']) ? $list['high'] : 32767;
				break;
			case 'replace':
				$name = 'str_replace';
				// TODO: support 'position' arg.
				$args = array($list['findstr'], $list['replacestr'], $list['str']);
				break;
			case 'sort':
				$name = 'ws_sort';
				$args[] = $list['array'];
				$args[] = $list['cols'];
				break;
            case 'varinfo':
                $name = 'ws_varinfo';
                $args[] = $list['name'];
                $args[] = $list['attribute'];
                break;
			default:
				$name = '// UNKNOWN FUNCTION "' . $node->name . '"';
                echo 'Unknown function: ' . $node->name . "\n";
		}
		
		return $name . '(' . implode(', ', $args) . ')';
	}
	
	public function meta_if($node)
	{
		$expr = $this->visit($node->list['expr'], OutputTarget::Expression());
		$block = $this->visit($node->child);
		return "if ($expr)\n{\n$block}\n";
	}
	
	public function meta_ifequal($node)
	{
		$left = $this->visit($node->list['value1'], OutputTarget::Expression());
		$right = $this->visit($node->list['value2'], OutputTarget::Expression());
		$block = $this->visit($node->child);
		return "if ($left == $right)\n{\n$block}\n";
	}

    public function meta_ifempty($node)
    {
        $value = $this->visit($node->list['value'], OutputTarget::Expression());
		$block = $this->visit($node->child);
		return "if ($value == null)\n{\n$block}\n";
    }

    public function meta_ifnotempty($node)
    {
        $value = $this->visit($node->list['value'], OutputTarget::Expression());
		$block = $this->visit($node->child);
		return "if ($value != null)\n{\n$block}\n";
    }
	
	public function meta_elseif($node)
	{
		$expr = $this->visit($node->list['expr'], OutputTarget::Expression());
		$block = $this->visit($node->child);
		return "elseif ($expr)\n{\n$block}\n";
	}
	
	public function meta_else($node)
	{
		$block = $this->visit($node->child);
		return "else\n{\n$block}\n";
	}

    public function meta_debug($node)
    {
        // TODO: support this.
		return 'null';
    }
	
	public function meta_arg($node)
	{
		$ident = new VariableIdentNode();
		$ident->name = $node->list['name']->list[0]->value;
		$ident->scope = 'arg';
		return $this->make_variable($ident);
	}
	
	public function meta_searcharg($node)
	{
		$ident = new VariableIdentNode();
		$ident->name = $node->list['name']->list[0]->value;
		$ident->scope = 'searcharg';
		return $this->make_variable($ident);
	}
	
	public function meta_postarg($node)
	{
		$ident = new VariableIdentNode();
		$ident->name = $node->list['name']->list[0]->value;
		$ident->scope = 'postarg';
		return $this->make_variable($ident);
	}

	public function meta_calc($node)
	{
		return $this->visit($node->list['expr']);
	}
	
	public function meta_filter($node)
	{
		$expr = $this->visit($node->list['expr']);
		$array = $this->visit($node->list['array']);
		$src = "array_filter($array, function(\$row) { return $expr; })";
		return $src;
	}
	
	public function meta_var($node)
	{
		$ident = $node->list['name'];
		if (!strlen($ident->scope)) {
			// Get scope from var. Check behavoir of witango on which one to use
			// if both are provided.
			$ident->scope = @$node->list['scope']->list[0]->value;
		}
		return $this->make_variable($ident);
	}
	
	public function meta_assign($node)
	{
		$ident = $node->list['name'];
		if (!strlen($ident->scope)) {
			$ident->scope = @$node->list['scope']->list[0]->value;
		}
		$result = $this->visit($node->list['value'], OutputTarget::Variable($ident));
		return $result;
	}

	public function visit_VariableNode(VariableNode $node)
	{
		return $this->make_variable($node->child);
	}
	
	public function visit_VariableIdentNode(VariableIdentNode $node)
	{
		return $this->make_variable($node);
	}
	
	public function visit_ExpressionFuncNode(ExpressionFuncNode $node)
	{
		$func = 'strlen';
		switch ($node->name) {
			case 'len':
				return 'strlen(' . $this->visit($node->child, OutputTarget::Expression()) . ')';
			default:
				throw new Expection('Unknown expression function "' . $node->name . '"');
		}
	}
	
	public function visit_NumberNode(NumberNode $node)
	{
		return $node->value;
	}
	
	public function visit_StringNode(StringNode $node)
	{
		$n = new FragmentNode();
		$n->list = $node->list;
		return $this->visit($n, OutputTarget::Expression());
	}

    public function visit_FilterVariableNode(FilterVariableNode $node)
    {
        return '$row[' . ($node->name - 1) . ']';
    }
	
	public function visit_TextNode(TextNode $node)
	{
		return "'" . str_replace(array('\\', "'"), array('\\\\', "\\'"), $node->value) . "'";
	}
	
	public function visit_ParenNode(ParenNode $node)
	{
		return '(' . $this->visit($node->child) . ')';
	}
	
	public function make_variable(VariableIdentNode $node)
	{
		// witango vars are not case sensitive, so we must normalize the case for PHP compatability.
		$name = strtolower($node->name);
		$scope = strtolower($node->scope);
		
		$src = '';
		if ($scope === 'request') {
			$src = '$' . $name;
		} elseif ($scope === 'arg') {
			$src = '$_REQUEST[\'' . $name . '\']';
		} elseif ($scope === 'searcharg') {
			$src = '$_GET[\'' . $name . '\']';
		} elseif ($scope === 'postarg') {
			$src = '$_POST[\'' . $name . '\']';
		} elseif ($scope === 'user') {
			$src = '$_SESSION[\'' . $name . '\']';
		} elseif ($scope === 'cookie') {
			$src = '$_COOKIE[\'' . $name . '\']';
		} else {
			$src = '$' . $name;
			//die("Witangone: Unknown variable scope '" . $scope . "'\n");
		}
		
		if ($node->array_accessor) {
			foreach ($node->array_accessor->list as $value) {
				$value_str = $this->visit($value);
				if (is_numeric($value_str)) {
					$src .= '[' . ($value_str - 1) . ']';
				} else {
					$src .= '[' . $value_str . ' - 1]';
				}
			}
		}
		
		return $src;
	}
}

