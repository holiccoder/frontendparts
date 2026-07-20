@php
    $tree = $getState();
@endphp

<x-dynamic-component
    :component="$getEntryWrapperView()"
    :entry="$entry"
>
    <div class="fi-composition-tree overflow-x-auto rounded-xl bg-gray-50 p-4 dark:bg-white/5">
        @if (is_array($tree))
            @include('filament.infolists.entries.composition-tree-node', ['node' => $tree])
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">No composition data.</p>
        @endif
    </div>
</x-dynamic-component>
