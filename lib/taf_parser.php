<?php

require_once('nodes.php');
require_once('script_parser.php');


class TafParser
{
    private $xml;
    private $skipped = 0;
    private $skip_list = '';

    public function __construct($code)
    {
        $this->xml = new SimpleXMLElement($code);
    }

    public function parse()
    {
        $tree = $this->parse_list($this->xml->Program->children());

        if (strlen($this->skip_list)) {
            echo "Skipped:\n{$this->skip_list}\n";
        }

		return $tree;
    }



    function get_node($id)
    {
        $list = $this->xml->xpath("//*[@ID='$id']");
        if (!count($list)) {
            die("Action '$id' does not exist\n");
        }
        return $list[0];
    }

    /**
     * All action exist in a list. This method iterates a list of actions,
     * calls the appropriate function to handle the action, and adds the
     * resulting AST to the list.
     */
    function parse_list($node_list)
    {
        $src = '';
        $tree = new ActionNodeList();
        
        foreach ($node_list as $node) {
            $action = $this->get_node((string)$node['Ref']);
            
            //echo "Encountered '" . $action->getName() . "'\n";
            $method = 'parse_' . $action->getName();
            if (method_exists($this, $method)) {
                $tree->list[] = $this->{$method}($node, $action);
            } else {
                $this->skipped++;
                $this->skip_list .= $action->getName() . "\n";
            }
        }

        return $tree;
    }

    function get_column_info($table_str, $column_str)
    {
        $schema = 'dbo';

        $parts = array_reverse(explode('.', $table_str));
        $table = $parts[0];
        if (isset($parts[1])) {
            $schema = $parts[1];
        }

        $parts = array_reverse(explode('.', $column_str));
        $column = $parts[0];
        if (isset($parts[1])) {
            $table = $parts[1];
        }
        if (isset($parts[2])) {
            $schema = $parts[2];
        }

        return array('schema' => $schema, 'table' => $table, 'column' => $column);
    }

    function parse_IfAction($node, $action)
    {
        $tree = new IfActionNode();

		$expr = (string)$action->Expression;
		if ('(' === substr($expr, 0, 1) && ')' === substr($expr, -1, 1)) {
			$expr = substr($expr, 1, -1);
		}

        $tree->list['expr'] = ScriptParser::get_expression($expr);
        $tree->list['block'] = $this->parse_list($node->children());
        return $tree;
    }

    function parse_ElseIfAction($node, $action)
    {
        $tree = new ElseIfActionNode();

		$expr = (string)$action->Expression;
		if ('(' === substr($expr, 0, 1) && ')' === substr($expr, -1, 1)) {
			$expr = substr($expr, 1, -1);
		}

        $tree->list['expr'] = ScriptParser::get_expression($expr);
        $tree->list['block'] = $this->parse_list($node->children());
        return $tree;
    }

    function parse_ElseAction($node, $action)
    {
        $tree = new ElseActionNode();
        $tree->list['block'] = $this->parse_list($node->children());
        return $tree;
    }

    function parse_ForAction($node, $action)
    {
		$tree = new ForActionNode();
        $tree->variable = $action->LoopVariable;
        $tree->start = (string)$action->Start;
		$tree->increment = (string)$action->Increment;
        $tree->list['stop'] = ScriptParser::get_fragment((string)$action->Stop);
		$tree->list['block'] = $this->parse_list($node->children());
        
		return $tree;
    }

    function parse_AssignAction($node, $action)
    {
		// TODO: support script in variable names.
		$tree = new AssignActionNode();

        foreach ($action->AssignItem as $item) {
			$tree->list[(string)$item->Name] = ScriptParser::get_fragment((string)$item->Value);
        }

		return $tree;
    }

    function parse_PresentationAction($node, $action)
    {
		$tree = new PresentationActionNode();

        if (strlen($action->PagePath)) {
			$tree->path = $action->PagePath;
        } elseif (strlen($action->ServerPath)) {
            die("Don't know how to handle ServerPath!\n");
		} else {
			die("Unknown path type\n");
		}

		return $tree;
    }

    function parse_ReturnAction($node, $action)
    {
		return new ReturnActionNode();
    }

    function parse_ResultAction($node, $action)
    {
		$tree = new ResultsActionNode();
        $output = (string)$this->get_node((string)$action->ResultsOutput['Ref']);
        $tree->list['script'] = ScriptParser::get_fragment($output);
		return $tree;
    }

    function parse_DirectDBMSAction($node, $action)
    {
		$tree = new DirectDBMSActionNode();
		$tree->list['sql'] = ScriptParser::get_fragment((string)$action->Custom);
		$tree->start_row = (string)$action->StartRow;
		$tree->result_type = (string)$action->ResultSet['Type'];
		$tree->list['result_ident'] = ScriptParser::get_variable_ident((string)$action->ResultSet['Name']);
        return $tree;
    }

    function parse_SearchAction($node, $action)
    {
        $tree = new SearchActionNode();
        
        $tables = array();
        foreach ($action->Tables->children() as $table) {
            $tables[] = (string)$table;
        }

        $columns = array();
        foreach ($action->SearchColumns->children() as $col) {
            $columns[] = $this->get_column_info((string)$col->TableName, (string)$col->ColumnName);
        }

        $criteria = array();
        foreach ($action->Criteria->children() as $item) {
            $value = ScriptParser::get_fragment((string)$item->Value);
            $criteria[] = array(
                'conjunction' => (string)$item->Conjunction,
                'column' => $this->get_column_info((string)$item->TableName, (string)$item->ColumnName),
                'operator' => (string)$item->Operator,
                'value' => $value,
                'quotevalue' => (bool)$item->QuoteValue,
                'includeifempty' => (bool)$item->IncludeIfEmpty
            );
        }

        $tree->tables = $tables;
        $tree->columns = $columns;
        $tree->criteria = $criteria;
        $tree->output = (string)$action->ResultSet['Name'];
        return $tree;
    }

	/*
    function parse_InsertAction($node, $action)
    {
        echo "not implemented: InsertAction\n";
        return "// SQL Insert.\n";
    }

    function parse_UpdateAction($node, $action)
    {
        echo "not implemented: UpdateAction\n";
        return "// SQL Update.\n";
    }

    function parse_DeleteAction($node, $action)
    {
        echo "not implemented: DeleteAction\n";
        return "// SQL Delete.\n";
    }

    function parse_MailAction($node, $action)
    {
        echo "not implemented: MailAction\n";
        return "// Mail Action.\n";
    }
	 */

}
