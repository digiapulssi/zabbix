#include "t_rsm.h"
#include "../zabbix_server/poller/checks_simple_rsm.c"

#define DEFAULT_RES_IP		"127.0.0.1"
#define DEFAULT_WHOIS_PREFIX	"nic"
#define DEFAULT_TESTPREFIX	"whois.nic"
#define DEFAULT_MAXREDIRS	10
#define DEFAULT_RDDS_NS_STRING	"Name Server:"

#define LOG_FILE1	"test1.log"
#define LOG_FILE2	"test2.log"

void	exit_usage(const char *progname)
{
	fprintf(stderr, "usage: %s -t <tld> [-r <res_ip>] [-w <whois_prefix>] [-p <testprefix>] [-m <maxredirs80>] [-g]"
			" [-f] [-h]\n", progname);
	fprintf(stderr, "       -t <tld>          TLD to test\n");
	fprintf(stderr, "       -r <res_ip>       IP address of resolver to use (default: %s)\n", DEFAULT_RES_IP);
	fprintf(stderr, "       -w <whos_prefix>  TLD prefix to use when querying RDDS43 server (default: %s)\n",
			DEFAULT_WHOIS_PREFIX);
	fprintf(stderr, "       -p <testprefix>   TLD prefix to use in RDDS43/RDDS80 tests (default: %s)\n",
			DEFAULT_TESTPREFIX);
	fprintf(stderr, "       -m <maxredirs80>  maximum redirections to use in RDDS80 test (default: %d)\n",
			DEFAULT_MAXREDIRS);
	fprintf(stderr, "       -g                ignore errors, try to finish the test\n");
	fprintf(stderr, "       -f                log packets to files (%s, %s) instead of stdout\n", LOG_FILE1, LOG_FILE2);
	fprintf(stderr, "       -h                show this message and quit\n");
	exit(EXIT_FAILURE);
}

int	main(int argc, char *argv[])
{
	char			err[256], *tld = NULL, *res_ip = DEFAULT_RES_IP, *whois_prefix = DEFAULT_WHOIS_PREFIX,
				*ip43 = NULL, *ip80 = NULL, *testprefix = DEFAULT_TESTPREFIX, ignore_err = 0, internal,
				testname[ZBX_HOST_BUF_SIZE], testurl[ZBX_HOST_BUF_SIZE], *answer = NULL;
	ldns_resolver		*res = NULL;
	int			c, index, rtt43 = -1, rtt80 = -1, maxredirs = DEFAULT_MAXREDIRS, log_to_file = 0;
	size_t			i;
	zbx_vector_str_t	ips43, nss;
	FILE			*log_fd = stdout;

	opterr = 0;

	while ((c = getopt (argc, argv, "t:r:w:p:m:gfh")) != -1)
	{
		switch (c)
		{
			case 't':
				tld = optarg;
				break;
			case 'r':
				res_ip = optarg;
				break;
			case 'w':
				whois_prefix = optarg;
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

	zbx_vector_str_create(&nss);
	zbx_vector_str_create(&ips43);

	if (log_to_file != 0)
	{
		if (NULL == (log_fd = fopen(LOG_FILE1, "w")))
		{
			zbx_rsm_errf(stderr, "cannot open file \"%s\" for writing: %s", LOG_FILE1, strerror(errno));
			exit(EXIT_FAILURE);
		}
	}

	/* create resolver */
	if (SUCCEED != zbx_create_resolver(&res, "resolver", res_ip, ZBX_RSM_UDP, 1, 1, log_fd, err, sizeof(err)))
	{
		zbx_rsm_errf(stderr, "cannot create resolver: %s", err);
		goto out;
	}

	zbx_snprintf(testname, sizeof(testname), "%s.%s", testprefix, tld);

	if (SUCCEED != zbx_resolve_host(res, testname, &ips43, 1, 1, log_fd, &internal, err, sizeof(err)))
	{
		zbx_rsm_errf(stderr, "RDDS43 \"%s\": %s", testname, err);
		if (0 == ignore_err)
			goto out;
	}

	zbx_delete_unsupported_ips(&ips43, 1, 1);

	if (0 == ips43.values_num)
	{
		zbx_rsm_errf(stderr, "RDDS43 \"%s\": IP address(es) of host not supported by this probe", testname);
		if (0 == ignore_err)
			goto out;
	}

	for (i = 0; i < ips43.values_num; i++)
		zbx_rsm_infof(stdout, "%s", ips43.values[i]);

	/* choose random IP */
	i = zbx_random(ips43.values_num);
	ip43 = ips43.values[i];

	ip80 = ip43;

	zbx_snprintf(testname, sizeof(testname), "%s.%s", whois_prefix, tld);

	if (SUCCEED != zbx_rdds43_test(testname, ip43, 43, ZBX_RSM_TCP_TIMEOUT, &answer, &rtt43,
				err, sizeof(err)))
	{
		zbx_rsm_errf(stderr, "RDDS43 of \"%s\" (%s) failed: %s", ip43, testname, err);
		if (0 == ignore_err)
			goto out;
	}

	if (log_to_file != 0)
	{
		if (0 != fclose(log_fd))
		{
			zbx_rsm_errf(stderr, "cannot close file %s: %s", LOG_FILE1, strerror(errno));
			goto out;
		}

		if (NULL == (log_fd = fopen(LOG_FILE2, "w")))
		{
			zbx_rsm_errf(stderr, "cannot open file \"%s\" for writing: %s", LOG_FILE2, strerror(errno));
			exit(EXIT_FAILURE);
		}
	}

	zbx_get_rdds43_nss(&nss, answer, DEFAULT_RDDS_NS_STRING, log_fd);

	if (0 == nss.values_num)
	{
		zbx_rsm_errf(stderr, "no Name Servers found in the output of RDDS43 server \"%s\""
				" for query \"%s\" (expecting prefix \"%s\")",
				ip43, testname, DEFAULT_RDDS_NS_STRING);
		if (0 == ignore_err)
			goto out;
	}

	for (i = 0; i < nss.values_num; i++)
		zbx_rsm_infof(stdout, "%s %s", DEFAULT_RDDS_NS_STRING, nss.values[i]);

	zbx_snprintf(testname, sizeof(testname), "%s.%s", testprefix, tld);

	if (is_ip6(ip80) == SUCCEED)
		zbx_snprintf(testurl, sizeof(testurl), "http://[%s]", ip80);
	else
		zbx_snprintf(testurl, sizeof(testurl), "http://%s", ip80);

	zbx_rsm_infof(stdout, "RDDS80: host=%s url=%s", testname, testurl);

	if (SUCCEED != zbx_rdds80_test(testname, testurl, 80, ZBX_RSM_TCP_TIMEOUT, maxredirs, &rtt80, err, sizeof(err)))
	{
		zbx_rsm_errf(stderr, "RDDS80 of \"%s\" (%s) failed: %s", testname, testname, err);
		if (0 == ignore_err)
			goto out;
	}

	printf("OK (RTT43:%d RTT80:%d)\n", rtt43, rtt80);
out:
	if (log_to_file != 0)
	{
		if (0 != fclose(log_fd))
			zbx_rsm_errf(stderr, "cannot close log file: %s", strerror(errno));
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
