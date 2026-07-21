<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Browser-suite wiring gate (task 5.7): the Playwright suite itself runs via
 * `npm run test:browser` against a served APP_ENV=browser instance — never
 * inside PHPUnit. This test only asserts the suite exists and stays wired
 * (config, specs, fixtures command, npm scripts, gitignored artifacts), so
 * a stray deletion or rename fails the normal suite. No browser is launched.
 */
class BrowserSuiteTest extends TestCase
{
    public function test_playwright_config_and_specs_exist()
    {
        $this->assertFileExists(base_path('playwright.config.ts'));

        foreach (['preview-modal', 'structure-tree', 'live-edit'] as $spec) {
            $this->assertFileExists(base_path("tests/Browser/{$spec}.spec.ts"), "missing browser spec {$spec}.spec.ts");
        }
    }

    public function test_runner_scripts_and_browser_environment_exist()
    {
        foreach (['env.mjs', 'serve.mjs', 'global-setup.ts', 'esm-shims.ts'] as $script) {
            $this->assertFileExists(base_path("tests/Browser/scripts/{$script}"), "missing runner script {$script}");
        }

        $this->assertFileExists(base_path('.env.browser'));
        $this->assertFileExists(app_path('Console/Commands/BrowserFixturesCommand.php'));
    }

    public function test_playwright_config_uses_system_chrome_and_manages_the_server()
    {
        $config = (string) file_get_contents(base_path('playwright.config.ts'));

        // System Chrome — no downloaded browser binaries required.
        $this->assertStringContainsString("channel: 'chrome'", $config);

        // Playwright owns the dev-server lifecycle (no backgrounded processes).
        $this->assertStringContainsString('webServer', $config);
        $this->assertStringContainsString('tests/Browser/scripts/serve.mjs', $config);

        // Specs stay out of the PHPUnit suite.
        $this->assertStringContainsString("testDir: './tests/Browser'", $config);
    }

    public function test_npm_scripts_are_registered()
    {
        $package = json_decode((string) file_get_contents(base_path('package.json')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('@playwright/test', $package['devDependencies'] ?? []);

        $scripts = $package['scripts'] ?? [];

        $this->assertArrayHasKey('test:browser', $scripts);
        $this->assertArrayHasKey('test:browser:headed', $scripts);
        $this->assertStringContainsString('playwright test', $scripts['test:browser']);
        $this->assertStringContainsString('--headed', $scripts['test:browser:headed']);
    }

    public function test_browser_artifacts_are_gitignored()
    {
        $gitignore = (string) file_get_contents(base_path('.gitignore'));

        foreach (['/database/browser.sqlite', '/tests/Browser/.cache/', '/tests/Browser/results/', '/tests/Browser/report/'] as $pattern) {
            $this->assertStringContainsString($pattern, $gitignore, "{$pattern} must be gitignored");
        }
    }
}
