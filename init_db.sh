#!/bin/bash
echo "\
CREATE USER login;
GRANT SELECT(username, password, is_administrator, can_view_as_others, can_edit_as_others,
		can_actually_login) ON TABLE users TO login;
CREATE ROLE "PUBLIC";
INSERT INTO users(username, password, is_administrator, can_view_tables, can_edit_tables,
		can_send_messages, can_inject_messages, can_send_queries, can_view_rules, can_edit_rules,
		can_view_configuration, can_edit_configuration, can_view_permissions, can_edit_permissions,
		can_view_remotes, can_edit_remotes, can_execute_rules, can_view_users, can_edit_users,
		can_actually_login) VALUES('root',
		'`php -r "echo password_hash('root', PASSWORD_DEFAULT);"`',
		`for a in {1..16}; do echo -n "TRUE, "; done` TRUE), ('public',
		'`php -r "echo password_hash('public', PASSWORD_DEFAULT);"`',
		`for a in {1..16}; do echo -n "FALSE, "; done` FALSE);
INSERT INTO configuration(username, forward_messages, use_internet_switch_algorithm, nsecs_id,
		nsecs_src, trust_everyone, default_gateway, insecure_port, secure_port) VALUES('root', TRUE,
		TRUE, 600, 36000, FALSE, '\\x0000000000000000', 44000, 44001), ('public', TRUE, TRUE, 600,
		36000, FALSE, '\\x0000000000000000', 44000, 44001);
" | psql -U postgres
