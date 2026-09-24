# Creation of a new Vehicle.

# Illuminate\Database\QueryException - Internal Server Error

SQLSTATE[22P02]: Invalid text representation: 7 ERROR:  invalid input syntax for type numeric: ""
CONTEXT:  unnamed portal parameter $10 = '' (Connection: pgsql, Host: db, Port: 5432, Database: rental, SQL: insert into "vehicles" ("registration", "make", "model", "year", "category_id", "fuel_type", "transmission", "mileage_km", "daily_rate", "weekly_rate", "monthly_rate", "entered_service_at", "notes", "version", "updated_at", "created_at") values (DEMO003, Lexus, Lexus XGS, 2026, 2, hybrid, automatic, 6000, 50000, , , 2026-07-15 00:00:00, ?, 1, 2026-09-24 12:37:05, 2026-09-24 12:37:05) returning "id")

PHP 8.4.25
Laravel 13.32.0
localhost:8080

## Stack Trace

0 - vendor/laravel/framework/src/Illuminate/Database/Connection.php:857
1 - vendor/laravel/framework/src/Illuminate/Database/Connection.php:813
2 - vendor/laravel/framework/src/Illuminate/Database/Connection.php:426
3 - vendor/laravel/framework/src/Illuminate/Database/Connection.php:412
4 - vendor/laravel/framework/src/Illuminate/Database/Query/Processors/PostgresProcessor.php:24
5 - vendor/laravel/framework/src/Illuminate/Database/Query/Builder.php:4313
6 - vendor/laravel/framework/src/Illuminate/Database/Eloquent/Builder.php:2336
7 - vendor/laravel/framework/src/Illuminate/Database/Eloquent/Model.php:1691
8 - vendor/laravel/framework/src/Illuminate/Database/Eloquent/Model.php:1607
9 - vendor/laravel/framework/src/Illuminate/Database/Eloquent/Model.php:1411
10 - app/Modules/Catalog/Actions.php:145
11 - vendor/laravel/framework/src/Illuminate/Database/Concerns/ManagesTransactions.php:35
12 - vendor/laravel/framework/src/Illuminate/Database/DatabaseManager.php:502
13 - vendor/laravel/framework/src/Illuminate/Support/Facades/Facade.php:364
14 - app/Modules/Catalog/Actions.php:122
15 - app/Livewire/CatalogDirectory.php:70
16 - vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php:36
17 - vendor/laravel/framework/src/Illuminate/Container/Util.php:43
18 - vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php:96
19 - vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php:35
20 - vendor/livewire/livewire/src/Wrapped.php:23
21 - vendor/livewire/livewire/src/Mechanisms/HandleComponents/HandleComponents.php:583
22 - vendor/livewire/livewire/src/Mechanisms/HandleComponents/HandleComponents.php:224
23 - vendor/livewire/livewire/src/LivewireManager.php:132
24 - vendor/livewire/livewire/src/Mechanisms/HandleRequests/HandleRequests.php:220
25 - vendor/laravel/framework/src/Illuminate/Routing/ControllerDispatcher.php:46
26 - vendor/laravel/framework/src/Illuminate/Routing/Route.php:276
27 - vendor/laravel/framework/src/Illuminate/Routing/Route.php:216
28 - vendor/laravel/framework/src/Illuminate/Routing/Router.php:822
29 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:180
30 - vendor/livewire/livewire/src/Mechanisms/HandleRequests/RequireLivewireHeaders.php:19
31 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:219
32 - vendor/laravel/boost/src/Middleware/InjectBoost.php:22
33 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:219
34 - app/Http/Middleware/AccountAccess.php:29
35 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:219
36 - vendor/laravel/framework/src/Illuminate/Routing/Middleware/SubstituteBindings.php:52
37 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:219
38 - vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/PreventRequestForgery.php:104
39 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:219
40 - vendor/laravel/framework/src/Illuminate/View/Middleware/ShareErrorsFromSession.php:48
41 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:219
42 - vendor/laravel/framework/src/Illuminate/Session/Middleware/StartSession.php:120
43 - vendor/laravel/framework/src/Illuminate/Session/Middleware/StartSession.php:63
44 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:219
45 - vendor/laravel/framework/src/Illuminate/Cookie/Middleware/AddQueuedCookiesToResponse.php:36
46 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:219
47 - vendor/laravel/framework/src/Illuminate/Cookie/Middleware/EncryptCookies.php:74
48 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:219
49 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:137
50 - vendor/laravel/framework/src/Illuminate/Routing/Router.php:821
51 - vendor/laravel/framework/src/Illuminate/Routing/Router.php:800
52 - vendor/laravel/framework/src/Illuminate/Routing/Router.php:764
53 - vendor/laravel/framework/src/Illuminate/Routing/Router.php:753
54 - vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php:200
55 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:180
56 - vendor/livewire/livewire/src/Features/SupportDisablingBackButtonCache/DisableBackButtonCacheMiddleware.php:19
57 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:219
58 - vendor/laravel/mcp/src/Server/Middleware/AddWwwAuthenticateHeader.php:21
59 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:219
60 - vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/ConvertEmptyStringsToNull.php:27
61 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:219
62 - vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/TrimStrings.php:47
63 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:219
64 - vendor/laravel/framework/src/Illuminate/Http/Middleware/ValidatePostSize.php:27
65 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:219
66 - vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/PreventRequestsDuringMaintenance.php:110
67 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:219
68 - vendor/laravel/framework/src/Illuminate/Http/Middleware/HandleCors.php:61
69 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:219
70 - vendor/laravel/framework/src/Illuminate/Http/Middleware/TrustProxies.php:58
71 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:219
72 - vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/InvokeDeferredCallbacks.php:22
73 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:219
74 - vendor/laravel/framework/src/Illuminate/Http/Middleware/ValidatePathEncoding.php:28
75 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:219
76 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:137
77 - vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php:175
78 - vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php:144
79 - vendor/laravel/framework/src/Illuminate/Foundation/Application.php:1227
80 - public/index.php:20
81 - vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php:23

