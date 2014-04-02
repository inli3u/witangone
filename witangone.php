#!/usr/bin/php
<?php

define('PATH', dirname(__FILE__) . '/');
define('ALLOW_MISSING_SYMBOLS', true);

require_once(PATH . 'lib/taf_parser.php');
require_once(PATH . 'lib/script_parser.php');
//require_once('lib/taf_translator.php');
require_once(PATH . 'lib/script_translator.php');


class Witangone
{
	public static $missing_actions = [];
	public static $missing_meta_tags = [];

	public static function track_missing_action($name)
	{
		if (!isset(self::$missing_actions[$name])) {
			self::$missing_actions[$name] = 0;
		}

		self::$missing_actions[$name]++;
	}

	public static function track_missing_meta_tag($name)
	{
		if (!isset(self::$missing_meta_tags[$name])) {
			self::$missing_meta_tags[$name] = 0;
		}

		self::$missing_meta_tags[$name]++;
	}

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
		return $this->get_prepend_code() . $this->prettify($translator->visit($tree, OutputTarget::StdOut())); 
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
			return $this->get_prepend_code() . $this->prettify($translator->visit($tree, OutputTarget::StdOut()));
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
				$first = substr($line, 0, 1);
				$last = substr($line, -1);

				if ($first === '}') {
					$level -= 1;
				}
				
				// Line continuation: function chaining.
				if (substr($line, 0, 2) === '->') {
					$line_level_adjustment = 1;
				}

				$line = str_repeat("\t", $level + $line_level_adjustment) . $line;
				
				if ($last === '{') {
					$level++;
				}
			}
			$out[] = $line;
		}
		
		return implode("\n", $out);
	}

	private function get_prepend_code()
	{
		static $code = null;
		if ($code === null) {
			$code = file_get_contents(PATH . 'template/file/prepend.php');
		}
		return $code;
	}
}

class WitangoneCli
{
	public static function run($argc, $argv)
	{
		if ($argc <= 1) {
			die("Available commands: init, compile\n");
		}

		array_shift($argv);
		$command = strtolower(array_shift($argv));

		switch ($command) {
			case 'init': self::init($argv); break;
			case 'compile': self::compile($argv); break;
			default: die("Unknown command '$command'\n");
		}
	}

	/**
	 * init: Create project in currect working directory.
	 */
	private static function init($argv)
	{
		$path = getcwd();
		if (self::pathHasFiles($path)) {
			die("Path is not empty. No action taken.\n");
		}

		foreach (glob(PATH . 'template/project/*') as $file) {
			copy($file, $path . '/' . basename($file));
		}

		echo "Project initialized. Run 'composer install' to load dependencies.\n";
	}

	/**
	 * compile: Translate target file/directory and place output in current working directory.
	 */
	private static function compile($argv)
	{
		$destPath = getcwd();
		$destTree = [];
		$source = array_shift($argv);
		$sourcePath = '';
		$sourceFiles = [];

		if (!file_exists($source)) {
			die("Could not find path '$taget'\n");
		}

		if (is_file($source)) {
			$sourcePath = dirname($source);
			$sourceFiles[] = basename($source);
		} elseif (is_dir($source)) {
			$sourcePath = $source;
			$all = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source));
			foreach ($all as $file) {
				$basename = $file->getFileName();
				if ($basename[0] == '.') {
					continue;
				}
				$dotpos = strrpos($basename, '.');
				if ($dotpos === false) {
					continue;
				}
				$ext = strtolower(substr($basename, $dotpos + 1));
				if ($ext === 'taf' || $ext === 'tml' || $ext === 'html') {
					// Store file path to compile.
					$relativePath = substr($file->getPathName(), strlen($sourcePath) + 1);
					$sourceFiles[] = $relativePath;
					// Store unique dir names.
					$destTree[dirname($relativePath)] = true;
				}
			}
		} else {
			die("Not a valid path '$target'\n");
		}

		if (!count($sourceFiles)) {
			die("Could not find any files to compile\n");
		}

		// Build dir structure
		foreach (array_keys($destTree) as $dir) {
			if ($dir == '.' || $dir == '..') {
				continue;
			}

			$dir = $destPath . '/' . $dir;
			if (!is_dir($dir)) {
				mkdir($dir);
			}
		}

		// Compile.
		$total = 0;
		$succeeded = 0;
		$failed = 0;
		$w = new Witangone();
		$w->debug = true;
		foreach ($sourceFiles as $file) {
			$path = pathinfo($file);
			switch ($path['extension']) {
				case 'taf':
					$destExt = 'php';
					break;
				case 'tml':
				case 'html':
					$destExt = 'html.php';
					break;
				default:
					die("Unsupported file extension: '$path[extension]'\n");
			}

			try {
				$src = $w->translate_file($sourcePath . '/' . $file, $argv);
				file_put_contents($destPath . '/' . $path['dirname'] . '/' . $path['filename'] . '.' . $destExt, $src);
				$succeeded++;
			} catch (ParseError $e) {
				echo $file . ': ' . get_class($e) . ' - ' . $e->getMessage() . "\n";
				$failed++;
			}
			$total++;
		}

		echo "\n";
		echo "Compiled: $succeeded\n";
		echo "Failed: $failed\n";

		if (ALLOW_MISSING_SYMBOLS) {
			if (count(Witangone::$missing_actions)) {
				echo "\n";
				echo "Unsupported Actions:\n";
				$actions = Witangone::$missing_actions;
				asort($actions);
				foreach (array_reverse($actions) as $name => $times) {
					echo $name . "\t\t" . $times . " times\n";
				}
			}

			if (count(Witangone::$missing_meta_tags)) {
				echo "\n";
				echo "Unsupported Meta Tags:\n";
				$tags = Witangone::$missing_meta_tags;
				asort($tags);
				foreach (array_reverse($tags) as $name => $times) {
					echo $name . "\t\t" . $times . " times\n";
				}
			}
		}
	}

	private static function pathHasFiles($path)
	{
		if (!is_dir($path)) {
			throw new Exception("Path is not a directory: '$path'");
		}

		$d = dir($path);
		while (false !== ($entry = $d->read())) {
   			if ($entry !== '.' && $entry !== '..') {
   				return true;
   			}
		}
		return false;
	}
}


// Check if running as main script, allows this file to be included without
// running.
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
	WitangoneCli::run($argc, $argv);
}

