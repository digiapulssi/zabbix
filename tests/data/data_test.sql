-- Activate Zabbix Server, set visible name and make it a more unique name
UPDATE hosts SET status=0,name='ЗАББИКС Сервер',host='Test host' WHERE host='Zabbix server';

-- Enabling debug mode
UPDATE usrgrp SET debug_mode = 1 WHERE usrgrpid = 7;

-- New media types
INSERT INTO media_type (mediatypeid,type,description,smtp_server,smtp_helo,smtp_email,exec_path,gsm_modem,username,passwd,status) VALUES (4,100,'SMS via IP','','','','0','','test','test',0);

-- More medias for user 'Admin'
INSERT INTO media (mediaid, userid, mediatypeid, sendto, active, severity, period) VALUES (1,1,1,'test@zabbix.com',0,63,'1-7,00:00-24:00;');
INSERT INTO media (mediaid, userid, mediatypeid, sendto, active, severity, period) VALUES (2,1,1,'test2@zabbix.com',1,60,'1-7,00:00-24:00;');
INSERT INTO media (mediaid, userid, mediatypeid, sendto, active, severity, period) VALUES (3,1,3,'123456789',0,32,'1-7,00:00-24:00;');
INSERT INTO media (mediaid, userid, mediatypeid, sendto, active, severity, period) VALUES (4,1,2,'test@jabber.com',0,16,'1-7,00:00-24:00;');
INSERT INTO media (mediaid, userid, mediatypeid, sendto, active, severity, period) VALUES (5,1,4,'test_account',0,63,'6-7,09:00-18:00;');

-- More user scripts
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation) VALUES (4,'Reboot','/sbin/shutdown -r',3,7,4,'This command reboots server.','Do you really want to reboot it?');

-- Add proxies
INSERT INTO hosts (hostid, proxy_hostid, host, status, disable_until, error, available, errors_from, lastaccess, ipmi_authtype, ipmi_privilege, ipmi_username, ipmi_password, ipmi_disable_until, ipmi_available, snmp_disable_until, snmp_available, maintenanceid, maintenance_status, maintenance_type, maintenance_from, ipmi_errors_from, snmp_errors_from, ipmi_error, snmp_error) VALUES (20000,NULL,'Active proxy 1',5,0,'',0,0,0,0,2,'','',0,0,0,0,NULL,0,0,0,0,0,'','');
INSERT INTO hosts (hostid, proxy_hostid, host, status, disable_until, error, available, errors_from, lastaccess, ipmi_authtype, ipmi_privilege, ipmi_username, ipmi_password, ipmi_disable_until, ipmi_available, snmp_disable_until, snmp_available, maintenanceid, maintenance_status, maintenance_type, maintenance_from, ipmi_errors_from, snmp_errors_from, ipmi_error, snmp_error) VALUES (20001,NULL,'Active proxy 2',5,0,'',0,0,0,0,2,'','',0,0,0,0,NULL,0,0,0,0,0,'','');
INSERT INTO hosts (hostid, proxy_hostid, host, status, disable_until, error, available, errors_from, lastaccess, ipmi_authtype, ipmi_privilege, ipmi_username, ipmi_password, ipmi_disable_until, ipmi_available, snmp_disable_until, snmp_available, maintenanceid, maintenance_status, maintenance_type, maintenance_from, ipmi_errors_from, snmp_errors_from, ipmi_error, snmp_error) VALUES (20002,NULL,'Active proxy 3',5,0,'',0,0,0,0,2,'','',0,0,0,0,NULL,0,0,0,0,0,'','');
INSERT INTO hosts (hostid, proxy_hostid, host, status, disable_until, error, available, errors_from, lastaccess, ipmi_authtype, ipmi_privilege, ipmi_username, ipmi_password, ipmi_disable_until, ipmi_available, snmp_disable_until, snmp_available, maintenanceid, maintenance_status, maintenance_type, maintenance_from, ipmi_errors_from, snmp_errors_from, ipmi_error, snmp_error) VALUES (20003,NULL,'Passive proxy 1',6,0,'',0,0,0,0,2,'','',0,0,0,0,NULL,0,0,0,0,0,'','');
INSERT INTO hosts (hostid, proxy_hostid, host, status, disable_until, error, available, errors_from, lastaccess, ipmi_authtype, ipmi_privilege, ipmi_username, ipmi_password, ipmi_disable_until, ipmi_available, snmp_disable_until, snmp_available, maintenanceid, maintenance_status, maintenance_type, maintenance_from, ipmi_errors_from, snmp_errors_from, ipmi_error, snmp_error) VALUES (20004,NULL,'Passive proxy 2',6,0,'',0,0,0,0,2,'','',0,0,0,0,NULL,0,0,0,0,0,'','');
INSERT INTO hosts (hostid, proxy_hostid, host, status, disable_until, error, available, errors_from, lastaccess, ipmi_authtype, ipmi_privilege, ipmi_username, ipmi_password, ipmi_disable_until, ipmi_available, snmp_disable_until, snmp_available, maintenanceid, maintenance_status, maintenance_type, maintenance_from, ipmi_errors_from, snmp_errors_from, ipmi_error, snmp_error) VALUES (20005,NULL,'Passive proxy 3',6,0,'',0,0,0,0,2,'','',0,0,0,0,NULL,0,0,0,0,0,'','');

INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) VALUES (10018,20003,1,0,1,'127.0.0.1','proxy1.zabbix.com','10051');
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) VALUES (10019,20004,1,0,1,'127.0.0.1','proxy2.zabbix.com','10333');
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) VALUES (10020,20005,1,0,0,'127.0.0.1','proxy3.zabbix.com','10051');
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) VALUES (10030,10084,1,4,1,'127.0.0.1','jmxagent.zabbix.com','10051');

-- create an empty host "Template linkage test host"
INSERT INTO hosts (hostid, proxy_hostid, host, status, disable_until, error, available, errors_from, lastaccess, ipmi_authtype, ipmi_privilege, ipmi_username, ipmi_password, ipmi_disable_until, ipmi_available, snmp_disable_until, snmp_available, maintenanceid, maintenance_status, maintenance_type, maintenance_from, ipmi_errors_from, snmp_errors_from, ipmi_error, snmp_error,name) VALUES (10053,NULL,'Template linkage test host',0,0,'',0,0,0,0,2,'','',0,0,0,0,NULL,0,0,0,0,0,'','','Visible host for template linkage');
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) VALUES (10021,10053,1,1,1,'127.0.0.1','','10050');
INSERT INTO interface (interfaceid,hostid,main,type,useip,ip,dns,port) values (10022,10053,1,2,1,'127.0.0.1','','161');
INSERT INTO interface (interfaceid,hostid,main,type,useip,ip,dns,port) values (10023,10053,1,3,1,'127.0.0.1','','623');
INSERT INTO interface (interfaceid,hostid,main,type,useip,ip,dns,port) values (10024,10053,1,4,1,'127.0.0.1','','12345');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (100,10053,4);

-- Add regular expressions
INSERT INTO regexps (regexpid, name, test_string) VALUES (20,'1_regexp_1','first test string');
INSERT INTO regexps (regexpid, name, test_string) VALUES (21,'1_regexp_2','first test string');
INSERT INTO regexps (regexpid, name, test_string) VALUES (22,'2_regexp_1','second test string');
INSERT INTO regexps (regexpid, name, test_string) VALUES (23,'2_regexp_2','second test string');
INSERT INTO regexps (regexpid, name, test_string) VALUES (24,'3_regexp_1','test');
INSERT INTO regexps (regexpid, name, test_string) VALUES (25,'3_regexp_2','test');
INSERT INTO regexps (regexpid, name, test_string) VALUES (26,'4_regexp_1','abcd');
INSERT INTO regexps (regexpid, name, test_string) VALUES (27,'4_regexp_2','abcd');
INSERT INTO regexps (regexpid, name, test_string) VALUES (28,'5_regexp_1','abcd');
INSERT INTO regexps (regexpid, name, test_string) VALUES (29,'5_regexp_2','abcd');

-- Add expressions for regexps
INSERT INTO expressions (expressionid,regexpid,expression,expression_type,exp_delimiter,case_sensitive) VALUES (20,20,'first test string',0,',',1);
INSERT INTO expressions (expressionid,regexpid,expression,expression_type,exp_delimiter,case_sensitive) VALUES (21,21,'first test string2',0,',',1);
INSERT INTO expressions (expressionid,regexpid,expression,expression_type,exp_delimiter,case_sensitive) VALUES (22,22,'second test string',1,',',1);
INSERT INTO expressions (expressionid,regexpid,expression,expression_type,exp_delimiter,case_sensitive) VALUES (23,23,'second string',1,',',1);
INSERT INTO expressions (expressionid,regexpid,expression,expression_type,exp_delimiter,case_sensitive) VALUES (24,24,'abcd test',2,',',1);
INSERT INTO expressions (expressionid,regexpid,expression,expression_type,exp_delimiter,case_sensitive) VALUES (25,25,'test',2,',',1);
INSERT INTO expressions (expressionid,regexpid,expression,expression_type,exp_delimiter,case_sensitive) VALUES (26,26,'abcd',3,',',1);
INSERT INTO expressions (expressionid,regexpid,expression,expression_type,exp_delimiter,case_sensitive) VALUES (27,27,'asdf',3,',',1);
INSERT INTO expressions (expressionid,regexpid,expression,expression_type,exp_delimiter,case_sensitive) VALUES (28,28,'abcd',4,',',1);
INSERT INTO expressions (expressionid,regexpid,expression,expression_type,exp_delimiter,case_sensitive) VALUES (29,29,'asdf',4,',',1);

-- trigger actions
INSERT INTO actions (actionid, name, eventsource, evaltype, status, esc_period, def_shortdata, def_longdata, recovery_msg, r_shortdata, r_longdata) VALUES (10,'Simple action',0,0,0,60,'{TRIGGER.NAME}: {TRIGGER.STATUS}','{TRIGGER.NAME}: {TRIGGER.STATUS}\r\nLast value: {ITEM.LASTVALUE}\r\n\r\n{TRIGGER.URL}',0,'{TRIGGER.NAME}: {TRIGGER.STATUS}','{TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}');
INSERT INTO actions (actionid, name, eventsource, evaltype, status, esc_period, def_shortdata, def_longdata, recovery_msg, r_shortdata, r_longdata) VALUES (11,'Trigger action 1',0,0,0,3600,'{TRIGGER.NAME}: {TRIGGER.STATUS}','{TRIGGER.NAME}: {TRIGGER.STATUS}\r\nLast value: {ITEM.LASTVALUE}\r\n\r\n{TRIGGER.URL}',0,'{TRIGGER.NAME}: {TRIGGER.STATUS}','{TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}');
INSERT INTO actions (actionid, name, eventsource, evaltype, status, esc_period, def_shortdata, def_longdata, recovery_msg, r_shortdata, r_longdata) VALUES (12,'Trigger action 2',0,0,0,60,'{TRIGGER.NAME}: {TRIGGER.STATUS}','{TRIGGER.NAME}: {TRIGGER.STATUS}\r\nLast value: {ITEM.LASTVALUE}\r\n\r\n{TRIGGER.URL}',1,'Recovery: {TRIGGER.NAME}: {TRIGGER.STATUS}','Recovery: {TRIGGER.NAME}: {TRIGGER.STATUS}\r\nLast value: {ITEM.LASTVALUE}{TRIGGER.URL}');
INSERT INTO actions (actionid, name, eventsource, evaltype, status, esc_period, def_shortdata, def_longdata, recovery_msg, r_shortdata, r_longdata) VALUES (13,'Trigger action 3',0,0,0,60,'{TRIGGER.NAME}: {TRIGGER.STATUS}','{TRIGGER.NAME}: {TRIGGER.STATUS}\r\nLast value: {ITEM.LASTVALUE}\r\n\r\n{TRIGGER.URL}',1,'Recovery: {TRIGGER.NAME}: {TRIGGER.STATUS}','Recovery: {TRIGGER.NAME}: {TRIGGER.STATUS}\r\nLast value: {ITEM.LASTVALUE}{TRIGGER.URL}');
INSERT INTO actions (actionid, name, eventsource, evaltype, status, esc_period, def_shortdata, def_longdata, recovery_msg, r_shortdata, r_longdata) VALUES (14,'Trigger action 4',0,0,1,60,'{TRIGGER.NAME}: {TRIGGER.STATUS}','{TRIGGER.NAME}: {TRIGGER.STATUS}\r\nLast value: {ITEM.LASTVALUE}\r\n\r\n{TRIGGER.URL}',1,'Recovery: {TRIGGER.NAME}: {TRIGGER.STATUS}','Recovery: {TRIGGER.NAME}: {TRIGGER.STATUS}\r\nLast value: {ITEM.LASTVALUE}{TRIGGER.URL}');

-- auto-registration actions
INSERT INTO actions (actionid, name, eventsource, evaltype, status, esc_period, def_shortdata, def_longdata, recovery_msg, r_shortdata, r_longdata) VALUES (9,'Autoregistration action 1',2,0,0,0,'{TRIGGER.NAME}: {TRIGGER.STATUS}','{TRIGGER.NAME}: {TRIGGER.STATUS}\r\nLast value: {ITEM.LASTVALUE}\r\n\r\n{TRIGGER.URL}',0,'','');
INSERT INTO actions (actionid, name, eventsource, evaltype, status, esc_period, def_shortdata, def_longdata, recovery_msg, r_shortdata, r_longdata) VALUES (15,'Autoregistration action 2',2,0,1,0,'{TRIGGER.NAME}: {TRIGGER.STATUS}','{TRIGGER.NAME}: {TRIGGER.STATUS}\r\nLast value: {ITEM.LASTVALUE}\r\n\r\n{TRIGGER.URL}',0,'','');

INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (500, 9, 22, 2, 'MySQL');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (501, 9, 22, 3, 'DB2');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (502, 9, 20, 0, '20000');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (503, 9, 20, 1, '20001');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (504, 10, 5, 0, '1');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (505, 11, 5, 0, '1');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (506, 12, 5, 0, '1');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (507, 12, 15, 0, 'MySQL');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (508, 12, 15, 2, 'MYSQL');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (509, 12, 15, 3, 'PostgreSQL');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (510, 12, 0, 0, '2');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (511, 12, 0, 1, '4');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (512, 12, 13, 0, '10001');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (513, 12, 13, 1, '10081');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (514, 12, 1, 0, '10084');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (515, 12, 1, 1, '10084');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (516, 12, 2, 0, '13496');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (517, 12, 2, 1, '13491');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (518, 12, 3, 2, 'Oracle');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (519, 12, 3, 3, 'DB2');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (520, 12, 4, 0, '1');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (521, 12, 4, 1, '2');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (522, 12, 4, 5, '3');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (523, 12, 4, 6, '4');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (524, 12, 4, 0, '5');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (525, 12, 5, 0, '0');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (526, 12, 6, 4, '1-7,00:00-24:00');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (527, 12, 6, 7, '6-7,08:00-18:00');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (528, 12, 16, 4, '');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (529, 12, 16, 7, '');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (530, 13, 5, 0, '1');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (531, 13, 15, 0, 'MySQL');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (532, 13, 15, 2, 'MYSQL');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (533, 13, 15, 3, 'PostgreSQL');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (534, 13, 0, 0, '2');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (535, 13, 0, 1, '4');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (536, 13, 13, 0, '10001');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (537, 13, 13, 1, '10081');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (538, 13, 1, 0, '10084');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (539, 13, 1, 1, '10084');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (540, 13, 2, 0, '13496');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (541, 13, 2, 1, '13491');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (542, 13, 3, 2, 'Oracle');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (543, 13, 3, 3, 'DB2');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (544, 13, 4, 0, '1');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (545, 13, 4, 1, '2');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (546, 13, 4, 5, '3');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (547, 13, 4, 6, '4');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (548, 13, 4, 0, '5');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (549, 13, 5, 0, '0');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (550, 13, 6, 4, '1-7,00:00-24:00');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (551, 13, 6, 7, '6-7,08:00-18:00');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (552, 13, 16, 4, '');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (553, 13, 16, 7, '');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (554, 14, 5, 0, '1');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (555, 14, 15, 0, 'MySQL');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (556, 14, 15, 2, 'MYSQL');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (557, 14, 15, 3, 'PostgreSQL');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (558, 14, 0, 0, '2');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (559, 14, 0, 1, '4');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (560, 14, 13, 0, '10001');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (561, 14, 13, 1, '10081');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (562, 14, 1, 0, '10084');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (563, 14, 1, 1, '10084');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (564, 14, 2, 0, '13496');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (565, 14, 2, 1, '13491');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (566, 14, 3, 2, 'Oracle');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (567, 14, 3, 3, 'DB2');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (568, 14, 4, 0, '1');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (569, 14, 4, 1, '2');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (570, 14, 4, 5, '3');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (571, 14, 4, 6, '4');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (572, 14, 4, 0, '5');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (573, 14, 5, 0, '0');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (574, 14, 6, 4, '1-7,00:00-24:00');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (575, 14, 6, 7, '6-7,08:00-18:00');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (576, 14, 16, 4, '');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (577, 14, 16, 7, '');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (578, 15, 22, 2, 'MySQL');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (579, 15, 22, 3, 'DB2');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (580, 15, 20, 0, '20000');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (581, 15, 20, 1, '20001');

INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (7, 10, 0, 0, 1, 1, 0);
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (8, 11, 0, 0, 1, 1, 0);
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (9, 12, 0, 0, 1, 1, 0);
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (10, 13, 0, 0, 1, 1, 0);
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (11, 13, 0, 3600, 2, 2, 0);
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (12, 13, 0, 0, 5, 6, 0);
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (13, 14, 0, 0, 1, 1, 0);
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (14, 14, 0, 3600, 2, 2, 0);
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (15, 14, 0, 0, 5, 6, 0);
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (16, 14, 1, 0, 20, 0, 0);

INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid) VALUES (7, 1, '{TRIGGER.NAME}: {TRIGGER.STATUS}', '{TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}', NULL);
INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid) VALUES (8, 1, '{TRIGGER.NAME}: {TRIGGER.STATUS}', '{TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}', NULL);
INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid) VALUES (9, 1, '{TRIGGER.NAME}: {TRIGGER.STATUS}', '{TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}', NULL);
INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid) VALUES (10, 1, '{TRIGGER.NAME}: {TRIGGER.STATUS}', '{TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}', NULL);
INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid) VALUES (11, 1, '{TRIGGER.NAME}: {TRIGGER.STATUS}', '{TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}', 1);
INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid) VALUES (12, 0, 'Custom: {TRIGGER.NAME}: {TRIGGER.STATUS}', 'Custom: {TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}', 1);
INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid) VALUES (13, 1, '{TRIGGER.NAME}: {TRIGGER.STATUS}', '{TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}', NULL);
INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid) VALUES (14, 1, '{TRIGGER.NAME}: {TRIGGER.STATUS}', '{TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}', 1);
INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid) VALUES (15, 0, 'Custom: {TRIGGER.NAME}: {TRIGGER.STATUS}', 'Custom: {TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}', 1);

