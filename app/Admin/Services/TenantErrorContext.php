<?php

namespace App\Admin\Services;

/**
 * Per-request static holder for the error code generated during exception reporting.
 * Bridges the reportable() and renderable() exception handler callbacks.
 */
class TenantErrorContext
{
    private static ?string $errorCode = null;

    public static function set(string $code): void
    {
        self::$errorCode = $code;
    }

    public static function get(): ?string
    {
        return self::$errorCode;
    }

    public static function clear(): void
    {
        self::$errorCode = null;
    }
}
