<?php
require_once 'common.php';
if (checkAuthorization(7, 'view rules')) {
	if (isset($_GET['truncate']) && $_SESSION['is_root']) {
		if (isset($_GET['confirm'])) {
			$result = pgquery('TRUNCATE TABLE rules CASCADE;');
			echo "Table &quot;rules&quot; truncated.<br/>\n";
			pg_free_result($result);
		} else {
?>
			Are you sure?
			<a href="?truncate&amp;confirm">Yes</a>
			<a href="?">No</a>
<?php
			exit(0);
		}
	} else if (!empty($_GET['id']) && !empty($_GET['user'])) {
		if (isset($_SESSION['insert']) {
			$result = pgquery("SELECT TRUE FROM users WHERE username = '{$_GET['user']}' AND NOT is_administrator;");
			if ($_GET['user'] == "'{$_SESSION['username']}'" || $_SESSION['is_administrator'] && pg_fetch_row($result) || $_SESSION['is_root']) {
				pg_free_result(pgquery("INSERT INTO rules(user, id, send_receive_seconds, filter, drop_modify_nothing, modification, query_command_nothing, query_command_1, send_inject_query_command_nothing, query_command_2, proto_id, imm_addr, CCF, ACF, broadcast, override_implicit_rules, activate, deactivate, is_active) VALUES({$_GET['user']}, {$_GET['id']}, {$_GET['send_receive_seconds']}, {$_GET['filter']}, {$_GET['drop_modify_nothing']}, {$_GET['modification']}, {$_GET['query_command_nothing']}, {$_GET['query_command_1']}, {$_GET['send_inject_query_command_nothing']}, {$_GET['query_command_2']}, {$_GET['proto_id']}, E'\\\\x" . substr($_GET['imm_addr'], 2) . ', ' . (isset($_GET['CCF']) ? 'TRU' : 'FALS') . 'E, ' . (isset($_GET['ACF']) ? 'TRU' : 'FALS') . 'E, ' . (isset($_GET['broadcast']) ? 'TRU' : 'FALS') . 'E, ' . (isset($_GET['override_implicit_rules']) ? 'TRU' : 'FALS') . "E, {$_GET['activate']}, {$_GET['deactivate']}, " . (isset($_GET['is_active']) ? 'TRU' : 'FALS') . "E);"));
				echo 'For user ', htmlspecialchars($_GET['user']), ' rule ', htmlspecialchars($_GET['id']), " inserted.<br/>\n";
			}
			pg_free_result($result);
		} else if (!empty($_GET['key1']) && !empty($_GET['key2'])) {
			$result = pgquery("SELECT TRUE FROM users WHERE username = {$_GET['key1']} OR username = '{$_GET['user']}' AND is_administrator;");
			if ($_GET['key1'] == "'{$_SESSION['username']}'" && $_GET['key1'] == $_GET['user'] || $_SESSION['is_administrator'] && !pg_fetch_row($result) || $_SESSION['is_root']) {
				if (isset($_GET['update'])) {
					pg_free_result(pgquery("UPDATE rules SET (user, id, send_receive_seconds, filter, drop_modify_nothing, modification, query_command_nothing, query_command_1, send_inject_query_command_nothing, query_command_2, proto_id, imm_addr, CCF, ACF, broadcast, override_implicit_rules, activate, deactivate, is_active) = ({$_GET['user']}, {$_GET['id']}, {$_GET['send_receive_seconds']}, {$_GET['filter']}, {$_GET['drop_modify_nothing']}, {$_GET['modification']}, {$_GET['query_command_nothing']}, {$_GET['query_command_2']}, {$_GET['send_inject_query_command_nothing']}, {$_GET['query_command_2']}, {$_GET['proto_id']}, E'\\\\x" . substr($_GET['imm_addr'], 2) . ', ' . (isset($_GET['CCF']) ? 'TRU' : 'FALS') . 'E, ' . (isset($_GET['ACF']) ? 'TRU' : 'FALS') . 'E, ' . (isset($_GET['broadcast']) ? 'TRU' : 'FALS') . 'E, ' . (isset($_GET['override_implicit_rules']) ? 'TRU' : 'FALS') . "E, {$_GET['activate']}, {$_GET['deactivate']}, " . (isset($_GET['is_active']) ? 'TRU' : 'FALS') . "E) WHERE user = {$_GET['key1']} AND id = {$_GET['key2']};"));
					echo 'For user ', htmlspecialchars($_GET['key1']), ' rule ', htmlspecialchars($_GET['key2']), " updated.<br/>\n";
				} else if (isset($_GET['delete'])) {
					if (isset($_GET['confirm'])) {
						pg_free_result(pgquery("DELETE FROM rules WHERE user = {$_GET['key1']} AND id = {$_GET['key2']};"));
						echo 'For user ', htmlspecialchars($_GET['key1']), ' rule ', htmlspecialchars($_GET['key2']), " deleted.<br/>\n";
					} else {
?>
						Are you sure?
<?php
						echo '<a href="?key1=', urlencode($_GET['key1']), '&amp;key2=', urlencode($_GET['key2']), "&amp;delete&amp;confirm\">Yes</a>\n";
?>
						<a href="?">No</a>
<?php
						exit(0);
					}
				}
			}
			pg_free_result($result);
		}
	}
	if ($_SESSION['is_root']) {
		$result = pgquery('TABLE rules ORDER BY user ASC, id ASC;');
?>
		Viewing table &quot;rules&quot;.
<?php
	} else if ($_SESSION['is_administrator']) {
		$result = pgquery("SELECT rules.* FROM rules INNER JOIN users ON rules.user = users.username WHERE rules.user = '{$_SESSION['username']}' OR NOT users.is_administrator ORDER BY users.is_administrator DESC, rules.user ASC, rules.id ASC;");
		echo 'Viewing table &quot;rules&quot; for ', htmlspecialchars($_SESSION['username']), " and non-administrators.<br/>\n";
	} else {
		$result = pgquery("SELECT * FROM rules WHERE user = '{$_SESSION['username']}' ORDER BY id ASC;");
		echo 'Viewing table &quot;rules&quot; for ', htmlspecialchars($_SESSION['username']), ".<br/>\n";
	}
?>
	<table border="1">
		<tbody>
			<tr>
				<td>user</td>
				<td>id</td>
				<td>send_receive_seconds</td>
				<td>filter</td>
				<td>drop_modify_nothing</td>
				<td>modification</td>
				<td>query_command_nothing</td>
				<td>query_command_1</td>
				<td>send_inject_query_command_nothing</td>
				<td>query_command_2</td>
				<td>proto_id</td>
				<td>imm_addr</td>
				<td>CCF</td>
				<td>ACF</td>
				<td>broadcast</td>
				<td>override_implicit_rules</td>
				<td>activate</td>
				<td>deactivate</td>
				<td>active</td>
				<td>last_run</td>
			</tr>
			<tr>
				<td>TEXT</td>
				<td>INTEGER</td>
				<td>SMALLINT</td>
				<td>CLOB</td>
				<td>SMALLINT</td>
				<td>CLOB</td>
				<td>SMALLINT</td>
				<td>CLOB</td>
				<td>SMALLINT</td>
				<td>CLOB</td>
				<td>CLOB</td>
				<td>BLOB</td>
				<td>BOOLEAN</td>
				<td>BOOLEAN</td>
				<td>BOOLEAN</td>
				<td>BOOLEAN</td>
				<td>INTEGER</td>
				<td>INTEGER</td>
				<td>BOOLEAN</td>
				<td>TIMESTAMP(0) WITH TIME ZONE</td>
			</tr>
			<tr>
				<th>For user<th>
				<th>this is rule number</th>
				<th>It is activated</th>
				<th>(filter)</th>
				<th>It instructs to</th>
				<th>(modification)</th>
				<th>to execute this</th>
				<th>(query/command 1)</th>
				<th>and to form a new raw msg from this</th>
				<th>(query/command 2)</th>
				<th>using protocol identifier</th>
				<th>and immediate address</th>
				<th>using also CCF</th>
				<th>and also ACF</th>
				<th>using broadcast</th>
				<th>and override implicit rules</th>
				<th>Also activate rule number</th>
				<th>Also deactivate rule number</th>
				<th>Is rule active?</th>
				<th>Last run on</th>
				<th>(actions)</th>
			</tr>
			<tr>
				<td>
<?php
					echo '<input form="insert" type="text" name="user"', $_SESSION['is_administrator'] ? '' : ' value="&apos;' . htmlspecialchars($_SESSION['username']) . '&apos;" disabled="disabled"', "/>\n";
?>
				</td>
				<td>
					<input form="insert" type="text" name="id"/>
					.
				</td>
				<td>
					<input form="insert" type="radio" name="send_receive_seconds" value="0" checked="checked"/>
					on sending when:<br/>
					<input form="insert" type="radio" name="send_receive_seconds" value="1"/>
					on receiving when:<br/>
					<input form="insert" type="radio" name="send_receive_seconds" value="2"/>
					every this amount of seconds:
				</td>
				<td>
					<input form="insert" type="text" name="filter"/>
					.
				</td>
				<td>
					<input form="insert" type="radio" name="drop_modify_nothing" value="0" checked="checked"/>
					drop message<br/>
					<input form="insert" type="radio" name="drop_modify_nothing" value="1"/>
					modify message with this:<br/>
					<input form="insert" type="radio" name="drop_modify_nothing" value="2"/>
					do nothing
				</td>
				<td>
					<input form="insert" type="text" name="modification"/>
					,
				</td>
				<td>
					<input form="insert" type="radio" name="query_command_nothing" value="0" checked="checked"/>
					SQL query:<br/>
					<input form="insert" type="radio" name="query_command_nothing" value="1"/>
					bash command:<br/>
					<input form="insert" type="radio" name="query_command_nothing" value="2"/>
					(execute nothing)
				</td>
				<td>
					<input form="insert" type="text" name="query_command_1"/>
					,
				</td>
				<td>
					<input form="insert" type="radio" name="send_inject_query_command_nothing" value="0" checked="checked"/>
					query and send it:<br/>
					<input form="insert" type="radio" name="send_inject_query_command_nothing" value="1"/>
					command and send it:<br/>
					<input form="insert" type="radio" name="send_inject_query_command_nothing" value="2"/>
					query and inject it:<br/>
					<input form="insert" type="radio" name="send_inject_query_command_nothing" value="3"/>
					command and inject it:<br/>
					<input form="insert" type="radio" name="send_inject_query_command_nothing" value="4"/>
					(form nothing)
				</td>
				<td>
					<input form="insert" type="text" name="query_command_2"/>
				</td>
				<td>
					<input form="insert" type="text" name="proto_id"/>
				</td>
				<td>
					<input form="insert" type="text" name="imm_addr"/>
				</td>
				<td>
					<input form="insert" type="checkbox" name="CCF"/>
				</td>
				<td>
					<input form="insert" type="checkbox" name="ACF"/>
				</td>
				<td>
					<input form="insert" type="checkbox" name="broadcast"/>
				</td>
				<td>
					<input form="insert" type="checkbox" name="override_implicit_rules"/>
					.
				</td>
				<td>
					<input form="insert" type="text" name="activate"/>
					.
				</td>
				<td>
					<input form="insert" type="text" name="deactivate"/>
					.
				</td>
				<td>
					<input form="insert" type="checkbox" name="active"/>
					.
				</td>
				<td>
					<input form="insert" type="text" name="last_run" value="CURRENT_TIMESTAMP(0)" disabled="disabled"/>
				</td>
				<td>
					<form id="insert" action="" method="GET">
						<input type="submit" name="insert" value="INSERT"/><br/>
						<input form="insert" type="reset" value="reset"/><br/>
					</form>
<?php
					if ($_SESSION['is_root']) {
?>
						<form action="" method="GET">
							<input type="submit" name="truncate" value="TRUNCATE"/>
						</form>
<?php
					}
?>
				</td>
			</tr>
<?php
			for ($row = pg_fetch_row($result); $row; $row = pg_fetch_row($result)) {
?>
				<tr>
					<td>
<?php
						$user = htmlspecialchars($row[0]);
						echo '<input form="update_', $user, '_', $row[1], 'type="text" name="user" value="&apos;', $user, '&apos;"', $_SESSION['is_administrator'] ? '' : ' disabled="disabled"' , "/>\n";
?>
					</td>
					<td>
<?php
						echo '<input form="update_', $user, '_', $row[1], '" type="text" name="id", value="', $row[1], "\"/>\n";
?>
						.
					</td>
					<td>
<?php
						echo '<input form="update_', $user, '_', $row[1], '" type="radio" name="send_receive_seconds" value="0"', $row[2] == '0' ? ' checked="checked"' : '', "/>\n";
?>
						on sending when:<br/>
<?php
						echo '<input form="update_', $user, '_', $row[1], '" type="radio" name="send_receive_seconds" value="1"', $row[2] == '1' ? ' checked="checked"' : '', "/>\n";
?>
						on receiving when:<br/>
<?php
						echo '<input form="update_', $user, '_', $row[1], '" type="radio" name="send_receive_seconds" value="2"', $row[2] == '2' ? ' checked="checked"' : '', "/>\n";
?>
						every this amount of seconds:
					</td>
					<td>
<?php
						echo '<input form="update_', $user, '_', $row[1], '" type="text" name="filter" value="&apos;', htmlspecialchars($row[3]), "&apos;\"/>\n";
?>
						.
					</td>
					<td>
<?php
						echo '<input form="update_', $user, '_', $row[1], '" type="radio" name="drop_modify_nothing" value="0"', $row[4] == '0' ? ' checked="checked"' : '', "/>\n";
?>
						drop message<br/>
<?php
						echo '<input form="update_', $user, '_', $row[1], '" type="radio" name="drop_modify_nothing" value="1"', $row[4] == '1' ? ' checked="checked"' : '', "/>\n";
?>
						modify message with this:<br/>
<?php
						echo '<input form="update_', $user, '_', $row[1], '" type="radio" name="drop_modify_nothing" value="2"', $row[4] == '2' ? ' checked="checked"' : '', "/>\n";
?>
						do nothing
					</td>
					<td>
<?php
						echo '<input form="update_', $user, '_', $row[1], '" type="text" name="modification" value="&apos;', htmlspecialchars($row[5]), "&apos;\"/>\n";
?>
						,
					</td>
					<td>
<?php
						echo '<input form="update_', $user, '_', $row[1], '" type="radio" name="query_command_nothing" value="0"', $row[6] == '0' ? ' checked="checked"' : '', "/>\n";
?>
						SQL query:<br/>
<?php
						echo '<input form="update_', $user, '_', $row[1], '" type="radio" name="query_command_nothing" value="1"', $row[6] == '1' ? ' checked="checked"' : '', "/>\n";
?>
						bash command:<br/>
<?php
						echo '<input form="update_', $user, '_', $row[1], '" type="radio" name="query_command_nothing" value="2"', $row[6] == '2' ? ' checked="checked"' : '', "/>\n";
?>
						(execute nothing)
					</td>
					<td>
<?php
						echo '<input form="update_', $user, '_', $row[1], '" type="text" name="query_command_1" value="&apos;', htmlspecialchars($row[7]), "&apos;\"/>\n";
?>
						,
					</td>
					<td>
<?php
						echo '<input form="update_', $user, '_', $row[1], '" type="radio" name="send_inject_query_command_nothing" value="0"', $row[8] == '0' ? ' checked="checked"' : '', "/>\n";
?>
						query and send it:<br/>
<?php
						echo '<input form="update_', $user, '_', $row[1], '" type="radio" name="send_inject_query_command_nothing" value="1"', $row[8] == '1' ? ' checked="checked"' : '', "/>\n";
?>
						command and send it:<br/>
<?php
						echo '<input form="update_', $user, '_', $row[1], '" type="radio" name="send_inject_query_command_nothing" value="2"', $row[8] == '2' ? ' checked="checked"' : '', "/>\n";
?>
						query and inject it:<br/>
<?php
						echo '<input form="update_', $user, '_', $row[1], '" type="radio" name="send_inject_query_command_nothing" value="3"', $row[8] == '3' ? ' checked="checked"' : '', "/>\n";
?>
						command and inject it:<br/>
<?php
						echo '<input form="update_', $user, '_', $row[1], '" type="radio" name="send_inject_query_command_nothing" value="4"', $row[8] == '4' ? ' checked="checked"' : '', "/>\n";
?>
						(form nothing)
					</td>
					<td>
<?php
						echo '<input form="update_', $user, '_', $row[1], '" type="text" name="query_command_2" value="&apos;', htmlspecialchars($row[9]), "&apos;\"/>\n";
?>
					</td>
					<td>
<?php
						echo '<input form="update_', $user, '_', $row[1], '" type="text" name="proto_id" value="&apos;', htmlspecialchars($row[10]), "&apos;\"/>\n";
?>
					</td>
					<td>
<?php
						echo '<input form="update_', $user, '_', $row[1], '" type="text" name="imm_addr" value="X&apos;', htmlspecialchars(strtoupper(substr($row[11], 2))), "&apos;\"/>\n";
?>
					</td>
					<td>
<?php
						echo '<input form="update_', $user, '_', $row[1], '" type="checkbox" name="CCF"', $row[12] == 't' ? ' checked="checked"' : '', "/>\n";
?>
					</td>
					<td>
<?php
						echo '<input form="update_', $user, '_', $row[1], '" type="checkbox" name="ACF"', $row[13] == 't' ? ' checked="checked"' : '', "/>\n";
?>
					</td>
					<td>
<?php
						echo '<input form="update_', $user, '_', $row[1], '" type="checkbox" name="broadcast"', $row[14] == 't' ? ' checked="checked"' : '', "/>\n";
?>
					</td>
					<td>
<?php
						echo '<input form="update_', $user, '_', $row[1], '" type="checkbox" name="override_implicit_rules"', $row[15] == 't' ? ' checked="checked"' : '', "/>\n";
?>
						.
					</td>
					<td>
<?php
						echo '<input form="update_', $user, '_', $row[1], '" type="text" name="activate" value="', $row[16] === null ? 'NULL' : $row[16], "\"/>\n";
?>
						.
					</td>
					<td>
<?php
						echo '<input form="update_', $user, '_', $row[1], '" type="text" name="deactivate" value="', $row[17] === null ? 'NULL' : $row[17], "\"/>\n";
?>
						.
					</td>
					<td>
<?php
						echo '<input form="update_', $user, '_', $row[1], '" type="checkbox" name="active"', $row[18] == 't' ? ' checked="checked"' : '', "/>\n";
?>
						.
					</td>
					<td>
<?php
						echo '<form id="update_', $user, '_', $row[1], "\" action=\"\" method=\"GET\">\n";
							echo '<input type="hidden" name="key1" value="&apos;', $user, "&apos;\"/>\n";
							echo '<input type="hidden" name="key2" value="', $row[1], "\"/>\n";
?>
							<input type="submit" name="update" value="UPDATE"/>;
							<input type="reset" value="reset"/>;
<?php
						echo "</form>";
?>
						<form action="" method="GET">
<?php
							echo '<input type="hidden" name="key1" value="&apos;', $user, "&apos;\"/>\n";
							echo '<input type="hidden" name="key2" value="', $row[1], "\"/>\n";
?>
							<input type="submit" name="delete" value="DELETE"/>
						</form>
					</td>
				</tr>
<?php
			}
?>
		</tbody>
	</table>
	If &quot;SELECT &lt;filter&gt;&quot; evaluates to TRUE, the filter is triggered. You can use column names HD, ID, etc. Appropriate FROM is automatically appended.<br/>
	Modification is performed like &quot;UPDATE message SET &lt;semicolon-separated command 1&gt;; UPDATE message SET &lt;semicolon-separated command 2&gt;; &lt;...&gt;&quot;.<br/>
	During SQL queries the current message is stored in table "formatted_message_for_send_receive" and columns HD, ID, etc.<br/>
	bash commands are NOT executed as /root/, but as the user who started the database.<br/>
	Deactivating a rule deletes its timer. Changing a period does not.<br/>
	Id must be unique. Smaller value indicates bigger priority.<br/>
	When broadcasting a message any imm_DST is ignored.<br/>
	On send and inject rules last_run is meaningless.<br/>
	<a href="index.php">Done</a>
<?php
	pg_free_result($result);
}
?>