## Previous exception

### 1. PDOException

SQLSTATE[22P02]: Invalid text representation: 7 ERROR:  invalid input syntax for type numeric: ""
CONTEXT:  unnamed portal parameter $10 = ''

0 - vendor/laravel/framework/src/Illuminate/Database/Connection.php:440
1 - vendor/laravel/framework/src/Illuminate/Database/Connection.php:440
2 - vendor/laravel/framework/src/Illuminate/Database/Connection.php:846
3 - vendor/laravel/framework/src/Illuminate/Database/Connection.php:813
4 - vendor/laravel/framework/src/Illuminate/Database/Connection.php:426
5 - vendor/laravel/framework/src/Illuminate/Database/Connection.php:412
6 - vendor/laravel/framework/src/Illuminate/Database/Query/Processors/PostgresProcessor.php:24
7 - vendor/laravel/framework/src/Illuminate/Database/Query/Builder.php:4313
8 - vendor/laravel/framework/src/Illuminate/Database/Eloquent/Builder.php:2336
9 - vendor/laravel/framework/src/Illuminate/Database/Eloquent/Model.php:1691
10 - vendor/laravel/framework/src/Illuminate/Database/Eloquent/Model.php:1607
11 - vendor/laravel/framework/src/Illuminate/Database/Eloquent/Model.php:1411
12 - app/Modules/Catalog/Actions.php:145
13 - vendor/laravel/framework/src/Illuminate/Database/Concerns/ManagesTransactions.php:35
14 - vendor/laravel/framework/src/Illuminate/Database/DatabaseManager.php:502
15 - vendor/laravel/framework/src/Illuminate/Support/Facades/Facade.php:364
16 - app/Modules/Catalog/Actions.php:122
17 - app/Livewire/CatalogDirectory.php:70
18 - vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php:36
19 - vendor/laravel/framework/src/Illuminate/Container/Util.php:43
20 - vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php:96
21 - vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php:35
22 - vendor/livewire/livewire/src/Wrapped.php:23
23 - vendor/livewire/livewire/src/Mechanisms/HandleComponents/HandleComponents.php:583
24 - vendor/livewire/livewire/src/Mechanisms/HandleComponents/HandleComponents.php:224
25 - vendor/livewire/livewire/src/LivewireManager.php:132
26 - vendor/livewire/livewire/src/Mechanisms/HandleRequests/HandleRequests.php:220
27 - vendor/laravel/framework/src/Illuminate/Routing/ControllerDispatcher.php:46
28 - vendor/laravel/framework/src/Illuminate/Routing/Route.php:276
29 - vendor/laravel/framework/src/Illuminate/Routing/Route.php:216
30 - vendor/laravel/framework/src/Illuminate/Routing/Router.php:822
31 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:180
32 - vendor/livewire/livewire/src/Mechanisms/HandleRequests/RequireLivewireHeaders.php:19
33 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:219
34 - vendor/laravel/boost/src/Middleware/InjectBoost.php:22
35 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:219
36 - app/Http/Middleware/AccountAccess.php:29
37 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:219
38 - vendor/laravel/framework/src/Illuminate/Routing/Middleware/SubstituteBindings.php:52
39 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:219
40 - vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/PreventRequestForgery.php:104
41 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:219
42 - vendor/laravel/framework/src/Illuminate/View/Middleware/ShareErrorsFromSession.php:48
43 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:219
44 - vendor/laravel/framework/src/Illuminate/Session/Middleware/StartSession.php:120
45 - vendor/laravel/framework/src/Illuminate/Session/Middleware/StartSession.php:63
46 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:219
47 - vendor/laravel/framework/src/Illuminate/Cookie/Middleware/AddQueuedCookiesToResponse.php:36
48 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:219
49 - vendor/laravel/framework/src/Illuminate/Cookie/Middleware/EncryptCookies.php:74
50 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:219
51 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:137
52 - vendor/laravel/framework/src/Illuminate/Routing/Router.php:821
53 - vendor/laravel/framework/src/Illuminate/Routing/Router.php:800
54 - vendor/laravel/framework/src/Illuminate/Routing/Router.php:764
55 - vendor/laravel/framework/src/Illuminate/Routing/Router.php:753
56 - vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php:200
57 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:180
58 - vendor/livewire/livewire/src/Features/SupportDisablingBackButtonCache/DisableBackButtonCacheMiddleware.php:19
59 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:219
60 - vendor/laravel/mcp/src/Server/Middleware/AddWwwAuthenticateHeader.php:21
61 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:219
62 - vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/ConvertEmptyStringsToNull.php:27
63 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:219
64 - vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/TrimStrings.php:47
65 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:219
66 - vendor/laravel/framework/src/Illuminate/Http/Middleware/ValidatePostSize.php:27
67 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:219
68 - vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/PreventRequestsDuringMaintenance.php:110
69 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:219
70 - vendor/laravel/framework/src/Illuminate/Http/Middleware/HandleCors.php:61
71 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:219
72 - vendor/laravel/framework/src/Illuminate/Http/Middleware/TrustProxies.php:58
73 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:219
74 - vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/InvokeDeferredCallbacks.php:22
75 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:219
76 - vendor/laravel/framework/src/Illuminate/Http/Middleware/ValidatePathEncoding.php:28
77 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:219
78 - vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:137
79 - vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php:175
80 - vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php:144
81 - vendor/laravel/framework/src/Illuminate/Foundation/Application.php:1227
82 - public/index.php:20
83 - vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php:23

