#!/bin/bash
user_fields=(can_view_tables can_edit_tables can_send_messages can_inject_messages
		can_send_queries can_view_rules can_edit_rules can_view_configuration
		can_edit_configuration can_view_permissions can_edit_permissions can_view_remotes
		can_edit_remotes can_execute_rules can_view_users can_edit_users)
echo "\
CREATE USER login;
CREATE USER local;
CREATE USER administrator;
GRANT SELECT(username, password, is_administrator, can_actually_login) ON TABLE users TO login;
GRANT SELECT(username, is_administrator, ${user_fields[@]/%/, } can_actually_login),
		UPDATE(password) ON TABLE users TO local;
GRANT SELECT(username, is_administrator, ${user_fields[@]/%/, } can_actually_login), INSERT,
		UPDATE(password, ${user_fields[@]/%/, } can_actually_login), DELETE ON TABLE users
		TO administrator;
GRANT SELECT ON TABLE current_username TO local, administrator;
GRANT SELECT, INSERT, UPDATE, DELETE ON TABLE configuration, rules, table_reader, table_owner,
		addr_oID, SRC_DST, SRC_proto, ID_TWR, iSRC_TWR TO local, administrator;
GRANT SELECT, INSERT, UPDATE, DELETE, TRUNCATE ON TABLE `psql -U postgres -c "SELECT relname FROM
				pg_class WHERE relname LIKE 't________________' AND relname <>
				'table_constraints';" | grep -Eo 't.{16}'` TO local, administrator;
ALTER DEFAULT PRIVILEGES GRANT SELECT, INSERT, UPDATE, DELETE, TRUNCATE ON TABLES TO local,
		administrator;
GRANT EXECUTE ON PROCEDURE send_inject(send BOOLEAN, message BYTEA, proto_id TEXT, imm_addr BYTEA,
		CCF BOOLEAN, ACF BOOLEAN, broadcast BOOLEAN, override_implicit_rules BOOLEAN) TO local,
		administrator;
GRANT EXECUTE ON PROCEDURE manually_execute_timed_rule(username TEXT, id INTEGER) TO local,
		administrator;
GRANT EXECUTE ON PROCEDURE load_store(load BOOLEAN) TO local, administrator;
GRANT EXECUTE ON PROCEDURE config() TO local, administrator;
GRANT EXECUTE ON PROCEDURE update_permissions() TO local, administrator;
INSERT INTO users(username, password, is_administrator, ${user_fields[@]/%/, } can_actually_login)
		VALUES('root', '`php -r "echo password_hash('root', PASSWORD_DEFAULT);"`',
		`for a in ${!user_fields[@]}; do echo -n "TRUE, "; done` TRUE), ('public',
		'`php -r "echo password_hash('public', PASSWORD_DEFAULT);"`',
		`for a in ${!user_fields[@]}; do echo -n "FALSE, "; done` FALSE);
INSERT INTO configuration(username, forward_messages, use_internet_switch_algorithm, nsecs_id,
		nsecs_src, trust_everyone, default_gateway) VALUES('root', TRUE, TRUE, 600, 36000, FALSE,
		'\\x0000000000000000'), ('public', TRUE, TRUE, 600, 36000, FALSE, '\\x0000000000000000');
" | psql -U postgres
