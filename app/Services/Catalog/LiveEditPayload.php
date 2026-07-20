<?php

namespace App\Services\Catalog;

use App\Models\Component;

/**
 * Live-edit tab payload (SPEC §5.6, §2.5): everything the in-browser bundler
 * needs to compile a component client-side — the full composition closure's
 * authored sources (parent + children, one virtual file per component), each
 * member's sample data module, and the closure's npm dependencies pinned from
 * the dependency registry so esm.sh serves the exact versions the prebuilt
 * previews bundle. React files keep their library-relative paths so the
 * authored relative imports (`../../elements/…`) resolve verbatim in the
 * virtual FS; the Vue payload uses the flat `src/` map @vue/repl requires
 * (Phase 3.2).
 */
class LiveEditPayload
{
    /** Repl store main file: the generated wrapper mounting the entry SFC. */
    public const VUE_MAIN_FILE = 'src/App.vue';

    public function __construct(
        private readonly ComponentContent $content = new ComponentContent,
        private readonly ClosureZip $closureZip = new ClosureZip,
    ) {}

    /**
     * @return array{entry: string, files: list<array{path: string, code: string}>, data: array<string, array<string, mixed>>, deps: array<string, string|null>}
     */
    public function react(Component $component): array
    {
        $members = $this->closure($component);

        $files = [];
        $data = [];

        foreach ($members as $member) {
            $content = $this->content->for($member);
            $source = $content['files']['react'][0] ?? null;

            if ($source !== null) {
                $files[] = $source;
            }

            if ($content['data'] !== []) {
                $data[$member->slug] = $content['data'];
            }
        }

        return [
            'entry' => $component->slug,
            'files' => $files,
            'data' => $data,
            'deps' => $this->closureZip->resolveDeps($members, 'react'),
        ];
    }

    /**
     * The Vue twin of {@see react()} (Phase 3.2), structured for direct
     * Repl-store consumption: the closure's SFCs as a flat
     * `src/{PascalName}.vue` file map (the Repl resolves `./` imports
     * against its `src/` root — see ClosureZip::vueReplFiles), the
     * generated wrapper's main-file name, the repl filename of the entry
     * component's SFC, per-slug data modules (the client materializes the
     * entry's data as an editable `src/data.ts`), and the closure's deps
     * pinned from the registry's VUE column for the preview import map.
     *
     * @return array{entry: string, entryFile: string|null, mainFile: string, files: array<string, string>, data: array<string, array<string, mixed>>, deps: array<string, string|null>}
     */
    public function vue(Component $component): array
    {
        $members = $this->closure($component);

        $repl = $this->closureZip->vueReplFiles($members);
        $data = [];

        foreach ($members as $member) {
            $content = $this->content->for($member);

            if ($content['data'] !== []) {
                $data[$member->slug] = $content['data'];
            }
        }

        return [
            'entry' => $component->slug,
            'entryFile' => isset($repl['names'][$component->slug]) && isset($repl['files']["src/{$repl['names'][$component->slug]}.vue"])
                ? "src/{$repl['names'][$component->slug]}.vue"
                : null,
            'mainFile' => self::VUE_MAIN_FILE,
            'files' => $repl['files'],
            'data' => $data,
            'deps' => $this->closureZip->resolveDeps($members, 'vue'),
        ];
    }

    /**
     * The component plus its transitive descendants, deduplicated and ordered
     * elements → blocks → sections → pages (SPEC §2.2, §2.4).
     *
     * @return list<Component>
     */
    private function closure(Component $component): array
    {
        return $this->closureZip->order(
            Component::query()
                ->whereIn('id', [$component->id, ...$component->descendantIds()])
                ->get()
                ->all()
        );
    }
}
