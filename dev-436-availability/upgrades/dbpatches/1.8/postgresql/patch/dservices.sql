-- See also dhosts.sql

DROP INDEX dservices_1;
CREATE UNIQUE INDEX dservices_1 on dservices (dcheckid,type,key_,ip,port);
CREATE INDEX dservices_2 on dservices (dhostid);
