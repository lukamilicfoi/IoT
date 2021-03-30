<?php
require_once 'common.php';
session_start();
$dbconn = pgconnect('host=localhost dbname=postgres user=login client_encoding=UTF8');
if (isset($_POST['username']) && isset($_POST['password'])) {
	$result = pgquery("SELECT password, can_actually_login FROM users WHERE username = '{$_POST['username']}';");
	$row = pg_fetch_row($result);
	if ($row && password_verify($_POST['password'], $row[0]) && $row[1] == 't') {
		session_destroy();
		session_start();
		$_SESSION['user'] = $_POST['username'];
	}
	pg_free_result($result);
} else if (isset($_GET['logout'])) {
	session_destroy();
	session_start();
}
pg_close($dbconn);
if (isset($_SESSION['user'])) {
	header('Location: index.php');
} else {
?>
	<!DOCTYPE html>
	<html>
		<head>
			<title>Login</title>
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
		</head>
		<body>
			<form action="" method="POST">
				Login:
				<input type="text" name="username"/>
				<input type="password" name="password"/>
				<input type="submit" value="submit"/>
				<input type="reset" value="reset"/>
			</form>
		</body>
	</html>
<?php
}
?>