INSERT INTO opmessage_grp (opmessage_grpid, operationid, usrgrpid) VALUES (10, 7, 7);
INSERT INTO opmessage_grp (opmessage_grpid, operationid, usrgrpid) VALUES (11, 8, 7);
INSERT INTO opmessage_grp (opmessage_grpid, operationid, usrgrpid) VALUES (12, 9, 7);
INSERT INTO opmessage_grp (opmessage_grpid, operationid, usrgrpid) VALUES (13, 10, 7);
INSERT INTO opmessage_grp (opmessage_grpid, operationid, usrgrpid) VALUES (14, 11, 7);
INSERT INTO opmessage_grp (opmessage_grpid, operationid, usrgrpid) VALUES (15, 13, 7);
INSERT INTO opmessage_grp (opmessage_grpid, operationid, usrgrpid) VALUES (16, 14, 7);

INSERT INTO opmessage_usr (opmessage_usrid, operationid, userid) VALUES (2, 12, 1);
INSERT INTO opmessage_usr (opmessage_usrid, operationid, userid) VALUES (3, 15, 1);

INSERT INTO opcommand (operationid, type, scriptid, execute_on, port, authtype, username, password, publickey, privatekey, command) VALUES (16, 0, NULL, 0, '', 0, '', '', '', '', '/sbin/shutdown -r');

INSERT INTO opcommand_hst (opcommand_hstid, operationid, hostid) VALUES (1, 16, NULL);

INSERT INTO opconditions (opconditionid, operationid, conditiontype, operator, value) VALUES (1,11,14,0,'0');
INSERT INTO opconditions (opconditionid, operationid, conditiontype, operator, value) VALUES (2,11,14,0,'1');
INSERT INTO opconditions (opconditionid, operationid, conditiontype, operator, value) VALUES (3,12,14,0,'0');
INSERT INTO opconditions (opconditionid, operationid, conditiontype, operator, value) VALUES (4,14,14,0,'0');
INSERT INTO opconditions (opconditionid, operationid, conditiontype, operator, value) VALUES (5,14,14,0,'1');
INSERT INTO opconditions (opconditionid, operationid, conditiontype, operator, value) VALUES (6,15,14,0,'0');
INSERT INTO opconditions (opconditionid, operationid, conditiontype, operator, value) VALUES (7,16,14,0,'0');

INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (17, 9, 0, 0, 1, 1, 0);
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (18, 9, 0, 0, 1, 1, 0);
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (19, 9, 1, 0, 1, 1, 0);
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (20, 9, 2, 0, 1, 1, 0);
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (21, 9, 9, 0, 1, 1, 0);
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (22, 9, 4, 0, 1, 1, 0);
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (23, 9, 6, 0, 1, 1, 0);
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (24, 15, 0, 0, 1, 1, 0);
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (25, 15, 0, 0, 1, 1, 0);
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (26, 15, 1, 0, 1, 1, 0);

INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid) VALUES (17, 0, 'Special: {TRIGGER.NAME}: {TRIGGER.STATUS}', 'Special: {TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}', NULL);
INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid) VALUES (18, 1, '{TRIGGER.NAME}: {TRIGGER.STATUS}', '{TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}', 4);
INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid) VALUES (24, 0, 'Special: {TRIGGER.NAME}: {TRIGGER.STATUS}', 'Special: {TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}', NULL);
INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid) VALUES (25, 1, '{TRIGGER.NAME}: {TRIGGER.STATUS}', '{TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}', 4);

INSERT INTO opmessage_grp (opmessage_grpid, operationid, usrgrpid) VALUES (17, 17, 7);
INSERT INTO opmessage_grp (opmessage_grpid, operationid, usrgrpid) VALUES (18, 18, 7);
INSERT INTO opmessage_grp (opmessage_grpid, operationid, usrgrpid) VALUES (19, 24, 7);
INSERT INTO opmessage_grp (opmessage_grpid, operationid, usrgrpid) VALUES (20, 25, 7);

INSERT INTO opcommand (operationid, type, command) VALUES (19, 0, 'echo TEST');
INSERT INTO opcommand (operationid, type, command) VALUES (26, 0, 'echo TEST');

INSERT INTO opcommand_hst (opcommand_hstid, operationid, hostid) VALUES (2, 19, NULL);
INSERT INTO opcommand_hst (opcommand_hstid, operationid, hostid) VALUES (3, 26, NULL);

INSERT INTO opgroup (opgroupid, operationid, groupid) VALUES (3, 22, 5);

INSERT INTO optemplate (optemplateid, operationid, templateid) VALUES (3, 23, 10001);

-- Add test graph
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (200000,'Test graph 1',900,200,0.0,100.0,NULL,1,0,1,1,0,0.0,0.0,1,1,NULL,NULL,0);

-- Add graph items
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (200000, 200000, 10009, 1, 1, 'FF5555', 0, 2, 0);

-- Add more screens
INSERT INTO screens (screenid, name, hsize, vsize, templateid) VALUES (200000,'Test screen (graph)',1,1,NULL);
INSERT INTO screens (screenid, name, hsize, vsize, templateid) VALUES (200001,'Test screen (clock)',1,1,NULL);
INSERT INTO screens (screenid, name, hsize, vsize, templateid) VALUES (200002,'Test screen (data overview, left align)',1,1,NULL);
INSERT INTO screens (screenid, name, hsize, vsize, templateid) VALUES (200003,'Test screen (history of actions)',1,1,NULL);
INSERT INTO screens (screenid, name, hsize, vsize, templateid) VALUES (200004,'Test screen (history of events)',1,1,NULL);
INSERT INTO screens (screenid, name, hsize, vsize, templateid) VALUES (200005,'Test screen (hosts info, horizontal align)',1,1,NULL);
INSERT INTO screens (screenid, name, hsize, vsize, templateid) VALUES (200006,'Test screen (hosts info, vertical align)',1,1,NULL);
INSERT INTO screens (screenid, name, hsize, vsize, templateid) VALUES (200007,'Test screen (map)',1,1,NULL);
INSERT INTO screens (screenid, name, hsize, vsize, templateid) VALUES (200008,'Test screen (plain text)',1,1,NULL);
INSERT INTO screens (screenid, name, hsize, vsize, templateid) VALUES (200009,'Test screen (screen)',1,1,NULL);
INSERT INTO screens (screenid, name, hsize, vsize, templateid) VALUES (200010,'Test screen (server info)',1,1,NULL);
INSERT INTO screens (screenid, name, hsize, vsize, templateid) VALUES (200011,'Test screen (simple graph)',1,1,NULL);
INSERT INTO screens (screenid, name, hsize, vsize, templateid) VALUES (200012,'Test screen (status of hostgroup triggers)',1,1,NULL);
INSERT INTO screens (screenid, name, hsize, vsize, templateid) VALUES (200013,'Test screen (status of host triggers)',1,1,NULL);
INSERT INTO screens (screenid, name, hsize, vsize, templateid) VALUES (200014,'Test screen (system status)',1,1,NULL);
INSERT INTO screens (screenid, name, hsize, vsize, templateid) VALUES (200015,'Test screen (triggers info, horizontal align)',1,1,NULL);
INSERT INTO screens (screenid, name, hsize, vsize, templateid) VALUES (200016,'Test screen (triggers overview, left align)',1,1,NULL);
INSERT INTO screens (screenid, name, hsize, vsize, templateid) VALUES (200017,'Test screen (triggers overview, top align)',1,1,NULL);
INSERT INTO screens (screenid, name, hsize, vsize, templateid) VALUES (200018,'Test screen (url)',1,1,NULL);
INSERT INTO screens (screenid, name, hsize, vsize, templateid) VALUES (200019,'Test screen (data overview, top align)',1,1,NULL);
INSERT INTO screens (screenid, name, hsize, vsize, templateid) VALUES (200020,'Test screen (triggers info, vertical align)',1,1,NULL);

INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic, sort_triggers) VALUES (200000,200000,0,200000,500,100,0,0,0,0,0,0,0,0,'',0,0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic, sort_triggers) VALUES (200001,200001,7,0,500,100,0,0,0,0,0,0,0,0,'',0,0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic, sort_triggers) VALUES (200002,200002,10,4,500,100,0,0,0,0,0,0,0,0,'',0,0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic, sort_triggers) VALUES (200003,200003,12,0,500,100,0,0,0,0,25,0,0,0,'',0,0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic, sort_triggers) VALUES (200004,200004,13,0,500,100,0,0,0,0,25,0,0,0,'',0,0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic, sort_triggers) VALUES (200005,200005,4,4,500,100,0,0,0,0,0,0,0,0,'',0,0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic, sort_triggers) VALUES (200006,200006,4,4,500,100,0,0,0,0,0,0,0,1,'',0,0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic, sort_triggers) VALUES (200007,200007,2,2,500,100,0,0,0,0,0,0,0,0,'',0,0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic, sort_triggers) VALUES (200008,200008,3,10057,500,100,0,0,0,0,25,0,0,0,'',0,0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic, sort_triggers) VALUES (200009,200009,8,200000,500,100,0,0,0,0,0,0,0,0,'',0,0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic, sort_triggers) VALUES (200010,200010,6,0,500,100,0,0,0,0,0,0,0,0,'',0,0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic, sort_triggers) VALUES (200011,200011,1,10026,500,100,0,0,0,0,0,0,0,0,'',0,0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic, sort_triggers) VALUES (200012,200012,14,2,500,100,0,0,0,0,25,0,0,0,'',0,0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic, sort_triggers) VALUES (200013,200013,16,10084,500,100,0,0,0,0,25,0,0,0,'',0,0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic, sort_triggers) VALUES (200014,200014,15,0,500,100,0,0,0,0,0,0,0,0,'',0,0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic, sort_triggers) VALUES (200015,200015,5,4,500,100,0,0,0,0,0,0,0,0,'',0,0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic, sort_triggers) VALUES (200016,200016,9,4,500,100,0,0,0,0,0,0,0,0,'',0,0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic, sort_triggers) VALUES (200017,200017,9,4,500,100,0,0,0,0,0,0,0,1,'',0,0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic, sort_triggers) VALUES (200018,200018,11,0,500,500,0,0,0,0,0,0,0,0,'http://www.google.com',0,0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic, sort_triggers) VALUES (200019,200019,10,4,500,100,0,0,0,0,0,0,0,1,'',0,0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic, sort_triggers) VALUES (200020,200020,5,4,500,100,0,0,0,0,0,0,0,1,'',0,0);

-- Add slide shows
INSERT INTO slideshows (slideshowid, name, delay) VALUES (200001,'Test slide show 1',10);
INSERT INTO slideshows (slideshowid, name, delay) VALUES (200002,'Test slide show 2',10);
INSERT INTO slideshows (slideshowid, name, delay) VALUES (200003,'Test slide show 3',900);

INSERT INTO slides (slideid, slideshowid, screenid, step, delay) VALUES (200000,200001,200000,0,0);
INSERT INTO slides (slideid, slideshowid, screenid, step, delay) VALUES (200001,200001,200001,1,0);
INSERT INTO slides (slideid, slideshowid, screenid, step, delay) VALUES (200003,200002,200002,0,0);
INSERT INTO slides (slideid, slideshowid, screenid, step, delay) VALUES (200004,200002,200003,1,0);
INSERT INTO slides (slideid, slideshowid, screenid, step, delay) VALUES (200005,200002,200004,2,15);
INSERT INTO slides (slideid, slideshowid, screenid, step, delay) VALUES (200006,200002,200005,3,20);
INSERT INTO slides (slideid, slideshowid, screenid, step, delay) VALUES (200007,200003,200007,0,0);
INSERT INTO slides (slideid, slideshowid, screenid, step, delay) VALUES (200008,200003,200009,1,0);
INSERT INTO slides (slideid, slideshowid, screenid, step, delay) VALUES (200009,200003,200016,2,15);
INSERT INTO slides (slideid, slideshowid, screenid, step, delay) VALUES (200010,200003,200019,3,20);
INSERT INTO slides (slideid, slideshowid, screenid, step, delay) VALUES (200011,200003,200020,4,60);

-- Add maintenance periods
INSERT INTO maintenances (maintenanceid, name, maintenance_type, description, active_since, active_till) VALUES (1,'Maintenance period 1 (data collection)',0,'Test description 1',1294760280,1294846680);
INSERT INTO maintenances (maintenanceid, name, maintenance_type, description, active_since, active_till) VALUES (2,'Maintenance period 2 (no data collection)',1,'Test description 1',1294760280,1294846680);

INSERT INTO maintenances_hosts (maintenance_hostid, maintenanceid, hostid) VALUES (1,1,20000);
INSERT INTO maintenances_hosts (maintenance_hostid, maintenanceid, hostid) VALUES (2,2,20000);

INSERT INTO maintenances_groups (maintenance_groupid, maintenanceid, groupid) VALUES (1,1,4);
INSERT INTO maintenances_groups (maintenance_groupid, maintenanceid, groupid) VALUES (2,2,4);

INSERT INTO timeperiods (timeperiodid, timeperiod_type, every, month, dayofweek, day, start_time, period, start_date) VALUES (1,0,1,0,0,1,43200,184200,1294760340);
INSERT INTO timeperiods (timeperiodid, timeperiod_type, every, month, dayofweek, day, start_time, period, start_date) VALUES (2,2,2,0,0,1,43200,93780,1294760400);
INSERT INTO timeperiods (timeperiodid, timeperiod_type, every, month, dayofweek, day, start_time, period, start_date) VALUES (3,3,2,0,85,1,85800,300,1294760400);
INSERT INTO timeperiods (timeperiodid, timeperiod_type, every, month, dayofweek, day, start_time, period, start_date) VALUES (4,4,0,1365,0,15,37500,183840,1294760460);
INSERT INTO timeperiods (timeperiodid, timeperiod_type, every, month, dayofweek, day, start_time, period, start_date) VALUES (5,4,1,2730,85,0,84600,1800,1294760520);
INSERT INTO timeperiods (timeperiodid, timeperiod_type, every, month, dayofweek, day, start_time, period, start_date) VALUES (6,0,1,0,0,1,43200,184200,1294760340);
INSERT INTO timeperiods (timeperiodid, timeperiod_type, every, month, dayofweek, day, start_time, period, start_date) VALUES (7,2,2,0,0,1,43200,93780,1294760400);
INSERT INTO timeperiods (timeperiodid, timeperiod_type, every, month, dayofweek, day, start_time, period, start_date) VALUES (8,3,2,0,85,1,85800,300,1294760400);
INSERT INTO timeperiods (timeperiodid, timeperiod_type, every, month, dayofweek, day, start_time, period, start_date) VALUES (9,4,0,1365,0,15,37500,183840,1294760460);
INSERT INTO timeperiods (timeperiodid, timeperiod_type, every, month, dayofweek, day, start_time, period, start_date) VALUES (10,4,1,2730,85,0,84600,1800,1294760520);

INSERT INTO maintenances_windows (maintenance_timeperiodid, maintenanceid, timeperiodid) VALUES (1,1,1);
INSERT INTO maintenances_windows (maintenance_timeperiodid, maintenanceid, timeperiodid) VALUES (2,1,2);
INSERT INTO maintenances_windows (maintenance_timeperiodid, maintenanceid, timeperiodid) VALUES (3,1,3);
INSERT INTO maintenances_windows (maintenance_timeperiodid, maintenanceid, timeperiodid) VALUES (4,1,4);
INSERT INTO maintenances_windows (maintenance_timeperiodid, maintenanceid, timeperiodid) VALUES (5,1,5);
INSERT INTO maintenances_windows (maintenance_timeperiodid, maintenanceid, timeperiodid) VALUES (6,2,6);
INSERT INTO maintenances_windows (maintenance_timeperiodid, maintenanceid, timeperiodid) VALUES (7,2,7);
INSERT INTO maintenances_windows (maintenance_timeperiodid, maintenanceid, timeperiodid) VALUES (8,2,8);
INSERT INTO maintenances_windows (maintenance_timeperiodid, maintenanceid, timeperiodid) VALUES (9,2,9);
INSERT INTO maintenances_windows (maintenance_timeperiodid, maintenanceid, timeperiodid) VALUES (10,2,10);

-- Add maps
INSERT INTO sysmaps (sysmapid, name, width, height, backgroundid, label_type, label_location, highlight, expandproblem, markelements, show_unack) VALUES (3,'Test map 1',800,600,NULL,0,0,1,1,1,2);

INSERT INTO sysmaps_elements (selementid, sysmapid, elementid, elementtype, iconid_off, iconid_on, label, label_location, x, y, iconid_disabled, iconid_maintenance) VALUES (3,3,0,4,7,NULL,'Test phone icon',0,151,101,NULL,NULL);
INSERT INTO sysmaps_elements (selementid, sysmapid, elementid, elementtype, iconid_off, iconid_on, label, label_location, x, y, iconid_disabled, iconid_maintenance) VALUES (4,3,1,1,3,NULL,'Map element (Local network)',0,401,101,NULL,NULL);
INSERT INTO sysmaps_elements (selementid, sysmapid, elementid, elementtype, iconid_off, iconid_on, label, label_location, x, y, iconid_disabled, iconid_maintenance) VALUES (5,3,13497,2,15,NULL,'Trigger element (CPU load)',0,101,301,NULL,NULL);
INSERT INTO sysmaps_elements (selementid, sysmapid, elementid, elementtype, iconid_off, iconid_on, label, label_location, x, y, iconid_disabled, iconid_maintenance) VALUES (6,3,2,3,1,NULL,'Host group element (Linux servers)',0,301,351,NULL,NULL);
INSERT INTO sysmaps_elements (selementid, sysmapid, elementid, elementtype, iconid_off, iconid_on, label, label_location, x, y, iconid_disabled, iconid_maintenance) VALUES (7,3,10084,0,19,NULL,'Host element (Zabbix Server)',0,501,301,NULL,NULL);

