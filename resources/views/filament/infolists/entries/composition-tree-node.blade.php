{{-- Recursive read-only composition tree node (SPEC §2.2). Expects $node: array{slug, basename, usage, name, level, instances, children} --}}
<ul class="space-y-1 border-l border-gray-200 pl-4 dark:border-gray-700">
    <li>
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <span class="font-medium text-gray-950 dark:text-white">{{ $node['name'] }}</span>
            <x-filament::badge size="sm" color="gray">
                {{ $node['level'] }}
            </x-filament::badge>
            <span class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ $node['slug'] }}</span>
            @if (($node['instances'] ?? 1) > 1)
                <x-filament::badge size="sm" color="info">
                    ×{{ $node['instances'] }}
                </x-filament::badge>
            @endif
        </div>

        @if (! empty($node['children']))
            @foreach ($node['children'] as $child)
                @include('filament.infolists.entries.composition-tree-node', ['node' => $child])
            @endforeach
        @endif
    </li>
</ul>
