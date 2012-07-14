#!/usr/bin/php
<?php

require_once('lib/witango_parser.php');
require_once('lib/php_translator.php');


class Witangone
{
	public function translate($code, $flags = array())
	{
		$w = new WitangoParser($code);
		$w->fragment($tree);
		$tree->statement = true;
		if (in_array('-t', $flags)) {
			return $tree->text();
		} elseif (in_array('-p', $flags)) {
			return print_r($tree);
		} else {
			return $this->indentify($this->toPHP($tree));
		}
	}
	
	public function toPHP($tree)
	{
		$translator = new PHPTranslator();
		return $translator->visit($tree);
	}
	
	public function expression($code)
	{
		$w = new WitangoParser($code);
		$tree = null;
		$w->expression($tree);
		return $this->toPHP($tree);
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


// Check if running as main script, allows this file to be included without
// running.
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
	if ($argc <= 1) {
		die("Source file required\n");
	}
	
	$filename = array_pop($argv);
	if (!file_exists($filename)) {
		die("Could not find file '$filename'\n");
	}
	$expr = file_get_contents($filename);
	
	$w = new Witangone();
	$w->debug = true;
	echo $w->translate($expr, $argv);
	echo "\n";
}

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
