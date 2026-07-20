<?php

namespace App\Services\Library;

use Illuminate\Support\Facades\File;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\Process\Process;

/**
 * Headless-browser screenshots of the prebuilt preview artifacts
 * (SPEC §5.2 step 5): QA gate + catalog thumbnails + OG images.
 *
 * spatie/browsershot (via a resolvable puppeteer module) is preferred; when
 * puppeteer is unavailable but a Chrome/Edge binary is detectable we drive
 * the browser's own `--headless --screenshot` mode instead. When neither is
 * available the job records the failure and the integration tests skip.
 */
class PreviewScreenshotter
{
    private static ?bool $puppeteerAvailable = null;

    private static ?string $chromePath = null;

    /**
     * Whether any usable headless-browser stack exists on this machine.
     */
    public function available(): bool
    {
        return $this->puppeteerAvailable() || $this->chromePath() !== null;
    }

    /**
     * Capture one viewport-width screenshot of a self-contained HTML file.
     *
     * @throws PreviewScreenshotException
     */
    public function capture(string $htmlPath, string $outPath, int $width, int $height = 800): void
    {
        File::ensureDirectoryExists(dirname($outPath));

        if ($this->puppeteerAvailable()) {
            $this->captureViaBrowsershot($htmlPath, $outPath, $width, $height);

            return;
        }

        $chrome = $this->chromePath();

        if ($chrome === null) {
            throw PreviewScreenshotException::noBrowser();
        }

        $this->captureViaChromeCli($chrome, $htmlPath, $outPath, $width, $height);
    }

    /**
     * @throws PreviewScreenshotException
     */
    private function captureViaBrowsershot(string $htmlPath, string $outPath, int $width, int $height): void
    {
        try {
            $shot = Browsershot::html((string) file_get_contents($htmlPath))
                ->windowSize($width, $height)
                ->noSandbox()
                ->timeout(60);

            $chrome = $this->chromePath();

            if ($chrome !== null) {
                $shot->setChromePath($chrome);
            }

            $shot->save($outPath);
        } catch (\Throwable $exception) {
            throw PreviewScreenshotException::captureFailed($exception->getMessage());
        }

        if (! is_file($outPath)) {
            throw PreviewScreenshotException::captureFailed('browsershot produced no file');
        }
    }

    /**
     * Chrome on Windows clamps the window to a minimum width of ~500px, so a
     * direct capture below that floor renders the page at 500px and crops the
     * right side. Under the floor we instead render the preview inside a
     * fixed-width iframe shim — media queries evaluate against the iframe's
     * viewport — capture at 500px, then crop the shim region back to the
     * requested width with GD. Without GD we fall back to a direct capture.
     *
     * @throws PreviewScreenshotException
     */
    private function captureViaChromeCli(string $chrome, string $htmlPath, string $outPath, int $width, int $height): void
    {
        $url = 'file:///'.str_replace('\\', '/', $htmlPath);

        $shimPath = null;
        $capturePath = $outPath;
        $windowWidth = $width;

        if ($width < 500 && extension_loaded('gd')) {
            $windowWidth = 500;
            $capturePath = $outPath.'.uncropped.png';
            $shimPath = $this->writeIframeShim($url, $width, $height);
            $url = 'file:///'.str_replace('\\', '/', $shimPath);
        }

        try {
            foreach (['--headless=new', '--headless'] as $headlessFlag) {
                $process = new Process([
                    $chrome,
                    $headlessFlag,
                    '--disable-gpu',
                    '--no-sandbox',
                    '--hide-scrollbars',
                    '--force-device-scale-factor=1',
                    "--window-size={$windowWidth},{$height}",
                    '--virtual-time-budget=4000',
                    "--screenshot={$capturePath}",
                    $url,
                ], null, null, null, 60);

                $process->run();

                if ($process->isSuccessful() && is_file($capturePath)) {
                    if ($capturePath !== $outPath) {
                        $this->cropToWidth($capturePath, $outPath, $width, $height);
                    }

                    return;
                }
            }

            throw PreviewScreenshotException::captureFailed(trim($process->getErrorOutput().' '.$process->getOutput()));
        } finally {
            if ($shimPath !== null) {
                @unlink($shimPath);
            }

            if ($capturePath !== $outPath) {
                @unlink($capturePath);
            }
        }
    }

    /**
     * Write a throwaway outer page that embeds the preview in a `{width}px`
     * iframe anchored at the top-left corner of a 500px window.
     */
    private function writeIframeShim(string $url, int $width, int $height): string
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'fp-shim-'.bin2hex(random_bytes(6)).'.html';

        $html = <<<HTML
        <!doctype html>
        <html>
        <head><meta charset="utf-8"><style>html,body{margin:0;padding:0;background:#fff}iframe{display:block;border:0;width:{$width}px;height:{$height}px}</style></head>
        <body><iframe src="{$url}" width="{$width}" height="{$height}"></iframe></body>
        </html>
        HTML;

        file_put_contents($path, $html);

        return $path;
    }

    /**
     * Crop the top-left `{width}×{height}` region of a full capture (GD).
     *
     * @throws PreviewScreenshotException
     */
    private function cropToWidth(string $fullPath, string $outPath, int $width, int $height): void
    {
        $source = @imagecreatefrompng($fullPath);

        if ($source === false) {
            throw PreviewScreenshotException::captureFailed('GD could not read the uncropped capture');
        }

        $cropped = imagecreatetruecolor($width, $height);
        imagecopy($cropped, $source, 0, 0, 0, 0, $width, $height);
        imagepng($cropped, $outPath);
        imagedestroy($source);
        imagedestroy($cropped);

        if (! is_file($outPath)) {
            throw PreviewScreenshotException::captureFailed('GD crop produced no file');
        }
    }

    private function puppeteerAvailable(): bool
    {
        if (self::$puppeteerAvailable !== null) {
            return self::$puppeteerAvailable;
        }

        $process = new Process([$this->nodeBinary(), '-e', "require('puppeteer')"], base_path(), null, null, 20);
        $process->run();

        return self::$puppeteerAvailable = $process->isSuccessful();
    }

    /**
     * Detect a Chrome/Chromium/Edge binary: config override first, then the
     * usual install locations, then PATH lookup.
     */
    private function chromePath(): ?string
    {
        if (self::$chromePath !== null) {
            return self::$chromePath !== '' ? self::$chromePath : null;
        }

        $configured = config('library.chrome_binary');

        if (is_string($configured) && $configured !== '' && is_file($configured)) {
            return self::$chromePath = $configured;
        }

        $candidates = [
            'C:\Program Files\Google\Chrome\Application\chrome.exe',
            'C:\Program Files (x86)\Google\Chrome\Application\chrome.exe',
            'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe',
            'C:\Program Files\Microsoft\Edge\Application\msedge.exe',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return self::$chromePath = $candidate;
            }
        }

        foreach (['google-chrome', 'chrome', 'chromium', 'chromium-browser'] as $name) {
            $process = PHP_OS_FAMILY === 'Windows'
                ? new Process(['where', $name], null, null, null, 10)
                : new Process(['which', $name], null, null, null, 10);

            $process->run();

            if ($process->isSuccessful() && trim($process->getOutput()) !== '') {
                return self::$chromePath = trim(explode("\n", trim($process->getOutput()))[0]);
            }
        }

        return self::$chromePath = '';
    }

    private function nodeBinary(): string
    {
        return (string) config('library.node_binary', 'node');
    }
}
