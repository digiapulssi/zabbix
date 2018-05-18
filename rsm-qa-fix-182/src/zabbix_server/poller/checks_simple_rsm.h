/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#ifndef ZABBIX_CHECKS_SIMPLE_RSM_H
#define ZABBIX_CHECKS_SIMPLE_RSM_H

#include "dbcache.h"

/* internal */
#define ZBX_EC_INTERNAL			-1	/* general internal error */
#define ZBX_EC_INTERNAL_IP_UNSUP	-2	/* IP version not supported by Probe */
/* auxiliary generic HTTP error codes */
#define ZBX_EC_HTTP_TO			-3
#define ZBX_EC_HTTP_ECON		-4
#define ZBX_EC_HTTP_EHTTP		-5
#define ZBX_EC_HTTP_EHTTPS		-6
#define ZBX_EC_HTTP_NOCODE		-7
#define ZBX_EC_HTTP_BASE		-8
/* Code ZBX_EC_HTTP_BASE - zbx_map_http_code(xxx) means we got HTTP status code xxx */
/* DNS UDP */
#define ZBX_EC_DNS_UDP_NS_NOREPLY	-200	/* DNS UDP - No reply from name server */
#define ZBX_EC_DNS_UDP_CLASS_CHAOS	-207	/* DNS UDP - Expecting DNS CLASS IN but got CHAOS */
#define ZBX_EC_DNS_UDP_CLASS_HESIOD	-208	/* DNS UDP - Expecting DNS CLASS IN but got HESIOD */
#define ZBX_EC_DNS_UDP_CLASS_CATCHALL	-209	/* DNS UDP - Expecting DNS CLASS IN but got something different than IN, CHAOS or HESIOD */
#define ZBX_EC_DNS_UDP_HEADER		-210	/* DNS UDP - Header section incomplete */
#define ZBX_EC_DNS_UDP_QUESTION		-211	/* DNS UDP - Question section incomplete */
#define ZBX_EC_DNS_UDP_ANSWER		-212	/* DNS UDP - Answer section incomplete */
#define ZBX_EC_DNS_UDP_AUTHORITY	-213	/* DNS UDP - Authority section incomplete */
#define ZBX_EC_DNS_UDP_ADDITIONAL	-214	/* DNS UDP - Additional section incomplete */
#define ZBX_EC_DNS_UDP_CATCHALL		-215	/* DNS UDP - Malformed DNS response */
#define ZBX_EC_DNS_UDP_NOAAFLAG		-250	/* DNS UDP - Querying for a non existent domain - AA flag not present in response */
#define ZBX_EC_DNS_UDP_NODOMAIN		-251	/* DNS UDP - Querying for a non existent domain - Domain name being queried not present in question section */
/* Error code for every assigned, non private DNS RCODE (with the exception of RCODE/NXDOMAIN) */
/* as per: https://www.iana.org/assignments/dns-parameters/dns-parameters.xhtml */
#define ZBX_EC_DNS_UDP_RCODE_NOERROR	-252	/* DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got NOERROR */
#define ZBX_EC_DNS_UDP_RCODE_FORMERR	-253	/* DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got FORMERR */
#define ZBX_EC_DNS_UDP_RCODE_SERVFAIL	-254	/* DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got SERVFAIL */
#define ZBX_EC_DNS_UDP_RCODE_NOTIMP	-255	/* DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got NOTIMP */
#define ZBX_EC_DNS_UDP_RCODE_REFUSED	-256	/* DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got REFUSED */
#define ZBX_EC_DNS_UDP_RCODE_YXDOMAIN	-257	/* DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got YXDOMAIN */
#define ZBX_EC_DNS_UDP_RCODE_YXRRSET	-258	/* DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got YXRRSET */
#define ZBX_EC_DNS_UDP_RCODE_NXRRSET	-259	/* DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got NXRRSET */
#define ZBX_EC_DNS_UDP_RCODE_NOTAUTH	-260	/* DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got NOTAUTH */
#define ZBX_EC_DNS_UDP_RCODE_NOTZONE	-261	/* DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got NOTZONE */
#define ZBX_EC_DNS_UDP_RCODE_BADVERS_OR	-262	/* DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADVERS or BADSIG */
#define ZBX_EC_DNS_UDP_RCODE_BADKEY	-263	/* DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADKEY */
#define ZBX_EC_DNS_UDP_RCODE_BADTIME	-264	/* DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADTIME */
#define ZBX_EC_DNS_UDP_RCODE_BADMODE	-265	/* DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADMODE */
#define ZBX_EC_DNS_UDP_RCODE_BADNAME	-266	/* DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADNAME */
#define ZBX_EC_DNS_UDP_RCODE_BADALG	-267	/* DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADALG */
#define ZBX_EC_DNS_UDP_RCODE_BADTRUNC	-268	/* DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADTRUNC */
#define ZBX_EC_DNS_UDP_RCODE_BADCOOKIE	-269	/* DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADCOOKIE */
#define ZBX_EC_DNS_UDP_RCODE_CATCHALL	-270	/* DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got unexpected */
#define ZBX_EC_DNS_UDP_RES_NOREPLY	-400	/* DNS UDP - No reply from local resolver */
#define ZBX_EC_DNS_UDP_RES_NOADBIT	-401	/* DNS UDP - No AD bit from local resolver */
#define ZBX_EC_DNS_UDP_RES_SERVFAIL	-402	/* DNS UDP - Expecting NOERROR RCODE but got SERVFAIL from local resolver */
#define ZBX_EC_DNS_UDP_RES_NXDOMAIN	-403	/* DNS UDP - Expecting NOERROR RCODE but got NXDOMAIN from local resolver */
#define ZBX_EC_DNS_UDP_RES_CATCHALL	-404	/* DNS UDP - Expecting NOERROR RCODE but got unexpecting from local resolver */
#define ZBX_EC_DNS_UDP_ALGO_UNKNOWN	-405	/* DNS UDP - Unknown cryptographic algorithm */
#define ZBX_EC_DNS_UDP_ALGO_NOT_IMPL	-406	/* DNS UDP - Cryptographic algorithm not implemented */
#define ZBX_EC_DNS_UDP_RRSIG_NONE	-407	/* DNS UDP - No RRSIGs where found in any section, and the TLD has the DNSSEC flag enabled */
#define ZBX_EC_DNS_UDP_RRSIG_NOTCOVERED	-410	/* DNS UDP - The signature does not cover this RRset */
#define ZBX_EC_DNS_UDP_RRSIG_NOT_SIGNED	-414	/* DNS UDP - The RRSIG found is not signed by a DNSKEY from the KEYSET of the TLD */
#define ZBX_EC_DNS_UDP_SIG_BOGUS	-415	/* DNS UDP - Bogus DNSSEC signature */
#define ZBX_EC_DNS_UDP_SIG_EXPIRED	-416	/* DNS UDP - DNSSEC signature has expired */
#define ZBX_EC_DNS_UDP_SIG_NOT_INCEPTED	-417	/* DNS UDP - DNSSEC signature not incepted yet */
#define ZBX_EC_DNS_UDP_SIG_EX_BEFORE_IN	-418	/* DNS UDP - DNSSEC signature has expiration date earlier than inception date */
#define ZBX_EC_DNS_UDP_NSEC3_ERROR	-419	/* DNS UDP - Error in NSEC3 denial of existence proof */
#define ZBX_EC_DNS_UDP_NSEC3_ITERATIONS	-421	/* DNS UDP - Iterations count for NSEC3 record higher than maximum */
#define ZBX_EC_DNS_UDP_RR_NOTCOVERED	-422	/* DNS UDP - RR not covered by the given NSEC RRs */
#define ZBX_EC_DNS_UDP_WILD_NOTCOVERED	-423	/* DNS UDP - Wildcard not covered by the given NSEC RRs */
#define ZBX_EC_DNS_UDP_RRSIG_MISS_RDATA	-425	/* DNS UDP - The RRSIG has too few RDATA fields */
#define ZBX_EC_DNS_UDP_KEY_MISS_RDATA	-426	/* DNS UDP - The DNSKEY has too few RDATA fields */
#define ZBX_EC_DNS_UDP_DNSSEC_CATCHALL	-427	/* DNS UDP - Malformed DNSSEC response */
#define ZBX_EC_DNS_UDP_DNSKEY_NONE	-428	/* DNS UDP - The TLD is configured as DNSSEC-enabled, but no DNSKEY was found in the apex */
/* DNS TCP */
#define ZBX_EC_DNS_TCP_NS_TO		-600	/* DNS TCP - DNS TCP - Timeout reply from name server */
#define ZBX_EC_DNS_TCP_NS_ECON		-601	/* DNS TCP - Error opening connection to name server */
#define ZBX_EC_DNS_TCP_CLASS_CHAOS	-607	/* DNS TCP - Expecting DNS CLASS IN but got CHAOS */
#define ZBX_EC_DNS_TCP_CLASS_HESIOD	-608	/* DNS TCP - Expecting DNS CLASS IN but got HESIOD */
#define ZBX_EC_DNS_TCP_CLASS_CATCHALL	-609	/* DNS TCP - Expecting DNS CLASS IN but got something different than IN, CHAOS or HESIOD */
#define ZBX_EC_DNS_TCP_HEADER		-610	/* DNS TCP - Header section incomplete */
#define ZBX_EC_DNS_TCP_QUESTION		-611	/* DNS TCP - Question section incomplete */
#define ZBX_EC_DNS_TCP_ANSWER		-612	/* DNS TCP - Answer section incomplete */
#define ZBX_EC_DNS_TCP_AUTHORITY	-613	/* DNS TCP - Authority section incomplete */
#define ZBX_EC_DNS_TCP_ADDITIONAL	-614	/* DNS TCP - Additional section incomplete */
#define ZBX_EC_DNS_TCP_CATCHALL		-615	/* DNS TCP - Malformed DNS response */
#define ZBX_EC_DNS_TCP_NOAAFLAG		-650	/* DNS TCP - Querying for a non existent domain - AA flag not present in response */
#define ZBX_EC_DNS_TCP_NODOMAIN		-651	/* DNS TCP - Querying for a non existent domain - Domain name being queried not present in question section */
/* Error code for every assigned, non private DNS RCODE (with the exception of RCODE/NXDOMAIN) */
/* as per: https://www.iana.org/assignments/dns-parameters/dns-parameters.xhtml */
#define ZBX_EC_DNS_TCP_RCODE_NOERROR	-652	/* DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got NOERROR */
#define ZBX_EC_DNS_TCP_RCODE_FORMERR	-653	/* DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got FORMERR */
#define ZBX_EC_DNS_TCP_RCODE_SERVFAIL	-654	/* DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got SERVFAIL */
#define ZBX_EC_DNS_TCP_RCODE_NOTIMP	-655	/* DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got NOTIMP */
#define ZBX_EC_DNS_TCP_RCODE_REFUSED	-656	/* DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got REFUSED */
#define ZBX_EC_DNS_TCP_RCODE_YXDOMAIN	-657	/* DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got YXDOMAIN */
#define ZBX_EC_DNS_TCP_RCODE_YXRRSET	-658	/* DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got YXRRSET */
#define ZBX_EC_DNS_TCP_RCODE_NXRRSET	-659	/* DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got NXRRSET */
#define ZBX_EC_DNS_TCP_RCODE_NOTAUTH	-660	/* DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got NOTAUTH */
#define ZBX_EC_DNS_TCP_RCODE_NOTZONE	-661	/* DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got NOTZONE */
#define ZBX_EC_DNS_TCP_RCODE_BADVERS_OR	-662	/* DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADVERS or BADSIG */
#define ZBX_EC_DNS_TCP_RCODE_BADKEY	-663	/* DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADKEY */
#define ZBX_EC_DNS_TCP_RCODE_BADTIME	-664	/* DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADTIME */
#define ZBX_EC_DNS_TCP_RCODE_BADMODE	-665	/* DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADMODE */
#define ZBX_EC_DNS_TCP_RCODE_BADNAME	-666	/* DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADNAME */
#define ZBX_EC_DNS_TCP_RCODE_BADALG	-667	/* DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADALG */
#define ZBX_EC_DNS_TCP_RCODE_BADTRUNC	-668	/* DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADTRUNC */
#define ZBX_EC_DNS_TCP_RCODE_BADCOOKIE	-669	/* DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADCOOKIE */
#define ZBX_EC_DNS_TCP_RCODE_CATCHALL	-670	/* DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got unexpected */
#define ZBX_EC_DNS_TCP_RES_NOREPLY	-800	/* DNS TCP - No reply from local resolver */
#define ZBX_EC_DNS_TCP_RES_NOADBIT	-801	/* DNS TCP - No AD bit from local resolver */
#define ZBX_EC_DNS_TCP_RES_SERVFAIL	-802	/* DNS TCP - Expecting NOERROR RCODE but got SERVFAIL from local resolver */
#define ZBX_EC_DNS_TCP_RES_NXDOMAIN	-803	/* DNS TCP - Expecting NOERROR RCODE but got NXDOMAIN from local resolver */
#define ZBX_EC_DNS_TCP_RES_CATCHALL	-804	/* DNS TCP - Expecting NOERROR RCODE but got unexpecting from local resolver */
#define ZBX_EC_DNS_TCP_ALGO_UNKNOWN	-805	/* DNS TCP - Unknown cryptographic algorithm */
#define ZBX_EC_DNS_TCP_ALGO_NOT_IMPL	-806	/* DNS TCP - Cryptographic algorithm not implemented */
#define ZBX_EC_DNS_TCP_RRSIG_NONE	-807	/* DNS TCP - No RRSIGs where found in any section, and the TLD has the DNSSEC flag enabled */
#define ZBX_EC_DNS_TCP_RRSIG_NOTCOVERED	-810	/* DNS TCP - The signature does not cover this RRset */
#define ZBX_EC_DNS_TCP_RRSIG_NOT_SIGNED	-814	/* DNS TCP - The RRSIG found is not signed by a DNSKEY from the KEYSET of the TLD */
#define ZBX_EC_DNS_TCP_SIG_BOGUS	-815	/* DNS TCP - Bogus DNSSEC signature */
#define ZBX_EC_DNS_TCP_SIG_EXPIRED	-816	/* DNS TCP - DNSSEC signature has expired */
#define ZBX_EC_DNS_TCP_SIG_NOT_INCEPTED	-817	/* DNS TCP - DNSSEC signature not incepted yet */
#define ZBX_EC_DNS_TCP_SIG_EX_BEFORE_IN	-818	/* DNS TCP - DNSSEC signature has expiration date earlier than inception date */
#define ZBX_EC_DNS_TCP_NSEC3_ERROR	-819	/* DNS TCP - Error in NSEC3 denial of existence proof */
#define ZBX_EC_DNS_TCP_NSEC3_ITERATIONS	-821	/* DNS TCP - Iterations count for NSEC3 record higher than maximum */
#define ZBX_EC_DNS_TCP_RR_NOTCOVERED	-822	/* DNS TCP - RR not covered by the given NSEC RRs */
#define ZBX_EC_DNS_TCP_WILD_NOTCOVERED	-823	/* DNS TCP - Wildcard not covered by the given NSEC RRs */
#define ZBX_EC_DNS_TCP_RRSIG_MISS_RDATA	-825	/* DNS TCP - The RRSIG has too few RDATA fields */
#define ZBX_EC_DNS_TCP_KEY_MISS_RDATA	-826	/* DNS TCP - The DNSKEY has too few RDATA fields */
#define ZBX_EC_DNS_TCP_DNSSEC_CATCHALL	-827	/* DNS TCP - Malformed DNSSEC response */
#define ZBX_EC_DNS_TCP_DNSKEY_NONE	-828	/* DNS TCP - The TLD is configured as DNSSEC-enabled, but no DNSKEY was found in the apex */
/* RDDS */
#define ZBX_EC_RDDS43_NONS		-201	/* Whois server returned no NS */
#define ZBX_EC_RDDS80_NOCODE		-206	/* no HTTP status code */
#define ZBX_EC_RDDS43_RES_NOREPLY	-222	/* RDDS43 - No reply from local resolver */
#define ZBX_EC_RDDS43_RES_SERVFAIL	-224	/* RDDS43 - Expecting NOERROR RCODE but got SERVFAIL when resolving hostname */
#define ZBX_EC_RDDS43_RES_NXDOMAIN	-225	/* RDDS43 - Expecting NOERROR RCODE but got NXDOMAIN when resolving hostname */
#define ZBX_EC_RDDS43_RES_CATCHALL	-226	/* RDDS43 - Expecting NOERROR RCODE but got unexpected when resolving hostname */
#define ZBX_EC_RDDS43_TO		-227	/* RDDS43 - Timeout */
#define ZBX_EC_RDDS43_ECON		-228	/* RDDS43 - Error opening connection to server */
#define ZBX_EC_RDDS43_EMPTY		-229	/* RDDS43 - Empty response */
#define ZBX_EC_RDDS80_RES_NOREPLY	-250	/* RDDS80 - No reply from local resolver */
#define ZBX_EC_RDDS80_RES_SERVFAIL	-252	/* RDDS80 - Expecting NOERROR RCODE but got SERVFAIL when resolving hostname */
#define ZBX_EC_RDDS80_RES_NXDOMAIN	-253	/* RDDS80 - Expecting NOERROR RCODE but got NXDOMAIN when resolving hostname */
#define ZBX_EC_RDDS80_RES_CATCHALL	-254	/* RDDS80 - Expecting NOERROR RCODE but got unexpected when resolving hostname */
#define ZBX_EC_RDDS80_TO		-255	/* RDDS80 - Timeout */
#define ZBX_EC_RDDS80_ECON		-256	/* RDDS80 - Error opening connection to server */
#define ZBX_EC_RDDS80_EHTTP		-257	/* RDDS80 - Error in HTTP protocol */
#define ZBX_EC_RDDS80_EHTTPS		-258	/* RDDS80 - Error in HTTPS protocol */
#define ZBX_EC_RDDS80_HTTP_BASE		-300
/* Code ZBX_EC_RDDS80_HTTP_BASE - zbx_map_http_code(xxx) means */
						/* RDDS80 - Expecting HTTP status code 200 but got xxx */
