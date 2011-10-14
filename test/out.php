<?php

if ('1' == '12') {
	if (ws_cgiparam('CLIENT_IP') == '000.000.000.000') {
		$debugmode = 'forceOn';
	}
}
if ((!strlen($_REQUEST['do'])))
{
	// SQL Query.
	// SQL Query.
	// SQL Query.
	// Direct SQL Query.
	for ($i = 1; $i <= ws_numrows($ending); $i += 1)
	{
		// SQL Query.
	}
	echo ws_sort($honorroll, '4 desc');
	require('assembly_main.html');
}
elseif (($_REQUEST['do'] == 'newmember'))
{
	if (($_SESSION['adminassemid'] == '-2'))
	{
		if ((!strlen($_REQUEST['assembly_id'])))
		{
			require('assembly_add_select.html');
			return;
		}
		else
		{
			$assembly_id_num = $_REQUEST['assembly_id'];
		}
	}
	else
	{
		$assembly_id_num = $_SESSION['assembly'];
	}
	// SQL Query.
	// SQL Query.
	// SQL Query.
	require('assembly_add_test.html');
}
elseif (($_REQUEST['do'] == 'insert'))
{
	$password = strtolower(substr($_POST['firstname'], '1', '1')) . strtolower(substr($_POST['lastname'], '1', '1')) . rand('1111', '9999');
	$username = strtolower(substr($_POST['firstname'], '1', '1')) . strtolower($_POST['lastname']);
	$username = ws_keep($username, 'abcdefghijklmnopqrstuvwxyz');
	// SQL Insert.
	// SQL Query.
	// SQL Insert.
	// SQL Insert.
	if (($_POST['sendinfo'] == '1'))
	{
		if ((strlen($_POST['email'])))
		{
			// Mail Action.
		}
	}
	// Direct SQL Query.
	// Direct SQL Query.
	// SQL Query.
	if ((ws_numrows($jml) > '0'))
	{
		// Mail Action.
	}
	$message = 'Member was successfully added.<p><a href="' . ws_cgi() . ws_appfile() . '">Return to Menu</a>';
	require('message.html');
}
elseif (($_REQUEST['do'] == 'status'))
{
	// SQL Query.
	for ($i = 1; $i <= ws_numrows($matches); $i += 1)
	{
		// SQL Query.
		// SQL Query.
		// SQL Query.
		$thisrow = $girl[1][3];
		$thisrow .= chr('28');
		$thisrow .= $girl[1][2];
		$thisrow .= chr('28');
		$thisrow .= $girl[1][4];
		$thisrow .= chr('28');
		$thisrow .= $girl[1][1];
		$thisrow .= chr('28');
		$thisrow .= $code[1][1];
		$thisrow .= chr('30');
		echo // UNKNOWN FUNCTION "addrows"();
	}
	$members = // UNKNOWN FUNCTION "filter"();
	echo ws_sort($members, '1,2');
	echo // UNKNOWN FUNCTION "debug"();
	echo $members;
	// SQL Query.
	require('assembly_status.html');
}
elseif (($_REQUEST['do'] == 'updatestatus'))
{
	if (($_SESSION['adminlevel'] > '-2') && (ws_datediff($_REQUEST['eventdate'], '1/1/2010') < '0'))
	{
		$message = 'You are not allowed to add or edit events that have an event date before 1/1/2010 as this will affect Annual Report submissions.';
		require('message.html');
	}
	else
	{
		// SQL Insert.
		// SQL Update.
		$message = 'Status was successfully updated.
		' . // UNKNOWN FUNCTION "ifnotempty"() . '<p><a href="annrepsubmit.taf?do=2&year=' . $_REQUEST['from'] . '">Return to Annual Report</a></p>';
		require('message.html');
	}
}
elseif (($_REQUEST['do'] == 'statuspast'))
{
	// SQL Query.
	for ($i = 1; $i <= ws_numrows($matches); $i += 1)
	{
		// SQL Query.
		// SQL Query.
		// SQL Query.
		$thisrow = $girl[1][3];
		$thisrow .= chr('28');
		$thisrow .= $girl[1][2];
		$thisrow .= chr('28');
		$thisrow .= $girl[1][4];
		$thisrow .= chr('28');
		$thisrow .= $girl[1][1];
		$thisrow .= chr('28');
		$thisrow .= $code[1][1];
		$thisrow .= chr('30');
		echo // UNKNOWN FUNCTION "addrows"();
	}
	$members = // UNKNOWN FUNCTION "filter"();
	echo ws_sort($members, '1,2');
	// SQL Query.
	require('assembly_status.html');
}
elseif (($_REQUEST['do'] == 'affiliate'))
{
	require('assembly_affiliate.html');
}
elseif (($_REQUEST['do'] == 'affiliation'))
{
	// SQL Insert.
	// SQL Insert.
	$message = 'Member was successfully affiliated.<p><a href="' . ws_cgi() . ws_appfile() . '">Return to Menu</a>';
	require('message.html');
}
elseif (($_REQUEST['do'] == 'addevent'))
{
	require('assembly_addevent.html');
}
elseif (($_REQUEST['do'] == 'eventinsert'))
{
	if (($_REQUEST['hour'] == 12) && ($_REQUEST['ampm'] == 12)) {
		$hour = '12';
	}
	elseif (($_REQUEST['hour'] == 12) && ($_REQUEST['ampm'] == 0)) {
		$hour = '0';
	}
	else {
		$hour = $_REQUEST['hour'] + $_REQUEST['ampm'];
	}
	// SQL Insert.
	$message = 'Event was successfully added.<p><a href="' . ws_cgi() . ws_appfile() . '">Return to Menu</a>';
	require('message.html');
}
elseif (($_REQUEST['do'] == 'password'))
{
	if (($_SESSION['adminlevel'] > '-1'))
	{
		$message = 'You are not authorized to change your password. Only Supreme Inspectors can change passwords for Mother Advisors.<p><a href="' . ws_cgi() . ws_appfile() . '">Return to Menu</a>';
		require('message.html');
	}
	else
	{
		require('assembly_password.html');
	}
}
elseif (($_REQUEST['do'] == 'changepassword'))
{
	if (($_POST['pw1'] == $_POST['pw2']))
	{
		// SQL Update.
		$message = 'Your password was successfully updated.<p><a href="' . ws_cgi() . ws_appfile() . '">Return to Menu</a>';
		require('message.html');
	}
	else
	{
		$message = 'The passwords you entered did not match. Please click the back button on your browser and try again.<p><a href="' . ws_cgi() . ws_appfile() . '">Return to Menu</a>';
		require('message.html');
	}
}
elseif (($_REQUEST['do'] == 'eventview'))
{
	// SQL Query.
	echo '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
	<html>
	<head>
	<meta http-equiv="content-type" content="text/html;charset=iso-8859-1">
	<title>Lorem Ipsum</title>
	<style type="text/css" media="all">
	<!--
	@import url("../css/style.css");
	-->
	</style>
	</head>

	<body style="background:white; text-align:left; padding:20px">
	<span class="subheadBlue">';
	echo $event[1][2];
	echo '</span>
	<p>Date and Time: ';
	echo $event[1][1];
	echo '</p>
	<p>';
	echo $event[1][3];
	echo '</p>
	<p><a href="';
	echo ws_cgi();
	echo ws_appfile();
	echo '?do=editevent&eventID=';
	echo $_REQUEST['eventid'];
	echo '">Edit This Event</a></p>
	<p align="right"><a href="#" onclick="window.close()">Close Window</a></p>
	</body>
	</html>';
}
elseif (($_REQUEST['do'] == 'editevent'))
{
	// SQL Query.
	echo '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
	<html>
	<head>
	<meta http-equiv="content-type" content="text/html;charset=iso-8859-1">
	<title>Lorem Ipsum</title>
	<style type="text/css" media="all">
	<!--
	@import url("../css/style.css");
	-->
	</style>
	<script type="text/javascript" src="../css/CalendarPopup.js" language="JavaScript"></script>
	<SCRIPT type="text/javascript" LANGUAGE="JavaScript">document.write(getCalendarStyles());</SCRIPT>
	<SCRIPT type="text/javascript" LANGUAGE="JavaScript" ID="todaycal">
	var todaycal = new CalendarPopup();
	todaycal.showYearNavigation();
	</SCRIPT>
	</head>

	<body style="background:white; text-align:left; padding:20px">
	<form action="';
	echo ws_cgi();
	echo ws_appfile();
	echo '?do=updateevent" method="post" name="addevent" id="addevent">
	<table width="100%" border="0" cellspacing="0" cellpadding="2">
	<tr>
	<td width="17%" align="right">Name:</td>
	<td width="83%"><input name="eventName" type="text" id="eventName" size="40" value="';
	echo $event[1][2];
	echo '" /></td>
	</tr>
	<tr>
	<td align="right" valign="top">Description:</td>
	<td><textarea name="eventDesc" id="eventDesc" cols="38" rows="5">';
	echo $event[1][3];
	echo '</textarea></td>
	</tr>
	<tr>
	<td align="right">Date:</td>
	<td><input name="eventDate" type="text" id="eventDate" size="15" value="';
	echo $event[1][1];
	echo '">
	<A HREF="#" onClick="todaycal.select(document.forms['addevent'].eventDate,'anchor1xx','MM/dd/yyyy'); return false;" NAME="anchor1xx" ID="anchor1xx"><img src="../images/show-calendar.gif" alt="Pop Calendar" align="absmiddle" border="0"></a></td>
	</tr>
	<tr>
	<td align="right">Time:</td>
	<td><select name="hour" size="1">
	<option value="12" ';
	if ($event[1][1] == '12') {
		echo 'selected';
	}
	echo '>12</option>
	<option value="01" ';
	if ($event[1][1] == '1') {
		echo 'selected';
	}
	echo '>1</option>
	<option value="02" ';
	if ($event[1][1] == '2') {
		echo 'selected';
	}
	echo '>2</option>
	<option value="03" ';
	if ($event[1][1] == '3') {
		echo 'selected';
	}
	echo '>3</option>
	<option value="04" ';
	if ($event[1][1] == '4') {
		echo 'selected';
	}
	echo '>4</option>
	<option value="05" ';
	if ($event[1][1] == '5') {
		echo 'selected';
	}
	echo '>5</option>
	<option value="06" ';
	if ($event[1][1] == '6') {
		echo 'selected';
	}
	echo '>6</option>
	<option value="07" ';
	if ($event[1][1] == '7') {
		echo 'selected';
	}
	echo '>7</option>
	<option value="08" ';
	if ($event[1][1] == '8') {
		echo 'selected';
	}
	echo '>8</option>
	<option value="09" ';
	if ($event[1][1] == '9') {
		echo 'selected';
	}
	echo '>9</option>
	<option value="10" ';
	if ($event[1][1] == '10') {
		echo 'selected';
	}
	echo '>10</option>
	<option value="11" ';
	if ($event[1][1] == '11') {
		echo 'selected';
	}
	echo '>11</option>
	</select>
	:
	<select name="minute" size="1">
	<option value="00" ';
	if ($event[1][1] == '00') {
		echo 'selected';
	}
	echo '>00</option>
	<option value="15" ';
	if ($event[1][1] == '15') {
		echo 'selected';
	}
	echo '>15</option>
	<option value="30" ';
	if ($event[1][1] == '30') {
		echo 'selected';
	}
	echo '>30</option>
	<option value="45" ';
	if ($event[1][1] == '45') {
		echo 'selected';
	}
	echo '>45</option>
	</select>
	<select name="ampm" size="1">
	<option value="0" ';
	if ($event[1][1] == 'AM') {
		echo 'selected';
	}
	echo '>a.m.</option>
	<option value="12" ';
	if ($event[1][1] == 'PM') {
		echo 'selected';
	}
	echo '>p.m.</option>
	</select>
	</td>
	</tr>
	<tr>
	<td align="right">&nbsp;</td>
	<td><input type="hidden" name="eventID" value="';
	echo $event[1][4];
	echo '"><input type="submit" name="button" id="button" value="Update Event" /></td>
	</tr>
	</table>
	<p align="right"><a href="#" onclick="window.close()">Close Window</a></p>
	</form>
	</body>
	</html>';
}
elseif (($_REQUEST['do'] == 'updateevent'))
{
	if (($_REQUEST['hour'] == 12) && ($_REQUEST['ampm'] == 12)) {
		$hour = '12';
	}
	elseif (($_REQUEST['hour'] == 12) && ($_REQUEST['ampm'] == 0)) {
		$hour = '0';
	}
	else {
		$hour = $_REQUEST['hour'] + $_REQUEST['ampm'];
	}
	// SQL Update.
	// SQL Query.
	echo '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
	<html>
	<head>
	<meta http-equiv="content-type" content="text/html;charset=iso-8859-1">
	<title>Lorem Ipsum</title>
	<style type="text/css" media="all">
	<!--
	@import url("../css/style.css");
	-->
	</style>
	</head>

	<body style="background:white; text-align:left; padding:20px">
	<span class="subheadBlue">';
	echo $event[1][2];
	echo '</span>
	<p>Date and Time: ';
	echo $event[1][1];
	echo '</p>
	<p>';
	echo $event[1][3];
	echo '</p>
	<p><a href="';
	echo ws_cgi();
	echo ws_appfile();
	echo '?do=editevent&eventID=';
	echo $_REQUEST['eventid'];
	echo '">Edit This Event</a></p>
	<p align="right"><a href="#" onclick="window.close()">Close Window</a></p>
	</body>
	</html>';
}

/*
Skipped 2 actions:
CreateObjectAction
CallMethodAction
*/
