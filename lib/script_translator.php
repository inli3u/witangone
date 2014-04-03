<?php

require_once(PATH . 'lib/nodes.php');
require_once(PATH . 'lib/ast_visitor.php');
require_once(PATH . 'lib/output_target.php');


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
		return array_pop($this->target);
	}
    
    public function get_target()
    {
        $count = count($this->target);
        if ($count) {
            return $this->target[$count - 1];
        }
    }

	public function visit(Node $node, $target = null)
	{
        $code = '';

		if ($target !== null) {
            $this->push_target($target);
        }

        if (strlen($node->comment)) {
            $code .= '// ' . $node->comment . "\n";
        }
		
		$code .= parent::visit($node);

		if ($target !== null) {
            $this->pop_target($target);
        }
		
		return $code;
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
		if ($node->value == 'contains' || $node->value == 'beginswith' || $node->value == 'endswith') {
			return 'WitangoLib::' . $node->value . '(' . $this->visit($node->left) . ', ' . $this->visit($node->right) . ')';
		} else {
			return $this->visit($node->left) . ' ' . $node->value . ' ' . $this->visit($node->right);
		}
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
		$meta_method = 'meta_' . $node->name;
		if (method_exists($this, $meta_method)) {
			// First try special case meta tags.
			$code = call_user_func(array($this, $meta_method), $node);
		} else {
			// Then try a lib function.
			$code = $this->meta_default($node);
		}

		// Handle encoding options.
		if ($node->encoding !== null) {
			$encoding = $this->visit($node->encoding, OutputTarget::Expression());
			$code = "WitangoLib::encoding({$code}, {$encoding})";
		}

		return $code;
	}

	// Handles all non-special case meta tags.
	public function meta_default($node)
	{
		$list = array();
		foreach ($node->list as $arg_name => $arg) {
			$list[$arg_name] = $this->visit($arg, OutputTarget::Expression());
		}
		
		$lib = 'WitangoLib::';
		$args = array();
		switch ($node->name) {
            case 'addrows':
                $name = $lib . 'addrows';
                $args[] = $list['array'];
                $args[] = $list['value'];
                if (array_key_exists('position', $list)) {
                    $args[] = $list['position'];
                }
                break;
			case 'appfile':
				$name = $lib . 'appfile';
				break;
			case 'array':
				$name = $lib . 'makearray';
				$args[] = strlen(@$list['rows']) ? $list['rows'] : 'null';
				$args[] = strlen(@$list['cols']) ? $list['cols'] : 'null';
				if (array_key_exists('value', $list)) { $args[] = $list['value']; }
				if (array_key_exists('cdelim', $list)) { $args[] = $list['cdelim']; }
				if (array_key_exists('rdelim', $list)) { $args[] = $list['rdelim']; }
				break;
			case 'cgi':
				$name = $lib . 'cgi';
				break;
			case 'cgiparam':
				$name = $lib . 'cgiparam';
				$args[] = $list['name'];
				break;
			case 'char':
				$name = 'chr';
				$args[] = $list['code'];
				break;
            case 'currentdate':
				$name = $lib . 'currentdate';
				$args[] = strlen(@$list['format']) ? $list['format'] : null;
				break;
            case 'currenttime':
				$name = $lib . 'currenttime';
				$args[] = strlen(@$list['format']) ? $list['format'] : null;
				break;
            case 'currenttimestamp':
				$name = $lib . 'currenttimestamp';
				$args[] = strlen(@$list['format']) ? $list['format'] : null;
				break;
			case 'datediff':
				$name = $lib . 'datediff';
				$args = array($list['date1'], $list['date2']);
				break;
			case 'httpattribute':
				$name = $lib . 'cgiparam';
				$args[] = $list['name'];
				break;
			case 'keep':
				$name = $lib . 'keep';
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
				$name = $lib . 'numrows';
				$args = strlen(@$list['array']) ? array(@$list['array']) : array();
				break;
			case 'numcols':
				$name = $lib . 'numcols';
				$args = strlen(@$list['array']) ? array(@$list['array']) : array();
				break;
			case 'omit':
				$name = $lib . 'omit';
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
				$name = $lib . 'replace';
				// TODO: support 'position' arg.
				$args = array($list['findstr'], $list['replacestr'], $list['str']);
				break;
			case 'sort':
				$name = $lib . 'sort';
				$args[] = $list['array'];
				$args[] = $list['cols'];
				break;
            case 'varinfo':
                $name = $lib . 'varinfo';
                $args[] = $list['name'];
                $args[] = $list['attribute'];
                break;
			default:
				if (ALLOW_MISSING_SYMBOLS) {
					$name = '/* UNKNOWN META TAG "' . $node->name . '" */ ' . $lib . 'unknown_meta_tag';
					$args = ["'" . $node->name . "'"];
	            } else {
	            	throw new UnknownSymbolError(null, "Unknown function '" . $node->name . "'");
	            }
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









    public function visit_UnknownActionNode(UnknownActionNode $node)
    {
        return '// UNKNOWN ACTION: ' . $node->name . "\n";
    }

    public function visit_ActionNodeList(ActionNodeList $node)
    {
        $code = '';
        foreach ($node->list as $n) {
            $result = $this->visit($n);

            // Handle extracted statements, copied from visit_FragmentListNode()
            $target = $this->get_target();
			if (!$target->is_expression() && count($this->extracted_complex_nodes)) {
				$extracted_complex_nodes = $this->extracted_complex_nodes;
				$this->extracted_complex_nodes = array();
				foreach ($extracted_complex_nodes as $item) {
					list($temp_ident_node, $complex_node) = $item;
					$complex_assignment = $this->visit($complex_node, OutputTarget::Variable($temp_ident_node));
					$code .= $complex_assignment;
				}
			}

            $code .= $result;
        }
        return $code;
    }

    public function visit_IfActionNode(IfActionNode $node)
    {
        $expr = $this->visit($node->list['expr'], OutputTarget::Expression());

        $src = "if ($expr)\n";
        $src .= "{\n";
        $src .= $this->visit($node->list['block']);
        $src .= "}\n";
        return $src;
    }

    public function visit_ElseIfActionNode(ElseIfActionNode $node)
    {
        $expr = $this->visit($node->list['expr'], OutputTarget::Expression());

        $src = "elseif ($expr)\n";
        $src .= "{\n";
        $src .= $this->visit($node->list['block']);
        $src .= "}\n";
        return $src;
    }

    public function visit_ElseActionNode(ElseActionNode $node)
    {
        $src = "else\n";
        $src .= "{\n";
        $src .= $this->visit($node->list['block']);
        $src .= "}\n";
        return $src;
    }

    public function visit_ForActionNode(ForActionNode $node)
    {
        $var = '$' . $node->variable;
        $start = $node->start;
        $inc = $node->increment;
        $stop = $this->visit($node->list['stop'], OutputTarget::Expression());

        $src = "for ($var = $start; $var <= $stop; $var += $inc)\n";
        $src .= "{\n";
        $src .= $this->visit($node->list['block']);
        $src .= "}\n";
        return $src;
    }

    public function visit_AssignActionNode(AssignActionNode $node)
    {
        // TODO: support complex expressions in name.
        $src = '';
        foreach ($node->list as $name => $valueTree) {
            $src .= $this->visit($valueTree, OutputTarget::Variable(new VariableIdentNode($name)));
        }
        return $src;
    }

    public function visit_PresentationActionNode(PresentationActionNode $node)
    {
        return "require('" . $node->path . "');\n";
    }

    public function visit_ReturnActionNode(ReturnActionNode $node)
    {
        return "return;\n";
    }

    public function visit_ResultsActionNode(ResultsActionNode $node)
    {
        $src = $this->visit($node->list['script'], OutputTarget::StdOut());
        return $src;
    }

    public function visit_DirectDBMSActionNode(DirectDBMSActionNode $node)
    {
        //die("WHAT THE HELL IS THIS TRANSLATING OF VARIABLE NAMES?!\n");

        $var = null;
        if ($node->list['result_ident'] !== null) {
        	$var = $this->visit($node->list['result_ident'], OutputTarget::Expression());
        }
        
        $sql = $this->visit($node->list['sql'], OutputTarget::Expression());
        $code = "DB::select($sql)";

        if ($var !== null) {
        	$code = "$var = toArray($code)";
        }
        return $code . ";\n"; 
    }

    public function visit_SearchActionNode(SearchActionNode $node)
    {
    	$query = $this->generate_query_body($node);
	    $query[] = "->get()";

	    $var = '$' . $node->output;
        $code = "$var = toArray(" . implode("\n", $query) . ");\n";
        return $code;
    }

    public function visit_InsertActionNode(InsertActionNode $node)
    {
		$query = $this->generate_query_body($node);
        $code = implode("\n", $query) . ";\n";
        return $code;
    }

    public function visit_UpdateActionNode(UpdateActionNode $node)
    {
    	$query = $this->generate_query_body($node);
        $code = implode("\n", $query) . ";\n";
        return $code;
    }

    public function visit_DeleteActionNode(DeleteActionNode $node)
    {
    	$query = $this->generate_query_body($node);
    	$query[] = "->delete()";

        $code = implode("\n", $query) . ";\n";
        return $code;
    }

    private function render_column($col)
    {
    	$parts = [];

        if ($col['schema'] != '' && strtolower($col['schema']) != 'dbo') {
        	$parts[] = $col['schema'];
        }
        if ($col['table'] != '') {
        	$parts[] = $col['table'];
        }
        $parts[] = $col['column'];
        return implode('.', $parts);
    }

    private function generate_query_body(QueryBuilderActionNode $node)
    {
        $query = [];

        // Begin query.
        $table = $node->tables[0];
        $query[] = "DB::table('$table')";

        if ($node->distinct) {
        	$query[] = "->distinct()";
        }

        // Joins.
        for ($i = 1; $i < count($node->tables); $i++) {
        	$join = $node->tables[$i];
        	$query[] = "->join('$join')";
        }

        // Column names.
        if (count($node->columns)) {
	        $columns = [];
	        foreach ($node->columns as $col) {
	            $columns[] = "'" . $this->render_column($col) . "'";
	        }
	        $query[] = "->select(" . implode(', ', $columns) . ")";
	    }

	    // Where clause.
	    if (count($node->criteria)) {
	        foreach ($node->criteria as $item) {
	            switch ($item['operator']) {
		            case 'iseq': $op = '='; break;
		            case 'gthn': $op = '>'; break;
		            case 'lthn': $op = '<'; break;
		            case 'gteq': $op = '>='; break;
		            case 'lteq': $op = '<='; break;
		            case 'isnt': $op = '<>'; break;
		            case 'isin': $op = 'is in'; break;
	            	default: $op = $item['operator'];
	            }

	            $column = $this->render_column($item['column']);
	            $value = $this->visit($item['value'], OutputTarget::Expression());

	            if ($op == 'is in') {
	            	$value = "array_map('trim', explode(',', $value))";
	            }

	            if (!$item['quotevalue']) {
	            	$value = 'DB::raw(' . $value . ')';
	            }
	            
	            if ($op == 'is in') {
	            	$query[] = "->whereIn('$column', '$op', $value)";
	            } else {
	            	$query[] = "->where('$column', '$op', $value)";
	        	}
	        }
	    }

	    // Values.
	    if (count($node->values)) {
	    	if ($node instanceof InsertActionNode) {
	    		$query[] = "->insert([";
	    	} elseif ($node instanceof UpdateActionNode) {
	    		$query[] = "->update([";
	    	} else {
	    		throw new Exception('Node type ' . get_class($node) . ' should not have values');
	    	}

	        foreach ($node->values as $item) {
	            $column = $item['name'];
	            $value = $this->visit($item['value'], OutputTarget::Expression());
	            if (!$item['quotevalue']) {
	            	$value = 'DB::raw(' . $value . ')';
	            }
	            
	            $query[] = "'$column' => $value,";
	        }

	        $query[count($query) - 1] .= "])";
	    }

	    if ($node->limit !== null) {
	    	$query[] = "->take({$node->limit})";
	    }

	    if ($node->offset !== null) {
	    	$query[] = "->skip({$node->offset})";
	    }

	    return $query;
    }
}

