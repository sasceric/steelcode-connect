# Sync queue operations

Updated: 3 October 2026. Applies to Shopware/Woo catalogue publication and the
existing Shopware/Woo Sales and inventory stock queues. Business users continue
using the same Integration, History and Logs screens.

Integration log collectors must include tenant subdirectories:
`var/log/integrations/<tenant UUID>/<connector>-YYYY-MM-DD.log`. Entries include
tenant, connection and run IDs. Historical flat logs are retained and the UI
filters them by owned run/connection; no migration or deletion is required.
See [Tenant-isolation acceptance](tenant-isolation-acceptance.md).

## 1. Why separate lanes

| Lane | Work | Local command in `backend` |
| --- | --- | --- |
| Control | Scheduler and lightweight coordination | `composer sync:control` |
| Sales | Ongoing Shopware/Woo Sales polling | `composer sync:sales` |
| Stock | Scheduled inventory-outbox dispatch | `composer sync:stock` |
| Catalogue | Automatic Shopware/Woo reconciliation and incremental exports | `composer sync:catalogue` |
| Bulk (`async`) | Catalogue/Sales historical imports, manual previews and Sync now | `composer imports:consume` |

Use **five separate terminals/processes**, not a single combined consumer for
all lanes. A busy bulk consumer must not occupy the Sales/stock/control consumers.
No Redis/RabbitMQ service is required: the lanes use the existing Doctrine
Messenger transport and database. The failure transport remains `failed`.
This follows Symfony's [separate-transport worker guidance](https://symfony.com/doc/7.4/messenger.html#prioritized-transports).

Workers disable query/debug profiling and exit after one hour or at their memory
limit (128 MB control, 256 MB others). In production your systemd/Supervisor
services restart each lane on exit and after reboot. In a local terminal, rerun
the command after an intentional exit. A stopped local consumer is an operational
state, not missing application functionality.

Start with one control/scheduler process and one consumer per other lane. Measure
backlog and host/database capacity before adding concurrency. Catalogue advisory
locks and active-run checks serialize publication per tenant/connection. Different
connections can progress independently. Dedicated tenant workers/shards can be
introduced later without changing domain workflows; strict per-tenant CPU quotas
are not implemented by the least-recently-dispatched ordering.

For an operator's targeted stock recovery, use
`php bin/console app:stock-sync:dispatch --tenant=TENANT_UUID --limit=100 --no-debug`.
The tenant must exist; another tenant's events are not selected. Omitting `--tenant`
preserves the scheduler's global processing behaviour. The normal Sales-freshness,
saved stock authority, locking and failure-history guards still apply; this is not
a way to bypass them.

## 2. Deploy and migrate safely

1. Apply the database migrations, including `Version20261001160000` (debounce
   timestamps, reconciliation leases and export checkpoints), `Version20261002100000`
   (parent-first partial work indexes), `Version20261002100500` (started-write
   recovery markers), `Version20261002101000` (safe missing stock-identity repair)
   and `Version20261002102000` (concurrent reverse-identity index). The last migration
   must not run inside an all-or-nothing transaction.
2. Gracefully retire background consumers at message boundaries. Never kill the
   browser, frontend or API to deploy worker changes. On a supervised production
   deployment use `messenger:stop-workers` and the process manager's normal reload.
   Run the stop command with the worker's same environment/debug/cache settings:
   for local `--no-debug` consumers, use
   `php bin/console messenger:stop-workers --no-debug`. A stop signal written to
   another cache namespace may not reach them. Check the exact worker PIDs and
   graceful exit before starting replacements; do not restart the browser,
   frontend or API to reload a queue handler.
   Locally coordinate retirement of a user's foreground worker first.
3. Rebuild the correct environment's container/cache, then start all five lanes.
   A running PHP worker cannot load new handlers merely because files changed.
   For this development setup with debug disabled:

   ```bash
   APP_DEBUG=0 php bin/console cache:clear --env=dev --no-debug
   APP_DEBUG=0 php bin/console debug:messenger --env=dev --no-debug
   ```

4. Keep consuming `async`: already queued messages retain their old transport.
   Do not delete/requeue the whole backlog just because routing changed.
   New automatic catalogue continuations use `catalogue`; new ongoing Sales and
   stock jobs use their own lanes. Checkpoints retain the original run/history.
5. Verify History progresses and terminal errors are absent. Inspect failed
   messages before selectively retrying; do not blindly replay all failures.

The one-hour/memory recycling settings are process-manager inputs, not a promise
that the app will start Supervisor itself. Use the deployment's credential key,
database and shared lock configuration consistently across hosts.

