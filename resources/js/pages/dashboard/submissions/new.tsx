import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Submissions',
        href: '/dashboard/submissions',
    },
    {
        title: 'New submission',
        href: '/dashboard/submissions/new',
    },
];

interface UsageCategoryOption {
    id: number;
    name: string;
    zone: string | null;
}

interface NewSubmissionProps {
    categories: UsageCategoryOption[];
    levels: Record<string, string>;
    frameworks: Record<string, string>;
}

const codeClassName =
    'border-input bg-background placeholder:text-muted-foreground focus-visible:ring-ring flex w-full rounded-md border px-3 py-2 font-mono text-sm focus-visible:ring-2 focus-visible:outline-hidden';

/**
 * New community submission (task 5.3, CSR): metadata plus a single-file
 * paste per declared framework, optional sample data (JSON object) and the
 * real-world citation URL. The code textareas follow the framework pick —
 * "Both" asks for the React and the Vue implementation.
 */
export default function NewSubmission({ categories, levels, frameworks }: NewSubmissionProps) {
    const { data, setData, post, processing, errors } = useForm<{
        name: string;
        level: string;
        usage_category_id: string;
        framework: string;
        description: string;
        react_code: string;
        vue_code: string;
        sample_data: string;
        source_url: string;
    }>({
        name: '',
        level: '',
        usage_category_id: '',
        framework: '',
        description: '',
        react_code: '',
        vue_code: '',
        sample_data: '',
        source_url: '',
    });

    const wantsReact = data.framework === 'react' || data.framework === 'both';
    const wantsVue = data.framework === 'vue' || data.framework === 'both';

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('dashboard.submissions.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New submission" />

            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <HeadingSmall
                    title="Submit a component"
                    description="Share a single component you recreated from a live site (with attribution). Approved submissions are credited to you and published as free components after QA."
                />

                <form onSubmit={submit} className="flex max-w-2xl flex-col gap-4">
                    <div className="grid gap-1.5">
                        <Label htmlFor="name">Component name</Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder="e.g. Pricing card with badge"
                            required
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="grid gap-1.5">
                            <Label htmlFor="level">Level</Label>
                            <Select value={data.level} onValueChange={(value) => setData('level', value)}>
                                <SelectTrigger id="level" className="w-full">
                                    <SelectValue placeholder="Choose" />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(levels).map(([value, label]) => (
                                        <SelectItem key={value} value={value}>
                                            {label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.level} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="usage_category_id">Usage pattern</Label>
                            <Select value={data.usage_category_id} onValueChange={(value) => setData('usage_category_id', value)}>
                                <SelectTrigger id="usage_category_id" className="w-full">
                                    <SelectValue placeholder="Choose" />
                                </SelectTrigger>
                                <SelectContent>
                                    {categories.map((category) => (
                                        <SelectItem key={category.id} value={String(category.id)}>
                                            {category.zone ? `${category.zone} › ${category.name}` : category.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.usage_category_id} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="framework">Framework</Label>
                            <Select value={data.framework} onValueChange={(value) => setData('framework', value)}>
                                <SelectTrigger id="framework" className="w-full">
                                    <SelectValue placeholder="Choose" />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(frameworks).map(([value, label]) => (
                                        <SelectItem key={value} value={value}>
                                            {label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.framework} />
                        </div>
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="description">Description & usage scenario</Label>
                        <textarea
                            id="description"
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            placeholder="What does the component do, and when would a developer reach for it?"
                            rows={4}
                            required
                            className="border-input bg-background placeholder:text-muted-foreground focus-visible:ring-ring flex w-full rounded-md border px-3 py-2 text-base focus-visible:ring-2 focus-visible:outline-hidden md:text-sm"
                        />
                        <InputError message={errors.description} />
                    </div>

                    {wantsReact && (
                        <div className="grid gap-1.5">
                            <Label htmlFor="react_code">React source (single .tsx file)</Label>
                            <textarea
                                id="react_code"
                                value={data.react_code}
                                onChange={(e) => setData('react_code', e.target.value)}
                                placeholder={'export default function PricingCard() {\n    return …\n}'}
                                rows={10}
                                spellCheck={false}
                                className={codeClassName}
                            />
                            <InputError message={errors.react_code} />
                        </div>
                    )}

                    {wantsVue && (
                        <div className="grid gap-1.5">
                            <Label htmlFor="vue_code">Vue source (single .vue file)</Label>
                            <textarea
                                id="vue_code"
                                value={data.vue_code}
                                onChange={(e) => setData('vue_code', e.target.value)}
                                placeholder={'<script setup lang="ts">\n…\n</script>\n\n<template>\n    …\n</template>'}
                                rows={10}
                                spellCheck={false}
                                className={codeClassName}
                            />
                            <InputError message={errors.vue_code} />
                        </div>
                    )}

                    <div className="grid gap-1.5">
                        <Label htmlFor="sample_data">Sample data (optional, JSON object)</Label>
                        <textarea
                            id="sample_data"
                            value={data.sample_data}
                            onChange={(e) => setData('sample_data', e.target.value)}
                            placeholder={'{\n    "label": "Get started"\n}'}
                            rows={5}
                            spellCheck={false}
                            className={codeClassName}
                        />
                        <InputError message={errors.sample_data} />
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="source_url">Citation URL (optional)</Label>
                        <Input
                            id="source_url"
                            type="url"
                            value={data.source_url}
                            onChange={(e) => setData('source_url', e.target.value)}
                            placeholder="https://example.com — the live site this layout references"
                        />
                        <InputError message={errors.source_url} />
                    </div>

                    <div>
                        <Button type="submit" disabled={processing}>
                            Submit for review
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
