<?php

class ParseError extends Exception
{
	public function __construct($src, $error = '')
	{
		parent::__construct($error);
	}
}
