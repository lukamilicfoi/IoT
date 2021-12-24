<?php
require_once 'common.php';
$can_view_tables = check_authorization('view tables');
$can_edit_tables = check_authorization('edit tables');
if ($can_edit_tables) {
	if (!empty($_GET['add'])) {
		$s1add = pg_escape_identifier($_GET['add']);
		$s2add = pg_escape_literal($_GET['add']);
		pgquery("CREATE TABLE $s1add(t TIMESTAMP(4) WITHOUT TIME ZONE);");
		pgquery("INSERT INTO table_owner(tablename, username) VALUES($s2add, 'public');");
	} else if (!empty($_GET['remove'])) {
		$s1remove = pg_escape_identifier($_GET['remove']);
		$s2remove = pg_escape_literal($_GET['remove']);
		$u_remove = urlencode($_GET['remove']);
		if (can_edit_table($s2remove)) {
			if (isset($_GET['confirm'])) {
				pgquery("DROP TABLE $s1remove;");
				pgquery("DELETE FROM table_owner WHERE tablename = $s2remove;");
			} else {
?>
				Are you sure?
<?php
				echo "<a href=\"?remove=$u_remove&amp;confirm\">Yes</a>\n";
?>
				<a href="?">No</a>
<?php
				exit(0);
			}
		}
	}
}
if ($can_view_tables) {
	$result = pgquery('SELECT tablename FROM table_owner
			ORDER BY tablename LIKE \'t________________\'
			AND tablename <> \'table_constraints\' DESC, tablename ASC;');
	if ($_SESSION['is_root']) {
?>
		You are authorized to view (edit) all tables.
<?php
	} else if ($_SESSION['is_administrator']) {
		echo "You are authorized to view (edit) {$_SESSION['h2username']}-readable (-owned) or
				non-administrator-readable (-owned) tables.\n";
	} else {
		echo "You are authorized to view (edit) {$_SESSION['h2username']}-readable (-owned) or
				public-readable (-owned) tables.\n";
	}
?>
	<form action="" method="GET">
		View table, device tables shown first:
<?php
		for ($row = pg_fetch_row($result); $row; $row = pg_fetch_row($result)) {
			$u_tablename = urlencode($row[0]);
			$h_tablename = htmlspecialchars($row[0]);
			$s_tablename = pg_escape_literal($row[0]);
			if (can_view_table($s_tablename)) {
				echo "<a href=\"view_table.php?tablename=$u_tablename\">$h_tablename</a>\n";
			}
			if (can_edit_table($s_tablename)) {
				echo "<a href=\"?remove=$u_tablename\">(remove)</a>\n";
			}
		}
		if (pg_num_rows($result) == 0) {
?>
			&lt;no tables&gt;
<?php
		}
		if ($can_edit_tables) {
?>
			<input type="text" name="add"/>
			<input type="submit" value="(add as public)"/>
			Write name as a string, e.g., table.
<?php
		}
?>
	</form>
	Tables ordered by name ascending.<br/><br/>
<?php
}
if (check_authorization('send messages')) {
	if (!empty($_GET['msgtosend']) && !empty($_GET['proto_id']) && !empty($_GET['imm_DST'])) {
		$s_msgtosend = pgescapebytea($_GET['msgtosend']);
		$h_msgtosend = 'X&apos;' . htmlspecialchars($_GET['msgtosend']) . '&apos;';
		$proto_id = pg_escape_literal($_GET['proto_id']);
		$imm_DST = pgescapebytea($_GET['imm_DST']);
		pgquery("SELECT send_inject(TRUE, $s_msgtosend, (SELECT proto FROM proto_name
				WHERE name = $proto_id), $imm_DST, " . pgescapebool($_GET['CCF']) . ', '
				. pgescapebool($_GET['ACF']) . ', ' . pgescapebool($_GET['broadcast']) . ', '
				. pgescapebool($_GET['override_implicit_rules']) . ');');
		echo "Message $h_msgtosend sent.\n";
	}
?>
	<form action="" method="GET">
		Send message
		<input type="text" name="msgtosend"/>
		using protocol
		<input type="text" name="proto_id"/>
		and imm_DST
		<input type="text" name="imm_DST"/><br/>
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
	Write message and imm_DST as a binary string, e.g., abababababababab;
			write protocol as a string, e.g., tcp.<br/><br/>
