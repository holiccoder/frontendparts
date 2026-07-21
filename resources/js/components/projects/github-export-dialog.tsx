import { Link } from '@inertiajs/react';
import { ExternalLink, Github, Loader2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { xsrfToken } from '@/lib/xsrf';

export interface GithubExportAction {
    url: string;
    available: boolean;
    connected: boolean;
    account: string | null;
}

interface ExportResult {
    url: string;
    full_name: string;
}

/**
 * Export to GitHub (SPEC §6.4): Pro-gated dialog on the project page —
 * framework picker, repo name, visibility — POSTing JSON to the export
 * endpoint which creates the repo and pushes the starter in a single
 * commit. The repo URL comes straight back in the response (no polling).
 * Unconnected users are sent to /settings/connections first.
 */
export default function GithubExportDialog({ action, projectName }: { action: GithubExportAction; projectName: string }) {
    const [open, setOpen] = useState(false);
    const [framework, setFramework] = useState<'next' | 'nuxt'>('next');
    const [name, setName] = useState(defaultRepoName(projectName));
    const [visibility, setVisibility] = useState<'public' | 'private'>('private');
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [result, setResult] = useState<ExportResult | null>(null);

    if (!action.available) {
        return (
            <Button variant="outline" asChild>
                <Link href="/pricing">
                    <Github className="size-4" />
                    Export to GitHub
                    <Badge>Pro</Badge>
                </Link>
            </Button>
        );
    }

    if (!action.connected) {
        return (
            <Button variant="outline" asChild>
                <Link href="/settings/connections">
                    <Github className="size-4" />
                    Connect GitHub to export
                </Link>
            </Button>
        );
    }

    const submit: FormEventHandler = async (e) => {
        e.preventDefault();

        setProcessing(true);
        setError(null);
        setResult(null);

        try {
            const response = await fetch(action.url, {
                method: 'POST',
                headers: {
                    'X-XSRF-TOKEN': xsrfToken(),
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ framework, name, visibility }),
            });

            const body = await response.json();

            if (response.status === 201) {
                setResult(body.repo);
            } else if (body.error === 'upgrade_required') {
                setError('GitHub export is a Pro feature — upgrade to push starters to a repository.');
            } else if (body.error === 'github_not_connected') {
                setError('Your GitHub connection was removed — reconnect it in settings to keep exporting.');
            } else if (body.message) {
                setError(body.message);
            } else if (body.errors) {
                setError(Object.values(body.errors).flat().join(' '));
            } else {
                setError('The export failed — please try again.');
            }
        } catch {
            setError('The export request failed — check your connection and try again.');
        } finally {
            setProcessing(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <Github className="size-4" />
                    Export to GitHub
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Export to GitHub</DialogTitle>
                    <DialogDescription>Creates a repository on {action.account} and pushes the starter as a single commit.</DialogDescription>
                </DialogHeader>

                {result ? (
                    <div className="grid gap-4">
                        <p className="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
                            Your repository is ready — the starter was pushed as a single commit.
                        </p>
                        <Button asChild>
                            <a href={result.url} target="_blank" rel="noopener noreferrer">
                                <ExternalLink className="size-4" />
                                Open {result.full_name}
                            </a>
                        </Button>
                    </div>
                ) : (
                    <form onSubmit={submit} className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="github-framework">Framework</Label>
                            <Select value={framework} onValueChange={(value) => setFramework(value as 'next' | 'nuxt')}>
                                <SelectTrigger id="github-framework">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="next">Next.js</SelectItem>
                                    <SelectItem value="nuxt">Nuxt</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="github-repo-name">Repository name</Label>
                            <Input
                                id="github-repo-name"
                                value={name}
                                onChange={(e) => setName(e.target.value)}
                                pattern="[A-Za-z0-9._-]+"
                                maxLength={100}
                                required
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="github-visibility">Visibility</Label>
                            <Select value={visibility} onValueChange={(value) => setVisibility(value as 'public' | 'private')}>
                                <SelectTrigger id="github-visibility">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="private">Private</SelectItem>
                                    <SelectItem value="public">Public</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <InputError message={error ?? undefined} />

                        <Button type="submit" disabled={processing}>
                            {processing ? <Loader2 className="size-4 animate-spin" /> : <Github className="size-4" />}
                            Create repository &amp; push
                        </Button>
                    </form>
                )}
            </DialogContent>
        </Dialog>
    );
}

/**
 * GitHub-safe repo name from the project name: lowercase alphanumerics plus
 * `-`, `_`, `.` (the endpoint validates the same rule).
 */
function defaultRepoName(projectName: string): string {
    const slug = projectName
        .toLowerCase()
        .replace(/[^a-z0-9._-]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 100);

    return slug === '' ? 'frontendparts-export' : slug;
}
