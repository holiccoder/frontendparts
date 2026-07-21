<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\CategoryType;
use App\Enums\ComponentLevel;
use App\Enums\SubmissionFramework;
use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Submissions\StoreComponentSubmissionRequest;
use App\Models\Category;
use App\Models\ComponentSubmission;
use App\Notifications\SubmissionCreatedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * User-side community submissions (task 5.3, PRD §4.2 P3, CSR zone): list of
 * own submissions with review status and the new-submission form. Review
 * itself happens in Filament; users only ever see their own rows (owner
 * scoping in every query).
 */
class ComponentSubmissionController extends Controller
{
    public function index(Request $request): Response
    {
        $submissions = $request->user()->componentSubmissions()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ComponentSubmission $submission): array => [
                'id' => $submission->id,
                'name' => $submission->name,
                'level' => $submission->level->value,
                'framework' => $submission->framework->value,
                'status' => $submission->status->value,
                'review_note' => $submission->review_note,
                'created_at' => $submission->created_at->toIso8601String(),
            ]);

        return Inertia::render('dashboard/submissions/index', [
            'submissions' => $submissions,
        ]);
    }

    public function create(): Response
    {
        $categories = Category::query()
            ->where('type', CategoryType::Usage->value)
            ->orderBy('zone')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'zone'])
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'zone' => $category->zone,
            ]);

        return Inertia::render('dashboard/submissions/new', [
            'categories' => $categories,
            'levels' => collect(ComponentLevel::cases())
                ->mapWithKeys(fn (ComponentLevel $level): array => [$level->value => ucfirst($level->value)])
                ->all(),
            'frameworks' => SubmissionFramework::options(),
        ]);
    }

    public function store(StoreComponentSubmissionRequest $request): RedirectResponse
    {
        $submission = $request->user()->componentSubmissions()->create([
            'name' => $request->validated('name'),
            'level' => $request->validated('level'),
            'usage_category_id' => $request->validated('usage_category_id'),
            'framework' => $request->validated('framework'),
            'description' => $request->validated('description'),
            'react_code' => $request->validated('react_code'),
            'vue_code' => $request->validated('vue_code'),
            'sample_data' => $request->sampleData(),
            'source_url' => $request->validated('source_url'),
            'status' => SubmissionStatus::Pending,
        ]);

        // New-submission alert to the admin inbox (mirrors §16.1 ticket alerts).
        Notification::route('mail', config('mail.admin.address'))
            ->notify(new SubmissionCreatedNotification($submission));

        return to_route('dashboard.submissions.index')
            ->with('notice', 'Submission received — we will review it and email you the outcome.');
    }
}