<?php
}
if (check_authorization('inject messages')) {
	if (!empty($_GET['msgtoinject']) && !empty($_GET['proto_id']) && !empty($_GET['imm_SRC'])) {
		$s_msgtoinject = pgescapebytea($_GET['msgtoinject']);
		$h_msgtoinject = 'X&apos;' . htmlspecialchars($_GET['msgtoinject']) . '&apos;';
		$proto_id = pg_escape_literal($_GET['proto_id']);
		$imm_SRC = pgescapebytea($_GET['imm_SRC']);
		pgquery("SELECT send_inject(FALSE, $s_msgtoinject, (SELECT proto FROM proto_name
				WHERE name = $proto_id), $imm_SRC, " . pgescapebool($_GET['CCF']) . ', '
				. pgescapebool($_GET['ACF']) . ', ' . pgescapebool($_GET['broadcast']) . ', '
				. pgescapebool($_GET['override_implicit_rules']) . ');');
		echo "Message $h_msgtoinject injected.\n";
	}
?>
	<form action="" method="GET">
		Inject message
		<input type="text" name="msgtoinject"/>
		using protocol
		<input type="text" name="proto_id"/>
		and imm_SRC
		<input type="text" name="imm_SRC"/><br/>
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
	Write message and imm_SRC as a binary string, e.g., abababababababab;
			write protocol as a string, e.g., tcp.<br/><br/>
<?php
}
if (check_authorization('send queries')) {
	if (!empty($_GET['query'])) {
		$h_query = '&apos;' . htmlspecialchars($_GET['query']) . '&apos;';
		if (!$_SESSION['is_root']) {
			$flock = fopen('/tmp/flock', 'c');
			if (!$flock) {
				exit('cannot fopen');
			}
			if (!flock($flock, LOCK_EX)) {
				exit('cannot flock');
			}
			pgconnect('postgres');
			pgquery("UPDATE current_username SET current_username = {$_SESSION['s_username']};");
			pg_close();
			pgconnect($_SESSION['is_administrator'] ? 'administrator' : 'local');
		}
		$result = pgquery($_GET['query']);
		if (!$_SESSION['is_root']) {
			fclose($flock);
		}
		echo "Query $h_query sent to database (PostgreSQL ", pg_version()['client'], ").\n";
?>
		<table border="1">
			<tbody>
				<tr>
<?php
					for ($i = 0, $j = pg_num_fields($result); $i < $j; $i++) {
						echo '<th>', pg_field_name($result, $i), ' (',
								pg_field_type_oid($result, $i), ")</th>\n";
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
	}
	if ($_SESSION['is_root']) {
?>
		You are authorized to send queries to read (write) all tables.
<?php
	} else if ($_SESSION['is_administrator']) {
		echo "You are authorized to send queries to read (write) {$_SESSION['h2username']}-readable
				(-owned) or non-administrator-readable (-owned) tables.\n";
	} else {
		echo "You are authorized to send queries to read (write) {$_SESSION['h2username']}-readable
				(-owned) or non-administrator-readable (-owned) tables.\n";
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
	Write query as a string, e.g., SELECT a FROM b;.<br/><br/>
<?php
}
if (check_authorization('execute rules')) {
	if (!empty($_GET['username']) && !empty($_GET['id'])) {
		$s_username = pg_escape_literal($_GET['username']);
		$h_username = '&apos;' . htmlspecialchars($_GET['username']);
		$id = intval($_GET['id']);
		if ($_GET['username'] == $_SESSION['username'] || $_SESSION['is_administrator']
				&& !is_administrator($s_username) || $_SESSION['is_root']) {
			pgquery("CALL manually_execute_timed_rule($s_username, $id);");
		}
		echo "For username $h_username timed rule $id manually executed.\n";
	}
	if ($_SESSION['is_root']) {
?>
		You are authorized to execute rules for all users.
<?php
	} else if ($_SESSION['is_administrator']) {
		echo "You are authorized to execute rules for {$_SESSION['h2username']}
				or non-administrators.\n";
	} else {
		echo "You are authorized to execute rules for {$_SESSION['h1username']} or public.\n";
	}
?>
	<form action="" method="GET">
		For username
		<input type="text" name="username"/>
		manually execute timed rule
		<input type="text" name="id"/>
		right now
		<input type="submit" value="submit"/>
		<input type="reset" value="reset"/>
	</form>
	Write username and rule as a string and an integer, e.g., root and 11.<br/><br/>
<?php
}
if (check_authorization('view rules')) {
?>
	<a href="view_rules.php">View rules</a><br/>
<?php
}
?>
<a href="view_certificates_and_private_keys.php">View certificates and private keys</a><br/>
<a href="view_users.php">View users</a><br/>
<a href="view_adapters_and_underlying_protocols.php">View adapters and underlying protocols</a><br/>
<?php
if (check_authorization('view configuration')) {
?>
	<a href="view_configuration.php">View configuration</a><br/>
<?php
}
if (check_authorization('view permissions')) {
?>
	<a href="view_permissions.php">View permissions</a><br/>
<?php
}
if (check_authorization('view remotes')) {
?>
	<a href="view_remotes.php">View remotes</a><br/>
<?php
}
?>
<br/><a href="login.php?logout">Logout</a>