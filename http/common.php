<?php
$page_name = ucfirst(strtr(substr($_SERVER['SCRIPT_NAME'], 1, -4), '_', ' '));
$needs_login = $page_name != 'Login';
session_start();
if ($needs_login && !isset($_SESSION['username'])) {
	header('Location: login.php');
	exit(0);
}
?>

<!DOCTYPE html>
<html>
	<head>

<?php
if ($_SESSION['is_root']) {
	$trail = ' as root';
	$username = 'postgres';
} else if ($_SESSION['is_administrator']) {
	$trail = ' as administrator';
	$username = 'administrator';
} else {
	$username = $needs_login ? 'local' : 'login';
	$trail = '';
}
echo '<title>', $page_name, $trail, "</title>\n";
?>

		<meta http_equiv="Content-Type" content="text/html; charset=utf-8"/>
	</head>
<body>

<?php
if (pg_connect("host=localhost dbname=postgres user=$username client_encoding=UTF8")) {
	exit('Could not connect - ' . pg_last_error());
}
register_shutdown_function(function() {
?>

	</body>
</html>

<?php
});

function pgquery($string) {
	$result = pg_query($string);
	if ($result) {
		return $result;
	}
	exit('Query failed - ' . pg_last_error());
}

function checkAuthorization($index, $text) {
	$result = pgquery("SELECT * FROM users WHERE username = '{$_SESSION['username']}'");
	if (pg_fetch_row($result)[$index] == 'f') {
		echo "&lt;You are not authorized to $text.&gt;<br/>\n";
		pg_free_result($result);
		return false;
	}
	pg_free_result($result);
	return true;
}
?>