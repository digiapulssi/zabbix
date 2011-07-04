--
-- ZABBIX
-- Copyright (C) 2000-2011 SIA Zabbix
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 2 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program; if not, write to the Free Software
-- Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
--

--
-- Do not use spaces
--

TABLE|slideshows|slideshowid|ZBX_SYNC
FIELD		|slideshowid	|t_id		|	|NOT NULL	|0
FIELD		|name		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|delay		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC

TABLE|slides|slideid|ZBX_SYNC
FIELD		|slideid	|t_id		|	|NOT NULL	|0
FIELD		|slideshowid	|t_id		|	|NOT NULL	|ZBX_SYNC		|1|slideshows
FIELD		|screenid	|t_id		|	|NOT NULL	|ZBX_SYNC		|2|screens
FIELD		|step		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|delay		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
INDEX		|slides_1	|slideshowid

TABLE|drules|druleid|ZBX_SYNC
FIELD		|druleid	|t_id		|	|NOT NULL	|0
FIELD		|proxy_hostid	|t_id		|	|NULL		|ZBX_SYNC		|1|hosts	|hostid		|RESTRICT
FIELD		|name		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|iprange	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|delay		|t_integer	|'3600'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|nextcheck	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|status		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC

TABLE|dchecks|dcheckid|ZBX_SYNC
FIELD		|dcheckid	|t_id		|	|NOT NULL	|0
FIELD		|druleid	|t_id		|	|NOT NULL	|ZBX_SYNC,ZBX_PROXY	|1|drules
FIELD		|type		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|key_		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|snmp_community	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|ports		|t_varchar(255)	|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|snmpv3_securityname|t_varchar(64)|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|snmpv3_securitylevel|t_integer	|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|snmpv3_authpassphrase|t_varchar(64)|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|snmpv3_privpassphrase|t_varchar(64)|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|uniq		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
INDEX		|1		|druleid

TABLE|dhosts|dhostid|ZBX_SYNC
FIELD		|dhostid	|t_id		|	|NOT NULL	|0
FIELD		|druleid	|t_id		|	|NOT NULL	|ZBX_SYNC		|1|drules
FIELD		|status		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|lastup		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|lastdown	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
INDEX		|1		|druleid

TABLE|dservices|dserviceid|ZBX_SYNC
FIELD		|dserviceid	|t_id		|	|NOT NULL	|0
FIELD		|dhostid	|t_id		|	|NOT NULL	|ZBX_SYNC		|1|dhosts
FIELD		|type		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|key_		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|value		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|port		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|status		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|lastup		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|lastdown	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|dcheckid	|t_id		|	|NOT NULL	|ZBX_SYNC		|2|dchecks
FIELD		|ip		|t_varchar(39)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|dns		|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
UNIQUE		|1		|dcheckid,type,key_,ip,port
INDEX		|2		|dhostid

TABLE|ids|nodeid,table_name,field_name|
FIELD		|nodeid		|t_integer	|	|NOT NULL	|0			|-|nodes
FIELD		|table_name	|t_varchar(64)	|''	|NOT NULL	|0
FIELD		|field_name	|t_varchar(64)	|''	|NOT NULL	|0
FIELD		|nextid		|t_id		|	|NOT NULL	|0

TABLE|httptest|httptestid|ZBX_SYNC
FIELD		|httptestid	|t_id		|	|NOT NULL	|0
FIELD		|name		|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|applicationid	|t_id		|	|NOT NULL	|ZBX_SYNC		|1|applications
FIELD		|lastcheck	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|nextcheck	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|curstate	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|curstep	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|lastfailedstep	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|delay		|t_integer	|'60'	|NOT NULL	|ZBX_SYNC
FIELD		|status		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|macros		|t_blob		|''	|NOT NULL	|ZBX_SYNC
FIELD		|agent		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|time		|t_double	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|error		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|authentication	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|http_user	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|http_password	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
INDEX		|httptest_1	|applicationid
INDEX		|2		|name
INDEX		|3		|status

TABLE|httpstep|httpstepid|ZBX_SYNC
FIELD		|httpstepid	|t_id		|	|NOT NULL	|0
FIELD		|httptestid	|t_id		|	|NOT NULL	|ZBX_SYNC		|1|httptest
FIELD		|name		|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|no		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|url		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|timeout	|t_integer	|'30'	|NOT NULL	|ZBX_SYNC
FIELD		|posts		|t_blob		|''	|NOT NULL	|ZBX_SYNC
FIELD		|required	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|status_codes	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
INDEX		|httpstep_1	|httptestid

TABLE|httpstepitem|httpstepitemid|ZBX_SYNC
FIELD		|httpstepitemid	|t_id		|	|NOT NULL	|0
FIELD		|httpstepid	|t_id		|	|NOT NULL	|ZBX_SYNC		|1|httpstep
FIELD		|itemid		|t_id		|	|NOT NULL	|ZBX_SYNC		|2|items
FIELD		|type		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
UNIQUE		|httpstepitem_1	|httpstepid,itemid

TABLE|httptestitem|httptestitemid|ZBX_SYNC
FIELD		|httptestitemid	|t_id		|	|NOT NULL	|0
FIELD		|httptestid	|t_id		|	|NOT NULL	|ZBX_SYNC		|1|httptest
FIELD		|itemid		|t_id		|	|NOT NULL	|ZBX_SYNC		|2|items
FIELD		|type		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
UNIQUE		|httptestitem_1	|httptestid,itemid

TABLE|nodes|nodeid|
FIELD		|nodeid		|t_integer	|	|NOT NULL	|0
FIELD		|name		|t_varchar(64)	|'0'	|NOT NULL	|0
FIELD		|timezone	|t_integer	|'0'	|NOT NULL	|0
FIELD		|ip		|t_varchar(39)	|''	|NOT NULL	|0
FIELD		|port		|t_integer	|'10051'|NOT NULL	|0
FIELD		|slave_history	|t_integer	|'30'	|NOT NULL	|0
FIELD		|slave_trends	|t_integer	|'365'	|NOT NULL	|0
FIELD		|nodetype	|t_integer	|'0'	|NOT NULL	|0
FIELD		|masterid	|t_integer	|	|NULL		|0			|1|nodes		|nodeid

TABLE|node_cksum||0
FIELD		|nodeid		|t_integer	|	|NOT NULL	|0			|1|nodes
FIELD		|tablename	|t_varchar(64)	|''	|NOT NULL	|0
FIELD		|recordid	|t_id		|	|NOT NULL	|0
FIELD		|cksumtype	|t_integer	|'0'	|NOT NULL	|0
FIELD		|cksum		|t_cksum_text	|''	|NOT NULL	|0
FIELD		|sync		|t_char(128)	|''	|NOT NULL	|0
INDEX		|1		|nodeid,cksumtype,tablename,recordid

TABLE|services_times|timeid|ZBX_SYNC
FIELD		|timeid		|t_id		|	|NOT NULL	|0
FIELD		|serviceid	|t_id		|	|NOT NULL	|ZBX_SYNC		|1|services
FIELD		|type		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|ts_from	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|ts_to		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|note		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
INDEX		|times_1	|serviceid,type,ts_from,ts_to

-- History tables

TABLE|alerts|alertid|ZBX_HISTORY
FIELD		|alertid	|t_id		|	|NOT NULL	|0
FIELD		|actionid	|t_id		|	|NOT NULL	|0			|1|actions
FIELD		|eventid	|t_id		|	|NOT NULL	|0			|2|events
FIELD		|userid		|t_id		|	|NULL		|0			|3|users
FIELD		|clock		|t_time		|'0'	|NOT NULL	|0
FIELD		|mediatypeid	|t_id		|	|NULL		|0			|4|media_type
FIELD		|sendto		|t_varchar(100)	|''	|NOT NULL	|0
FIELD		|subject	|t_varchar(255)	|''	|NOT NULL	|0
FIELD		|message	|t_blob		|''	|NOT NULL	|0
FIELD		|status		|t_integer	|'0'	|NOT NULL	|0
FIELD		|retries	|t_integer	|'0'	|NOT NULL	|0
FIELD		|error		|t_varchar(128)	|''	|NOT NULL	|0
FIELD		|nextcheck	|t_integer	|'0'	|NOT NULL	|0
FIELD		|esc_step	|t_integer	|'0'	|NOT NULL	|0
FIELD		|alerttype	|t_integer	|'0'	|NOT NULL	|0
INDEX		|1		|actionid
INDEX		|2		|clock
INDEX		|3		|eventid
INDEX		|4		|status,retries
INDEX		|5		|mediatypeid
INDEX		|6		|userid