## Request

POST /livewire-90b3c140/update

## Headers

* **host**: localhost:8080
* **connection**: keep-alive
* **content-length**: 1142
* **sec-ch-ua-platform**: "Linux"
* **user-agent**: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36
* **sec-ch-ua**: "Brave";v="153", "Not_A Brand";v="8", "Chromium";v="153"
* **content-type**: application/json
* **x-livewire**: 1
* **sec-ch-ua-mobile**: ?0
* **accept**: */*
* **sec-gpc**: 1
* **origin**: http://localhost:8080
* **sec-fetch-site**: same-origin
* **sec-fetch-mode**: cors
* **sec-fetch-dest**: empty
* **referer**: http://localhost:8080/vehicles
* **accept-encoding**: gzip, deflate, br, zstd
* **accept-language**: en-US,en;q=0.6
* **cookie**: XSRF-TOKEN=eyJpdiI6Ik81VnRTZGxOd0d4ZEM5VFIyRlY0cVE9PSIsInZhbHVlIjoiSi8wZForalYxckU4cWFKN3g3UldCaERXOEFpQWN0UjZ0a1kyYjAwYjdEakhwZUF5SFkvZ2w0Y0xRTXViaGpDaDBUazYyZUQ5Qzh6eDU5anBBQ016RDlhbmRwUGh6bjM4NDlXaTc4TGJYam15U3RNS0RnZE9LOXoyNW82UzlQTVgiLCJtYWMiOiI4OTdmNzQ0YjljYWU0MzU5N2UxNzNlNjk5YjU4NTZjNDBiOGVlNzQ1MWU0ZDUzZjAxZWJmOTVmYWIwNjlhNDM2IiwidGFnIjoiIn0%3D; agence-location-session=eyJpdiI6IkNGUTR5TDNCUlJGZGFIcGVwaXYrZUE9PSIsInZhbHVlIjoiR2pDemZlQWFYbXpRRG8yWGpqbGtXbXZwTWE0Z1A4RmkwOTA3OWIxdjdtRXdia0NrcFdPdGUwci9NVHFnTHdteVBMWGViNnVDWm51aWVDL1k4TjlIOHpvOEVSTnpZN00zUy8wbGJJVHNEZVUzZXJIb0VOMzBtS2dxR0JCWHpHZm8iLCJtYWMiOiI4ZGQ3NjYwNzMxYzBhMTZkMTQzOGU3NjZiMzhkZWE1NGVkNGRiMmZmMjM4MDk0NzFlNDNlNmY3MmI4ZWM0OGNlIiwidGFnIjoiIn0%3D

## Route Context

controller: Livewire\Mechanisms\HandleRequests\HandleRequests@handleUpdate
route name: default-livewire.update
middleware: web, Livewire\Mechanisms\HandleRequests\RequireLivewireHeaders

## Route Parameters

No route parameter data available.

## Database Queries

* pgsql - select * from "sessions" where "id" = 'pdbo2dSWknNNGOfOYmn3I5JmFznNXpauI6aCzKIL' limit 1 (10.9 ms)
* pgsql - select * from "users" where "id" = 1 limit 1 (1.03 ms)
* pgsql - select * from "cache" where "key" in ('agence-location-cache-livewire-checksum-failures:172.20.0.1') (0.46 ms)
* pgsql - select count(*) as "aggregate" from "vehicle_categories" where "id" = 2 (0.84 ms)
* pgsql - select * from "agency_settings" where "id" = 1 limit 1 for update (0.38 ms)
* pgsql - select count(*) as "aggregate" from "vehicles" where "registration" = 'DEMO003' (0.39 ms)
* pgsql - select a.attname as name, t.typname as type_name, format_type(a.atttypid, a.atttypmod) as type, (select tc.collcollate from pg_catalog.pg_collation tc where tc.oid = a.attcollation) as collation, not a.attnotnull as nullable, (select pg_get_expr(adbin, adrelid) from pg_attrdef where c.oid = pg_attrdef.adrelid and pg_attrdef.adnum = a.attnum) as default, a.attgenerated as generated, col_description(c.oid, a.attnum) as comment from pg_attribute a, pg_class c, pg_type t, pg_namespace n where c.relname = 'vehicles' and n.nspname = current_schema() and a.attnum > 0 and a.attrelid = c.oid and a.atttypid = t.oid and n.oid = c.relnamespace order by a.attnum (2.23 ms)