INSERT INTO sysmaps_links (linkid, sysmapid, selementid1, selementid2, drawtype, color, label) VALUES (1,3,3,4,2,'00CC00','CPU load: {Zabbix Server:system.cpu.load[].last(0)}');
INSERT INTO sysmaps_links (linkid, sysmapid, selementid1, selementid2, drawtype, color, label) VALUES (2,3,3,5,0,'00CC00','');
INSERT INTO sysmaps_links (linkid, sysmapid, selementid1, selementid2, drawtype, color, label) VALUES (3,3,6,5,0,'00CC00','');
INSERT INTO sysmaps_links (linkid, sysmapid, selementid1, selementid2, drawtype, color, label) VALUES (4,3,7,6,0,'00CC00','');
INSERT INTO sysmaps_links (linkid, sysmapid, selementid1, selementid2, drawtype, color, label) VALUES (5,3,4,7,0,'00CC00','');
INSERT INTO sysmaps_links (linkid, sysmapid, selementid1, selementid2, drawtype, color, label) VALUES (6,3,4,5,0,'00CC00','');
INSERT INTO sysmaps_links (linkid, sysmapid, selementid1, selementid2, drawtype, color, label) VALUES (7,3,3,6,0,'00CC00','');
INSERT INTO sysmaps_links (linkid, sysmapid, selementid1, selementid2, drawtype, color, label) VALUES (8,3,7,3,0,'00CC00','');

INSERT INTO sysmaps_link_triggers (linktriggerid, linkid, triggerid, drawtype, color) VALUES (1,1,13136,4,'DD0000');

INSERT INTO sysmap_element_url (sysmapelementurlid, selementid, name, url) VALUES (1,4,'Zabbix home','www.zabbix.com');
INSERT INTO sysmap_element_url (sysmapelementurlid, selementid, name, url) VALUES (2,5,'www.wikipedia.org','www.wikipedia.org');

-- Host inventories
INSERT INTO host_inventory (type,type_full,name,alias,os,os_full,os_short,serialno_a,serialno_b,tag,asset_tag,macaddress_a,macaddress_b,hardware,hardware_full,software,software_full,software_app_a,software_app_b,software_app_c,software_app_d,software_app_e,contact,location,location_lat,location_lon,notes,chassis,model,hw_arch,vendor,contract_number,installer_name,deployment_status,url_a,url_b,url_c,host_networks,host_netmask,host_router,oob_ip,oob_netmask,oob_router,date_hw_purchase,date_hw_install,date_hw_expiry,date_hw_decomm,site_address_a,site_address_b,site_address_c,site_city,site_state,site_country,site_zip,site_rack,site_notes,poc_1_name,poc_1_email,poc_1_phone_a,poc_1_phone_b,poc_1_cell,poc_1_screen,poc_1_notes,poc_2_name,poc_2_email,poc_2_phone_a,poc_2_phone_b,poc_2_cell,poc_2_screen,poc_2_notes,hostid) VALUES ('Type','Type (Full details)','Name','Alias','OS','OS (Full details)','OS (Short)','Serial number A','Serial number B','Tag','Asset tag','MAC address A','MAC address B','Hardware','Hardware (Full details)','Software','Software (Full details)','Software application A','Software application B','Software application C','Software application D','Software application E','Contact','Location','Location latitud','Location longitu','Notes','Chassis','Model','HW architecture','Vendor','Contract number','Installer name','Deployment status','URL A','URL B','URL C','Host networks','Host subnet mask','Host router','OOB IP address','OOB subnet mask','OOB router','Date HW purchased','Date HW installed','Date HW maintenance expires','Date hw decommissioned','Site address A','Site address B','Site address C','Site city','Site state / province','Site country','Site ZIP / postal','Site rack location','Site notes','Primary POC name','Primary POC email','Primary POC phone A','Primary POC phone B','Primary POC cell','Primary POC screen name','Primary POC notes','Secondary POC name','Secondary POC email','Secondary POC phone A','Secondary POC phone B','Secondary POC cell','Secondary POC screen name','Secondary POC notes',10053);

-- delete Discovery Rule
INSERT INTO items (itemid, type, snmp_community, snmp_oid, hostid, description, key_, delay, history, trends, status, value_type, trapper_hosts, units, multiplier, delta, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, formula, error, lastlogsize, logtimefmt, templateid, valuemapid, delay_flex, params, ipmi_sensor, data_type, authtype, username, password, publickey, privatekey, mtime, flags, filter, interfaceid, port) VALUES (22188, 0, '', '', 10053, 'rule', 'key', 30, 90, 365, 0, 0, '', '', 0, 0, '', 0, '', '', '1', '', 0, '', NULL, NULL, '', '', '', 0, 0, '', '', '', '', 0, 1, ':', 10021, '');

-- add some test items
-- first, one that references a non-existent user macro in the key and then references that key parameter in the item name using a positional reference
INSERT INTO items (itemid, type, snmp_community, snmp_oid, hostid, description, key_, delay, history, trends, status, value_type, trapper_hosts, units, multiplier, delta, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, formula, error, lastlogsize, logtimefmt, templateid, valuemapid, delay_flex, params, ipmi_sensor, data_type, authtype, username, password, publickey, privatekey, mtime, flags, filter, interfaceid, port) VALUES (23100, 0, '', '', 10053, 'a. i am referencing a non-existent user macro $1', 'key[{$I_DONT_EXIST}]', 30, 90, 365, 0, 0, '', '', 0, 0, '', 0, '', '', '1', '', 0, '', NULL, NULL, '', '', '', 0, 0, '', '', '', '', 0, 0, ':', 10021, '');
INSERT INTO items (itemid, type, snmp_community, snmp_oid, hostid, description, key_, delay, history, trends, status, value_type, trapper_hosts, units, multiplier, delta, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, formula, error, lastlogsize, logtimefmt, templateid, valuemapid, delay_flex, params, ipmi_sensor, data_type, authtype, username, password, publickey, privatekey, mtime, flags, filter, interfaceid, port, inventory_link) VALUES (23101, 0, '', '', 10053, 'i am populating filed Type', 'key.test.pop.type', 30, 90, 365, 0, 0, '', '', 0, 0, '', 0, '', '', '1', '', 0, '', NULL, NULL, '', '', '', 0, 0, '', '', '', '', 0, 0, ':', 10021, '', 1);

-- test discovery rule
INSERT INTO drules (druleid, proxy_hostid, name, iprange, delay, nextcheck, status) VALUES (3, NULL, 'External network', '192.168.3.1-255', 600, 0, 0);

INSERT INTO dchecks (dcheckid, druleid, type, key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, uniq) VALUES (6, 3, 9, 'system.uname', '', '10050', '', 0, '', '', 0);
INSERT INTO dchecks (dcheckid, druleid, type, key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, uniq) VALUES (7, 3, 3, '', '', '21,1021', '', 0, '', '', 0);
INSERT INTO dchecks (dcheckid, druleid, type, key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, uniq) VALUES (8, 3, 4, '', '', '80,8080', '', 0, '', '', 0);
INSERT INTO dchecks (dcheckid, druleid, type, key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, uniq) VALUES (9, 3, 14, '', '', '443', '', 0, '', '', 0);
INSERT INTO dchecks (dcheckid, druleid, type, key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, uniq) VALUES (10, 3, 12, '', '', '0', '', 0, '', '', 0);
INSERT INTO dchecks (dcheckid, druleid, type, key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, uniq) VALUES (11, 3, 7, '', '', '143-145', '', 0, '', '', 0);
INSERT INTO dchecks (dcheckid, druleid, type, key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, uniq) VALUES (12, 3, 1, '', '', '389', '', 0, '', '', 0);
INSERT INTO dchecks (dcheckid, druleid, type, key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, uniq) VALUES (13, 3, 6, '', '', '119', '', 0, '', '', 0);
INSERT INTO dchecks (dcheckid, druleid, type, key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, uniq) VALUES (14, 3, 5, '', '', '110', '', 0, '', '', 0);
INSERT INTO dchecks (dcheckid, druleid, type, key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, uniq) VALUES (15, 3, 2, '', '', '25', '', 0, '', '', 0);
INSERT INTO dchecks (dcheckid, druleid, type, key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, uniq) VALUES (16, 3, 10, 'ifIndex0', 'public', '161', '', 0, '', '', 0);
INSERT INTO dchecks (dcheckid, druleid, type, key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, uniq) VALUES (17, 3, 11, 'ifInOut0', 'private1', '162', '', 0, '', '', 0);
INSERT INTO dchecks (dcheckid, druleid, type, key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, uniq) VALUES (18, 3, 13, 'ifIn0', '', '161', 'private2', 0, '', '', 0);
INSERT INTO dchecks (dcheckid, druleid, type, key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, uniq) VALUES (19, 3, 0, '', '', '22', '', 0, '', '', 0);
INSERT INTO dchecks (dcheckid, druleid, type, key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, uniq) VALUES (20, 3, 8, '', '', '10000-20000', '', 0, '', '', 0);
INSERT INTO dchecks (dcheckid, druleid, type, key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, uniq) VALUES (21, 3, 15, '', '', '23', '', 0, '', '', 0);
INSERT INTO dchecks (dcheckid, druleid, type, key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, uniq) VALUES (22, 3, 9, 'agent.uname', '', '10050', '', 0, '', '', 0);

-- Global macros
INSERT INTO globalmacro (globalmacroid, macro, value) VALUES (3,'{$DEFAULT_DELAY}','30');
INSERT INTO globalmacro (globalmacroid, macro, value) VALUES (4,'{$LOCALIP}','127.0.0.1');
INSERT INTO globalmacro (globalmacroid, macro, value) VALUES (5,'{$DEFAULT_LINUX_IF}','eth0');
INSERT INTO globalmacro (globalmacroid, macro, value) VALUES (6,'{$0123456789012345678901234567890123456789012345678901234567890}','012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234');
INSERT INTO globalmacro (globalmacroid, macro, value) VALUES (7,'{$A}','Some text');
INSERT INTO globalmacro (globalmacroid, macro, value) VALUES (8,'{$1}','Numeric macro');
INSERT INTO globalmacro (globalmacroid, macro, value) VALUES (9,'{$_}','Underscore');

-- Adding records into Auditlog

-- add user
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid) VALUES (500, 1, 1328684400, 0, 0, 'User alias [Admin] name [Admin] surname [Admin]', '192.168.3.38', 0);
-- update user
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid) VALUES (501, 1, 1328684410, 1, 0, 'User alias [Admin2] name [Admin2] surname [Admin2]', '192.168.3.38', 0);
-- delete user
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid) VALUES (502, 1, 1328684420, 2, 0, 'User alias [Admin2] name [Admin2] surname [Admin2]', '192.168.3.38', 0);
-- can check also block user (enable,disable)

-- add host
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (503, 1, 1328684430, 0, 4, '0', '192.168.3.32', 10054, 'H1');

-- update host
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (504, 1, 1328684440, 1, 4, '0', '192.168.3.32', 10054, 'H1 updated');

-- delete host
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (505, 1, 1328684450, 2, 4, '0', '192.168.3.32', 10054, 'H1 updated');

-- enable host, hosts.status: 1 => 0
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (506, 1, 1328684460, 1, 4, '0', '192.168.3.32', 10054, 'H1 updated');
INSERT INTO auditlog_details (auditdetailid, auditid, table_name, field_name, oldvalue, newvalue) VALUES (500, 506, 'hosts', 'status', '1', '0');

-- disable host, hosts.status: 0 => 1
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (507, 1, 1328684460, 1, 4, '0', '192.168.3.32', 10054, 'H1 updated');
INSERT INTO auditlog_details (auditdetailid, auditid, table_name, field_name, oldvalue, newvalue) VALUES (501, 507, 'hosts', 'status', '0', '1');

-- add hostgroup
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (508, 1, 1328684470, 0, 14, '0', '192.168.3.32', 6, 'HG1');

-- update hostgroup
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (509, 1, 1328684470, 1, 14, '0', '192.168.3.32', 6, 'HG1 updated');
INSERT INTO auditlog_details (auditdetailid, auditid, table_name, field_name, oldvalue, newvalue) VALUES (502, 509, 'groups', 'name', 'HG1', 'HG1 updated');

-- delete hostgroup
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (510, 1, 1328684480, 2, 14, '0', '192.168.3.32', 6, 'HG1 updated');

-- add item
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (511, 1, 1328684490, 0, 15, '0', '192.168.3.32', 22500, 'Item added');

-- update item
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (512, 1, 1328684500, 1, 15, '0', '192.168.3.32', 22500, 'Item updated');

-- disable item
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (513, 1, 1328684520, 1, 15, '0', '192.168.3.32', 22500, 'H1 updated:test_item');
INSERT INTO auditlog_details (auditdetailid, auditid, table_name, field_name, oldvalue, newvalue) VALUES (503, 513, 'items', 'status', '0', '1');

-- enable item
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (514, 1, 1328684530, 1, 15, '0', '192.168.3.32', 22500, 'H1 updated:test_item');
INSERT INTO auditlog_details (auditdetailid, auditid, table_name, field_name, oldvalue, newvalue) VALUES (504, 514, 'items', 'status', '1', '0');

-- delete item
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (515, 1, 1328684540, 2, 15, 'Item [agent.version] [22500] Host [H1]', '192.168.3.32', 22500, 'Item deleted');

-- add trigger
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (516, 1, 1328684550, 0, 13, '0', '192.168.3.32', 13000, 'Trigger1');

-- update trigger
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (517, 1, 1328684555, 0, 13, '0', '192.168.3.32', 13000, 'Trigger1');
INSERT INTO auditlog_details (auditdetailid, auditid, table_name, field_name, oldvalue, newvalue) VALUES (505, 517, '', 'description', 'Trigger1', 'Trigger1 updated');

-- disable trigger
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (518, 1, 1328684560, 1, 13, '0', '192.168.3.32', 13000, 'H1 updated:Trigger1');
INSERT INTO auditlog_details (auditdetailid, auditid, table_name, field_name, oldvalue, newvalue) VALUES (506, 518, 'triggers', 'status', '0', '1');

-- enable trigger
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (519, 1, 1328684570, 1, 13, '0', '192.168.3.32', 13000, 'H1 updated:Trigger1');
INSERT INTO auditlog_details (auditdetailid, auditid, table_name, field_name, oldvalue, newvalue) VALUES (507, 519, 'triggers', 'status', '1', '0');

-- TODO: delete trigger

-- add action
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (520, 1, 1328684580, 0, 5, 'Name: Action1', '192.168.3.32', 0, '');

-- update action
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (521, 1, 1328684590, 1, 5, 'Name: Action1 updated', '192.168.3.32', 0, '');

-- disable action
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (522, 1, 1328684600, 1, 5, 'Actions [11] disabled', '192.168.3.32', 0, '');

-- enable action
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (523, 1, 1328684610, 1, 5, 'Actions [11] enabled', '192.168.3.32', 0, '');

-- delete action
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (524, 1, 1328684620, 2, 5, 'Actions [11] deleted', '192.168.3.32', 11, 'Action deleted');

-- add application
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (525, 1, 1328684630, 0, 12, 'Application [App1 ] [177]', '192.168.3.32', 0, '');

-- update application
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (526, 1, 1328684640, 1, 12, 'Application [App1 updated ] []', '192.168.3.32', 0, '');

-- disable application  (work in the same way as update app- disable all items on this host), such records do not exist at this moment
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (527, 1, 1328684650, 1, 12, '0', '192.168.3.32', 22165, 'test_item');
INSERT INTO auditlog_details (auditdetailid, auditid, table_name, field_name, oldvalue, newvalue) VALUES (508, 527, 'items', 'status', '0', '1');

-- enable application (work in the same way as update app- disable all items on this host), such records do not exist at this moment
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (528, 1, 1328684660, 1, 12, '0', '192.168.3.32', 22165, 'test_item');
INSERT INTO auditlog_details (auditdetailid, auditid, table_name, field_name, oldvalue, newvalue) VALUES (509, 528, 'items', 'status', '1', '0');

-- delete application
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (529, 1, 1328684665, 2, 12, 'Application [App1] from host [H1]', '192.168.3.32', 0, '');

-- add graph
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (530, 1, 1328684670, 0, 6, 'Graph [graph1]', '192.168.3.32', 0, '');

-- update graph
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (531, 1, 1328684680, 1, 6, 'Graph [graph1 updated]', '192.168.3.32', 0, '');

-- delete graph, no records in the DB for this operation
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (532, 1, 1328684690, 2, 6, 'Graph ID [386] Graph [graph1]', '192.168.3.32', 0, '');

-- add image
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (533, 1, 1328684700, 0, 16, 'Image [1image] added', '192.168.3.32', 0, '');

-- update image
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (534, 1, 1328684710, 1, 16, 'Image [1image] updated', '192.168.3.32', 0, '');

-- delete image
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (535, 1, 1328684720, 2, 16, 'Image [1image] updated', '192.168.3.32', 0, '');

-- add globalmacro
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (536, 1, 1328684730, 0, 29, '0', '192.168.3.32', 9, '{$B}&nbsp;&rArr;&nbsp;abcd');

-- update globalmacro
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (537, 1, 1328684740, 1, 29, '0', '192.168.3.32', 9, '{$B}&nbsp;&rArr;&nbsp;xyz');

-- delete globalmacro
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (538, 1, 1328684750, 2, 29, '0', '192.168.3.32', 9, 'Array&nbsp;&rArr;&nbsp;xyz');

-- add valuemap
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (539, 1, 1328684760, 0, 17, 'Value map [testvaluemap1]', '192.168.3.32', 0, '');

-- update valuemap
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (540, 1, 1328684770, 1, 17, '0', '192.168.3.32', 0, '');

-- delete valuemap
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (541, 1, 1328684780, 2, 17, '0', '192.168.3.32', 0, '');

-- add maint period
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (542, 1, 1328684790, 0, 27, 'Name: Maintenance1', '192.168.3.32', 0, '');

-- update maint period
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (543, 1, 1328684780, 1, 27, 'Name: Maintenance2', '192.168.3.32', 0, '');

-- delete maint period
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (544, 1, 1328684790, 2, 27, 'Id [3] Name [Maintenance2]', '192.168.3.32', 0, '');

-- add IT service
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (545, 1, 1328684800, 0, 18, 'Name [service1] id [1]', '192.168.3.32', 0, '');

-- update IT service
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (546, 1, 1328684810, 1, 18, 'Name [service1] id [1]', '192.168.3.32', 0, '');

-- delete IT service
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (547, 1, 1328684820, 2, 18, 'Name [service1] id [1]', '192.168.3.32', 0, '');

-- add DRule
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (548, 1, 1328684830, 0, 23, '[10] drule1', '192.168.3.32', 0, '');

-- update DRule
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (549, 1, 1328684840, 1, 23, '[10] drule1-new', '192.168.3.32', 0, '');

-- delete DRule
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (550, 1, 1328684850, 2, 23, 'Discovery rule [10] drule1-new deleted', '192.168.3.32', 0, '');

-- disable DRule
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (551, 1, 1328684860, 1, 23, 'Discovery rule [10] disabled', '192.168.3.32', 0, '');

-- enable DRule
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (552, 1, 1328684610, 1, 23, 'Discovery rule [10] enabled', '192.168.3.32', 0, '');

-- add map
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (553, 1, 1328684620, 0, 19, 'Test Map1', '192.168.3.32', 20, '');

-- update map
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (554, 1, 1328684630, 1, 19, 'Test Map2', '192.168.3.32', 20, '');

