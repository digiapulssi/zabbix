-- Test data for API tests

-- Activate "Zabbix Server" host
UPDATE hosts SET status=0 WHERE host='Zabbix server';

-- applications
INSERT INTO hosts (hostid, host, name, status, description) VALUES (50009, 'API Host', 'API Host', 0, '');
INSERT INTO hosts (hostid, host, name, status, description) VALUES (50010, 'API Template', 'API Template', 3, '');
INSERT INTO interface (interfaceid,hostid,main,type,useip,ip,dns,port) values (50022,50009,1,1,1,'127.0.0.1','','10050');
INSERT INTO groups (groupid,name,internal) VALUES (50012,'API group for hosts',0);
INSERT INTO groups (groupid,name,internal) VALUES (50013,'API group for templates',0);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50009, 50009, 50012);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50011, 50010, 50013);
INSERT INTO hosts_templates (hosttemplateid, hostid, templateid) VALUES (50003, 50009, 50010);
INSERT INTO applications (applicationid,hostid,name) VALUES (366,50009,'API application');
INSERT INTO applications (applicationid,hostid,name) VALUES (367,50009,'API host application for update');
INSERT INTO applications (applicationid,hostid,name) VALUES (368,10093,'API template application for update');
INSERT INTO applications (applicationid,hostid,name) VALUES (369,50010,'API templated application');
INSERT INTO applications (applicationid,hostid,name) VALUES (370,50009,'API templated application');
INSERT INTO applications (applicationid,hostid,name) VALUES (371,50009,'API application delete');
INSERT INTO applications (applicationid,hostid,name) VALUES (372,50009,'API application delete2');
INSERT INTO applications (applicationid,hostid,name) VALUES (373,50009,'API application delete3');
INSERT INTO applications (applicationid,hostid,name) VALUES (374,50009,'API application delete4');
INSERT INTO applications (applicationid,hostid,name) VALUES (376,10084,'API application for Zabbix server');
INSERT INTO application_template (application_templateid,applicationid,templateid) VALUES (52,370,369);
-- discovered application
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,flags,posts,headers) VALUES (40066, 50009, 50022, 0, 2,'API discovery rule','vfs.fs.discovery',30,90,0,'','',1,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,flags,posts,headers) VALUES (40067, 50009, 50022, 0, 2,'API discovery item','vfs.fs.size[{#FSNAME},free]',30,90,0,'','',2,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,flags,posts,headers) VALUES (40068, 50009, 50022, 0, 2,'API discovery item','vfs.fs.size[/,free]',30,90,0,'','',4,'','');
INSERT INTO item_discovery (itemdiscoveryid,itemid,parent_itemid,key_) VALUES (15085,40067,40066,'vfs.fs.size[{#FSNAME},free]');
INSERT INTO item_discovery (itemdiscoveryid,itemid,parent_itemid,key_) VALUES (15086,40068,40067,'vfs.fs.size[{#FSNAME},free]');
INSERT INTO applications (applicationid,hostid,name,flags) VALUES (375,50009,'API discovery application',4);
INSERT INTO application_prototype (application_prototypeid,itemid,name) VALUES (2,40066,'API discovery application');
INSERT INTO application_discovery (application_discoveryid,applicationid,application_prototypeid,name) VALUES (1,375,2,'API discovery application');
INSERT INTO items_applications (itemappid,applicationid,itemid) VALUES (6000,375,40068);
INSERT INTO item_application_prototype (item_application_prototypeid,application_prototypeid,itemid) VALUES (2,2,40067);

