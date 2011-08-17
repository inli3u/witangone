<?php

require_once('witango_parser.php');

class NodeVisitor
{
	public function visit($node)
	{
		$method = 'visit_' . get_class($node);
		if (method_exists($this, $method)) {
			echo "calling $method\n";
			return call_user_func(array($this, $method), $node);
		} else {
			return false;
		}
	}
}

class PHPTranslator extends NodeVisitor
{
	const CONSUMED = 1;
	private $is_consumed = false;
	private $depth = 0;
	private $indent_level = 0;
	private $target = array();
	

	
	public function visit($node, $flags = 0)
	{
		// The stack of consumed states is maintained in this recursive call stack.
		$last_consumed = $this->is_consumed;
		if ($flags === self::CONSUMED) {
			$this->is_consumed = true;
		}
		
		$result = parent::visit($node);

		$this->is_consumed = $last_consumed;
		
		return $result;
	}

	public function is_expression($node)
	{
		return !$this->is_statement($node) && !$this->is_control($node);
	}

	public function is_statement($node)
	{
		return $node instanceof AssignmentNode;
	}

	public function is_control($node)
	{
		return $node instanceof ConditionalNode;
	}
	
	public function is_consumed()
	{
		return $this->is_consumed;
	}

	public function visit_FragmentNode(FragmentNode $node)
	{
		$code = array();
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

			if (true || !$this->is_consumed) {
				if ($this->is_expression($n)) {
					$target_index = count($this->target) - 1;
					$target = @$this->target[$target_index];
					$target_str = '';
					if (strlen($target['name'])) {
						print_r($target);
						$target_str = $this->make_variable($target['name'], $target['scope']);
						if ($target['hits'] === 0) {
							$target_str .= ' = ';
						} else {
							$target_str .= ' .= ';
						}
						$this->target[$target_index]['hits']++;
					} else {
						$target_str = 'echo ';
					}
					$result = $target_str . $result . ";\n";
				} elseif ($this->is_statement($n)) {
					$result .= ";\n";
				} elseif ($this->is_control($n)) {
					// Do nothing.
				} else {
					throw new Exception('Unknown statement type');
				}
			} else {
				// Do nothing.
			}
			$code[] = $result;
		}
		if (false && $this->is_consumed) {
			// concat ' . '
			return implode(' . ', $code);
		} else {
			$indent = str_repeat("\t", $this->indent_level);
			return $indent . implode($indent, $code);
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
		
	public function visit_MetaTagNode(MetaTagNode $node)
	{
		$name = $node->name;
		$list = array();
		foreach ($node->list as $arg_name => $arg) {
			$list[$arg_name] = $this->visit($arg);
		}
		
		switch (strtolower($name)) {
			case 'arg':
				$keys = array_keys($list);
				$name = @$keys[0];
				// TODO: support name="".
				if (!strlen($name)) {
					throw new Exception('Expecting <@arg> name');
				}
				return $this->make_variable($name, 'arg');
			case 'calc':
				return $list['expr'];
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

	public function visit_AssignmentNode(AssignmentNode $node)
	{
		//if ($node->name instanceof FragmentNode) {
		//	// Dynamic name lookup.
		//	return '$_varlookup = ' . $this->visit($node->name) . ";\n" . '$$_varlookup = ' . $this->visit($node->value) . ';';
		//}
		if (true) {
			// Set output to a var name. Fragment visitor handles assignment.
			// Need to check for previous assignement, initialize if not been.
			// - first assignment, "=", after that, ".=".
			// Does witango allow nested assignment?
			array_push($this->target, array('name' => $node->name, 'scope' => $node->scope, 'hits' => 0));
			$result = $this->visit($node->child, self::CONSUMED);
			array_pop($this->target);
			return $result;
		} else {
			return $this->make_variable($node->name, $node->scope) . ' = ' . $this->visit($node->child, self::CONSUMED);
		}
	}

	public function visit_ConditionalNode(ConditionalNode $node)
	{
		$expr = $this->visit($node->expr, self::CONSUMED);
		$left = $this->visit($node->left);
		$right = $this->visit($node->right);
		
		if (false && $this->is_consumed()) {
			$code = '(' . $expr . ') ? ' . $left . ' : ' . $right;
			return $code;
		} else {
			$code = 'if (' . $expr . ") {\n" . $left . '}';
			if ($node->right) {
				$code .= " else {\n" . $right . '}';
			}
			return $code . "\n";
		}
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
		} elseif ($scope === 'arg') {
			return '$_REQUEST[\'' . $name . '\']';
		} elseif ($scope === 'searcharg') {
			return '$_GET[\'' . $name . '\']';
		} elseif ($scope === 'postarg') {
			return '$_POST[\'' . $name . '\']';
		} elseif ($scope === 'user') {
			return '$_SESSION[\'' . $name . '\']';
		} elseif ($scope === 'cookie') {
			return '$_COOKIE[\'' . $name . '\']';
		} else {
			return '$' . $name;
			//die("Witangone: Unknown variable scope '" . $scope . "'\n");
		}
	}
}

class Witangone
{
	public function translate($code, $flags = array())
	{
		$w = new WitangoParser($code);
		$w->fragment($tree);
		$tree->statement = true;
		if (in_array('-t', $flags)) {
			return $tree->text();
		} else {
			return $this->indentify($this->toPHP($tree));
		}
	}
	
	public function toPHP($tree)
	{
		$translator = new PHPTranslator($tree);
		return $translator->visit($tree);
	}
	
	public function indentify($code)
	{
		$in = explode("\n", $code);
		$out = array();
		$level = 0;
		
		foreach ($in as $line) {
			$line = trim($line);
			if (strlen($line)) {
				if (substr($line, 0, 1) === '}') {
					$level -= 1;
				}
				
				$line = str_repeat("\t", $level) . $line;
				
				if (substr($line, -1) === '{') {
					$level++;
				}
			}
			$out[] = $line;
		}
		
		return implode("\n", $out);
	}
}

$expr = <<<EOL
<@assign name="bob" scope="request" value="<@calc expr='5 + <@strlen value="<@substring value='ranch' start='@@request\$start' numchars='2'>">'>"
the date diff is: @@request\$bob and @@request\$name said "@@request\$msg"
EOL;

$expr = '<@assign name="bob@@request$varname" scope="request" value="I am <@calc expr=\'5 + 2\'>">
	the next line: @@requst$bob';
	//$expr = 'I am @@request$fragment variable';

$expr = '
<@if expr="5 + 1 > 2">
	<@assign name="test" scope="request" value="smoke up <@if expr=\'1 = 2\'>@@request$bitches<@else>bob</@if>">
<@else>
	false text @@request$name is sloppy
</@if>
value: @@request$test 
some random text
that happens
across lines';

// From assembly.taf
$expr = <<<EOL
<@if expr="(<@ARG hour> = 12) and (<@ARG ampm> = 12)"> <@! if noon>
<@assign name="hour" scope="local" value="12">
<@elseif expr="(<@ARG hour> = 12) and (<@ARG ampm> = 0)"> <@! if midnight>
<@assign name="hour" scope="local" value="0">
<@else> <@! if anything else>
<@assign name="hour" scope="local" value="<@calc expr='<@ARG hour> + <@ARG ampm>'>">
</@if>
EOL;

$w = new Witangone();
echo "<?php\n" . $w->translate($expr, $argv) . "\n?>";
echo "\n";

/*
 * For Any given fragment, determine if each element is a statement or expression.
 * statements are: statement;
 * expressions are: echo expression;
 * advanced - join adjacent expressions: echo expression . expression;
 */

//$tree = null;
//$src = new WitangoParser($expr);
//$src->fragment($tree);
//print_r($tree);
