# SteelCode Connect — tehnička specifikacija

> Verzija 0.1 · 24. juli 2026 · status: početni plan za MVP

## 1. Sažetak proizvoda

SteelCode Connect je multi-tenant SaaS za centralno upravljanje produktnim katalogom i njegovu sinhronizaciju između dobavljača, webshopova i marketplace kanala.

Glavna vrijednost proizvoda je: **jedan centralni katalog → više prodajnih kanala**.

Platforma ne smije tretirati Shopware kao obavezno središte sistema. Shopware je jedan od kanala, kao što su OLX i Ananas; KimTec, Comtrade i ERP-ovi su izvori podataka. Time su podržani i tokovi bez webshopa, npr. `KimTec → OLX`.

```text
Dobavljači / izvori                 SteelCode Connect                    Kanali
KimTec, Comtrade, CSV/XML, ERP  →  normalizovani katalog, pravila  →  Shopware, OLX,
postojeći Shopware                 cijena i sync orkestracija          Ananas, WooCommerce…
```

## 2. MVP granice

Prvi isporučivi vertikalni tok:

```text
Tenant kreira firmu
  → poveže KimTec
  → uveze do 100 proizvoda
  → pregleda/normalizuje centralni katalog
  → mapira kategoriju i pravila cijena
  → objavi odabrane proizvode u Shopware i/ili OLX
  → vidi rezultat, historiju i mogućnost ponavljanja neuspjelog posla
```

### U MVP-u

- Email/password prijava, firma (tenant), članovi i osnovne uloge.
- KimTec source connector: test veze, puni uvoz, promjena cijene i zalihe.
- Centralni katalog: proizvodi, varijante, mediji, svojstva, izvorni podaci.
- Shopware 6 channel connector: kreiranje/ažuriranje proizvoda, cijene, zalihe i medija.
- OLX channel connector, **nakon potvrde dostupnog OLX API/pristupnog modela**: objava, izmjena, deaktivacija i slike.
- Mape kategorija i atributa po kanalu.
- Pravila odabira proizvoda i pravila cijena po vezi izvor → kanal.
- Asinhroni sync, idempotentnost, retry, error log i ručno pokretanje synca.
- Administracija konekcija, proizvoda, mapiranja, pravila i sync runova.

### Izvan MVP-a

- Ananas, Shopify, WooCommerce, Pantheon i dodatni dobavljači.
- Dvosmjerni import narudžbi i rezervacija zalihe.
- Pretplate, naplata, napredne dozvole, 2FA, marketplace za third-party dodatke.
- Mikroservisi, Elasticsearch i zaseban data warehouse.

## 3. Tehnološke odluke

| Dio | Odluka | Napomena |
| --- | --- | --- |
| Backend | Symfony **7.4 LTS**, PHP 8.4+ | LTS ima sigurnosnu podršku do novembra 2029; Symfony 8.1 je aktuelni stable, ali mu je kratkoročna podrška. |
| API | Symfony kontroleri + OpenAPI | API Platform je opcionalan samo za jednostavne resurse; sync ostaje eksplicitan custom API. |
| ORM i migracije | Doctrine ORM + Doctrine Migrations | PostgreSQL-specifične migracije su dozvoljene gdje stvarno donose korist. |
| Baza | PostgreSQL **18** (uvijek zadnji sigurnosni patch) | Primarna, trajna poslovna baza; `jsonb` samo za fleksibilne/izvorne payloadove. |
| Queue / cache / lock | Redis + Symfony Messenger, Cache i Lock | Odvojeni queue nazivi po vrsti opterećenja. |
| Scheduler | Symfony Scheduler | Pokreće periodične syncove; ne obavlja težak rad u HTTP procesu. |
| Admin | **Nuxt UI Dashboard** | UI temelj za Nuxt 4 aplikaciju; donosi Vue 3, Nuxt UI, Tailwind CSS i gotove dashboard obrasce. |
| Frontend data | Nuxt data fetching + Pinia | Nuxt upravlja rutama i dohvatom podataka; Pinia čuva samo lokalni/UI kontekst. |
| Storage | S3-compatible storage | Mediji i uvozni artefakti; lokalni MinIO u developmentu. |
| Observability | Monolog JSON, Sentry, health endpointi | Svaki sync ima correlation ID. |
| Local development | Docker Compose | API, worker, scheduler, PostgreSQL, Redis, MinIO i frontend. |

