<?php

require_once('nodes.php');


class TafParser
{
    private $xml;
    private $skipped = 0;
    private $skip_list = '';
    private $w = null;

    public function __construct($code)
    {
        $this->w = new Witangone();
        $this->xml = new SimpleXMLElement($code);
    }

    public function parse()
    {
        $this->parse_list($tree, $this->xml->Program->children());

        if (strlen($this->skip_list)) {
            echo "Skipped {$this->skip_list}\n";
        }
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

    function parse_IfAction($node, $action)
    {
        $tree = new IfActionNode();
        $tree->list['expr'] = $this->w->expression((string)$action->Expression);
        $tree->list['block'] = $this->parse_list($node->children());
        return $tree;

        $src = "if ($expr)\n";
        $src .= "{\n";
        $src .= $this->parse_list($node->children() + 1);
        $src .= "}\n";
        return $src;
    }

    function parse_ElseIfAction($node, $action)
    {
        return null;

        $ws = (string)$action->Expression;
        $expr = $this->w->expression($ws);
        $src = "elseif ($expr)\n";
        $src .= "{\n";
        $src .= $this->parse_list($node->children() + 1);
        $src .= "}\n";
        return $src;
    }

    function parse_ElseAction($node, $action)
    {
        return null;

        $src = "else\n";
        $src .= "{\n";
        $src .= $this->parse_list($node->children() + 1);
        $src .= "}\n";
        return $src;
    }

    function parse_ForAction($node, $action)
    {
        return null;

        $var = '$' . $action->LoopVariable;
        $start = (string)$action->Start;
        $stop_ws = (string)$action->Stop;
        
        // Helper function for this. Needs the CONSUMED flag.
        $w = new WitangoParser($stop_ws);
        $this->w->fragment($tree);
        $p = new PHPTranslator();
        $stop = $p->visit($tree, PHPTranslator::CONSUMED);
        
        $inc = (string)$action->Increment;
        $src = "for ($var = $start; $var <= $stop; $var += $inc)\n";
        $src .= "{\n";
        $src .= $this->parse_list($node->children() + 1);
        $src .= "}\n";
        return $src;
    }

    function parse_AssignAction($node, $action)
    {
        return null;

        $src = '';
        foreach ($action->AssignItem as $item) {
            // Helper function for this. Needs the CONSUMED flag.
            $w = new WitangoParser((string)$item->Value);
            $this->w->fragment($tree);
            $p = new PHPTranslator();
            $value = $p->visit($tree, PHPTranslator::CONSUMED);
            
            $src .= '$' . $item->Name . " = " . $value . ";\n";
        }
        return $src;
    }

    function parse_PresentationAction($node, $action)
    {
        return null;

        if (strlen($action->PagePath)) {
            return "require('" . $action->PagePath . "');\n";
        } elseif (strlen($action->ServerPath)) {
            die("Don't know how to handle ServerPath!\n");
        }
    }

    function parse_ReturnAction($node, $action)
    {
        return null;

        return "return;\n";
    }


    // TODO:

    function parse_ResultAction($node, $action)
    {
        return null;

        $output = $this->get_node((string)$action->ResultsOutput['Ref']);
        return $this->w->translate_script((string)$output);
        //return "// Result Action.\n";
    }

    function parse_DirectDBMSAction($node, $action)
    {
        echo "not implemented: DirectDBMSAction\n";
        return "// Direct SQL Query.\n";
    }

    function parse_SearchAction($node, $action)
    {
        return null;

        $tables = array();
        foreach ($action->Tables->children() as $table) {
            $tables[] = (string)$table;
        }

        $columns = array();
        foreach ($action->SearchColumns->children() as $column) {
            $columns[] = (string)$column->TableName . '.' . (string)$column->ColumnName;
        }

        $criteria = array();

        $output = '$' . $action->ResultSet['Name'];

        $columns_str = implode(', ', $columns);
        $tables_str = implode(', ', $tables);
        $sql = "SELECT $columns_str FROM $tables_str";

        // TODO: needs to support include empty == false.

        return "$output = query(\"$sql\");\n";
    }

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

}
