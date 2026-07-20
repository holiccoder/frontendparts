@php
    $matrix = $this->matrixData();
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        heading="Coverage matrix"
        description="Published components per industry × usage pattern. Red cells (&lt;3) are the build-next queue."
    >
        <div class="mb-3 flex flex-wrap items-center gap-4 text-xs text-gray-600 dark:text-gray-300">
            <span class="flex items-center gap-1">
                <span class="inline-block h-3 w-3 rounded bg-red-500"></span> &lt;3 — build next
            </span>
            <span class="flex items-center gap-1">
                <span class="inline-block h-3 w-3 rounded bg-amber-400"></span> 3–5 — thin
            </span>
            <span class="flex items-center gap-1">
                <span class="inline-block h-3 w-3 rounded bg-green-500"></span> &gt;5 — covered
            </span>
            <span class="ml-auto font-semibold">
                Coverage: {{ $matrix['covered'] }}/{{ $matrix['total'] }} cells ({{ $matrix['coverage_pct'] }}%)
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="text-xs">
                <thead>
                    <tr>
                        <th class="sticky left-0 bg-white p-1 text-left dark:bg-gray-900"></th>
                        @foreach ($matrix['usages'] as $usage)
                            <th class="p-1 align-bottom">
                                <div class="w-8 origin-bottom-left -rotate-45 whitespace-nowrap font-medium text-gray-500 dark:text-gray-400" style="transform: rotate(-45deg); transform-origin: left bottom; height: 5.5rem;">
                                    {{ $usage->name }}
                                </div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($matrix['industries'] as $industry)
                        <tr>
                            <th class="sticky left-0 whitespace-nowrap bg-white p-1 pr-2 text-left font-medium text-gray-700 dark:bg-gray-900 dark:text-gray-200">
                                {{ $industry->name }}
                            </th>
                            @foreach ($matrix['usages'] as $usage)
                                @php
                                    $count = $matrix['cells'][$industry->id][$usage->id] ?? 0;
                                    $tone = \App\Filament\Widgets\CoverageMatrixWidget::cellTone($count);
                                @endphp
                                <td class="p-0.5">
                                    <div
                                        title="{{ $industry->name }} × {{ $usage->name }}: {{ $count }} published"
                                        @class([
                                            'flex h-7 w-8 items-center justify-center rounded text-[10px] font-semibold',
                                            'bg-red-500/90 text-white' => $tone === 'critical',
                                            'bg-amber-400/90 text-gray-900' => $tone === 'warning',
                                            'bg-green-500/90 text-white' => $tone === 'ok',
                                        ])
                                    >{{ $count }}</div>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
