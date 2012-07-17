<?php

require_once('nodes.php');


$xml = null;
$skipped = 0;
$skip_list = '';
$w = new Witangone();


function translate_taf($code)
{
    global $xml, $skipped, $skip_list;

    $xml = new SimpleXMLElement($code);

    $tree = null;
    taf_list($tree, $xml->Program->children());

    if (strlen($skip_list)) {
        echo "Skipped $skip_list\n";
    }

    return $tree;
}



function get_indent($level)
{
	return str_repeat('    ', $level);
}

function get_node($id)
{
	global $xml;
	$list = $xml->xpath("//*[@ID='$id']");
	if (!count($list)) {
		die("Action '$id' does not exist\n");
	}
	return $list[0];
}

function taf_list($node_list, $level = 0)
{
	global $skipped;
	global $skip_list;
	
	$src = '';
	foreach ($node_list as $node) {
		$action = get_node((string)$node['Ref']);
		
		//echo "Encountered '" . $action->getName() . "'\n";
		$xlate = 'taf_' . $action->getName();
		if (function_exists($xlate)) {
			$src .= call_user_func($xlate, $node, $action, $level);
		} else {
			$skipped++;
			$skip_list .= $action->getName() . "\n";
		}
	}
	return $src;
}

function taf_IfAction($node, $action, $level)
{
	global $w;
	$ws = (string)$action->Expression;
	$expr = $w->expression($ws);
	$src = get_indent($level) . "if ($expr)\n";
	$src .= get_indent($level) . "{\n";
	$src .= taf_list($node->children(), $level + 1);
	$src .= get_indent($level) . "}\n";
	return $src;
}

function taf_ElseIfAction($node, $action, $level)
{
	global $w;
	$ws = (string)$action->Expression;
	$expr = $w->expression($ws);
	$src = get_indent($level) . "elseif ($expr)\n";
	$src .= get_indent($level) . "{\n";
	$src .= taf_list($node->children(), $level + 1);
	$src .= get_indent($level) . "}\n";
	return $src;
}

function taf_ElseAction($node, $action, $level)
{
	$src = get_indent($level) . "else\n";
	$src .= get_indent($level) . "{\n";
	$src .= taf_list($node->children(), $level + 1);
	$src .= get_indent($level) . "}\n";
	return $src;
}

function taf_ForAction($node, $action, $level)
{
	$var = '$' . $action->LoopVariable;
	$start = (string)$action->Start;
	$stop_ws = (string)$action->Stop;
	
	// Helper function for this. Needs the CONSUMED flag.
	$w = new WitangoParser($stop_ws);
	$w->fragment($tree);
	$p = new PHPTranslator();
	$stop = $p->visit($tree, PHPTranslator::CONSUMED);
	
	$inc = (string)$action->Increment;
	$src = get_indent($level) . "for ($var = $start; $var <= $stop; $var += $inc)\n";
	$src .= get_indent($level) . "{\n";
	$src .= taf_list($node->children(), $level + 1);
	$src .= get_indent($level) . "}\n";
	return $src;
}

function taf_AssignAction($node, $action, $level)
{
	$src = '';
	foreach ($action->AssignItem as $item) {
		// Helper function for this. Needs the CONSUMED flag.
		$w = new WitangoParser((string)$item->Value);
		$w->fragment($tree);
		$p = new PHPTranslator();
		$value = $p->visit($tree, PHPTranslator::CONSUMED);
		
		$src .= get_indent($level) . '$' . $item->Name . " = " . $value . ";\n";
	}
	return $src;
}

function taf_PresentationAction($node, $action, $level)
{
	if (strlen($action->PagePath)) {
		return get_indent($level) . "require('" . $action->PagePath . "');\n";
	} elseif (strlen($action->ServerPath)) {
		die("Don't know how to handle ServerPath!\n");
	}
}

function taf_ReturnAction($node, $action, $level)
{
	return get_indent($level) . "return;\n";
}


// TODO:

function taf_ResultAction($node, $action, $level)
{
	global $w;
	$output = get_node((string)$action->ResultsOutput['Ref']);
	return $w->translate_script((string)$output);
	//return get_indent($level) . "// Result Action.\n";
}

function taf_DirectDBMSAction($node, $action, $level)
{
    echo "not implemented: DirectDBMSAction\n";
	return get_indent($level) . "// Direct SQL Query.\n";
}

function taf_SearchAction($node, $action, $level)
{
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

	return get_indent($level) . "$output = query(\"$sql\");\n";
}

function taf_InsertAction($node, $action, $level)
{
    echo "not implemented: InsertAction\n";
	return get_indent($level) . "// SQL Insert.\n";
}

function taf_UpdateAction($node, $action, $level)
{
    echo "not implemented: UpdateAction\n";
	return get_indent($level) . "// SQL Update.\n";
}

function taf_DeleteAction($node, $action, $level)
{
    echo "not implemented: DeleteAction\n";
	return get_indent($level) . "// SQL Delete.\n";
}

function taf_MailAction($node, $action, $level)
{
    echo "not implemented: MailAction\n";
	return get_indent($level) . "// Mail Action.\n";
}


