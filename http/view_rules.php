<?php
require_once 'common.php';
$can_view_rules = check_authorization('can_view_rules', 'view rules');
$can_edit_rules = check_authorization('can_edit_rules', 'edit rules');
if ($can_edit_rules) {
	if (isset($_GET['truncate']) && $_SESSION['is_root']) {
		if (isset($_GET['confirm'])) {
			pgquery('TRUNCATE TABLE rules CASCADE;');
			echo "Table &quot;rules&quot; truncated.<br/>\n";
		} else {
?>
			Are you sure?
			<a href="?truncate&amp;confirm">Yes</a>
			<a href="?">No</a>
<?php
			exit(0);
		}
	} elseif (!vacuous($_GET['username']) && !vacuous($_GET['id'])) {
		$s_username = pg_escape_literal($_GET['username']);
		$h_username = '&apos;' . htmlspecialchars($_GET['username']) . '&apos;';
		$id = intval($_GET['id']);
		if (($_GET['username'] == $_SESSION['username'] || $_SESSION['is_administrator']
				&& !is_administrator($s_username) && $_SESSION['can_edit_as_others']
			 	|| $_SESSION['is_root']) && isset($_GET['insert'])) {
			pgquery("INSERT INTO rules(username, id, send_receive_seconds, filter,
					drop_modify_nothing, modification, query_command_nothing, query_command_1,
					send_inject_query_command_nothing, query_command_2, proto_id, imm_addr,
					insecure_port, secure_port, CCF, ACF, broadcast, override_implicit_rules,
					activate, deactivate, is_active) VALUES($s_username, $id, "
					. intval($_GET['send_receive_seconds']) . ', '
					. pg_escape_literal($_GET['filter']) . ', '
					. intval($_GET['drop_modify_nothing']) . ', '
					. formescapetext($_GET['modification']) . ', '
					. intval($_GET['query_command_nothing']) . ', '
					. formescapetext($_GET['query_command_1']) . ', '
					. intval($_GET['send_inject_query_command_nothing']) . ', '
					. formescapetext($_GET['query_command_2']) . ', '
					. (!vacuous($_GET['proto_name']) ? '(SELECT proto FROM proto_name WHERE name = '
					. pg_escape_literal($_GET['proto_id']) : 'NULL') . ', '
					. formescapebytea($_GET['imm_addr']) . ', '
					. formescapeinteger($_GET['insecure_port']) . ', '
					. formescapeinteger($_GET['secure_port']) . ', '
					. formescapebool($_GET['CCF']) . ', ' . formescapebool($_GET['ACF']) . ', '
					. formescapebool($_GET['broadcast']) . ', '
					. formescapebool($_GET['override_implicit_rules']) . ', '
					. formescapeinteger($_GET['activate']) . ', '
					. formescapeinteger($_GET['deactivate']) . ', '
					. formescapebool($_GET['is_active']) . ');');
			echo "For username $h_username rule $id inserted.<br/>\n";
		} elseif (!vacuous($_GET['key1']) && !vacuous($_GET['key2'])) {
			$s_key1 = pg_escape_literal($_GET['key1']);
			$h_key1 = '&apos;' . htmlspecialchars($_GET['key1']) . '&apos;';
			$key2 = intval($_GET['key2']);
			if (($_GET['key1'] == $_SESSION['username'] && $_GET['key1'] == $_GET['username']
					|| $_SESSION['is_administrator'] && !is_administrator($s_key1)
					&& !is_administrator($s_username) && $_SESSION['can_edit_as_others']
				 	|| $_SESSION['is_root']) && isset($_GET['update'])) {
				pgquery("UPDATE rules SET (username, id, send_receive_seconds, filter,
						drop_modify_nothing, modification, query_command_nothing, query_command_1,
						send_inject_query_command_nothing, query_command_2, proto_id, imm_addr,
						insecure_port, secure_port, CCF, ACF, broadcast, override_implicit_rules,
						activate, deactivate, is_active) = ($s_username, $id, "
						. intval($_GET['send_receive_seconds']) . ', '
						. pg_escape_literal($_GET['filter']) . ', '
						. intval($_GET['drop_modify_nothing']) . ', '
						. formescapetext($_GET['modification']) . ', '
						. intval($_GET['query_command_nothing']) . ', '
						. formescapetext($_GET['query_command_1']) . ', '
						. intval($_GET['send_inject_query_command_nothing']) . ', '
						. formescapetext($_GET['query_command_2']) . ', '
						. (!vacuous($_GET['proto_name']) ? '(SELECT proto FROM proto_name
						WHERE name = ' . pg_escape_literal($_GET['proto_name']) : 'NULL') . ', '
						. formescapebytea($_GET['imm_addr'])
						. ', ' . formescapeinteger($_GET['insecure_port']) . ', '
						. formescapeinteger($_GET['secure_port']) . formescapebool($_GET['CCF']) . ', '
						. formescapebool($_GET['ACF']) . ', ' . formescapebool($_GET['broadcast'])
						. ', ' . formescapebool($_GET['override_implicit_rules']) . ', '
						. formescapeinteger($_GET['activate']) . ', '
						. formescapeinteger($_GET['deactivate']) . ', '
						. formescapebool($_GET['is_active'])
						. ") WHERE username = $s_key1 AND id = $key2;");
				echo "For username $h_key1 rule $key2 updated.<br/>\n";
			}
		}
	} elseif (!vacuous($_GET['key1']) && !vacuous($_GET['key2'])) {
		$s_key1 = pg_escape_literal($_GET['key1']);
		$h_key1 = '&apos;' . htmlspecialchars($_GET['key1']) . '&apos;';
		$u_key1 = urlencode($_GET['key1']);
		$key2 = intval($_GET['key2']);
		if (($_GET['key1'] == $_SESSION['username'] || $_SESSION['is_administrator']
				&& !is_administrator($s_key1) && $_SESSION['can_edit_as_others']
			 	|| $_SESSION['is_root']) && isset($_GET['delete'])) {
			if (isset($_GET['confirm'])) {
				pgquery("DELETE FROM rules WHERE username = $s_key1 AND id = $key2;");
				echo "For username $h_key1 rule $key2 deleted.<br/>\n";
			} else {
?>
				Are you sure?
<?php
				echo "<a href=\"?key1=$u_key1&amp;key2=$key2&amp;delete&amp;confirm\">Yes</a>\n";
?>
				<a href="?">No</a>
<?php
				exit(0);
			}
		}
	}
}
if ($can_view_rules) {
	if ($_SESSION['is_root']) {
		$result = pgquery('SELECT rules.*, TRUE, proto_name.name FROM rules LEFT OUTER JOIN proto_name
				ON rules.proto_id = proto_name.proto ORDER BY rules.username ASC, rules.id ASC;');
?>
		You are authorized to view (edit) rules for all users.<br/>
<?php
	} elseif ($_SESSION['is_administrator']) {
		$result = pgquery("SELECT rules.*, rules.username = {$_SESSION['s_username']}
				OR NOT users.is_administrator AND {$_SESSION['s_can_edit_as_others']}, proto_name.name
				FROM rules INNER JOIN users ON rules.username = users.username LEFT OUTER JOIN proto_name
				ON rules.proto_id = proto_name.proto WHERE rules.username
				= {$_SESSION['s_username']} OR NOT users.is_administrator
				AND {$_SESSION['s_can_view_as_others']} ORDER BY rules.username ASC, rules.id ASC;");
		echo 'You are authorized to view', $can_edit_rules ? ' (edit)' : '',
				" rules for username {$_SESSION['h2username']}", $_SESSION['can_view_as_others']
				? ' or non-administrators' : '', $_SESSION['can_edit_as_others'] && $can_edit_rules
				? '' : ' (noedit)', ".<br/>\n";
	} elseif ($_SESSION['is_public']) {
		$result = pgquery('SELECT rules.*, TRUE, proto_name.name FROM rules LEFT OUTER JOIN proto_name
				ON rules.proto_id = proto_name.proto WHERE rules.username = \'public\'
				ORDER BY rules.id ASC;');
		echo 'You are authorized to view', $can_edit_rules ? ' (edit)' : '',
				" rules for public user.<br/>\n";
	} else {
		$result = pgquery("SELECT rules.*, rules.username = {$_SESSION['s_username']}
				OR rules.username = 'public' AND {$_SESSION['s_can_edit_as_others']}, proto_name.name
				FROM rules LEFT OUTER JOIN proto_name ON rules.proto_id = proto_name.proto
				WHERE rules.username = {$_SESSION['s_username']} OR rules.username = 'public'
				AND {$_SESSION['s_can_view_as_others']} ORDER BY rules.username ASC, rules.id ASC;");
		echo 'You are authorized to view', $can_edit_rules ? ' (edit)' : '',
				" rules for username {$_SESSION['h2username']}", $_SESSION['can_view_as_others']
				? ' or public user' : '', $_SESSION['can_edit_as_others'] && $can_edit_rules ? ''
				: ' (noedit)', ".<br/>\n";
	}
?>
	Viewing table &quot;rules&quot;.<br/>
	Table ordered by username ascending and id ascending.<br/><br/>
	<table border="1">
		<tbody>
			<tr>
				<th>For username</th>
				<th>this is rule number</th>
				<th>It is activated</th>
				<th>(filter)</th>
				<th>It instructs to</th>
				<th>(modification)</th>
				<th>to execute this</th>
				<th>(query/command 1)</th>
				<th>and to form a new msg from this</th>
				<th>(query/command 2)</th>
				<th>using protocol</th>
				<th>and immediate address</th>
				<th>using insecure port</th>
				<th>and secure port</th>
				<th>using CCF</th>
				<th>and ACF</th>
				<th>using broadcast</th>
				<th>and override implicit rules</th>
				<th>Also activate rule number</th>
				<th>Also deactivate rule number</th>
				<th>Is active?</th>
				<th>Last run on:</th>
<?php
				if ($can_edit_rules) {
?>
					<th>(actions)</th>
<?php
				}
?>
			</tr>
<?php
			if ($can_edit_rules) {
?>
				<tr>
					<td nowrap>
						<input form="insert" type="text" name="username" size="10" required autofocus/>
						,
					</td>
					<td nowrap>
						<input form="insert" type="text" name="id" size="10" required/>
						.
					</td>
					<td nowrap>
						<input form="insert" type="radio" name="send_receive_seconds" value="0"
								checked/>
						on sending when:<br/>
						<input form="insert" type="radio" name="send_receive_seconds" value="1"/>
						on receiving when:<br/>
						<input form="insert" type="radio" name="send_receive_seconds" value="2"/>
						every this amount<br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;of seconds:
					</td>
					<td nowrap>
						<input form="insert" type="text" name="filter" size="10"/>
						.
					</td>
					<td nowrap>
						<input form="insert" type="radio" name="drop_modify_nothing" value="0"
								checked/>
						drop message<br/>
						<input form="insert" type="radio" name="drop_modify_nothing" value="1"/>
						modify message<br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;with this:<br/>
						<input form="insert" type="radio" name="drop_modify_nothing" value="2"/>
						do nothing
					</td>
					<td nowrap>
						<input form="insert" type="text" name="modification" size="10"/>
						,
					</td>
					<td nowrap>
						<input form="insert" type="radio" name="query_command_nothing" value="0"
								checked/>
						SQL query:<br/>
						<input form="insert" type="radio" name="query_command_nothing" value="1"/>
						bash command:<br/>
						<input form="insert" type="radio" name="query_command_nothing" value="2"/>
						(execute nothing)
					</td>
					<td nowrap>
						<input form="insert" type="text" name="query_command_1" size="10"/>
						,
					</td>
					<td nowrap=>
						<input form="insert" type="radio" name="send_inject_query_command_nothing"
								value="0" checked/>
						query and send it:<br/>
						<input form="insert" type="radio" name="send_inject_query_command_nothing"
								value="1"/>
						command and send it:<br/>
						<input form="insert" type="radio" name="send_inject_query_command_nothing"
								value="2"/>
						query and inject it:<br/>
						<input form="insert" type="radio" name="send_inject_query_command_nothing"
								value="3"/>
						command and inject it:<br/>
						<input form="insert" type="radio" name="send_inject_query_command_nothing"
								value="4"/>
						(form nothing)
					</td>
					<td>
						<input form="insert" type="text" name="query_command_2" size="10"/>
					</td>
					<td>
						<input form="insert" type="text" name="proto_id" size="10"/>
					</td>
					<td>
						<input form="insert" type="text" name="imm_addr" size="10"/>
					</td>
					<td>
						<input form="insert" type="text" name="insecure_port" size="10"/>
					</td>
					<td>
						<input form="insert" type="text" name="secure_port" size="10"/>
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
					<td nowrap>
						<input form="insert" type="checkbox" name="override_implicit_rules"/>
						.
					</td>
					<td nowrap>
						<input form="insert" type="text" name="activate" size="10"/>
						.
					</td>
					<td nowrap=>
						<input form="insert" type="text" name="deactivate" size="10"/>
						.
					</td>
					<td>
						<input form="insert" type="checkbox" name="is_active"/>
					</td>
					<td>
						<input form="insert" type="text" name="last_run" value="LOCALTIMESTAMP(0)"
								disabled/>
					</td>
					<td>
						<form id="insert" action="" method="GET">
							<input type="submit" name="insert" value="INSERT"/><br/>
							<input form="insert" type="reset" value="reset"/>
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
			}
			for ($row = pg_fetch_row($result); $row; $row = pg_fetch_row($result)) {
?>
				<tr>
					<td>
<?php
						$username = htmlspecialchars($row[0]);
						$form = "\"update_{$username}_{$row[1]}\"";
						echo "<input form=$form type=\"text\" name=\"username\"
									value=\"$username\" size=\"10\" required/>\n";
?>
						,
					</td>
					<td>
<?php
						echo "<input form=$form type=\"text\" name=\"id\", value=\"{$row[1]}\"
								size=\"10\" required/>\n";
?>
						.
					</td>
					<td>
<?php
						echo "<input form=$form type=\"radio\" name=\"send_receive_seconds\"
								value=\"0\"", $row[2] == '0' ? ' checked' : '', "/>\n";
?>
						on sending when:<br/>
<?php
						echo "<input form=$form type=\"radio\" name=\"send_receive_seconds\"
								value=\"1\"", $row[2] == '1' ? ' checked' : '', "/>\n";
?>
						on receiving when:<br/>
<?php
						echo "<input form=$form type=\"radio\" name=\"send_receive_seconds\"
								value=\"2\"", $row[2] == '2' ? ' checked' : '', "/>\n";
?>
						every this amount<br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;of seconds:
					</td>
					<td>
<?php
						echo "<input form=$form type=\"text\" name=\"filter\" value=\"",
								htmlspecialchars($row[3]), "\" size=\"10\"/>\n";
?>
						.
					</td>
					<td>
<?php
						echo "<input form=$form type=\"radio\" name=\"drop_modify_nothing\"
								value=\"0\"", $row[4] == '0' ? ' checked' : '', "/>\n";
?>
						drop message<br/>
<?php
						echo "<input form=$form type=\"radio\" name=\"drop_modify_nothing\"
								value=\"1\"", $row[4] == '1' ? ' checked' : '', "/>\n";
?>
						modify message<br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;with this:<br/>
<?php
						echo "<input form=$form type=\"radio\" name=\"drop_modify_nothing\"
								value=\"2\"", $row[4] == '2' ? ' checked' : '', "/>\n";
?>
						do nothing
					</td>
					<td>
<?php
						echo "<input form=$form type=\"text\" name=\"modification\" value=\"",
								is_null($row[5]) ? '' : htmlspecialchars($row[5]),
								"\" size=\"10\"/>\n";
?>
						,
					</td>
					<td>
<?php
						echo "<input form=$form type=\"radio\" name=\"query_command_nothing\"
								value=\"0\"", $row[6] == '0' ? ' checked' : '', "/>\n";
?>
						SQL query:<br/>
<?php
						echo "<input form=$form type=\"radio\" name=\"query_command_nothing\"
								value=\"1\"", $row[6] == '1' ? ' checked' : '', "/>\n";
?>
						bash command:<br/>
<?php
						echo "<input form=$form type=\"radio\" name=\"query_command_nothing\"
								value=\"2\"", $row[6] == '2' ? ' checked' : '', "/>\n";
?>
						(execute nothing)
					</td>
					<td>
<?php
						echo "<input form=$form type=\"text\" name=\"query_command_1\" value=\"",
								is_null($row[7]) ? '' : htmlspecialchars($row[7]),
								"\" size=\"10\"/>\n";
?>
						,
					</td>
					<td>
<?php
						echo "<input form=$form type=\"radio\"
								name=\"send_inject_query_command_nothing\" value=\"0\"",
								$row[8] == '0' ? ' checked' : '', "/>\n";
?>
						query and send it:<br/>
<?php
						echo "<input form=$form type=\"radio\"
								name=\"send_inject_query_command_nothing\" value=\"1\"",
								$row[8] == '1' ? ' checked' : '', "/>\n";
?>
						command and send it:<br/>
<?php
						echo "<input form=$form type=\"radio\"
								name=\"send_inject_query_command_nothing\" value=\"2\"",
								$row[8] == '2' ? ' checked' : '', "/>\n";
?>
						query and inject it:<br/>
<?php
						echo "<input form=$form type=\"radio\"
								name=\"send_inject_query_command_nothing\" value=\"3\"",
								$row[8] == '3' ? ' checked' : '', "/>\n";
?>
						command and inject it:<br/>
<?php
						echo "<input form=$form type=\"radio\"
								name=\"send_inject_query_command_nothing\" value=\"4\"",
								$row[8] == '4' ? ' checked' : '', "/>\n";
?>
						(form nothing)
					</td>
					<td>
<?php
						echo "<input form=$form type=\"text\" name=\"query_command_2\" value=\"",
								is_null($row[9]) ? '' : htmlspecialchars($row[9]),
								"\" size=\"10\"/>\n";
?>
					</td>
					<td>
<?php
						echo "<input form=$form type=\"text\" name=\"proto_id\" value=\"",
								is_null($row[23]) ? '' : htmlspecialchars($row[23]),
								"\" size=\"10\"/>\n";
?>
					</td>
					<td>
<?php
						echo "<input form=$form type=\"text\" name=\"imm_addr\" value=\"",
								is_null($row[11]) ? '' : htmlspecialchars(substr($row[11], 2)),
								"\" size=\"10\"/>\n";
?>
					</td>
					<td>
<?php
						echo "<input form=$form type=\"text\" name=\"insecure_port\" value=\"",
								is_null($row[12]) ? '' : $row[12], "\" size=\"10\"/>\n";
?>
					</td>
					<td>
<?php
						echo "<input form=$form type=\"text\" name=\"secure_port\" value=\"",
								is_null($row[13]) ? '' : $row[13], "\" size=\"10\"/>\n";
?>
					</td>
					<td>
<?php
						echo "<input form=$form type=\"checkbox\" name=\"CCF\"",
								$row[14] == 't' ? ' checked' : '', "/>\n";
?>
					</td>
					<td>
<?php
						echo "<input form=$form type=\"checkbox\" name=\"ACF\"",
								$row[15] == 't' ? ' checked' : '', "/>\n";
?>
					</td>
					<td>
<?php
						echo "<input form=$form type=\"checkbox\" name=\"broadcast\"",
								$row[16] == 't' ? ' checked' : '', "/>\n";
?>
					</td>
					<td>
<?php
						echo "<input form=$form type=\"checkbox\" name=\"override_implicit_rules\"",
								$row[17] == 't' ? ' checked' : '', "/>\n";
?>
						.
					</td>
					<td>
<?php
						echo "<input form=$form type=\"text\" name=\"activate\" value=\"",
								is_null($row[18]) ? '' : $row[18], "\" size=\"10\"/>\n";
?>
						.
					</td>
					<td>
<?php
						echo "<input form=$form type=\"text\" name=\"deactivate\" value=\"",
								is_null($row[19]) ? '' : $row[19], "\" size=\"10\"/>\n";
?>
						.
					</td>
					<td>
<?php
						echo "<input form=$form type=\"checkbox\" name=\"is_active\"",
								$row[20] == 't' ? ' checked' : '', "/>\n";
?>
					</td>
					<td>
<?php
						echo "<input form=$form type=\"text\" name=\"last_run\" value=\"{$row[21]}\"/>\n";
?>
					</td>
<?php
					if ($can_edit_rules && $row[22] == 't') {
?>
						<td>
<?php
							echo "<form id=$form action=\"\" method=\"GET\">\n";
								echo "<input type=\"hidden\" name=\"key1\" value=\"$username\"/>\n";
								echo "<input type=\"hidden\" name=\"key2\" value=\"{$row[1]}\"/>\n";
?>
								<input type="submit" name="update" value="UPDATE"/><br/>
								<input type="reset" value="reset"/>
<?php
							echo "</form>";
?>
							<form action="" method="GET">
<?php
								echo "<input type=\"hidden\" name=\"key1\" value=\"$username\"/>\n";
								echo "<input type=\"hidden\" name=\"key2\" value=\"{$row[1]}\"/>\n";
?>
								<input type="submit" name="delete" value="DELETE"/>
							</form>
						</td>
<?php
					}
?>
				</tr>
<?php
			}
?>
		</tbody>
	</table>
	<br/>If &quot;SELECT &lt;filter&gt;;&quot; evaluates to TRUE, the filter is triggered.
	You can use column names HD, ..., CRC, CCF, ACF, broadcast, override, insecure and secure.
	Appropriate FROM is automatically appended.<br/>
	Modification is performed like &quot;UPDATE message SET &lt;semicolon-separated command 1&gt;;
			UPDATE message SET &lt;semicolon-separated command 2&gt;; &lt;...&gt;;&quot;.<br/>
	During SQL queries the current message is stored in table &quot;message&quot; and columns HD, ..., CRC, CCF, ACF,
			broadcast, override, insecure and secure.<br/>
	bash commands are NOT executed as /root/, but as the user who started the database.<br/>
	Filter can be either a number or a string.<br/>
	Leaving a field empty indicates NULL value.<br/>
	Deactivating a rule deletes its timer. Changing a period does not.<br/>
	Id must be unique for user. Smaller value indicates bigger priority.<br/>
	When broadcasting a message any &quot;imm_DST&quot; is ignored.<br/>
	On send and receive rules &quot;last_run&quot; is meaningless.<br/>
	Strings are written without excess quotations, e.g., proto = &apos;tcp&apos;.<br/><br/>
	<a href="index.php">Done</a>
<?php
}
?>