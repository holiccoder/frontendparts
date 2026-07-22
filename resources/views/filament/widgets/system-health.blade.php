<x-filament-widgets::widget>
    <x-filament::section heading="System health">
        {{-- Failed queue jobs --}}
        <div>
            <h3 class="mb-2 text-sm font-semibold text-gray-950 dark:text-white">Failed queue jobs</h3>

            @php $failedJobs = $this->failedJobsCount(); @endphp

            <div class="mb-3 flex items-center gap-2">
                <span class="text-2xl font-bold text-gray-950 dark:text-white">{{ $failedJobs }}</span>
                @if ($failedJobs > 0)
                    <x-filament::badge color="danger">Needs attention</x-filament::badge>
                @else
                    <x-filament::badge color="success">Clear</x-filament::badge>
                @endif
            </div>

            @if ($failedJobs > 0)
                <ul class="space-y-2">
                    @foreach ($this->recentFailedJobs() as $job)
                        <li class="rounded-lg border border-gray-200 p-2 text-xs dark:border-white/10">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-medium text-gray-950 dark:text-white">{{ $job->queue }} / {{ Str::limit($job->connection, 20) }}</span>
                                <x-filament::badge color="warning" size="sm">{{ $job->failed_at ? Carbon\Carbon::parse($job->failed_at)->diffForHumans() : '-' }}</x-filament::badge>
                            </div>
                            <p class="mt-1 line-clamp-2 text-gray-500 dark:text-gray-400">
                                {{ Str::limit($job->exception, 120) }}
                            </p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
