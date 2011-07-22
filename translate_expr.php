<?php

$expr = <<<EOL
('<@datediff date1="<@ARG initiation>" date2="1/1/<@currentdate format='datetime:%Y'>">'<'0') and ('@@user\$adminlevel'>'-2')
EOL;

$expr = '@@user$adminlevel@@request$test';

/*
$matches = null;
var_dump(preg_match('#[a-zA-Z]+#', $expr, $matches, 0, 10));
//preg_match('#' . $pattern . '#', $this->code, $matches, 0, $this->pos)
print_r($matches);
exit;
*/


$src = new Parser($expr);

$out = try_expr($src);
echo $out . "\n";

function try_expr(Parser $src)
{
	$out = '';
	while (!$src->is_eof()) {
		if (false !== ($str = try_tag($src)) ||
			false !== ($str = try_var($src)) ||
			false !== ($str = try_parens($src)) ||
			false !== ($str = try_value($src))) {
			$out .= $str;
		} else {
			die("Source is not parsable!\n");
		}
	}
	return $out;
}

function try_parens(&$src)
{
	return false;
}

function try_value(&$src)
{
	return false;
}

function try_tag($in, $i = 0)
{
	return false;
}

function try_var(&$src)
{
	$scope = '';
	$ident = '';
	$matches = null;
	if (try_var_start($src)) {
		expect_var_scope($src, $scope);
		$src->expect('$');
		expect_var_identifier($src, $ident);
/*
	} elseif ($src->peek_preg('#[<]@ARG #i')) {
		$scope = 'get';
		expect_var_identifier($src, $ident);
*/
	} else {
		return false;
	}
	
	if ($scope == 'request') {
		return '$' . $ident;
	} elseif ($scope == 'user') {
		return '$_SESSION[\'' . $ident . '\']';
	} elseif ($scope == 'get') {
		return '$_GET[\'' . $ident . '\']';
	} else {
		throw new ParseError($src, 'Invalid variable scope specified');
	}
}

function try_var_start(&$src)
{
	return $src->peek('@@');
}

function expect_var_scope(&$src, &$scope = null)
{
	echo $src->pos . "\n";
	if ($src->peek_preg('#(user|request)#i', $matches)) {
		$scope = $matches[0];
	} else {
		throw new ParseError($src, 'Expected variable scope');
	}
}

function expect_var_identifier(&$src, &$ident = null)
{
	if ($src->peek_preg('#[a-zA-Z]+#', $matches)) {
		$ident = $matches[0];
	} else {
		throw new ParseError($src, 'Expected variable identifier');
	}
}



/*
word			abc
string			'val'

var				@@{scope}${word}

tag				{tag_open}{tag_name}{tag_attr_list}{tag_close}
tag_attr		{word}={string}
tag_attr_list   {tag_attr}{tag_attr_list}
tag_open		<@
tag_close		@>

*/


class Parser
{
	public $code = '';
	public $pos = 0;
	
	public function __construct($code)
	{
		$this->code = $code;
	}
	
	public function is_eof()
	{
		return $this->pos >= strlen($this->code);
	}
	
	public function advance($amount)
	{
		$this->pos += $amount;
	}
	
	public function peek($str)
	{
		if ($str === substr($this->code, $this->pos, strlen($str))) {
			$this->pos += strlen($str);
			return true;
		}
		return false;
	}
	
	public function peek_preg($pattern, &$matches = null)
	{
		if (preg_match($pattern, $this->code, $matches, 0, $this->pos)) {
			$this->pos += strlen(@$matches[0]);
			return true;
		}
		return false;
	}
	
	public function expect($str)
	{
		if (!$this->peek($str)) {
			throw new ParseError($this, 'Expected ' . $str);
		}
	}
	
	public function expect_preg($pattern, &$matches)
	{
		if (!$this->peek_preg($str, $matches)) {
			throw new ParseError($this, 'Expected ' . $pattern);
		}
	}
}

class ParseError extends Exception
{
	public function __construct($src, $error = '')
	{
		parent::__construct($error);
	}
}