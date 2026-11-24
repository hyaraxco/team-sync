# Staff role authority checks

Only `superadmin` can assign privileged roles or change an existing role set. HR keeps ordinary profile maintenance, staff-account creation, and unchanged multi-role payloads. HR cannot change another privileged account's email or password.

`StaffMemberRoleAuthorityTest` covers dummy principals, multipart `POST` with `_method=PUT`, rejection before validation, and atomic rejection across users, profiles, job, bank, contacts, and role pivots. `StaffMemberEdit.smoke.test.js` covers the client omitting an unchanged role selector. `Step2JobInfo.role.test.js` covers the selector offering only staff plus the current role to non-superadmins.

Run the affected suites in the disposable offline sandbox. Build the source image from the pre-provisioned local toolchain, then run both phases:

```sh
docker build --network=none --pull=false \
    --build-arg TOOLCHAIN_IMAGE=team-sync-tea13-toolchain:fd4096 \
    --build-arg SOURCE_SHA="$(git rev-parse HEAD)" \
    -f scripts/sandbox/Dockerfile -t team-sync-5a6:source .
sh scripts/sandbox/run.sh team-sync-5a6:source --phase be --seconds 1800
sh scripts/sandbox/run.sh team-sync-5a6:source --phase fe --seconds 1800
```

The sandbox uses an empty environment, no external network, a read-only source and toolchain, a scratch tmpfs, and finite CPU, memory, PID, file, and wall limits. These are request-level regressions with SQLite fixtures. They do not prove deployment policy, MySQL behavior, or coverage of every route. No development database is reset and no dependency is downloaded.

Local results in the offline sandbox (`scripts/sandbox`):

- Scoped BE (`tests/Feature/StaffMember`, `StaffMemberProfileStoreRequestTest`, `StaffMemberProfileDtoTest`): 97 passed, 920 assertions, exit 0.
- Full BE suite: 1823 passed, 1 skipped (1824 total), 9060 assertions, exit 0.
- Full FE suite: 1178 passed across 151 files, exit 0.
- FE build (`vite build`): passed (10.20s), exit 0.
- Coverage denominators are reported by the sandbox collector per phase. Coverage is not evidence that untested routes are safe.

