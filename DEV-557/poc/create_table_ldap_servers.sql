CREATE TABLE ldap_servers (
	server_id	    bigint				      NOT NULL,
	status		    integer	    DEFAULT 0		      NOT NULL,
	display_name	    varchar(255)    DEFAULT ''		      NOT NULL,
	host		    varchar(255)    DEFAULT ''		      NOT NULL,
	port		    integer	    DEFAULT 389		      NOT NULL,
	bind_dn		    varchar(255)    DEFAULT ''		      NOT NULL,
	bind_pw		    varchar(128)    DEFAULT ''		      NOT NULL,
	use_tls		    integer	    DEFAULT 0		      NOT NULL,
	net_timeout	    integer	    DEFAULT 10		      NOT NULL,
	proc_timeout	    integer	    DEFAULT 10		      NOT NULL,
	PRIMARY KEY (server_id)
);
CREATE UNIQUE INDEX ldap_servers_1 ON ldap_servers (host,port);
