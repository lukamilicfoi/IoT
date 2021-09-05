<?php
require_once 'common.php';
if (checkAuthorization(10, 'view remotes') && !empty($_GET['addr'])) {
	$addr = pg_escape_string($_GET['addr']);
	$result1 = pgquery("SELECT TRUE FROM table_user WHERE tablename = 't$addr';");
	$result2 = pgquery("SELECT TRUE FROM table_user WHERE tablename = 't$addr'
			AND username = {$_SESSION['sql_username']};");
	$result3 = pgquery("SELECT TRUE FROM table_user INNER JOIN users
			ON table_user.username = users.username WHERE table_user.tablename = 't$addr'
			AND NOT users.is_administrator;");
	$h_addr = htmlspecialchars($_GET['addr']);
	$u_addr = urlencode($_GET['addr']);
	if ($_SESSION['is_root'] || !pg_fetch_row($result1) || pg_fetch_row($result2)
				|| $_SESSION['is_administrator'] && pg_fetch_row($result3)) {
		$s_addr = pgescapebytea($_GET['addr']);
		if (!empty($_GET['out_ID'])) {
			$out_ID = intval($_GET['out_ID']);
			pg_free_result(pgquery("UPDATE addr_oID SET out_ID = $out_ID
					WHERE addr = $s_addr;"));
			echo 'out_ID changed for DST X&apos;', $h_addr, "&apos;.<br/>\n";
		} else if (isset($_GET['randomize'])) {
			pg_free_result(pgquery('UPDATE addr_oID SET out_ID = ' . rand(0, 255)
					. " WHERE addr = $s_addr;"));
			echo 'out_ID randomized for DST X&apos;', $h_addr, "&apos;.<br/>\n";
		} else if (!empty($_GET['add_proto'])) {
			$sql_add_proto = pg_escape_literal($_GET['add_proto']);
			$html_add_proto = htmlspecialchars($_GET['add_proto']);
			pg_free_result(pgquery("INSERT INTO SRC_proto(SRC, proto) VALUES($sql_addr',
					(SELECT proto FROM proto_name WHERE name = $sql_add_proto));"));
			echo 'proto &apos;', $sql_add_proto, '&apos; added for SRC X&apos;', $h_addr,
					"&apos;.<br/>\n";
		} else if (!empty($_GET['add_DST'])) {
			$sql_add_DST = pgescapebytea($_GET['add_DST']);
			$html_add_DST = htmlspecialchars($_GET['add_DST']);
			pg_free_result(pgquery("INSERT INTO SRC_DST(SRC, DST) VALUES($sql_addr, $sql_add_DST);"));
			echo 'DST X&apos;', $html_add_dst, '&apos; added for SRC X&apos;', $h_addr,
					"&apos;.<br/>\n";
		} else if (!empty($_GET['remove_proto'])) {
			if (isset($_GET['confirm'])) {
				pg_free_result(pgquery("DELETE FROM SRC_proto WHERE SRC = $sql_addr
						AND proto = (SELECT proto FROM proto_name WHERE name = "
						. pg_escape_literal($_GET['remove_proto']) . ');'));
				echo 'proto &apos;', htmlspecialchars($_GET['remove_proto']),
						'&apos; removed for SRC X&apos;', $h_addr, "&apos;.<br/>\n";
			} else {
?>
				Are you sure?
<?php
				echo '<a href="?addr=', $u_addr, '&amp;remove_proto=',
						urlencode($_GET['remove_proto']), "&amp;confirm\">Yes</a>\n";
				echo '<a href="?addr=', $u_addr, "\">No</a>\n";
				exit(0);
			}
		} else if (!empty($_GET['remove_DST'])) {
			if (isset($_GET['confirm'])) {
				pg_free_result(pgquery("DELETE FROM SRC_DST WHERE SRC = $sql_addr
						AND DST = " . pgescapebytea($_GET['remove_DST']) . ';'));
				echo 'DST X&apos;', htmlspecialchars($_GET['remove_DST']),
						'&apos; removed for SRC X&apos;', htmlspecialchars($_GET['addr']), "&apos;.<br/>\n";
			} else {
?>
				Are you sure?
<?php
				echo '<a href="?addr=', $u_addr, '&amp;remove_DST=',
						urlencode($_GET['remove_DST']), "&amp;confirm\">Yes</a>\n";
				echo '<a href="?addr=', $u_addr, "\">No</a>\n";
				exit(0);
			}
		}
?>
		<form action="" method="GET">
			View destination:
<?php
			$result = pgquery("SELECT DST FROM SRC_DST WHERE SRC = $sql_addr ORDER BY DST ASC;");
			for ($row = pg_fetch_row($result); $row; $row = pg_fetch_row($result)) {
				$str = substr($row[0], 2);
				echo '<a href="view_source_destination_details.php?SRC=', $u_addr, '&amp;DST=',
						$str, '">', $str, "</a>\n";
				echo '<a href="?addr=', urlencode($_GET['addr']), '&amp;remove_DST=',
						$str, "\">(remove)</a>\n";
			}
			if (pg_num_rows($result) == 0) {
?>
				&lt;no destinations&gt;
<?php
			}
			echo '<input type="hidden" name="addr" value="', $h_addr, "\"/>\n";
?>
			<input type="text" name="add_DST"/>
			<input type="submit" value="(add)"/>
		</form>
		outgoing ID
<?php
		pg_free_result($result);
		$result = pgquery("SELECT out_ID FROM addr_oID WHERE addr = $sql_addr;");
		echo '<input form="change" type="hidden" name="addr" value="', $h_addr, "\"/>\n";
		echo '<input form="change" type="text" name="out_ID" value="', pg_fetch_row($result)[0],
				"\"/>\n";
?>
		<input form="change" type="submit" value="change"/>
		<input form="change" type="reset" value="reset"/>
<?php
		echo '<input form="random" type="hidden" name="addr" value="', $h_addr, "\"/>\n";
?>
		<input form="random" type="submit" name="randomize" value="randomize"/>
		<form id="change" action="" method="GET"></form>
		<form id="random" action="" method="GET"></form>
		<form action="" method="GET">
			View protocol:
<?php
			pg_free_result($result);
			$result = pgquery("SELECT proto_name.name FROM SRC_proto INNER JOIN proto_name
					ON SRC_proto.proto = proto_name.proto WHERE SRC = $sql_addr
					ORDER BY SRC_proto.proto ASC;");
			for ($row = pg_fetch_row($result); $row; $row = pg_fetch_row($result)) {
				echo '<a href="view_source_protocol_details.php?SRC=', $u_addr, '&amp;proto=',
						$row[0], '">', $row[0], "</a>\n";
				echo '<a href="?addr=', urlencode($_GET['addr']), '&amp;remove_proto=',
						$row[0], "\">(remove)</a>\n";
			}
			if (pg_num_rows($result) == 0) {
?>
				&lt;no protocols&gt;
<?php
			}
			echo '<input type="hidden" name="addr" value="', $h_addr, "\"/>\n";
?>
			<input type="text" name="add_proto"/>
			<input type="submit" value="(add)"/>
		</form>
		Write destination as a binary string, e.g., abababababababab.<br/>
		Write id as an integer, e.g., 11.<br/>
		Write protocol as a string, e.g., tcp.<br/>
		<a href="view_remotes.php">Done</a>
<?php
		pg_free_result($result);
	}
	pg_free_result($result1);
	pg_free_result($result2);
	pg_free_result($result3);
}
?>