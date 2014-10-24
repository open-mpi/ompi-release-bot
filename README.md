Simple bot for setting various attributes on the
(ompi-release)[https://github.com/open-mpi/ompi-release] Github repo.
This bot is necessary because, by long-standing tradition/convention,
the bulk of the Open MPI developer community does not have write
access to the release branches (i.e., the
(ompi-release)[https://github.com/open-mpi/ompi-release] repo).

In Github terms, this means that all the OMPI Github devs only have
"read only" access to this repo.  As such, they can file pull
requests, but they cannot assign labels, milestones, or users to them.
That's a bummer.

As such, we have this bot that monitors the comments on the
ompi-release pull requests.  If it sees various special keywords in a
comment on a pull request, it assigns the requested labels,
milestones, and users to to that pull request.

This is basically a workaround because while we don't want the devs to
have push/write access to the git repo, we *do* want to allow them to
assign labels, milestones, and users to pull requests.