TABLE|history||0
FIELD		|itemid		|t_id		|	|NOT NULL	|0			|-|items
FIELD		|clock		|t_time		|'0'	|NOT NULL	|0
FIELD		|value		|t_double	|'0.0000'|NOT NULL	|0
FIELD		|ns		|t_nanosec	|'0'	|NOT NULL	|0
INDEX		|1		|itemid,clock

TABLE|history_sync|id|ZBX_HISTORY_SYNC
FIELD		|id		|t_serial	|	|NOT NULL	|0
FIELD		|nodeid		|t_integer	|	|NOT NULL	|0			|-|nodes
FIELD		|itemid		|t_id		|	|NOT NULL	|ZBX_HISTORY_SYNC	|-|items
FIELD		|clock		|t_time		|'0'	|NOT NULL	|ZBX_HISTORY_SYNC
FIELD		|value		|t_double	|'0.0000'|NOT NULL	|ZBX_HISTORY_SYNC
FIELD		|ns		|t_nanosec	|'0'	|NOT NULL	|ZBX_HISTORY_SYNC
INDEX		|1		|nodeid,id

TABLE|history_uint||0
FIELD		|itemid		|t_id		|	|NOT NULL	|0			|-|items
FIELD		|clock		|t_time		|'0'	|NOT NULL	|0
FIELD		|value		|t_bigint	|'0'	|NOT NULL	|0
FIELD		|ns		|t_nanosec	|'0'	|NOT NULL	|0
INDEX		|1		|itemid,clock

TABLE|history_uint_sync|id|ZBX_HISTORY_SYNC
FIELD		|id		|t_serial	|	|NOT NULL	|0
FIELD		|nodeid		|t_integer	|	|NOT NULL	|0			|-|nodes
FIELD		|itemid		|t_id		|	|NOT NULL	|ZBX_HISTORY_SYNC	|-|items
FIELD		|clock		|t_time		|'0'	|NOT NULL	|ZBX_HISTORY_SYNC
FIELD		|value		|t_bigint	|'0'	|NOT NULL	|ZBX_HISTORY_SYNC
FIELD		|ns		|t_nanosec	|'0'	|NOT NULL	|ZBX_HISTORY_SYNC
INDEX		|1		|nodeid,id

TABLE|history_str||0
FIELD		|itemid		|t_id		|	|NOT NULL	|0			|-|items
FIELD		|clock		|t_time		|'0'	|NOT NULL	|0
FIELD		|value		|t_varchar(255)	|''	|NOT NULL	|0
FIELD		|ns		|t_nanosec	|'0'	|NOT NULL	|0
INDEX		|1		|itemid,clock

TABLE|history_str_sync|id|ZBX_HISTORY_SYNC
FIELD		|id		|t_serial	|	|NOT NULL	|0
FIELD		|nodeid		|t_integer	|	|NOT NULL	|0			|-|nodes
FIELD		|itemid		|t_id		|	|NOT NULL	|ZBX_HISTORY_SYNC	|-|items
FIELD		|clock		|t_time		|'0'	|NOT NULL	|ZBX_HISTORY_SYNC
FIELD		|value		|t_varchar(255)	|''	|NOT NULL	|ZBX_HISTORY_SYNC
FIELD		|ns		|t_nanosec	|'0'	|NOT NULL	|ZBX_HISTORY_SYNC
INDEX		|1		|nodeid,id

TABLE|history_log|id|ZBX_HISTORY
FIELD		|id		|t_id		|	|NOT NULL	|0
FIELD		|itemid		|t_id		|	|NOT NULL	|0			|-|items
FIELD		|clock		|t_time		|'0'	|NOT NULL	|0
FIELD		|timestamp	|t_time		|'0'	|NOT NULL	|0
FIELD		|source		|t_varchar(64)	|''	|NOT NULL	|0
FIELD		|severity	|t_integer	|'0'	|NOT NULL	|0
FIELD		|value		|t_history_log	|''	|NOT NULL	|0
FIELD		|logeventid	|t_integer	|'0'	|NOT NULL	|0
FIELD		|ns		|t_nanosec	|'0'	|NOT NULL	|0
INDEX		|1		|itemid,clock
UNIQUE		|2		|itemid,id

TABLE|history_text|id|ZBX_HISTORY
FIELD		|id		|t_id		|	|NOT NULL	|0
FIELD		|itemid		|t_id		|	|NOT NULL	|0			|-|items
FIELD		|clock		|t_time		|'0'	|NOT NULL	|0
FIELD		|value		|t_history_text	|''	|NOT NULL	|0
FIELD		|ns		|t_nanosec	|'0'	|NOT NULL	|0
INDEX		|1		|itemid,clock
UNIQUE		|2		|itemid,id

TABLE|proxy_history|id|0
FIELD		|id		|t_serial	|	|NOT NULL	|0
FIELD		|itemid		|t_id		|	|NOT NULL	|0			|-|items
FIELD		|clock		|t_time		|'0'	|NOT NULL	|0
FIELD		|timestamp	|t_time		|'0'	|NOT NULL	|0
FIELD		|source		|t_varchar(64)	|''	|NOT NULL	|0
FIELD		|severity	|t_integer	|'0'	|NOT NULL	|0
FIELD		|value		|t_history_log	|''	|NOT NULL	|0
FIELD		|logeventid	|t_integer	|'0'	|NOT NULL	|0
FIELD		|ns		|t_nanosec	|'0'	|NOT NULL	|0
INDEX		|1		|clock

TABLE|proxy_dhistory|id|0
FIELD		|id		|t_serial	|	|NOT NULL	|0
FIELD		|clock		|t_time		|'0'	|NOT NULL	|0
FIELD		|druleid	|t_id		|	|NOT NULL	|0			|-|drules
FIELD		|type		|t_integer	|'0'	|NOT NULL	|0
FIELD		|ip		|t_varchar(39)	|''	|NOT NULL	|0
FIELD		|port		|t_integer	|'0'	|NOT NULL	|0
FIELD		|key_		|t_varchar(255)	|''	|NOT NULL	|0
FIELD		|value		|t_varchar(255)	|''	|NOT NULL	|0
FIELD		|status		|t_integer	|'0'	|NOT NULL	|0
FIELD		|dcheckid	|t_id		|	|NOT NULL	|0			|-|dchecks
FIELD		|dns		|t_varchar(64)	|''	|NOT NULL	|0
INDEX		|1		|clock

TABLE|events|eventid|ZBX_HISTORY
FIELD		|eventid	|t_id		|	|NOT NULL	|0
FIELD		|source		|t_integer	|'0'	|NOT NULL	|0
FIELD		|object		|t_integer	|'0'	|NOT NULL	|0
FIELD		|objectid	|t_id		|'0'	|NOT NULL	|0
FIELD		|clock		|t_time		|'0'	|NOT NULL	|0
FIELD		|value		|t_integer	|'0'	|NOT NULL	|0
FIELD		|acknowledged	|t_integer	|'0'	|NOT NULL	|0
FIELD		|ns		|t_nanosec	|'0'	|NOT NULL	|0
FIELD		|value_changed	|t_integer	|'0'	|NOT NULL	|0
INDEX		|1		|object,objectid,eventid
INDEX		|2		|clock

TABLE|trends|itemid,clock|
FIELD		|itemid		|t_id		|	|NOT NULL	|0			|-|items
FIELD		|clock		|t_time		|'0'	|NOT NULL	|0
FIELD		|num		|t_integer	|'0'	|NOT NULL	|0
FIELD		|value_min	|t_double	|'0.0000'|NOT NULL	|0
FIELD		|value_avg	|t_double	|'0.0000'|NOT NULL	|0
FIELD		|value_max	|t_double	|'0.0000'|NOT NULL	|0

