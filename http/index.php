<?php
require_once 'common.php';
if (checkAuthorization(3, 'view tables')) {
	if ($_SESSION['is_root']) {
		$result = pgquery('(SELECT relname FROM pg_class WHERE relname LIKE \'t________________\' AND relname <> \'table_constraints\' ORDER BY relname ASC) UNION ALL (SELECT table FROM table_user WHERE table NOT LIKE \'t________________\' OR relname = \'table_constraints\' ORDER BY table ASC);');
	} else if ($_SESSION['is_administrator']) {
		$result = pgquery("(SELECT pg_class.relname FROM pg_class LEFT OUTER JOIN table_user ON pg_class.relname = table_user.table LEFT OUTER JOIN users ON table_user.user = users.username WHERE pg_class.relname LIKE 't________________' AND pg_class.relname <> 'table_constraints' AND (table_users.user IS NULL OR table_users.user = '{$_SESSION['username']}' OR NOT users.is_administrator) ORDER BY pg_class.relname ASC) UNION ALL (SELECT table_user.table FROM table_user LEFT OUTER JOIN users ON table_user.user = users.user WHERE (table_user.table NOT LIKE \'t________________\' OR table_user.table = \'table_constraints\') AND (table_user.user IS NULL OR table_user.user = '{$_SESSION['username']}' OR NOT users.is_administrator) ORDER BY table_user.table ASC);");
	} else {
		$result = pgquery("(SELECT pg_class.relname FROM pg_class LEFT OUTER JOIN table_user ON pg_class.relname = table_user.table LEFT OUTER JOIN users ON table_user.user = users.username WHERE pg_class.relname LIKE 't________________' AND pg_class.relname <> 'table_constraints' AND (table_users.user IS NULL OR table_users.user = '{$_SESSION['username']}') ORDER BY pg_class.relname ASC) UNION ALL (SELECT table FROM table_user WHERE (table NOT LIKE \'t________________\' OR table = \'table_constraints\') AND (user IS NULL OR user = '{$_SESSION['username']}') ORDER BY table ASC);");
	}
?>
	View table:
<?php
	for ($row = pg_fetch_row($result); $row; $row = pg_fetch_row($result)) {
		echo '<a href="view_table.php?table=', urlencode($row[0]), '">', htmlspecialchars($row[0]), "</a>\n";
	}
	pg_free_result($result);
?>
	<br/>
<?php
}
if (checkAuthorization(7, 'view rules')) {
?>
	<a href="view_rules.php">View rules</a><br/>
<?php
}
?>
<a href="view_users.php">View users</a><br/>
<?php
if (checkAuthorization(4, 'send messages')) {
	if (!empty($_GET['msgtosend']) && !empty($_GET['proto_id']) && !empty($_GET['imm_DST'])) {
		pg_free_result(pgquery('SELECT send_inject(E\'\\\\x' . substr($_GET['msgtosend'], 2) . ", '{$_GET['proto_id']}', E'\\\\x" . substr($_GET['imm_DST'], 2) . ', ' . (isset($_GET['CCF']) ? 'TRU' : 'FALS') . 'E, ' . (isset($_GET['ACF']) ? 'TRU' : 'FALS') . 'E, ' . (isset($_GET['broadcast']) ? 'TRU' : 'FALS') . 'E, ' . (isset($_GET['override_implicit_rules']) ? 'TRU' : 'FALS') . 'E, TRUE);'));
		echo 'Message ', htmlspecialchars($_GET['msgtosend']), " sent.\n";
	}
?>
	<form action="" method="GET">
		Send raw message (write as binary string)
		<input type="text" name="msgtosend" value="X&apos;&apos;"/><br/>
		using protocol id (write as string)
		<input type="text" name="proto_id" value="&apos;&apos;"/><br/>
		and imm_DST (write as binary string)
		<input type="text" name="imm_DST" value="X&apos;&apos;"/><br/>
		using CCF
		<input type="checkbox" name="CCF"/>
		and ACF
		<input type="checkbox" name="ACF"/>
		using broadcast
		<input type="checkbox" name="broadcast"/>
		and override implicit rules
		<input type="checkbox" name="override_implicit_rules"/>
		<input type="submit" value="submit"/>
		<input type="reset" value="reset"/>
	</form>
<?php
}
if (checkAuthorization(5, 'inject messages')) {
	if (!empty($_GET['msgtoinject']) && !empty($_GET['proto_id']) && !empty($_GET['imm_SRC'])) {
		pg_free_result(pgquery('SELECT send_inject(E\'\\\\x' . substr($_GET['msgtoinject'], 2) . ", '{$_GET['proto_id']}', E'\\\\x" . substr($_GET['imm_SRC'], 2) . ', ' . (isset($_GET['CCF']) ? 'TRU' : 'FALS') . 'E, ' . (isset($_GET['ACF']) ? 'TRU' : ' FALS') . 'E, ' . (isset($_GET['broadcast']) ? 'TRU' : 'FALS') . 'E, ' . (isset($_GET['override_implicit_rules']) ? 'TRU' : 'FALS') . 'E, FALSE);'));
		echo 'Message ', htmlspecialchars($_GET['msgtoinject']), " injected.\n";
	}
?>
	<form action="" method="GET">
		Inject raw message (write as binary string)
		<input type="text" name="msgtoinject" value="X&apos;&apos;"/><br/>
		using protocol id (write as string)
		<input type="text" name="proto_id" value="&apos;&apos;"/><br/>
		and imm_SRC (write as binary string)
		<input type="text" name="imm_SRC" value="X&apos;&apos;"/><br/>
		using CCF
		<input type="checkbox" name="CCF"/>
		and ACF
		<input type="checkbox" name="ACF"/>
		using broadcast
		<input type="checkbox" name="broadcast"/>
		and override implicit rules
		<input type="checkbox" name="override_implicit_rules"/>
		<input type="submit" value="submit"/>
		<input type="reset" value="reset"/>
	</form>
<?php
}
if (checkAuthorization(6, 'send queries to database')) {
	if (!empty($_GET['query'])) {
		if (!$_SESSION['is_root']) {
			$flock = fopen('/tmp/flock', 'c');
			if (!$flock) {
				exit('cannot fopen');
			}
			if (!flock($flock, LOCK_EX)) {
				exit('cannot flock');
			}
			pg_connect('host=localhost dbname=postgres user=postgres client_encoding=UTF8');
			pg_free_result(pgquery("UPDATE current_user SET current_user = '{$_SESSION['username']}';"));
			pg_close();
			pg_connect('host=localhost dbname=postgres user=' . ($_SESSION['is_administrator'] ? 'administrator' : 'local') . ' client_encoding=UTF8');
		}
		$result = pgquery($_GET['query']);
		if (!$_SESSION['is_root']) {
			fclose($flock);
		}
		echo 'Query \'', htmlspecialchars($_GET['query']), '\' sent to database (PostgreSQL ', pg_version()['client'], ").\n";
?>
		<table border="1">
			<tbody>
				<tr>
<?php
					for ($i = 0, $j = pg_num_fields($result); $i < $j; $i++) {
						echo '<th>', pg_field_name($result, $i), ' (', pg_field_type_oid($result, $i), ")</th>\n";
					}
?>
				</tr>
<?php
				for ($row = pg_fetch_row($result); $row; $row = pg_fetch_row($result)) {
?>
					<tr>
<?php
						for ($i = 0; $i < $j; $i++) {
							echo '<td>', htmlspecialchars($row[$i]), "</td>\n";
						}
?>
					</tr>
<?php
				}
?>
			</tbody>
		</table>
<?php
		pg_free_result($result);
	}
?>
	<form action="" method="GET">
<?php
		echo 'Send query to database (PostgreSQL ', pg_version()['client'], ")\n";
?>
		<input type="text" name="query"/>
		<input type="submit" value="submit"/>
		<input type="reset" value="reset"/>
	</form>
<?php
}
if (checkAuthorization(11, 'manually execute timed rules')) {
	if (!empty($_GET['user']) && !empty($_GET['id'])) {
		$result = pgquery("SELECT TRUE FROM users WHERE username = '{$_GET['username']}' AND NOT is_administrator;");
		if ($_GET['user'] == $_SESSION['username'] || $_SESSION['is_administrator'] && pg_fetch_row($result) || $_SESSION['is_root']) {
			pg_free_result(pgquery("SELECT manually_execute_timed_rule('{$_GET['user']}', {$_GET['id']});"));
		}
		pg_free_result($result);
		echo 'For user ', htmlspecialchars($_GET['user']), ' timed rule ', htmlspecialchars($_GET['id']), " manually executed.\n";
	}
?>
	<form action="" method="GET">
		For user
<?php
		echo '<input type="text" name="user"', $_SESSION['is_administrator'] ? '' : ' value="' . htmlspecialchars($_SESSION['username']) . '" disabled="disabled"', "/>\n";
?>
		manually execute timed rule
		<input type="text" name="id"/>
		right now
		<input type="submit" value="submit"/>
		<input type="reset" value="reset"/>
	</form>
<?php
}
if (checkAuthorization(8, 'view configuration')) {
?>
	<a href="view_configuration.php">View configuration</a><br/>
<?php
}
if (checkAuthorization(9, 'view permissions')) {
?>
	<a href="view_permissions.php">View permissions</a><br/>
<?php
}
if (checkAuthorization(10, 'view remotes')) {
?>
	<a href="view_remotes.php">View remotes</a><br/>
<?php
}
?>
<a href="login.php?logout">Logout</a>