## 3. Expected catalogue behaviour

1. An ordinary tenant product edit records/coalesces a durable change hint in the
   same database workflow. Saving never waits for remote publication.
2. A five-second control tick selects ready connections; edits wait five seconds
   after their latest change or thirty seconds after their first continuous edit.
3. At most one leased reconciliation is queued per connection. On failure its
   lease remains while Messenger retries; an abandoned lease expires after ten
   minutes. The coordinator performs no Shopware HTTP requests.
4. A scan checks at most 100 candidates and queues at most 25 changed products
   plus required parents. Unchanged fingerprints skip publication. Baselines and
   candidates are loaded in batches; nested payload associations still need their
   domain reads. Not every ORM association has been converted to a batch loader.
5. Frozen preview, full-source preflight and guarded publication process separate
   chunks. Each phase yields after 25 items or a soft ten-second budget at the
   next safe boundary. Publication sends at most 25 products in one Shopware Sync
   or Woo batch API request. Woo variations use their parent's batch endpoint.
   Preparing dependencies/media, final validation, the HTTP write and
   read-back can extend that soft budget; it is not a hard ten-second deadline.
   Validation fallback also yields remaining pending items at that soft budget;
   it does not hold a consumer for twenty-five slow individual requests.
   Parents are acknowledged before children enter another request. Sources and
   destination/reference snapshots are re-read before each write; the source and
   destination checks are never disabled to achieve throughput.
6. API/network/rate-limit problems delay catalogue continuations rather than
   sleeping in the consumer. Provider Retry-After and bounded phase retries are
   respected. When a handler propagates a temporary provider error, the shared
   Sales/stock/catalogue retry strategy respects its delay without unlimited
   retries. Stock events caught and persisted by the outbox retain that outbox's
   retry workflow rather than failing the whole dispatcher. A
   permanent item error records a failure and lets other items run.
7. A one-minute reconciliation page catches out-of-band changes when edit hints
   are absent. Large catalogues take multiple pages; this is not a full scan per
   tick. A repeating unchanged failed source backs off fifteen minutes.

There is **no guaranteed queue wait or crash-proof claim**. Latency depends on
consumer availability, arrival rate, Shopware response times, media and database
capacity. An active import holds catalogue publication on its own connection.
Sales and stock retain their existing safety/freshness guards. Changing products
does not bypass inventory ownership or send warehouse quantities in the catalogue
payload. Historical imports still page inside their existing bulk handlers.

## 4. Observe before scaling

In Export, inspect the shared progress card and pending-hint/oldest-age alert.
An age above two minutes is a warning to investigate, not an automatic failure:
an active import, validation conflict, provider outage or rate limit may explain it.

Read one tenant/connection's backlog, scan lease and active run phases:

```bash
php bin/console app:catalogue-sync:status TENANT_UUID CONNECTION_UUID
```

This emits JSON without credentials or product/customer payloads and rejects a
connection outside that tenant. It is read-only and suitable for an operator's
monitoring collector. A zero backlog does not prove that every product is valid;
inspect failed/blocked plans and reconciliation activity too.

This command now supports **Shopware and WooCommerce**. Add `--check` for
health-based exit status (0 healthy, 1 critical, 2 warning). It includes stale
queued/running jobs, latest-run failures, Sales checkpoint freshness and explicitly
tenant-scoped stock counts. Configure your collector/notification sink to consume
it; it does not replace process-manager checks or install an external alert service.
Thresholds and concurrent/fault evidence are in [release readiness](sync-release-readiness.md).

Process-level checks (operator-only):

```bash
php bin/console messenger:stats control sales stock catalogue async failed
php bin/console messenger:failed:show --stats
```

Alert on worker exits, failed messages, growing ready backlog, stale active-run
heartbeats, stale Sales checkpoints, failed stock outbox events and unresolved
operational exceptions. Track arrival/processing rate, job duration, API latency,
429 count, worker memory and database load. Queue counts alone are not a tenant
security boundary; do not expose global transport statistics in a tenant API.

For troubleshooting, first identify the affected lane. Do not create a second
import to unblock a stopped consumer. Inspect the connection's latest error and
cancel/rebuild a conflicting export preview when appropriate. Already written
Shopware records are not rolled back by cancellation.

## 5. Scope of this optimization

This change isolates workloads and checkpoints the shared Shopware/Woo export
workflow. Provider payloads remain separate; stock authority is unchanged.
It does not convert every historical import into a resumable chunk job.
Production load testing and capacity thresholds still require the actual pilot
traffic and hosting environment. Existing acceptance evidence is recorded in
`shopware-catalogue-publication.md` and `woocommerce-catalogue-publication.md`;
do not equate fixture tests with a production
throughput guarantee.

