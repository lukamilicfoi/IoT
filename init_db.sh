#!/bin/bash
echo "\
CREATE USER login;
GRANT SELECT(username, password, canActuallyLogin) ON TABLE users TO login;
CREATE USER luka;
GRANT SELECT(username, canViewTables, canSendMessages, can_Receive_Messages, canSendQueries,
 	 	 canViewRules, canActuallyLogin) ON TABLE users TO luka;
GRANT UPDATE(password) ON TABLE users TO luka;
GRANT SELECT ON TABLE currentuser TO luka;
GRANT SELECT, INSERT, UPDATE, DELETE, TRUNCATE ON TABLE rules TO luka;
GRANT SELECT, INSERT, UPDATE, DELETE, TRUNCATE ON TABLE `psql -U postgres -c "SELECT relname FROM
				pg_catalog.pg_class WHERE relname LIKE 't________________' AND relname <>
				'table_constraints';" | grep -Eo 't.{16}'` TO luka;
ALTER DEFAULT PRIVILEGES GRANT SELECT, INSERT, UPDATE, DELETE, TRUNCATE ON TABLES TO luka;
GRANT EXECUTE ON FUNCTION send_receive(message BYTEA, proto_id CHARACTER VARYING(10),
		imm_addr BYTEA, CCF BOOLEAN, ACF BOOLEAN, send BOOLEAN) TO luka;\
" | psql -U postgres
