# Tenant-isolation acceptance

Validated locally: **3 October 2026**. Full backend regression suite:
**116 tests / 1,588 assertions passed**. PHP syntax, dependency-container lint
and diff validation also passed.

This is a code-level acceptance of the tested company boundaries, not a complete
production penetration test or a certification of every endpoint and staff role.
No new frontend, table, import screen or parallel synchronization workflow was added.

## 1. What was tested

The HTTP tests use the real Symfony firewall, authenticated sessions, router and
controllers. They create two independent companies with owner accounts, products,
manufacturers, categories, warehouses, media, customers, orders and connections.
Fixtures intentionally reuse product numbers and source customer/order IDs.

Business fixtures run in the separate `_test` database inside transactions and
are rolled back. Temporary files are confined to unique fixture directories and
removed afterwards. No public registration, live shop mutation, ordinary queue
consumption or application-server/worker restart is involved.

| Boundary | Acceptance |
| --- | --- |
| Guessed foreign IDs | Tested product/detail/variant/publication, category, manufacturer, media, stock/warehouse, customer/order/picking and integration/import/export requests return 404. Denied writes leave both companies and the Messenger table unchanged. |
| Lists, search and selectors | Tested catalogue, Sales, integration, media, warehouse, stock and active-run responses do not contain the other company's records or credential values. |
| Client tenant spoofing | Supplying another company ID in the query or `X-Tenant-ID` does not change the authenticated company. |
| Relationship and bulk mutations | Mixed-company deletion, category/manufacturer product assignment, category moves, stock-count creation and export selection are rejected without partial writes. |
| Missing/ambiguous membership | Business requests return 403 when the user has no membership or more than one possible active company. |
| Import identity | Identical SKUs, order/customer/address source IDs and line IDs resolve within their own company/connection. Historical replay remains idempotent and creates no stock movement. |
| File storage | Session media, product media, signed connector delivery and Shopware uploads share the tenant-path resolver. Foreign keys, traversal, foreign Media ownership and file/directory symlink escapes are rejected. |
| Private media response | Session and capability downloads share private/no-store, no-referrer, nosniff and sandboxed CSP headers. A valid capability cannot authorize a foreign storage path. |
| Export jobs | Both providers' shared workflow rejects mismatched tenant, connection, plan and run IDs before credential-dependent calls, provider hooks or continuation dispatch. |
| Ongoing Woo Sales jobs | A message carrying one company's tenant and another's connection does not call the provider, create a cursor or change a run. |
| Credentials/cache | Companies using the same provider URL decrypt only their connection's credentials and reuse only their own cached settings. Refreshing one company's settings leaves the other's cache intact. |
| Import logs | New files and entries include tenant scope. Readers require exact run/connection ownership, reject conflicting or malformed tenant claims and cannot read another company's new log directory. |

Existing regression coverage also remains for signed-capability expiry/tampering,
stock-dispatch tenant selection, publication identity, provider retries, inventory
operations and health-report isolation. Global currency/locale definitions remain
shared reference data; this does not make company-owned taxes, units or operational
records global.

## 2. Defects found and fixes

### Manufacturer selection silently accepted foreign product IDs

Previously, replacing a manufacturer's products with a foreign ID returned success
and could clear its legitimate assignments. The request now validates every UUID
and resolves the entire selection in the owning tenant **before** changing anything.
Foreign, missing, malformed and duplicate selections are rejected. Valid replacement
and clearing remain supported, without modifying the other company.

The implementation also queries only the requested and currently assigned products,
rather than loading the company's entire catalogue.

### File ownership stopped at the database row

Authenticated download routes previously concatenated the stored key without
checking the tenant filesystem boundary. Signed delivery confined the file to the
application's `var` directory, not the narrower tenant directory. No public API
allowing arbitrary storage-key writes was found in this check; the regression
deliberately constructs inconsistent stored rows to exercise defense in depth.

`TenantMediaStorage` now validates both Media ownership and the canonical file path.
Supported layouts are:

- Current: `var/media/<tenant UUID>/…`.
- Legacy: `var/product-media/<tenant UUID>/…`.

Path components cannot be empty, `.` or `..`, contain backslashes/NULs, or redirect
through a symlink outside the owning tenant root. The shared response policy also
avoids Symfony's default public binary-file caching and restricts inline content.
Both existing legacy images were verified readable with a read-only check.

### Implicit first-membership company selection

The previous lookup could choose an arbitrary membership if a user belonged to
multiple companies. The shared `TenantMembershipRepository::forUser()` now returns
a company only for exactly one membership. All current controller company lookups,
including login/current-user restoration, use it.

**Company switching is not implemented by this change.** Multi-company accounts
fail closed until an explicit server-validated active-company/session workflow is
implemented. Do not enable multi-company invitations while relying on an arbitrary
first row. A read-only check found zero ambiguous accounts in the current demo DB.
Existing single-company owner access is unchanged; staff permission rollout remains
the separately deferred membership/roles milestone.

### Shared import-log directory

New logs use:

```text
var/log/integrations/<tenant UUID>/<connector>-YYYY-MM-DD.log
```

Each entry includes tenant, connection and run IDs. Inconsistent run/connection
ownership is rejected before reading or writing. Older flat log files are retained
and remain readable only through an owned run with matching connection/run IDs;
an explicit conflicting or malformed tenant claim is rejected. No historical log
was deleted or moved. Adapt log collectors to discover the tenant subdirectories.
These are application boundaries, not separate operating-system permissions for
each tenant; the trusted backend process still has access to its storage roots.

## 3. Reproduce

From `backend`, use the usual test database configuration. Doctrine appends `_test`
to the configured database name; never run fixture acceptance against a production
database. For the existing local test setup:

```bash
DATABASE_URL='postgresql://root@127.0.0.1:5432/steelcode_connect?serverVersion=16&charset=utf8' \
php vendor/bin/phpunit --filter 'TenantApiIsolationTest|TenantQueueAndCredentialsIsolationTest|TenantMediaStorageTest|IntegrationLogTenantScopeTest|CatalogueMediaDeliveryScopeTest'

DATABASE_URL='postgresql://root@127.0.0.1:5432/steelcode_connect?serverVersion=16&charset=utf8' \
php vendor/bin/phpunit

php bin/console lint:container --env=test
```

The HTTP tests verify denied requests against before/after record and queue
snapshots. Queue tests invoke the real ownership-resolution workflows with a
provider boundary that fails the test if any request is attempted; these are not
new live-shop load measurements.

## 4. What still remains for deployment

- Public HTTPS image reachability, TLS/proxy configuration and capability-query
  redaction from the destination shop's network.
- Role-by-role and membership-invitation acceptance before non-owner staff rollout;
  explicit validated company switching before multi-company accounts are enabled.
- Production secret management, key rotation, credential-access policy and outbound
  integration URL/network policy, including private-network access restrictions.
- Upload/remote-media policy review, retention, backup restoration, complete audit
  coverage and the original PostgreSQL RLS decision. No claim of database-level
  isolation from arbitrary unscoped SQL is made here.
- Representative live large-catalogue throughput, customer traffic, hosting limits
  and abrupt in-flight worker crash/redelivery acceptance.

Do not reopen completed Inventory/Sales modules or add another platform to cover
these release checks. See [release readiness](sync-release-readiness.md) and
[implementation status](implementation-status.md).
