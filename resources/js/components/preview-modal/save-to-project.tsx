import { xsrfToken } from '@/lib/xsrf';
import type { LiveEditSave } from '@/types/catalog';
import { Check, FolderPlus, Loader2 } from 'lucide-react';
import { useState } from 'react';

interface SaveToProjectProps {
    /** Fork-save endpoint + the reader's projects (edit payload, Phase 3.3). */
    save: LiveEditSave;
    /** Build the POST body (framework + edited sources) from the live editor
     * state; null while the editor state is not saveable (e.g. invalid JSON). */
    buildBody: () => Record<string, unknown> | null;
}

/**
 * Save to Project (SPEC §5.6; Phase 3.3): persists the edited sources as a
 * customized fork linked to one of the reader's projects and queues the
 * background preview rebuild. The request returns immediately (202); the
 * project page tracks the rebuild — the success note links straight there.
 */
export function SaveToProject({ save, buildBody }: SaveToProjectProps) {
    const [projectId, setProjectId] = useState<string>(() => (save.projects[0] ? String(save.projects[0].id) : ''));
    const [saving, setSaving] = useState(false);
    const [savedProjectUrl, setSavedProjectUrl] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);

    const submit = async () => {
        const body = buildBody();

        if (body === null || projectId === '') {
            return;
        }

        setSaving(true);
        setError(null);
        setSavedProjectUrl(null);

        try {
            const response = await fetch(save.url, {
                method: 'POST',
                headers: {
                    'X-XSRF-TOKEN': xsrfToken(),
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ ...body, project_id: Number(projectId) }),
            });

            if (response.status === 401) {
                throw new Error('Sign in to save forks to a project.');
            }

            if (response.status === 403) {
                const payload: { error?: string } | null = await response.json().catch(() => null);

                throw new Error(
                    payload?.error === 'upgrade_required'
                        ? 'Saving a fork of this paid component needs a full-library plan.'
                        : 'You can only save to your own projects.',
                );
            }

            if (!response.ok) {
                throw new Error(`Save failed (${response.status}) — please try again.`);
            }

            const payload: { fork?: { project_url?: string } } = await response.json();

            setSavedProjectUrl(payload.fork?.project_url ?? '/dashboard/projects');
        } catch (saveError) {
            setError(saveError instanceof Error ? saveError.message : String(saveError));
        } finally {
            setSaving(false);
        }
    };

    if (save.projects.length === 0) {
        return (
            <a
                href="/dashboard/projects"
                className="inline-flex items-center gap-1.5 rounded-md border border-neutral-300 px-3 py-1.5 text-xs font-medium text-neutral-700 transition hover:border-neutral-400"
            >
                <FolderPlus className="h-3.5 w-3.5" />
                Create a project to save forks
            </a>
        );
    }

    return (
        <span className="inline-flex flex-wrap items-center gap-2">
            <label htmlFor="fp-save-project" className="sr-only">
                Project to save the fork into
            </label>
            <select
                id="fp-save-project"
                value={projectId}
                onChange={(event) => setProjectId(event.target.value)}
                className="max-w-44 rounded-md border border-neutral-300 bg-white px-2 py-1.5 text-xs text-neutral-700 transition hover:border-neutral-400"
            >
                {save.projects.map((project) => (
                    <option key={project.id} value={project.id}>
                        {project.name}
                    </option>
                ))}
            </select>

            <button
                type="button"
                onClick={submit}
                disabled={saving || projectId === ''}
                className="inline-flex items-center gap-1.5 rounded-md border border-neutral-300 px-3 py-1.5 text-xs font-medium text-neutral-700 transition hover:border-neutral-400 disabled:cursor-not-allowed disabled:opacity-50"
            >
                {saving ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <FolderPlus className="h-3.5 w-3.5" />}
                Save to project
            </button>

            {savedProjectUrl !== null && (
                <a
                    href={savedProjectUrl}
                    className="inline-flex items-center gap-1 text-xs font-medium text-green-700 underline decoration-green-300 underline-offset-2 transition hover:text-green-900"
                >
                    <Check className="h-3.5 w-3.5" />
                    Saved — track the preview rebuild in the project
                </a>
            )}

            {error !== null && <span className="text-xs text-red-600">{error}</span>}
        </span>
    );
}