## 6. Measured local acceptance — 2 October 2026

The five local lanes were started after gracefully retiring the old background
consumer. Catalogue/bulk consumers were refreshed again after the batch changes.
The browser, frontend on port 3000 and API on port 8000 were not stopped.

`backend/tests/Benchmark/catalogue-scale.php` exercises the real exporter,
PostgreSQL and ORM against a **simulated, zero-latency Shopware boundary**. The
fixture contains existing inactive products, content-only updates, no image
uploads and a one-product second tenant. Automatic and bulk continuations are
cooperatively interleaved; this is **not** simultaneous-worker or production
capacity acceptance. Memory includes the entire simulated destination catalogue.

| Products in bulk tenant | Core workflow time | Simulated API calls | Maximum delivery time | Second-tenant completion | Peak fixture memory |
| --- | --- | --- | --- | --- | --- |
| 100 | 0.881 s | 55 | 0.105 s | 0.246 s | 18 MB |
| 1,000 | 9.015 s | 379 | 0.169 s | 0.195 s | 24 MB |
| 20,000 | 213.337 s | 7,219 | 6.901 s | 13.680 s | 86 MB |

Every run completed with exact item counts, unchanged inactive/stock values and
zero inventory movements. The 20,000 run shared the host with regression tests;
the slower maximum delivery/second-tenant result must not be replaced with the
sub-second small-fixture result. A real shop adds its API/network/indexing/media
costs. These numbers are not a promise that a real 20,000-product export completes
in 213 seconds, or that every tenant update finishes in thirteen seconds.

Before API batching, the same committed 20,000-record fixture used **82,417 HTTP
calls** and **170.513 s** of zero-latency core work. Batching reduced calls by
about **91%**, while adding a final fresh-source validation pass. CPU-only timing
did not improve in the concurrent run; the benefit is fewer expensive real API
round-trips, not an invented production throughput claim. There are still roughly
49 ORM/SQL statements per product in this content-only fixture; further association
batch-loading should be guided by production query/API profiling.

An earlier 20,000 baseline using one long rollback transaction was intentionally
stopped: its MVCC row history distorted throughput and blocked another fixture's
currency insert. It is not a valid before/after timing. Large tests now commit
normal chunks in a separate disposable `*_benchmark_test` database. Default runs
still roll back; `--committed` refuses any other database suffix. Example, after an
operator creates a separate test-schema database:

```bash
APP_ENV=test APP_DEBUG=0 \
DATABASE_URL='postgresql://root@127.0.0.1:5432/steelcode_connect_catalogue_benchmark?serverVersion=16&charset=utf8' \
php tests/Benchmark/catalogue-scale.php 20000 --committed
```

Doctrine adds `_test` in this environment. Remove only that disposable benchmark
database afterwards; never run the committed fixture against the application's
demo/production database. No 20,000-record publication was sent to the real shop.

The actual disposable Shopware connection also received an ordinary ORM edit of
the existing inactive QA product through the five-second Scheduler → control →
catalogue → preview/preflight/publication path: **one update, zero failures**, about
**9 seconds** from edit to completion, stock **0**, inactive status retained and
the local inventory checksum unchanged. The original name's queued restoration
completed in about **12 seconds**, again with zero failures. That test caught and fixed a guard which
incorrectly compared auto-resolved plan mappings with saved settings. A regression
now verifies the distinction. The QA name and original export scope are restored
after checking the queued restoration. The dedicated benchmark database and its
generated products were removed; application demo data was not deleted.

The full backend suite passes **79 tests / 887 assertions**, including bounded
API batch sizes, legacy counter repair, rejection isolation, a 503 after a remote
commit, no duplicate writes on redelivery and preservation of an external edit
during the outage. Container lint and diff checks also pass.

At the final check, all five work lanes had zero queued backlog. The failure
transport retained eleven **older** messages dated 30–31 July and 1 October
(five product imports, four Woo Sales jobs and two catalogue exports). They were
not new failures from this acceptance and were not deleted or blindly replayed;
review their original error/configuration before any selective retry.

For a production pilot, measure incremental edit-to-publication p95/p99, API
response/indexing time, batch sizes, 429s, arrival rate and per-tenant backlog
under real simultaneous workers. Start with one consumer per lane; add catalogue
capacity only after checking database/provider limits. A sub-minute incremental
target is a target to validate, not a guaranteed SLA. Strict tenant capacity
quotas, dedicated tenant queues and production load acceptance are still separate
operational work; tenant boundaries are enforced independently of queue fairness.