-- valuemap
INSERT INTO valuemaps (valuemapid,name) VALUES (59,'API value map for update');
INSERT INTO valuemaps (valuemapid,name) VALUES (60,'API value map for update with mappings');
INSERT INTO valuemaps (valuemapid,name) VALUES (61,'API value map delete');
INSERT INTO valuemaps (valuemapid,name) VALUES (62,'API value map delete2');
INSERT INTO valuemaps (valuemapid,name) VALUES (63,'API value map delete3');
INSERT INTO valuemaps (valuemapid,name) VALUES (64,'API value map delete4');
INSERT INTO mappings (mappingid,valuemapid,value,newvalue) VALUES (684,60,'One','Online');
INSERT INTO mappings (mappingid,valuemapid,value,newvalue) VALUES (685,60,'Two','Offline');
INSERT INTO mappings (mappingid,valuemapid,value,newvalue) VALUES (686,62,'Three','Other');
INSERT INTO mappings (mappingid,valuemapid,value,newvalue) VALUES (687,63,'Four','Unknown');
INSERT INTO users (userid, alias, passwd, autologin, autologout, lang, refresh, type, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (4, 'zabbix-admin', '5fce1b3e34b520afeffb37ce08c7cd66', 0, 0, 'en_GB', '30s', 2, 'default', 0, 0, 50);
INSERT INTO users (userid, alias, passwd, autologin, autologout, lang, refresh, type, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (5, 'zabbix-user', '5fce1b3e34b520afeffb37ce08c7cd66', 0, 0, 'en_GB', '30s', 1, 'default', 0, 0, 50);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (6, 8, 4);

-- host groups
INSERT INTO groups (groupid,name,internal) VALUES (50005,'API host group for update',0);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50010, 50009, 50005);
INSERT INTO groups (groupid,name,internal) VALUES (50006,'API host group for update internal',1);
INSERT INTO groups (groupid,name,internal) VALUES (50007,'API host group delete internal',1);
INSERT INTO groups (groupid,name,internal) VALUES (50008,'API host group delete',0);
INSERT INTO groups (groupid,name,internal) VALUES (50009,'API host group delete2',0);
INSERT INTO groups (groupid,name,internal) VALUES (50010,'API host group delete3',0);
INSERT INTO groups (groupid,name,internal) VALUES (50011,'API host group delete4',0);
-- discovered host groups
INSERT INTO hosts (hostid, host, name, status, flags, description) VALUES (50011, 'API host prototype {#FSNAME}', 'API host prototype {#FSNAME}', 0, 2, '');
INSERT INTO groups (groupid,name,internal) VALUES (50014,'API group for host prototype',0);
INSERT INTO host_discovery (hostid,parent_hostid,parent_itemid) VALUES (50011,NULL,40066);
INSERT INTO group_prototype (group_prototypeid, hostid, name, groupid, templateid) VALUES (8, 50011, 'API discovery group {#HV.NAME}', NULL, NULL);
INSERT INTO group_prototype (group_prototypeid, hostid, name, groupid, templateid) VALUES (9, 50011, '', 50014, NULL);
INSERT INTO groups (groupid,name,internal,flags) VALUES (50015,'API discovery group {#HV.NAME}',0,4);
INSERT INTO group_discovery (groupid, parent_group_prototypeid, name) VALUES (50015, 8, 'API discovery group {#HV.NAME}');

-- user group
INSERT INTO usrgrp (usrgrpid, name) VALUES (13, 'API user group for update');
INSERT INTO usrgrp (usrgrpid, name) VALUES (14, 'API user group for update with user and rights');
INSERT INTO usrgrp (usrgrpid, name) VALUES (15, 'API user group with one user');
INSERT INTO usrgrp (usrgrpid, name) VALUES (16, 'API user group delete');
INSERT INTO usrgrp (usrgrpid, name) VALUES (17, 'API user group delete1');
INSERT INTO usrgrp (usrgrpid, name) VALUES (18, 'API user group delete2');
INSERT INTO usrgrp (usrgrpid, name) VALUES (19, 'API user group delete3');
INSERT INTO usrgrp (usrgrpid, name) VALUES (20, 'API user group in actions');
INSERT INTO usrgrp (usrgrpid, name) VALUES (21, 'API user group in scripts');
INSERT INTO usrgrp (usrgrpid, name) VALUES (22, 'API user group in configuration');
INSERT INTO users (userid, alias, passwd, autologin, autologout, lang, refresh, type, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (6, 'user-in-one-group', '5fce1b3e34b520afeffb37ce08c7cd66', 0, 0, 'en_GB', '30s', 2, 'default', 0, 0, 50);
INSERT INTO users (userid, alias, passwd, autologin, autologout, lang, refresh, type, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (7, 'user-in-two-groups', '5fce1b3e34b520afeffb37ce08c7cd66', 0, 0, 'en_GB', '30s', 2, 'default', 0, 0, 50);
INSERT INTO users (userid, alias, passwd, autologin, autologout, lang, refresh, type, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (8, 'api-user', '5fce1b3e34b520afeffb37ce08c7cd66', 0, 0, 'en_GB', '30s', 2, 'default', 0, 0, 50);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (8, 14, 4);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (9, 15, 6);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (10, 16, 7);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (11, 17, 7);
INSERT INTO rights (rightid, groupid, permission, id) VALUES (2, 14, 3, 50012);
INSERT INTO actions (actionid, name, eventsource, evaltype, status, esc_period, def_shortdata, def_longdata, r_shortdata, r_longdata, ack_longdata) VALUES (16,'API action',0,0,0,60,'{TRIGGER.NAME}: {TRIGGER.STATUS}','{TRIGGER.NAME}: {TRIGGER.STATUS}\r\nLast value: {ITEM.LASTVALUE}','{TRIGGER.NAME}: {TRIGGER.STATUS}','{TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}', '');
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (31, 16, 0, 0, 1, 1, 0);
INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid) VALUES (31, 1, '{TRIGGER.NAME}: {TRIGGER.STATUS}', '{TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}', NULL);
INSERT INTO opmessage_grp (opmessage_grpid, operationid, usrgrpid) VALUES (21, 31, 20);
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description) VALUES (5,'API script','test',2,21,NULL,'api script description');
UPDATE config SET alert_usrgrpid = 22 WHERE configid = 1;

-- users
INSERT INTO users (userid, alias, passwd, autologin, autologout, lang, refresh, type, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (9, 'api-user-for-update', '5fce1b3e34b520afeffb37ce08c7cd66', 0, '15m', 'en_GB', '30s', 2, 'default', 0, 0, 50);
INSERT INTO users (userid, alias, passwd, autologin, autologout, lang, refresh, type, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (10, 'api-user-delete', '5fce1b3e34b520afeffb37ce08c7cd66', 0, '15m', 'en_GB', '30s', 2, 'default', 0, 0, 50);
INSERT INTO users (userid, alias, passwd, autologin, autologout, lang, refresh, type, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (11, 'api-user-delete1', '5fce1b3e34b520afeffb37ce08c7cd66', 0, '15m', 'en_GB', '30s', 2, 'default', 0, 0, 50);
INSERT INTO users (userid, alias, passwd, autologin, autologout, lang, refresh, type, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (12, 'api-user-delete2', '5fce1b3e34b520afeffb37ce08c7cd66', 0, '15m', 'en_GB', '30s', 2, 'default', 0, 0, 50);
INSERT INTO users (userid, alias, passwd, autologin, autologout, lang, refresh, type, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (13, 'api-user-action', '5fce1b3e34b520afeffb37ce08c7cd66', 0, '15m', 'en_GB', '30s', 2, 'default', 0, 0, 50);
INSERT INTO users (userid, alias, passwd, autologin, autologout, lang, refresh, type, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (14, 'api-user-map', '5fce1b3e34b520afeffb37ce08c7cd66', 0, '15m', 'en_GB', '30s', 2, 'default', 0, 0, 50);
INSERT INTO users (userid, alias, passwd, autologin, autologout, lang, refresh, type, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (15, 'api-user-screen', '5fce1b3e34b520afeffb37ce08c7cd66', 0, '15m', 'en_GB', '30s', 2, 'default', 0, 0, 50);
INSERT INTO users (userid, alias, passwd, autologin, autologout, lang, refresh, type, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (16, 'api-user-slideshow', '5fce1b3e34b520afeffb37ce08c7cd66', 0, '15m', 'en_GB', '30s', 2, 'default', 0, 0, 50);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (12, 14, 9);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (13, 14, 10);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (14, 14, 11);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (15, 14, 12);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (16, 9, 13);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (17, 14, 14);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (18, 14, 15);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (19, 14, 16);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (20, 14, 5);
INSERT INTO actions (actionid, name, eventsource, evaltype, status, esc_period, def_shortdata, def_longdata, r_shortdata, r_longdata, ack_longdata) VALUES (17,'API action with user',0,0,0,60,'{TRIGGER.NAME}: {TRIGGER.STATUS}','{TRIGGER.NAME}: {TRIGGER.STATUS}\r\nLast value: {ITEM.LASTVALUE}','{TRIGGER.NAME}: {TRIGGER.STATUS}','{TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}', '');
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (32, 17, 0, 0, 1, 1, 0);
INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid) VALUES (32, 1, '{TRIGGER.NAME}: {TRIGGER.STATUS}', '{TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}', NULL);
INSERT INTO opmessage_usr (opmessage_usrid, operationid, userid) VALUES (4, 32, 13);
INSERT INTO sysmaps (sysmapid, name, width, height, backgroundid, label_type, label_location, highlight, expandproblem, markelements, show_unack, userid, private) VALUES (6, 'API map', 800, 600, NULL, 0, 0, 1, 1, 1, 2, 14, 0);
INSERT INTO screens (screenid, name, hsize, vsize, templateid, userid, private) VALUES (200021, 'API screen', 1, 1, NULL, 15, 0);
INSERT INTO slideshows (slideshowid, name, delay, userid, private) VALUES (200004, 'API slide show', 10, 16, 0);
INSERT INTO screens (screenid, name, hsize, vsize, templateid, userid, private) VALUES (200022, 'API screen for slide show', 1, 1, NULL, 1, 0);
INSERT INTO slides (slideid, slideshowid, screenid, step, delay) VALUES (200012, 200004, 200022, 0, 0);

-- scripts
INSERT INTO hosts (hostid, host, name, status, description) VALUES (50013, 'API disabled host', 'API disabled host', 1, '');
INSERT INTO interface (interfaceid,hostid,main,type,useip,ip,dns,port) values (50024,50013,1,1,1,'127.0.0.1','','10050');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50013, 50013, 50012);
INSERT INTO hosts (hostid, host, name, status, description) VALUES (50012, 'API Host for read permissions', 'API Host for read permissions', 0, '');
INSERT INTO interface (interfaceid,hostid,main,type,useip,ip,dns,port) values (50023,50012,1,1,1,'127.0.0.1','','10050');
INSERT INTO groups (groupid,name,internal) VALUES (50016,'API group with read permissions',0);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50012, 50012, 50016);
INSERT INTO rights (rightid, groupid, permission, id) VALUES (3, 14, 2, 50016);
INSERT INTO hosts (hostid, host, name, status, description) VALUES (50014, 'API Host for deny permissions', 'API Host for deny permissions', 0, '');
INSERT INTO interface (interfaceid,hostid,main,type,useip,ip,dns,port) values (50025,50014,1,1,1,'127.0.0.1','','10050');
INSERT INTO groups (groupid,name,internal) VALUES (50017,'API group with deny permissions',0);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50014, 50014, 50017);
INSERT INTO rights (rightid, groupid, permission, id) VALUES (4, 14, 0, 50017);
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description) VALUES (6,'API script for update one','/sbin/shutdown -r',2,NULL,NULL,'');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description) VALUES (7,'API script for update two','/sbin/shutdown -r',2,NULL,NULL,'');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description) VALUES (8,'API script for delete','/sbin/shutdown -r',2,NULL,NULL,'');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description) VALUES (9,'API script for delete1','/sbin/shutdown -r',2,NULL,NULL,'');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description) VALUES (10,'API script for delete2','/sbin/shutdown -r',2,NULL,NULL,'');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description) VALUES (11,'API script in action','/sbin/shutdown -r',2,NULL,NULL,'');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description) VALUES (12,'API script with user group','/sbin/shutdown -r',2,7,NULL,'');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description) VALUES (13,'API script with host group','/sbin/shutdown -r',2,NULL,4,'');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description) VALUES (14,'API script with write permissions for the host group','/sbin/shutdown -r',3,NULL,NULL,'');
INSERT INTO actions (actionid, name, eventsource, evaltype, status, esc_period, def_shortdata, def_longdata, r_shortdata, r_longdata, ack_longdata) VALUES (18,'API action with script',0,0,0,60,'{TRIGGER.NAME}: {TRIGGER.STATUS}','{TRIGGER.NAME}: {TRIGGER.STATUS}\r\nLast value: {ITEM.LASTVALUE}','{TRIGGER.NAME}: {TRIGGER.STATUS}','{TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}', '');
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (33, 18, 1, 0, 1, 1, 0);
INSERT INTO opcommand_hst (opcommand_hstid, operationid, hostid) VALUES (4, 33, NULL);
INSERT INTO opcommand (operationid, type, scriptid, execute_on, port, authtype, username, password, publickey, privatekey, command) VALUES (33, 4, 11, 0, '', 0, '', '', '', '', '');

