<?php
require_once('common.php');
checkLogin();
if ($_SESSION['user'] != 'admin') {
	http_response_code(403);
} else {
?>
	<!DOCTYPE html>
	<html>
		<head>
			<title>View permissions (as administrator)</title>
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
		</head>
		<body>
<?php
			$dbconn = pgconnect('host=localhost dbname=postgres user=postgres client_encoding=UTF8');
			if (isset($_GET['truncate'])) {
				if (isset($_GET['confirm'])) {
					pg_free_result(pgquery("TRUNCATE TABLE table_address;"));
					echo "Table &quot;table_address&quot; truncated.</br>\n";
				} else {
?>
					Are you sure?
					<a href="?truncate&amp;confirm">Yes</a>\n";
					<a href="">No</a>\n";
<?php
					pg_close($dbconn);
					exit(0);
				}
			} else if (isset($_GET['insert'])) {
				pg_free_result(pgquery("INSERT INTO table_address(table, address) VALUES('{$_GET['table']}', E'\\\\x{$_GET['address']}');"));
				echo 'Row ', htmlspecialchars($_GET['table']), ', ', htmlspecialchars($_GET['address']), " inserted.<br/>\n";
			} else if (isset($_GET['key1']) && isset($_GET['key2'])) {
				if (isset($_GET['update'])) {
					pg_free_result(pgquery("UPDATE table_address SET (table, address) = ('{$_GET['table']}', E'\\\\x{$_GET['address']}');"));
					echo 'Row ', htmlspecialchars($_GET['table']), ', ', htmlspecialchars($_GET['address']), " updated.<br/>\n";
				} else if (isset($_GET['delete'])) {
					if (isset($_GET['confirm'])) {
						pg_free_result(pgquery("DELETE FROM table_address WHERE table = '{$_GET['table']}' AND address = E'\\\\x{$_GET['address']}';"));
						echo 'Row ', htmlspecialchars($_GET['table']), ', ', htmlspecialchars($_GET['address']), " deleted.<br/>\n";
					} else {
?>
						Are you sure?
<?php
						echo '<a href="?key1=', urlencode($_GET['table']), '&amp;key2=', urlencode($_GET['address']), "&amp;delete&amp;confirm\">Yes</a>\n";
						echo "<a href=\"\">No</a>\n";
						pg_close($dbconn);
						exit(0);
					}
				}
			}
			$result = pgquery('TABLE table_address ORDER BY table ASC, address ASC;');
?>
			Viewing table &quot;table_address&quot;.
			<table border="1">
				<tbody>
					<tr>
						<th>Table</th>
						<th>Address</th>
						<th>Actions</th>
					</tr>
					<tr>
						<td>
							<input form="insert" type="text" name="table"/>
						</td>
						<td>
							<input form="insert" type="text" name="address"/>
						</td>
						<td>
							<form id="insert" action="" method="GET">
								<input type="submit" name="insert" value="INSERT"/><br/>
								<input type="reset" value="reset"/>
							</form>
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
								$str = substr($row[1], 2);
								echo "<input form=\"update{$row[0]}{$str}\" type=\"text\" name=\"table\" value=\"{$row[0]}\"/>\n";
?>
							</td>
							<td>
<?php
								echo "<input form=\"update{$str}\" type=\"text\" name=\"address\" value=\"{$str}\"/>\n";
?>
							</td>
							<td>
<?php
								echo "<form id=\"update{$row[0]}{$str}\" action=\"\" method=\"GET\">\n";
									echo "<input type=\"hidden\" name=\"key1\" value=\"{$row[0]}\"/>\n";
									echo "<input type=\"hidden\" name=\"key2\" value=\"{$str}\"/>\n";
									echo "<input type=\"submit\" name=\"update\" value=\"UPDATE\"/>\n";
									echo "<input type=\"reset\" value=\"reset\"/>\n";
								echo "</form>\n";
?>
								<form action="" method="GET">
<?php
									echo "<input type=\"hidden\" name=\"key1\" value=\"{$row[0]}\"/>\n";
									echo "<input type=\"hidden\" name=\"key2\" value=\"{$str}\"/>\n";
									echo "<input type=\"submit\" name=\"delete\" value=\"DELETE\"/>\n";
?>
								</form>
							</td>
						</tr>
<?php
					}
?>
				</tbody>
			</table>
			<a href="index.php">Done</a>
<?php
			pg_free_result($result);
			pg_close($dbconn);
?>
		</body>
	</html>
<?php
}
?>