CREATE TABLE actions_tmp (
        actionid                bigint         DEFAULT '0'     NOT NULL,
        name                    varchar(255)            DEFAULT ''      NOT NULL,
        eventsource             integer         DEFAULT '0'     NOT NULL,
        evaltype                integer         DEFAULT '0'     NOT NULL,
        status          integer         DEFAULT '0'     NOT NULL,
        PRIMARY KEY (actionid)
) with OIDS;

CREATE TABLE operations (
        operationid             bigint         DEFAULT '0'     NOT NULL,
        actionid                bigint         DEFAULT '0'     NOT NULL,
        operationtype           integer         DEFAULT '0'     NOT NULL,
        object          integer         DEFAULT '0'     NOT NULL,
        objectid                bigint         DEFAULT '0'     NOT NULL,
        shortdata               varchar(255)            DEFAULT ''      NOT NULL,
        longdata                text            DEFAULT ''      NOT NULL,
        scripts_tmp             text            DEFAULT ''      NOT NULL,
        PRIMARY KEY (operationid)
) with OIDS;
CREATE INDEX operations_1 on operations (actionid);

insert into actions_tmp select actionid,actionid,source,0,status from actions;

insert into operations select actionid,actionid,actiontype,recipient,userid,subject,message,scripts from actions;
update operations set longdata=scripts_tmp where operationtype=1;
alter table operations drop scripts_tmp;

drop table actions;
alter table actions_tmp rename to actions;
