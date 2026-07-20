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
     * @throws PreviewScreenshotException
     */
    private function captureViaChromeCli(string $chrome, string $htmlPath, string $outPath, int $width, int $height): void
    {
        $url = 'file:///'.str_replace('\\', '/', $htmlPath);

        foreach (['--headless=new', '--headless'] as $headlessFlag) {
            $process = new Process([
                $chrome,
                $headlessFlag,
                '--disable-gpu',
                '--no-sandbox',
                '--hide-scrollbars',
                '--force-device-scale-factor=1',
                "--window-size={$width},{$height}",
                '--virtual-time-budget=4000',
                "--screenshot={$outPath}",
                $url,
            ], null, null, null, 60);

            $process->run();

            if ($process->isSuccessful() && is_file($outPath)) {
                return;
            }
        }

        throw PreviewScreenshotException::captureFailed(trim($process->getErrorOutput().' '.$process->getOutput()));
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
