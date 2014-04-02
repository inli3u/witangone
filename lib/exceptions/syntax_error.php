<?php

require_once(PATH . 'lib/exceptions/parse_error.php');

class SyntaxError extends ParseError
{
	public function __construct($src, $error = '')
	{
		parent::__construct($src, $error);
	}
}