-- delete map
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (555, 1, 1328684640, 2, 19, '0', '192.168.3.32', 20, 'Test Map2');

-- add media type
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (556, 1, 1328684650, 0, 3, 'Media type [Media1]', '192.168.3.32', 0, '');

-- update media type
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (557, 1, 1328684660, 1, 3, 'Media type [Media2]', '192.168.3.32', 0, '');

-- disable media type
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (558, 1, 1328684660, 1, 3, 'Media type [Media2]', '192.168.3.32', 0, '');

-- enable media type
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (559, 1, 1328684670, 1, 3, 'Media type [Media2]', '192.168.3.32', 0, '');

-- delete media type
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (560, 1, 1328684680, 2, 3, 'Media type [Media2]', '192.168.3.32', 0, '');

-- add node
-- update node
-- delete node

-- add proxy
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (564, 1, 1328684720, 0, 26, '[test_proxy1] [10054]', '192.168.3.32', 0, '');

-- update proxy
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (565, 1, 1328684730, 1, 26, '[test_proxy2] [10054]', '192.168.3.32', 0, '');

-- disable proxy - this will disable all hosts that are monitored by this proxy
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (566, 1, 1328684740, 1, 4, '0', '192.168.3.32', 10053, 'Test host');
INSERT INTO auditlog_details (auditdetailid, auditid, table_name, field_name, oldvalue, newvalue) VALUES (510, 566, 'hosts', 'status', '0', '1');

-- enable proxy - this will enable all hosts that are monitored by this proxy
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (567, 1, 1328684750, 1, 4, '0', '192.168.3.32', 10053, 'Test host');
INSERT INTO auditlog_details (auditdetailid, auditid, table_name, field_name, oldvalue, newvalue) VALUES (511, 567, 'hosts', 'status', '1', '0');

-- delete proxy
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (568, 1, 1328684760, 1, 4, '0', '192.168.3.32', 10053, 'Test host');

-- add web scenario
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (569, 1, 1328684770, 0, 22, 'Scenario [Scenario1] [1] Host [Test host]', '192.168.3.32', 0, '');

-- update web scenario
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (570, 1, 1328684780, 1, 22, 'Scenario [Scenario1] [1] Host [Test host]', '192.168.3.32', 0, '');

-- disable scenario
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (571, 1, 1328684790, 1, 22, 'Scenario [Scenario1] [1] Host [Test host]Scenario disabled', '192.168.3.32', 0, '');

-- enable scenario
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (572, 1, 1328684800, 1, 22, 'Scenario [Scenario1] [1] Host [Test host]Scenario activated', '192.168.3.32', 0, '');

-- delete scenario
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (573, 1, 1328684810, 1, 22, 'Scenario "Scenario1" "1" host "Test host".', '192.168.3.32', 0, '');

-- add screen
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (574, 1, 1328684820, 0, 20, 'Name [screen1]', '192.168.3.32', 0, '');

-- update screen
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (575, 1, 1328684830, 1, 20, 'Name [screen1]', '192.168.3.32', 0, '');

-- delete screen
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (576, 1, 1328684840, 2, 20, '0', '192.168.3.32', 24, 'screen1');

-- add script
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (577, 1, 1328684850, 0, 25, 'Name [script1] id [4]', '192.168.3.32', 0, '');

-- update script
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (578, 1, 1328684860, 1, 25, 'Name [script1] id [4]', '192.168.3.32', 0, '');

-- delete script
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (579, 1, 1328684870, 2, 25, 'Script [4]', '192.168.3.32', 0, '');

-- add slideshow
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (580, 1, 1328684880, 0, 24, 'Name Slideshow_4', '192.168.3.32', 0, '');

-- update slideshow
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (581, 1, 1328684890, 1, 24, 'Name Slideshow_4', '192.168.3.32', 0, '');

-- delete slideshow
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (582, 1, 1328684900, 2, 24, 'Name Slideshow_4', '192.168.3.32', 0, '');

-- add template
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (583, 1, 1328684910, 0, 30, '', '192.168.3.32', 10055, 'Test_template1');

-- update template
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (584, 1, 1328684920, 1, 30, '', '192.168.3.32', 10055, 'Test_template1');

-- delete template
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (585, 1, 1328684930, 2, 30, '0', '192.168.3.32', 10055, 'Test_template1');

-- updating record "Configuration of Zabbix" in the auditlog
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, details, ip, resourceid, resourcename) VALUES (700, 1, 1328684860, 1, 2, 'Default theme "originalblue".; Event acknowledges "1".; Show events not older than (in days) "7".; Show events max "100".; Dr...', '192.168.3.32', 0, '');

-- adding test data to the 'alerts' table for testing Audit->Actions report
INSERT INTO events (eventid, source, object, objectid, clock, value, acknowledged, ns) VALUES (1, 0, 0, 13136, 1329724790, 1, 0, 0);

INSERT INTO alerts (alertid, actionid, eventid, userid, clock, mediatypeid, sendto, subject, message, status, retries, error, esc_step, alerttype) VALUES (1, 12, 1, 1, 1329724800, 1, 'igor.danoshaites@zabbix.com', 'PROBLEM: Value of item key1 > 5', 'Event at 2012.02.20 10:00:00 Hostname: H1 Value of item key1 > 5: PROBLEM Last value: 6', 1, 0, '', 1, 0);
INSERT INTO alerts (alertid, actionid, eventid, userid, clock, mediatypeid, sendto, subject, message, status, retries, error, esc_step, alerttype) VALUES (2, 12, 1, 1, 1329724810, 1, 'igor.danoshaites@zabbix.com', 'PROBLEM: Value of item key1 > 6', 'Event at 2012.02.20 10:00:10 Hostname: H1 Value of item key1 > 6: PROBLEM', 1, 0, '', 1, 0);
INSERT INTO alerts (alertid, actionid, eventid, userid, clock, mediatypeid, sendto, subject, message, status, retries, error, esc_step, alerttype) VALUES (3, 12, 1, 1, 1329724820, 1, 'igor.danoshaites@zabbix.com', 'PROBLEM: Value of item key1 > 7', 'Event at 2012.02.20 10:00:20 Hostname: H1 Value of item key1 > 7: PROBLEM', 1, 0, '', 1, 0);
INSERT INTO alerts (alertid, actionid, eventid, userid, clock, mediatypeid, sendto, subject, message, status, retries, error, esc_step, alerttype) VALUES (4, 12, 1, 1, 1329724830, 1, 'igor.danoshaites@zabbix.com', 'PROBLEM: Value of item key1 > 10', 'Event at 2012.02.20 10:00:30 Hostname: H1 Value of item key1 > 10: PROBLEM', 2, 0, 'Get value from agent failed: cannot connect to [[127.0.0.1]:10050]: [111] Connection refused', 1, 0);
INSERT INTO alerts (alertid, actionid, eventid, userid, clock, mediatypeid, sendto, subject, message, status, retries, error, esc_step, alerttype) VALUES (5, 12, 1, 1, 1329724840, 1, 'igor.danoshaites@zabbix.com', 'PROBLEM: Value of item key1 > 20', 'Event at 2012.02.20 10:00:40 Hostname: H1 Value of item key1 > 20: PROBLEM', 0, 0, 'Get value from agent failed: cannot connect to [[127.0.0.1]:10050]: [111] Connection refused', 1, 0);
INSERT INTO alerts (alertid, actionid, eventid, userid, clock, mediatypeid, sendto, subject, message, status, retries, error, esc_step, alerttype) VALUES (6, 12, 1, NULL, 1329724850, NULL, '', '', 'Command: H1:ls -la', 1, 0, '', 1, 1);
INSERT INTO alerts (alertid, actionid, eventid, userid, clock, mediatypeid, sendto, subject, message, status, retries, error, esc_step, alerttype) VALUES (7, 12, 1, NULL, 1329724860, NULL, '', '', 'Command: H1:ls -la', 1, 0, '', 1, 1);

-- deleting auditid from the ids table
-- delete from ids where table_name='auditlog' and field_name='auditid'

-- host, item, trigger  for testing macro resolving in trigger description
INSERT INTO hosts (host, name, status, hostid) VALUES ('Host for trigger description macros','Host for trigger description macros', 0, 20006);
INSERT INTO hosts_groups (hostid, groupid, hostgroupid) VALUES (20006, 4, 101);
INSERT INTO interface (type, ip, dns, useip, port, main, hostid, interfaceid) VALUES (1, '127.0.0.1', '', '1', '10050', '1', 20006, 10025);
INSERT INTO items (name, key_, hostid, interfaceid, delay, value_type, itemid) VALUES ('item1', 'key1', 20006, 10025, 30, 3, 24338);
INSERT INTO triggers (description, value, state, lastchange, triggerid) VALUES ('trigger host.host:{HOST.HOST} | host.host2:{HOST.HOST2} | host.name:{HOST.NAME} | item.value:{ITEM.VALUE} | item.value1:{ITEM.VALUE1} | item.lastvalue:{ITEM.LASTVALUE} | host.ip:{HOST.IP} | host.dns:{HOST.DNS} | host.conn:{HOST.CONN}', 0, 1, '1339761311', 15517);
INSERT INTO functions (functionid, itemid, triggerid, function, parameter) VALUES (15946, 24338, 15517, 'last', '0');

-- create an empty template for inheritance testing
INSERT INTO hosts (hostid, proxy_hostid, host, status, disable_until, error, available, errors_from, lastaccess, ipmi_authtype, ipmi_privilege, ipmi_username, ipmi_password, ipmi_disable_until, ipmi_available, snmp_disable_until, snmp_available, maintenanceid, maintenance_status, maintenance_type, maintenance_from, ipmi_errors_from, snmp_errors_from, ipmi_error, snmp_error,name) VALUES (30000,NULL,'Inheritance test template',3,0,'',0,0,0,0,2,'','',0,0,0,0,NULL,0,0,0,0,0,'','','Inheritance test template');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (30000, 30000, 1);
INSERT INTO hosts (host, name, status, hostid) VALUES ('Template inheritance test host','Template inheritance test host', 0, 30001);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (30001, 30001, 4);
INSERT INTO interface (type, ip, dns, useip, port, hostid, interfaceid) VALUES (1, '127.0.0.1', '', 1, '10050', 30001, 30000);
INSERT INTO hosts_templates (hosttemplateid, hostid, templateid) VALUES (30000, 30001, 30000);

-- create Form test template
INSERT INTO hosts (hostid, proxy_hostid, host, status, disable_until, error, available, errors_from, lastaccess, ipmi_authtype, ipmi_privilege, ipmi_username, ipmi_password, ipmi_disable_until, ipmi_available, snmp_disable_until, snmp_available, maintenanceid, maintenance_status, maintenance_type, maintenance_from, ipmi_errors_from, snmp_errors_from, ipmi_error, snmp_error,name) VALUES (40000,NULL,'Form test template',3,0,'',0,0,0,0,2,'','',0,0,0,0,NULL,0,0,0,0,0,'','','Form test template');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (40000, 40000, 1);

-- create Simple form test
INSERT INTO hosts (hostid, host, name, status) VALUES (40001, 'Simple form test host','Simple form test host', 0);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (40001, 40001, 4);
INSERT INTO hosts_templates (hosttemplateid, hostid, templateid) VALUES (40000, 40001, 40000);

-- testFormItem interfaces
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, port) VALUES (40011, 40001, 1, 1, 1, '127.0.5.1', '10051');
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, port) VALUES (40012, 40001, 1, 2, 1, '127.0.5.2', '10052');
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, port) VALUES (40013, 40001, 1, 3, 1, '127.0.5.3', '10053');
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, port) VALUES (40014, 40001, 1, 4, 1, '127.0.5.4', '10054');

-- testFormItem.LayoutCheck testFormItem.SimpleUpdate
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid) VALUES (30000, 0, 40001, 'testFormItem1', 'testFormItems', 'test-item-form1', 30, 40011);
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid) VALUES (30001, 0, 40001, 'testFormItem2', 'testFormItems', 'test-item-form2', 30, 40011);
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid) VALUES (30002, 0, 40001, 'testFormItem3', 'testFormItems', 'test-item-form3', 30, 40011);
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid) VALUES (30003, 0, 40001, 'testFormItem4', 'testFormItems', 'test-item-form4', 30, 40011);

-- testFormItem.LayoutCheck testInheritanceItem.SimpleUpdate
INSERT INTO items (itemid, type, snmp_community, snmp_oid, hostid, name, description, key_, delay, history, trends, status, value_type, trapper_hosts, units, multiplier, delta, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, formula, error, lastlogsize, logtimefmt, templateid, valuemapid, delay_flex, params, ipmi_sensor, data_type, authtype, username, password, publickey, privatekey, mtime, flags) VALUES (40000, 0, '', '', 30000, 'testInheritanceItem1', 'testInheritanceItems','test-inheritance-item1', 30, 90, 365, 0, 3, '', '', 0, 0, '', 0, '', '', '1', '', 0, '', NULL, NULL, '', '', '', 0, 0, '', '', '', '', 0, 0);
INSERT INTO items (itemid, type, snmp_community, snmp_oid, hostid, name, description, key_, delay, history, trends, status, value_type, trapper_hosts, units, multiplier, delta, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, formula, error, lastlogsize, logtimefmt, templateid, valuemapid, delay_flex, params, ipmi_sensor, data_type, authtype, username, password, publickey, privatekey, mtime, flags, interfaceid) VALUES (40001, 0, '', '', 30001, 'testInheritanceItem1', 'testInheritanceItems','test-inheritance-item1', 30, 90, 365, 0, 0, '', '', 0, 0, '', 0, '', '', '1', '', 0, '', 40000, NULL, '', '', '', 0, 0, '', '', '', '', 0, 0, 40011);
INSERT INTO items (itemid, type, snmp_community, snmp_oid, hostid, name, description, key_, delay, history, trends, status, value_type, trapper_hosts, units, multiplier, delta, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, formula, error, lastlogsize, logtimefmt, templateid, valuemapid, delay_flex, params, ipmi_sensor, data_type, authtype, username, password, publickey, privatekey, mtime, flags) VALUES (40002, 0, '', '', 30000, 'testInheritanceItem2', 'testInheritanceItems','test-inheritance-item2', 30, 90, 365, 0, 3, '', '', 0, 0, '', 0, '', '', '1', '', 0, '', NULL, NULL, '', '', '', 0, 0, '', '', '', '', 0, 0);
INSERT INTO items (itemid, type, snmp_community, snmp_oid, hostid, name, description, key_, delay, history, trends, status, value_type, trapper_hosts, units, multiplier, delta, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, formula, error, lastlogsize, logtimefmt, templateid, valuemapid, delay_flex, params, ipmi_sensor, data_type, authtype, username, password, publickey, privatekey, mtime, flags, interfaceid) VALUES (40003, 0, '', '', 30001, 'testInheritanceItem2', 'testInheritanceItems','test-inheritance-item2', 30, 90, 365, 0, 0, '', '', 0, 0, '', 0, '', '', '1', '', 0, '', 40002, NULL, '', '', '', 0, 0, '', '', '', '', 0, 0, 40011);
INSERT INTO items (itemid, type, snmp_community, snmp_oid, hostid, name, description, key_, delay, history, trends, status, value_type, trapper_hosts, units, multiplier, delta, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, formula, error, lastlogsize, logtimefmt, templateid, valuemapid, delay_flex, params, ipmi_sensor, data_type, authtype, username, password, publickey, privatekey, mtime, flags) VALUES (40004, 0, '', '', 30000, 'testInheritanceItem3', 'testInheritanceItems','test-inheritance-item3', 30, 90, 365, 0, 3, '', '', 0, 0, '', 0, '', '', '1', '', 0, '', NULL, NULL, '', '', '', 0, 0, '', '', '', '', 0, 0);
INSERT INTO items (itemid, type, snmp_community, snmp_oid, hostid, name, description, key_, delay, history, trends, status, value_type, trapper_hosts, units, multiplier, delta, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, formula, error, lastlogsize, logtimefmt, templateid, valuemapid, delay_flex, params, ipmi_sensor, data_type, authtype, username, password, publickey, privatekey, mtime, flags, interfaceid) VALUES (40005, 0, '', '', 30001, 'testInheritanceItem3', 'testInheritanceItems','test-inheritance-item3', 30, 90, 365, 0, 0, '', '', 0, 0, '', 0, '', '', '1', '', 0, '', 40004, NULL, '', '', '', 0, 0, '', '', '', '', 0, 0, 40011);
INSERT INTO items (itemid, type, snmp_community, snmp_oid, hostid, name, description, key_, delay, history, trends, status, value_type, trapper_hosts, units, multiplier, delta, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, formula, error, lastlogsize, logtimefmt, templateid, valuemapid, delay_flex, params, ipmi_sensor, data_type, authtype, username, password, publickey, privatekey, mtime, flags) VALUES (40006, 0, '', '', 30000, 'testInheritanceItem4', 'testInheritanceItems','test-inheritance-item4', 30, 90, 365, 0, 3, '', '', 0, 0, '', 0, '', '', '1', '', 0, '', NULL, NULL, '', '', '', 0, 0, '', '', '', '', 0, 0);
INSERT INTO items (itemid, type, snmp_community, snmp_oid, hostid, name, description, key_, delay, history, trends, status, value_type, trapper_hosts, units, multiplier, delta, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, formula, error, lastlogsize, logtimefmt, templateid, valuemapid, delay_flex, params, ipmi_sensor, data_type, authtype, username, password, publickey, privatekey, mtime, flags, interfaceid) VALUES (40007, 0, '', '', 30001, 'testInheritanceItem4', 'testInheritanceItems','test-inheritance-item4', 30, 90, 365, 0, 0, '', '', 0, 0, '', 0, '', '', '1', '', 0, '', 40006, NULL, '', '', '', 0, 0, '', '', '', '', 0, 0, 40011);

-- testFormTrigger.SimpleCreate
INSERT INTO items (itemid, type, snmp_community, snmp_oid, hostid, name, description, key_, delay, history, trends, status, value_type, trapper_hosts, units, multiplier, delta, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, formula, error, lastlogsize, logtimefmt, templateid, valuemapid, delay_flex, params, ipmi_sensor, data_type, authtype, username, password, publickey, privatekey, mtime, flags, interfaceid) VALUES (30004, 0, '', '', 40001, 'testFormItem', 'testFormItems','test-item-reuse', 30, 90, 365, 0, 0, '', '', 0, 0, '', 0, '', '', 1, '', 0, '', NULL, NULL, '', '', '', 0, 0, '', '', '', '', 0, 0, 40011);

-- testFormTrigger.SimpleUpdate
INSERT INTO triggers (triggerid, expression, description, comments) VALUES (14000, '{14000}=0', 'testFormTrigger1', '');
INSERT INTO functions (functionid, itemid, triggerid, function, parameter) VALUES (14000, 30004, 14000, 'last', '0');