TABLE|trends_uint|itemid,clock|
FIELD		|itemid		|t_id		|	|NOT NULL	|0			|-|items
FIELD		|clock		|t_time		|'0'	|NOT NULL	|0
FIELD		|num		|t_integer	|'0'	|NOT NULL	|0
FIELD		|value_min	|t_bigint	|'0'	|NOT NULL	|0
FIELD		|value_avg	|t_bigint	|'0'	|NOT NULL	|0
FIELD		|value_max	|t_bigint	|'0'	|NOT NULL	|0

TABLE|acknowledges|acknowledgeid|ZBX_HISTORY
FIELD		|acknowledgeid	|t_id		|	|NOT NULL	|0
FIELD		|userid		|t_id		|	|NOT NULL	|0			|1|users
FIELD		|eventid	|t_id		|	|NOT NULL	|0			|2|events
FIELD		|clock		|t_time		|'0'	|NOT NULL	|0
FIELD		|message	|t_varchar(255)	|''	|NOT NULL	|0
INDEX		|1		|userid
INDEX		|2		|eventid
INDEX		|3		|clock

TABLE|auditlog|auditid|ZBX_HISTORY
FIELD		|auditid	|t_id		|	|NOT NULL	|0
FIELD		|userid		|t_id		|	|NOT NULL	|0			|1|users
FIELD		|clock		|t_time		|'0'	|NOT NULL	|0
FIELD		|action		|t_integer	|'0'	|NOT NULL	|0
FIELD		|resourcetype	|t_integer	|'0'	|NOT NULL	|0
FIELD		|details	|t_varchar(128) |'0'	|NOT NULL	|0
FIELD		|ip		|t_varchar(39)	|''	|NOT NULL	|0
FIELD		|resourceid	|t_id		|'0'	|NOT NULL	|0
FIELD		|resourcename	|t_varchar(255)	|''	|NOT NULL	|0
INDEX		|1		|userid,clock
INDEX		|2		|clock

TABLE|auditlog_details|auditdetailid|ZBX_HISTORY
FIELD		|auditdetailid	|t_id		|	|NOT NULL	|0
FIELD		|auditid	|t_id		|	|NOT NULL	|0			|1|auditlog
FIELD		|table_name	|t_varchar(64)	|''	|NOT NULL	|0
FIELD		|field_name	|t_varchar(64)	|''	|NOT NULL	|0
FIELD		|oldvalue	|t_blob		|''	|NOT NULL	|0
FIELD		|newvalue	|t_blob		|''	|NOT NULL	|0
INDEX		|1		|auditid

TABLE|service_alarms|servicealarmid|ZBX_HISTORY
FIELD		|servicealarmid	|t_id		|	|NOT NULL	|0
FIELD		|serviceid	|t_id		|	|NOT NULL	|0			|1|services
FIELD		|clock		|t_time		|'0'	|NOT NULL	|0
FIELD		|value		|t_integer	|'0'	|NOT NULL	|0
INDEX		|1		|serviceid,clock
INDEX		|2		|clock

-- Other tables

TABLE|actions|actionid|ZBX_SYNC
FIELD		|actionid	|t_id		|	|NOT NULL	|0
FIELD		|name		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|eventsource	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|evaltype	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|status		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|esc_period	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|def_shortdata	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|def_longdata	|t_blob		|''	|NOT NULL	|ZBX_SYNC
FIELD		|recovery_msg	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|r_shortdata	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|r_longdata	|t_blob		|''	|NOT NULL	|ZBX_SYNC
INDEX		|1		|eventsource,status

TABLE|operations|operationid|ZBX_SYNC
FIELD		|operationid	|t_id		|	|NOT NULL	|0
FIELD		|actionid	|t_id		|	|NOT NULL	|ZBX_SYNC		|1|actions
FIELD		|operationtype	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|esc_period	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|esc_step_from	|t_integer	|'1'	|NOT NULL	|ZBX_SYNC
FIELD		|esc_step_to	|t_integer	|'1'	|NOT NULL	|ZBX_SYNC
FIELD		|evaltype	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
INDEX		|1		|actionid

TABLE|opmessage|operationid|ZBX_SYNC
FIELD		|operationid	|t_id		|	|NOT NULL	|0			|1|operations
FIELD		|default_msg	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|subject	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|message	|t_text		|''	|NOT NULL	|ZBX_SYNC
FIELD		|mediatypeid	|t_id		|	|NULL		|ZBX_SYNC		|2|media_type	|		|RESTRICT

TABLE|opmessage_grp|opmessage_grpid|ZBX_SYNC
FIELD		|opmessage_grpid|t_id		|	|NOT NULL	|0
FIELD		|operationid	|t_id		|	|NOT NULL	|ZBX_SYNC		|1|operations
FIELD		|usrgrpid	|t_id		|	|NOT NULL	|ZBX_SYNC		|2|usrgrp	|		|RESTRICT
UNIQUE		|1		|operationid,usrgrpid

TABLE|opmessage_usr|opmessage_usrid|ZBX_SYNC
FIELD		|opmessage_usrid|t_id		|	|NOT NULL	|0
FIELD		|operationid	|t_id		|	|NOT NULL	|ZBX_SYNC		|1|operations
FIELD		|userid		|t_id		|	|NOT NULL	|ZBX_SYNC		|2|users	|		|RESTRICT
UNIQUE		|1		|operationid,userid

TABLE|opcommand|operationid|ZBX_SYNC
FIELD		|operationid	|t_id		|	|NOT NULL	|0			|1|operations
FIELD		|type		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|scriptid	|t_id		|	|NULL		|ZBX_SYNC		|2|scripts	|		|RESTRICT
FIELD		|execute_on	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|port		|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|authtype	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|username	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|password	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|publickey	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|privatekey	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|command	|t_text		|''	|NOT NULL	|ZBX_SYNC

TABLE|opcommand_hst|opcommand_hstid|ZBX_SYNC
FIELD		|opcommand_hstid|t_id		|	|NOT NULL	|0
FIELD		|operationid	|t_id		|	|NOT NULL	|ZBX_SYNC		|1|operations
FIELD		|hostid		|t_id		|	|NULL		|ZBX_SYNC		|2|hosts	|		|RESTRICT
INDEX		|1		|operationid

TABLE|opcommand_grp|opcommand_grpid|ZBX_SYNC
FIELD		|opcommand_grpid|t_id		|	|NOT NULL	|0
FIELD		|operationid	|t_id		|	|NOT NULL	|ZBX_SYNC		|1|operations
FIELD		|groupid	|t_id		|	|NOT NULL	|ZBX_SYNC		|2|groups	|		|RESTRICT
INDEX		|1		|operationid

TABLE|opgroup|opgroupid|ZBX_SYNC
FIELD		|opgroupid	|t_id		|	|NOT NULL	|0
FIELD		|operationid	|t_id		|	|NOT NULL	|ZBX_SYNC		|1|operations
FIELD		|groupid	|t_id		|	|NOT NULL	|ZBX_SYNC		|2|groups	|		|RESTRICT
UNIQUE		|1		|operationid,groupid

TABLE|optemplate|optemplateid|ZBX_SYNC
FIELD		|optemplateid	|t_id		|	|NOT NULL	|0
FIELD		|operationid	|t_id		|	|NOT NULL	|ZBX_SYNC		|1|operations
FIELD		|templateid	|t_id		|	|NOT NULL	|ZBX_SYNC		|2|hosts	|hostid		|RESTRICT
UNIQUE		|1		|operationid,templateid

TABLE|opconditions|opconditionid|ZBX_SYNC
FIELD		|opconditionid	|t_id		|	|NOT NULL	|0
FIELD		|operationid	|t_id		|	|NOT NULL	|ZBX_SYNC		|1|operations
FIELD		|conditiontype	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|operator	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|value		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
INDEX		|1		|operationid

TABLE|escalations|escalationid|0
FIELD		|escalationid	|t_id		|	|NOT NULL	|0
FIELD		|actionid	|t_id		|	|NOT NULL	|0			|-|actions
FIELD		|triggerid	|t_id		|	|NULL		|0			|-|triggers
FIELD		|eventid	|t_id		|	|NOT NULL	|0			|-|events
FIELD		|r_eventid	|t_id		|	|NULL		|0			|-|events	|eventid
FIELD		|nextcheck	|t_time		|'0'	|NOT NULL	|0
FIELD		|esc_step	|t_integer	|'0'	|NOT NULL	|0
FIELD		|status		|t_integer	|'0'	|NOT NULL	|0
INDEX		|1		|actionid,triggerid
INDEX		|2		|status,nextcheck

