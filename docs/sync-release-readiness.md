# Sync release readiness

Updated: 3 October 2026. Applies to catalogue, Sales and inventory sync. Reuse
Integration Export, History, Logs and inventory exceptions; these checks add no
parallel UI or publication workflow. This record is not a production throughput SLA.

## 1. Concurrent and recovery acceptance

`backend/tests/Benchmark/catalogue-scale.php` now runs three independent tenant
processes with committed PostgreSQL/ORM work: 20,000, 1,000 and 25 products.
Every four records contain one parent and one resolved Blue variant; the remaining
records are simple products. The large tenant includes 5,000 variants.

With `--doctrine-queue`, continuations are serialized, sent, received and acknowledged
using actual Doctrine Messenger transport tables. Queue names are unique to each
fixture in a dedicated database. No application queue is consumed. Child database
sockets are created after fork rather than sharing a live connection.

The provider is still a **zero-latency simulated Shopware HTTP boundary**. These
are not 20,000 records published to a real shop. Separate fixture consumers test
concurrent progress, not shared-pool tenant fairness or a production SLA.

Each run verifies exact unique publications, one write per item, parent identities
confined to the correct tenant, unchanged inactive status/stock 47 and zero inventory
movements. Replayed acknowledged and foreign-tenant messages must not write.
Handlers are recreated and in-process queue state discarded; continuations must
remain in PostgreSQL. This does not simulate an actual process kill at an arbitrary
instruction or database failover.

`--faults` injects a 429, a transport timeout and a 503 **after a remote commit**
for every tenant. Actual delayed queue availability is respected. Recovery must
not duplicate writes. Reports include chunk p95/p99, maximum, five slowest phases,
monotonic duration, PHP CPU, queries and memory (including simulated destination data).

### Measurements before the reverse-identity index

| Fixture | Completion | Chunk p95 / p99 | Maximum | Result |
| --- | --- | --- | --- | --- |
| Fresh DB: 20,000 / 5,000 variants | 218.881 s | 0.215 / 0.323 s | 0.858 s | 20,000 unique writes, no failures/movements |
| Concurrent 1,000 / 250 variants | 7.256 s | 0.106 / 0.109 s | 0.167 s | 1,000 unique writes, no failures/movements |
| Concurrent 25 / 6 variants | 0.263 s | 0.102 / 0.102 s | 0.102 s | 25 unique writes, no failures/movements |
| Fault run: two 1,000-product tenants | 72.549 / 72.548 s | — | 4.941 / 4.944 s | All injected faults recovered |
| Fault run: concurrent 25 products | 49.274 s | — | 0.100 s | All injected faults recovered |

An earlier durable-queue run on a reused benchmark database took **630.266 s**
with a **133.313 s** chunk. A fresh repeat did not reproduce that outlier; its
cause was not conclusively established. Do not hide it, attribute it to provider
latency (HTTP was simulated), or use only the fastest result to promise latency.
Representative host/database/network tracing remains a production gate.

### Final indexed run with faults and durable queues

| Fixture | Completion including retry delays | Chunk p95 / p99 | Maximum |
| --- | --- | --- | --- |
| 20,000 / 5,000 variants | 211.668 s | 0.100 / 0.126 s | 0.298 s |
| Concurrent 1,000 / 250 variants | 58.402 s | 0.110 / 0.113 s | 0.140 s |
| Concurrent 25 / 6 variants | 50.097 s | 0.124 / 0.124 s | 0.124 s |

All three recovered the injected 429/timeout/committed-503. The large tenant had
20,000 unique writes/publications, 49 rejected acknowledged replays, 94 MB peak
fixture memory and zero inventory movements. It used 9,613 simulated HTTP calls
and 1,121,540 SQL statements (including durable queue polling during delayed retries).
Database work still warrants future association batching guided by profiling;
the index does not eliminate all per-product ORM queries. Before/after runs have
different fault/table-history conditions, so these are not a controlled percentage
speedup or a promised real-shop completion time.