INSERT INTO triggers (triggerid, expression, description, comments) VALUES (14001, '{14001}=0', 'testFormTrigger2', '');
INSERT INTO functions (functionid, itemid, triggerid, function, parameter) VALUES (14001, 30004, 14001, 'last', '0');

INSERT INTO triggers (triggerid, expression, description, comments) VALUES (14002, '{14002}=0', 'testFormTrigger3', '');
INSERT INTO functions (functionid, itemid, triggerid, function, parameter) VALUES (14002, 30004, 14002, 'last', '0');

INSERT INTO triggers (triggerid, expression, description, comments) VALUES (14003, '{14003}=0', 'testFormTrigger4', '');
INSERT INTO functions (functionid, itemid, triggerid, function, parameter) VALUES (14003, 30004, 14003, 'last', '0');

-- testFormGraph.LayoutCheck testInheritanceGraph.SimpleUpdate testFormTrigger.SimpleUpdate testInheritanceTrigger.SimpleUpdate testInheritanceItem.SimpleUpdate
INSERT INTO items (name, key_, hostid, delay, value_type, itemid, params, description) VALUES ('itemInheritance', 'key-item-inheritance', 30000, 30, 3, 24329, '', '');
INSERT INTO items (name, key_, hostid, delay, value_type, itemid, templateid, params, description) VALUES ('itemInheritance', 'key-item-inheritance', 30001, 30, 3, 24333, 30000, '', '');
-- testFormDiscoveryRule.SimpleCreate testInheritanceDiscoveryRule.SimpleCreate
INSERT INTO items (name, key_, hostid, delay, value_type, itemid, params, description, interfaceid) VALUES ('itemInheritanceTest', 'key-test-inheritance', 30001, 30, 3, 35000, '', '', 30000);
-- testFormDiscoveryRule.CheckLayout testInheritanceDiscoveryRule.CheckLayout testFormDiscoveryRule.SimpleCreate testInheritanceDiscoveryRule.SimpleCreate
INSERT INTO interface (type, ip, dns, useip, port, main, hostid, interfaceid) VALUES (1, '127.0.7.1', '', '1', '10071', '1', 30001, 50011);
INSERT INTO interface (type, ip, dns, useip, port, main, hostid, interfaceid) VALUES (2, '127.0.7.2', '', '1', '10072', '1', 30001, 50012);
INSERT INTO interface (type, ip, dns, useip, port, main, hostid, interfaceid) VALUES (3, '127.0.7.3', '', '1', '10073', '1', 30001, 50013);
INSERT INTO interface (type, ip, dns, useip, port, main, hostid, interfaceid) VALUES (4, '127.0.7.4', '', '1', '10074', '1', 30001, 50014);

-- testFormTrigger.SimpleUpdate and testInheritanceTrigger.SimpleUpdate
INSERT INTO triggers (triggerid, expression, description, templateid) VALUES (15000, '{15000}=0', 'testInheritanceTrigger1', NULL);
INSERT INTO triggers (triggerid, expression, description, templateid) VALUES (15001, '{15001}=0', 'testInheritanceTrigger1', 15000);
INSERT INTO functions (functionid, itemid, triggerid, function, parameter) VALUES (15000, 24329, 15000, 'last', '0');
INSERT INTO functions (functionid, itemid, triggerid, function, parameter) VALUES (15001, 24333, 15001, 'last', '0');

INSERT INTO triggers (triggerid, expression, description, templateid) VALUES (15002, '{15002}=0', 'testInheritanceTrigger2', NULL);
INSERT INTO triggers (triggerid, expression, description, templateid) VALUES (15003, '{15003}=0', 'testInheritanceTrigger2', 15002);
INSERT INTO functions (functionid, itemid, triggerid, function, parameter) VALUES (15002, 24329, 15002, 'last', '0');
INSERT INTO functions (functionid, itemid, triggerid, function, parameter) VALUES (15003, 24333, 15003, 'last', '0');

INSERT INTO triggers (triggerid, expression, description, templateid) VALUES (15004, '{15004}=0', 'testInheritanceTrigger3', NULL);
INSERT INTO triggers (triggerid, expression, description, templateid) VALUES (15005, '{15005}=0', 'testInheritanceTrigger3', 15004);
INSERT INTO functions (functionid, itemid, triggerid, function, parameter) VALUES (15004, 24329, 15004, 'last', '0');
INSERT INTO functions (functionid, itemid, triggerid, function, parameter) VALUES (15005, 24333, 15005, 'last', '0');

INSERT INTO triggers (triggerid, expression, description, templateid) VALUES (15006, '{15006}=0', 'testInheritanceTrigger4', NULL);
INSERT INTO triggers (triggerid, expression, description, templateid) VALUES (15007, '{15007}=0', 'testInheritanceTrigger4', 15006);
INSERT INTO functions (functionid, itemid, triggerid, function, parameter) VALUES (15006, 24329, 15006, 'last', '0');
INSERT INTO functions (functionid, itemid, triggerid, function, parameter) VALUES (15007, 24333, 15007, 'last', '0');

-- testFormGraph.LayoutCheck testFormGraph.SimpleUpdate
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (300000,'testFormGraph1',900,200,0.0,100.0,NULL,1,0,1,1,0,0.0,0.0,1,1,NULL,NULL,0);
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (300001,'testFormGraph2',900,200,0.0,100.0,NULL,1,0,1,1,0,0.0,0.0,1,1,NULL,NULL,0);
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (300002,'testFormGraph3',900,200,0.0,100.0,NULL,1,0,1,1,0,0.0,0.0,1,1,NULL,NULL,0);
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (300003,'testFormGraph4',900,200,0.0,100.0,NULL,1,0,1,1,0,0.0,0.0,1,1,NULL,NULL,0);

-- testFormGraph.LayoutCheck testFormGraph.SimpleUpdate
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (300000, 300000, 30004, 1, 1, 'FF5555', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (300001, 300001, 30004, 1, 1, 'FF5555', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (300002, 300002, 30004, 1, 1, 'FF5555', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (300003, 300003, 30004, 1, 1, 'FF5555', 0, 2, 0);

-- testFormGraph.LayoutCheck testInheritanceGraph.SimpleUpdate
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (500000,'testInheritanceGraph1',900,200,0.0,100.0,NULL,1,0,1,1,0,0.0,0.0,1,1,NULL,NULL,0);
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (500001,'testInheritanceGraph1',900,200,0.0,100.0,500000,1,0,1,1,0,0.0,0.0,1,1,NULL,NULL,0);
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (500002,'testInheritanceGraph2',900,200,0.0,100.0,NULL,1,0,1,1,0,0.0,0.0,1,1,NULL,NULL,0);
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (500003,'testInheritanceGraph2',900,200,0.0,100.0,500002,1,0,1,1,0,0.0,0.0,1,1,NULL,NULL,0);
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (500004,'testInheritanceGraph3',900,200,0.0,100.0,NULL,1,0,1,1,0,0.0,0.0,1,1,NULL,NULL,0);
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (500005,'testInheritanceGraph3',900,200,0.0,100.0,500004,1,0,1,1,0,0.0,0.0,1,1,NULL,NULL,0);
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (500006,'testInheritanceGraph4',900,200,0.0,100.0,NULL,1,0,1,1,0,0.0,0.0,1,1,NULL,NULL,0);
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (500007,'testInheritanceGraph4',900,200,0.0,100.0,500006,1,0,1,1,0,0.0,0.0,1,1,NULL,NULL,0);

-- testFormGraph.LayoutCheck testInheritanceGraph.SimpleUpdate
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (500000, 500000, 24329, 1, 1, 'FF5555', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (500001, 500001, 24333, 1, 1, 'FF5555', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (500002, 500002, 24329, 1, 1, 'FF5555', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (500003, 500003, 24333, 1, 1, 'FF5555', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (500004, 500004, 24329, 1, 1, 'FF5555', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (500005, 500005, 24333, 1, 1, 'FF5555', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (500006, 500006, 24329, 1, 1, 'FF5555', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (500007, 500007, 24333, 1, 1, 'FF5555', 0, 2, 0);

-- testFormDiscoveryRule.SimpleUpdate
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, delay, params, description, interfaceid) VALUES ('testFormDiscoveryRule1', 'discovery-rule-form1', 40001, 4, 33700, 1,  50, '', '', 40011);
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, delay, params, description, interfaceid) VALUES ('testFormDiscoveryRule2', 'discovery-rule-form2', 40001, 4, 33701, 1,  50, '', '', 40011);
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, delay, params, description, interfaceid) VALUES ('testFormDiscoveryRule3', 'discovery-rule-form3', 40001, 4, 33702, 1,  50, '', '', 40011);
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, delay, params, description, interfaceid) VALUES ('testFormDiscoveryRule4', 'discovery-rule-form4', 40001, 4, 33703, 1,  50, '', '', 40011);

-- testInheritanceDiscoveryRule.LayoutCheck and testInheritanceDiscoveryRule.SimpleUpdate
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, delay, params, description) VALUES ('testInheritanceDiscoveryRule1', 'discovery-rule-inheritance1', 30000, 4, 33500, 1, 50, '', '');
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, templateid, delay, params, description) VALUES ('testInheritanceDiscoveryRule1', 'discovery-rule-inheritance1', 30001, 4, 33501, 1, 33500, 50, '', '');
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, delay, params, description) VALUES ('testInheritanceDiscoveryRule2', 'discovery-rule-inheritance2', 30000, 4, 33502, 1, 50, '', '');
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, templateid, delay, params, description) VALUES ('testInheritanceDiscoveryRule2', 'discovery-rule-inheritance2', 30001, 4, 33503, 1, 33502, 50, '', '');
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, delay, params, description) VALUES ('testInheritanceDiscoveryRule3', 'discovery-rule-inheritance3', 30000, 4, 33504, 1, 50, '', '');
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, templateid, delay, params, description) VALUES ('testInheritanceDiscoveryRule3', 'discovery-rule-inheritance3', 30001, 4, 33505, 1, 33504, 50, '', '');
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, delay, params, description) VALUES ('testInheritanceDiscoveryRule4', 'discovery-rule-inheritance4', 30000, 4, 33506, 1, 50, '', '');
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, templateid, delay, params, description) VALUES ('testInheritanceDiscoveryRule4', 'discovery-rule-inheritance4', 30001, 4, 33507, 1, 33506, 50, '', '');

-- testFormItemPrototype.SimpleUpdate
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, delay, params, description) VALUES ('testFormDiscoveryRule', 'discovery-rule-form', 40001, 4, 33800, 1,  50, '', '');

-- testFormItemPrototype.SimpleUpdate
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, delay, params, description) VALUES ('testFormItemPrototype1', 'item-prototype-form1', 40001, 3, 23800, 2, 5, '', '');
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid) values (501, 23800, 33800);
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, delay, params, description) VALUES ('testFormItemPrototype2', 'item-prototype-form2', 40001, 3, 23801, 2, 5, '', '');
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid) values (502, 23801, 33800);
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, delay, params, description) VALUES ('testFormItemPrototype3', 'item-prototype-form3', 40001, 3, 23802, 2, 5, '', '');
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid) values (503, 23802, 33800);
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, delay, params, description) VALUES ('testFormItemPrototype4', 'item-prototype-form4', 40001, 3, 23803, 2, 5, '', '');
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid) values (504, 23803, 33800);

-- testFormItemPrototype, testInheritanceItemPrototype etc. for all prototype testing
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, delay, params, description) VALUES ('testInheritanceDiscoveryRule', 'inheritance-discovery-rule', 30000, 4, 23500, 1, 50, '', '');
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, templateid, delay, params, description) VALUES ('testInheritanceDiscoveryRule', 'inheritance-discovery-rule', 30001, 4, 23501, 1, 23500, 50, '', '');

-- testInheritanceItemPrototype.SimpleUpdate and testInheritanceItemPrototype.SimpleCreate
INSERT INTO items (name, key_, hostid, value_type, itemid, flags) VALUES ('itemDiscovery', 'item-discovery-prototype', 30000, 3, 23600, 2);
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, templateid) VALUES ('itemDiscovery', 'item-discovery-prototype', 30001, 3, 23601, 2, 23600);
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid) values (301, 23600, 23500);
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid) values (302, 23601, 23501);

-- testInheritanceItemPrototype.SimpleUpdate
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, delay, params, description) VALUES ('testInheritanceItemPrototype1', 'item-prototype-test1', 30000, 3, 23700, 2, 5, '', '');
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, templateid, delay, params, description) VALUES ('testInheritanceItemPrototype1', 'item-prototype-test1', 30001, 3, 23701, 2, 23700, 5, '', '');
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid) values (401, 23700, 23500);
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid) values (402, 23701, 23501);
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, delay, params, description) VALUES ('testInheritanceItemPrototype2', 'item-prototype-test2', 30000, 3, 23702, 2, 5, '', '');
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, templateid, delay, params, description) VALUES ('testInheritanceItemPrototype2', 'item-prototype-test2', 30001, 3, 23703, 2, 23702, 5, '', '');
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid) values (403, 23702, 23500);
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid) values (404, 23703, 23501);
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, delay, params, description) VALUES ('testInheritanceItemPrototype3', 'item-prototype-test3', 30000, 3, 23704, 2, 5, '', '');
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, templateid, delay, params, description) VALUES ('testInheritanceItemPrototype3', 'item-prototype-test3', 30001, 3, 23705, 2, 23704, 5, '', '');
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid) values (405, 23704, 23500);
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid) values (406, 23705, 23501);
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, delay, params, description) VALUES ('testInheritanceItemPrototype4', 'item-prototype-test4', 30000, 3, 23706, 2, 5, '', '');
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, templateid, delay, params, description) VALUES ('testInheritanceItemPrototype4', 'item-prototype-test4', 30001, 3, 23707, 2, 23706, 5, '', '');
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid) values (407, 23706, 23500);
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid) values (408, 23707, 23501);

-- testFormTriggerPrototype.SimpleCreate
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, delay, params, description) VALUES ('testFormItemReuse', 'item-prototype-reuse', 40001, 3, 23804, 2, 5, '', '');
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid) values (505, 23804, 33800);

-- testFormTriggerPrototype.SimpleUpdate
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (15518,'{15947}=0','testFormTriggerPrototype1','',0,0,0,0,'','',NULL,0,0,2);
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (15519,'{15948}=0','testFormTriggerPrototype2','',0,0,0,0,'','',NULL,0,0,2);
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (15520,'{15949}=0','testFormTriggerPrototype3','',0,0,0,0,'','',NULL,0,0,2);
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (15521,'{15950}=0','testFormTriggerPrototype4','',0,0,0,0,'','',NULL,0,0,2);
INSERT INTO functions (functionid,itemid,triggerid,function,parameter) VALUES (15947,23804,15518,'last','0');
INSERT INTO functions (functionid,itemid,triggerid,function,parameter) VALUES (15948,23804,15519,'last','0');
INSERT INTO functions (functionid,itemid,triggerid,function,parameter) VALUES (15949,23804,15520,'last','0');
INSERT INTO functions (functionid,itemid,triggerid,function,parameter) VALUES (15950,23804,15521,'last','0');

-- testFormTriggerPrototype.LayoutCheck, testInheritanceTriggerPrototype.SimpleUpdate
INSERT INTO triggers (triggerid, expression, description, comments, flags) VALUES (16000, '{16000}=0', 'testInheritanceTriggerPrototype1', '', 2);
INSERT INTO functions (functionid, itemid, triggerid, function, parameter) VALUES (16000, 23600 ,16000,'last',0);
INSERT INTO triggers (triggerid, expression, description, comments, templateid, flags) VALUES (16001, '{16001}=0', 'testInheritanceTriggerPrototype1', '', 16000, 2);
INSERT INTO functions (functionid, itemid, triggerid, function, parameter) VALUES (16001, 23601 ,16001,'last',0);
INSERT INTO triggers (triggerid, expression, description, comments, flags) VALUES (16002, '{16002}=0', 'testInheritanceTriggerPrototype2', '', 2);
INSERT INTO functions (functionid, itemid, triggerid, function, parameter) VALUES (16002, 23600 ,16002,'last',0);
INSERT INTO triggers (triggerid, expression, description, comments, templateid, flags) VALUES (16003, '{16003}=0', 'testInheritanceTriggerPrototype2', '', 16002, 2);
INSERT INTO functions (functionid, itemid, triggerid, function, parameter) VALUES (16003, 23601 ,16003,'last',0);
INSERT INTO triggers (triggerid, expression, description, comments, flags) VALUES (16004, '{16004}=0', 'testInheritanceTriggerPrototype3', '', 2);
INSERT INTO functions (functionid, itemid, triggerid, function, parameter) VALUES (16004, 23600 ,16004,'last',0);
INSERT INTO triggers (triggerid, expression, description, comments, templateid, flags) VALUES (16005, '{16005}=0', 'testInheritanceTriggerPrototype3', '', 16004, 2);
INSERT INTO functions (functionid, itemid, triggerid, function, parameter) VALUES (16005, 23601 ,16005,'last',0);
INSERT INTO triggers (triggerid, expression, description, comments, flags) VALUES (16006, '{16006}=0', 'testInheritanceTriggerPrototype4', '', 2);
INSERT INTO functions (functionid, itemid, triggerid, function, parameter) VALUES (16006, 23600 ,16006,'last',0);
INSERT INTO triggers (triggerid, expression, description, comments, templateid, flags) VALUES (16007, '{16007}=0', 'testInheritanceTriggerPrototype4', '', 16006, 2);
INSERT INTO functions (functionid, itemid, triggerid, function, parameter) VALUES (16007, 23601 ,16007,'last',0);

-- testFormGraphPrototype.LayoutCheck and testFormGraphPrototype.SimpleUpdate
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (600000,'testFormGraphPrototype1',900,200,0.0,100.0,NULL,1,0,1,1,0,0.0,0.0,1,1,NULL,NULL,2);
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (600001,'testFormGraphPrototype2',900,200,0.0,100.0,NULL,1,0,1,1,0,0.0,0.0,1,1,NULL,NULL,2);
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (600002,'testFormGraphPrototype3',900,200,0.0,100.0,NULL,1,0,1,1,0,0.0,0.0,1,1,NULL,NULL,2);
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (600003,'testFormGraphPrototype4',900,200,0.0,100.0,NULL,1,0,1,1,0,0.0,0.0,1,1,NULL,NULL,2);

