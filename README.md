# Laravel Expo Integrity

Laravel middleware for the request signatures produced by
`@korioinc/expo-core/integrity`. Verifies HMAC-SHA256, caches minute-derived
keys, and atomically consumes nonces in file or Redis cache before the protected
route runs. The default is Laravel's `file` cache; Redis is optional.

This is a lightweight barrier to scripts, **not** Apple App Attest, Google Play
Integrity, user authentication, or proof that a genuine Expo binary sent the
request. Client keys can be extracted. Keep authentication, authorization,
HTTPS and rate limits.

## Requirements

- PHP 8.3+ and Laravel 12 or 13. Local verification uses PHP 8.5 / Laravel 13;
  the existing CI matrix covers other declared combinations.
- A Laravel file cache store (default), or an optional Redis cache store.
  File cache requires one server and the same writable, persistent directory
  for all its PHP workers. For multiple API servers/containers, configure shared
  Redis; independent local directories cannot prevent cross-server replay.
- Synchronized API server clocks.

## Installation

```bash
composer require korioinc/laravel-expo-integrity
```

Laravel auto-discovery registers the provider, merges configuration and adds the
`expo-integrity` middleware alias. **It does not protect any route automatically.**
The package retains its existing `Korioinc\ExpoIntegrity` namespace.
There is no facade or HTTP client wrapper.

Publishing configuration is optional:

```bash
php artisan vendor:publish --tag=laravel-expo-integrity-config
```

The published file is `config/laravel-expo-integrity.php`. Read runtime values
with `config('laravel-expo-integrity.secret')`, not `config('integrity.secret')`.

```dotenv
INTEGRITY_SECRET="<your-shared-string>"
INTEGRITY_CACHE_STORE=file
```

Configure `INTEGRITY_CACHE_STORE=redis` if your API runs on multiple replicas.
All replicas must use the same store/namespace. Redis requires a configured client
(`phpredis` or `predis`); file mode requires neither. The package only accepts
Laravel `FileStore` or `RedisStore`, including named stores backed by those drivers.
Array/null/failover stores are rejected rather than weakening replay protection.

Use the same nonempty string as `EXPO_PUBLIC_INTEGRITY_SECRET` in your app.
There is no hex, character-set or length restriction; UUIDs work. The exact UTF-8
bytes are used without decoding, trimming or normalization. The key is read as
raw environment text, so values such as `true`, `false` and `null` remain strings.
Quote whitespace or
special characters in environment files. Prefer an unpredictable random value;
short or guessable keys weaken protection. Do not commit real keys. Expo public
environment values are bundled into the app and are not confidential secrets.

This replaces the previous hex-decoded key format. Even a hex-looking key now
uses its literal text bytes, so signatures change. Update client and backend
together; legacy hex-key signatures are not accepted.

One key covers app version updates. No `INTEGRITY_APP_VERSION` is required;
`app-version` remains signed metadata, not an allowlist or binary identity.
Key rotation still requires coordinated app/server updates. Use a different key
for each independent API trust boundary: protocol v1 does not bind host/audience,
so services sharing a key and separate replay stores can accept the same proof.

Attach the middleware class explicitly to the routes that require signatures:

```php
use Korioinc\ExpoIntegrity\Http\Middleware\ExpoIntegrityMiddleware;

Route::get('/gifts', [GiftController::class, 'index'])
    ->middleware(ExpoIntegrityMiddleware::class);
```

If a string alias is preferred, the equivalent is
`->middleware('expo-integrity')`. The old `integrity` alias is not registered.

Rebuild cached configuration after changing environment values using your
normal deployment process. Existing web routes, health checks and third-party
webhooks are unaffected unless you explicitly attach this middleware.

## Expo client

Core owns version lookup, nonces and the signing-key cache. Set
`integrity.secret: process.env.EXPO_PUBLIC_INTEGRITY_SECRET` in `expo-kit.config.ts`
and pass it through the existing `withExpoKitConf` setup. The key must match this
backend. Request callers need only `createIntegrityHeaders(payload)`; `fetch`
remains unwrapped:

```ts
import { fetch } from 'expo/fetch';
import { createIntegrityHeaders } from '@korioinc/expo-core/integrity';

const url = new URL('https://api.example.com/api/gifts?language=ko');
const payload = { method: 'GET', path: url.pathname + url.search };
const headers = createIntegrityHeaders(payload);

const response = await fetch(url.href, {
  method: payload.method,
  redirect: 'error',
  headers: {
    Accept: 'application/json',
    ...headers,
  },
});
```

GET/HEAD have no body. POST/PUT/PATCH use the same serialized text in the helper
and `fetch`. Multipart, binary and compressed request bodies are outside this
protocol. Protect write retries with a separate idempotency key; a fresh nonce
does not make business effects idempotent. Do not send real proof to public demo
APIs or follow redirects to another destination.

