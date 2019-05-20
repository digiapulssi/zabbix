#include "t_rsm.h"
#include "../zabbix_server/poller/checks_simple_rsm.c"

#define DEFAULT_RES_IP		"127.0.0.1"
#define DEFAULT_TESTPREFIX	"whois.nic"
#define DEFAULT_MAXREDIRS	10
#define DEFAULT_RDDS_NS_STRING	"Name Server:"

#define LOG_FILE1	"test1.log"
#define LOG_FILE2	"test2.log"

void	exit_usage(const char *progname)
{
	fprintf(stderr, "usage: %s -t <tld> -w <whois_server> <[-4] [-6]> [-r <res_ip>] [-p <testprefix>] [-m <maxredirs80>] [-g]"
			" [-f] [-h]\n", progname);
	fprintf(stderr, "       -t <tld>          TLD to test\n");
	fprintf(stderr, "       -w <whos_server>  WHOIS server to use for RDDS43 test\n");
	fprintf(stderr, "       -4                enable IPv4\n");
	fprintf(stderr, "       -6                enable IPv6\n");
	fprintf(stderr, "       -r <res_ip>       IP address of resolver to use (default: %s)\n", DEFAULT_RES_IP);
	fprintf(stderr, "       -p <testprefix>   TLD prefix to use in RDDS43/RDDS80 tests (default: %s)\n",
			DEFAULT_TESTPREFIX);
	fprintf(stderr, "       -m <maxredirs80>  maximum redirections to use in RDDS80 test (default: %d)\n",
			DEFAULT_MAXREDIRS);
	fprintf(stderr, "       -g                ignore errors, try to finish the test (default: off)\n");
	fprintf(stderr, "       -f                log packets to files (%s, %s) (default: stdout)\n", LOG_FILE1, LOG_FILE2);
	fprintf(stderr, "       -v                enable CURLOPT_VERBOSE when performing HTTP request (default: off)\n");
	fprintf(stderr, "       -h                show this message and quit\n");
	exit(EXIT_FAILURE);
}