-- global macro
INSERT INTO globalmacro (globalmacroid, macro, value) VALUES (13,'{$API_MACRO_FOR_UPDATE1}','update');
INSERT INTO globalmacro (globalmacroid, macro, value) VALUES (14,'{$API_MACRO_FOR_UPDATE2}','update');
INSERT INTO globalmacro (globalmacroid, macro, value) VALUES (15,'{$API_MACRO_FOR_DELETE}','abc');
INSERT INTO globalmacro (globalmacroid, macro, value) VALUES (16,'{$API_MACRO_FOR_DELETE1}','1');
INSERT INTO globalmacro (globalmacroid, macro, value) VALUES (17,'{$API_MACRO_FOR_DELETE2}','2');

-- icon map
INSERT INTO icon_map (iconmapid, name, default_iconid) VALUES (1,'API icon map',2);
INSERT INTO icon_mapping (iconmappingid, iconmapid, iconid, inventory_link, expression, sortorder) VALUES (1,1,2,1,'api icon map expression',0);
INSERT INTO icon_map (iconmapid, name, default_iconid) VALUES (2,'API icon map for update1',2);
INSERT INTO icon_mapping (iconmappingid, iconmapid, iconid, inventory_link, expression, sortorder) VALUES (2,2,2,1,'api expression for update1',0);
INSERT INTO icon_map (iconmapid, name, default_iconid) VALUES (3,'API icon map for update2',2);
INSERT INTO icon_mapping (iconmappingid, iconmapid, iconid, inventory_link, expression, sortorder) VALUES (3,3,2,1,'api expression for update2',0);
INSERT INTO icon_map (iconmapid, name, default_iconid) VALUES (4,'API icon map for delete',2);
INSERT INTO icon_mapping (iconmappingid, iconmapid, iconid, inventory_link, expression, sortorder) VALUES (4,4,2,1,'api expression for delete',0);
INSERT INTO icon_map (iconmapid, name, default_iconid) VALUES (5,'API icon map for delete1',2);
INSERT INTO icon_mapping (iconmappingid, iconmapid, iconid, inventory_link, expression, sortorder) VALUES (5,5,2,1,'api expression for delete1',0);
INSERT INTO icon_map (iconmapid, name, default_iconid) VALUES (6,'API icon map for delete2',2);
INSERT INTO icon_mapping (iconmappingid, iconmapid, iconid, inventory_link, expression, sortorder) VALUES (6,6,2,1,'api expression for delete2',0);
INSERT INTO icon_map (iconmapid, name, default_iconid) VALUES (7,'API iconmap in map',2);
INSERT INTO icon_mapping (iconmappingid, iconmapid, iconid, inventory_link, expression, sortorder) VALUES (7,7,7,1,'api expression',0);
INSERT INTO sysmaps (sysmapid, name, width, height, backgroundid, label_type, label_location, highlight, expandproblem, markelements, show_unack, iconmapid, userid, private) VALUES (7, 'Map with iconmap', 800, 600, NULL, 0, 0, 1, 1, 1, 2, 7, 1, 0);

