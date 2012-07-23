<?php

class Node
{
}

class ActionNode extends Node
{
    public $list = array();
}

class ActionNodeList extends ActionNode
{
}

class IfActionNode extends ActionNode
{
}

class ElseIfActionNode extends ActionNode
{
}

class ElseActionNode extends ActionNode
{
}

class ForActionNode extends ActionNode
{
	public $variable;
	public $start;
	public $increment;
}

class AssignActionNode extends ActionNode
{
}

class PresentationActionNode extends ActionNode
{
	public $path;
}

class ReturnActionNode extends ActionNode
{
}

class ResultsActionNode extends ActionNode
{
}

class DirectDBMSActionNode extends ActionNode
{
	public $start_row;
	public $result_type;
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

	public function is_complex()
	{
		return false;
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

    public function is_complex()
    {
        return $this->child && $this->child->is_complex();
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

    public function is_complex()
    {
        return $this->left && $this->left->is_complex() || $this->right && $this->right->is_complex();
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

    public function is_complex()
    {
        foreach ($this->list as $node) {
            if ($node->is_complex()) {
                return true;
            }
        }
        return false;
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
	public $attr_defs = array(
        'addrows' => array(
            'array' => array('variable_ident', 0, self::REQUIRED),
            'value' => array('fragment', 1, self::REQUIRED),
            'position' => array('fragment', 2),
        ),
        'appfile' => array(
        ),
        'array' => array(
            'rows' => array('fragment', 0),
            'cols' => array('fragment', 1),
            'value' => array('fragment', 2),
            'cdelim' => array('fragment', 3),
            'rdelim' => array('fragment', 4),
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
        'cgi' => array(
        ),
		'cgiparam' => array(
			'name' => array('fragment', 0, self::REQUIRED),
		),
		'httpattribute' => array(
			'name' => array('fragment', 0, self::REQUIRED),
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
        'substring' => array(
            'str' => array('fragment', 0, self::REQUIRED),
            'start' => array('fragment', 1, self::REQUIRED),
            'numchars' => array('fragment', 2, self::REQUIRED),
        ),
        'varinfo' => array(
            'name' => array('variable_ident', 0, self::REQUIRED),
            'attribute' => array('fragment', 1),
        ),
	);

	public function handles_tag($tag_name)
	{
		return array_key_exists($tag_name, $this->attr_defs);
	}
	
}

class BlockMetaTagNode extends MetaTagNode
{
	public $attr_defs = array(
        'debug' => array(
        ),
		'if' => array(
			'expr' => array('expression', 0, self::REQUIRED),
			'true' => array('fragment', 1),
			'false' => array('fragment', 2),
		),
		'ifequal' => array(
			'value1' => array('fragment', 0, self::REQUIRED),
			'value2' => array('fragment', 1, self::REQUIRED),
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
			'value' => array('fragment', 0, self::REQUIRED),
		),
		'elseifnotempty' => array(
			'value' => array('fragment', 0, self::REQUIRED),
		),
		'elseifequal' => array(
			'value1' => array('fragment', 0, self::REQUIRED),
			'value2' => array('fragment', 1, self::REQUIRED),
		),
        'else' => array(
        ),
	);

    public function is_complex()
    {
        return true;
    }
}

class VariableNode extends UnaryNode
{
}

class VariableIdentNode extends UnaryNode
{
	public $name;
	public $scope;
	public $array_accessor;

	public function __construct($name = '', $scope = 'request')
	{
		$this->name = $name;
		$this->scope = $scope;
	}
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
