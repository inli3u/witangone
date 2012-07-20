<?php

class Node
{
}

class ActionNode extends Node
{
}

class ActionNodeList extends ActionNode
{
    public $list = array();
}

class IfActionNode extends ActionNode
{
}




class ScriptNode extends Node
{
	public $parent;
	public $name;
	public $value;
	public $body = false;
	public $line = 0;
	public function __construct() {}
	public function text($level = 0)
	{
		return str_repeat("\t", $level) . get_class($this) . ' [' . $this->name . '] ' . $this->value . "\n";
	}
	
	
}

class UnaryNode extends ScriptNode
{
	public $child;
	
	public function text($level = 0)
	{
		return str_repeat("\t", $level) . get_class($this) . ' [' . $this->name . '] ' . $this->value . "\n" .
			str_repeat("\t", $level) . "- Child:\n" .
			$this->child->text($level + 1);
	}
}

class BinaryNode extends ScriptNode
{
	public $left;
	public $right;
	public function text($level = 0)
	{
		return str_repeat("\t", $level) . get_class($this) . ' [' . $this->name . '] ' . $this->value . "\n" .
			str_repeat("\t", $level) . "- Left:\n" .
			$this->left->text($level + 1) .
			(($this->right instanceof Node) ?
				str_repeat("\t", $level) . "- Right:\n" .
				$this->right->text($level + 1)
			: '');
	}
}

class ListNode extends ScriptNode
{
	public $list = array();
	
	public function insert_before($node)
	{
		if ($this->parent instanceof ListNode) {
			//$this->parent->list[$this->index]
		} else {
			throw new Exception('Not in a node list');
		}
	}
	
	public function text($level = 0)
	{
		$str = str_repeat("\t", $level) . get_class($this) . ' [' . $this->name . '] ' . $this->value . "\n";
		foreach ($this->list as $node) {
			$str .= $node->text($level + 1);
		}
		return $str;
	}
}

class NoopNode extends ScriptNode
{
}

class FragmentNode extends ListNode
{
	public $statement = false;
}
    
    
class QuotedExpressionNode extends ListNode
{
}

class ConditionalNode extends BinaryNode
{
	public $expr;
}

class ConditionNode extends BinaryNode
{
}

class OpNode extends BinaryNode
{
}

class PrefixOpNode extends UnaryNode
{
	public $value;
}

class MetaTagNode extends UnaryNode
{
	public $name;
	public $list = array();
	
	const REQUIRED = 1;
	public static $attr_defs = array(
        'addrows' => array(
            'array' => array('variable_ident', 0, self::REQUIRED),
            'value' => array('fragment', 1, self::REQUIRED),
            'position' => array('fragment', 2),
        ),
		'assign' => array(
			'name' => array('variable_ident', 0, self::REQUIRED),
			'value' => array('fragment', 1, self::REQUIRED),
			'scope' => array('fragment'),
			'expires' => array(),
			'path' => array(),
			'domain' => array(),
			'secure' => array(),
		),
		'var' => array(
			'name' => array('variable_ident', 0, self::REQUIRED),
		),
		'arg' => array(
			'name' => array('fragment', 0, self::REQUIRED),
		),
		'char' => array(
			'code' => array('fragment', 0, self::REQUIRED),
		),
		'datediff' => array(
			'date1' => array('fragment', 0, self::REQUIRED),
			'date2' => array('fragment', 1, self::REQUIRED),
		),
        'debug' => array(
        ),
		'lower' => array(
			'str' => array('fragment', 0, self::REQUIRED),
		),
		'postarg' => array(
			'name' => array('fragment', 0, self::REQUIRED),
		),
		'numrows' => array(
			'array' => array('variable_ident', 0),
		),
		'numcols' => array(
			'array' => array('variable_ident', 0),
		),
		'calc' => array(
			'expr' => array('expression', 0, self::REQUIRED),
		),
		'cgiparam' => array(
			'name' => array('fragment', 0, self::REQUIRED),
		),
		'httpattribute' => array(
			'name' => array('fragment', 0, self::REQUIRED),
		),
		'if' => array(
			'expr' => array('expression', 0, self::REQUIRED),
			'true',
			'false',
		),
		'ifequal' => array(
			'expr_left' => array('fragment', 0, self::REQUIRED),
			'expr_right' => array('fragment', 1, self::REQUIRED),
			'true',
			'false',
		),
        'ifempty' => array(
            'value' => array('fragment', 0, self::REQUIRED),
        ),
        'ifnotempty' => array(
            'value' => array('fragment', 0, self::REQUIRED),
        ),
		'elseif' => array(
			'expr' => array('expression', 0, self::REQUIRED),
		),
		'elseifempty' => array(
			'value' => array(null, 0),
		),
		'elseifnotempty' => array(
			'value' => array(null, 0),
		),
		'elseifequal' => array(
			'value1' => array(null, 0),
			'value2' => array(null, 1),
		),
		'filter' => array(
			'array' => array('variable_ident', 0, self::REQUIRED),
			'expr' => array('expression', 1, self::REQUIRED),
			'scope' => array('fragment'),
		),
		'keep' => array(
			'str' => array('fragment', 0, self::REQUIRED),
			'chars' => array('fragment', 1, self::REQUIRED),
		),
		'omit' => array(
			'str' => array('fragment', 0, self::REQUIRED),
			'chars' => array('fragment', 1, self::REQUIRED),
		),
		'random' => array(
			'high' => array('fragment', 0),
			'low' => array('fragment', 1),
		),
		'searcharg' => array(
			'name' => array('fragment', 0, self::REQUIRED),
		),
		'sort' => array(
			'array' => array('variable_ident', 0, self::REQUIRED),
			'cols' => array('fragment'),
			'scope' => array('fragment'),
		),
        'varinfo' => array(
            'name' => array('variable_ident', 0, self::REQUIRED),
            'attribute' => array('fragment', 1),
        ),
	);
	
}

class VariableNode extends UnaryNode
{
}

class VariableIdentNode extends UnaryNode
{
	public $name;
	public $scope;
	public $array_accessor;
}

class ArrayAccessorNode extends ListNode
{
	
}

class AssignmentNode extends UnaryNode
{
	public $name;
	public $scope;
}

class ExpressionFuncNode extends UnaryNode
{
	
}

class NumberNode extends ScriptNode
{
}

class StringNode extends ListNode
{
}

class FilterVariableNode extends ScriptNode
{
	public $name;
}

class ParenNode extends UnaryNode
{
}

class TextNode extends ScriptNode
{
}

class CommentNode extends ScriptNode
{
}