-- web scenarios
INSERT INTO httptest (httptestid, name, delay, agent, hostid) VALUES (15000, 'Api web scenario', 60, 'Zabbix', 50009);
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, posts) VALUES (15000, 15000, 'Api step', 1, 'http://api.com', '');
INSERT INTO httptest (httptestid, name, delay, agent, hostid) VALUES (15001, 'Api web scenario for update one', 60, 'Zabbix', 50009);
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, posts) VALUES (15001, 15001, 'Api step for update one', 1, 'http://api1.com', '');
INSERT INTO httptest (httptestid, name, delay, agent, hostid) VALUES (15002, 'Api web scenario for update two', 60, 'Zabbix', 50009);
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, posts) VALUES (15002, 15002, 'Api step for update two', 1, 'http://api2.com', '');
INSERT INTO httptest (httptestid, name, delay, agent, hostid) VALUES (15003, 'Api web scenario for delete0', 60, 'Zabbix', 50009);
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, posts) VALUES (15003, 15003, 'Api step for delete0', 1, 'http://api.com', '');
INSERT INTO httptest (httptestid, name, delay, agent, hostid) VALUES (15004, 'Api web scenario for delete1', 60, 'Zabbix', 50009);
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, posts) VALUES (15004, 15004, 'Api step for delete1', 1, 'http://api.com', '');
INSERT INTO httptest (httptestid, name, delay, agent, hostid) VALUES (15005, 'Api web scenario for delete2', 60, 'Zabbix', 50009);
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, posts) VALUES (15005, 15005, 'Api step for delete2', 1, 'http://api.com', '');
INSERT INTO httptest (httptestid, name, delay, agent, hostid) VALUES (15006, 'Api templated web scenario', 60, 'Zabbix', 50010);
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, posts) VALUES (15006, 15006, 'Api templated step', 1, 'http://api.com', '');
INSERT INTO httptest (httptestid, name, delay, agent, hostid, templateid) VALUES (15007, 'Api templated web scenario', 60, 'Zabbix', 50009, 15006);
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, posts) VALUES (15007, 15007, 'Api templated step', 1, 'http://api.com', '');
INSERT INTO httptest (httptestid, name, delay, agent, hostid) VALUES (15008, 'Api web scenario with read permissions', 60, 'Zabbix', 50012);
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, posts) VALUES (15008, 15008, 'Api step with read permissions', 1, 'http://api.com', '');
INSERT INTO httptest (httptestid, name, delay, agent, hostid) VALUES (15009, 'Api web with deny permissions', 60, 'Zabbix', 50014);
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, posts) VALUES (15009, 15009, 'Api step with deny permissions', 1, 'http://api.com', '');
INSERT INTO httptest (httptestid, name, delay, agent, hostid) VALUES (15010, 'Api web scenario for delete as zabbix-admin', 60, 'Zabbix', 50009);
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, posts) VALUES (15010, 15010, 'Api step for delete as zabbix-admin', 1, 'http://api.com', '');
INSERT INTO httptest (httptestid, name, delay, agent, hostid) VALUES (15011, 'Api web scenario for delete as zabbix-user', 60, 'Zabbix', 50009);
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, posts) VALUES (15011, 15011, 'Api step for delete as zabbix-user', 1, 'http://api.com', '');

