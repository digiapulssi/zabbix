ALTER TABLE media_type ALTER COLUMN mediatypeid SET WITH DEFAULT NULL
/
REORG TABLE media_type
/
