<?php

class AstVisitor
{
	public function visit(Node $node, $target = null)
	{
		$method = 'visit_' . get_class($node);
		if (method_exists($this, $method)) {
			//echo "calling $method\n";
			return call_user_func(array($this, $method), $node);
		} else {
            //throw new Exception('Visitor method not found "' . $method . '"');
            return false;
		}
	}
}
