# Release policy

Merging a PR does not publish a tag, GitHub Release, or production deployment.
The Release workflow runs validation tests on relevant PRs and main pushes;
its publish job runs only on an explicit manual dispatch from `main`.

## Publish a release

1. Choose a full commit SHA already on `main` and review the changes to release.
2. Verify BE Tests and FE Tests succeeded on that exact SHA, including their
   build/accessibility steps. If path filters did not run a suite, dispatch
   `be-tests.yml` and `fe-tests.yml` on `main` and verify their actual SHA.
   Pending, failed, skipped, missing, and PR-only runs do not qualify.
3. Choose an unused stable `vMAJOR.MINOR.PATCH` greater than all existing stable
   versions. Decide the bump explicitly, including breaking changes.
4. With maintainer approval, run Release from Actions on `main`, entering the
   version and full SHA. This creates an annotated tag and publishes release
   notes. It does not deploy the application.

The publisher serializes manual runs, validates ancestry and exact-SHA CI,
and refuses existing versions. Never delete or move an existing tag to retry.
If tagging succeeds but release publication fails, inspect that partial state
and obtain explicit approval for recovery; rerunning does not republish it.

Manual dispatch is a permission-controlled action, not a second-person approval
gate. Required reviewers need a separately configured GitHub Environment and
a supported repository/plan. No such protection is claimed by this workflow.
