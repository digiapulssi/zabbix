ALTER TABLE sysmaps MODIFY sysmapid DEFAULT NULL;
ALTER TABLE sysmaps MODIFY width DEFAULT '600';
ALTER TABLE sysmaps MODIFY height DEFAULT '400';
ALTER TABLE sysmaps MODIFY backgroundid DEFAULT NULL;
ALTER TABLE sysmaps MODIFY backgroundid NULL;
ALTER TABLE sysmaps MODIFY label_type DEFAULT '2';
ALTER TABLE sysmaps MODIFY label_location DEFAULT '3';
ALTER TABLE sysmaps ADD expandproblem number(10) DEFAULT '1' NOT NULL;
ALTER TABLE sysmaps ADD markelements number(10) DEFAULT '0' NOT NULL;
ALTER TABLE sysmaps ADD show_unack number(10) DEFAULT '0' NOT NULL;
ALTER TABLE sysmaps ADD grid_size number(10) DEFAULT '50' NOT NULL;
ALTER TABLE sysmaps ADD grid_show number(10) DEFAULT '1' NOT NULL;
ALTER TABLE sysmaps ADD grid_align number(10) DEFAULT '1' NOT NULL;
ALTER TABLE sysmaps ADD label_format number(10) DEFAULT '0' NOT NULL;
ALTER TABLE sysmaps ADD label_type_host number(10) DEFAULT '2' NOT NULL;
ALTER TABLE sysmaps ADD label_type_hostgroup number(10) DEFAULT '2' NOT NULL;
ALTER TABLE sysmaps ADD label_type_trigger number(10) DEFAULT '2' NOT NULL;
ALTER TABLE sysmaps ADD label_type_map number(10) DEFAULT '2' NOT NULL;
ALTER TABLE sysmaps ADD label_type_image number(10) DEFAULT '2' NOT NULL;
ALTER TABLE sysmaps ADD label_string_host nvarchar2(255) DEFAULT '';
ALTER TABLE sysmaps ADD label_string_hostgroup nvarchar2(255) DEFAULT '';
ALTER TABLE sysmaps ADD label_string_trigger nvarchar2(255) DEFAULT '';
ALTER TABLE sysmaps ADD label_string_map nvarchar2(255) DEFAULT '';
ALTER TABLE sysmaps ADD label_string_image nvarchar2(255) DEFAULT '';
ALTER TABLE sysmaps ADD iconmapid number(20) NULL;
UPDATE sysmaps SET backgroundid=NULL WHERE backgroundid=0;
UPDATE sysmaps SET show_unack=1 WHERE highlight>7 AND highlight<16;
UPDATE sysmaps SET show_unack=2 WHERE highlight>23;
UPDATE sysmaps SET highlight=(highlight-16) WHERE highlight>15;
UPDATE sysmaps SET highlight=(highlight-8) WHERE highlight>7;
UPDATE sysmaps SET markelements=1 WHERE highlight>3  AND highlight<8;
UPDATE sysmaps SET highlight=(highlight-4) WHERE highlight>3;
UPDATE sysmaps SET expandproblem=0 WHERE highlight>1 AND highlight<4;
UPDATE sysmaps SET highlight=(highlight-2) WHERE highlight>1;
ALTER TABLE sysmaps ADD CONSTRAINT c_sysmaps_1 FOREIGN KEY (backgroundid) REFERENCES images (imageid);
ALTER TABLE sysmaps ADD CONSTRAINT c_sysmaps_2 FOREIGN KEY (iconmapid) REFERENCES icon_map (iconmapid);
