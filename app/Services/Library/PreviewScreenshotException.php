<?php

namespace App\Services\Library;

use RuntimeException;

class PreviewScreenshotException extends RuntimeException
{
    public static function noBrowser(): self
    {
        return new self('screenshot: no headless browser available (no resolvable puppeteer module and no Chrome/Chromium binary detected)');
    }

    public static function captureFailed(string $output): self
    {
        return new self('screenshot: capture failed: '.$output);
    }
}
