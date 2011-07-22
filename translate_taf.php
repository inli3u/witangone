<?php

$file = @$argv[1];
$out_file = @$argv[2];

if (!strlen($file)) {
	die("Usage: translate file\n");
}

$file_contents = file_get_contents($file);
$xml = new SimpleXMLElement($file_contents);

$skipped = 0;
$skip_list = '';

// Translate.
$src = "<?php\n\n" . taf_list($xml->Program->children());


$src .= "\n/*\n";
$src .= "Skipped $skipped actions:\n";
$src .= $skip_list;
$src .= "*/\n";

if (strlen($out_file)) {
	file_put_contents($out_file, $src);
} else {
	echo $src;
}

exit;

function get_indent($level)
{
	return str_repeat('    ', $level);
}

function get_action($id)
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
		$action = get_action((string)$node['Ref']);
		
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
	$expr = (string)$action->Expression;
	$src = get_indent($level) . "if ($expr)\n";
	$src .= get_indent($level) . "{\n";
	$src .= taf_list($node->children(), $level + 1);
	$src .= get_indent($level) . "}\n";
	return $src;
}

function taf_ElseIfAction($node, $action, $level)
{
	$expr = (string)$action->Expression;
	$src = get_indent($level) . "elseif ($expr)\n";
	$src .= get_indent($level) . "{\n";
	$src .= taf_list($node->children(), $level + 1);
	$src .= get_indent($level) . "}\n";
	return $src;
}

function taf_ElseAction($node, $action, $level)
{
	$expr = (string)$action->Expression;
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
	$stop = (string)$action->Stop;
	$inc = (string)$action->Increment;
	$src = get_indent($level) . "for ($var = $start; $var <= '$stop'; $var += $inc)\n";
	$src .= get_indent($level) . "{\n";
	$src .= taf_list($node->children(), $level + 1);
	$src .= get_indent($level) . "}\n";
	return $src;
}

function taf_AssignAction($node, $action, $level)
{
	$src = '';
	foreach ($action->AssignItem as $item) {
		$src .= get_indent($level) . '$' . $item->Name . " = '" . $item->Value . "';\n";
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
	return get_indent($level) . "// Result Action.\n";
}

function taf_DirectDBMSAction($node, $action, $level)
{
	return get_indent($level) . "// Direct SQL Query.\n";
}

function taf_SearchAction($node, $action, $level)
{
	return get_indent($level) . "// SQL Query.\n";
}

function taf_InsertAction($node, $action, $level)
{
	return get_indent($level) . "// SQL Insert.\n";
}

function taf_UpdateAction($node, $action, $level)
{
	return get_indent($level) . "// SQL Update.\n";
}

function taf_DeleteAction($node, $action, $level)
{
	return get_indent($level) . "// SQL Delete.\n";
}

function taf_MailAction($node, $action, $level)
{
	return get_indent($level) . "// Mail Action.\n";
}