-- testFormGraphPrototype.LayoutCheck and testFormGraphPrototype.SimpleUpdate
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (600000, 600000, 30004, 1, 1, 'FF5555', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (600001, 600000, 23804, 1, 1, 'FF5555', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (600002, 600001, 30004, 1, 1, 'FF5555', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (600003, 600001, 23804, 1, 1, 'FF5555', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (600004, 600002, 30004, 1, 1, 'FF5555', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (600005, 600002, 23804, 1, 1, 'FF5555', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (600006, 600003, 30004, 1, 1, 'FF5555', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (600007, 600003, 23804, 1, 1, 'FF5555', 0, 2, 0);

-- testFormGraphPrototype.LayoutCheck and testInheritanceGraphPrototype.SimpleUpdate
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (700000,'testInheritanceGraphPrototype1',900,200,0.0,100.0,NULL,1,0,1,1,0,0.0,0.0,1,1,NULL,NULL,2);
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (700001,'testInheritanceGraphPrototype1',900,200,0.0,100.0,700000,1,0,1,1,0,0.0,0.0,1,1,NULL,NULL,2);
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (700002,'testInheritanceGraphPrototype2',900,200,0.0,100.0,NULL,1,0,1,1,0,0.0,0.0,1,1,NULL,NULL,2);
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (700003,'testInheritanceGraphPrototype2',900,200,0.0,100.0,700002,1,0,1,1,0,0.0,0.0,1,1,NULL,NULL,2);
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (700004,'testInheritanceGraphPrototype3',900,200,0.0,100.0,NULL,1,0,1,1,0,0.0,0.0,1,1,NULL,NULL,2);
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (700005,'testInheritanceGraphPrototype3',900,200,0.0,100.0,700004,1,0,1,1,0,0.0,0.0,1,1,NULL,NULL,2);
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (700006,'testInheritanceGraphPrototype4',900,200,0.0,100.0,NULL,1,0,1,1,0,0.0,0.0,1,1,NULL,NULL,2);
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (700007,'testInheritanceGraphPrototype4',900,200,0.0,100.0,700006,1,0,1,1,0,0.0,0.0,1,1,NULL,NULL,2);

-- testFormGraphPrototype.LayoutCheck and testInheritanceGraphPrototype.SimpleUpdate
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (700000, 700000, 23600, 1, 1, 'FF5555', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (700001, 700001, 23601, 1, 1, 'FF5555', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (700002, 700002, 23600, 1, 1, 'FF5555', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (700003, 700003, 23601, 1, 1, 'FF5555', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (700004, 700004, 23600, 1, 1, 'FF5555', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (700005, 700005, 23601, 1, 1, 'FF5555', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (700006, 700006, 23600, 1, 1, 'FF5555', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (700007, 700007, 23601, 1, 1, 'FF5555', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (700008, 700000, 24329, 1, 1, 'FF5555', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (700009, 700001, 24333, 1, 1, 'FF5555', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (700010, 700002, 24329, 1, 1, 'FF5555', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (700011, 700003, 24333, 1, 1, 'FF5555', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (700012, 700004, 24329, 1, 1, 'FF5555', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (700013, 700005, 24333, 1, 1, 'FF5555', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (700014, 700006, 24329, 1, 1, 'FF5555', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (700015, 700007, 24333, 1, 1, 'FF5555', 0, 2, 0);

-- testFormWeb.SimpleUpdate
INSERT INTO httptest (httptestid,name,applicationid,nextcheck,delay,status,variables,agent,authentication,http_user,http_password,hostid,templateid,http_proxy,retries) VALUES (94,'testFormWeb1',NULL,0,60,0,'','Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)',0,'','',40001,NULL,'',1);
INSERT INTO httptest (httptestid,name,applicationid,nextcheck,delay,status,variables,agent,authentication,http_user,http_password,hostid,templateid,http_proxy,retries) VALUES (95,'testFormWeb2',NULL,0,60,0,'','Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)',0,'','',40001,NULL,'',1);
INSERT INTO httptest (httptestid,name,applicationid,nextcheck,delay,status,variables,agent,authentication,http_user,http_password,hostid,templateid,http_proxy,retries) VALUES (96,'testFormWeb3',NULL,0,60,0,'','Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)',0,'','',40001,NULL,'',1);
INSERT INTO httptest (httptestid,name,applicationid,nextcheck,delay,status,variables,agent,authentication,http_user,http_password,hostid,templateid,http_proxy,retries) VALUES (97,'testFormWeb4',NULL,0,60,0,'','Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)',0,'','',40001,NULL,'',1);
INSERT INTO httpstep (httpstepid,httptestid,name,no,url,timeout,posts,required,status_codes,variables) VALUES (94,94,'testFormWeb1',1,'testFormWeb1',15,'','','','');
INSERT INTO httpstep (httpstepid,httptestid,name,no,url,timeout,posts,required,status_codes,variables) VALUES (95,95,'testFormWeb2',1,'testFormWeb2',15,'','','','');
INSERT INTO httpstep (httpstepid,httptestid,name,no,url,timeout,posts,required,status_codes,variables) VALUES (96,96,'testFormWeb3',1,'testFormWeb3',15,'','','','');
INSERT INTO httpstep (httpstepid,httptestid,name,no,url,timeout,posts,required,status_codes,variables) VALUES (97,97,'testFormWeb4',1,'testFormWeb4',15,'','','','');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol) VALUES (23400,9,'','',40001,'Download speed for scenario "$1".','web.test.in[testFormWeb1,,bps]',60,30,90,0,0,'','Bps',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol) VALUES (23401,9,'','',40001,'Failed step of scenario "$1".','web.test.fail[testFormWeb1]',60,30,90,0,3,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol) VALUES (23402,9,'','',40001,'Last error message of scenario "$1".','web.test.error[testFormWeb1]',60,30,90,0,1,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol) VALUES (23403,9,'','',40001,'Download speed for step "$2" of scenario "$1".','web.test.in[testFormWeb1,testFormWeb1,bps]',60,30,90,0,0,'','Bps',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol) VALUES (23404,9,'','',40001,'Response time for step "$2" of scenario "$1".','web.test.time[testFormWeb1,testFormWeb1,resp]',60,30,90,0,0,'','s',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol) VALUES (23405,9,'','',40001,'Response code for step "$2" of scenario "$1".','web.test.rspcode[testFormWeb1,testFormWeb1]',60,30,90,0,3,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol) VALUES (23406,9,'','',40001,'Download speed for scenario "$1".','web.test.in[testFormWeb2,,bps]',60,30,90,0,0,'','Bps',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol) VALUES (23407,9,'','',40001,'Failed step of scenario "$1".','web.test.fail[testFormWeb2]',60,30,90,0,3,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol) VALUES (23408,9,'','',40001,'Last error message of scenario "$1".','web.test.error[testFormWeb2]',60,30,90,0,1,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol) VALUES (23409,9,'','',40001,'Download speed for step "$2" of scenario "$1".','web.test.in[testFormWeb2,testFormWeb2,bps]',60,30,90,0,0,'','Bps',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol) VALUES (23410,9,'','',40001,'Response time for step "$2" of scenario "$1".','web.test.time[testFormWeb2,testFormWeb2,resp]',60,30,90,0,0,'','s',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol) VALUES (23411,9,'','',40001,'Response code for step "$2" of scenario "$1".','web.test.rspcode[testFormWeb2,testFormWeb2]',60,30,90,0,3,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol) VALUES (23412,9,'','',40001,'Download speed for scenario "$1".','web.test.in[testFormWeb3,,bps]',60,30,90,0,0,'','Bps',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol) VALUES (23413,9,'','',40001,'Failed step of scenario "$1".','web.test.fail[testFormWeb3]',60,30,90,0,3,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol) VALUES (23414,9,'','',40001,'Last error message of scenario "$1".','web.test.error[testFormWeb3]',60,30,90,0,1,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol) VALUES (23415,9,'','',40001,'Download speed for step "$2" of scenario "$1".','web.test.in[testFormWeb3,testFormWeb3,bps]',60,30,90,0,0,'','Bps',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol) VALUES (23416,9,'','',40001,'Response time for step "$2" of scenario "$1".','web.test.time[testFormWeb3,testFormWeb3,resp]',60,30,90,0,0,'','s',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol) VALUES (23417,9,'','',40001,'Response code for step "$2" of scenario "$1".','web.test.rspcode[testFormWeb3,testFormWeb3]',60,30,90,0,3,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol) VALUES (23418,9,'','',40001,'Download speed for scenario "$1".','web.test.in[testFormWeb4,,bps]',60,30,90,0,0,'','Bps',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol) VALUES (23419,9,'','',40001,'Failed step of scenario "$1".','web.test.fail[testFormWeb4]',60,30,90,0,3,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol) VALUES (23420,9,'','',40001,'Last error message of scenario "$1".','web.test.error[testFormWeb4]',60,30,90,0,1,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol) VALUES (23421,9,'','',40001,'Download speed for step "$2" of scenario "$1".','web.test.in[testFormWeb4,testFormWeb4,bps]',60,30,90,0,0,'','Bps',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol) VALUES (23422,9,'','',40001,'Response time for step "$2" of scenario "$1".','web.test.time[testFormWeb4,testFormWeb4,resp]',60,30,90,0,0,'','s',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol) VALUES (23423,9,'','',40001,'Response code for step "$2" of scenario "$1".','web.test.rspcode[testFormWeb4,testFormWeb4]',60,30,90,0,3,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (910,94,23400,2);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (911,94,23401,3);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (912,94,23402,4);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (913,95,23406,2);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (914,95,23407,3);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (915,95,23408,4);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (916,96,23412,2);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (917,96,23413,3);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (918,96,23414,4);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (919,97,23418,2);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (920,97,23419,3);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (921,97,23420,4);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (910,94,23403,2);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (911,94,23404,1);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (912,94,23405,0);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (913,95,23409,2);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (914,95,23410,1);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (915,95,23411,0);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (916,96,23415,2);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (917,96,23416,1);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (918,96,23417,0);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (919,97,23421,2);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (920,97,23422,1);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (921,97,23423,0);

-- testInheritanceWeb.SimpleUpdate
INSERT INTO httptest (httptestid,name,applicationid,nextcheck,delay,status,variables,agent,authentication,http_user,http_password,hostid,templateid,http_proxy,retries) VALUES (1,'testInheritanceWeb1',NULL,0,60,0,'','Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)',0,'','',30000,NULL,'',1);
INSERT INTO httptest (httptestid,name,applicationid,nextcheck,delay,status,variables,agent,authentication,http_user,http_password,hostid,templateid,http_proxy,retries) VALUES (2,'testInheritanceWeb1',NULL,0,60,0,'','Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)',0,'','',30001,1,'',1);
INSERT INTO httptest (httptestid,name,applicationid,nextcheck,delay,status,variables,agent,authentication,http_user,http_password,hostid,templateid,http_proxy,retries) VALUES (3,'testInheritanceWeb2',NULL,0,60,0,'','Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)',0,'','',30000,NULL,'',1);
INSERT INTO httptest (httptestid,name,applicationid,nextcheck,delay,status,variables,agent,authentication,http_user,http_password,hostid,templateid,http_proxy,retries) VALUES (4,'testInheritanceWeb2',NULL,0,60,0,'','Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)',0,'','',30001,3,'',1);
INSERT INTO httptest (httptestid,name,applicationid,nextcheck,delay,status,variables,agent,authentication,http_user,http_password,hostid,templateid,http_proxy,retries) VALUES (5,'testInheritanceWeb3',NULL,0,60,0,'','Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)',0,'','',30000,NULL,'',1);
INSERT INTO httptest (httptestid,name,applicationid,nextcheck,delay,status,variables,agent,authentication,http_user,http_password,hostid,templateid,http_proxy,retries) VALUES (6,'testInheritanceWeb3',NULL,0,60,0,'','Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)',0,'','',30001,5,'',1);
INSERT INTO httptest (httptestid,name,applicationid,nextcheck,delay,status,variables,agent,authentication,http_user,http_password,hostid,templateid,http_proxy,retries) VALUES (7,'testInheritanceWeb4',NULL,0,60,0,'','Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)',0,'','',30000,NULL,'',1);
INSERT INTO httptest (httptestid,name,applicationid,nextcheck,delay,status,variables,agent,authentication,http_user,http_password,hostid,templateid,http_proxy,retries) VALUES (8,'testInheritanceWeb4',NULL,0,60,0,'x','Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)',0,'','',30001,7,'',1);
INSERT INTO httpstep (httpstepid,httptestid,name,no,url,timeout,posts,required,status_codes,variables) VALUES (1,1,'testInheritanceWeb1',1,'testInheritanceWeb1',15,'','','','');
INSERT INTO httpstep (httpstepid,httptestid,name,no,url,timeout,posts,required,status_codes,variables) VALUES (2,2,'testInheritanceWeb1',1,'testInheritanceWeb1',15,'','','','');
INSERT INTO httpstep (httpstepid,httptestid,name,no,url,timeout,posts,required,status_codes,variables) VALUES (3,3,'testInheritanceWeb2',1,'testInheritanceWeb2',15,'','','','');
INSERT INTO httpstep (httpstepid,httptestid,name,no,url,timeout,posts,required,status_codes,variables) VALUES (4,4,'testInheritanceWeb2',1,'testInheritanceWeb2',15,'','','','');
INSERT INTO httpstep (httpstepid,httptestid,name,no,url,timeout,posts,required,status_codes,variables) VALUES (5,5,'testInheritanceWeb3',1,'testInheritanceWeb3',15,'','','','');
INSERT INTO httpstep (httpstepid,httptestid,name,no,url,timeout,posts,required,status_codes,variables) VALUES (6,6,'testInheritanceWeb3',1,'testInheritanceWeb3',15,'','','','');
INSERT INTO httpstep (httpstepid,httptestid,name,no,url,timeout,posts,required,status_codes,variables) VALUES (7,7,'testInheritanceWeb4',1,'testInheritanceWeb4',15,'','','','');
INSERT INTO httpstep (httpstepid,httptestid,name,no,url,timeout,posts,required,status_codes,variables) VALUES (8,8,'testInheritanceWeb4',1,'testInheritanceWeb4',15,'','','','');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30005,9,'','',30000,'Download speed for scenario "$1".','web.test.in[testInheritanceWeb1,,bps]',60,30,90,0,0,'','Bps',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30006,9,'','',30000,'Failed step of scenario "$1".','web.test.fail[testInheritanceWeb1]',60,30,90,0,3,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30007,9,'','',30000,'Last error message of scenario "$1".','web.test.error[testInheritanceWeb1]',60,30,90,0,1,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30008,9,'','',30000,'Download speed for step "$2" of scenario "$1".','web.test.in[testInheritanceWeb1,testInheritanceWeb1,bps]',60,30,90,0,0,'','Bps',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30009,9,'','',30000,'Response time for step "$2" of scenario "$1".','web.test.time[testInheritanceWeb1,testInheritanceWeb1,resp]',60,30,90,0,0,'','s',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30010,9,'','',30000,'Response code for step "$2" of scenario "$1".','web.test.rspcode[testInheritanceWeb1,testInheritanceWeb1]',60,30,90,0,3,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30011,9,'','',30001,'Download speed for scenario "$1".','web.test.in[testInheritanceWeb1,,bps]',60,30,90,0,0,'','Bps',0,0,'',0,'','','1','',0,'',30005,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30012,9,'','',30001,'Failed step of scenario "$1".','web.test.fail[testInheritanceWeb1]',60,30,90,0,3,'','',0,0,'',0,'','','1','',0,'',30006,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30013,9,'','',30001,'Last error message of scenario "$1".','web.test.error[testInheritanceWeb1]',60,30,90,0,1,'','',0,0,'',0,'','','1','',0,'',30007,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30014,9,'','',30001,'Download speed for step "$2" of scenario "$1".','web.test.in[testInheritanceWeb1,testInheritanceWeb1,bps]',60,30,90,0,0,'','Bps',0,0,'',0,'','','1','',0,'',30008,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30015,9,'','',30001,'Response time for step "$2" of scenario "$1".','web.test.time[testInheritanceWeb1,testInheritanceWeb1,resp]',60,30,90,0,0,'','s',0,0,'',0,'','','1','',0,'',30009,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30016,9,'','',30001,'Response code for step "$2" of scenario "$1".','web.test.rspcode[testInheritanceWeb1,testInheritanceWeb1]',60,30,90,0,3,'','',0,0,'',0,'','','1','',0,'',30010,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30017,9,'','',30000,'Download speed for scenario "$1".','web.test.in[testInheritanceWeb2,,bps]',60,30,90,0,0,'','Bps',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30018,9,'','',30000,'Failed step of scenario "$1".','web.test.fail[testInheritanceWeb2]',60,30,90,0,3,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30019,9,'','',30000,'Last error message of scenario "$1".','web.test.error[testInheritanceWeb2]',60,30,90,0,1,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30020,9,'','',30000,'Download speed for step "$2" of scenario "$1".','web.test.in[testInheritanceWeb2,testInheritanceWeb2,bps]',60,30,90,0,0,'','Bps',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30021,9,'','',30000,'Response time for step "$2" of scenario "$1".','web.test.time[testInheritanceWeb2,testInheritanceWeb2,resp]',60,30,90,0,0,'','s',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30022,9,'','',30000,'Response code for step "$2" of scenario "$1".','web.test.rspcode[testInheritanceWeb2,testInheritanceWeb2]',60,30,90,0,3,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30023,9,'','',30001,'Download speed for scenario "$1".','web.test.in[testInheritanceWeb2,,bps]',60,30,90,0,0,'','Bps',0,0,'',0,'','','1','',0,'',30017,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30024,9,'','',30001,'Failed step of scenario "$1".','web.test.fail[testInheritanceWeb2]',60,30,90,0,3,'','',0,0,'',0,'','','1','',0,'',30018,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30025,9,'','',30001,'Last error message of scenario "$1".','web.test.error[testInheritanceWeb2]',60,30,90,0,1,'','',0,0,'',0,'','','1','',0,'',30019,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30026,9,'','',30001,'Download speed for step "$2" of scenario "$1".','web.test.in[testInheritanceWeb2,testInheritanceWeb2,bps]',60,30,90,0,0,'','Bps',0,0,'',0,'','','1','',0,'',30020,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30027,9,'','',30001,'Response time for step "$2" of scenario "$1".','web.test.time[testInheritanceWeb2,testInheritanceWeb2,resp]',60,30,90,0,0,'','s',0,0,'',0,'','','1','',0,'',30021,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30028,9,'','',30001,'Response code for step "$2" of scenario "$1".','web.test.rspcode[testInheritanceWeb2,testInheritanceWeb2]',60,30,90,0,3,'','',0,0,'',0,'','','1','',0,'',30022,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30029,9,'','',30000,'Download speed for scenario "$1".','web.test.in[testInheritanceWeb3,,bps]',60,30,90,0,0,'','Bps',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30030,9,'','',30000,'Failed step of scenario "$1".','web.test.fail[testInheritanceWeb3]',60,30,90,0,3,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30031,9,'','',30000,'Last error message of scenario "$1".','web.test.error[testInheritanceWeb3]',60,30,90,0,1,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30032,9,'','',30000,'Download speed for step "$2" of scenario "$1".','web.test.in[testInheritanceWeb3,testInheritanceWeb3,bps]',60,30,90,0,0,'','Bps',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30033,9,'','',30000,'Response time for step "$2" of scenario "$1".','web.test.time[testInheritanceWeb3,testInheritanceWeb3,resp]',60,30,90,0,0,'','s',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30034,9,'','',30000,'Response code for step "$2" of scenario "$1".','web.test.rspcode[testInheritanceWeb3,testInheritanceWeb3]',60,30,90,0,3,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30035,9,'','',30001,'Download speed for scenario "$1".','web.test.in[testInheritanceWeb3,,bps]',60,30,90,0,0,'','Bps',0,0,'',0,'','','1','',0,'',30029,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30036,9,'','',30001,'Failed step of scenario "$1".','web.test.fail[testInheritanceWeb3]',60,30,90,0,3,'','',0,0,'',0,'','','1','',0,'',30030,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30037,9,'','',30001,'Last error message of scenario "$1".','web.test.error[testInheritanceWeb3]',60,30,90,0,1,'','',0,0,'',0,'','','1','',0,'',30031,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30038,9,'','',30001,'Download speed for step "$2" of scenario "$1".','web.test.in[testInheritanceWeb3,testInheritanceWeb3,bps]',60,30,90,0,0,'','Bps',0,0,'',0,'','','1','',0,'',30032,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30039,9,'','',30001,'Response time for step "$2" of scenario "$1".','web.test.time[testInheritanceWeb3,testInheritanceWeb3,resp]',60,30,90,0,0,'','s',0,0,'',0,'','','1','',0,'',30033,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30040,9,'','',30001,'Response code for step "$2" of scenario "$1".','web.test.rspcode[testInheritanceWeb3,testInheritanceWeb3]',60,30,90,0,3,'','',0,0,'',0,'','','1','',0,'',30034,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30041,9,'','',30000,'Download speed for scenario "$1".','web.test.in[testInheritanceWeb4,,bps]',60,30,90,0,0,'','Bps',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30042,9,'','',30000,'Failed step of scenario "$1".','web.test.fail[testInheritanceWeb4]',60,30,90,0,3,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30043,9,'','',30000,'Last error message of scenario "$1".','web.test.error[testInheritanceWeb4]',60,30,90,0,1,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30044,9,'','',30000,'Download speed for step "$2" of scenario "$1".','web.test.in[testInheritanceWeb4,testInheritanceWeb4,bps]',60,30,90,0,0,'','Bps',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30045,9,'','',30000,'Response time for step "$2" of scenario "$1".','web.test.time[testInheritanceWeb4,testInheritanceWeb4,resp]',60,30,90,0,0,'','s',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30046,9,'','',30000,'Response code for step "$2" of scenario "$1".','web.test.rspcode[testInheritanceWeb4,testInheritanceWeb4]',60,30,90,0,3,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30047,9,'','',30001,'Download speed for scenario "$1".','web.test.in[testInheritanceWeb4,,bps]',60,30,90,0,0,'','Bps',0,0,'',0,'','','1','',0,'',30041,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30048,9,'','',30001,'Failed step of scenario "$1".','web.test.fail[testInheritanceWeb4]',60,30,90,0,3,'','',0,0,'',0,'','','1','',0,'',30042,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30049,9,'','',30001,'Last error message of scenario "$1".','web.test.error[testInheritanceWeb4]',60,30,90,0,1,'','',0,0,'',0,'','','1','',0,'',30043,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30050,9,'','',30001,'Download speed for step "$2" of scenario "$1".','web.test.in[testInheritanceWeb4,testInheritanceWeb4,bps]',60,30,90,0,0,'','Bps',0,0,'',0,'','','1','',0,'',30044,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30051,9,'','',30001,'Response time for step "$2" of scenario "$1".','web.test.time[testInheritanceWeb4,testInheritanceWeb4,resp]',60,30,90,0,0,'','s',0,0,'',0,'','','1','',0,'',30045,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state) VALUES (30052,9,'','',30001,'Response code for step "$2" of scenario "$1".','web.test.rspcode[testInheritanceWeb4,testInheritanceWeb4]',60,30,90,0,3,'','',0,0,'',0,'','','1','',0,'',30046,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (1,1,30005,2);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (2,1,30006,3);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (3,1,30007,4);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (4,2,30011,2);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (5,2,30012,3);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (6,2,30013,4);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (7,3,30017,2);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (8,3,30018,3);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (9,3,30019,4);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (10,4,30023,2);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (11,4,30024,3);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (12,4,30025,4);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (13,5,30029,2);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (14,5,30030,3);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (15,5,30031,4);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (16,6,30035,2);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (17,6,30036,3);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (18,6,30037,4);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (19,7,30041,2);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (20,7,30042,3);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (21,7,30043,4);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (22,8,30047,2);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (23,8,30048,3);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (24,8,30049,4);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (1,1,30008,2);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (2,1,30009,1);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (3,1,30010,0);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (4,2,30014,2);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (5,2,30015,1);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (6,2,30016,0);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (7,3,30020,2);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (8,3,30021,1);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (9,3,30022,0);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (10,4,30026,2);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (11,4,30027,1);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (12,4,30028,0);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (13,5,30032,2);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (14,5,30033,1);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (15,5,30034,0);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (16,6,30038,2);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (17,6,30039,1);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (18,6,30040,0);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (19,7,30044,2);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (20,7,30045,1);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (21,7,30046,0);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (22,8,30050,2);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (23,8,30051,1);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (24,8,30052,0);

-- testZBX6663.MassSelect
INSERT INTO hosts (hostid, proxy_hostid, host, status, disable_until, error, available, errors_from, lastaccess, ipmi_authtype, ipmi_privilege, ipmi_username, ipmi_password, ipmi_disable_until, ipmi_available, snmp_disable_until, snmp_available, maintenanceid, maintenance_status, maintenance_type, maintenance_from, ipmi_errors_from, snmp_errors_from, ipmi_error, snmp_error,name) VALUES (50000,NULL,'Template ZBX6663 First',3,0,'',0,0,0,0,2,'','',0,0,0,0,NULL,0,0,0,0,0,'','','Template ZBX6663 First');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50000, 50000, 1);
INSERT INTO hosts (hostid, proxy_hostid, host, status, disable_until, error, available, errors_from, lastaccess, ipmi_authtype, ipmi_privilege, ipmi_username, ipmi_password, ipmi_disable_until, ipmi_available, snmp_disable_until, snmp_available, maintenanceid, maintenance_status, maintenance_type, maintenance_from, ipmi_errors_from, snmp_errors_from, ipmi_error, snmp_error,name) VALUES (50002,NULL,'Template ZBX6663 Second',3,0,'',0,0,0,0,2,'','',0,0,0,0,NULL,0,0,0,0,0,'','','Template ZBX6663 Second');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50001, 50002, 1);
INSERT INTO hosts (hostid, host, name, status) VALUES (50001, 'Host ZBX6663','Host ZBX6663', 0);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50002, 50001, 4);
INSERT INTO hosts_templates (hosttemplateid, hostid, templateid) VALUES (50000, 50001, 50002);
INSERT INTO hosts_templates (hosttemplateid, hostid, templateid) VALUES (50002, 50000, 50002);
INSERT INTO interface (type, ip, dns, useip, port, main, hostid, interfaceid) VALUES (1, '127.0.7.1', '', '1', '10071', '1', 50001, 50015);
INSERT INTO applications (applicationid,hostid,name) VALUES (359,50000,'App ZBX6663 First');
INSERT INTO applications (applicationid,hostid,name) VALUES (358,50000,'App ZBX6663 Second');
INSERT INTO applications (applicationid,hostid,name) VALUES (360,50001,'App ZBX6663');
INSERT INTO applications (applicationid,hostid,name) VALUES (357,50001,'App ZBX6663 Second');
INSERT INTO applications (applicationid,hostid,name) VALUES (361,50002,'App ZBX6663 Second');
INSERT INTO application_template (application_templateid,applicationid,templateid) VALUES (30,357,361);
INSERT INTO application_template (application_templateid,applicationid,templateid) VALUES (31,358,361);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40008,9,'','',50000,'Download speed for scenario "$1".','web.test.in[Web ZBX6663 First,,bps]',60,30,90,0,0,'','Bps',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40009,9,'','',50000,'Failed step of scenario "$1".','web.test.fail[Web ZBX6663 First]',60,30,90,0,3,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40010,9,'','',50000,'Last error message of scenario "$1".','web.test.error[Web ZBX6663 First]',60,30,90,0,1,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40011,9,'','',50000,'Download speed for step "$2" of scenario "$1".','web.test.in[Web ZBX6663 First,Web ZBX6663 First Step,bps]',60,30,90,0,0,'','Bps',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40012,9,'','',50000,'Response time for step "$2" of scenario "$1".','web.test.time[Web ZBX6663 First,Web ZBX6663 First Step,resp]',60,30,90,0,0,'','s',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40013,9,'','',50000,'Response code for step "$2" of scenario "$1".','web.test.rspcode[Web ZBX6663 First,Web ZBX6663 First Step]',60,30,90,0,3,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40014,9,'','',50002,'Download speed for scenario "$1".','web.test.in[Web ZBX6663 Second,,bps]',60,30,90,0,0,'','Bps',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40015,9,'','',50002,'Failed step of scenario "$1".','web.test.fail[Web ZBX6663 Second]',60,30,90,0,3,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40016,9,'','',50002,'Last error message of scenario "$1".','web.test.error[Web ZBX6663 Second]',60,30,90,0,1,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40017,9,'','',50002,'Download speed for step "$2" of scenario "$1".','web.test.in[Web ZBX6663 Second,Web ZBX6663 Second Step,bps]',60,30,90,0,0,'','Bps',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40018,9,'','',50002,'Response time for step "$2" of scenario "$1".','web.test.time[Web ZBX6663 Second,Web ZBX6663 Second Step,resp]',60,30,90,0,0,'','s',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40019,9,'','',50002,'Response code for step "$2" of scenario "$1".','web.test.rspcode[Web ZBX6663 Second,Web ZBX6663 Second Step]',60,30,90,0,3,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40020,9,'','',50001,'Download speed for scenario "$1".','web.test.in[Web ZBX6663 Second,,bps]',60,30,90,0,0,'','Bps',0,0,'',0,'','','1','',0,'',40014,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40021,9,'','',50001,'Failed step of scenario "$1".','web.test.fail[Web ZBX6663 Second]',60,30,90,0,3,'','',0,0,'',0,'','','1','',0,'',40015,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40022,9,'','',50001,'Last error message of scenario "$1".','web.test.error[Web ZBX6663 Second]',60,30,90,0,1,'','',0,0,'',0,'','','1','',0,'',40016,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40023,9,'','',50001,'Download speed for step "$2" of scenario "$1".','web.test.in[Web ZBX6663 Second,Web ZBX6663 Second Step,bps]',60,30,90,0,0,'','Bps',0,0,'',0,'','','1','',0,'',40017,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40024,9,'','',50001,'Response time for step "$2" of scenario "$1".','web.test.time[Web ZBX6663 Second,Web ZBX6663 Second Step,resp]',60,30,90,0,0,'','s',0,0,'',0,'','','1','',0,'',40018,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40025,9,'','',50001,'Response code for step "$2" of scenario "$1".','web.test.rspcode[Web ZBX6663 Second,Web ZBX6663 Second Step]',60,30,90,0,3,'','',0,0,'',0,'','','1','',0,'',40019,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40026,9,'','',50000,'Download speed for scenario "$1".','web.test.in[Web ZBX6663 Second,,bps]',60,30,90,0,0,'','Bps',0,0,'',0,'','','1','',0,'',40014,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40027,9,'','',50000,'Failed step of scenario "$1".','web.test.fail[Web ZBX6663 Second]',60,30,90,0,3,'','',0,0,'',0,'','','1','',0,'',40015,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40028,9,'','',50000,'Last error message of scenario "$1".','web.test.error[Web ZBX6663 Second]',60,30,90,0,1,'','',0,0,'',0,'','','1','',0,'',40016,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40029,9,'','',50000,'Download speed for step "$2" of scenario "$1".','web.test.in[Web ZBX6663 Second,Web ZBX6663 Second Step,bps]',60,30,90,0,0,'','Bps',0,0,'',0,'','','1','',0,'',40017,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40030,9,'','',50000,'Response time for step "$2" of scenario "$1".','web.test.time[Web ZBX6663 Second,Web ZBX6663 Second Step,resp]',60,30,90,0,0,'','s',0,0,'',0,'','','1','',0,'',40018,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40031,9,'','',50000,'Response code for step "$2" of scenario "$1".','web.test.rspcode[Web ZBX6663 Second,Web ZBX6663 Second Step]',60,30,90,0,3,'','',0,0,'',0,'','','1','',0,'',40019,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40032,9,'','',50001,'Download speed for scenario "$1".','web.test.in[Web ZBX6663,,bps]',60,30,90,0,0,'','Bps',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40033,9,'','',50001,'Failed step of scenario "$1".','web.test.fail[Web ZBX6663]',60,30,90,0,3,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40034,9,'','',50001,'Last error message of scenario "$1".','web.test.error[Web ZBX6663]',60,30,90,0,1,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40035,9,'','',50001,'Download speed for step "$2" of scenario "$1".','web.test.in[Web ZBX6663,Web ZBX6663 Step,bps]',60,30,90,0,0,'','Bps',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40036,9,'','',50001,'Response time for step "$2" of scenario "$1".','web.test.time[Web ZBX6663,Web ZBX6663 Step,resp]',60,30,90,0,0,'','s',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40037,9,'','',50001,'Response code for step "$2" of scenario "$1".','web.test.rspcode[Web ZBX6663,Web ZBX6663 Step]',60,30,90,0,3,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40038,0,'','',50002,'Item ZBX6663 Second','item-ZBX6663-second',30,90,365,0,3,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40039,0,'','',50001,'Item ZBX6663 Second','item-ZBX6663-second',30,90,365,0,3,'','',0,0,'',0,'','','1','',0,'',40038,NULL,'','','',0,0,'','','','',0,0,'',50015,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40040,0,'','',50000,'Item ZBX6663 Second','item-ZBX6663-second',30,90,365,0,3,'','',0,0,'',0,'','','1','',0,'',40038,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40041,0,'','',50000,'Item ZBX6663 First','item-ZBX6663-first',30,90,365,0,3,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',NULL,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40042,0,'','',50001,'Item ZBX6663','item-ZBX6663',30,90,365,0,3,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',50015,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40043,0,'','',50001,'DiscoveryRule ZBX6663','drule-zbx6663',30,90,365,0,4,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,1,':',50015,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40045,0,'','',50002,'DiscoveryRule ZBX6663 Second','drule-ZBX6663-second',30,90,365,0,4,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,1,':',NULL,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40046,0,'','',50001,'DiscoveryRule ZBX6663 Second','drule-ZBX6663-second',30,90,365,0,4,'','',0,0,'',0,'','','1','',0,'',40045,NULL,'','','',0,0,'','','','',0,1,':',50015,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40047,0,'','',50000,'DiscoveryRule ZBX6663 Second','drule-ZBX6663-second',30,90,365,0,4,'','',0,0,'',0,'','','1','',0,'',40045,NULL,'','','',0,0,'','','','',0,1,':',NULL,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40048,0,'','',50002,'ItemProto ZBX6663 Second','item-proto-zbx6663-second',30,90,365,0,3,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,2,'',NULL,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40049,0,'','',50001,'ItemProto ZBX6663 Second','item-proto-zbx6663-second',30,90,365,0,3,'','',0,0,'',0,'','','1','',0,'',40048,NULL,'','','',0,0,'','','','',0,2,'',50015,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40050,0,'','',50000,'ItemProto ZBX6663 Second','item-proto-zbx6663-second',30,90,365,0,3,'','',0,0,'',0,'','','1','',0,'',40048,NULL,'','','',0,0,'','','','',0,2,'',NULL,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40051,0,'','',50000,'DiscoveryRule ZBX6663 First','drule-zbx6663-first',30,90,365,0,4,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,1,':',NULL,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40052,0,'','',50001,'ItemProto ZBX6663 HSecond','item-proto-zbx6663-hsecond',30,90,365,0,3,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,2,'',50015,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40054,0,'','',50000,'ItemProto ZBX6663 TSecond','item-proto-zbx6663-tsecond',30,90,365,0,3,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,2,'',NULL,'','',0,'30',0,0,0,'');
INSERT INTO item_discovery (itemdiscoveryid,itemid,parent_itemid,key_,lastcheck,ts_delete) VALUES (507,40048,40045,'',0,0);
INSERT INTO item_discovery (itemdiscoveryid,itemid,parent_itemid,key_,lastcheck,ts_delete) VALUES (508,40049,40046,'',0,0);
INSERT INTO item_discovery (itemdiscoveryid,itemid,parent_itemid,key_,lastcheck,ts_delete) VALUES (509,40050,40047,'',0,0);
INSERT INTO item_discovery (itemdiscoveryid,itemid,parent_itemid,key_,lastcheck,ts_delete) VALUES (510,40052,40046,'',0,0);
INSERT INTO item_discovery (itemdiscoveryid,itemid,parent_itemid,key_,lastcheck,ts_delete) VALUES (512,40054,40047,'',0,0);
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (16008,'{16008}=0','Trigger ZBX6663 Second','',0,0,0,0,'','',NULL,0,0,0);
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (16009,'{16009}=0','Trigger ZBX6663 Second','',0,0,0,0,'','',16008,0,0,0);
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (16010,'{16010}=0','Trigger ZBX6663 Second','',0,0,0,0,'','',16008,0,0,0);
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (16011,'{16011}=0','Trigger ZBX6663 First','',0,0,0,0,'','',NULL,0,0,0);
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (16012,'{16012}=0','Trigger ZBX6663','',0,0,0,0,'','',NULL,0,0,0);
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (16013,'{16013}=0','TriggerProto ZBX6663 TSecond','',0,0,0,0,'','',NULL,0,0,2);
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (16014,'{16014}=0','TriggerProto ZBX6663 Second','',0,0,0,0,'','',NULL,0,0,2);
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (16015,'{16015}=0','TriggerProto ZBX6663 Second','',0,0,0,0,'','',16014,0,0,2);
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (16016,'{16016}=0','TriggerProto ZBX6663 Second','',0,0,0,0,'','',16014,0,0,2);
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (16017,'{16017}=0','TriggerProto ZBX6663 HSecond','',0,0,0,0,'','',NULL,0,0,2);
INSERT INTO functions (functionid,itemid,triggerid,function,parameter) VALUES (16008,40038,16008,'last','0');
INSERT INTO functions (functionid,itemid,triggerid,function,parameter) VALUES (16009,40039,16009,'last','0');
INSERT INTO functions (functionid,itemid,triggerid,function,parameter) VALUES (16010,40040,16010,'last','0');
INSERT INTO functions (functionid,itemid,triggerid,function,parameter) VALUES (16011,40041,16011,'last','0');
INSERT INTO functions (functionid,itemid,triggerid,function,parameter) VALUES (16012,40042,16012,'last','0');
INSERT INTO functions (functionid,itemid,triggerid,function,parameter) VALUES (16013,40054,16013,'last','0');
INSERT INTO functions (functionid,itemid,triggerid,function,parameter) VALUES (16014,40048,16014,'last','0');
INSERT INTO functions (functionid,itemid,triggerid,function,parameter) VALUES (16015,40049,16015,'last','0');
INSERT INTO functions (functionid,itemid,triggerid,function,parameter) VALUES (16016,40050,16016,'last','0');
INSERT INTO functions (functionid,itemid,triggerid,function,parameter) VALUES (16017,40052,16017,'last','0');
INSERT INTO graphs (graphid,name,width,height,yaxismin,yaxismax,templateid,show_work_period,show_triggers,graphtype,show_legend,show_3d,percent_left,percent_right,ymin_type,ymax_type,ymin_itemid,ymax_itemid,flags) VALUES (700008,'Graph ZBX6663',900,200,0.0000,100.0000,NULL,1,1,0,1,0,0.0000,0.0000,0,0,NULL,NULL,0);
INSERT INTO graphs (graphid,name,width,height,yaxismin,yaxismax,templateid,show_work_period,show_triggers,graphtype,show_legend,show_3d,percent_left,percent_right,ymin_type,ymax_type,ymin_itemid,ymax_itemid,flags) VALUES (700009,'Graph ZBX6663 Second',900,200,0.0000,100.0000,NULL,1,1,0,1,0,0.0000,0.0000,0,0,NULL,NULL,0);
INSERT INTO graphs (graphid,name,width,height,yaxismin,yaxismax,templateid,show_work_period,show_triggers,graphtype,show_legend,show_3d,percent_left,percent_right,ymin_type,ymax_type,ymin_itemid,ymax_itemid,flags) VALUES (700010,'Graph ZBX6663 Second',900,200,0.0000,100.0000,700009,1,1,0,1,0,0.0000,0.0000,0,0,NULL,NULL,0);
INSERT INTO graphs (graphid,name,width,height,yaxismin,yaxismax,templateid,show_work_period,show_triggers,graphtype,show_legend,show_3d,percent_left,percent_right,ymin_type,ymax_type,ymin_itemid,ymax_itemid,flags) VALUES (700011,'Graph ZBX6663 Second',900,200,0.0000,100.0000,700009,1,1,0,1,0,0.0000,0.0000,0,0,NULL,NULL,0);
INSERT INTO graphs (graphid,name,width,height,yaxismin,yaxismax,templateid,show_work_period,show_triggers,graphtype,show_legend,show_3d,percent_left,percent_right,ymin_type,ymax_type,ymin_itemid,ymax_itemid,flags) VALUES (700012,'Graph ZBX6663 First',900,200,0.0000,100.0000,NULL,1,1,0,1,0,0.0000,0.0000,0,0,NULL,NULL,0);
INSERT INTO graphs (graphid,name,width,height,yaxismin,yaxismax,templateid,show_work_period,show_triggers,graphtype,show_legend,show_3d,percent_left,percent_right,ymin_type,ymax_type,ymin_itemid,ymax_itemid,flags) VALUES (700013,'GraphPrototype ZBX6663 Second',900,200,0.0000,100.0000,NULL,1,1,0,1,0,0.0000,0.0000,0,0,NULL,NULL,2);
INSERT INTO graphs (graphid,name,width,height,yaxismin,yaxismax,templateid,show_work_period,show_triggers,graphtype,show_legend,show_3d,percent_left,percent_right,ymin_type,ymax_type,ymin_itemid,ymax_itemid,flags) VALUES (700014,'GraphPrototype ZBX6663 Second',900,200,0.0000,100.0000,700013,1,1,0,1,0,0.0000,0.0000,0,0,NULL,NULL,2);
INSERT INTO graphs (graphid,name,width,height,yaxismin,yaxismax,templateid,show_work_period,show_triggers,graphtype,show_legend,show_3d,percent_left,percent_right,ymin_type,ymax_type,ymin_itemid,ymax_itemid,flags) VALUES (700015,'GraphPrototype ZBX6663 Second',900,200,0.0000,100.0000,700013,1,1,0,1,0,0.0000,0.0000,0,0,NULL,NULL,2);
INSERT INTO graphs (graphid,name,width,height,yaxismin,yaxismax,templateid,show_work_period,show_triggers,graphtype,show_legend,show_3d,percent_left,percent_right,ymin_type,ymax_type,ymin_itemid,ymax_itemid,flags) VALUES (700016,'GraphProto ZBX6663 TSecond',900,200,0.0000,100.0000,NULL,1,1,0,1,0,0.0000,0.0000,0,0,NULL,NULL,2);
INSERT INTO graphs (graphid,name,width,height,yaxismin,yaxismax,templateid,show_work_period,show_triggers,graphtype,show_legend,show_3d,percent_left,percent_right,ymin_type,ymax_type,ymin_itemid,ymax_itemid,flags) VALUES (700017,'GraphProto ZBX6663 HSecond',900,200,0.0000,100.0000,NULL,1,1,0,1,0,0.0000,0.0000,0,0,NULL,NULL,2);
INSERT INTO graphs_items (gitemid,graphid,itemid,drawtype,sortorder,color,yaxisside,calc_fnc,type) VALUES (700016,700008,40042,0,0,'C80000',0,2,0);
INSERT INTO graphs_items (gitemid,graphid,itemid,drawtype,sortorder,color,yaxisside,calc_fnc,type) VALUES (700017,700009,40038,0,0,'C80000',0,2,0);
INSERT INTO graphs_items (gitemid,graphid,itemid,drawtype,sortorder,color,yaxisside,calc_fnc,type) VALUES (700018,700010,40039,0,0,'C80000',0,2,0);
INSERT INTO graphs_items (gitemid,graphid,itemid,drawtype,sortorder,color,yaxisside,calc_fnc,type) VALUES (700019,700011,40040,0,0,'C80000',0,2,0);
INSERT INTO graphs_items (gitemid,graphid,itemid,drawtype,sortorder,color,yaxisside,calc_fnc,type) VALUES (700020,700012,40041,0,0,'C80000',0,2,0);
INSERT INTO graphs_items (gitemid,graphid,itemid,drawtype,sortorder,color,yaxisside,calc_fnc,type) VALUES (700021,700013,40048,0,0,'C80000',0,2,0);
INSERT INTO graphs_items (gitemid,graphid,itemid,drawtype,sortorder,color,yaxisside,calc_fnc,type) VALUES (700022,700014,40049,0,0,'C80000',0,2,0);
INSERT INTO graphs_items (gitemid,graphid,itemid,drawtype,sortorder,color,yaxisside,calc_fnc,type) VALUES (700023,700015,40050,0,0,'C80000',0,2,0);
INSERT INTO graphs_items (gitemid,graphid,itemid,drawtype,sortorder,color,yaxisside,calc_fnc,type) VALUES (700024,700016,40054,0,0,'C80000',0,2,0);
INSERT INTO graphs_items (gitemid,graphid,itemid,drawtype,sortorder,color,yaxisside,calc_fnc,type) VALUES (700025,700017,40052,0,0,'C80000',0,2,0);
INSERT INTO httptest (httptestid,name,applicationid,nextcheck,delay,status,variables,agent,authentication,http_user,http_password,hostid,templateid,http_proxy,retries) VALUES (98,'Web ZBX6663 First',NULL,0,60,0,'','Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)',0,'','',50000,NULL,'',1);
INSERT INTO httptest (httptestid,name,applicationid,nextcheck,delay,status,variables,agent,authentication,http_user,http_password,hostid,templateid,http_proxy,retries) VALUES (99,'Web ZBX6663 Second',NULL,0,60,0,'','Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)',0,'','',50002,NULL,'',1);
INSERT INTO httptest (httptestid,name,applicationid,nextcheck,delay,status,variables,agent,authentication,http_user,http_password,hostid,templateid,http_proxy,retries) VALUES (100,'Web ZBX6663 Second',NULL,0,60,0,'','Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)',0,'','',50001,99,'',1);
INSERT INTO httptest (httptestid,name,applicationid,nextcheck,delay,status,variables,agent,authentication,http_user,http_password,hostid,templateid,http_proxy,retries) VALUES (101,'Web ZBX6663 Second',NULL,0,60,0,'','Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)',0,'','',50000,99,'',1);
INSERT INTO httptest (httptestid,name,applicationid,nextcheck,delay,status,variables,agent,authentication,http_user,http_password,hostid,templateid,http_proxy,retries) VALUES (102,'Web ZBX6663',NULL,0,60,0,'','Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)',0,'','',50001,NULL,'',1);
INSERT INTO httpstep (httpstepid,httptestid,name,no,url,timeout,posts,required,status_codes,variables) VALUES (98,98,'Web ZBX6663 First Step',1,'Web ZBX6663 First Url',15,'','','','');
INSERT INTO httpstep (httpstepid,httptestid,name,no,url,timeout,posts,required,status_codes,variables) VALUES (99,99,'Web ZBX6663 Second Step',1,'Web ZBX6663 Second Url',15,'','','','');
INSERT INTO httpstep (httpstepid,httptestid,name,no,url,timeout,posts,required,status_codes,variables) VALUES (100,100,'Web ZBX6663 Second Step',1,'Web ZBX6663 Second Url',15,'','','','');
INSERT INTO httpstep (httpstepid,httptestid,name,no,url,timeout,posts,required,status_codes,variables) VALUES (101,101,'Web ZBX6663 Second Step',1,'Web ZBX6663 Second Url',15,'','','','');
INSERT INTO httpstep (httpstepid,httptestid,name,no,url,timeout,posts,required,status_codes,variables) VALUES (102,102,'Web ZBX6663 Step',1,'Web ZBX6663 Url',15,'','','','');
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (922,98,40008,2);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (923,98,40009,3);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (924,98,40010,4);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (925,99,40014,2);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (926,99,40015,3);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (927,99,40016,4);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (928,100,40020,2);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (929,100,40021,3);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (930,100,40022,4);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (931,101,40026,2);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (932,101,40027,3);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (933,101,40028,4);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (934,102,40032,2);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (935,102,40033,3);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (936,102,40034,4);

-- testZBX6648.eventsFilter
INSERT INTO groups (groupid,name,internal) VALUES (50000,'ZBX6648 Group No Hosts',0);
INSERT INTO groups (groupid,name,internal) VALUES (50001,'ZBX6648 Disabled Triggers',0);
INSERT INTO groups (groupid,name,internal) VALUES (50002,'ZBX6648 Enabled Triggers',0);
INSERT INTO groups (groupid,name,internal) VALUES (50003,'ZBX6648 All Triggers',0);
INSERT INTO hosts (hostid,proxy_hostid,host,status,disable_until,error,available,errors_from,lastaccess,ipmi_authtype,ipmi_privilege,ipmi_username,ipmi_password,ipmi_disable_until,ipmi_available,snmp_disable_until,snmp_available,maintenanceid,maintenance_status,maintenance_type,maintenance_from,ipmi_errors_from,snmp_errors_from,ipmi_error,snmp_error,jmx_disable_until,jmx_available,jmx_errors_from,jmx_error,name) VALUES (50003,NULL,'ZBX6648 Disabled Triggers Host',0,0,'',0,0,0,-1,2,'','',0,0,0,0,NULL,0,0,0,0,0,'','',0,0,0,'','ZBX6648 Disabled Triggers Host');
INSERT INTO hosts (hostid,proxy_hostid,host,status,disable_until,error,available,errors_from,lastaccess,ipmi_authtype,ipmi_privilege,ipmi_username,ipmi_password,ipmi_disable_until,ipmi_available,snmp_disable_until,snmp_available,maintenanceid,maintenance_status,maintenance_type,maintenance_from,ipmi_errors_from,snmp_errors_from,ipmi_error,snmp_error,jmx_disable_until,jmx_available,jmx_errors_from,jmx_error,name) VALUES (50004,NULL,'ZBX6648 Enabled Triggers Host',0,0,'',0,0,0,-1,2,'','',0,0,0,0,NULL,0,0,0,0,0,'','',0,0,0,'','ZBX6648 Enabled Triggers Host');
INSERT INTO hosts (hostid,proxy_hostid,host,status,disable_until,error,available,errors_from,lastaccess,ipmi_authtype,ipmi_privilege,ipmi_username,ipmi_password,ipmi_disable_until,ipmi_available,snmp_disable_until,snmp_available,maintenanceid,maintenance_status,maintenance_type,maintenance_from,ipmi_errors_from,snmp_errors_from,ipmi_error,snmp_error,jmx_disable_until,jmx_available,jmx_errors_from,jmx_error,name) VALUES (50005,NULL,'ZBX6648 All Triggers Host',0,0,'',0,0,0,-1,2,'','',0,0,0,0,NULL,0,0,0,0,0,'','',0,0,0,'','ZBX6648 All Triggers Host');
INSERT INTO hosts_groups (hostgroupid,hostid,groupid) VALUES (50003,50003,50001);
INSERT INTO hosts_groups (hostgroupid,hostid,groupid) VALUES (50004,50004,50002);
INSERT INTO hosts_groups (hostgroupid,hostid,groupid) VALUES (50005,50005,50003);
INSERT INTO interface (interfaceid,hostid,main,type,useip,ip,dns,port) VALUES (50016,50003,1,1,1,'127.0.7.1','','10071');
INSERT INTO interface (interfaceid,hostid,main,type,useip,ip,dns,port) VALUES (50017,50004,1,1,1,'127.0.7.1','','10071');
INSERT INTO interface (interfaceid,hostid,main,type,useip,ip,dns,port) VALUES (50018,50005,1,1,1,'127.0.7.1','','10071');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40055,0,'','',50003,'zbx6648 item disabled','zbx6648-item-disabled',30,90,365,0,3,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',50016,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40056,0,'','',50004,'zbx6648 item enabled','zbx6648-item-enabled',30,90,365,0,3,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',50017,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40057,0,'','',50005,'zbx6648 item all','zbx6648-item-all',30,90,365,0,3,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',50018,'','',0,'30',0,0,0,'');
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (16018,'{16018}=0','zbx6648 trigger disabled','',1,0,0,0,'','',NULL,0,0,0);
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (16019,'{16019}=0','zbx6648 trigger enabled','',0,0,0,0,'','',NULL,0,0,0);
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (16020,'{16020}=0','zbx6648 trigger all enabled','',0,0,0,0,'','',NULL,0,0,0);
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (16021,'{16021}=0','zbx6648 trigger all disabled','',1,0,0,0,'','',NULL,0,0,0);
INSERT INTO functions (functionid,itemid,triggerid,function,parameter) VALUES (16018,40055,16018,'last','0');
INSERT INTO functions (functionid,itemid,triggerid,function,parameter) VALUES (16019,40056,16019,'last','0');
INSERT INTO functions (functionid,itemid,triggerid,function,parameter) VALUES (16020,40057,16020,'last','0');
INSERT INTO functions (functionid,itemid,triggerid,function,parameter) VALUES (16021,40057,16021,'last','0');

-- testPageItems, testPageTriggers, testPageDiscoveryRules, testPageItemPrototype, testPageTriggerPrototype
INSERT INTO hosts (hostid, proxy_hostid, host, status, disable_until, error, available, errors_from, lastaccess, ipmi_authtype, ipmi_privilege, ipmi_username, ipmi_password, ipmi_disable_until, ipmi_available, snmp_disable_until, snmp_available, maintenanceid, maintenance_status, maintenance_type, maintenance_from, ipmi_errors_from, snmp_errors_from, ipmi_error, snmp_error,name) VALUES (50006,NULL,'Template-layout-test-001',3,0,'',0,0,0,0,2,'','',0,0,0,0,NULL,0,0,0,0,0,'','','Template-layout-test-001');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50006, 50006, 1);
INSERT INTO hosts (hostid, host, name, status) VALUES (50007, 'Host-layout-test-001','Host-layout-test-001', 0);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50007, 50007, 4);
INSERT INTO interface (type, ip, dns, useip, port, main, hostid, interfaceid) VALUES (1, '127.0.7.1', '', '1', '10071', '1', 50007, 50019);
INSERT INTO interface (type, ip, dns, useip, port, main, hostid, interfaceid) VALUES (1, '127.0.7.1', '', '1', '10071', '1', 50006, 50020);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40058,0,'','',50006,'Discovery-rule-layout-test-001','drule-layout-test001',30,90,365,0,4,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,1,':',NULL,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40059,0,'','',50007,'Discovery-rule-layout-test-002','drule-layout-test002',30,90,365,0,4,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,1,':',NULL,'','',0,'30',0,0,0,'');
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, delay, params, description) VALUES ('Item-proto-layout-test-001', 'item-proto-layout-test001', 50006, 3, 40060, 2, 5, '', '');
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid) values (513, 40060, 40058);
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, delay, params, description) VALUES ('Item-proto-layout-test-002', 'item-proto-layout-test002', 50007, 3, 40061, 2, 5, '', '');
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid) values (514, 40061, 40059);
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40062,0,'','',50006,'Item-layout-test-001','item-layout-test-001',30,90,365,0,3,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',50020,'','',0,'30',0,0,0,'');
INSERT INTO items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,error,lastlogsize,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,authtype,username,password,publickey,privatekey,mtime,flags,filter,interfaceid,port,description,inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,state,snmpv3_contextname) VALUES (40063,0,'','',50007,'Item-layout-test-002','item-layout-test-002',30,90,365,0,3,'','',0,0,'',0,'','','1','',0,'',NULL,NULL,'','','',0,0,'','','','',0,0,'',50019,'','',0,'30',0,0,0,'');
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (16022,'{16022}=0','Trigger-proto-layout-test-001','',0,0,0,0,'','',NULL,0,0,2);
INSERT INTO functions (functionid,itemid,triggerid,function,parameter) VALUES (16022,40060,16022,'last','0');
INSERT INTO triggers (triggerid, expression, description, comments, flags) VALUES (16023, '{16023}=0', 'Trigger-proto-layout-test-001', '', 2);
INSERT INTO functions (functionid, itemid, triggerid, function, parameter) VALUES (16023, 40061 ,16023,'last',0);
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (16024,'{16024}=0','Trigger-layout-test-001','',1,0,0,0,'','',NULL,0,0,0);
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (16025,'{16025}=0','Trigger-layout-test-002','',0,0,0,0,'','',NULL,0,0,0);
INSERT INTO functions (functionid,itemid,triggerid,function,parameter) VALUES (16024,40063,16024,'last','0');
INSERT INTO functions (functionid,itemid,triggerid,function,parameter) VALUES (16025,40062,16025,'last','0');
