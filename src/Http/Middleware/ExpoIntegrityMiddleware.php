<?php

declare(strict_types=1);

namespace Korioinc\ExpoIntegrity\Http\Middleware;

use Closure;
use Illuminate\Cache\FileStore;
use Illuminate\Cache\RedisStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use LogicException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;
use UnexpectedValueException;

class ExpoIntegrityMiddleware
{
    private const string PROTOCOL = 'korio-integrity:v1';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $headers = $this->validatedHeaders($request);
        $timestamp = (int) $headers['timestamp'];
        $currentMinute = now()->startOfMinute()->getTimestamp();

        abort_unless($timestamp % 60 === 0 && abs($timestamp - $currentMinute) <= 60, 401, 'Invalid request integrity.');
        $this->validateRequest($request);

        try {
            $this->verifySignature($request, $headers, $timestamp);
        } catch (HttpExceptionInterface $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            abort(503, 'Request integrity is unavailable.');
        }

        return $next($request);
    }

    /**
     * @return array{version: string, timestamp: string, nonce: string, signature: string}
     */
    private function validatedHeaders(Request $request): array
    {
        $headers = [
            'version' => $this->singleHeader($request, 'app-version'),
            'timestamp' => $this->singleHeader($request, 'x-integrity-timestamp'),
            'nonce' => $this->singleHeader($request, 'x-integrity-nonce'),
            'signature' => $this->singleHeader($request, 'x-integrity-signature'),
        ];
        $validator = Validator::make($headers, [
            'version' => ['required', 'string', 'regex:/\A[A-Za-z0-9][A-Za-z0-9._+-]{0,63}\z/'],
            'timestamp' => ['required', 'string', 'regex:/\A(?:0|[1-9][0-9]{0,12})\z/'],
            'nonce' => ['required', 'string', 'regex:/\A[a-f0-9]{32}\z/'],
            'signature' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
        ])->stopOnFirstFailure();

        abort_if($validator->fails(), 401, 'Invalid request integrity.');

        return $headers;
    }

    private function singleHeader(Request $request, string $name): string
    {
        $values = $request->headers->all($name);
        abort_unless(count($values) === 1 && is_string($values[0]), 401, 'Invalid request integrity.');

        return $values[0];
    }

    private function validateRequest(Request $request): void
    {
        abort_if($request->getMethod() !== $request->getRealMethod(), 401, 'Invalid request integrity.');
        abort_if($request->hasHeader('X-HTTP-Method-Override'), 401, 'Invalid request integrity.');
        abort_if($request->hasHeader('Content-Encoding'), 415, 'Encoded integrity bodies are unsupported.');

        $body = $request->getContent();
        abort_if(in_array($request->getRealMethod(), ['GET', 'HEAD'], true) && $body !== '', 401, 'Invalid request integrity.');

        $path = $request->getRequestUri();
        abort_unless(str_starts_with($path, '/') && ! preg_match('/[\r\n#]/', $path), 401, 'Invalid request integrity.');
    }

    /**
     * @param  array{version: string, timestamp: string, nonce: string, signature: string}  $headers
     */
    private function verifySignature(Request $request, array $headers, int $timestamp): void
    {
        $secret = config('laravel-expo-integrity.secret');
        $cacheName = config('laravel-expo-integrity.cache_store');
        abort_unless(is_string($secret) && $secret !== '', 503, 'Request integrity is unavailable.');
        abort_unless(is_string($cacheName) && $cacheName !== '', 503, 'Request integrity is unavailable.');

        $expiresAt = $timestamp + 120;
        abort_if($expiresAt <= now()->getTimestamp(), 401, 'Invalid request integrity.');

        $cache = Cache::store($cacheName);
        $store = $cache->getStore();

        if (! $store instanceof FileStore && ! $store instanceof RedisStore) {
            throw new LogicException('Integrity requires a file or Redis cache store with atomic nonce storage.');
        }

        $keyFingerprint = hash('sha256', $secret);
        $minuteCacheKey = 'integrity:v1:key:'.$keyFingerprint.':'.$headers['version'].':'.$timestamp;
        $minuteKeyHex = $cache->get($minuteCacheKey);
        $cacheMiss = $minuteKeyHex === null;

        if ($cacheMiss) {
            $minuteKeyHex = hash_hmac('sha256', $this->encode([self::PROTOCOL.':key', $headers['version'], $timestamp]), $secret);
        }

        if (! is_string($minuteKeyHex) || preg_match('/\A[a-f0-9]{64}\z/', $minuteKeyHex) !== 1) {
            throw new UnexpectedValueException('Invalid cached integrity key.');
        }

        $minuteKey = hex2bin($minuteKeyHex);
        abort_unless(is_string($minuteKey), 503, 'Request integrity is unavailable.');

        $payload = $this->encode([
            self::PROTOCOL,
            $headers['version'],
            $timestamp,
            $headers['nonce'],
            $request->getRealMethod(),
            $request->getRequestUri(),
            hash('sha256', $request->getContent()),
        ]);
        $expected = hash_hmac('sha256', $payload, $minuteKey);
        abort_unless(hash_equals($expected, $headers['signature']), 401, 'Invalid request integrity.');

        $ttl = $expiresAt - now()->getTimestamp();
        abort_if($ttl <= 0, 401, 'Invalid request integrity.');

        if ($cacheMiss && ! $cache->put($minuteCacheKey, $minuteKeyHex, $ttl)) {
            throw new RuntimeException('Integrity key cache write failed.');
        }

        $nonceKey = 'integrity:v1:nonce:'.$keyFingerprint.':'.$headers['version'].':'.$headers['nonce'];
        abort_unless($cache->add($nonceKey, true, $ttl), 401, 'Invalid request integrity.');
    }

    /**
     * @param  array<int, string|int>  $values
     */
    private function encode(array $values): string
    {
        return json_encode($values, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