/* RDAP */
#define ZBX_EC_RDAP_RES_NOREPLY		-200	/* RDAP - No reply from local resolver */
#define ZBX_EC_RDAP_RES_NOADBIT		-201	/* RDAP - No AD bit from local resolver */
#define ZBX_EC_RDAP_RES_SERVFAIL	-202	/* RDAP - Expecting NOERROR RCODE but got SERVFAIL when resolving hostname */
#define ZBX_EC_RDAP_RES_NXDOMAIN	-203	/* RDAP - Expecting NOERROR RCODE but got NXDOMAIN when resolving hostname */
#define ZBX_EC_RDAP_RES_CATCHALL	-204	/* RDAP - Expecting NOERROR RCODE but got unexpected error when resolving hostname */
#define ZBX_EC_RDAP_TO			-205	/* RDAP - Timeout */
#define ZBX_EC_RDAP_ECON		-206	/* RDAP - Error opening connection to server */
#define ZBX_EC_RDAP_EJSON		-207	/* RDAP - Invalid JSON format in response */
#define ZBX_EC_RDAP_NONAME		-208	/* RDAP - ldhName member not found in response */
#define ZBX_EC_RDAP_ENAME		-209	/* RDAP - ldhName member doesn't match query in response */
#define ZBX_EC_RDAP_EHTTP		-213	/* RDAP - Error in HTTP protocol */
#define ZBX_EC_RDAP_EHTTPS		-214	/* RDAP - Error in HTTPS protocol */
#define ZBX_EC_RDAP_HTTP_BASE		-250
/* Code ZBX_EC_RDAP_HTTP_BASE - zbx_map_http_code(xxx) means */
						/* RDAP - Expecting HTTP status code 200 but got xxx */
