<?php

require_once('witango_parser.php');

function assert_true($value)
{
	if ($value !== true) {
		throw new Exception('Assert Failed');
	}
}

// Variables.
$w = new WitangoParser('@@reqest$varname');
assert_true($node = $w->variable($tree));

// Simple meta tag.
$w = new WitangoParser('<@ARG hour>');
assert_true($node = $w->meta_tag($tree));

// Complex meta tag.
$w = new WitangoParser('<@complex attr1="quoted value" attr2=value>');
assert_true($node = $w->meta_tag($tree));

// Simple expression.
$w = new WitangoParser('<@ARG hour> + 12');
assert_true($node = $w->operand($tree));

// Paren expression.
$w = new WitangoParser('(<@ARG hour> + 12)');
assert_true($node = $w->parens($tree));

// Simple comparison.
$w = new WitangoParser('<@ARG hour> = 12');
assert_true($node = $w->operand($tree));

// Multi-character comparison symbol.
$w = new WitangoParser('<@ARG hour> >= 12');
assert_true($node = $w->operand($tree));

// Paren comparison.
$w = new WitangoParser('(<@ARG hour> = 12)');
assert_true($node = $w->parens($tree));

// Custom scope assignment.
$w = new WitangoParser('<@assign name="hour" scope="local" value="12">');
assert_true($node = $w->meta_tag($tree));
assert_true($tree->name === 'hour' && $tree->scope === 'local');
?>
