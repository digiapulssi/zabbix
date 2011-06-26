ALTER TABLE config MODIFY configid DEFAULT NULL;
ALTER TABLE config MODIFY alert_usrgrpid DEFAULT NULL;
ALTER TABLE config MODIFY alert_usrgrpid NULL;
ALTER TABLE config MODIFY discovery_groupid DEFAULT NULL;
ALTER TABLE config MODIFY default_theme nvarchar2(128) DEFAULT 'css_ob.css' NOT NULL;
ALTER TABLE config ADD ns_support number(10) DEFAULT '0' NOT NULL;
ALTER TABLE config ADD severity_color_0 nvarchar2(6) DEFAULT 'DBDBDB';
ALTER TABLE config ADD severity_color_1 nvarchar2(6) DEFAULT 'D6F6FF';
ALTER TABLE config ADD severity_color_2 nvarchar2(6) DEFAULT 'FFF6A5';
ALTER TABLE config ADD severity_color_3 nvarchar2(6) DEFAULT 'FFB689';
ALTER TABLE config ADD severity_color_4 nvarchar2(6) DEFAULT 'FF9999';
ALTER TABLE config ADD severity_color_5 nvarchar2(6) DEFAULT 'FF3838';
ALTER TABLE config ADD severity_name_0 nvarchar2(32) DEFAULT 'Not classified';
ALTER TABLE config ADD severity_name_1 nvarchar2(32) DEFAULT 'Information';
ALTER TABLE config ADD severity_name_2 nvarchar2(32) DEFAULT 'Warning';
ALTER TABLE config ADD severity_name_3 nvarchar2(32) DEFAULT 'Average';
ALTER TABLE config ADD severity_name_4 nvarchar2(32) DEFAULT 'High';
ALTER TABLE config ADD severity_name_5 nvarchar2(32) DEFAULT 'Disaster';
UPDATE config SET alert_usrgrpid=NULL WHERE NOT alert_usrgrpid IN (SELECT usrgrpid FROM usrgrp);
UPDATE config SET discovery_groupid=NULL WHERE NOT discovery_groupid IN (SELECT groupid FROM groups);
UPDATE config SET default_theme='css_ob.css' WHERE default_theme='default.css';
ALTER TABLE config ADD CONSTRAINT c_config_1 FOREIGN KEY (alert_usrgrpid) REFERENCES usrgrp (usrgrpid);
ALTER TABLE config ADD CONSTRAINT c_config_2 FOREIGN KEY (discovery_groupid) REFERENCES groups (groupid);
