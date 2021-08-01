#!/bin/bash
echo "\
CREATE USER login;
CREATE USER local;
CREATE USER administrator;
GRANT SELECT(username, password, can_actually_login, is_administrator) ON TABLE users TO login;
GRANT SELECT(username, is_administrator, can_view_tables, can_send_messages, can_inject_messages,
		can_send_queries, can_view_rules, can_view_configuration, can_view_permissions,
		can_view_remotes, can_execute_rules, can_actually_login), UPDATE(password)
		ON TABLE users TO local;
GRANT SELECT(username, is_administrator, can_view_tables, can_send_messages, can_inject_messages,
		can_send_queries, can_view_rules, can_view_configuration, can_view_permissions,
		can_view_remotes, can_execute_rules, can_actually_login), INSERT, UPDATE, DELETE
		ON TABLE users TO administrator;
GRANT SELECT ON TABLE current_user TO local, administrator;
GRANT SELECT, INSERT, UPDATE, DELETE ON TABLE configuration, rules, table_user, addr_oID, SRC_DST,
		SRC_proto, ID_TWR, iSRC_TWR TO local, administrator;
GRANT SELECT, INSERT, UPDATE, DELETE, TRUNCATE ON TABLE `psql -U postgres -c "SELECT relname FROM
				pg_catalog.pg_class WHERE relname LIKE 't________________' AND relname <>
				'table_constraints';" | grep -Eo 't.{16}'` TO local, administrator;
ALTER DEFAULT PRIVILEGES GRANT SELECT, INSERT, UPDATE, DELETE, TRUNCATE ON TABLES TO local,
		administrator;
GRANT EXECUTE ON FUNCTION send_receive(message BYTEA, proto_id CHARACTER VARYING(10),
		imm_addr BYTEA, CCF BOOLEAN, ACF BOOLEAN, broadcast BOOLEAN,
		override_implicit_rules BOOLEAN, send BOOLEAN) TO local, administrator;
INSERT INTO users(username, password, is_administrator, can_view_tables, can_send_messages,
		can_inject_messages, can_send_queries, can_view_rules, can_view_configuration,
		can_view_permissions, can_view_remotes, can_execute_rules, can_actually_login)
		VALUES('root', '`php -r "echo password_hash('root', PASSWORD_DEFAULT);"`', TRUE, TRUE,
		TRUE, TRUE, TRUE, TRUE, TRUE, TRUE, TRUE, TRUE, TRUE);
INSERT INTO configuration(user, forward_messages, use_internet_switch_algorithm, nsecs_id,
		nsecs_src, trust_everyone, default_gateway) VALUES('root', TRUE, TRUE, 600, 36000, FALSE,
		NULL);
" | psql -U postgres