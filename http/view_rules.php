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
					$result = pgquery('TRUNCATE TABLE rules;');
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
				$result = pgquery("INSERT INTO rules(id, sendReceive, filter, dropModify, modification, prequery, message, activate, deactivate, active) VALUES({$_GET['id']}, {$_GET['sendReceive']}, {$_GET['filter']}, {$_GET['dropModify']}, {$_GET['modification']}, {$_GET['prequery']}, E'\\\\x" . substr($_GET['message'], 2) . ", {$_GET['activate']}, {$_GET['deactivate']}, " . (isset($_GET['active']) ? 'TRU' : 'FALS') . "E);");
				echo 'Rule ', htmlspecialchars($_GET['id']), " inserted.<br/>\n";
				pg_free_result($result);
			} else if (isset($_GET['key'])) {
				if (isset($_GET['update'])) {
					$result = pgquery("UPDATE rules SET (id, sendReceive, filter, dropModify, modification, prequery, message, activate, deactivate, active) = ({$_GET['id']}, {$_GET['sendReceive']}, {$_GET['filter']}, {$_GET['dropModify']}, {$_GET['modification']}, {$_GET['prequery']}, E'\\\\x" . substr($_GET['message'], 2) . ", {$_GET['activate']}, {$_GET['deactivate']}, " . (isset($_GET['active']) ? 'TRU' : 'FALS') . "E) WHERE id = {$_GET['key']};");
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
						<th>Number (INT)</th>
						<th>Effect</th>
						<th>Filter (VARCHAR)</th>
						<th>Action</th>
						<th>Modification (VARCHAR)</th>
						<th>Prequery (VARCHAR)</th>
						<th>Msg to send (VARBINARY)</th>
						<th>Activate (INT)</th>
						<th>Deactivate (INT) </th>
						<th>Active</th>
						<th>Actions</th>
					</tr>
					<tr>
						<td>
							<form id="insert" action="" method="GET">
								<input type="text" name="id"/>
							</form>
						</td>
						<td>
							<input form="insert" type="radio" name="sendReceive" value="0" checked="checked"/>
							sending<br/>
							<input form="insert" type="radio" name="sendReceive" value="1"/>
							receiving<br/>
							<input form="insert" type="radio" name="sendReceive" value="2"/>
							both
						</td>
						<td>
							<input form="insert" type="text" name="filter"/>
						</td>
						<td>
							<input form="insert" type="radio" name="dropModify" value="0" checked="checked"/>
							drop<br/>
							<input form="insert" type="radio" name="dropModify" value="1"/>
							modify<br/>
							<input form="insert" type="radio" name="dropModify" value="2"/>
							nothing
						</td>
						<td>
							<input form="insert" type="text" name="modification"/>
						</td>
						<td>
							<input form="insert" type="text" name="prequery"/>
						</td>
						<td>
							<input form="insert" type="text" name="message"/>
						</td>
						<td>
							<input form="insert" type="text" name="activate"/>
						</td>
						<td>
							<input form="insert" type="text" name="deactivate"/>
						</td>
						<td>
							<input form="insert" type="checkbox" name="active"/>
						</td>
						<td>
							<input form="insert" type="submit" name="insert" value="INSERT"/><br/>
							<input form="insert" type="reset" value="reset"/>
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
							</td>
							<td>
<?php
								echo "<input form=\"update{$row[0]}\" type=\"radio\" name=\"sendReceive\" value=\"0\"", $row[1] == 0 ? ' checked="checked"' : '', "/>\n";
?>
								sending<br/>
<?php
								echo "<input form=\"update{$row[0]}\" type=\"radio\" name=\"sendReceive\" value=\"1\"", $row[1] == 1 ? ' checked="checked"' : '', "/>\n";
?>
								receiving<br/>
<?php
								echo "<input form=\"update{$row[0]}\" type=\"radio\" name=\"sendReceive\" value=\"2\"", $row[1] == 2 ? ' checked="checked"' : '', "/>\n";
?>
								both
							</td>
							<td>
<?php
								echo "<input form=\"update{$row[0]}\" type=\"text\" name=\"filter\" value=\"&apos;", htmlspecialchars($row[2]), "&apos;\"/>\n";
?>
							</td>
							<td>
<?php
								echo "<input form=\"update{$row[0]}\" type=\"radio\" name=\"dropModify\" value=\"0\"", $row[3] == 0 ? ' checked="checked"' : '', "/>\n";
?>
								drop<br/>
<?php
								echo "<input form=\"update{$row[0]}\" type=\"radio\" name=\"dropModify\" value=\"1\"", $row[3] == 1 ? ' checked="checked"' : '', "/>\n";
?>
								modify<br/>
<?php
								echo "<input form=\"update{$row[0]}\" type=\"radio\" name=\"dropModify\" value=\"2\"", $row[3] == 2 ? ' checked="checked"' : '', "/>\n";
?>
								nothing
							</td>
							<td>
<?php
								echo "<input form=\"update{$row[0]}\" type=\"text\" name=\"modification\" value=\"&apos;", htmlspecialchars($row[4]), "&apos;\"/>\n";
?>
							</td>
							<td>
<?php
								echo "<input form=\"update{$row[0]}\" type=\"text\" name=\"prequery\" value=\"&apos;", htmlspecialchars($row[5]), "&apos;\"/>\n";
?>
							</td>
							<td>
<?php
								echo "<input form=\"update{$row[0]}\" type=\"text\" name=\"message\" value=\"X&apos;", htmlspecialchars(strtoupper(substr($row[6], 2))), "'\"/>\n";
?>
							</td>
							<td>
<?php
								echo "<input form=\"update{$row[0]}\" type=\"text\" name=\"activate\" value=\"", $row[7] === null ? 'NULL' : $row[7], "\"/>\n";
?>
							</td>
							<td>
<?php
								echo "<input form=\"update{$row[0]}\" type=\"text\" name=\"deactivate\" value=\"", $row[8] === null ? 'NULL' : $row[8], "\"/>\n";
?>
							</td>
							<td>
<?php
								echo "<input form=\"update{$row[0]}\" type=\"checkbox\" name=\"active\"", $row[9] == 't' ? ' checked="checked"' : '', "/>\n";
?>
							</td>
							<td>
<?php
								echo "<input form=\"update{$row[0]}\" type=\"hidden\" name=\"key\" value=\"{$row[0]}\"/>\n";
								echo "<input form=\"update{$row[0]}\" type=\"submit\" name=\"update\" value=\"UPDATE\"/><br/>\n";
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
			<a href="index.php">Done</a>
<?php
			pg_free_result($result);
		}
		pg_close($dbconn);
?>
	</body>
</html>