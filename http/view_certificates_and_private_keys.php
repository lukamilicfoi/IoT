<?php
require_once 'common.php';
$can_view = check_authorization('can_view_certificates_and_private_keys',
		'view certificates and private keys');
$can_edit = check_authorization('can_edit_certificates_and_private_keys',
		'edit certificates and private keys');
if ($can_edit) {
	if (isset($_GET['truncate']) && !vacuous($_GET['type']) && $_SESSION['is_root']) {
		$type = $_GET['type'] == 'certificate' ? 'certificate' : 'privateKey';
		if (isset($_GET['confirm'])) {
			array_map('unlink', glob('/home/luka/{$type}s/*'));
			echo "{$type}s truncated.<br/>\n";
		} else {
?>
			Are you sure?
<?php
			echo "<a href=\"?type=$type&amp;truncate&amp;confirm\">Yes</a>\n";
?>
			<a href="?">No</a>
<?php
			exit(0);
		}
	} elseif (!vacuous($_POST['eui']) && !vacuous($_POST['type'])) {
		$type = $_POST['type'] == 'certificate' ? 'certificate' : 'privateKey';
		$h_eui = 'X&apos;' . htmlspecialchars($_POST['eui']) . '&apos;';
		$p_eui = "/home/luka/{$type}s/" . str_replace($_POST['eui'], '/', '\\/') . '.pem';
		$eui_owner = find_owner(pg_escape_literal('t' . $_POST['eui']));
		$eui_owner_is_administrator = is_administrator(pg_escape_literal($eui_owner));
		if ($eui_owner == $_SESSION['username'] || ($eui_owner == 'public'
				|| $_SESSION['is_administrator'] && !$eui_owner_is_administrator)
				&& $_SESSION['can_edit_as_others'] || $_SESSION['is_root']
				&& isset($_POST['insert']) && !file_exists($p_eui)) {
			move_uploaded_file($_FILES['file']['tmp_name'], $p_eui);
			echo "$type $h_eui inserted.<br/>\n";
		} elseif ($eui_owner == $_SESSION['username'] || ($eui_owner == 'public'
				|| $_SESSION['is_administrator'] && !$eui_owner_is_administrator)
				&& $_SESSION['can_edit_as_others'] || $_SESSION['is_root']
				&& isset($_POST['update']) && file_exists($p_eui)) {
			unlink($p_eui);
			move_uploaded_file($_FILES['file']['tmp_name'], $p_eui);
			echo "$type $h_eui updated.<br/>\n";
		}
	} elseif (!vacuous($_GET['eui']) && !vacuous($_GET['type'])) {
		$type = $_GET['type'] == 'certificate' ? 'certificate' : 'privateKey';
		$h_eui = 'X&apos;' . htmlspecialchars($_GET['eui']) . '&apos;';
		$p_eui = "/home/luka/{$type}s/" . str_replace($_GET['eui'], '/', '\\/') . '.pem';
		$u_eui = urlencode($_GET['eui']);
		$eui_owner = find_owner(pg_escape_literal('t' . $_GET['eui']));
		if ($eui_owner == $_SESSION['username'] || ($eui_owner == 'public'
				|| $_SESSION['is_administrator'] && !is_administrator(pg_escape_literal($eui_owner))
				&& $_SESSION['can_edit_as_others'] || $_SESSION['is_root']
				&& isset($_GET['delete']) && file_exists($p_eui))) {
			if (isset($_GET['confirm'])) {
				unlink($p_eui);
				echo "$type $h_eui deleted.<br/>\n";
			} else {
?>
				Are you sure?
<?php
				echo "<a href=\"?eui=$u_eui&amp;type=$type&amp;delete&amp;confirm\">Yes</a>\n";
?>
				<a href="?">No</a>
<?php
				exit(0);
			}
		}
	}
}
if ($can_view) {
	if (!vacuous($_GET['eui']) && !vacuous($_GET['type'])) {
		$type = $_GET['type'] == 'certificate' ? 'certificate' : 'privateKey';
		$eui = "/home/luka/{$type}s/" . str_replace($_GET['eui'], '/', '\\') . '.pem';
		$eui_owner = find_owner(pg_escape_literal('t' . $_GET['eui']));
		if ($eui_owner == $_SESSION['username'] || ($eui_owner == 'public'
				|| $_SESSION['is_administrator'] && !is_administrator(pg_escape_literal($eui_owner))
				&& $_SESSION['can_edit_as_others'] || $_SESSION['is_root']
				&& isset($_GET['view']) && file_exists($p_eui))) {
			readfile($eui);
			exit(0);
		}
	}
	if ($_SESSION['is_root']) {
		$result = pgquery('SELECT tablename, TRUE FROM table_owner
				WHERE tablename LIKE \'t________________\' ORDER BY tablename ASC;');
?>
		You are authorized to view (edit) certificates and private keys for all EUIs.<br/>
<?php
	} elseif ($_SESSION['is_administrator']) {
		$result = pgquery("SELECT DISTINCT table_owner.tablename, TRUE FROM table_owner
				INNER JOIN users ON table_owner.username = users.username
				WHERE table_owner.tablename LIKE 't________________'
				AND (table_owner.username = {$_SESSION['s_username']}
				OR NOT users.is_administrator AND {$_SESSION['s_can_edit_as_others']}) UNION ALL
				SELECT DISTINCT table_reader.tablename, FALSE FROM table_reader INNER JOIN users
				ON table_reader.username = users.username
				WHERE table_reader.tablename LIKE 't________________'
				AND (table_reader.username = {$_SESSION['s_username']}
				OR NOT users.is_administrator AND {$_SESSION['s_can_view_as_others']})
				ORDER BY tablename ASC;");
		echo "You are authorized to <br/>\n";
	} elseif ($_SESSION['is_public']) {
		$result = pgquery('SELECT tablename, TRUE FROM table_owner
				WHERE tablename LIKE \'t________________\' AND username = \'public\' UNION ALL
				SELECT tablename, FALSE FROM table_reader
				WHERE tablename LIKE \'t________________\' AND username = \'public\'
				ORDER BY tablename ASC;');
		echo "You are authorized to <br>\n";
	} else {
		$result = pgquery("SELECT DISTINCT tablename, TRUE FROM table_owner
				WHERE tablename LIKE 't________________'
				AND (username = {$_SESSION['s_username']} OR username = \'public\'
				AND {$_SESSION['s_can_edit_as_others']}) UNION ALL SELECT DISTINCT tablename,
				FALSE FROM table_reader WHERE tablename LIKE 't________________'
				AND (username = {$_SESSION['s_username']}
				OR username = \'public\' AND {$_SESSION['s_can_view_as_others']})
				ORDER BY tablename ASC;");
		echo "You are authorized to <br>\n";
	}
?>
	<br/>Viewing certificates.<br/>
	Ordered by EUI ascending.<br/>
	<a href="?truncate&amp;type=certificate">TRUNCATE</a>
	<table border="1">
		<tbody>
			<tr>
				<th>EUI</th>
				<th>Certificate</th>
<?php
				if ($can_edit) {
?>
					<th>Actions</th>
<?php
				}
?>
			</tr>
<?php
			$last = '';
			for ($row = pg_fetch_row($result); $row; $row = pg_fetch_row($result)) {
				if ($row[0] != $last) {
?>
					<tr>
						<td>
<?php
							$h_eui = htmlspecialchars(substr($row[0], 1));
							$form1 = "\"{$h_eui}get1\"";
							$form2 = "\"{$h_eui}post1\"";
							echo "<input form=$form1 type=\"text\" name=\"eui\"
									value=\"$h_eui\" readonly/>\n";
							echo "<input form=$form1 type=\"hidden\" name=\"type\"
									value=\"certificate\"/>\n";
							echo "<input form=$form2 type=\"hidden\" name=\"eui\"
									value=\"$h_eui\"/>\n";
							echo "<input form=$form2 type=\"hidden\" name=\"type\"
									value=\"privateKey\"/>\n";
?>
						</td>
						<td>
<?php
							$exists = file_exists("/home/luka/certificates/$h_eui.pem");
							if ($exists) {
								echo "<a href=\"?eui=$h_eui&amp;type=certificate&amp;view\"
										/>View</a>\n";
							} else {
?>
								&lt;None&gt;
<?php
							}
?>
						</td>
<?php
						if ($can_edit && $row[1] == 't') {
?>
							<td>
<?php
								echo "<input form=$form2 type=\"file\" name=\"file\"/>\n";
								if ($exists) {
									echo "<input form=$form2 type=\"submit\" name=\"update\"
											value=\"UPDATE\"/>\n";
									echo "<input form=$form1 type=\"submit\" name=\"delete\"
											value=\"DELETE\"/>\n";
									echo "<form id=$form1 action=\"?\" method=\"GET\"/></form>\n";
								} else {
									echo "<input form=$form2 type=\"submit\" name=\"insert\"
											value=\"INSERT\"/>\n";
								}
								echo "<form id=$form2 action=\"?\" method=\"POST\"
										enctype=\"multipart/form-data\"/></form>\n";
?>
							</td>
<?php
						}
?>
					</tr>
<?php
					$last = $row[0];
				}
			}
?>
		</tbody>
	</table><br/>
	Viewing private keys.<br/>
	Ordered by EUI ascending.<br/>
	<a href="?truncate&amp;type=privateKey">TRUNCATE</a>
	<table border="1">
		<tbody>
			<tr>
				<th>EUI</th>
				<th>Private key</th>
<?php
				if ($can_edit) {
?>
					<th>(actions)</th>
<?php
				}
?>
			</tr>
<?php
			$last = '';
			for ($row = pg_fetch_row($result, pg_num_rows($result) == 0 ? null : 0); $row; $row = pg_fetch_row($result)) {
				if ($row[0] != $last) {
?>
					<tr>
						<td>
<?php
							$h_eui = htmlspecialchars(substr($row[0], 1));
							$form1 = "\"{$h_eui}get2\"";
							$form2 = "\"{$h_eui}post2\"";
							echo "<input form=$form1 type=\"text\" name=\"eui\"
									value=\"$h_eui\" readonly/>\n";
							echo "<input form=$form1 type=\"hidden\" name=\"type\"
									value=\"privateKey\"/>\n";
							echo "<input form=$form2 type=\"hidden\" name=\"eui\"
									value=\"$h_eui\"/>\n";
							echo "<input form=$form2 type=\"hidden\" name=\"type\"
									value=\"privateKey\"/>\n";
?>
						</td>
						<td>
<?php
							$exists = file_exists("/home/luka/privateKeys/{$row[0]}.pem");
							if ($exists) {
								echo "<a href=\"?eui=$h_eui&amp;type=privateKey&amp;view\"
										/>View</a>\n";
							} else {
?>
								&lt;None&gt;
<?php
							}
?>
						</td>
<?php
						if ($can_edit && $row[1] == 't') {
?>
							<td>
<?php
								echo "<input form=$form2 type=\"file\" name=\"file\"/>\n";
								if ($exists) {
									echo "<input form=$form2 type=\"submit\" name=\"update\"
											value=\"UPDATE\"/>\n";
									echo "<input form=$form1 type=\"submit\" name=\"delete\"
											value=\"DELETE\"/>\n";
									echo "<form id=$form1 action=\"?\"method=\"GET\"></form>\n";
								} else {
									echo "<input form=$form2 type=\"submit\" name=\"insert\"
											value=\"INSERT\"/>\n";
								}
								echo "<form id=$form2 action=\"?\" method=\"POST\"></form>\n";
?>
							</td>
<?php
						}
?>
					</tr>
<?php
					$last = $row[0];
				}
			}
?>
		</tbody>
	</table><br/>
	<a href="index.php">Done</a>
<?php
}
?>