import { type BreadcrumbItem, type SharedData } from '@/types';
import { Transition } from '@headlessui/react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';

type DigestFrequency = 'weekly' | 'monthly' | 'off';

interface NotificationPreferences {
    product_updates: boolean;
    blog: boolean;
    digest_frequency: DigestFrequency;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Notification settings',
        href: '/settings/notifications',
    },
];

export default function Notifications({ preferences }: { preferences: NotificationPreferences }) {
    const { name } = usePage<SharedData>().props;
    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm<NotificationPreferences>({
        product_updates: preferences.product_updates,
        blog: preferences.blog,
        digest_frequency: preferences.digest_frequency,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        patch(route('notifications.update'), { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Notification settings" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall title="Email preferences" description={`Choose which emails ${name} sends you`} />

                    <form onSubmit={submit} className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="digest_frequency">Digest</Label>

                            <Select value={data.digest_frequency} onValueChange={(value) => setData('digest_frequency', value as DigestFrequency)}>
                                <SelectTrigger id="digest_frequency" className="mt-1 w-full">
                                    <SelectValue placeholder="Choose a frequency" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="weekly">Weekly — every Monday</SelectItem>
                                    <SelectItem value="monthly">Monthly — the 1st of each month</SelectItem>
                                    <SelectItem value="off">Off</SelectItem>
                                </SelectContent>
                            </Select>

                            <p className="text-sm text-neutral-500">Product updates and blog highlights, rounded up per period.</p>

                            <InputError className="mt-2" message={errors.digest_frequency} />
                        </div>

                        <div className="flex items-center space-x-3">
                            <Checkbox id="blog" checked={data.blog} onCheckedChange={(checked) => setData('blog', checked === true)} />
                            <Label htmlFor="blog">Blog highlights</Label>
                        </div>
                        <InputError message={errors.blog} />

                        <div className="flex items-center space-x-3">
                            <Checkbox
                                id="product_updates"
                                checked={data.product_updates}
                                onCheckedChange={(checked) => setData('product_updates', checked === true)}
                            />
                            <Label htmlFor="product_updates">Product updates &amp; onboarding tips</Label>
                        </div>
                        <InputError message={errors.product_updates} />

                        <div className="rounded-md border border-neutral-200 bg-neutral-50 p-4">
                            <div className="flex items-center space-x-3">
                                <Checkbox id="transactional" checked disabled />
                                <Label htmlFor="transactional" className="text-neutral-500">
                                    Transactional email — always on
                                </Label>
                            </div>
                            <p className="mt-2 text-sm text-neutral-500">
                                Order, license, security and support-ticket emails are essential to your account and can't be disabled.
                            </p>
                        </div>

                        <div className="flex items-center gap-4">
                            <Button disabled={processing}>Save</Button>

                            <Transition
                                show={recentlySuccessful}
                                enter="transition ease-in-out"
                                enterFrom="opacity-0"
                                leave="transition ease-in-out"
                                leaveTo="opacity-0"
                            >
                                <p className="text-sm text-neutral-600">Saved</p>
                            </Transition>
                        </div>
                    </form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