TABLE|applications|applicationid|ZBX_SYNC
FIELD		|applicationid	|t_id		|	|NOT NULL	|0
FIELD		|hostid		|t_id		|	|NOT NULL	|ZBX_SYNC		|1|hosts
FIELD		|name		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|templateid	|t_id		|	|NULL		|ZBX_SYNC		|2|applications	|applicationid
INDEX		|1		|templateid
UNIQUE		|2		|hostid,name

TABLE|conditions|conditionid|ZBX_SYNC
FIELD		|conditionid	|t_id		|	|NOT NULL	|0
FIELD		|actionid	|t_id		|	|NOT NULL	|ZBX_SYNC		|1|actions
FIELD		|conditiontype	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|operator	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|value		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
INDEX		|1		|actionid

TABLE|config|configid|ZBX_SYNC
FIELD		|configid	|t_id		|	|NOT NULL	|0
FIELD		|alert_history	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|event_history	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|refresh_unsupported|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|work_period	|t_varchar(100)	|'1-5,00:00-24:00'|NOT NULL	|ZBX_SYNC
FIELD		|alert_usrgrpid	|t_id		|	|NULL		|ZBX_SYNC		|1|usrgrp	|usrgrpid	|RESTRICT
FIELD		|event_ack_enable|t_integer	|'1'	|NOT NULL	|ZBX_SYNC
FIELD		|event_expire	|t_integer	|'7'	|NOT NULL	|ZBX_SYNC
FIELD		|event_show_max	|t_integer	|'100'	|NOT NULL	|ZBX_SYNC
FIELD		|default_theme	|t_varchar(128)	|'css_ob.css'|NOT NULL	|ZBX_SYNC
FIELD		|authentication_type|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|ldap_host	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|ldap_port	|t_integer	|389	|NOT NULL	|ZBX_SYNC
FIELD		|ldap_base_dn	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|ldap_bind_dn	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|ldap_bind_password|t_varchar(128)|''	|NOT NULL	|ZBX_SYNC
FIELD		|ldap_search_attribute|t_varchar(128)|''|NOT NULL	|ZBX_SYNC
FIELD		|dropdown_first_entry|t_integer	|'1'	|NOT NULL	|ZBX_SYNC
FIELD		|dropdown_first_remember|t_integer|'1'	|NOT NULL	|ZBX_SYNC
FIELD		|discovery_groupid|t_id		|	|NOT NULL	|ZBX_SYNC		|2|groups	|groupid	|RESTRICT
FIELD		|max_in_table	|t_integer	|'50'	|NOT NULL	|ZBX_SYNC
FIELD		|search_limit	|t_integer	|'1000'	|NOT NULL	|ZBX_SYNC
FIELD		|ns_support	|t_integer	|'0'	|NOT NULL	|0
FIELD		|severity_color_0|t_varchar(6)	|'DBDBDB'|NOT NULL	|ZBX_SYNC
FIELD		|severity_color_1|t_varchar(6)	|'D6F6FF'|NOT NULL	|ZBX_SYNC
FIELD		|severity_color_2|t_varchar(6)	|'FFF6A5'|NOT NULL	|ZBX_SYNC
FIELD		|severity_color_3|t_varchar(6)	|'FFB689'|NOT NULL	|ZBX_SYNC
FIELD		|severity_color_4|t_varchar(6)	|'FF9999'|NOT NULL	|ZBX_SYNC
FIELD		|severity_color_5|t_varchar(6)	|'FF3838'|NOT NULL	|ZBX_SYNC
FIELD		|severity_name_0|t_varchar(32)	|'Not classified'|NOT NULL	|ZBX_SYNC
FIELD		|severity_name_1|t_varchar(32)	|'Information'|NOT NULL	|ZBX_SYNC
FIELD		|severity_name_2|t_varchar(32)	|'Warning'|NOT NULL	|ZBX_SYNC
FIELD		|severity_name_3|t_varchar(32)	|'Average'|NOT NULL	|ZBX_SYNC
FIELD		|severity_name_4|t_varchar(32)	|'High'	|NOT NULL	|ZBX_SYNC
FIELD		|severity_name_5|t_varchar(32)	|'Disaster'|NOT NULL	|ZBX_SYNC
FIELD		|ok_period	|t_integer	|'1800'	|NOT NULL	|ZBX_SYNC 
FIELD		|blink_period	|t_integer	|'1800'	|NOT NULL	|ZBX_SYNC 
FIELD		|problem_unack_color|t_varchar(6)|'DC0000'|NOT NULL	|ZBX_SYNC
FIELD		|problem_ack_color|t_varchar(6)	|'DC0000'|NOT NULL	|ZBX_SYNC 
FIELD		|ok_unack_color	|t_varchar(6)	|'00AA00'|NOT NULL	|ZBX_SYNC
FIELD		|ok_ack_color	|t_varchar(6)	|'00AA00'|NOT NULL	|ZBX_SYNC   
FIELD		|problem_unack_style|t_integer	|'0'	|NOT NULL	|ZBX_SYNC 
FIELD		|problem_ack_style|t_integer	|'0'	|NOT NULL	|ZBX_SYNC 
FIELD		|ok_unack_style	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC 
FIELD		|ok_ack_style	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC 
FIELD		|snmptrap_logging|t_integer	|'0'	|NOT NULL	|ZBX_SYNC

TABLE|globalvars|globalvarid|0
FIELD		|globalvarid	|t_id		|	|NOT NULL	|0
FIELD		|snmp_lastsize	|t_integer	|'0'	|NOT NULL	|0

TABLE|functions|functionid|ZBX_SYNC
FIELD		|functionid	|t_id		|	|NOT NULL	|0
FIELD		|itemid		|t_id		|	|NOT NULL	|ZBX_SYNC		|1|items
FIELD		|triggerid	|t_id		|	|NOT NULL	|ZBX_SYNC		|2|triggers
FIELD		|function	|t_varchar(12)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|parameter	|t_varchar(255)	|'0'	|NOT NULL	|ZBX_SYNC
INDEX		|1		|triggerid
INDEX		|2		|itemid,function,parameter

TABLE|graphs|graphid|ZBX_SYNC
FIELD		|graphid	|t_id		|	|NOT NULL	|0
FIELD		|name		|t_varchar(128)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|width		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|height		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|yaxismin	|t_double	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|yaxismax	|t_double	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|templateid	|t_id		|	|NULL		|ZBX_SYNC		|1|graphs	|graphid
FIELD		|show_work_period|t_integer	|'1'	|NOT NULL	|ZBX_SYNC
FIELD		|show_triggers	|t_integer	|'1'	|NOT NULL	|ZBX_SYNC
FIELD		|graphtype	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|show_legend	|t_integer	|'1'	|NOT NULL	|ZBX_SYNC
FIELD		|show_3d	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|percent_left	|t_double	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|percent_right	|t_double	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|ymin_type	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|ymax_type	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|ymin_itemid	|t_id		|	|NULL		|ZBX_SYNC		|2|items	|itemid		|RESTRICT
FIELD		|ymax_itemid	|t_id		|	|NULL		|ZBX_SYNC		|3|items	|itemid		|RESTRICT
FIELD		|flags		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
INDEX		|graphs_1	|name

TABLE|graph_discovery|graphdiscoveryid|ZBX_SYNC
FIELD		|graphdiscoveryid|t_id		|	|NOT NULL	|0
FIELD		|graphid	|t_id		|	|NOT NULL	|ZBX_SYNC		|1|graphs
FIELD		|parent_graphid	|t_id		|	|NOT NULL	|ZBX_SYNC		|2|graphs	|graphid
FIELD		|name		|t_varchar(128)	|''	|NOT NULL	|0
UNIQUE		|1		|graphid,parent_graphid