/* EPP */
#define ZBX_EC_EPP_NO_IP		-200	/* IP is missing for EPP server */
#define ZBX_EC_EPP_CONNECT		-201	/* cannot connect to EPP server */
#define ZBX_EC_EPP_CRYPT		-202	/* invalid certificate or private key */
#define ZBX_EC_EPP_FIRSTTO		-203	/* first message timeout */
#define ZBX_EC_EPP_FIRSTINVAL		-204	/* first message is invalid */
#define ZBX_EC_EPP_LOGINTO		-205	/* LOGIN command timeout */
#define ZBX_EC_EPP_LOGININVAL		-206	/* invalid reply to LOGIN command */
#define ZBX_EC_EPP_UPDATETO		-207	/* UPDATE command timeout */
#define ZBX_EC_EPP_UPDATEINVAL		-208	/* invalid reply to UPDATE command */
#define ZBX_EC_EPP_INFOTO		-209	/* INFO command timeout */
#define ZBX_EC_EPP_INFOINVAL		-210	/* invalid reply to INFO command */
#define ZBX_EC_EPP_SERVERCERT		-211	/* Server certificate validation failed */

#define ZBX_EC_PROBE_OFFLINE		0	/* probe in automatic offline mode */
#define ZBX_EC_PROBE_ONLINE		1	/* probe in automatic online mode */
#define ZBX_EC_PROBE_UNSUPPORTED	2	/* internal use only */