Koristiti zaključane verzije u `composer.lock` i lockfileu frontenda, automatizovane security updateove i redovni dependabot/renovate pregled. Ne uvoditi Kubernetes, RabbitMQ ili mikroservise prije stvarne potrebe.

Referentni linkovi: [Symfony releases](https://symfony.com/releases), [Vue documentation](https://vuejs.org/guide/introduction.html), [PostgreSQL documentation](https://www.postgresql.org/docs/), [Redis documentation](https://redis.io/docs/latest/).

## 4. Arhitektura

Početna arhitektura je **modularni monolit**. Jedan deployable backend i jedna PostgreSQL baza pojednostavljuju razvoj, transakcije i operacije, a moduli imaju stroge granice da se kasnije mogu izdvojiti ako za to nastane mjerljiv razlog.

```text
Vue SPA ── HTTPS/session ──> Symfony API ─────────────> PostgreSQL
                                │  │                      Redis
                                │  └──────────────> S3/MinIO
                                ▼
                       Messenger transports
                         ├─ catalog workers
                         ├─ publication workers
                         ├─ media workers
                         └─ failed transport
                                │
             dobavljači / Shopware / OLX / drugi vanjski API-ji
```

### Struktura repozitorija

```text
steelcode-connect/
├── backend/
│   ├── src/{Identity,Tenant,Connection,Catalog,Mapping,Pricing,Sync,Integration,Shared}/
│   ├── config/
│   ├── migrations/
│   └── tests/{Unit,Integration,Functional}/
├── frontend/                            # Nuxt 4 application
│   └── {app,pages,components,composables,stores,i18n}/
├── packages/distribution-contracts/    # tek kada ga dijele backend i Shopware plugin
├── infrastructure/{docker,nginx}/
├── docs/
└── compose.yaml
```

Svaki backend modul prati praktičnu podjelu `Domain`, `Application`, `Infrastructure` i `UI`. Ne praviti ceremonijalni puni DDD gdje nema poslovne složenosti; granice modula i testabilan application sloj su važniji.

## 5. Connector model

Konektor je ugrađeni Symfony modul/adaptor, a u UI-u se može predstaviti kao “plugin”. Ne dozvoljavati proizvoljno izvršavanje third-party PHP koda u MVP-u.

| Tip | Smjer | Primjeri |
| --- | --- | --- |
| Source connector | eksterni sistem → centralni katalog | KimTec, Comtrade, CSV/XML, ERP, Shopware import |
| Channel connector | centralni katalog → eksterni sistem | Shopware, OLX, Ananas, WooCommerce, Shopify |

Jedan provider može implementirati oba tipa. Connector definicija izlaže `key`, naziv, tip, konfiguracijsku šemu i capability-je (`product_create`, `price_update`, `stock_update`, `media_upload`, kasnije `order_import`). API vraća definicije UI-u, koji iz njih prikazuje dostupne konekcije.

Adapteri rade samo s normalizovanim DTO-ima i nemaju direktan pristup HTTP kontrolerima. Za svaku vanjsku operaciju moraju podržavati timeout, rate limit, sigurno logovanje i klasifikaciju greške na retryable/non-retryable.

## 6. Podatkovni model

Svaka poslovna tabela ima `tenant_id`, `id`, `created_at` i `updated_at` kada je primjenjivo. ID-jevi su UUIDv7. Novac je `numeric(19,4)` + ISO valuta; nikada `float`. Količine su `numeric(19,4)`.

| Područje | Ključne tabele | Svrha |
| --- | --- | --- |
| Identitet | `tenants`, `users`, `tenant_memberships`, `sessions` | Firma, korisnici, role i browser sesije. |
| Konekcije | `connector_definitions`, `connections`, `connection_secrets` | Konfiguracija source/channel veze; tajne su enkriptovane. |
| Katalog | `products`, `product_variants`, `product_media`, `product_properties`, `source_product_records` | Centralni, normalizovani katalog i neizmijenjeni ulazni zapis. |
| Mapiranje | `category_mappings`, `attribute_mappings`, `channel_product_overrides` | Transformacija centralnog modela prema zahtjevima kanala. |
| Pravila | `publication_rules`, `price_rules` | Selektor proizvoda, marže, PDV, provizije i zaokruživanje. |
| Objave | `channel_listings` | Veza proizvod/varijanta ↔ eksterni listing, eksterni ID, status, hash payload-a i zadnja greška. |
| Sync | `sync_runs`, `sync_jobs`, `sync_errors`, `outbox_messages` | Historija, napredak, greške i pouzdano slanje poruka. |
| Audit | `audit_logs` | Ko je promijenio konekciju, pravilo ili mapiranje. |

Obavezna ograničenja uključuju jedinstvenost `(tenant_id, connection_id, external_id)` za source zapise i `(tenant_id, channel_connection_id, product_variant_id)` za listing. Indeksirati svaku kombinaciju koja sadrži `tenant_id` i služi pretragama/syncu.

`source_product_records.raw_payload` čuva originalni `jsonb` payload za dijagnostiku. Normalizovana polja (`sku`, `ean`, naziv, cijena, količina, brand) ostaju relacijska i indeksabilna.

## 7. Multi-tenancy i sigurnost

- Jedna aplikacija i jedna baza za MVP; izolacija se provodi `tenant_id`-om.
- `TenantContext` se uspostavlja iz autentifikovane sesije ili servisnog identiteta. Repository/application metode eksplicitno zahtijevaju tenant, bez implicitnih “globalnih” upita.
- Uvesti PostgreSQL Row-Level Security kao dodatni sloj prije prvog produkcijskog tenanta, nakon što se discipline konekcija i migracija testiraju. Aplikacijska provjera ostaje obavezna.
- Vue koristi `Secure`, `HttpOnly`, `SameSite` session cookie i CSRF zaštitu. Ne spremati browser access token u `localStorage`.
- Credentials distributera/kanala šifrovati aplikacijskim ključem iz secret managera; ne vraćati ih kroz API i redigovati ih u logovima.
- Vanjski connectori koriste provider-specifični OAuth2/client credentials ili API ključ. Rotacija tajni i audit izmjena su obavezni.
- Role za MVP: `owner`, `admin`, `operator`, `viewer`.

## 8. Sync engine

Sync run je trajna poslovna evidencija, a Messenger poruke su izvršni mehanizam. Statusi: `created → queued → running → partially_completed|completed|failed|cancelled`.

```text
1. Scheduler ili korisnik kreira SyncRun.
2. StartSync poruka učitava stranicu/batch od sourcea.
3. Svaki batch se validira, čuva kao source record i normalizuje u katalog.
4. Pravila određuju koji proizvodi idu na koji kanal.
5. Odvojene poruke grade channel payload i kreiraju/ažuriraju/deaktiviraju listing.
6. Ishod, pokušaj, vanjski request ID i greška zapisuju se u SyncRun/SyncJob.
```

Transporti: `catalog`, `publication`, `media`, `maintenance`, `failed`. Worker se skaluje po transportu. Duge operacije nikad ne teku u HTTP requestu.

Za pouzdanost:

- Handleri su idempotentni: ponovljen job ne smije kreirati dupli eksterni listing.
- Koristiti stabilni idempotency key i `channel_listings` kao vezu s eksternim ID-jem.
- Outbox pattern zapisuje promjenu stanja i poruku u istoj PostgreSQL transakciji; dispatcher je šalje nakon commita.
- Retry samo transient grešaka (mreža, 429, 5xx); exponential backoff + jitter i maksimalni broj pokušaja.
- Poštovati provider rate limit i koristiti Lock za sprječavanje paralelnih syncova iste konekcije.
- “Failed” poruke i sync greške moraju se vidjeti u UI-u i biti bezbjedne za ručni retry.

## 9. Cijene, pravila i mapiranja

Cijena kanala je izračun, ne polje koje se prepisuje preko nabavne cijene:

```text
nabavna cijena
+ marža / fiksna naknada
+ marketplace provizija
+ dostava i porezni tretman
→ provjera minimalne marže
→ zaokruživanje
= cijena za kanal
```

Pravila su verzionisana i imaju jasan prioritet: `tenant default → source/channel pravilo → eksplicitni product override`. Za MVP podržati samo deklarativne uslove (kategorija, brand, cijena, zaliha, eksplicitni odabir), bez korisničkog skriptnog jezika.

Mapiranja kategorija i atributa su zasebni resursi; connector validira obavezna polja prije objave. Channel override pokriva naslov, opis, kategoriju, slike i vrijednosti atributa specifične za kanal.

## 10. API i frontend

API je verzionisan pod `/api/v1` i dokumentovan OpenAPI specifikacijom koja se generiše u CI-u. Primjeri resursa/akcija:

```text
POST   /auth/login                 POST /auth/logout
GET    /connectors                 GET/POST/PATCH /connections
POST   /connections/{id}/test
GET    /products                   GET/PATCH /products/{id}
GET/PUT /mappings/categories       GET/PUT /price-rules
POST   /sync-runs                  GET /sync-runs/{id}
POST   /sync-runs/{id}/retry
GET    /channel-listings           GET /audit-logs
```

Akcije kao test konekcije, pokretanje synca i retry su namjenske komande, ne generički CRUD. Lista proizvoda mora imati cursor paginaciju, filtriranje i sortiranje na serveru.

Frontend je baziran na [Nuxt UI Dashboard templateu](https://github.com/nuxt-ui-templates/dashboard), a ne na ručno dizajniranom dashboardu. Nuxt 4, Nuxt UI i Tailwind pružaju layout-e, navigaciju, stranice, tabele, forme, validaciju, modale, notifikacije, dark mode i i18n kao početnu tačku za administrativne funkcije.

Ne pravimo zaseban design system niti custom dashboard komponentu kada Nuxt UI već pruža odgovarajući obrazac. Custom kod je ograničen na SteelCode poslovne ekrane i connector-specifičnu funkcionalnost, sastavljenu iz postojećih Nuxt UI komponenti.

Nuxt stranice/moduli: auth, onboarding, connections, catalog, mappings, pricing, sync i settings. Nuxt upravlja rutama i server-state dohvatom; Pinia čuva samo sesijski/UI kontekst. Sync detalji koriste polling dok MVP ne uvede SSE.

## 11. Testiranje, operacije i kvaliteta

- Unit testovi za pricing, selekciju, normalizaciju i transformacije payload-a.
- Integration testovi s PostgreSQL/Redis za repozitorije, outbox i Messenger handlere.
- Contract testovi s snimljenim provider odgovorima za svaki connector.
- Functional testovi za najvažnije API tokove i E2E test za: konekcija → import → mapping → publish → retry.
- PHPStan na najvišem praktičnom nivou, Rector, PHP-CS-Fixer i ESLint/Prettier/TypeScript strict u CI-u.
- Health endpointi provjeravaju aplikaciju, PostgreSQL, Redis i storage; ne izlažu tajne.
- Strukturisani logovi s `tenant_id`, `connection_id`, `sync_run_id` i correlation ID-jem. Sentry upozorenje za trajne greške i neuspjele syncove.
- Dnevni PostgreSQL backup, testiran restore, lifecycle pravila za S3 i retention politika za raw payloadove/logove.

## 12. Faze izgradnje

1. **Temelj:** monorepo, Docker Compose, Symfony/Vue skeleton, CI, auth, tenant i audit infrastruktura.
2. **Konekcije:** connector registry, secrets, test konekcije, sync run model i Messenger/outbox.
3. **Katalog:** KimTec import, normalizacija, katalog UI i media storage.
4. **Objava:** category mapping, pricing engine, Shopware publish/update, historija i retry.
5. **OLX:** prvo potvrditi API, dozvole, rate limit i obavezne atribute; zatim implementirati adapter i end-to-end tok.
6. **Hardening:** RLS, load test, backup/restore test, alerting i pilot tenant.

## 13. Otvorene odluke prije implementacije konektora

- Koji tačno KimTec feed/API, autentifikacija, frekvencija i polja su dostupni?
- Da li OLX nudi odgovarajući službeni API za kreiranje/izmjenu oglasa za ciljne korisnike, te koje su njegove komercijalne i tehničke granice?
- Ko je autoritativni izvor zalihe kada proizvod postoji na više kanala i kada se uvedu narudžbe?
- Koja valuta, PDV pravila, skladišta i pravila zaokruživanja su potrebna u prvom tržištu?
- Da li Shopware integracija cilja samo Shopware 6 i koje minimalne verzije podržava?

Ove odgovore treba pretvoriti u connector-specific contracte i acceptance kriterije prije procjene rokova.