TABLE|graphs_items|gitemid|ZBX_SYNC
FIELD		|gitemid	|t_id		|	|NOT NULL	|0
FIELD		|graphid	|t_id		|	|NOT NULL	|ZBX_SYNC		|1|graphs
FIELD		|itemid		|t_id		|	|NOT NULL	|ZBX_SYNC		|2|items
FIELD		|drawtype	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|sortorder	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|color		|t_varchar(6)	|'009600'|NOT NULL	|ZBX_SYNC
FIELD		|yaxisside	|t_integer	|'1'	|NOT NULL	|ZBX_SYNC
FIELD		|calc_fnc	|t_integer	|'2'	|NOT NULL	|ZBX_SYNC
FIELD		|type		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|periods_cnt	|t_integer	|'5'	|NOT NULL	|ZBX_SYNC
INDEX		|1		|itemid
INDEX		|2		|graphid

TABLE|graph_theme|graphthemeid|0
FIELD		|graphthemeid	|t_id		|	|NOT NULL	|0
FIELD		|description	|t_varchar(64)	|''	|NOT NULL	|0
FIELD		|theme		|t_varchar(64)	|''	|NOT NULL	|0
FIELD		|backgroundcolor|t_varchar(6)	|'F0F0F0'|NOT NULL	|0
FIELD		|graphcolor	|t_varchar(6)	|'FFFFFF'|NOT NULL	|0
FIELD		|graphbordercolor|t_varchar(6)	|'222222'|NOT NULL	|0
FIELD		|gridcolor	|t_varchar(6)	|'CCCCCC'|NOT NULL	|0
FIELD		|maingridcolor	|t_varchar(6)	|'AAAAAA'|NOT NULL	|0
FIELD		|gridbordercolor|t_varchar(6)	|'000000'|NOT NULL	|0
FIELD		|textcolor	|t_varchar(6)	|'202020'|NOT NULL	|0
FIELD		|highlightcolor	|t_varchar(6)	|'AA4444'|NOT NULL	|0
FIELD		|leftpercentilecolor|t_varchar(6)|'11CC11'|NOT NULL	|0
FIELD		|rightpercentilecolor|t_varchar(6)|'CC1111'|NOT NULL	|0
FIELD		|nonworktimecolor|t_varchar(6)	|'CCCCCC'|NOT NULL	|0
FIELD		|gridview	|t_integer	|1	|NOT NULL	|0
FIELD		|legendview	|t_integer	|1	|NOT NULL	|0
INDEX		|1		|description
INDEX		|2		|theme

TABLE|groups|groupid|ZBX_SYNC
FIELD		|groupid	|t_id		|	|NOT NULL	|0
FIELD		|name		|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|internal	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
INDEX		|1		|name

TABLE|help_items|itemtype,key_|0
FIELD		|itemtype	|t_integer	|'0'	|NOT NULL	|0
FIELD		|key_		|t_varchar(255)	|''	|NOT NULL	|0
FIELD		|description	|t_varchar(255)	|''	|NOT NULL	|0

TABLE|hosts|hostid|ZBX_SYNC
FIELD		|hostid		|t_id		|	|NOT NULL	|0
FIELD		|proxy_hostid	|t_id		|	|NULL		|ZBX_SYNC		|1|hosts	|hostid		|RESTRICT
FIELD		|host		|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|status		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|disable_until	|t_integer	|'0'	|NOT NULL	|0
FIELD		|error		|t_varchar(128)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|available	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|errors_from	|t_integer	|'0'	|NOT NULL	|0
FIELD		|lastaccess	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|ipmi_authtype	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|ipmi_privilege	|t_integer	|'2'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|ipmi_username	|t_varchar(16)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|ipmi_password	|t_varchar(20)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|ipmi_disable_until|t_integer	|'0'	|NOT NULL	|0
FIELD		|ipmi_available	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|snmp_disable_until|t_integer	|'0'	|NOT NULL	|0
FIELD		|snmp_available	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|maintenanceid	|t_id		|	|NULL		|ZBX_SYNC		|2|maintenances	|		|RESTRICT
FIELD		|maintenance_status|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|maintenance_type|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|maintenance_from|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|ipmi_errors_from|t_integer	|'0'	|NOT NULL	|0
FIELD		|snmp_errors_from|t_integer	|'0'	|NOT NULL	|0
FIELD		|ipmi_error	|t_varchar(128)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|snmp_error	|t_varchar(128)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|jmx_disable_until|t_integer	|'0'	|NOT NULL	|0
FIELD		|jmx_available	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|jmx_errors_from|t_integer	|'0'	|NOT NULL	|0
FIELD		|jmx_error	|t_varchar(128)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|name		|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
INDEX		|1		|host
INDEX		|2		|status
INDEX		|3		|proxy_hostid
INDEX		|4		|name

TABLE|interface|interfaceid|ZBX_SYNC
FIELD		|interfaceid	|t_id		|	|NOT NULL	|0
FIELD		|hostid		|t_id		|	|NOT NULL	|ZBX_SYNC,ZBX_PROXY	|1|hosts
FIELD		|main		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|type		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|useip		|t_integer	|'1'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|ip		|t_varchar(39)	|'127.0.0.1'|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|dns		|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|port		|t_varchar(64)	|'10050'|NOT NULL	|ZBX_SYNC,ZBX_PROXY
INDEX		|1		|hostid,type
INDEX		|2		|ip,dns

TABLE|globalmacro|globalmacroid|ZBX_SYNC
FIELD		|globalmacroid	|t_id		|	|NOT NULL	|0
FIELD		|macro		|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|value		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
INDEX		|1		|macro

TABLE|hostmacro|hostmacroid|ZBX_SYNC
FIELD		|hostmacroid	|t_id		|	|NOT NULL	|0
FIELD		|hostid		|t_id		|	|NOT NULL	|ZBX_SYNC,ZBX_PROXY	|1|hosts
FIELD		|macro		|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|value		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
UNIQUE		|1		|hostid,macro

TABLE|hosts_groups|hostgroupid|ZBX_SYNC
FIELD		|hostgroupid	|t_id		|	|NOT NULL	|0
FIELD		|hostid		|t_id		|	|NOT NULL	|ZBX_SYNC		|1|hosts
FIELD		|groupid	|t_id		|	|NOT NULL	|ZBX_SYNC		|2|groups
UNIQUE		|1		|hostid,groupid
INDEX		|2		|groupid

TABLE|host_profile|hostid|ZBX_SYNC
FIELD		|hostid		|t_id		|	|NOT NULL	|0			|1|hosts
FIELD		|profile_mode	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|type		|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|type_full	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|name		|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|alias		|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|os		|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|os_full	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|os_short	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|serialno_a	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|serialno_b	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|tag		|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|asset_tag	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|macaddress_a	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|macaddress_b	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|hardware	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|hardware_full	|t_text		|''	|NOT NULL	|ZBX_SYNC
FIELD		|software	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|software_full	|t_text		|''	|NOT NULL	|ZBX_SYNC
FIELD		|software_app_a	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|software_app_b	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|software_app_c	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|software_app_d	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|software_app_e	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|contact	|t_text		|''	|NOT NULL	|ZBX_SYNC
FIELD		|location	|t_text		|''	|NOT NULL	|ZBX_SYNC
FIELD		|location_lat	|t_varchar(16)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|location_lon	|t_varchar(16)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|notes		|t_text		|''	|NOT NULL	|ZBX_SYNC
FIELD		|chassis	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|model		|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|hw_arch	|t_varchar(32)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|vendor		|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|contract_number|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|installer_name	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|deployment_status|t_varchar(64)|''	|NOT NULL	|ZBX_SYNC
FIELD		|url_a		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|url_b		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|url_c		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|host_networks	|t_text		|''	|NOT NULL	|ZBX_SYNC
FIELD		|host_netmask	|t_varchar(39)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|host_router	|t_varchar(39)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|oob_ip		|t_varchar(39)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|oob_netmask	|t_varchar(39)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|oob_router	|t_varchar(39)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|date_hw_purchase|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|date_hw_install|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|date_hw_expiry	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|date_hw_decomm	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|site_address_a	|t_varchar(128)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|site_address_b	|t_varchar(128)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|site_address_c	|t_varchar(128)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|site_city	|t_varchar(128)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|site_state	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|site_country	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|site_zip	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|site_rack	|t_varchar(128)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|site_notes	|t_text		|''	|NOT NULL	|ZBX_SYNC
FIELD		|poc_1_name	|t_varchar(128)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|poc_1_email	|t_varchar(128)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|poc_1_phone_a	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|poc_1_phone_b	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|poc_1_cell	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|poc_1_screen	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|poc_1_notes	|t_text		|''	|NOT NULL	|ZBX_SYNC
FIELD		|poc_2_name	|t_varchar(128)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|poc_2_email	|t_varchar(128)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|poc_2_phone_a	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|poc_2_phone_b	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|poc_2_cell	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|poc_2_screen	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|poc_2_notes	|t_text		|''	|NOT NULL	|ZBX_SYNC

