CFLAGS = -I../../include
TARGETS = log macros poc

all: $(TARGETS)

log:
	objcopy ../../src/libs/zbxlog/libzbxlog.a libmockzbxlog.a \
		--redefine-sym __zbx_zabbix_log=mocked__zbx_zabbix_log

%.o: %.c
	$(CC) $(CFLAGS) -c $< -o $@

macros: macros.o unresolved.o
	objcopy ../../src/libs/zbxdbcache/libzbxdbcache.a libmockmacroszbxdbcache.a \
		--redefine-sym DCget_user_macro=mocked_DCget_user_macro \
		--redefine-sym DCconfig_get_hosts_by_itemids=mocked_DCconfig_get_hosts_by_itemids \
		--redefine-sym DCconfig_get_functions_by_functionids=mocked_DCconfig_get_functions_by_functionids \
		--redefine-sym DCconfig_clean_functions=mocked_DCconfig_clean_functions \
		--redefine-sym zbx_vc_get_value=mocked_zbx_vc_get_value
	objcopy ../../src/libs/zbxdb/libzbxdb.a libmockmacroszbxdb.a \
		--redefine-sym zbx_db_vselect=mocked_zbx_db_vselect \
		--redefine-sym zbx_db_fetch=mocked_zbx_db_fetch \
		--redefine-sym DBfree_result=mocked_DBfree_result
	$(CC) $(LDFLAGS) -o $@ $^ \
		../../src/libs/zbxsysinfo/libzbxserversysinfo.a \
		../../src/libs/zbxsysinfo/common/libcommonsysinfo.a \
		../../src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
		libmockmacroszbxdbcache.a \
		../../src/libs/zbxserver/libzbxserver.a \
		../../src/libs/zbxmemory/libzbxmemory.a \
		../../src/libs/zbxregexp/libzbxregexp.a \
		../../src/libs/zbxself/libzbxself.a \
		../../src/libs/zbxnix/libzbxnix.a \
		../../src/libs/zbxalgo/libzbxalgo.a \
		../../src/libs/zbxsys/libzbxsys.a \
		../../src/libs/zbxconf/libzbxconf.a \
		../../src/libs/zbxmedia/libzbxmedia.a \
		../../src/libs/zbxcommon/libzbxcommon.a \
		../../src/libs/zbxcrypto/libzbxcrypto.a \
		../../src/libs/zbxcomms/libzbxcomms.a \
		../../src/libs/zbxcommshigh/libzbxcommshigh.a \
		../../src/libs/zbxjson/libzbxjson.a \
		../../src/libs/zbxhttp/libzbxhttp.a \
		../../src/libs/zbxexec/libzbxexec.a \
		../../src/libs/zbxicmpping/libzbxicmpping.a \
		../../src/libs/zbxdbupgrade/libzbxdbupgrade.a \
		../../src/libs/zbxdbhigh/libzbxdbhigh.a \
		libmockmacroszbxdb.a \
		../../src/libs/zbxmodules/libzbxmodules.a \
		../../src/libs/zbxtasks/libzbxtasks.a \
		../../src/libs/zbxalgo/libzbxalgo.a \
		../../src/libs/zbxcommon/libzbxcommon.a \
		../../src/libs/zbxsysinfo/libzbxserversysinfo.a \
		../../src/libs/zbxsysinfo/common/libcommonsysinfo.a \
		../../src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
		libmockzbxlog.a \
		../../src/libs/zbxmemory/libzbxmemory.a \
		../../src/libs/zbxregexp/libzbxregexp.a \
		../../src/libs/zbxself/libzbxself.a \
		../../src/libs/zbxnix/libzbxnix.a \
		../../src/libs/zbxalgo/libzbxalgo.a \
		../../src/libs/zbxsys/libzbxsys.a \
		../../src/libs/zbxconf/libzbxconf.a \
		../../src/libs/zbxmedia/libzbxmedia.a \
		../../src/libs/zbxcommon/libzbxcommon.a \
		../../src/libs/zbxcrypto/libzbxcrypto.a \
		../../src/libs/zbxcomms/libzbxcomms.a \
		../../src/libs/zbxcommshigh/libzbxcommshigh.a \
		../../src/libs/zbxjson/libzbxjson.a \
		../../src/libs/zbxhttp/libzbxhttp.a \
		../../src/libs/zbxexec/libzbxexec.a \
		../../src/libs/zbxicmpping/libzbxicmpping.a \
		../../src/libs/zbxdbupgrade/libzbxdbupgrade.a \
		../../src/libs/zbxdbhigh/libzbxdbhigh.a \
		../../src/libs/zbxmodules/libzbxmodules.a \
		../../src/libs/zbxtasks/libzbxtasks.a \
		-lmysqlclient -levent -lm -ldl -lresolv -lpcreposix -lpcre