-- proxy
INSERT INTO hosts (hostid, host, status, description) VALUES (99000, 'Api active proxy for delete0', 5, '');
INSERT INTO hosts (hostid, host, status, description) VALUES (99001, 'Api active proxy for delete1', 5, '');
INSERT INTO hosts (hostid, host, status, description) VALUES (99002, 'Api passive proxy for delete', 6, '');
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port, bulk) VALUES (99002, 99002,1, 0, 1, '127.0.0.1', 'localhost', 10051, 1);
INSERT INTO hosts (hostid, host, status, description) VALUES (99003, 'Api active proxy in action', 5, '');
INSERT INTO hosts (hostid, host, status, description) VALUES (99004, 'Api active proxy with host', 5, '');
INSERT INTO hosts (hostid, proxy_hostid, host, name, status, description) VALUES (99005, 99004,'API Host monitored with proxy', 'API Host monitored with proxy', 0, '');
INSERT INTO interface (interfaceid,hostid,main,type,useip,ip,dns,port) values (99003,99004,1,1,1,'127.0.0.1','','10050');
INSERT INTO actions (actionid, name, eventsource, evaltype, status, esc_period, def_shortdata, def_longdata, r_shortdata, r_longdata, ack_longdata) VALUES (90,'API action with proxy',1,0,0,'1h','Discovery: {DISCOVERY.DEVICE.STATUS} {DISCOVERY.DEVICE.IPADDRESS}','Discovery rule: {DISCOVERY.RULE.NAME}','','','');
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (90, 90, 0, 0, 1, 1, 0);
INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid) VALUES (90, 1, 'Discovery: {DISCOVERY.DEVICE.STATUS} {DISCOVERY.DEVICE.IPADDRESS}', 'Discovery rule: {DISCOVERY.RULE.NAME}', NULL);
INSERT INTO opmessage_grp (opmessage_grpid, operationid, usrgrpid) VALUES (90, 90, 7);
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value, value2) VALUES (90,90,20,0,99003,'');

