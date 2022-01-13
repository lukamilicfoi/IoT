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
		if (!empty($_SESSION['is_root'])) {
			$trail = ' as root';
			$username = 'postgres';
		} elseif (!empty($_SESSION['is_administrator'])) {
			$trail = ' as administrator';
			$username = 'postgres';
		} else {
			$username = $needs_login ? 'postgres' : 'login';
			$trail = '';
		}
		echo '<title>', $page_name, $trail, "</title>\n";
?>

		<meta http_equiv="Content-Type" content="text/html; charset=utf-8"/>
	</head>
	<body>

<?php
		register_shutdown_function(function() {
?>

	</body>
</html>

<?php
);
if (!pg_connect("host=localhost dbname=postgres user=$username client_encoding=UTF8")) {
	exit('Could not connect - ' . pg_last_error());
}

function Empty($var) {
	return !isset($var) || $var == '';
}

function pgescapebool(&$boolvar) {
	return $boolvar !== null ? 'TRUE' : 'FALSE';
}

function can_view_table($s_tablename) {
	return pg_num_rows(pgquery("SELECT TRUE FROM table_owner INNER JOIN users
			ON table_owner.username = users.username WHERE table_owner.tablename = $s_tablename
			AND (table_owner.username = {$_SESSION['s_username']}
			OR {$_SESSION['can_edit_as_others']} AND (table_owner.username = 'public'
			OR {$_SESSION['is_administrator']} AND NOT users.is_administrator)
			OR {$_SESSION['is_root']}) UNION ALL SELECT TRUE FROM table_reader INNER JOIN users
			ON table_reader.username = users.username WHERE table_reader.username = $s_tablename
			AND (table_reader.username = {$_SESSION['s_username']}
			OR {$_SESSION['can_view_as_others']} AND (table_reader.username = 'public'
			OR {$_SESSION['is_administrator']} AND NOT users.is_administrator)
			OR {$_SESSION['is_root']});")) != 0;
}

function pgescapeinteger(&$integervar) {
	return $integervar !== null ? intval($integervar) : 'NULL';
}

function pgescapetimestamp($timestampvar) {
	return 'TIMESTAMP ' . pg_escape_literal($timestampvar);
}

function postgresql_output_to_my_input($data, $oid) {
	if ($data === null) {
		return '';
	}
	switch ($oid) {
	case 1700://NUMERIC
	case 1266://TIME WITH TIME ZONE
	case 1186://INTERVAL
	case 1184://TIMESTAMP WITH TIME ZONE
	case 1114://TIMESTAMP WITHOUT TIME ZONE
	case 1083://TIME WITHOUT TIME ZONE
	case 1082://DATE
	case 25://TEXT
	case 16://BOOLEAN
		return $data;
	default://17//BYTEA
		return substr($data, 2);
	}
}

function find_owner($s_tablename) {
	return pg_fetch_row(pgquery("SELECT username FROM table_owner
			WHERE tablename = $s_tablename;"))[0];
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
	return pg_num_rows(pgquery("SELECT TRUE FROM table_owner INNER JOIN users
			ON table_owner.username = users.username WHERE table_owner.tablename = $s_tablename
			AND (table_owner.username = {$_SESSION['s_username']}
			OR {$_SESSION['can_edit_as_others']} AND (table_owner.username = 'public'
			OR {$_SESSION['is_administrator']} AND NOT users.is_administrator)
			OR {$_SESSION['is_root']});")) != 0;
}

$user_fields = array('can_view_tables', 'can_edit_tables', 'can_send_messages',
		'can_inject_messages', 'can_send_queries', 'can_view_rules', 'can_edit_rules',
		'can_view_configuration', 'can_edit_configuration', 'can_view_permissions',
		'can_edit_permissions', 'can_view_remotes', 'can_edit_remotes', 'can_execute_rules',
		'can_view_yourself', 'can_edit_yourself', 'can_view_others', 'can_edit_others',
		'can_view_as_others', 'can_edit_as_others');

function my_input_to_postgresql_input($data, $oid) {
	if ($data == '') {
		return 'NULL';
	}
	switch ($oid) {
	case 1700://NUMERIC
		return $data;
	case 1266://TIME WITH TIME ZONE
	case 1186://INTERVAL
	case 1184://TIMESTAMP WITH TIME ZONE
	case 1114://TIMESTAMP WITHOUT TIME ZONE
	case 1083://TIME WITHOUT TIME ZONE
	case 1082://DATE
	case 25://TEXT
		return '\'' . $data . '\'';
	case 16://BOOLEAN
		return $data == 't' ? 'TRUE' : 'FALSE';
	default://17//BYTEA
		return '\'\\x' . $data . '\'';
	}
}

function is_administrator($s_username) {
	return pg_num_rows(pgquery("SELECT TRUE FROM users
			WHERE username = $s_username AND is_administrator;")) != 0;
}

function check_authorization($field, $text) {
	if (pg_num_rows(pgquery("SELECT TRUE FROM users
			WHERE username = {$_SESSION['s_username']} AND $field;")) == 0) {
		echo "&lt;You are not authorized to $text.&gt;<br/>\n";
		return false;
	}
	return true;
}

function pgescapename($namevar) {
	return '\'t' . pg_escape_string($namevar) . '\'';
}
?>