#!/bin/bash
echo "
CREATE TABLE users(username TEXT, password TEXT NOT NULL, is_administrator BOOLEAN NOT NULL,
		can_view_tables BOOLEAN NOT NULL, can_edit_tables BOOLEAN NOT NULL,
		can_send_messages BOOLEAN NOT NULL, can_inject_messages BOOLEAN NOT NULL,
		can_send_queries BOOLEAN NOT NULL, can_view_rules BOOLEAN NOT NULL,
		can_edit_rules BOOLEAN NOT NULL, can_view_configuration BOOLEAN NOT NULL,
		can_edit_configuration BOOLEAN NOT NULL, can_view_permissions BOOLEAN NOT NULL,
		can_edit_permissions BOOLEAN NOT NULL, can_view_remotes BOOLEAN NOT NULL,
		can_edit_remotes BOOLEAN NOT NULL, can_execute_rules BOOLEAN NOT NULL,
		can_view_yourself BOOLEAN NOT NULL, can_edit_yourself BOOLEAN NOT NULL,
		can_view_others BOOLEAN NOT NULL, can_edit_others BOOLEAN NOT NULL,
		can_view_as_others BOOLEAN NOT NULL, can_edit_as_others BOOLEAN NOT NULL,
		can_actually_login BOOLEAN NOT NULL, PRIMARY KEY(username));
CREATE TABLE rules(username TEXT, id INTEGER, send_receive_seconds SMALLINT NOT NULL, filter TEXT,
		drop_modify_nothing SMALLINT NOT NULL, modification TEXT,
		query_command_nothing SMALLINT NOT NULL, query_command_1 TEXT,
		send_inject_query_command_nothing SMALLINT NOT NULL, query_command_2 TEXT, proto_id TEXT,
		imm_addr BYTEA, insecure_port INTEGER, secure_port INTEGER, CCF BOOLEAN, ACF BOOLEAN,
		broadcast BOOLEAN, override_implicit_rules BOOLEAN, activate INTEGER, deactivate INTEGER,
		is_active BOOLEAN NOT NULL, last_run TIMESTAMP(0) WITH TIME ZONE,
		run_period INTERVAL SECOND(0), next_run BIGINT, PRIMARY KEY(username, id),
		FOREIGN KEY(username) REFERENCES users(username) ON DELETE CASCADE ON UPDATE CASCADE);
CREATE TABLE addr_oID(addr BYTEA, out_ID SMALLINT NOT NULL, PRIMARY KEY(addr));
CREATE TABLE SRC_DST(SRC BYTEA, DST BYTEA, PRIMARY KEY(SRC, DST), FOREIGN KEY(SRC)
		REFERENCES addr_oID(addr) ON DELETE CASCADE ON UPDATE CASCADE);
CREATE TABLE ID_TWR(SRC BYTEA, DST BYTEA, ID SMALLINT, TWR TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
		PRIMARY KEY(SRC, DST, ID), FOREIGN KEY(SRC, DST) REFERENCES SRC_DST(SRC, DST)
		ON DELETE CASCADE ON UPDATE CASCADE);
CREATE TABLE proto_name(proto TEXT, name TEXT NOT NULL, PRIMARY KEY(proto));
CREATE TABLE formatted_message_for_send_receive(HD BYTEA, ID BYTEA, LEN BYTEA, DST BYTEA, SRC BYTEA,
		PL BYTEA, CRC BYTEA, ENCRYPTED BOOLEAN, SIGNED BOOLEAN, BROADCAST BOOLEAN, OVERRIDE BOOLEAN,
		proto TEXT, imm_addr BYTEA, insecure_port INTEGER, secure_port INTEGER, CCF BOOLEAN,
		ACF BOOLEAN, FOREIGN KEY(proto) REFERENCES proto_name(proto));
CREATE TABLE adapter_name(adapter INTEGER, name TEXT NOT NULL, PRIMARY KEY(adapter));
CREATE TABLE SRC_proto(SRC BYTEA, proto TEXT, PRIMARY KEY(SRC, proto), FOREIGN KEY(SRC)
		REFERENCES addr_oID(addr) ON DELETE CASCADE ON UPDATE CASCADE, FOREIGN KEY(proto)
		REFERENCES proto_name(proto) ON DELETE CASCADE ON UPDATE CASCADE);
CREATE TABLE iSRC_TWR(SRC BYTEA, proto TEXT, imm_SRC BYTEA,
		TWR TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(SRC, proto, imm_SRC),
		FOREIGN KEY(SRC, proto) REFERENCES SRC_proto(SRC, proto) ON DELETE CASCADE
		ON UPDATE CASCADE);
CREATE TABLE configuration(username TEXT, forward_messages BOOLEAN NOT NULL,
		use_internet_switch_algorithm BOOLEAN NOT NULL, nsecs_id INTEGER NOT NULL,
		nsecs_src INTEGER NOT NULL, trust_everyone BOOLEAN NOT NULL, default_gateway BYTEA NOT NULL,
		insecure_port INTEGER NOT NULL, secure_port INTEGER NOT NULL, PRIMARY KEY(username),
		FOREIGN KEY(username) REFERENCES users(username) ON DELETE CASCADE ON UPDATE CASCADE);
CREATE TABLE raw_message_for_query_command(message BYTEA);
CREATE TABLE table_owner(tablename NAME, username TEXT, PRIMARY KEY(tablename),
		FOREIGN KEY(username) REFERENCES users(username) ON UPDATE CASCADE ON DELETE CASCADE);
CREATE TABLE table_reader(tablename NAME, username TEXT, PRIMARY KEY(tablename, username),
		FOREIGN KEY(tablename) REFERENCES table_owner(tablename) ON UPDATE CASCADE
		ON DELETE CASCADE, FOREIGN KEY(username) REFERENCES users(username) ON UPDATE CASCADE
		ON DELETE CASCADE);
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
