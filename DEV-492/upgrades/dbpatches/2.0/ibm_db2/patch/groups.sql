ALTER TABLE groups ALTER COLUMN groupid SET WITH DEFAULT NULL;
REORG TABLE groups;
