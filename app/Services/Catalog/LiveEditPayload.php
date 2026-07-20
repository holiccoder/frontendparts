<?php

namespace App\Services\Catalog;

use App\Models\Component;

/**
 * Live-edit tab payload (SPEC §5.6, §2.5): everything the in-browser bundler
 * needs to compile a component client-side — the full composition closure's
 * authored sources (parent + children, one virtual file per component), each
 * member's sample data module, and the closure's npm dependencies pinned from
 * the dependency registry so esm.sh serves the exact versions the prebuilt
 * previews bundle. Files keep their library-relative paths so the authored
 * relative imports (`../../elements/…`) resolve verbatim in the virtual FS.
 * React-only for now (Phase 3.1); the Vue `@vue/repl` structure lands in 3.2.
 */
class LiveEditPayload
{
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
