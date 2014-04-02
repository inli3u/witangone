<?php

require_once(PATH . 'lib/nodes.php');
require_once(PATH . 'lib/ast_visitor.php');
require_once(PATH . 'lib/output_target.php');


class TafTranslator extends AstVisitor
{
    /**
     * Provides functionality to handle complex expressions. If $node
     * contains if statements or loops, this method will generate code
     * to evaluate the expression first, and return the variable
     * containing the result of the expression as the expression.
     */
    private function render_expression(ScriptNode $node, $tempVarName = '_temp')
    {
        $t = new ScriptTranslator();
        if ($node->is_complex()) {
            $before = $t->visit($node, OutputTarget::Variable(new VariableIdentNode($tempVarName)));
            $expr = '$' . $tempVarName;
        } else {
            $before = '';
            $expr = $t->visit($node, OutputTarget::Expression());
        }

        return array($before, $expr);
    }

    public function visit(ActionNode $node)
    {
        $src = '';
        if (strlen($node->comment)) {
            $src .= '// ' . $node->comment . "\n";
        }
        $src .= parent::visit($node);
        $src .= "\n";
        return $src;
    }

    public function visit_ActionNodeList(ActionNodeList $node)
    {
        $src = '';
        foreach ($node->list as $n) {
            $src .= $this->visit($n);
        }
        return $src;
    }

    public function visit_IfActionNode(IfActionNode $node)
    {
        list($before, $expr) = $this->render_expression($node->list['expr']);

        $src = $before;
        $src .= "if ($expr)\n";
        $src .= "{\n";
        $src .= $this->visit($node->list['block']);
        $src .= "}\n";
        return $src;
    }

    public function visit_ElseIfActionNode(ElseIfActionNode $node)
    {
        list($before, $expr) = $this->render_expression($node->list['expr']);

        $src = $before;
        $src .= "elseif ($expr)\n";
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
		$translator = new ScriptTranslator();

		$var = '$' . $node->variable;
		$start = $node->start;
		$inc = $node->increment;
		$stop = $translator->visit($node->list['stop'], OutputTarget::Expression());

        $src = "for ($var = $start; $var <= $stop; $var += $inc)\n";
        $src .= "{\n";
        $src .= $this->visit($node->list['block']);
        $src .= "}\n";
		return $src;
	}

	public function visit_AssignActionNode(AssignActionNode $node)
	{
		// TODO: support complex expressions in name.
		$translator = new ScriptTranslator();
		$src = '';
		foreach ($node->list as $name => $valueTree) {
			$src .= $translator->visit($valueTree, OutputTarget::Variable(new VariableIdentNode($name)));
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
		$translator = new ScriptTranslator();
		$src = $translator->visit($node->list['script'], OutputTarget::StdOut());
		return $src;
	}

	public function visit_DirectDBMSActionNode(DirectDBMSActionNode $node)
	{
		$translator = new ScriptTranslator();
		$var = $translator->visit($node->list['result_ident'], OutputTarget::Expression());
		list($before, $sql) = $this->render_expression($node->list['sql'], 'sql');
		return $before . "$var = ws_query($sql);\n"; 
	}

    public function visit_SearchActionNode(SearchActionNode $node)
    {
        $render_column = function($col) {
            if (strtolower($col['schema']) == 'dbo') {
                return $col['table'] . '.' . $col['column'];
            } else {
                return $col['schema'] . '.' . $col['table'] . '.' . $col['column'];
            }
        };

        $columns = array();
        foreach ($node->columns as $col) {
            $columns[] = $render_column($col);
        }
        $columns_str = implode(', ', $columns);
        $tables_str = implode(', ', $node->tables);

        $where = array();
        foreach ($node->criteria as $item) {
            switch ($item['operator']) {
            case 'iseq': $op = '='; break;
            case 'gthn': $op = '>'; break;
            case 'lthn': $op = '<'; break;
            case 'gteq': $op = '>='; break;
            case 'lteq': $op = '<='; break;
            case 'isin': $op = 'is in'; break;
            default: $op = $item['operator'];
            }

            $translator = new ScriptTranslator();
            $value = $translator->visit($item['value'], OutputTarget::Expression());
            if ($item['quotevalue']) {
                $value = "'" . $value . "'";
            }
            $where[] = strtoupper(trim($item['conjunction'])) . ' ' . $render_column($item['column']) . ' ' . $op . ' ' . $value;
        }

        $ws = "<@query \"SELECT $columns_str FROM $tables_str";
        if (count($where)) {
            $ws .= ' WHERE ' . implode(' ', $where);
        };
        $ws .= "\">";

        $var = '$' . $node->output;

        return "$var = ws_query(\"$sql\");\n";
    }
}

//ws_query()
//    ->select()
//    ->from()
//    ->join()
//    ->left_join()
//    ->where()