## 2. Defects found and corrected

### Live message-boundary worker recovery — 3 October

`tests/Acceptance/live-worker-recovery.php` boots the normal application services
with a test-only container. It changes only transport queue names and the cache
directory to a unique acceptance namespace. Catalogue selection, preview, preflight,
publication, credentials, logging, counters, locks and continuations all use the
existing production implementations. It starts real `messenger:consume` child
processes with `--limit=1`; each process exits normally after its delivery, and a
fresh process consumes the durable continuation. No ordinary queue, user worker,
browser, frontend or API is stopped. This proves **graceful recycling at message
boundaries**, not SIGKILL during an HTTP call or a database commit.

The disposable tenant was `019f9612-5dc1-7f5a-87ee-d6ff7fbfa253`.
Both destinations used real local HTTP, not the simulated benchmark provider:

| Destination | Mixed selection | Catalogue worker processes, including completed replay | Observed mid-publication restarts | Result |
| --- | --- | --- | --- | --- |
| `shopware67.test` | 30 simple, 2 parents, 7 variants: **39 records** | 8 | 25/39 and 36/39 published | 39 updates; zero creates/failures |
| `wp-test.test` | 30 simple, 2 parents, 3 variations: **35 records** | 9 | 25/35, 32/35 and 33/35 published | 35 updates; zero creates/failures |

Exact run IDs remain visible in Integration History:

- Shopware: `01a0ff1e-dfa3-73d9-9e30-25f6ef615d54`.
- Woo: `01a0ff1f-918c-7d6b-acfd-4933f62c7f05`.

All 74 items have started-write markers. Mappings, remote image identities,
stock, active/status and parent identities remained unchanged during these
content-only exports. Re-delivering each completed initial message through another
actual worker left the run counters and destination snapshots unchanged. The
read-only `verify-recovery-content.php` compared each acknowledged resolved owned
payload with live provider data using the existing exporter's canonical comparison:
**39 Shopware and 35 Woo payloads matched**, including the variants.

Publication generated 39 coalesced tenant stock notifications. The separate
`finish-recovery-stock.php` refreshed both saved continuous Sales workflows through
owned Sales workers, then ran the existing outbox command through an owned stock
worker, restricted with `--tenant`. All 39 events completed; live read-back verified
**37 Shopware leaf quantities** against active fulfillment warehouse availability.
The two parent notifications do not publish independent stock pools. Warehouse
balances and movement counts were unchanged by outbound stock dispatch.

Saved stock authority was respected: Shopware uses `connect`; Woo uses source-shop
authority (legacy stored value `shopware`). Woo stock authority was **not enabled**
or overwritten for this check. This is therefore not another acceptance of
Connect-authoritative Woo stock. Both connection health reports finished `ok`,
fresh Sales checkpoints and **zero pending/failed tenant stock events**. All
acceptance queues were empty, temporary export settings were restored, and history
was retained. These point-in-time results do not start permanent local consumers.

From `backend`, reproduce only against the approved disposable destinations:

```bash
php tests/Acceptance/live-worker-recovery.php TENANT SHOPWARE_CONNECTION --allow-demo-update
php tests/Acceptance/live-worker-recovery.php TENANT WOO_CONNECTION --allow-demo-update
php tests/Acceptance/verify-recovery-content.php TENANT SHOPWARE_RUN
php tests/Acceptance/verify-recovery-content.php TENANT WOO_RUN
php tests/Acceptance/finish-recovery-stock.php TENANT --allow-demo-update
```

The last command sends ordinary aggregate stock only where saved authority permits;
it refuses unapproved stock-authoritative destinations. Do not run these mutation
checks on a customer's production connection. These small content-only updates
are not fresh image-upload, large real-shop throughput or arbitrary crash tests.

### Preserve stock publication identity