int	main(int argc, char *argv[])
{
	char			err[256], *tld = NULL, *whois_server = NULL, *res_ip = DEFAULT_RES_IP, ipv4_enabled = 0,
				ipv6_enabled = 0, *ip43 = NULL, *ip80 = NULL, *testprefix = DEFAULT_TESTPREFIX,
				ignore_err = 0, testname[ZBX_HOST_BUF_SIZE], testurl[1024], *answer = NULL;
	ldns_resolver		*res = NULL;
	zbx_resolver_error_t	ec_res;
	int			c, index, rtt43 = -1, rtt80 = -1, maxredirs = DEFAULT_MAXREDIRS, log_to_file = 0,
				ipv_flags = 0, curl_flags = 0;
	size_t			i;
	zbx_vector_str_t	ips43, nss;
	zbx_http_error_t	ec_http;
	FILE			*log_fd = stdout;
	unsigned int		extras = RESOLVER_EXTRAS_NONE;

	opterr = 0;

	while ((c = getopt (argc, argv, "t:w:46r:p:m:gfvh")) != -1)
	{
		switch (c)
		{
			case 't':
				tld = optarg;
				break;
			case 'w':
				whois_server = optarg;
				break;
			case '4':
				ipv4_enabled = 1;
				break;
			case '6':
				ipv6_enabled = 1;
				break;
			case 'r':
				res_ip = optarg;
				break;
			case 'p':
				testprefix = optarg;
				break;
			case 'm':
				maxredirs = atoi(optarg);
				break;
			case 'g':
				ignore_err = 1;
				break;
			case 'f':
				log_to_file = 1;
				break;
			case 'v':
				curl_flags |= ZBX_FLAG_CURL_VERBOSE;
				break;
			case 'h':
				exit_usage(argv[0]);
			case '?':
				if (optopt == 't' || optopt == 'r' || optopt == 'w' || optopt == 'p' || optopt == 'm')
				{
					fprintf(stderr, "Option -%c requires an argument.\n", optopt);
				}
				else if (isprint(optopt))
				{
					fprintf(stderr, "Unknown option `-%c'.\n", optopt);
				}
				else
					fprintf(stderr, "Unknown option character `\\x%x'.\n", optopt);

				exit(EXIT_FAILURE);
			default:
				abort();
		}
	}

	for (index = optind; index < argc; index++)
		printf("Non-option argument %s\n", argv[index]);

	if (NULL == tld)
	{
		fprintf(stderr, "tld [-t] must be specified\n");
		exit_usage(argv[0]);
	}

	if (NULL == whois_server)
	{
		fprintf(stderr, "WHOIS server [-w] must be specified\n");
		exit_usage(argv[0]);
	}

	if (0 == ipv4_enabled && 0 == ipv6_enabled)
	{
		fprintf(stderr, "at least one IP version [-4, -6] must be specified\n");
		exit_usage(argv[0]);
	}

	zbx_vector_str_create(&nss);
	zbx_vector_str_create(&ips43);

	if (log_to_file != 0)
	{
		if (NULL == (log_fd = fopen(LOG_FILE1, "w")))
		{
			rsm_errf(stderr, "cannot open file \"%s\" for writing: %s", LOG_FILE1, strerror(errno));
			exit(EXIT_FAILURE);
		}
	}

	/* create resolver */
	if (SUCCEED != zbx_create_resolver(&res, "resolver", res_ip, RSM_UDP, ipv4_enabled, ipv6_enabled, extras,
			RSM_TCP_TIMEOUT, RSM_TCP_RETRY, log_fd, err, sizeof(err)))
	{
		rsm_errf(stderr, "cannot create resolver: %s", err);
		goto out;
	}

	if (0 == strcmp(".", tld) || 0 == strcmp("root", tld))
		zbx_snprintf(testname, sizeof(testname), "%s", testprefix);
	else
		zbx_snprintf(testname, sizeof(testname), "%s.%s", testprefix, tld);

	if (0 != ipv4_enabled)
		ipv_flags |= ZBX_FLAG_IPV4_ENABLED;
	if (0 != ipv6_enabled)
		ipv_flags |= ZBX_FLAG_IPV6_ENABLED;

	if (SUCCEED != zbx_resolver_resolve_host(res, testname, &ips43, ipv_flags, log_fd, &ec_res, err, sizeof(err)))
	{
		rsm_errf(stderr, "RDDS43 \"%s\": %s (%d)", testname, err, zbx_resolver_error_to_RDDS43(ec_res));
		if (0 == ignore_err)
			goto out;
	}

	zbx_delete_unsupported_ips(&ips43, ipv4_enabled, ipv6_enabled);

	if (0 == ips43.values_num)
	{
		rsm_errf(stderr, "RDDS43 \"%s\": IP address(es) of host not supported by this probe", testname);
		if (0 == ignore_err)
			goto out;
	}

	for (i = 0; i < ips43.values_num; i++)
		rsm_infof(stdout, "%s", ips43.values[i]);

	/* choose random IP */
	i = zbx_random(ips43.values_num);
	ip43 = ips43.values[i];

	ip80 = ip43;

	zbx_snprintf(testname, sizeof(testname), "%s", whois_server);

	if (SUCCEED != zbx_rdds43_test(testname, ip43, 43, RSM_TCP_TIMEOUT, &answer, &rtt43,
				err, sizeof(err)))
	{
		rsm_errf(stderr, "RDDS43 of \"%s\" (%s) failed: %s", ip43, testname, err);
		if (0 == ignore_err)
			goto out;
	}

	if (log_to_file != 0)
	{
		if (0 != fclose(log_fd))
		{
			rsm_errf(stderr, "cannot close file %s: %s", LOG_FILE1, strerror(errno));
			goto out;
		}

		if (NULL == (log_fd = fopen(LOG_FILE2, "w")))
		{
			rsm_errf(stderr, "cannot open file \"%s\" for writing: %s", LOG_FILE2, strerror(errno));
			exit(EXIT_FAILURE);
		}
	}

	zbx_get_rdds43_nss(&nss, answer, DEFAULT_RDDS_NS_STRING, log_fd);

	if (0 == nss.values_num)
	{
		rsm_errf(stderr, "no Name Servers found in the output of RDDS43 server \"%s\""
				" for query \"%s\" (expecting prefix \"%s\")",
				ip43, testname, DEFAULT_RDDS_NS_STRING);
		if (0 == ignore_err)
			goto out;
	}

	for (i = 0; i < nss.values_num; i++)
		rsm_infof(stdout, "%s %s", DEFAULT_RDDS_NS_STRING, nss.values[i]);

	if (0 == strcmp(".", tld) || 0 == strcmp("root", tld))
		zbx_snprintf(testname, sizeof(testname), "%s", testprefix);
	else
		zbx_snprintf(testname, sizeof(testname), "%s.%s", testprefix, tld);

	if (is_ip6(ip80) == SUCCEED)
		zbx_snprintf(testurl, sizeof(testurl), "http://[%s]", ip80);
	else
		zbx_snprintf(testurl, sizeof(testurl), "http://%s", ip80);

	rsm_infof(stdout, "RDDS80: host=%s url=%s", testname, testurl);

	if (SUCCEED != zbx_http_test(testname, testurl, RSM_TCP_TIMEOUT, maxredirs, &ec_http, &rtt80, NULL,
			curl_devnull, curl_flags, err, sizeof(err)))
	{
		rtt80 = zbx_http_error_to_RDDS80(ec_http);
		rsm_errf(stderr, "RDDS80 of \"%s\" (%s) failed: %s (%d)", testname, testurl, err, rtt80);
		if (0 == ignore_err)
			goto out;
	}

	printf("OK (RTT43:%d RTT80:%d)\n", rtt43, rtt80);
out:
	if (log_to_file != 0)
	{
		if (0 != fclose(log_fd))
			rsm_errf(stderr, "cannot close log file: %s", strerror(errno));
	}

	zbx_vector_str_clean_and_destroy(&ips43);
	zbx_vector_str_clean_and_destroy(&nss);

	zbx_free(answer);

	if (NULL != res)
	{
		if (0 != ldns_resolver_nameserver_count(res))
			ldns_resolver_deep_free(res);
		else
			ldns_resolver_free(res);
	}

	exit(EXIT_SUCCESS);
}
