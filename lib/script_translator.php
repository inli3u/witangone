<?php

require_once('nodes.php');
require_once('ast_visitor.php');


class OutputTarget
{
    const EXPRESSION = 1;
	const STDOUT = 2;
	const VARIABLE = 3;
    
    public $type = null;
    public $hits = 0;
    public $variable_ident = null;
    
    
    public static function StdOut()
    {
        $target = new OutputTarget();
        $target->type = self::STDOUT;
        return $target;
    }
    
    public static function Expression()
    {
        $target = new OutputTarget();
        $target->type = self::EXPRESSION;
        return $target;
    }
    
    public static function Variable($variable_ident)
    {
        $target = new OutputTarget();
        $target->type = self::VARIABLE;
        $target->variable_ident = $variable_ident;
        return $target;
    }
    
    public function is_stdout()
    {
        return $this->type === self::STDOUT;
    }
    
    public function is_expression()
    {
        return $this->type === self::EXPRESSION;
    }
    
    public function is_variable()
    {
        return $this->type === self::VARIABLE;
    }
}


class ScriptTranslator extends AstVisitor
{
	const CONSUMED = 1;
	const EXPRESSION = 1;
	const STDOUT = 2;
	const VARIABLE = 3;
	private $is_consumed = false;
	private $depth = 0;
	private $indent_level = 0;
	private $target = array();


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

	public function is_expression($node)
	{
		return !$this->is_control($node);
	}

	public function is_control($node)
	{
		return $node instanceof MetaTagNode && in_array($node->name, array('assign', 'if', 'ifequal', 'ifempty', 'ifnotempty', 'elseif', 'else'));
	}
	
	public function is_consumed()
	{
        $target = $this->get_target();
		return $target->is_expression();
	}

	public function visit_FragmentNode(FragmentNode $node)
	{
		$code = array();
		
        $target = $this->get_target();
		$target_str = '';
				
		foreach ($node->list as $n) {
			$result = $this->visit($n);
			if ($result === false) {
				continue;
			}
			// Each node will be translated to a single expression or statement.

			// TODO: Make CalculationNode...
			//if ($n instanceof MetaTagNode && strtolower($n->name) === 'calc') {
			//	$result = '(' . $result . ')';
			//}

			if ($this->is_expression($n)) {
				if (!$target->is_expression()) {
					if ($target->is_variable()) {
						$target_str = $this->make_variable($target->variable_ident);
						if ($target->hits === 0) {
							$target_str .= ' = ';
						} else {
							$target_str .= ' .= ';
						}
						// Update hits.
						$target->hits++;
					} else {
						$target_str = 'echo ';
					}
				}
				$result = $target_str . $result;
				$result .= $this->is_consumed() || $target->is_expression() ? '' : ";\n";
			} elseif ($this->is_control($n)) {
				// Do nothing.
			} else {
				throw new Exception('Unknown statement type');
			}

			$code[] = $result;
		}
		if ($target->is_expression()) {
			// concat ' . '
			// 
			return implode(' . ', $code);
		} else {
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
		//$this->push_output(self::VARIABLE, $ident);
		$result = $this->visit($node->list['value'], OutputTarget::Variable($ident));
		//$this->pop_output();
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