#define ZBX_NO_VALUE			-1000	/* no item value should be set */

/* NB! Do not change, these are used as DNS array indexes. */
#define ZBX_RSM_UDP	0
#define ZBX_RSM_TCP	1

#define ZBX_MACRO_DNS_RESOLVER		"{$RSM.RESOLVER}"
#define ZBX_MACRO_DNS_TESTPREFIX	"{$RSM.DNS.TESTPREFIX}"
#define ZBX_MACRO_DNS_UDP_RTT		"{$RSM.DNS.UDP.RTT.HIGH}"
#define ZBX_MACRO_DNS_TCP_RTT		"{$RSM.DNS.TCP.RTT.HIGH}"
#define ZBX_MACRO_RDDS_TESTPREFIX	"{$RSM.RDDS.TESTPREFIX}"
#define ZBX_MACRO_RDDS_RTT		"{$RSM.RDDS.RTT.HIGH}"
#define ZBX_MACRO_RDDS_NS_STRING	"{$RSM.RDDS.NS.STRING}"
#define ZBX_MACRO_RDDS_MAXREDIRS	"{$RSM.RDDS.MAXREDIRS}"
#define ZBX_MACRO_RDDS_ENABLED		"{$RSM.RDDS.ENABLED}"
#define ZBX_MACRO_EPP_LOGIN_RTT		"{$RSM.EPP.LOGIN.RTT.HIGH}"
#define ZBX_MACRO_EPP_UPDATE_RTT	"{$RSM.EPP.UPDATE.RTT.HIGH}"
#define ZBX_MACRO_EPP_INFO_RTT		"{$RSM.EPP.INFO.RTT.HIGH}"
#define ZBX_MACRO_IP4_ENABLED		"{$RSM.IP4.ENABLED}"
#define ZBX_MACRO_IP6_ENABLED		"{$RSM.IP6.ENABLED}"
#define ZBX_MACRO_IP4_MIN_SERVERS	"{$RSM.IP4.MIN.SERVERS}"
#define ZBX_MACRO_IP6_MIN_SERVERS	"{$RSM.IP6.MIN.SERVERS}"
#define ZBX_MACRO_IP4_REPLY_MS		"{$RSM.IP4.REPLY.MS}"
#define ZBX_MACRO_IP6_REPLY_MS		"{$RSM.IP6.REPLY.MS}"
#define ZBX_MACRO_PROBE_ONLINE_DELAY	"{$RSM.PROBE.ONLINE.DELAY}"
#define ZBX_MACRO_EPP_ENABLED		"{$RSM.EPP.ENABLED}"
#define ZBX_MACRO_EPP_USER		"{$RSM.EPP.USER}"
#define ZBX_MACRO_EPP_PASSWD		"{$RSM.EPP.PASSWD}"
#define ZBX_MACRO_EPP_CERT		"{$RSM.EPP.CERT}"
#define ZBX_MACRO_EPP_PRIVKEY		"{$RSM.EPP.PRIVKEY}"
#define ZBX_MACRO_EPP_KEYSALT		"{$RSM.EPP.KEYSALT}"
#define ZBX_MACRO_EPP_COMMANDS		"{$RSM.EPP.COMMANDS}"
#define ZBX_MACRO_EPP_SERVERID		"{$RSM.EPP.SERVERID}"
#define ZBX_MACRO_EPP_TESTPREFIX	"{$RSM.EPP.TESTPREFIX}"
#define ZBX_MACRO_EPP_SERVERCERTMD5	"{$RSM.EPP.SERVERCERTMD5}"
#define ZBX_MACRO_TLD_DNSSEC_ENABLED	"{$RSM.TLD.DNSSEC.ENABLED}"
#define ZBX_MACRO_TLD_RDDS_ENABLED	"{$RSM.TLD.RDDS.ENABLED}"
#define ZBX_MACRO_TLD_EPP_ENABLED	"{$RSM.TLD.EPP.ENABLED}"

