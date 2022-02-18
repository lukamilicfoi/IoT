<?php
require_once 'common.php';
$can_view_tables = check_authorization('can_view_tables', 'view device or regular tables');
$can_edit_tables = check_authorization('can_edit_tables', 'edit device or regular tables');
if ($can_edit_tables) {
	if (!vacuous($_GET['add'])) {
		$s1add = pg_escape_identifier($_GET['add']);
		$s2add = pg_escape_literal($_GET['add']);
		pgquery("CREATE TABLE $s1add(t TIMESTAMP(4) WITHOUT TIME ZONE);");
		pgquery("ALTER TABLE $s1add OWNER TO {$_SESSION['s2username']};");
		pgquery("INSERT INTO table_owner(tablename, username) VALUES($s2add,
				{$_SESSION['s_username']});");
	} elseif (!vacuous($_GET['remove'])) {
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
	if ($_SESSION['is_root']) {
		$result = pgquery('SELECT tablename, TRUE, tablename LIKE \'t________________\'
				AND tablename <> \'table_constraints\' AS is_device FROM table_owner
				ORDER BY is_device ASC, tablename ASC;');
?>
		You are authorized to view (edit) all tables.
<?php
	} elseif ($_SESSION['is_administrator']) {
		$table_name = 'table_owner.tablename';
		$can_edit = "table_owner.username = {$_SESSION['s_username']}
				OR NOT users.is_administrator AND {$_SESSION['s_can_edit_as_others']}";
		$is_device = "$table_name LIKE 't________________' AND table_name <> 'table_constraints'";
		$result = pgquery("SELECT $table_name, $can_edit, $is_device FROM table_owner
				INNER JOIN users ON table_owner.username = users.username WHERE $can_edit
				OR EXISTS(SELECT TRUE FROM table_reader INNER JOIN users ON table_reader.username
				= users.username WHERE table_reader.tablename = $table_name
				AND (table_reader.username = {$_SESSION['s_username']} OR NOT users.is_administrator
				AND {$_SESSION['s_can_view_as_others']}))
				ORDER BY $is_device ASC, $table_name ASC;");
		echo 'You are authorized to view', $can_edit_tables ? ' (edit)' : '',
				" username-{$_SESSION['h2username']}-readable", $can_edit_tables ? ' (-owned)'
				: '', $_SESSION['can_view_as_others'] ? ' or non-administrator-readable' : '',
				$_SESSION['can_edit_as_others'] && $can_edit_tables ? ' (-owned)' : '',
				" tables.\n";
	} else {
		$table_name = 'table_owner.tablename';
		$can_edit = "table_owner.username = {$_SESSION['s_username']}
				OR table_owner.username = 'public' AND {$_SESSION['s_can_edit_as_others']}";
		$is_device = "$table_name LIKE 't________________' AND table_name <> 'table_constraints'";
		$result = pgquery("SELECT $table_name, $can_edit, $is_device FROM table_owner
				INNER JOIN users ON table_owner.username = users.username WHERE $can_edit
				OR EXISTS(SELECT TRUE FROM table_reader WHERE tablename = $table_name
				AND (username = {$_SESSION['s_username']} OR username = 'public'
				AND {$_SESSION['s_can_view_as_others']}))
				ORDER BY $is_device ASC, $table_name ASC;");
		echo 'You are authorized to view', $can_edit_tables ? ' (edit)' : '',
				" username-{$_SESSION['h2username']}-readable", $can_edit_tables ? ' (-owned)'
				: '', $_SESSION['can_view_as_others'] ? ' or public-user-readable' : '',
				$_SESSION['can_edit_as_others'] && $can_edit_tables ? ' (-owned)' : '',
				" tables.\n";
	}
?>
	<form action="" method="GET">
		View table, regular tables shown first:
<?php
		for ($row = pg_fetch_row($result); $row; $row = pg_fetch_row($result)) {
			$u_tablename = urlencode($row[0]);
			$h_tablename = htmlspecialchars($row[0]);
			$s_tablename = pg_escape_literal($row[0]);
			echo "<a href=\"view_table.php?tablename=$u_tablename\">$h_tablename</a>\n";
			if ($can_edit_tables && $row[1] == 't') {
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
			<input type="text" name="add" required autofocus/>
			<input type="submit" value="(add as mine)"/>
			Write name as a string, e.g., table.
<?php
		}
?>
	</form>
	Tables ordered by name ascending.<br/><br/><br/>
<?php
}
if (check_authorization('can_send_messages', 'send messages to nodes')) {
	if (!vacuous($_GET['msgtosend']) && !vacuous($_GET['proto_name'])
			&& !vacuous($_GET['imm_DST'])) {
		$s_msgtosend = pgescapebytea($_GET['msgtosend']);
		$h_msgtosend = 'X&apos;' . htmlspecialchars($_GET['msgtosend']) . '&apos;';
		$proto_name = pg_escape_literal($_GET['proto_name']);
		$imm_DST = pgescapebytea($_GET['imm_DST']);
		pgquery("SELECT send_inject(TRUE, $s_msgtosend, (SELECT proto FROM proto_name
				WHERE name = $proto_name), $imm_DST, " . pgescapeinteger($_GET['insecure_port'])
				. ', ' . pgescapeinteger($_GET['secure_port']) . ', ' . pgescapebool($_GET['CCF'])
				. ', ' . pgescapebool($_GET['ACF']) . ', ' . pgescapebool($_GET['broadcast']) . ', '
				. pgescapebool($_GET['override_implicit_rules']) . ');');
		echo "Message $h_msgtosend sent.\n";
	}
?>
	<form action="" method="GET">
		Send message
		<input type="text" name="msgtosend" required autofocus/>
		using protocol
		<input type="text" name="proto_name" required/>
		and imm_DST
		<input type="text" name="imm_DST" required/>
		using custom insecure listen port
		<input type="text" name="insecure_port"/><br/>
		and custom secure listen port
		<input type="text" name="secure_port"/>
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
	Write message and imm_DST as a binary string, e.g., abababababababab; write protocol
			as a string, e.g., tcp; write ports as integers, e.g., 44000 and 44001.<br/><br/><br/>
<?php
}
if (check_authorization('can_inject_messages', 'inject messages from nodes')) {
	if (!vacuous($_GET['msgtoinject']) && !vacuous($_GET['proto_name'])
			&& !vacuous($_GET['imm_SRC'])) {
		$s_msgtoinject = pgescapebytea($_GET['msgtoinject']);
		$h_msgtoinject = 'X&apos;' . htmlspecialchars($_GET['msgtoinject']) . '&apos;';
		$proto_name = pg_escape_literal($_GET['proto_name']);
		$imm_SRC = pgescapebytea($_GET['imm_SRC']);
		pgquery("SELECT send_inject(FALSE, $s_msgtoinject, (SELECT proto FROM proto_name
				WHERE name = $proto_name), $imm_SRC, " . pgescapeinteger($_GET['insecure_port'])
				. ', ' . pgescapeinteger($_GET['secure_port']) . ', ' . pgescapebool($_GET['CCF'])
				. ', ' . pgescapebool($_GET['ACF']) . ', ' . pgescapebool($_GET['broadcast']) . ', '
				. pgescapebool($_GET['override_implicit_rules']) . ');');
		echo "Message $h_msgtoinject injected.\n";
	}
?>
	<form action="" method="GET">
		Inject message
		<input type="text" name="msgtoinject" required autofocus/>
		using protocol
		<input type="text" name="proto_name" required/>
		and imm_SRC
		<input type="text" name="imm_SRC" required/>
		using custom insecure listen port
		<input type="text" name="insecure_port"/><br/>
		and custom secure listen port
		<input type="text" name="secure_port"/>
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
	Write message and imm_SRC as a binary string, e.g., abababababababab; write protocol
			as a string, e.g., tcp; write ports as integers, e.g., 44000 and 44001.<br/><br/><br/>
<?php
}
if (check_authorization('can_send_queries', 'send queries to database')) {
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
			pgquery("SET ROLE {$_SESSION['s3username']};");
		}
		$result = pgquery($_GET['query']);
		if (!$_SESSION['is_root']) {
			pgquery('SET ROLE NONE;');
			fclose($flock);
		}
		echo "Query $h_query sent to database (PostgreSQL ", pg_version()['client'], ").\n";
?>
		<table border="1">
			<tbody>
				<tr>
<?php
					for ($i = 0, $j = pg_num_fields($result); $i < $j; $i++) {
						echo '<th>', htmlspecialchars(pg_field_name($result, $i)), ' (',
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
	} elseif ($_SESSION['is_administrator']) {
		echo 'You are authorized to send queries to read', $can_edit_tables ? ' (write)' : '',
				"username-{$_SESSION['h2username']}-readable", $can_edit_tables ? ' (-owned)' : '',
				$_SESSION['can_view_as_others'] ? ' or non-administrator-readable' : '',
				$_SESSION['can_edit_as_others'] && $can_edit_tables ? ' (-owned)' : '',
				" tables.\n";
	} else {
		echo 'You are authorized to send queries to read', $can_edit_tables ? ' (write)' : '',
				"username-{$_SESSION['h2username']}-readable", $can_edit_tables ? ' (-owned)' : '',
				$_SESSION['can_view_as_others'] ? ' or public-user-readable' : '',
				$_SESSION['can_edit_as_others'] && $can_edit_tables ? ' (-owned)' : '',
				" tables.\n";
	}
?>
	<form action="" method="GET">
<?php
		echo 'Send query to database (PostgreSQL ', pg_version()['client'], ")\n";
?>
		<input type="text" name="query" required autofocus/>
		<input type="submit" value="submit"/>
		<input type="reset" value="reset"/>
	</form>
	Write query as a string, e.g., SELECT a FROM b;.<br/><br/><br/>
<?php
}
if (check_authorization('can_execute_rules', 'manually execute timed rules')) {
	if (!vacuous($_GET['username']) && !vacuous($_GET['id'])) {
		$s_username = pg_escape_literal($_GET['username']);
		$h_username = '&apos;' . htmlspecialchars($_GET['username']) . '&apos;';
		$id = intval($_GET['id']);
		if ($_GET['username'] == $_SESSION['username'] || $_SESSION['is_administrator']
				&& !is_administrator($s_username) || $_SESSION['is_root']) {
			pgquery("CALL manually_execute_timed_rule($s_username, $id);");
		}
		echo "For username $h_username timed rule $id manually executed.<br/>\n";
	}
	if ($_SESSION['is_root']) {
?>
		You are authorized to execute rules for all users.
<?php
	} elseif ($_SESSION['is_administrator']) {
		echo "You are authorized to execute rules for username {$_SESSION['h2username']}
				or non-administrators.\n";
	} else {
		echo "You are authorized to execute rules for {$_SESSION['h1username']}
				or public user.\n";
	}
?>
	<form action="" method="GET">
		For username
		<input type="text" name="username" required autofocus/>
		manually execute timed rule
		<input type="text" name="id" required/>
		right now
		<input type="submit" value="submit"/>
		<input type="reset" value="reset"/>
	</form>
	Write username and rule as a string and an integer, e.g., root and 11.<br/><br/><br/>
<?php
}
if (check_authorization('can_view_rules', 'view rules')) {
?>
	<a href="view_rules.php">View rules</a><br/>
<?php
}
?>
<a href="view_certificates_and_private_keys.php">View certificates and private keys</a><br/>
<?php
if (check_authorization('can_view_yourself', 'view yourself')
		|| check_authorization('can_view_others', 'view others')) {
?>
	<a href="view_users.php">View users</a><br/>
<?php
}
?>
<a href="view_adapters_and_underlying_protocols.php">View adapters and underlying protocols</a><br/>
<?php
if (check_authorization('can_view_configuration', 'view configuration')) {
?>
	<a href="view_configuration.php">View configuration</a><br/>
<?php
}
if (check_authorization('can_view_permissions', 'view permissions')) {
?>
	<a href="view_permissions.php">View permissions</a><br/>
<?php
}
if (check_authorization('can_view_remotes', 'view remotes')) {
?>
	<a href="view_remotes.php">View remotes</a><br/>
<?php
}
?>
<br/><br/><a href="login.php?logout">Logout</a>