-- sysmaps
INSERT INTO sysmaps (sysmapid, name, width, height, backgroundid, label_type, label_location, highlight, expandproblem, markelements, show_unack, userid, private) VALUES (10001, 'A', 800, 600, NULL, 0, 0, 1, 1, 1, 2, 1, 0);
INSERT INTO sysmaps (sysmapid, name, width, height, backgroundid, label_type, label_location, highlight, expandproblem, markelements, show_unack, userid, private) VALUES (10002, 'B', 800, 600, NULL, 0, 0, 1, 1, 1, 2, 1, 1);
INSERT INTO sysmaps (sysmapid, name, width, height, backgroundid, label_type, label_location, highlight, expandproblem, markelements, show_unack, userid, private) VALUES (10003, 'C', 800, 600, NULL, 0, 0, 1, 1, 1, 2, 1, 0);
INSERT INTO sysmaps (sysmapid, name, width, height, backgroundid, label_type, label_location, highlight, expandproblem, markelements, show_unack, userid, private) VALUES (10004, 'D', 800, 600, NULL, 0, 0, 1, 1, 1, 2, 1, 0);
INSERT INTO sysmap_user (sysmapuserid, sysmapid, userid, permission) VALUES (1, 10001, 5, 3);
INSERT INTO sysmap_user (sysmapuserid, sysmapid, userid, permission) VALUES (2, 10003, 5, 3);
INSERT INTO sysmap_user (sysmapuserid, sysmapid, userid, permission) VALUES (3, 10004, 5, 3);
INSERT INTO sysmaps_elements (selementid, sysmapid, elementid, elementtype, iconid_off, iconid_on, label, label_location, x, y, iconid_disabled, iconid_maintenance, elementsubtype, areatype, width, height, viewtype, use_iconmap, application) VALUES (7, 10001, 0, 4, 151, NULL, 'New element', -1, 189, 77, NULL, NULL, 0, 0, 200, 200, 0, 1, '');

