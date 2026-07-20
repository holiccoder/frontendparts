<?php

namespace Tests\Feature\Library;

use Tests\TestCase;

class DepsRegistryTest extends TestCase
{
    /** @return array<string, array{react: string, vue: string}> */
    private function registry(): array
    {
        $path = base_path('library/deps.registry.json');
        $this->assertFileExists($path);

        $registry = json_decode(file_get_contents($path), true);
        $this->assertIsArray($registry);
        $this->assertNotEmpty($registry, 'deps.registry.json must not be empty');

        return $registry;
    }

    /**
     * Split `name@range` (scoped or not) into [name, range].
     *
     * @return array{0: string, 1: string}
     */
    private function parsePackageSpec(string $spec): array
    {
        $atPosition = strrpos($spec, '@');

        return [substr($spec, 0, $atPosition), substr($spec, $atPosition + 1)];
    }

    public function test_registry_schema_valid()
    {
        foreach ($this->registry() as $logicalName => $entry) {
            $this->assertIsArray($entry, "registry entry '{$logicalName}' must be an object");

            foreach (['react', 'vue'] as $framework) {
                $this->assertArrayHasKey($framework, $entry, "registry entry '{$logicalName}' is missing '{$framework}'");
                $this->assertIsString($entry[$framework], "registry entry '{$logicalName}.{$framework}' must be a string");
                $this->assertStringContainsString(
                    '@',
                    $entry[$framework],
                    "registry entry '{$logicalName}.{$framework}' must be a name@version spec",
                );
            }
        }
    }

    public function test_every_entry_has_both_ecosystems_with_pinned_versions()
    {
        foreach ($this->registry() as $logicalName => $entry) {
            foreach (['react', 'vue'] as $framework) {
                [$name, $range] = $this->parsePackageSpec($entry[$framework]);

                $this->assertNotSame('', $name, "registry entry '{$logicalName}.{$framework}' has an empty package name");
                $this->assertNotSame('', $range, "registry entry '{$logicalName}.{$framework}' has an empty version range");
                $this->assertNotSame('latest', $range, "registry entry '{$logicalName}.{$framework}' must not use 'latest'");
                $this->assertNotSame('*', $range, "registry entry '{$logicalName}.{$framework}' must not use '*'");
                $this->assertMatchesRegularExpression(
                    '/^[\^~]?\d+\.\d+\.\d+$/',
                    $range,
                    "registry entry '{$logicalName}.{$framework}' must pin a semver range like ^1.2.3, got '{$range}'",
                );
            }
        }
    }

    public function test_registry_packages_installed_in_both_apps()
    {
        foreach ($this->registry() as $logicalName => $entry) {
            foreach (['react', 'vue'] as $framework) {
                [$name, $range] = $this->parsePackageSpec($entry[$framework]);

                $packageJsonPath = base_path("library/{$framework}/package.json");
                $this->assertFileExists($packageJsonPath);

                $package = json_decode(file_get_contents($packageJsonPath), true);
                $dependencies = $package['dependencies'] ?? [];

                $this->assertArrayHasKey(
                    $name,
                    $dependencies,
                    "registry package '{$name}' ({$logicalName}) is not installed in library/{$framework}",
                );

                $registryMajor = (int) ltrim(explode('.', $range)[0], '^~');
                $installedMajor = (int) ltrim(explode('.', $dependencies[$name])[0], '^~');

                $this->assertSame(
                    $registryMajor,
                    $installedMajor,
                    "registry package '{$name}' major version ({$range}) does not match library/{$framework} ({$dependencies[$name]})",
                );
            }
        }
    }
}
