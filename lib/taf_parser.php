<?php

require_once(PATH . 'lib/exceptions/unknown_symbol_error.php');
require_once(PATH . 'lib/nodes.php');
require_once(PATH . 'lib/script_parser.php');


class TafParser
{
    private $xml;



    public function __construct($code)
    {
        $this->xml = new SimpleXMLElement($code);
    }

    public function parse()
    {
        $tree = $this->parse_list($this->xml->Program->children());

		return $tree;
    }



    function get_xml_element($id)
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
    function parse_list($action_ref_list)
    {
        $src = '';
        $tree = new ActionNodeList();
        
        foreach ($action_ref_list as $action_ref) {
            $action = $this->get_xml_element((string)$action_ref['Ref']);
            
            $method = 'parse_' . $action->getName();
            if (!method_exists($this, $method)) {
                $msg = 'Unknown action: ' . $action->getName() . "\n";
                if (!ALLOW_MISSING_SYMBOLS) {
                    throw new UnknownSymbolError(null, $msg);
                } else {
                    $method = 'parse_UnknownAction';
                    Witangone::track_missing_action($action->getName());
                }
            }

            $node = $this->{$method}($action_ref, $action);
            $node->comment = 'Action: ' . (string)$action['ID'];
            $tree->list[] = $node;
        }

        return $tree;
    }

    function get_column_info($table_str, $column_str)
    {
        $schema = '';

        $parts = array_reverse(explode('.', $table_str));
        $table = $parts[0];
        if (isset($parts[1])) {
            $schema = $parts[1];
        }

        if (false !== strpos($column_str, '(') || false !== strpos($column_str, ')')) {
            // Aggregate function in use, don't try to split blindly.
            $column = $column_str;
        } else {

            $parts = array_reverse(explode('.', $column_str));
            $column = $parts[0];
            if (isset($parts[1])) {
                $table = $parts[1];
            }
            if (isset($parts[2]) && strtolower($parts[2]) != 'dbo') {
                $schema = $parts[2];
            }

        }

        return array('schema' => $schema, 'table' => $table, 'column' => $column);
    }

    function parse_UnknownAction($action_ref, $action)
    {
        return new UnknownActionNode($action->getName());
    }

    function parse_IfAction($action_ref, $action)
    {
        $tree = new IfActionNode();

		$expr = (string)$action->Expression;
		if ('(' === substr($expr, 0, 1) && ')' === substr($expr, -1, 1)) {
			$expr = substr($expr, 1, -1);
		}

        $tree->list['expr'] = ScriptParser::get_expression($expr);
        $tree->list['block'] = $this->parse_list($action_ref->children());
        return $tree;
    }

    function parse_ElseIfAction($action_ref, $action)
    {
        $tree = new ElseIfActionNode();

		$expr = (string)$action->Expression;
		if ('(' === substr($expr, 0, 1) && ')' === substr($expr, -1, 1)) {
			$expr = substr($expr, 1, -1);
		}

        $tree->list['expr'] = ScriptParser::get_expression($expr);
        $tree->list['block'] = $this->parse_list($action_ref->children());
        return $tree;
    }

    function parse_ElseAction($action_ref, $action)
    {
        $tree = new ElseActionNode();
        $tree->list['block'] = $this->parse_list($action_ref->children());
        return $tree;
    }

    function parse_ForAction($action_ref, $action)
    {
		$tree = new ForActionNode();
        $tree->variable = $action->LoopVariable;
        $tree->start = (string)$action->Start;
		$tree->increment = (string)$action->Increment;
        $tree->list['stop'] = ScriptParser::get_fragment((string)$action->Stop);
		$tree->list['block'] = $this->parse_list($action_ref->children());
        
		return $tree;
    }

    function parse_AssignAction($action_ref, $action)
    {
		// TODO: support script in variable names.
		$tree = new AssignActionNode();

        foreach ($action->AssignItem as $item) {
			$tree->list[(string)$item->Name] = ScriptParser::get_fragment((string)$item->Value);
        }

		return $tree;
    }

    function parse_PresentationAction($action_ref, $action)
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

    function parse_ReturnAction($action_ref, $action)
    {
		return new ReturnActionNode();
    }

    function parse_ResultAction($action_ref, $action)
    {
		$tree = new ResultsActionNode();
        $output = (string)$this->get_xml_element((string)$action->ResultsOutput['Ref']);
        $tree->list['script'] = ScriptParser::get_fragment($output);
		return $tree;
    }