Both exporters created/updated `ProductChannelPublication` without the external
product ID, clearing the identity stock sync needs. Catalogue history could succeed
while stock failed with “The published product has no external ID.” Both exporters
now acknowledge visibility and the verified destination ID atomically with mappings,
baselines and item counters. Regression tests cover creation, update and recovery.

Migration `Version20261002101000` restores only missing IDs from one unambiguous
mapping belonging to the publication's tenant, connection and product. Known IDs,
ambiguous/cross-tenant mappings and invalid provider IDs are untouched. It changes
no warehouse balances, deletes no failures and bypasses no Sales freshness guard.
Skipped mappings require manual review.

The demo tenant had 99 failed stock events. After repair and ordinary five-lane
processing, both connection health reports showed fresh Sales checkpoints, zero
pending/failed stock events and no alerts. This recovery wrote ordinary aggregate
stock to the disposable shops, as authorized; it did not erase inventory history.

### Index local-to-external mapping lookup

EXPLAIN showed reverse lookups scanning **21,025 mapping rows**. Migration
`Version20261002102000` adds `(tenant_id, connection_id, entity_type, local_id)`
with `CREATE INDEX CONCURRENTLY`; ORM metadata declares the index too. The measured
lookup now uses it. It is non-unique so ambiguous historical mappings remain visible.
This migration is non-transactional: do not wrap it in an all-or-nothing deployment
transaction. An interrupted concurrent index build needs operator inspection before retry.

### Correct health timestamp ages

ORM run, Sales cursor and outbox timestamps are UTC without timezone. Their ages
now compare explicitly against PostgreSQL's UTC clock, avoiding a false two-hour
age under this local database's Europe/Sarajevo session. SQL-generated catalogue
hints retain the database clock used by their existing writes. No global timezone
or historical timestamps were rewritten.

## 3. Monitoring contract

```bash
php bin/console app:catalogue-sync:status TENANT_UUID CONNECTION_UUID --check --no-debug
```

This operator-only command supports Shopware and WooCommerce and emits read-only
JSON. It rejects a connection outside the supplied tenant and returns no credentials,
customer/product payloads or provider error bodies. Existing backlog/active-run fields
remain, with health/alerts, latest run, Sales checkpoint, thresholds and stock counts.

With `--check`: exit 0 healthy, 1 critical, 2 warning. Invalid arguments also use
standard CLI exit 2 but do not emit valid health JSON; collectors must reject
non-JSON errors. Without `--check`, a valid report exits 0 for backward compatibility.

| Signal | Default threshold | Severity |
| --- | --- | --- |
| Automatic catalogue hint age | 120 s | Warning |
| Queued integration run age | 120 s | Warning |
| Running heartbeat age | 900 s | Warning |
| Latest run failed/failed items/blocked plan | Current latest run | Critical |
| Enabled continuous Sales missing/unadvanced/error/stale checkpoint | 300 s | Critical |
| Failed stock outbox events | Any | Critical |
| Pending stock outbox age | 120 s | Warning |

Override pending/heartbeat thresholds with `--backlog-seconds` and
`--stale-run-seconds`. These are investigation thresholds, not SLAs; Retry-After
may legitimately extend a wait. Disabled automatic/continuous modes do not create
missing-checkpoint/backlog alerts just because that feature is off. Outbox counts
are explicitly **tenant** scope: one event can serve multiple connections, so
collectors should deduplicate stock alerts by tenant.

Configure your existing monitoring collector to poll every minute, notify on
warning/critical transitions and resolve on recovery. Monitor process exits, global
failure transport, host/database load, TLS expiry, backups and lane availability
separately. This command cannot prove worker liveness. No third-party notification
sink or hosting credentials were installed by this local change.

## 4. Media and tenant security

The local two-company API/job/file/credential acceptance and its fixes are recorded
in [Tenant-isolation acceptance](tenant-isolation-acceptance.md). This validates the
tested code paths, not every endpoint, production hosting policy or deferred staff
role. Company resolution now fails closed for absent/ambiguous membership; no
company-switching UI was added. New import logs are tenant-namespaced, with exact
run/connection filtering retained for historical flat logs.

