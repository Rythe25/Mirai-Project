<?php

namespace App\Support;

/** Links back to the storefront: the caller's origin if config/cors.php allows it, else STOREFRONT_URL. */
class StorefrontUrl
{
    public static function to(?string $origin, string $path): string
    {
        $base = in_array($origin, config('cors.allowed_origins'), true) ? $origin : config('app.storefront_url');

        return rtrim($base, '/').'/'.ltrim($path, '/');
    }
}
