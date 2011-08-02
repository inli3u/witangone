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
	public function visit_FragmentNode(FragmentNode $node)
	{
		$code = array();
		foreach ($node->list as $n) {
			$result = $this->visit($n);
			if ($n instanceof MetaTagNode && strtolower($n->name) === 'calc') {
				$result = '(' . $result . ')';
			}
			$code[] = $result;
		}
		return implode(' . ', $code);
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
	
	public function visit_MetaTagNode(MetaTagNode $node)
	{
		$name = $node->name;
		$list = array();
		foreach ($node->list as $arg_name => $arg) {
			$list[$arg_name] = $this->visit($arg);
		}
		
		switch (strtolower($name)) {
		case 'calc':
			return $list['expr'];
		case 'assign':
			// This is a lame way to access things...
			if ($node->list['name'] instanceof FragmentNode) {
				// Dynamic name lookup.
				return '$_varlookup = ' . $list['name'] . ";\n" . '$$_varlookup = ' . $list['value'] . ';';
			} else {
				// Normal var.
				return $this->make_variable($node->list['name']->value, $node->list['scope']->value) . ' = ' . $list['value'];
			}
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
		return $this->make_variable($node->name, $node->scope);
	}
	
	public function visit_NumberNode(NumberNode $node)
	{
		return $node->value;
	}
	
	public function visit_TextNode(TextNode $node)
	{
		return "'" . $node->value . "'";
	}
	
	public function visit_ParenNode(ParenNode $node)
	{
		return '(' . $this->visit($node->child) . ')';
	}
	
	public function make_variable($name, $scope = 'request')
	{
		// witango vars are not case sensitive, so we must normalize the case for PHP compatability.
		$name = strtolower($name);
		$scope = strtolower($scope);
		if ($scope === 'request') {
			return '$' . $name;
		} elseif ($scope === 'user') {
			return '$_SESSION[\'' . $name . '\']';
		} elseif ($scope === 'cookie') {
			return '$_COOKIE[\'' . $name . '\']';
		} else {
			die("Witangone: Unknown variable scope '" . $scope . "'\n");
		}
	}
}

class Witangone
{
	public function translate($code, $flags = array())
	{
		$w = new WitangoParser($code);
		$w->fragment($tree);
		if (in_array('-t', $flags)) {
			return $tree->text();
		} else {
			return $this->toPHP($tree);
		}
	}
	
	public function toPHP($tree)
	{
		$translator = new PHPTranslator($tree);
		return $translator->visit($tree);
	}
}

$expr = <<<EOL
<@assign name="bob" scope="request" value="<@calc expr='5 + <@strlen value="<@substring value='ranch' start='@@request\$start' numchars='2'>">'>"
the date diff is: @@request\$bob and @@request\$name said "@@request\$msg"
EOL;

	$expr = '<@assign name="bob@@request$varname" scope="request" value="I am <@calc expr=\'5 + 2\'>">
	the next line: @@request$bob';
	//$expr = 'I am @@request$fragment variable';
	
$w = new Witangone();
echo $w->translate($expr, $argv);
echo "\n";

//$tree = null;
//$src = new WitangoParser($expr);
//$src->fragment($tree);
//print_r($tree);