-- disabled item and LLD rule
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,flags,posts,headers) VALUES (90000, 10084, 1, 0, 3,'Api disabled item','disabled.item','30d','90d',1,'','',0,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,flags,posts,headers) VALUES (90001, 10084, 1, 0, 4,'Api disabled LLD rule','disabled.lld','30d','90d',1,'','',1,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,flags,posts,headers) VALUES (90002, 50013, 50024, 0, 3,'Api item in disabled host','disabled.host.item','30d','90d',0,'','',0,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,flags,posts,headers) VALUES (90003, 50013, 50024, 0, 4,'Api LLD rule in disabled host','disabled.host.lld','30d','90d',0,'','',1,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,flags,posts,headers) VALUES (90004, 10084, 1, 0, 3,'Api item for different item types','types.item','1d','90d',0,'','',0,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,flags,posts,headers) VALUES (90005, 10084, 1, 0, 4,'Api LLD rule for different types','types.lld','1d','90d',0,'','',1,'','');

-- interfaces
INSERT INTO interface (interfaceid,hostid,main,type,useip,ip,dns,port) values (99004,10084,1,2,1,'127.0.0.1','','161');

-- autoregistration action
INSERT INTO usrgrp (usrgrpid, name) VALUES (47, 'User group for action delete');
INSERT INTO users (userid, alias, passwd, autologin, autologout, lang, refresh, type, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (53, 'action-user', '5fce1b3e34b520afeffb37ce08c7cd66', 0, 0, 'en_GB', '30s', 1, 'default', 0, 0, 50);
INSERT INTO users (userid, alias, passwd, autologin, autologout, lang, refresh, type, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (54, 'action-admin', '5fce1b3e34b520afeffb37ce08c7cd66', 0, 0, 'en_GB', '30s', 2, 'default', 0, 0, 50);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (87, 47, 53);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (88, 47, 54);
INSERT INTO actions (actionid, name, eventsource, evaltype, status, esc_period, def_shortdata, def_longdata, r_shortdata, r_longdata, ack_longdata) VALUES (91,'Api Auto registration action',2,0,0,'1h','API Auto registration: {HOST.HOST}','Host name: {HOST.HOST}\r\nHost IP: {HOST.IP}\r\nAgent port: {HOST.PORT}','','','');
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (91, 91, 0, 0, 1, 1, 0);
INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid) VALUES (91, 1, 'Auto registration: {HOST.HOST}', 'Host name: {HOST.HOST}', NULL);
INSERT INTO opmessage_grp (opmessage_grpid, operationid, usrgrpid) VALUES (91, 91, 47);
INSERT INTO actions (actionid, name, eventsource, evaltype, status, esc_period, def_shortdata, def_longdata, r_shortdata, r_longdata, ack_longdata) VALUES (92,	'API Action for deleting',0,0,0,'1h','Problem: {TRIGGER.NAME}','Problem started at {EVENT.TIME} on {EVENT.DATE}\r\nProblem name: {TRIGGER.NAME}\r\nHost: {HOST.NAME}\r\nSeverity: {TRIGGER.SEVERITY}\r\n\r\nOriginal problem ID: {EVENT.ID}\r\n{TRIGGER.URL}','Resolved: {TRIGGER.NAME}','Problem has been resolved at {EVENT.RECOVERY.TIME} on {EVENT.RECOVERY.DATE}\r\nProblem name: {TRIGGER.NAME}\r\nHost: {HOST.NAME}\r\nSeverity: {TRIGGER.SEVERITY}\r\n\r\nOriginal problem ID: {EVENT.ID}\r\n{TRIGGER.URL}','{USER.FULLNAME} acknowledged problem at {ACK.DATE} {ACK.TIME} with the following message:\r\n{ACK.MESSAGE}\r\n\r\nCurrent problem status is {EVENT.STATUS}');
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (92, 91, 0, 0, 1, 1, 0);
INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid) VALUES (92,1,'Problem: {TRIGGER.NAME}',	'Problem started at {EVENT.TIME} on {EVENT.DATE}\r\nProblem name: {TRIGGER.NAME}\r\nHost: {HOST.NAME}\r\nSeverity: {TRIGGER.SEVERITY}\r\n\r\nOriginal problem ID: {EVENT.ID}\r\n{TRIGGER.URL}',NULL);
INSERT INTO opmessage_grp (opmessage_grpid, operationid, usrgrpid) VALUES (92, 92, 47);
INSERT INTO actions (actionid, name, eventsource, evaltype, status, esc_period, def_shortdata, def_longdata, r_shortdata, r_longdata, ack_longdata) VALUES (93,	'API Action for deleting 2',0,0,0,'1h','Problem: {TRIGGER.NAME}','Problem started at {EVENT.TIME} on {EVENT.DATE}\r\nProblem name: {TRIGGER.NAME}\r\nHost: {HOST.NAME}\r\nSeverity: {TRIGGER.SEVERITY}\r\n\r\nOriginal problem ID: {EVENT.ID}\r\n{TRIGGER.URL}','Resolved: {TRIGGER.NAME}',	'Problem has been resolved at {EVENT.RECOVERY.TIME} on {EVENT.RECOVERY.DATE}\r\nProblem name: {TRIGGER.NAME}\r\nHost: {HOST.NAME}\r\nSeverity: {TRIGGER.SEVERITY}\r\n\r\nOriginal problem ID: {EVENT.ID}\r\n{TRIGGER.URL}','{USER.FULLNAME} acknowledged problem at {ACK.DATE} {ACK.TIME} with the following message:\r\n{ACK.MESSAGE}\r\n\r\nCurrent problem status is {EVENT.STATUS}');
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (93, 91, 0, 0, 1, 1, 0);
INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid) VALUES (93,1,'Problem: {TRIGGER.NAME}','Problem started at {EVENT.TIME} on {EVENT.DATE}\r\nProblem name: {TRIGGER.NAME}\r\nHost: {HOST.NAME}\r\nSeverity: {TRIGGER.SEVERITY}\r\n\r\nOriginal problem ID: {EVENT.ID}\r\n{TRIGGER.URL}',NULL);
INSERT INTO opmessage_grp (opmessage_grpid, operationid, usrgrpid) VALUES (94, 93, 47);

