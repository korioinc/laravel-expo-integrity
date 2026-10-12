<?php

declare(strict_types=1);

use Illuminate\Support\Env;

return [
    /*
     * One nonempty shared string across app versions, used as UTF-8 bytes unchanged.
     * app-version remains signed metadata, not a release allowlist or user identity.
     * Keys embedded in an app are extractable; never use the public demo key.
     */
    'secret' => Env::getRepository()->get('INTEGRITY_SECRET'),

    /*
     * File works for one server with a persistent directory shared by its workers.
     * Use shared Redis for multiple API servers/containers; local files are not shared.
     * Clearing or losing nonce records weakens replay protection for either store.
     */
    'cache_store' => env('INTEGRITY_CACHE_STORE', 'file'),
];
