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
            $t->set_output('var', '$_test');
            $before = $t->visit($node);
            $expr = '$_test';
        } else {
            $t->set_output('implicit');
            $before = '';
            $expr = $t->visit($node);
        }

        return array($before, $expr);
    }

    public function visit(ActionNode $node)
    {
        parent::visit($node);
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
    }
}