Woo sideloads need `CATALOGUE_MEDIA_BASE_URL` pointing at the public HTTPS API
origin. In `prod`, URL generation rejects HTTP, private/loopback IPs, local/test names,
credentials, queries and fragments. Development still permits local HTTP fixtures.
Syntax validation neither resolves DNS nor proves public reachability/TLS.

Capabilities bind tenant, active connection, media, filename and checksum and expire
after fifteen minutes. Delivery requires a confined existing image file. The shared
resolver confines session downloads and Shopware uploads to the same tenant roots,
including the legacy layout, and rejects symlink escapes. Tests cover tampering,
foreign scope and a replaced checksum. All media responses use private/no-store,
no-referrer, nosniff and restrictive sandboxed CSP so inline SVG cannot execute scripts.
Those headers do not prevent the shop's ordinary HTTP image downloader.

Before deploying, from the **destination shop's network**:

1. Generate a fresh capability for a selected image; treat the URL as a secret.
2. Fetch through the production proxy without disabling certificate verification.
3. Verify 200 and expected image MIME/bytes, not HTML/login/private-host redirects.
   Ensure proxy logs redact capability query strings.
4. Verify expired URLs are denied, then obtain a new capability.
5. Publish a fresh gallery/variant image and replay; verify cover and attachment reuse.

There is no public HTTPS production endpoint in this local `.test` setup. These
network/TLS checks are **deployment gates**, not completed tests. Full credential-key
rotation, tenant permission audit, upload review, backup/restore, retention and the
original RLS decision also remain deployment work. Non-owner staff rollout waits for
the separately deferred membership/roles work.

## 5. Reproduce safely

Create a dedicated database from the test schema using the existing database owner;
do not grant CREATE DATABASE to the application role. Apply current migrations,
including the reverse identity index. Doctrine appends `_test` to the URL database:

```bash
APP_ENV=test APP_DEBUG=0 \
DATABASE_URL='postgresql://root@127.0.0.1:5432/steelcode_connect_release_benchmark?serverVersion=16&charset=utf8' \
php tests/Benchmark/catalogue-scale.php 20000 \
  --committed --concurrent --mixed --doctrine-queue --faults
```

Committed mode refuses any database without the `_benchmark_test` suffix. Fault
mode requires concurrent durable queues so delay stamps are honored. Only fixture
processes are managed; browser, frontend/API and user workers are never killed.
Save the JSON result, then remove **only** the exact disposable benchmark database.
Progress goes to stderr and the final result to stdout (local PHP startup warnings
may precede it). Media fixtures and the temporary test database are not production data.

## 6. Remaining release gate

Final validation: **116 backend tests / 1,588 assertions passed**, including health
tenant boundaries, UTC freshness, stock destination identity/migration, signed
media scope, real HTTP company boundaries, ambiguous-membership rejection,
credential/cache and job ownership, tenant log namespaces and existing provider
retry contracts. PHP syntax, container lint and diff checks passed. Tenant-targeted
stock dispatch leaves another tenant's pending event untouched. No frontend
components changed in this hardening step.
Both exact disposable benchmark databases were removed after recording results;
the application and normal test databases were retained. Fixtures can be regenerated
with the documented command; their removed data is not backed up.

Still measure a **real** 20,000+ mixed catalogue with network/indexing/media costs,
incremental edit-to-publication p95/p99, simultaneous customer traffic and abrupt worker
crash/redelivery inside an in-flight remote write in the intended hosting environment.
Actual graceful message-boundary worker recycling passed on both demo shops above;
do not confuse that with an arbitrary process kill. Establish supported consumer
and tenant/connection limits from that evidence. Do not advertise a sub-minute SLA
or arbitrary tenant capacity from the simulated-provider fixture.

Related: [queue operations](sync-queue-operations.md),
[implementation status](implementation-status.md),
[Inventory and Sales guide](inventory-and-sales-guide.md).