TABLE|hosts_templates|hosttemplateid|ZBX_SYNC
FIELD		|hosttemplateid	|t_id		|	|NOT NULL	|0
FIELD		|hostid		|t_id		|	|NOT NULL	|ZBX_SYNC,ZBX_PROXY	|1|hosts
FIELD		|templateid	|t_id		|	|NOT NULL	|ZBX_SYNC,ZBX_PROXY	|2|hosts	|hostid
UNIQUE		|1		|hostid,templateid
INDEX		|2		|templateid

TABLE|housekeeper|housekeeperid|0
FIELD		|housekeeperid	|t_id		|	|NOT NULL	|0
FIELD		|tablename	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|field		|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|value		|t_id		|	|NOT NULL	|ZBX_SYNC		|-|items

TABLE|images|imageid|ZBX_SYNC
FIELD		|imageid	|t_id		|	|NOT NULL	|0
FIELD		|imagetype	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|name		|t_varchar(64)	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|image		|t_image	|''	|NOT NULL	|ZBX_SYNC
INDEX		|1		|imagetype,name

TABLE|items|itemid|ZBX_SYNC
FIELD		|itemid		|t_id		|	|NOT NULL	|0
FIELD		|type		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|snmp_community	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|snmp_oid	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|hostid		|t_id		|	|NOT NULL	|ZBX_SYNC,ZBX_PROXY	|1|hosts
FIELD		|name		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|key_		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|delay		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|history	|t_integer	|'90'	|NOT NULL	|ZBX_SYNC
FIELD		|trends		|t_integer	|'365'	|NOT NULL	|ZBX_SYNC
FIELD		|lastvalue	|t_varchar(255)	|	|NULL		|0
FIELD		|lastclock	|t_time		|	|NULL		|0
FIELD		|prevvalue	|t_varchar(255)	|	|NULL		|0
FIELD		|status		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|value_type	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|trapper_hosts	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|units		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|multiplier	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|delta		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|prevorgvalue	|t_varchar(255)	|	|NULL		|0
FIELD		|snmpv3_securityname|t_varchar(64)|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|snmpv3_securitylevel|t_integer	|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|snmpv3_authpassphrase|t_varchar(64)|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|snmpv3_privpassphrase|t_varchar(64)|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|formula	|t_varchar(255)	|'1'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|error		|t_varchar(128)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|lastlogsize	|t_integer	|'0'	|NOT NULL	|0
FIELD		|logtimefmt	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|templateid	|t_id		|	|NULL		|ZBX_SYNC		|2|items	|itemid
FIELD		|valuemapid	|t_id		|	|NULL		|ZBX_SYNC		|3|valuemaps	|		|RESTRICT
FIELD		|delay_flex	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|params		|t_item_param	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|ipmi_sensor	|t_varchar(128)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|data_type	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|authtype	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|username	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|password	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|publickey	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|privatekey	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|mtime		|t_integer	|'0'	|NOT NULL	|0
FIELD		|lastns		|t_nanosec	|	|NULL		|0
FIELD		|flags		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|filter		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|interfaceid	|t_id		|	|NULL		|ZBX_SYNC,ZBX_PROXY	|4|interface	|		|RESTRICT
FIELD		|port		|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|description	|t_text		|''	|NOT NULL	|ZBX_SYNC
FIELD		|profile_link	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
UNIQUE		|1		|hostid,key_
INDEX		|3		|status
INDEX		|4		|templateid
INDEX		|5		|valuemapid

TABLE|item_discovery|itemdiscoveryid|ZBX_SYNC
FIELD		|itemdiscoveryid|t_id		|	|NOT NULL	|0
FIELD		|itemid		|t_id		|	|NOT NULL	|ZBX_SYNC		|1|items
FIELD		|parent_itemid	|t_id		|	|NOT NULL	|ZBX_SYNC		|2|items	|itemid
FIELD		|key_		|t_varchar(255)	|''	|NOT NULL	|0
UNIQUE		|1		|itemid,parent_itemid

TABLE|items_applications|itemappid|ZBX_SYNC
FIELD		|itemappid	|t_id		|	|NOT NULL	|0
FIELD		|applicationid	|t_id		|	|NOT NULL	|ZBX_SYNC		|1|applications
FIELD		|itemid		|t_id		|	|NOT NULL	|ZBX_SYNC		|2|items
UNIQUE		|1		|applicationid,itemid
INDEX		|2		|itemid

TABLE|mappings|mappingid|ZBX_SYNC
FIELD		|mappingid	|t_id		|	|NOT NULL	|0
FIELD		|valuemapid	|t_id		|	|NOT NULL	|ZBX_SYNC		|1|valuemaps
FIELD		|value		|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|newvalue	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
INDEX		|1		|valuemapid

TABLE|media|mediaid|ZBX_SYNC
FIELD		|mediaid	|t_id		|	|NOT NULL	|0
FIELD		|userid		|t_id		|	|NOT NULL	|ZBX_SYNC		|1|users
FIELD		|mediatypeid	|t_id		|	|NOT NULL	|ZBX_SYNC		|2|media_type
FIELD		|sendto		|t_varchar(100)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|active		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|severity	|t_integer	|'63'	|NOT NULL	|ZBX_SYNC
FIELD		|period		|t_varchar(100)	|'1-7,00:00-24:00'|NOT NULL	|ZBX_SYNC
INDEX		|1		|userid
INDEX		|2		|mediatypeid

TABLE|media_type|mediatypeid|ZBX_SYNC
FIELD		|mediatypeid	|t_id		|	|NOT NULL	|0
FIELD		|type		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|description	|t_varchar(100)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|smtp_server	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|smtp_helo	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|smtp_email	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|exec_path	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|gsm_modem	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|username	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|passwd		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC

TABLE|profiles|profileid|0
FIELD		|profileid	|t_id		|	|NOT NULL	|0
FIELD		|userid		|t_id		|	|NOT NULL	|0			|1|users
FIELD		|idx		|t_varchar(96)	|''	|NOT NULL	|0
FIELD		|idx2		|t_id		|'0'	|NOT NULL	|0
FIELD		|value_id	|t_id		|'0'	|NOT NULL	|0
FIELD		|value_int	|t_integer	|'0'	|NOT NULL	|0
FIELD		|value_str	|t_varchar(255)	|''	|NOT NULL	|0
FIELD		|source		|t_varchar(96)	|''	|NOT NULL	|0
FIELD		|type		|t_integer	|'0'	|NOT NULL	|0
INDEX		|1		|userid,idx,idx2
INDEX		|2		|userid,profileid

TABLE|rights|rightid|ZBX_SYNC
FIELD		|rightid	|t_id		|	|NOT NULL	|0
FIELD		|groupid	|t_id		|	|NOT NULL	|ZBX_SYNC		|1|usrgrp	|usrgrpid
FIELD		|permission	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|id		|t_id		|	|NOT NULL	|ZBX_SYNC		|2|groups	|groupid
INDEX		|1		|groupid
INDEX		|2		|id

TABLE|scripts|scriptid|ZBX_SYNC
FIELD		|scriptid	|t_id		|	|NOT NULL	|0
FIELD		|name		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|command	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|host_access	|t_integer	|'2'	|NOT NULL	|ZBX_SYNC
FIELD		|usrgrpid	|t_id		|	|NULL		|ZBX_SYNC		|1|usrgrp	|		|RESTRICT
FIELD		|groupid	|t_id		|	|NULL		|ZBX_SYNC		|2|groups	|		|RESTRICT
FIELD		|description	|t_text		|''	|NOT NULL	|ZBX_SYNC
FIELD		|confirmation	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|type		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|execute_on	|t_integer	|'1'	|NOT NULL	|ZBX_SYNC

