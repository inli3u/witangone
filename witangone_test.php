<?php

require_once('witangone.php');

function wtest($in, $out)
{
	$w = new Witangone();
	$translated = trim($w->translate($in));
	if ($translated !== $out) {
		echo $translated . "\n";
		throw new Exception('FAILED TEST');
	}
}

function witangone_test()
{
	wtest('@@arg', 'echo $arg;');
	wtest('@@request$arg', 'echo $arg;');
	wtest('@@request$arg[1,2,3]', 'echo $arg[1][2][3];');
	wtest('@@cookie$arg', 'echo $_COOKIE[\'arg\'];');
	wtest('<@arg blah>', 'echo $_REQUEST[\'blah\'];');
	wtest('<@searcharg blah>', 'echo $_GET[\'blah\'];');
	wtest('<@postarg blah>', 'echo $_POST[\'blah\'];');
}


// Check if running as main script, allows this file to be included without
// running.
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
	witangone_test();
	echo "Passed\n";
}