ALTER TABLE nodes MODIFY nodeid integer NOT NULL,
		  MODIFY masterid integer NULL;
UPDATE nodes SET masterid=NULL WHERE masterid=0;
ALTER TABLE nodes ADD CONSTRAINT c_nodes_1 FOREIGN KEY (masterid) REFERENCES nodes (nodeid) ON DELETE CASCADE;
