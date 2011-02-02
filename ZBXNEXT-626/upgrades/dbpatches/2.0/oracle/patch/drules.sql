ALTER TABLE dchecks MODIFY dcheckid DEFAULT NULL;
ALTER TABLE dchecks MODIFY druleid DEFAULT NULL;
ALTER TABLE dchecks ADD uniq number(10) DEFAULT '0' NOT NULL;
DELETE FROM dchecks WHERE NOT druleid IN (SELECT druleid FROM drules);
ALTER TABLE dchecks ADD CONSTRAINT c_dchecks_1 FOREIGN KEY (druleid) REFERENCES drules (druleid) ON DELETE CASCADE;
UPDATE dchecks SET uniq=1 WHERE dcheckid IN (SELECT unique_dcheckid FROM drules);
ALTER TABLE drules MODIFY druleid DEFAULT NULL;
ALTER TABLE drules MODIFY proxy_hostid DEFAULT NULL;
ALTER TABLE drules MODIFY proxy_hostid NULL;
ALTER TABLE drules DROP COLUMN unique_dcheckid;
UPDATE drules SET proxy_hostid=NULL WHERE NOT proxy_hostid IN (SELECT hostid FROM hosts);
ALTER TABLE drules ADD CONSTRAINT c_drules_1 FOREIGN KEY (proxy_hostid) REFERENCES hosts (hostid);
