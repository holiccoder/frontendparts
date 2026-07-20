<x-filament-panels::page>
    <x-filament::section
        heading="Citation sources"
        description="Every site the catalog cites as a layout reference (SPEC §9). Use this overview to answer takedown requests."
    >
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead>
                    <tr class="text-left">
                        <th class="py-2 pr-4 font-semibold text-gray-950 dark:text-white">Source</th>
                        <th class="py-2 pr-4 font-semibold text-gray-950 dark:text-white">URL</th>
                        <th class="py-2 pr-4 font-semibold text-gray-950 dark:text-white">Components</th>
                        <th class="py-2 font-semibold text-gray-950 dark:text-white">Latest added</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @forelse ($this->sources() as $source)
                        <tr>
                            <td class="py-2 pr-4 font-medium text-gray-950 dark:text-white">
                                {{ $source->source_name }}
                            </td>
                            <td class="py-2 pr-4">
                                @if ($source->source_url)
                                    <a
                                        href="{{ $source->source_url }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="text-primary-600 underline dark:text-primary-400"
                                    >{{ $source->source_url }}</a>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="py-2 pr-4">
                                <x-filament::badge color="info">
                                    {{ $source->components_count }}
                                </x-filament::badge>
                            </td>
                            <td class="py-2 text-gray-600 dark:text-gray-300">
                                {{ \Illuminate\Support\Carbon::parse($source->latest_added_at)->diffForHumans() }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-6 text-center text-gray-500 dark:text-gray-400">
                                No citation sources recorded yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