## Protocol and verification

| Header | Value |
| --- | --- |
| `app-version` | 1–64 ASCII version characters; included in the signature |
| `x-integrity-timestamp` | Unix seconds rounded down to the start of the minute |
| `x-integrity-nonce` | Fresh 16 random bytes as lowercase hexadecimal |
| `x-integrity-signature` | HMAC-SHA256 as lowercase hexadecimal |

The Expo signer and middleware use `korio-integrity:v1` with string keys:

```text
timestamp = floor(unixMilliseconds / 60000) * 60
minuteKey = HMAC-SHA256(UTF8(secret), UTF8(JSON.stringify([
  "korio-integrity:v1:key", appVersion, timestamp
])))
bodyHash = lowercaseHex(SHA256(exactRawBodyBytes))
payload = UTF8(JSON.stringify([
  "korio-integrity:v1", appVersion, timestamp, nonce,
  uppercaseMethod, pathnameAndSearch, bodyHash
]))
signature = lowercaseHex(HMAC-SHA256(minuteKey, payload))
```

The middleware uses Laravel `Validator` for header rules and `Request`/`Cache`
for input and nonce storage. `handle()` only coordinates header validation,
request constraints, signature verification and the next handler. Validation
failures remain `401`, not Laravel's default form-validation `422`.

The middleware reads the real HTTP method, original request URI including the
query, and raw request body. It does not parse and reserialize JSON. Request
method overrides are rejected. Query ordering, duplicate parameters and percent
encoding must match; preserve the original path across reverse proxies.

Current, previous and next minute buckets are accepted. A bucket expires at
`timestamp + 120` seconds, so this is not exactly a 60-second token lifetime.
Derived keys are cached by key fingerprint/version/minute only **after** a valid
signature. Every request still hashes its body and recomputes the request MAC.

Laravel's atomic cache `add` reserves the nonce after MAC verification and before
the route executes. FileStore uses an exclusive file lock; RedisStore uses an
atomic Lua operation. Replay, malformed/expired proof and signed-data changes
are rejected. A missing key or cache/configuration failure fails closed;
there is no fallback to a different store or unsigned traffic.

**File mode is for a single server with local persistent storage shared by all
PHP workers.** Separate containers with their own filesystems can each accept
the same nonce. Network/shared filesystem locking is not verified; use shared
Redis for multi-server deployments. Laravel's default file cache path is under
`storage/framework/cache/data`; permissions must allow reads, writes and locks.

Cache clearing, file deletion, Redis eviction, or replacing storage during a
restart/deployment can remove nonce records and reopen replay. Preserve records
through the acceptance window; do not clear this store during that window. File
write failures such as disk-full conditions can damage cache records, so monitor
storage health and stop protected traffic if nonce persistence is unreliable.
Use Redis for sustained/high-volume workloads as well as multiple replicas.
Laravel file-cache files can remain after expiry until read or cleared; account
for disk capacity before choosing file mode for a high-volume API. Captured
requests can still be raced for first use; nonce consumption prevents repeated
execution, not theft.

The client sends `app-version`. Symfony/PHP may normalize underscore header names
to hyphens before verification, so the middleware cannot reliably distinguish
an old underscore spelling; enforce raw spelling at ingress if needed.

Reject duplicate integrity headers at ingress: PHP may merge or discard raw duplicate
headers before Laravel can inspect them. Browser consumers must allow these
headers in the backend's CORS configuration.

## Local Composer development

In a sibling app's `backend/composer.json`, add this path repository:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../../laravel-expo-integrity",
      "options": { "symlink": true }
    }
  ],
  "require": {
    "korioinc/laravel-expo-integrity": "dev-main"
  }
}
```

Run `composer update korioinc/laravel-expo-integrity` from the backend. The
relative path resolves to the sibling package and Composer symlinks its source.
The local package must be present when installing that lockfile. Replace the
path repository and `dev-main` dependency with a released version before distributing
or deploying the application to an environment without that checkout.

## Development checks

```bash
composer test
```

Uses existing PHPStan, Pint, type-coverage and Pest checks. The security fixtures
are generated independently by the Expo kit's Node signer. Tests include a real
isolated file-cache replay check and isolate external Redis behavior; actual
cross-process contention and deployment storage must be verified separately.

The development-only ParaTest upper bound excludes `7.26.1`, whose five-argument
worker constructor is incompatible with Pest 5.3.1's parallel runner. Revisit the
bound when Pest supports that API; production consumers do not inherit this
development dependency.

## License

MIT. See [LICENSE.md](LICENSE.md).
