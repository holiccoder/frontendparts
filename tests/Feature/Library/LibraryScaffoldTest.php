<?php

namespace Tests\Feature\Library;

use Tests\TestCase;

class LibraryScaffoldTest extends TestCase
{
    public function test_react_app_structure_and_scripts_exist()
    {
        $appPath = base_path('library/react');

        $packageJsonPath = $appPath.'/package.json';
        $this->assertFileExists($packageJsonPath);

        $package = json_decode(file_get_contents($packageJsonPath), true);
        $this->assertIsArray($package);
        $this->assertSame('frontendparts-library-react', $package['name']);
        $this->assertTrue($package['private']);

        foreach (['dev', 'build', 'preview'] as $script) {
            $this->assertArrayHasKey($script, $package['scripts'] ?? [], "react app is missing a '{$script}' script");
        }

        foreach (['vite.config.ts', 'tsconfig.json', 'index.html', 'src/main.tsx'] as $file) {
            $this->assertFileExists($appPath.'/'.$file, "react app is missing {$file}");
        }
    }

    public function test_vue_app_structure_and_scripts_exist()
    {
        $appPath = base_path('library/vue');

        $packageJsonPath = $appPath.'/package.json';
        $this->assertFileExists($packageJsonPath);

        $package = json_decode(file_get_contents($packageJsonPath), true);
        $this->assertIsArray($package);
        $this->assertSame('frontendparts-library-vue', $package['name']);
        $this->assertTrue($package['private']);

        foreach (['dev', 'build', 'preview'] as $script) {
            $this->assertArrayHasKey($script, $package['scripts'] ?? [], "vue app is missing a '{$script}' script");
        }

        foreach (['vite.config.ts', 'tsconfig.json', 'index.html', 'src/main.ts'] as $file) {
            $this->assertFileExists($appPath.'/'.$file, "vue app is missing {$file}");
        }
    }

    public function test_preview_entry_present()
    {
        foreach (['react' => 'index.tsx', 'vue' => 'index.vue'] as $app => $componentFile) {
            $appPath = base_path("library/{$app}");

            // Glob/registry module powering /preview/{slug} resolution.
            $registryPath = $appPath.'/src/lib/registry.ts';
            $this->assertFileExists($registryPath, "{$app} app is missing its registry module");
            $this->assertStringContainsString(
                'import.meta.glob',
                file_get_contents($registryPath),
                "{$app} registry must resolve components via import.meta.glob",
            );

            // Example component with contract + sample data.
            $componentDir = $appPath.'/src/components/elements/section-title-01';
            foreach ([$componentFile, 'params.json', 'data.json'] as $file) {
                $this->assertFileExists($componentDir.'/'.$file, "{$app} example component is missing {$file}");
            }

            foreach (['params.json', 'data.json'] as $file) {
                $decoded = json_decode(file_get_contents($componentDir.'/'.$file), true);
                $this->assertIsArray($decoded, "{$app} example component {$file} must be valid JSON");
            }
        }
    }
}
