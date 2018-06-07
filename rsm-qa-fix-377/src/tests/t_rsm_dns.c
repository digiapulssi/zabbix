#include "t_rsm.h"
#include "../zabbix_server/poller/checks_simple_rsm.c"

#define DEFAULT_RES_IP		"127.0.0.1"
#define DEFAULT_TESTPREFIX	"www.zz--rsm-monitoring"

#define LOG_FILE1	"test1.log"
#define LOG_FILE2	"test2.log"

void	exit_usage(const char *progname)
{
	fprintf(stderr, "usage: %s -t <tld> -n <ns> -i <ip> [-r <res_ip>] [-p <testprefix>] [-d] [-g] [-f] [-h]\n", progname);
	fprintf(stderr, "       -t <tld>          TLD to test\n");
	fprintf(stderr, "       -n <ns>           Name Server to test\n");
	fprintf(stderr, "       -i <ip>           IP address of the Name Server to test\n");
	fprintf(stderr, "       -r <res_ip>       IP address of resolver to use (default: %s)\n", DEFAULT_RES_IP);
	fprintf(stderr, "       -p <testprefix>   domain testprefix to use (default: %s)\n", DEFAULT_TESTPREFIX);
	fprintf(stderr, "       -d                enable DNSSEC\n");
	fprintf(stderr, "       -c                use TCP instead of UDP\n");
	fprintf(stderr, "       -g                ignore errors, try to finish the test\n");
	fprintf(stderr, "       -f                log packets to files (%s, %s) instead of stdout\n", LOG_FILE1, LOG_FILE2);
	fprintf(stderr, "       -h                show this message and quit\n");
	exit(EXIT_FAILURE);
}

int	main(int argc, char *argv[])
{
	char		err[256], *res_ip = DEFAULT_RES_IP, *tld = NULL, *ns = NULL, *ns_ip = NULL, proto = ZBX_RSM_UDP,
			ipv4_enabled = 1, ipv6_enabled = 1, *testprefix = DEFAULT_TESTPREFIX, dnssec_enabled = 0, ignore_err = 0,
			log_to_file = 0;
	int		c, index, rtt;
	zbx_dnskeys_error_t res_ec;
	ldns_resolver	*res = NULL;
	ldns_rr_list	*keys = NULL;
	FILE		*log_fd = stdout;

	opterr = 0;

	while ((c = getopt (argc, argv, "t:n:i:r:p:dcgfh")) != -1)
	{
		switch (c)
		{
			case 't':
				tld = optarg;
				break;
			case 'n':
				ns = optarg;
				break;
			case 'i':
				ns_ip = optarg;
				break;
			case 'r':
				res_ip = optarg;
				break;
			case 'p':
				testprefix = optarg;
				break;
			case 'd':
				dnssec_enabled = 1;
				break;
			case 'c':
				proto = ZBX_RSM_TCP;
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
				if (optopt == 't' || optopt == 'n' || optopt == 'i' || optopt == 'r' || optopt == 'p')
					fprintf(stderr, "Option -%c requires an argument.\n", optopt);
				else if (isprint (optopt))
					fprintf(stderr, "Unknown option `-%c'.\n", optopt);
				else
					fprintf(stderr, "Unknown option character `\\x%x'.\n", optopt);
				exit(EXIT_FAILURE);
			default:
				abort();
		}
	}

	for (index = optind; index < argc; index++)
		printf("Non-option argument %s\n", argv[index]);

	if (NULL == tld || NULL == ns || NULL == ns_ip)
		exit_usage(argv[0]);

	zbx_rsm_infof(log_fd, "tld:%s, ns:%s, ip:%s, res:%s, testprefix:%s", tld, ns, ns_ip, res_ip, testprefix);

	/* create resolver */
	if (SUCCEED != zbx_create_resolver(&res, "resolver", res_ip, proto, ipv4_enabled, ipv6_enabled, dnssec_enabled,
			log_fd, err, sizeof(err)))
	{
		zbx_rsm_errf(stderr, "cannot create resolver: %s", err);
		goto out;
	}

	if (0 != dnssec_enabled)
	{
		if (log_to_file != 0)
		{
			if (NULL == (log_fd = fopen(LOG_FILE1, "w")))
			{
				zbx_rsm_errf(stderr, "cannot open file \"%s\" for writing: %s", LOG_FILE1, strerror(errno));
				exit(EXIT_FAILURE);
			}
		}

		if (SUCCEED != zbx_get_dnskeys(res, tld, res_ip, &keys, log_fd, &res_ec, err, sizeof(err)))
		{
			zbx_rsm_err(stderr, err);
			if (0 == ignore_err)
				goto out;
		}
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
			goto out;
		}
	}

	if (SUCCEED != zbx_get_ns_ip_values(res, ns, ns_ip, keys, testprefix, tld, log_fd, &rtt, NULL, ipv4_enabled,
					ipv6_enabled, 0, err, sizeof(err)))
	{
		zbx_rsm_err(stderr, err);
		if (0 == ignore_err)
			goto out;
	}

	printf("OK (RTT:%d)\n", rtt);
out:
	if (log_to_file != 0)
	{
		if (0 != fclose(log_fd))
			zbx_rsm_errf(stderr, "cannot close log file: %s", strerror(errno));
	}

	if (NULL != keys)
		ldns_rr_list_deep_free(keys);

	if (NULL != res)
	{
		if (0 != ldns_resolver_nameserver_count(res))
			ldns_resolver_deep_free(res);
		else
			ldns_resolver_free(res);
	}

	exit(EXIT_SUCCESS);
}
