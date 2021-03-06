<?php
function pgconnect($string) {
	$dbconn = pg_connect($string);
	if ($dbconn) {
		return $dbconn;
	}
	exit('Could not connect: ' . pg_last_error());
}

function pgquery($string) {
	$result = pg_query($string);
	if ($result) {
		return $result;
	}
	exit('Query failed: ' . pg_last_error());
}

function checkLogin() {
	session_start();
	if (!isset($_SESSION['user'])) {
		header('Location: login.php');
		exit(0);
	}
}

function checkAuthorization($index, $text) {
	$result = pgquery("SELECT * FROM users WHERE username = '{$_SESSION['user']}'");
	if (pg_fetch_row($result)[$index] == 'f') {
		echo "&lt;You are not authorized to $text.&gt;<br/>\n";
		pg_free_result($result);
		return false;
	}
	pg_free_result($result);
	return true;
}
?>