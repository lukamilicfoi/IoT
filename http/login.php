<?php
ob_start();
require_once 'common.php';
if (!Empty($_POST['username']) && !Empty($_POST['password'])) {
	$_SESSION['s_username'] = pg_escape_literal($_POST['username']);
	$_SESSION['h1username'] = htmlspecialchars($_POST['username']);
	$_SESSION['h2username'] = "&apos;{$_SESSION['h1username']}&apos;";
	$row = pg_fetch_row(pgquery("SELECT password, is_administrator,
			can_view_as_others, can_edit_as_others FROM users
			WHERE username = {$_SESSION['s_username']} AND can_actually_login;"));
	if ($row && password_verify($_POST['password'], $row[0])) {
		$_SESSION['is_root'] = $_POST['username'] == 'root';
		$_SESSION['can_view_as_others'] = $row[2] == 't';
		$_SESSION['is_administrator'] = $row[1] == 't';
		$_SESSION['can_edit_as_others'] = $row[3] == 't';
		$_SESSION['username'] = $_POST['username'];
	}
} elseif (isset($_GET['logout'])) {
	unset($_SESSION['username']);
}
if (isset($_SESSION['username'])) {
	header('Location: index.php');
} else {
?>
	<form action="" method="POST">
		Username:
		<input type="text" name="username" required/>
		Password:
		<input type="password" name="password" required/>
		Actions:
		<input type="submit" value="Login"/>
		<input type="reset" value="Clear"/>
	</form>
<?php
}
ob_end_flush();
?>