TABLE|screens|screenid|ZBX_SYNC
FIELD		|screenid	|t_id		|	|NOT NULL	|0
FIELD		|name		|t_varchar(255)	|	|NOT NULL	|ZBX_SYNC
FIELD		|hsize		|t_integer	|'1'	|NOT NULL	|ZBX_SYNC
FIELD		|vsize		|t_integer	|'1'	|NOT NULL	|ZBX_SYNC
FIELD		|templateid	|t_id		|	|NULL		|ZBX_SYNC		|1|hosts	|hostid

TABLE|screens_items|screenitemid|ZBX_SYNC
FIELD		|screenitemid	|t_id		|	|NOT NULL	|0
FIELD		|screenid	|t_id		|	|NOT NULL	|ZBX_SYNC		|1|screens
FIELD		|resourcetype	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|resourceid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|width		|t_integer	|'320'	|NOT NULL	|ZBX_SYNC
FIELD		|height		|t_integer	|'200'	|NOT NULL	|ZBX_SYNC
FIELD		|x		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|y		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|colspan	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|rowspan	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|elements	|t_integer	|'25'	|NOT NULL	|ZBX_SYNC
FIELD		|valign		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|halign		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|style		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|url		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|dynamic	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|sort_triggers	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC

TABLE|services|serviceid|ZBX_SYNC
FIELD		|serviceid	|t_id		|	|NOT NULL	|0
FIELD		|name		|t_varchar(128)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|status		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|algorithm	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|triggerid	|t_id		|	|NULL		|ZBX_SYNC		|1|triggers
FIELD		|showsla	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|goodsla	|t_double	|'99.9'	|NOT NULL	|ZBX_SYNC
FIELD		|sortorder	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
INDEX		|1		|triggerid

TABLE|services_links|linkid|ZBX_SYNC
FIELD		|linkid		|t_id		|	|NOT NULL	|0
FIELD		|serviceupid	|t_id		|	|NOT NULL	|ZBX_SYNC		|1|services	|serviceid
FIELD		|servicedownid	|t_id		|	|NOT NULL	|ZBX_SYNC		|2|services	|serviceid
FIELD		|soft		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
INDEX		|links_1	|servicedownid
UNIQUE		|links_2	|serviceupid,servicedownid

TABLE|sessions|sessionid|0
FIELD		|sessionid	|t_varchar(32)	|''	|NOT NULL	|0
FIELD		|userid		|t_id		|	|NOT NULL	|0			|1|users
FIELD		|lastaccess	|t_integer	|'0'	|NOT NULL	|0
FIELD		|status		|t_integer	|'0'	|NOT NULL	|0
INDEX		|1		|userid,status

TABLE|sysmaps_links|linkid|ZBX_SYNC
FIELD		|linkid		|t_id		|	|NOT NULL	|0
FIELD		|sysmapid	|t_id		|	|NOT NULL	|ZBX_SYNC		|1|sysmaps
FIELD		|selementid1	|t_id		|	|NOT NULL	|ZBX_SYNC		|2|sysmaps_elements|selementid
FIELD		|selementid2	|t_id		|	|NOT NULL	|ZBX_SYNC		|3|sysmaps_elements|selementid
FIELD		|drawtype	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|color		|t_varchar(6)	|'000000'|NOT NULL	|ZBX_SYNC
FIELD		|label		|t_varchar(255)|''	|NOT NULL	|ZBX_SYNC

TABLE|sysmaps_link_triggers|linktriggerid|ZBX_SYNC
FIELD		|linktriggerid	|t_id		|	|NOT NULL	|0
FIELD		|linkid		|t_id		|	|NOT NULL	|ZBX_SYNC		|1|sysmaps_links
FIELD		|triggerid	|t_id		|	|NOT NULL	|ZBX_SYNC		|2|triggers
FIELD		|drawtype	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|color		|t_varchar(6)	|'000000'|NOT NULL	|ZBX_SYNC
UNIQUE		|1		|linkid,triggerid

TABLE|sysmaps_elements|selementid|ZBX_SYNC
FIELD		|selementid	|t_id		|	|NOT NULL	|0
FIELD		|sysmapid	|t_id		|	|NOT NULL	|ZBX_SYNC		|1|sysmaps
FIELD		|elementid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|elementtype	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|iconid_off	|t_id		|	|NULL		|ZBX_SYNC		|2|images	|imageid	|RESTRICT
FIELD		|iconid_on	|t_id		|	|NULL		|ZBX_SYNC		|3|images	|imageid	|RESTRICT
FIELD		|label		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|label_location	|t_integer	|	|NULL		|ZBX_SYNC
FIELD		|x		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|y		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|iconid_disabled|t_id		|	|NULL		|ZBX_SYNC		|4|images	|imageid	|RESTRICT
FIELD		|iconid_maintenance|t_id	|	|NULL		|ZBX_SYNC		|5|images	|imageid	|RESTRICT

TABLE|sysmap_element_url|sysmapelementurlid|ZBX_SYNC
FIELD		|sysmapelementurlid|t_id	|	|NOT NULL	|0
FIELD		|selementid	|t_id		|	|NOT NULL	|ZBX_SYNC		|1		|sysmaps_elements
FIELD		|name		|t_varchar(255)	|	|NOT NULL	|ZBX_SYNC
FIELD		|url		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
UNIQUE		|1		|selementid,name

TABLE|sysmaps|sysmapid|ZBX_SYNC
FIELD		|sysmapid	|t_id		|	|NOT NULL	|0
FIELD		|name		|t_varchar(128)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|width		|t_integer	|'600'	|NOT NULL	|ZBX_SYNC
FIELD		|height		|t_integer	|'400'	|NOT NULL	|ZBX_SYNC
FIELD		|backgroundid	|t_id		|	|NULL		|ZBX_SYNC		|1|images	|imageid	|RESTRICT
FIELD		|label_type	|t_integer	|'2'	|NOT NULL	|ZBX_SYNC
FIELD		|label_location	|t_integer	|'3'	|NOT NULL	|ZBX_SYNC
FIELD		|highlight	|t_integer	|'1'	|NOT NULL	|ZBX_SYNC
FIELD		|expandproblem	|t_integer	|'1'	|NOT NULL	|ZBX_SYNC
FIELD		|markelements	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|show_unack	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|grid_size	|t_integer	|'50'	|NOT NULL	|ZBX_SYNC
FIELD		|grid_show	|t_integer	|'1'	|NOT NULL	|ZBX_SYNC
FIELD		|grid_align	|t_integer	|'1'	|NOT NULL	|ZBX_SYNC
FIELD		|label_format	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|label_type_host|t_integer	|'2'	|NOT NULL	|ZBX_SYNC
FIELD		|label_type_hostgroup|t_integer	|'2'	|NOT NULL	|ZBX_SYNC
FIELD		|label_type_trigger|t_integer	|'2'	|NOT NULL	|ZBX_SYNC
FIELD		|label_type_map|t_integer	|'2'	|NOT NULL	|ZBX_SYNC
FIELD		|label_type_image|t_integer	|'2'	|NOT NULL	|ZBX_SYNC
FIELD		|label_string_host|t_varchar(255)|''	|NOT NULL	|ZBX_SYNC
FIELD		|label_string_hostgroup|t_varchar(255)|''|NOT NULL	|ZBX_SYNC
FIELD		|label_string_trigger|t_varchar(255)|''	|NOT NULL	|ZBX_SYNC
FIELD		|label_string_map|t_varchar(255)|''	|NOT NULL	|ZBX_SYNC
FIELD		|label_string_image|t_varchar(255)|''	|NOT NULL	|ZBX_SYNC
INDEX		|1		|name

TABLE|sysmap_url|sysmapurlid|ZBX_SYNC
FIELD		|sysmapurlid	|t_id		|	|NOT NULL	|0
FIELD		|sysmapid	|t_id		|	|NOT NULL	|ZBX_SYNC		|1|sysmaps
FIELD		|name		|t_varchar(255)	|	|NOT NULL	|ZBX_SYNC
FIELD		|url		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|elementtype	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
UNIQUE		|1		|sysmapid,name

