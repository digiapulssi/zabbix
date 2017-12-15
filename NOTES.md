### How this mirror was built

Currently a nightly build runs the following process:

```sh
# Note original mirror of SVN takes a very long time
# git svn clone --stdlayout svn://svn.zabbix.com # original mirror
git svn fetch
git checkout trunk  # first time: git checkout -b trunk
git reset remotes/origin/trunk --hard
git branch -D master # hack; git svn re-creates master branch
# git push --mirror git@github.com:digiapulssi/svn.zabbix.com.git # destructive to other branches
git push --all git@github.com:digiapulssi/zabbix.git
# explicitly push SVN related refs (i.e. for svn->git tags)
git push git@github.com:digiapulssi/zabbix.git refs/remotes/*
git push git@github.com:digiapulssi/zabbix.git refs/remotes/origin/tags/*:refs/tags/*
```

During clone encountered `Complex regular subexpression recursion limit (32766)` error.
See [stackoverflow article](https://stackoverflow.com/questions/24074208/git-svn-fetch-could-not-unmemoize-function-check-cherry-pick-because-it-was).
As a workaround, applied `rm -rf .git/svn/.caches` and continued clone afterwards.

There also was errors concerning bad ref `.git/logs/refs/remotes/origin/svn:` (including colon
encoded as %3A at git side). The ref caused problems with automatic prune and as a workaround,
automatic prune was disabled with `git config --global gc.auto 0`.

