<?php

require_once('parse_error.php');

class GenericParser
{
	public $debug = false;
	public $code = '';
	public $pos = 0;
	private $rewind_pos = 0;
	
	public function __construct($code)
	{
		//preg_match_all('#(?:@@|<@|</@|<=|>=|!=|.)#', $code, $list);
		$this->code = $code;
		$this->pos = -1;
		$this->next_raw();
	}
	
	public function is_eof()
	{
		return $this->pos >= strlen($this->code);
	}
	
	function next_raw()
	{
		return $this->char = @$this->code[++$this->pos];
	}

	function next($distance = 1)
	{
		$i = 0;
		// Advance given distance, plus past any ending whitespace.
		while ($i++ < $distance || false !== @strpos(" \t\r\n", $this->code[$this->pos])) {
			$this->pos++;
		}
		return $this->char = @$this->code[$this->pos];
	}

	function look()
	{
		$pos = $this->pos;
		while (false !== strpos(" \t\r\n", @$this->code[++$pos])) {}
		return $this->code[$pos];
	}

	function look_raw()
	{
		return @$this->code[$this->pos + 1];
	}

	function is_digit($char)
	{
		return $char >= '0' && $char <= '9';
	}

	function is_alpha($char)
	{
		return ($char >= 'a' && $char <= 'z') || ($char >= 'A' && $char <= 'Z') || $char == '_';
	}

	function is_alpha_numeric($char)
	{
		return $this->is_alpha($char) || $this->is_digit($char);
	}
	
	public function peek($str)
	{
		$len = strlen($str);
		if (0 === strcasecmp($str, substr($this->code, $this->pos, $len))) {
			$this->next($len);
			return true;
		}
		return false;
	}
	
	public function expect($str)
	{
		if (!$this->peek($str)) {
			$this->error('Expected ' . $str);
		}
	}
	
	public function set_rewind_point()
	{
		$this->rewind_pos = $this->pos;
	}
	
	public function rewind()
	{
		$this->pos = $this->rewind_pos;
		$this->char = @$this->code[$this->pos];
	}
	
	public function debug($msg)
	{
		if ($this->debug) {
			echo $msg . "\n";
		}
	}
	
	public function info($msg)
	{
		if ($this->debug) {
			echo $msg . "\n";
		}
	}
	
	public function error($msg)
	{
        $code_so_far = substr($this->code, 0, $this->pos + 1);
        $lines = explode("\n", $code_so_far);
		$line = count($lines);
        $char = $this->pos - strrpos($code_so_far, "\n");
		
		throw new ParseError($this, $msg . ' on line ' . $line . ', char ' . $char . ': ' . $lines[count($lines) - 1]);
	}
}