    function parse_DirectDBMSAction($action_ref, $action)
    {
		$tree = new DirectDBMSActionNode();
		$tree->list['sql'] = ScriptParser::get_fragment((string)$action->Custom);
		$tree->start_row = (string)$action->StartRow;
		$tree->result_type = (string)$action->ResultSet['Type'];
		$tree->list['result_ident'] = ScriptParser::get_variable_ident((string)$action->ResultSet['Name']);
        return $tree;
    }

    function fill_database_action($tree, $action)
    {
        $tables = [];
        if ($action->Tables) {
            foreach ($action->Tables->children() as $table) {
                $table = (string)$table;
                if (strtolower(substr($table, 0, 4)) == 'dbo.') {
                    $table = substr($table, 4);
                }
                $tables[] = $table;
            }
        }

        // Search data dictionary for table names that might not be specified in the Tables section. Yeah.
        if ($action->DataDictionary) {
            foreach ($action->DataDictionary->children() as $col) {
                $column = $this->get_column_info((string)$col->TableName, (string)$col->ColumnName);
                // Tables can be defined here Tables section.
                if (strlen($column['table'])) {
                    if ($column['schema'] != '' && $column['schema'] != 'dbo') {
                        $tables[] = $column['schema'] . '.' . $column['table'];
                    } else {
                        $tables[] = $column['table'];
                    }
                }
            }
        }

        $columns = [];
        if ($action->SearchColumns) {
            foreach ($action->SearchColumns->children() as $col) {
                $column = $this->get_column_info((string)$col->TableName, (string)$col->ColumnName);
                $columns[] = $column;
            }
        }

        $criteria = [];
        if ($action->Criteria) {
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
        }

        $values = [];
        if ($action->ValueList) {
            foreach ($action->ValueList->children() as $item) {
                $values[] = array(
                    'name' => (string)$item->Name,
                    'value' => ScriptParser::get_fragment((string)$item->Value),
                    'quotevalue' => ($item->QuoteValue == 'true') ? true : false,
                    'includeifempty' => ($item->IncludeIfEmpty == 'true') ? true : false,
                    'nullvalue' => ($item->NullValue == 'true') ? true : false,
                );
            }
        }

        $tables = array_values(array_unique($tables));
        if (!count($tables)) {
            throw new ParseError(null, "At least one table is required for database actions. At action '" . $action['ID'] . "'.");
        }

        $tree->tables = $tables;
        $tree->columns = $columns;
        $tree->criteria = $criteria;
        $tree->values = $values;
        $tree->distinct = (strtolower($action['DistinctRows']) == "true");

        if ($action->MaxRows && strlen($action->MaxRows) > 0) {
            $tree->limit = (int)$action->MaxRows;
        }
        if ($action->StartRow && strlen($action->StartRow) > 0) {
            $offset = (int)$action->StartRow - 1;
            if ($offset > 0) {
                $tree->offset = $offset;
            }
        }
    }

    // Ignore references to dbo.
    // TODO: Add distinct, etc.
    function parse_SearchAction($action_ref, $action)
    {
        $tree = new SearchActionNode();
        $this->fill_database_action($tree, $action);
        $tree->output = (string)$action->ResultSet['Name'];
        return $tree;
    }

    /**
     * TODO: Things to support:
     * <IncludeIfEmpty>true</IncludeIfEmpty>
     * <NullValue>false</NullValue>
     */
    function parse_InsertAction($action_ref, $action)
    {
        $tree = new InsertActionNode();
        $this->fill_database_action($tree, $action);
        return $tree;
    }


    /**
     * TODO: Things to support:
     * <IncludeIfEmpty>true</IncludeIfEmpty>
     * <NullValue>false</NullValue>
     */
    function parse_UpdateAction($action_ref, $action)
    {
        $tree = new UpdateActionNode();
        $this->fill_database_action($tree, $action);
        return $tree;
    }

    function parse_DeleteAction($action_ref, $action)
    {
        $tree = new DeleteActionNode();
        $this->fill_database_action($tree, $action);
        return $tree;
    }

    /*
    function parse_MailAction($action_ref, $action)
    {
        echo "not implemented: MailAction\n";
        return "// Mail Action.\n";
    }
	 */

}
