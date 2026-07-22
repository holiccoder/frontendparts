<x-filament-widgets::widget>
    <x-filament::section heading="System health">
        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Failed preview builds --}}
            <div>
                <h3 class="mb-2 text-sm font-semibold text-gray-950 dark:text-white">Failed preview builds</h3>

                @php $failures = $this->failedBuilds(); @endphp

                <ul class="space-y-2">
                    @forelse ($failures as $failure)
                        <li class="rounded-lg border border-gray-200 p-2 text-sm dark:border-white/10">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-medium text-gray-950 dark:text-white">
                                    {{ $failure->component?->slug ?? "component #{$failure->component_id}" }}
                                </span>
                                <x-filament::badge color="danger" size="sm">
                                    {{ $failure->framework }}
                                </x-filament::badge>
                            </div>
                            <p class="mt-1 line-clamp-2 text-xs text-gray-500 dark:text-gray-400">
                                {{ \Illuminate\Support\Str::limit($failure->error, 160) }}
                            </p>
                            <div class="mt-2">
                                <x-filament::button size="xs" color="gray" wire:click="retryBuild({{ $failure->id }})">
                                    Retry
                                </x-filament::button>
                            </div>
                        </li>
                    @empty
                        <li class="text-sm text-gray-500 dark:text-gray-400">No failed builds 🎉</li>
                    @endforelse
                </ul>
            </div>

            {{-- Last library sync --}}
            <div>
                <h3 class="mb-2 text-sm font-semibold text-gray-950 dark:text-white">Last library sync</h3>

                @php $run = $this->lastSyncRun(); @endphp

                @if ($run)
                    <dl class="space-y-1 text-sm text-gray-600 dark:text-gray-300">
                        <div class="flex justify-between gap-2">
                            <dt class="text-gray-500 dark:text-gray-400">Ran</dt>
                            <dd>{{ $run->created_at->diffForHumans() }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-gray-500 dark:text-gray-400">Scanned</dt>
                            <dd>{{ $run->scanned }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-gray-500 dark:text-gray-400">Upserted</dt>
                            <dd>{{ $run->upserted }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-gray-500 dark:text-gray-400">Errors</dt>
                            <dd>
                                <x-filament::badge :color="count($run->errors ?? []) > 0 ? 'danger' : 'success'" size="sm">
                                    {{ count($run->errors ?? []) }}
                                </x-filament::badge>
                            </dd>
                        </div>
                    </dl>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400">No sync runs yet.</p>
                @endif
            </div>

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
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