#define ZBX_RSM_UDP_TIMEOUT	3	/* seconds */
#define ZBX_RSM_UDP_RETRY	1
#define ZBX_RSM_TCP_TIMEOUT	11	/* seconds (SLA: 5 times higher than max (2)) */
#define ZBX_RSM_TCP_RETRY	1

#define ZBX_RSM_DEFAULT_LOGDIR		"/var/log"	/* if Zabbix log dir is undefined */
#define ZBX_DNS_LOG_PREFIX		"dns"		/* file will be <LOGDIR>/<PROBE>-<TLD>-ZBX_DNS_LOG_PREFIX-<udp|tcp>.log */
#define ZBX_RDDS_LOG_PREFIX		"rdds"		/* file will be <LOGDIR>/<PROBE>-<TLD>-ZBX_RDDS_LOG_PREFIX.log */
#define ZBX_RDAP_LOG_PREFIX		"rdap"		/* file will be <LOGDIR>/<PROBE>-<TLD>-ZBX_RDAP_LOG_PREFIX.log */
#define ZBX_EPP_LOG_PREFIX		"epp"		/* file will be <LOGDIR>/<PROBE>-<TLD>-ZBX_EPP_LOG_PREFIX.log */
#define ZBX_PROBESTATUS_LOG_PREFIX	"probestatus"	/* file will be <LOGDIR>/<PROBE>-probestatus.log */

int	check_rsm_dns(DC_ITEM *item, const AGENT_REQUEST *request, AGENT_RESULT *result, char proto);
int	check_rsm_rdds(DC_ITEM *item, const AGENT_REQUEST *request, AGENT_RESULT *result);
int	check_rsm_rdap(DC_ITEM *item, const AGENT_REQUEST *request, AGENT_RESULT *result);
int	check_rsm_epp(DC_ITEM *item, const AGENT_REQUEST *request, AGENT_RESULT *result);
int	check_rsm_probe_status(DC_ITEM *item, const AGENT_REQUEST *request, AGENT_RESULT *result);

#endif
