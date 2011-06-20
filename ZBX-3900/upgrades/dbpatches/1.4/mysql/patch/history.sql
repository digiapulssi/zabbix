CREATE TABLE history_tmp (
	itemid		bigint unsigned		DEFAULT '0'	NOT NULL,
	clock		integer		DEFAULT '0'	NOT NULL,
	value		double(16,4)		DEFAULT '0.0000'	NOT NULL
) ENGINE=InnoDB;

CREATE TABLE history (
  itemid                int(4)          DEFAULT '0' NOT NULL,
  clock                 int(4)          DEFAULT '0' NOT NULL,
  value                 double(16,4)    DEFAULT '0.0000' NOT NULL,
--  PRIMARY KEY (itemid,clock)
  KEY itemidclock (itemid, clock)
) type=InnoDB;


insert into history_tmp select * from history;
CREATE INDEX history_1 on history_tmp (itemid,clock);
drop table history;
alter table history_tmp rename history;
