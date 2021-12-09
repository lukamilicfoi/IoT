<?php
ob_start();
require_once 'common.php';
if (!empty($_POST['username']) && !empty($_POST['password'])) {
	$_SESSION['s_username'] = pg_escape_literal($_POST['username']);
	$_SESSION['h1username'] = htmlspecialchars($_POST['username']);
	$_SESSION['h2username'] = "&apos;{$_SESSION['h1username']}&apos;";
	$row = pg_fetch_row(pgquery("SELECT password, is_administrator, can_actually_login FROM users
			WHERE username = {$_SESSION['s_username']};"));
	if ($row && password_verify($_POST['password'], $row[0]) && $row[2] == 't') {
		$_SESSION['is_root'] = $_POST['username'] == 'root';
		$_SESSION['s_is_root'] = pgescapebool($_SESSION['is_root']);
		$_SESSION['is_administrator'] = $row[1] == 't';
		$_SESSION['s_is_administrator'] = pgescapebool($_SESSION['is_administrator']);
		$_SESSION['username'] = $_POST['username'];
	}
} else if (isset($_GET['logout'])) {
	unset($_SESSION['username']);
}
if (isset($_SESSION['username'])) {
	header('Location: index.php');
} else {
?>
	<form action="" method="POST">
		Username:
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