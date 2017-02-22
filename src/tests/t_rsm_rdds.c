#include "t_rsm.h"
#include "../zabbix_server/poller/checks_simple_rsm.c"

#define DEFAULT_TESTPREFIX	"whois.nic"
#define DEFAULT_MAXREDIRS	10

void	exit_usage(const char *progname)
{
	fprintf(stderr, "usage: %s -t <tld> <[-i <ip43>] [-w <ip80>]> [-p <testprefix43>] [-q <query80>] [-m <maxredirs80>] [-g] [-h]\n", progname);
	fprintf(stderr, "       -t <tld>          TLD to test\n");
	fprintf(stderr, "       -i <ip43>         IP address of RDDS server to test\n");
	fprintf(stderr, "       -w <ip80>         IP address of Whois server to test\n");
	fprintf(stderr, "       -p <testprefix43> domain testprefix to use in RDDS43 test (default: %s)\n",
			DEFAULT_TESTPREFIX);
	fprintf(stderr, "       -q <query80>      query to use in RDDS43 test\n");
	fprintf(stderr, "       -m <maxredirs80>  maximum redirections to use in RDDS80 test (default: %d)\n",
			DEFAULT_MAXREDIRS);
	fprintf(stderr, "       -g                ignore errors, try to finish the test\n");
	fprintf(stderr, "       -h                show this message and quit\n");
	exit(EXIT_FAILURE);
}

int	main(int argc, char *argv[])
{
	char	err[256], *tld = NULL, *ip43 = NULL, *ip80 = NULL, *testprefix = DEFAULT_TESTPREFIX, ignore_err = 0,
		testname[ZBX_HOST_BUF_SIZE], *query = NULL;
	int	c, index, rtt43 = -1, rtt80 = -1, maxredirs = DEFAULT_MAXREDIRS;
	FILE	*log_fd = stdout;

	opterr = 0;

	while ((c = getopt (argc, argv, "t:i:w:p:q:m:gh")) != -1)
	{
		switch (c)
		{
			case 't':
				tld = optarg;
				break;
			case 'i':
				ip43 = optarg;
				break;
			case 'w':
				ip80 = optarg;
				break;
			case 'p':
				testprefix = optarg;
				break;
			case 'q':
				query = optarg;
				break;
			case 'm':
				maxredirs = atoi(optarg);
				break;
			case 'g':
				ignore_err = 1;
				break;
			case 'h':
				exit_usage(argv[0]);
			case '?':
				if (optopt == 't' || optopt == 'i' || optopt == 'w' || optopt == 'p' || optopt == 'm' ||
						optopt == 'q')
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

	if (NULL != ip80 && NULL == query)
	{
		fprintf(stderr, "query [-q] must be specified for RDDS80 test\n");
		exit_usage(argv[0]);
	}

	if (NULL == tld || (NULL == ip43 && NULL == ip80))
		exit_usage(argv[0]);

	printf("tld:%s, ip43:%s, ip80:%s, testprefix:%s\n", tld, (ip43 ? ip43 : "NONE"), (ip80 ? ip80 : "NONE"),
			testprefix);

	zbx_snprintf(testname, sizeof(testname), "%s.%s", testprefix, tld);

	if (NULL != ip43 && SUCCEED != zbx_rdds43_test(testname, ip43, 43, ZBX_RSM_TCP_TIMEOUT, NULL, &rtt43,
			err, sizeof(err)))
	{
		zbx_rsm_errf(log_fd, "RDDS43 of \"%s\" (%s) failed: %s", ip43, testname, err);
		if (0 == ignore_err)
			goto out;
	}

	if (NULL != ip80)
	{
		if (is_ip6(ip80))
			zbx_snprintf(testname, sizeof(testname), "http://[%s]", ip80);
		else
			zbx_snprintf(testname, sizeof(testname), "http://%s", ip80);

		if (SUCCEED != zbx_rdds80_test(query, testname, 80, ZBX_RSM_TCP_TIMEOUT, maxredirs, &rtt80,
				err, sizeof(err)))
		{
			zbx_rsm_errf(log_fd, "RDDS80 of \"%s\" (%s) failed: %s", testname, query, err);
			if (0 == ignore_err)
				goto out;
		}
	}

	printf("OK (RTT43:%d RTT80:%d)\n", rtt43, rtt80);
out:
	exit(EXIT_SUCCESS);
}
