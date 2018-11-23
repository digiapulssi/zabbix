CREATE TABLE ldap_zabbix_groups (
	id		 bigint			NOT NULL,
	search_id	 bigint			NOT NULL,
	usrgrpid	 bigint			NOT NULL,
	PRIMARY KEY (id)
);
CREATE UNIQUE INDEX ldap_zabbix_groups_1 ON ldap_zabbix_groups (search_id,usrgrpid);
CREATE INDEX ldap_zabbix_groups_2 ON ldap_zabbix_groups (usrgrpid);

ALTER TABLE ONLY ldap_zabbix_groups ADD CONSTRAINT ldap_zabbix_groups_1 FOREIGN KEY (search_id) REFERENCES ldap_searches (search_id) ON DELETE CASCADE;
ALTER TABLE ONLY ldap_zabbix_groups ADD CONSTRAINT ldap_zabbix_groups_2 FOREIGN KEY (usrgrpid) REFERENCES usrgrp (usrgrpid) ON DELETE CASCADE;
