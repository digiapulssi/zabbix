ALTER TABLE graphs MODIFY graphid DEFAULT NULL;
ALTER TABLE graphs MODIFY templateid DEFAULT NULL;
ALTER TABLE graphs MODIFY templateid NULL;
ALTER TABLE graphs MODIFY ymin_itemid DEFAULT NULL;
ALTER TABLE graphs MODIFY ymin_itemid NULL;
ALTER TABLE graphs MODIFY ymax_itemid DEFAULT NULL;
ALTER TABLE graphs MODIFY ymax_itemid NULL;
ALTER TABLE graphs MODIFY show_legend DEFAULT 1;
UPDATE graphs SET show_legend=1 WHERE graphtype=0 OR graphtype=1;
ALTER TABLE graphs ADD flags number(10) DEFAULT '0' NOT NULL;
UPDATE graphs SET templateid=NULL WHERE templateid=0;
UPDATE graphs SET templateid=NULL WHERE NOT templateid IS NULL AND NOT templateid IN (SELECT graphid FROM graphs);
UPDATE graphs SET ymin_itemid=NULL WHERE ymin_itemid=0 OR NOT ymin_itemid IN (SELECT itemid FROM items);
UPDATE graphs SET ymax_itemid=NULL WHERE ymax_itemid=0 OR NOT ymax_itemid IN (SELECT itemid FROM items);
UPDATE graphs SET ymin_type=0 WHERE ymin_type=2 AND ymin_itemid=NULL;
UPDATE graphs SET ymax_type=0 WHERE ymax_type=2 AND ymax_itemid=NULL;
ALTER TABLE graphs ADD CONSTRAINT c_graphs_1 FOREIGN KEY (templateid) REFERENCES graphs (graphid) ON DELETE CASCADE;
ALTER TABLE graphs ADD CONSTRAINT c_graphs_2 FOREIGN KEY (ymin_itemid) REFERENCES items (itemid);
ALTER TABLE graphs ADD CONSTRAINT c_graphs_3 FOREIGN KEY (ymax_itemid) REFERENCES items (itemid);
