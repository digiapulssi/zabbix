ALTER TABLE nodes MODIFY nodeid DEFAULT NULL;
ALTER TABLE nodes MODIFY masterid DEFAULT NULL;
ALTER TABLE nodes MODIFY masterid NULL;
UPDATE nodes SET masterid=NULL WHERE masterid=0;
ALTER TABLE nodes ADD CONSTRAINT c_nodes_1 FOREIGN KEY (masterid) REFERENCES nodes (nodeid);
