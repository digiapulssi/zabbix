ALTER TABLE `history_uint` DROP INDEX `history_uint_1`, ADD PRIMARY KEY (`itemid`, `clock`, `ns`);
ALTER TABLE `history` DROP INDEX `history_1`, ADD PRIMARY KEY (`itemid`, `clock`, `ns`);
ALTER TABLE `history_str` DROP INDEX `history_str_1`, ADD PRIMARY KEY (`itemid`, `clock`, `ns`);
ALTER TABLE `history_log` DROP PRIMARY KEY, ADD PRIMARY KEY (`itemid`, `clock`, `ns`), ADD INDEX `history_log_0` (`id`), DROP INDEX `history_log_2`, ADD INDEX `history_log_2` (`itemid`, `id`), DROP INDEX `history_log_1`;
ALTER TABLE `history_text` DROP PRIMARY KEY, ADD PRIMARY KEY (`itemid`, `clock`, `ns`), ADD INDEX `history_text_0` (`id`), DROP INDEX `history_text_2`, ADD INDEX `history_text_2` (`itemid`, `id`), DROP INDEX `history_text_1`;

ALTER TABLE `history` PARTITION BY RANGE ( clock) (PARTITION p2011_10_23 VALUES LESS THAN (UNIX_TIMESTAMP("2011-10-24 00:00:00") div 1) ENGINE = InnoDB);
ALTER TABLE `history_log` PARTITION BY RANGE ( clock) (PARTITION p2011_10_23 VALUES LESS THAN (UNIX_TIMESTAMP("2011-10-24 00:00:00") div 1) ENGINE = InnoDB);
ALTER TABLE `history_str` PARTITION BY RANGE ( clock) (PARTITION p2011_10_23 VALUES LESS THAN (UNIX_TIMESTAMP("2011-10-24 00:00:00") div 1) ENGINE = InnoDB);
ALTER TABLE `history_text` PARTITION BY RANGE ( clock) (PARTITION p2011_10_23 VALUES LESS THAN (UNIX_TIMESTAMP("2011-10-24 00:00:00") div 1) ENGINE = InnoDB);
ALTER TABLE `history_uint` PARTITION BY RANGE ( clock) (PARTITION p2011_10_23 VALUES LESS THAN (UNIX_TIMESTAMP("2011-10-24 00:00:00") div 1) ENGINE = InnoDB);
ALTER TABLE `trends` PARTITION BY RANGE ( clock) (PARTITION p2010_10 VALUES LESS THAN (UNIX_TIMESTAMP("2010-11-01 00:00:00") div 1) ENGINE = InnoDB);
ALTER TABLE `trends_uint` PARTITION BY RANGE ( clock) (PARTITION p2010_10 VALUES LESS THAN (UNIX_TIMESTAMP("2010-11-01 00:00:00") div 1) ENGINE = InnoDB);

ALTER TABLE `housekeeper` ENGINE BLACKHOLE;
