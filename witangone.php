#!/usr/bin/php
<?php

require_once('lib/witango_parser.php');
require_once('lib/php_translator.php');
require_once('lib/translate_taf.php');


class Witangone
{
    public function translate_file($filename, $flags = array())
    {
        $path = pathinfo($filename);
        switch ($path['extension']) {
            case 'taf':
                return translate_taf(file_get_contents($filename), $flags);
            case 'tml':
            case 'html':
                return translate_script(file_get_contents($filename), $flags);
        }
    }

    public function translate_taf($code, $flags = array())
    {
        return $this->prettify(translate_taf($code)); 
    }

	public function translate_script($code, $flags = array())
	{
		$w = new WitangoParser($code);
		$w->fragment($tree);
		$tree->statement = true;
		if (in_array('-t', $flags)) {
			return $tree->text();
		} elseif (in_array('-p', $flags)) {
			return print_r($tree);
		} else {
			return $this->prettify($this->to_php($tree));
		}
	}
	
	public function to_php($tree)
	{
		$translator = new PHPTranslator();
		return $translator->visit($tree);
	}
	
	public function expression($code)
	{
		$w = new WitangoParser($code);
		$tree = null;
		$w->expression($tree);
		return $this->to_php($tree);
	}
	
	public function prettify($code)
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
	
	$w = new Witangone();
	$w->debug = true;
	$src = $w->translate_file($filename, $argv);
    $path = pathinfo($filename);
    file_put_contents($path['dirname'] . '/' . $path['filename'] . '.php', $src);
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
