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
		if (isset($_SESSION['is_root']) && $_SESSION['is_root']) {
			$trail = ' as root';
			$username = 'postgres';
		} else if (isset($_SESSION['is_administrator']) && $_SESSION['is_administrator']) {
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
		if (!pg_connect("host=localhost dbname=postgres user=$username client_encoding=UTF8")) {
			exit('Could not connect - ' . pg_last_error());
		}
		register_shutdown_function(function() {
?>

	</body>
</html>

<?php
});

function pgescapebool(&$boolvar) {
	return $boolvar !== null ? 'TRUE' : 'FALSE';
}

function can_view_table($s_tablename) {
	return pg_num_rows(pgquery("SELECT TRUE FROM table_user INNER JOIN users
			ON table_user.username = users.username WHERE table_user.username = $s_tablename
			AND (table_user.username = 'public' OR table_user.username = {$_SESSION['s_username']}
			OR NOT users.is_administator AND NOT table_user.is_read_only AND "
			. pgescapebool($_SESSION['is_administrator']) . ' OR '
			. pgescapebool($_SESSION['is_root']) . ');')) != 0;
}

function pgquery($string) {
	$result = pg_query($string);
	if ($result) {
		return $result;
	}
	exit('Query failed - ' . pg_last_error());
}

function pgescapebytea($byteavar) {
	return '\'\\x' . pg_escape_string($byteavar) . '\'';
}

function can_edit_table($s_tablename) {
	return pg_num_rows(pgquery("SELECT TRUE FROM table_user INNER JOIN users
			ON table_user.username = users.username WHERE table_user.tablename = $s_tablename
			AND (table_user.username = 'public' OR table_user.username = {$_SESSION['s_username']}
			OR NOT users.is_administrator AND " . pgescapebool($_SESSION['is_administrator'])
			. ' OR ' . pgescapebool($_SESSION['is_root']) . ') AND NOT table_user.is_read_only;'))
			!= 0;
}

function checkAuthorization($index, $text) {
	$result = pgquery("SELECT can_view_tables, can_edit_tables, can_send_messages,
			can_inject_messages, can_send_queries, can_view_rules, can_edit_rules,
			can_view_configuration, can_edit_configuration, can_view_permissions,
			can_edit_permissions, can_view_remotes, can_edit_remotes, can_execute_rules FROM users
			WHERE username = {$_SESSION['s_username']}  ;");
	if (pg_fetch_row($result)[$index - 3] == 'f') {
		echo "&lt;You are not authorized to $text.&gt;<br/>\n";
		pg_free_result($result);
		return false;
	}
	pg_free_result($result);
	return true;
}

function pgescapename($namevar) {
	return '\'t' . pg_escape_string($namevar) . '\'';
}
?>