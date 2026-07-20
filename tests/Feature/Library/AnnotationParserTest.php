<?php

namespace Tests\Feature\Library;

use App\Enums\AccessLevel;
use App\Enums\ComponentLevel;
use App\Services\Library\AnnotationException;
use App\Services\Library\AnnotationParser;
use Tests\TestCase;

class AnnotationParserTest extends TestCase
{
    private function docblock(array $overrides = [], array $except = []): string
    {
        $fields = [
            'component' => 'pricing-section-01',
            'name' => 'Pricing Section 01',
            'level' => 'section',
            'usage' => 'pricing',
            'industries' => 'saas, fintech',
            'tags' => 'dark, gradient',
            'access' => 'pro',
            'source' => 'https://stripe.com/pricing',
            'deps' => 'lucide',
            'version' => '1.0.0',
            ...$overrides,
        ];

        $block = "/**\n";

        foreach ($fields as $tag => $value) {
            if (in_array($tag, $except, true)) {
                continue;
            }

            $block .= " * @{$tag} {$value}\n";
        }

        return $block." */\nexport default function Component() {}\n";
    }

    public function test_parses_full_annotation_block()
    {
        $annotation = (new AnnotationParser)->parse($this->docblock());

        $this->assertSame('pricing-section-01', $annotation['slug']);
        $this->assertSame('Pricing Section 01', $annotation['name']);
        $this->assertSame(ComponentLevel::Section, $annotation['level']);
        $this->assertSame('pricing', $annotation['usage']);
        $this->assertSame(['saas', 'fintech'], $annotation['industries']);
        $this->assertSame(['dark', 'gradient'], $annotation['tags']);
        $this->assertSame(AccessLevel::Paid, $annotation['access']);
        $this->assertSame('https://stripe.com/pricing', $annotation['sourceUrl']);
        $this->assertSame(['lucide'], $annotation['deps']);
        $this->assertSame('1.0.0', $annotation['version']);
    }

    public function test_empty_optional_lists_are_allowed()
    {
        $annotation = (new AnnotationParser)->parse($this->docblock([
            'industries' => '',
            'tags' => '',
            'deps' => '',
            'source' => '',
            'access' => 'free',
        ]));

        $this->assertSame([], $annotation['industries']);
        $this->assertSame([], $annotation['tags']);
        $this->assertSame([], $annotation['deps']);
        $this->assertNull($annotation['sourceUrl']);
        $this->assertSame(AccessLevel::Free, $annotation['access']);
    }

    public function test_missing_required_field_fails_with_field_name()
    {
        $this->expectException(AnnotationException::class);
        $this->expectExceptionMessage('@usage');

        (new AnnotationParser)->parse($this->docblock(except: ['usage']));
    }

    public function test_unknown_level_rejected()
    {
        $this->expectException(AnnotationException::class);
        $this->expectExceptionMessage("Unknown level 'widget'");

        (new AnnotationParser)->parse($this->docblock(['level' => 'widget']));
    }

    public function test_deps_names_only_no_versions()
    {
        $this->expectException(AnnotationException::class);
        $this->expectExceptionMessage('lucide-react@^1.25.0');

        (new AnnotationParser)->parse($this->docblock(['deps' => 'lucide-react@^1.25.0']));
    }

    public function test_scoped_dep_names_rejected()
    {
        $this->expectException(AnnotationException::class);
        $this->expectExceptionMessage('@scope/pkg');

        (new AnnotationParser)->parse($this->docblock(['deps' => '@scope/pkg']));
    }
}
