ALTER TABLE history_sync
	MODIFY itemid bigint unsigned NOT NULL,
	MODIFY nodeid integer NOT NULL,
	ADD ns integer DEFAULT '0' NOT NULL;
