#include "t_rsm.h"
#include "../poller/checks_simple_rsm.c"

int main()
{
	char		err[256], *res_ip = "156.154.102.25", *ns = "ns3.cctld.co", *ns_ip = "156.154.102.25",
			*domain = "co", proto = ZBX_RSM_UDP, ipv4_enabled = 1, ipv6_enabled = 1,
			*testprefix = "www.nonexistent.23242432";
	int		res_ec, rtt;
	ldns_resolver	*res = NULL;
	ldns_rr_list	*keys = NULL;
	FILE		*log_fd = stdout;

	/* create resolver */
	if (SUCCEED != zbx_create_resolver(&res, "resolver", res_ip, proto, ipv4_enabled, ipv6_enabled, log_fd,
			err, sizeof(err)))
	{
		zbx_rsm_errf(log_fd, "cannot create resolver: %s", err);
		goto out;
	}

	if (SUCCEED != zbx_get_dnskeys(res, domain, res_ip, &keys, log_fd, &res_ec, err, sizeof(err)))
	{
		zbx_rsm_err(log_fd, err);
		goto out;
	}

	if (SUCCEED != zbx_get_ns_ip_values(res, ns, ns_ip, keys, testprefix, domain, log_fd, &rtt,
			NULL, ipv4_enabled, ipv6_enabled, 0, err, sizeof(err)))
	{
		zbx_rsm_err(log_fd, err);
	}

	printf("OK\n");
out:
	if (NULL != keys)
		ldns_rr_list_deep_free(keys);

	if (NULL != res)
	{
		if (0 != ldns_resolver_nameserver_count(res))
			ldns_resolver_deep_free(res);
		else
			ldns_resolver_free(res);
	}

	return 0;
}
