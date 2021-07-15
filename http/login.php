<?php
ob_start();
require_once 'common.php';
if (!empty($_POST['username']) && !empty($_POST['password'])) {
	$result = pgquery("SELECT password, can_actually_login, is_administrator FROM users WHERE username = '{$_POST['username']}';");
	$row = pg_fetch_row($result);
	if ($row && password_verify($_POST['password'], $row[0]) && $row[1] == 't') {
		session_reset();
		$_SESSION['is_root'] = $_POST['username'] == 'root';
		$_SESSION['username'] = $_POST['username'];
		$_SESSION['is_administrator'] = $row[2] == 't';
	}
	pg_free_result($result);
} else if (isset($_GET['logout'])) {
	session_reset();
}
if (isset($_SESSION['username'])) {
	header('Location: index.php');
} else {
?>
	<form action="" method="POST">
		Login with username:
		<input type="text" name="username"/>
		Password:
		<input type="password" name="password"/>
		Actions:
		<input type="submit" value="submit"/>
		<input type="reset" value="reset"/>
	</form>
<?php
}
ob_end_flush();
?>
