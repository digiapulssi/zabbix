## Process this file with automake to produce Makefile.in

sbin_PROGRAMS = t_rsm_dns t_rsm_rdds

t_rsm_dns_SOURCES = t_rsm_dns.c t_rsm.h
t_rsm_rdds_SOURCES = t_rsm_rdds.c t_rsm.h

COMMON_OBJS = \
	$(top_srcdir)/src/zabbix_server/poller/libzbxpoller.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxdbcache/libzbxdbcache.a \
	$(top_srcdir)/src/libs/zbxsysinfo/libzbxserversysinfo.a \
	$(top_srcdir)/src/libs/zbxserver/libzbxserver.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxdbhigh/libzbxdbhigh.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxrsm/libzbxrsm.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a\
	$(top_srcdir)/src/libs/zbxsys/libzbxsys.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxmemory/libzbxmemory.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxdb/libzbxdb.a \
	$(top_srcdir)/src/libs/zbxexec/libzbxexec.a \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_srcdir)/src/zabbix_server/zabbix_server-events.o \
	$(top_srcdir)/src/zabbix_server/zabbix_server-actions.o \
	$(top_srcdir)/src/zabbix_server/zabbix_server-operations.o

t_rsm_dns_LDADD = $(COMMON_OBJS) @SERVER_LIBS@
t_rsm_dns_LDFLAGS = @SERVER_LDFLAGS@

t_rsm_rdds_LDADD = $(COMMON_OBJS) @SERVER_LIBS@
t_rsm_rdds_LDFLAGS = @SERVER_LDFLAGS@
