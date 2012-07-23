<?php

require_once('nodes.php');
require_once('ast_visitor.php');


class TafTranslator extends AstVisitor
{
    /**
     * Provides functionality to handle complex expressions. If $node
     * contains if statements or loops, this method will generate code
     * to evaluate the expression first, and return the variable
     * containing the result of the expression as the expression.
     */
    private function render_expression(ScriptNode $node)
    {
        $t = new ScriptTranslator();
        if ($node->is_complex()) {
            $t->push_output(ScriptTranslator::VARIABLE, new VariableIdentNode('_temp'));
            $before = $t->visit($node);
            $expr = '$_temp';
			$t->pop_output();
        } else {
            $t->push_output(ScriptTranslator::EXPRESSION);
            $before = '';
            $expr = $t->visit($node);
			$t->pop_output();
        }

        return array($before, $expr);
    }

    public function visit(ActionNode $node)
    {
        return parent::visit($node);
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
		$translator->push_output(ScriptTranslator::EXPRESSION);
		$stop = $translator->visit($node->list['stop']);
		$translator->pop_output();

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
			$translator->push_output(ScriptTranslator::VARIABLE, new VariableIdentNode($name));
			$src .= $translator->visit($valueTree);
			$translator->pop_output();
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
		$translator->push_output(ScriptTranslator::STDOUT);
		$src = $translator->visit($node->list['script']);
		$translator->pop_output();
		return $src;
	}

	public function visit_DirectDBMSActionNode(DirectDBMSActionNode $node)
	{
		$translator = new ScriptTranslator();
		$translator->push_output(ScriptTranslator::EXPRESSION);
		$var = $translator->visit($node->list['result_ident']);
		list($before, $sql) = $this->render_expression($node->list['sql']);
		$translator->pop_output();
		return $before . "$var = ws_query($sql);\n"; 
	}
}