poc: poc.c unresolved.c
	objcopy ../../src/libs/zbxdbcache/libzbxdbcache.a libmockpoczbxdbcache.a \
		--redefine-sym zbx_vc_get_value_range=mocked_zbx_vc_get_value_range
	gcc -c poc.c unresolved.c -I../../include
	gcc -o poc poc.o unresolved.o \
		../../src/libs/zbxsysinfo/common/libcommonsysinfo.a \
		../../src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
		../../src/libs/zbxserver/libzbxserver.a \
		libmockpoczbxdbcache.a \
		../../src/libs/zbxmemory/libzbxmemory.a \
		../../src/libs/zbxregexp/libzbxregexp.a \
		../../src/libs/zbxself/libzbxself.a \
		../../src/libs/zbxnix/libzbxnix.a \
		../../src/libs/zbxalgo/libzbxalgo.a \
		../../src/libs/zbxsys/libzbxsys.a \
		../../src/libs/zbxconf/libzbxconf.a \
		../../src/libs/zbxmedia/libzbxmedia.a \
		../../src/libs/zbxcommon/libzbxcommon.a \
		../../src/libs/zbxcrypto/libzbxcrypto.a \
		../../src/libs/zbxcomms/libzbxcomms.a \
		../../src/libs/zbxcommshigh/libzbxcommshigh.a \
		../../src/libs/zbxjson/libzbxjson.a \
		../../src/libs/zbxhttp/libzbxhttp.a \
		../../src/libs/zbxexec/libzbxexec.a \
		../../src/libs/zbxicmpping/libzbxicmpping.a \
		../../src/libs/zbxdbupgrade/libzbxdbupgrade.a \
		../../src/libs/zbxdbhigh/libzbxdbhigh.a \
		../../src/libs/zbxdb/libzbxdb.a \
		../../src/libs/zbxmodules/libzbxmodules.a \
		../../src/libs/zbxtasks/libzbxtasks.a \
		../../src/libs/zbxalgo/libzbxalgo.a \
		../../src/libs/zbxcommon/libzbxcommon.a \
		../../src/libs/zbxsysinfo/libzbxserversysinfo.a \
		../../src/libs/zbxsysinfo/common/libcommonsysinfo.a \
		../../src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
		libmockzbxlog.a \
		../../src/libs/zbxmemory/libzbxmemory.a \
		../../src/libs/zbxregexp/libzbxregexp.a \
		../../src/libs/zbxself/libzbxself.a \
		../../src/libs/zbxnix/libzbxnix.a \
		../../src/libs/zbxalgo/libzbxalgo.a \
		../../src/libs/zbxsys/libzbxsys.a \
		../../src/libs/zbxconf/libzbxconf.a \
		../../src/libs/zbxmedia/libzbxmedia.a \
		../../src/libs/zbxcommon/libzbxcommon.a \
		../../src/libs/zbxcrypto/libzbxcrypto.a \
		../../src/libs/zbxcomms/libzbxcomms.a \
		../../src/libs/zbxcommshigh/libzbxcommshigh.a \
		../../src/libs/zbxjson/libzbxjson.a \
		../../src/libs/zbxhttp/libzbxhttp.a \
		../../src/libs/zbxexec/libzbxexec.a \
		../../src/libs/zbxicmpping/libzbxicmpping.a \
		../../src/libs/zbxdbupgrade/libzbxdbupgrade.a \
		../../src/libs/zbxdbhigh/libzbxdbhigh.a \
		../../src/libs/zbxmodules/libzbxmodules.a \
		../../src/libs/zbxtasks/libzbxtasks.a \
		-lmysqlclient -levent -lm -ldl -lresolv -lpcreposix -lpcre

clean:
	rm -f *.o *.a $(TARGETS)
