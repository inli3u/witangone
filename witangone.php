<?php

require_once('witango_parser.php');

class NodeVisitor
{
	public function visit($node)
	{
		$method = 'visit_' . get_class($node);
		if (method_exists($this, $method)) {
			return call_user_func(array($this, $method), $node);
		}
	}
}

class PHPTranslator extends NodeVisitor
{
	public function visit_QuotedExpressionNode(QuotedExpressionNode $node)
	{
		return $this->visit($node->list[0]);
	}
	
	public function visit_OpNode(OpNode $node)
	{
		return $this->visit($node->left) . ' ' . $node->value . ' ' . $this->visit($node->right);
	}
	
	public function visit_MetaTagNode(MetaTagNode $node)
	{
		$name = $node->name;
		$list = array();
		foreach ($node->attr_list as $arg_name => $arg) {
			$list[$arg_name] = $this->visit($arg);
		}
		
		switch (strtolower($name)) {
		case 'substring':
			$name = 'substr';
			$list = array($list['value'], $list['start'], $list['numchars']);
			break;
		case 'replace':
			$name = 'str_replace';
			// TODO: support 'position' arg.
			$list = array($list['findstr'], $list['replacestr'], $list['str']);
			break;
		}
		
		return $name . '(' . implode(', ', $list) . ')';
	}
	
	public function visit_VariableNode(VariableNode $node)
	{
		if ($node->scope === 'request') {
			return '$' . $node->name;
		} elseif ($node->scope === 'user') {
			return '$_SESSION[\'' . $node->name . '\']';
		}
	}
	
	public function visit_NumberNode(NumberNode $node)
	{
		return $node->value;
	}
	
	public function visit_ParenNode(ParenNode $node)
	{
		return '(' . $this->visit($node->child) . ')';
	}
}

class Witangone
{
	public function translate($code)
	{
		$w = new WitangoParser($code);
		$w->expression($tree);
		//$this->prepare($tree);
		return $this->toPHP($tree);
	}
	
	public function toPHP($tree)
	{
		$translator = new PHPTranslator($tree);
		return $translator->visit($tree);
	}
}

$w = new Witangone();
echo $w->translate('(3 + <@replace str="@@request$text" findstr="5" replacestr="1">) * 7');
echo "\n";