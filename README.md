## [Zabbix](http://www.zabbix.com/)

This repo is a git mirror of [svn.zabbix.com](https://zabbix.org/wiki/Get_Zabbix). For the latest development see the trunk branch.

*Contribution* (namely issues and patches) information can be found at the [Get involved!](http://www.zabbix.com/developers.php) page.

See [Notes](NOTES.md) for more information on how this mirror was built and is kept up-to-date.

This repository has the following additional branches compared to the official svn.zabbix.com:

* This INFO branch
* pulssi-trunk branch that follows the latest merged svn tag (see below) and contains Pulssi specific changes
* pulssi-x.y.z tags that are tagged release versions containing Pulssi specific changes

Manual update procedure of pulssi-trunk to a later svn tag (here to version x.y.z):

```
git checkout pulssi-trunk
git checkout -b x.y.z-update
git merge x.y.z
```

Next, make a pull request from x.y.z-update to pulssi-trunk branch.
