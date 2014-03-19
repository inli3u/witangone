#!/usr/bin/php
<?php

require_once('lib/taf_parser.php');
require_once('lib/script_parser.php');
//require_once('lib/taf_translator.php');
require_once('lib/script_translator.php');


class Witangone
{
    public function translate_file($filename, $flags = array())
    {
        $path = pathinfo($filename);
        switch ($path['extension']) {
            case 'taf':
                return $this->translate_taf(file_get_contents($filename), $flags);
            case 'tml':
            case 'html':
                return $this->translate_script(file_get_contents($filename), $flags);
        }
    }

    public function translate_taf($code, $flags = array())
    {
        $parser = new TafParser($code);
        $tree = $parser->parse();
        $translator = new ScriptTranslator();
		return $this->get_bootstrap_code() . $this->prettify($translator->visit($tree, OutputTarget::StdOut())); 
    }

	public function translate_script($code, $flags = array())
	{
		$parser = new ScriptParser($code);
		$parser->fragment($tree);
		$tree->statement = true;

		if (in_array('-t', $flags)) {
			return $tree->text();
		} elseif (in_array('-p', $flags)) {
			return print_r($tree);
		} else {
            $translator = new ScriptTranslator();
			return $this->get_bootstrap_code() . $this->prettify($translator->visit($tree, OutputTarget::StdOut()));
		}
	}
	
	/**
	 * Indents PHP source code.
	 */
	public function prettify($code)
	{
		$in = explode("\n", $code);
		$out = array();
		$level = 0;
		
		foreach ($in as $line) {
			$line = trim($line);
			$line_level_adjustment = 0;

			if (strlen($line)) {
				if (substr($line, 0, 1) === '}') {
					$level -= 1;
				}
				
				// Support for one style of line continuation.
				if (substr($line, 0, 2) === '->') {
					$line_level_adjustment = 1;
				}

				$line = str_repeat("\t", $level + $line_level_adjustment) . $line;
				
				if (substr($line, -1) === '{') {
					$level++;
				}
			}
			$out[] = $line;
		}
		
		return implode("\n", $out);
	}

	private function get_bootstrap_code()
	{
		static $code = null;
		if ($code === null) {
			$code = file_get_contents('template/bootstrap-loader.php');
		}
		return $code;
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