-- event correlation
INSERT INTO correlation (correlationid, name, description, evaltype, status, formula) VALUES (99000, 'Event correlation for delete', 'Test description delete', 0, 0, '');
INSERT INTO corr_condition (corr_conditionid, correlationid, type) VALUES (99000, 99000, 0);
INSERT INTO corr_condition_tag (corr_conditionid, tag) VALUES (99000, 'delete tag');
INSERT INTO corr_operation (corr_operationid, correlationid, type) VALUES (99000, 99000, 0);

INSERT INTO correlation (correlationid, name, description, evaltype, status, formula) VALUES (99001, 'Event correlation for update', 'Test description update', 0, 0, '');
INSERT INTO corr_condition (corr_conditionid, correlationid, type) VALUES (99001, 99001, 0);
INSERT INTO corr_condition_tag (corr_conditionid, tag) VALUES (99001, 'update tag');
INSERT INTO corr_operation (corr_operationid, correlationid, type) VALUES (99001, 99001, 0);

INSERT INTO correlation (correlationid, name, description, evaltype, status, formula) VALUES (99002, 'Event correlation for cancel', 'Test description cancel', 1, 0, '');
INSERT INTO corr_condition (corr_conditionid, correlationid, type) VALUES (99002, 99002, 0);
INSERT INTO corr_condition_tag (corr_conditionid, tag) VALUES (99002, 'cancel tag');
INSERT INTO corr_operation (corr_operationid, correlationid, type) VALUES (99002, 99002, 0);

INSERT INTO correlation (correlationid, name, description, evaltype, status, formula) VALUES (99003, 'Event correlation for clone', 'Test description clone', 0, 0, '');
INSERT INTO corr_condition (corr_conditionid, correlationid, type) VALUES (99003, 99003, 0);
INSERT INTO corr_condition_tag (corr_conditionid, tag) VALUES (99003, 'clone tag');
INSERT INTO corr_operation (corr_operationid, correlationid, type) VALUES (99003, 99003, 0);