TABLE|triggers|triggerid|ZBX_SYNC
FIELD		|triggerid	|t_id		|	|NOT NULL	|0
FIELD		|expression	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|description	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|url		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|status		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|value		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|priority	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|lastchange	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|comments	|t_blob		|''	|NOT NULL	|ZBX_SYNC
FIELD		|error		|t_varchar(128)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|templateid	|t_id		|	|NULL		|ZBX_SYNC		|1|triggers	|triggerid
FIELD		|type		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|value_flags	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|flags		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
INDEX		|1		|status
INDEX		|2		|value

TABLE|trigger_discovery|triggerdiscoveryid|ZBX_SYNC
FIELD		|triggerdiscoveryid|t_id	|	|NOT NULL	|0
FIELD		|triggerid	|t_id		|	|NOT NULL	|ZBX_SYNC		|1|triggers
FIELD		|parent_triggerid|t_id		|	|NOT NULL	|ZBX_SYNC		|2|triggers	|triggerid
FIELD		|name		|t_varchar(255)	|''	|NOT NULL	|0
UNIQUE		|1		|triggerid,parent_triggerid

TABLE|trigger_depends|triggerdepid|ZBX_SYNC
FIELD		|triggerdepid	|t_id		|	|NOT NULL	|0
FIELD		|triggerid_down	|t_id		|	|NOT NULL	|ZBX_SYNC		|1|triggers	|triggerid
FIELD		|triggerid_up	|t_id		|	|NOT NULL	|ZBX_SYNC		|2|triggers	|triggerid
UNIQUE		|1		|triggerid_down,triggerid_up
INDEX		|2		|triggerid_up

TABLE|users|userid|ZBX_SYNC
FIELD		|userid		|t_id		|	|NOT NULL	|0
FIELD		|alias		|t_varchar(100)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|name		|t_varchar(100)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|surname	|t_varchar(100)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|passwd		|t_char(32)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|url		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|autologin	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|autologout	|t_integer	|'900'	|NOT NULL	|ZBX_SYNC
FIELD		|lang		|t_varchar(5)	|'en_GB'|NOT NULL	|ZBX_SYNC
FIELD		|refresh	|t_integer	|'30'	|NOT NULL	|ZBX_SYNC
FIELD		|type		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|theme		|t_varchar(128)	|'default.css'|NOT NULL	|ZBX_SYNC
FIELD		|attempt_failed	|t_integer	|0	|NOT NULL	|ZBX_SYNC
FIELD		|attempt_ip	|t_varchar(39)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|attempt_clock	|t_integer	|0	|NOT NULL	|ZBX_SYNC
FIELD		|rows_per_page	|t_integer	|50	|NOT NULL	|ZBX_SYNC
INDEX		|1		|alias

TABLE|usrgrp|usrgrpid|ZBX_SYNC
FIELD		|usrgrpid	|t_id		|	|NOT NULL	|0
FIELD		|name		|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|gui_access	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|users_status	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|debug_mode	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
INDEX		|1		|name

TABLE|users_groups|id|ZBX_SYNC
FIELD		|id		|t_id		|	|NOT NULL	|0
FIELD		|usrgrpid	|t_id		|	|NOT NULL	|ZBX_SYNC		|1|usrgrp
FIELD		|userid		|t_id		|	|NOT NULL	|ZBX_SYNC		|2|users
UNIQUE		|1		|usrgrpid,userid

TABLE|valuemaps|valuemapid|ZBX_SYNC
FIELD		|valuemapid	|t_id		|	|NOT NULL	|0
FIELD		|name		|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
INDEX		|1		|name

TABLE|maintenances|maintenanceid|ZBX_SYNC
FIELD		|maintenanceid	|t_id		|	|NOT NULL	|0
FIELD		|name		|t_varchar(128)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|maintenance_type|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|description	|t_blob		|''	|NOT NULL	|ZBX_SYNC
FIELD		|active_since	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|active_till	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
INDEX		|1		|active_since,active_till

TABLE|maintenances_hosts|maintenance_hostid|ZBX_SYNC
FIELD		|maintenance_hostid|t_id	|	|NOT NULL	|0
FIELD		|maintenanceid	|t_id		|	|NOT NULL	|ZBX_SYNC		|1|maintenances
FIELD		|hostid		|t_id		|	|NOT NULL	|ZBX_SYNC		|2|hosts
UNIQUE		|1		|maintenanceid,hostid

TABLE|maintenances_groups|maintenance_groupid|ZBX_SYNC
FIELD		|maintenance_groupid|t_id	|	|NOT NULL	|0
FIELD		|maintenanceid	|t_id		|	|NOT NULL	|ZBX_SYNC		|1|maintenances
FIELD		|groupid	|t_id		|	|NOT NULL	|ZBX_SYNC		|2|groups
UNIQUE		|1		|maintenanceid,groupid

TABLE|maintenances_windows|maintenance_timeperiodid|ZBX_SYNC
FIELD		|maintenance_timeperiodid|t_id	|	|NOT NULL	|0
FIELD		|maintenanceid	|t_id		|	|NOT NULL	|ZBX_SYNC		|1|maintenances
FIELD		|timeperiodid	|t_id		|	|NOT NULL	|ZBX_SYNC		|2|timeperiods
UNIQUE		|1		|maintenanceid,timeperiodid

TABLE|timeperiods|timeperiodid|ZBX_SYNC
FIELD		|timeperiodid	|t_id		|	|NOT NULL	|0
FIELD		|timeperiod_type|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|every		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|month		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|dayofweek	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|day		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|start_time	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|period		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|start_date	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC

TABLE|regexps|regexpid|ZBX_SYNC
FIELD		|regexpid	|t_id		|	|NOT NULL	|0
FIELD		|name		|t_varchar(128)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|test_string	|t_blob		|''	|NOT NULL	|ZBX_SYNC
INDEX		|1		|name

TABLE|user_history|userhistoryid|0
FIELD		|userhistoryid	|t_id		|	|NOT NULL	|0
FIELD		|userid		|t_id		|	|NOT NULL	|0			|1|users
FIELD		|title1		|t_varchar(255)	|''	|NOT NULL	|0
FIELD		|url1		|t_varchar(255)	|''	|NOT NULL	|0
FIELD		|title2		|t_varchar(255)	|''	|NOT NULL	|0
FIELD		|url2		|t_varchar(255)	|''	|NOT NULL	|0
FIELD		|title3		|t_varchar(255)	|''	|NOT NULL	|0
FIELD		|url3		|t_varchar(255)	|''	|NOT NULL	|0
FIELD		|title4		|t_varchar(255)	|''	|NOT NULL	|0
FIELD		|url4		|t_varchar(255)	|''	|NOT NULL	|0
FIELD		|title5		|t_varchar(255)	|''	|NOT NULL	|0
FIELD		|url5		|t_varchar(255)	|''	|NOT NULL	|0
UNIQUE		|1		|userid

TABLE|expressions|expressionid|ZBX_SYNC
FIELD		|expressionid	|t_id		|	|NOT NULL	|0
FIELD		|regexpid	|t_id		|	|NOT NULL	|ZBX_SYNC,ZBX_PROXY	|1|regexps
FIELD		|expression	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|expression_type|t_integer	|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|exp_delimiter	|t_varchar(1)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|case_sensitive	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
INDEX		|1		|regexpid

TABLE|autoreg_host|autoreg_hostid|ZBX_SYNC
FIELD		|autoreg_hostid	|t_id		|	|NOT NULL	|0
FIELD		|proxy_hostid	|t_id		|	|NULL		|ZBX_SYNC		|1|hosts		|hostid
FIELD		|host		|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|listen_ip	|t_varchar(39)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|listen_port	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|listen_dns	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
INDEX		|1		|proxy_hostid,host

TABLE|proxy_autoreg_host|id|0
FIELD		|id		|t_serial	|	|NOT NULL	|0
FIELD		|clock		|t_time		|'0'	|NOT NULL	|0
FIELD		|host		|t_varchar(64)	|''	|NOT NULL	|0
FIELD		|listen_ip	|t_varchar(39)	|''	|NOT NULL	|0
FIELD		|listen_port	|t_integer	|'0'	|NOT NULL	|0
FIELD		|listen_dns	|t_varchar(64)	|''	|NOT NULL	|0
INDEX		|1		|clock
