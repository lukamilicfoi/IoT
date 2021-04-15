<?php
require_once 'common.php';
checkLogin();
?>
<!DOCTYPE html>
<html>
	<head>
		<title>View rules</title>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	</head>
	<body>
<?php
		$dbconn = pgconnect('host=localhost dbname=postgres user=' . ($_SESSION['user'] == 'admin' ? 'postgres' : 'luka') . ' client_encoding=UTF8');
		if (checkAuthorization(6, 'view rules')) {
			if (isset($_GET['truncate'])) {
				if (isset($_GET['confirm'])) {
					$result = pgquery('TRUNCATE TABLE rules CASCADE;');
?>
					Table &quot;rules&quot; truncated.<br/>
<?php
					pg_free_result($result);
				} else {
?>
					Are you sure?
					<a href="?truncate&amp;confirm">Yes</a>
					<a href="?">No</a>
<?php
					pg_close($dbconn);
					exit(0);
				}
			} else if (isset($_GET['insert'])) {
				$result = pgquery("INSERT INTO rules(id, send_receive_seconds, filter, drop_modify_nothing, modification, query_command_nothing, query_command_1, send_inject_query_command_nothing, query_command_2, proto_id, imm_addr, CCF, ACF, broadcast, override_implicit_rules, activate, deactivate, active) VALUES({$_GET['id']}, {$_GET['send_receive_seconds']}, {$_GET['filter']}, {$_GET['drop_modify_nothing']}, {$_GET['modification']}, {$_GET['query_command_nothing']}, {$_GET['query_command_1']}, {$_GET['send_inject_query_command_nothing']}, {$_GET['query_command_2']}, {$_GET['proto_id']}, E'\\\\x" . substr($_GET['imm_addr'], 2) . ', ' . (isset($_GET['CCF']) ? 'TRU' : 'FALS') . 'E, ' . (isset($_GET['ACF']) ? 'TRU' : 'FALS') . 'E, ' . (isset($_GET['broadcast']) ? 'TRU' : 'FALS') . 'E, ' . (isset($_GET['override_implicit_rules']) ? 'TRU' : 'FALS') . "E, {$_GET['activate']}, {$_GET['deactivate']}, " . (isset($_GET['active']) ? 'TRU' : 'FALS') . 'E);');
				echo 'Rule ', htmlspecialchars($_GET['id']), " inserted.<br/>\n";
				pg_free_result($result);
			} else if (isset($_GET['key'])) {
				if (isset($_GET['update'])) {
					$result = pgquery("UPDATE rules SET (id, send_receive_seconds, filter, drop_modify_nothing, modification, query_command_nothing, query_command_1, send_inject_query_command_nothing, query_command_2, proto_id, imm_addr, CCF, ACF, broadcast, override_implicit_rules, activate, deactivate, active) = ({$_GET['id']}, {$_GET['send_receive_seconds']}, {$_GET['filter']}, {$_GET['drop_modify_nothing']}, {$_GET['modification']}, {$_GET['query_command_nothing']}, {$_GET['query_command_2']}, {$_GET['send_inject_query_command_nothing']}, {$_GET['query_command_2']}, {$_GET['proto_id']}, E'\\\\x" . substr($_GET['imm_addr'], 2) . ', ' . (isset($_GET['CCF']) ? 'TRU' : 'FALS') . 'E, ' . (isset($_GET['ACF']) ? 'TRU' : 'FALS') . 'E, ' . (isset($_GET['broadcast']) ? 'TRU' : 'FALS') . 'E, ' . (isset($_GET['override_implicit_rules']) ? 'TRU' : 'FALS') . "E, {$_GET['activate']}, {$_GET['deactivate']}, " . (isset($_GET['active']) ? 'TRU' : 'FALS') . "E) WHERE id = {$_GET['key']};");
					echo 'Rule ', htmlspecialchars($_GET['key']), " updated.<br/>\n";
					pg_free_result($result);
				} else if (isset($_GET['delete'])) {
					if (isset($_GET['confirm'])) {
						$result = pgquery("DELETE FROM rules WHERE id = {$_GET['key']};");
						echo 'Rule ', htmlspecialchars($_GET['key']), " deleted.<br/>\n";
						pg_free_result($result);
					} else {
?>
						Are you sure?
<?php
						echo '<a href="?key=', urlencode($_GET['key']), "&amp;delete&amp;confirm\">Yes</a>\n";
?>
						<a href="?">No</a>
<?php
						pg_close($dbconn);
						exit(0);
					}
				}
			}
			$result = pgquery('TABLE rules ORDER BY id ASC;');
?>
			Viewing table &quot;rules&quot;.
			<table border="1">
				<tbody>
					<tr>
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
					</tr>
					<tr>
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
					</tr>
					<tr>
						<th>This is rule number</th>
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
						<th>(actions)</th>
					</tr>
					<tr>
						<td>
							<form id="insert" action="" method="GET">
								<input type="text" name="id"/>
							</form>
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
							<input form="insert" type="submit" name="insert" value="INSERT"/><br/>
							<input form="insert" type="reset" value="reset"/><br/>
							<form action="" method="GET">
								<input type="submit" name="truncate" value="TRUNCATE"/>
							</form>
						</td>
					</tr>
<?php
					for ($row = pg_fetch_row($result); $row; $row = pg_fetch_row($result)) {
?>
						<tr>
							<td>
<?php
								echo "<form id=\"update{$row[0]}\" action=\"\" method=\"GET\">\n";
									echo "<input type=\"text\" name=\"id\" value=\"{$row[0]}\"/>\n";
								echo "</form>\n";
?>
								.
							</td>
							<td>
<?php
								echo "<input form=\"update{$row[0]}\" type=\"radio\" name=\"send_receive_seconds\" value=\"0\"", $row[1] == '0' ? ' checked="checked"' : '', "/>\n";
?>
								on sending when:<br/>
<?php
								echo "<input form=\"update{$row[0]}\" type=\"radio\" name=\"send_receive_seconds\" value=\"1\"", $row[1] == '1' ? ' checked="checked"' : '', "/>\n";
?>
								on receiving when:<br/>
<?php
								echo "<input form=\"update{$row[0]}\" type=\"radio\" name=\"send_receive_seconds\" value=\"2\"", $row[1] == '2' ? ' checked="checked"' : '', "/>\n";
?>
								every this amount of seconds:
							</td>
							<td>
<?php
								echo "<input form=\"update{$row[0]}\" type=\"text\" name=\"filter\" value=\"&apos;", htmlspecialchars($row[2]), "&apos;\"/>\n";
?>
								.
							</td>
							<td>
<?php
								echo "<input form=\"update{$row[0]}\" type=\"radio\" name=\"drop_modify_nothing\" value=\"0\"", $row[3] == '0' ? ' checked="checked"' : '', "/>\n";
?>
								drop message<br/>
<?php
								echo "<input form=\"update{$row[0]}\" type=\"radio\" name=\"drop_modify_nothing\" value=\"1\"", $row[3] == '1' ? ' checked="checked"' : '', "/>\n";
?>
								modify message with this:<br/>
<?php
								echo "<input form=\"update{$row[0]}\" type=\"radio\" name=\"drop_modify_nothing\" value=\"2\"", $row[3] == '2' ? ' checked="checked"' : '', "/>\n";
?>
								do nothing
							</td>
							<td>
<?php
								echo "<input form=\"update{$row[0]}\" type=\"text\" name=\"modification\" value=\"&apos;", htmlspecialchars($row[4]), "&apos;\"/>\n";
?>
								,
							</td>
							<td>
<?php
								echo "<input form=\"update($row[0]}\" type=\"radio\" name=\"query_command_nothing\" value=\"0\"", $row[5] == '0' ? ' checked="checked"' : '', "/>\n";
?>
								SQL query:<br/>
<?php
								echo "<input form=\"update{$row[0]}\" type=\"radio\" name=\"query_command_nothing\" value=\"1\"", $row[5] == '1' ? ' checked="checked"' : '', "/>\n";
?>
								bash command:<br/>
<?php
								echo "<input form=\"update{$row[0]}\" type=\"radio\" name=\"query_command_nothing\" value=\"2\"", $row[5] == '2' ? ' checked="checked"' : '', "/>\n";
?>
								(execute nothing)
							</td>
							<td>
<?php
								echo "<input form=\"update{$row[0]}\" type=\"text\" name=\"query_command_1\" value=\"&apos;", htmlspecialchars($row[6]), "&apos;\"/>\n";
?>
								,
							</td>
							<td>
<?php
								echo "<input form=\"update{$row[0]}\" type=\"radio\" name=\"send_inject_query_command_nothing\" value=\"0\"", $row[7] == '0' ? ' checked="checked"' : '', "/>\n";
?>
								query and send it:<br/>
<?php
								echo "<input form=\"update{$row[0]}\" type=\"radio\" name=\"send_inject_query_command_nothing\" value=\"1\"", $row[7] == '1' ? ' checked="checked"' : '', "/>\n";
?>
								command and send it:<br/>
<?php
								echo "<input form=\"update{$row[0]}\" type=\"radio\" name=\"send_inject_query_command_nothing\" value=\"2\"", $row[7] == '2' ? ' checked="checked"' : '', "/>\n";
?>
								query and inject it:<br/>
<?php
								echo "<input form=\"update{$row[0]}\" type=\"radio\" name=\"send_inject_query_command_nothing\" value=\"3\"", $row[7] == '3' ? ' checked="checked"' : '', "/>\n";
?>
								command and inject it:<br/>
<?php
								echo "<input form=\"update{$row[0]}\" type=\"radio\" name=\"send_inject_query_command_nothing\" value=\"4\"", $row[7] == '4' ? ' checked="checked"' : '', "/>\n";
?>
								(form nothing)
							</td>
							<td>
<?php
								echo "<input form=\"update{$row[0]}\" type=\"text\" name=\"query_command_2\" value=\"&apos;", htmlspecialchars($row[8]), "&apos;\"/>\n";
?>
							</td>
							<td>
<?php
								echo "<input form=\"update{$row[0]}\" type=\"text\" name=\"proto_id\" value=\"&apos;", htmlspecialchars($row[9]), "&apos;\"/>\n";
?>
							</td>
							<td>
<?php
								echo "<input form=\"update{$row[0]}\" type=\"text\" name=\"imm_addr\" value=\"X&apos;", htmlspecialchars(strtoupper(substr($row[10], 2))), "&apos;\"/>\n";
?>
							</td>
							<td>
<?php
								echo "<input form=\"update{$row[0]}\" type=\"checkbox\" name=\"CCF\"", $row[11] == 't' ? ' checked="checked"' : '', "/>\n";
?>
							</td>
							<td>
<?php
								echo "<input form=\"update{$row[0]}\" type=\"checkbox\" name=\"ACF\"", $row[12] == 't' ? ' checked="checked"' : '', "/>\n";
?>
							</td>
							<td>
<?php
								echo "<input form=\"update{$row[0]}\" type=\"checkbox\" name=\"broadcast\"", $row[13] == 't' ? ' checked="checked"' : '', "/>\n";
?>
							</td>
							<td>
<?php
								echo "<input form=\"update{$row[0]}\" type=\"checkbox\" name=\"override_implicit_rules\"", $row[14] == 't' ? ' checked="checked"' : '', "/>\n";
?>
								.
							</td>
							<td>
<?php
								echo "<input form=\"update{$row[0]}\" type=\"text\" name=\"activate\" value=\"", $row[14] === null ? 'NULL' : $row[14], "\"/>\n";
?>
								.
							</td>
							<td>
<?php
								echo "<input form=\"update{$row[0]}\" type=\"text\" name=\"deactivate\" value=\"", $row[15] === null ? 'NULL' : $row[15], "\"/>\n";
?>
								.
							</td>
							<td>
<?php
								echo "<input form=\"update{$row[0]}\" type=\"checkbox\" name=\"active\"", $row[16] == 't' ? ' checked="checked"' : '', "/>\n";
?>
								.
							</td>
							<td>
<?php
								echo "<input form=\"update{$row[0]}\" type=\"hidden\" name=\"key\" value=\"{$row[0]}\"/>\n";
								echo "<input form=\"update{$row[0]}\" type=\"submit\" name=\"update\" value=\"UPDATE\"/>\n";
								echo "<input form=\"update{$row[0]}\" type=\"reset\" value=\"reset\"/>\n";
?>
								<form action="" method="GET">
<?php
									echo "<input type=\"hidden\" name=\"key\" value=\"{$row[0]}\"/>\n";
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
			<a href="index.php">Done</a>
<?php
			pg_free_result($result);
		}
		pg_close($dbconn);
?>
	</body>
</html>