if AGENT
AGENT_tests = SYSTEM_CPU_SWITCHES
endif

noinst_PROGRAMS = $(AGENT_tests)

if AGENT
SYSTEM_CPU_SWITCHES_SOURCES = \
	SYSTEM_CPU_SWITCHES.c \
	../../../../src/zabbix_agent/active.c ./../../../src/zabbix_agent/active.h \
	../../../../src/zabbix_agent/stats.c ./../../../src/zabbix_agent/stats.h \
	../../../../src/zabbix_agent/cpustat.c ./../../../src/zabbix_agent/cpustat.h \
	../../../../src/zabbix_agent/diskdevices.c ./../../../src/zabbix_agent/diskdevices.h \
	../../../../src/zabbix_agent/vmstats.c ./../../../src/zabbix_agent/vmstats.h \
	../../../../src/zabbix_agent/logfiles.c ./../../../src/zabbix_agent/logfiles.h \
	../../../../src/zabbix_agent/zbxconf.c ./../../../src/zabbix_agent/zbxconf.h \
	../../../../src/zabbix_agent/listener.c ./../../../src/zabbix_agent/listener.h \
	../../../../src/zabbix_agent/procstat.c ./../../../src/zabbix_agent/procstat.h \
	../../../../zbxmocktest.h

SYSTEM_CPU_SWITCHES_LDADD = \
	$(top_srcdir)/src/libs/zbxsysinfo/libzbxagentsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/$(ARCH)/libspecsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/$(ARCH)/libspechostnamesysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/agent/libagentsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxsys/libzbxsys.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxexec/libzbxexec.a \
	$(top_srcdir)/src/libs/zbxmodules/libzbxmodules.a \
	$(top_srcdir)/src/libs/zbxself/libzbxself.a \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/tests/libzbxmockdata.a

SYSTEM_CPU_SWITCHES_LDADD += @AGENT_LIBS@

SYSTEM_CPU_SWITCHES_CFLAGS = -DZABBIX_DAEMON -I@top_srcdir@/tests
endif
