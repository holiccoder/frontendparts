import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';

interface BillingOrder {
    id: number;
    plan: string;
    status: string;
    license_state: string;
    billing_period: string;
    ends_at: string | null;
    receipt_url: string | null;
}

interface SaveOffer {
    reason: string;
    type: string;
    title: string;
    body: string;
}

interface BillingProps {
    order: BillingOrder | null;
    cancellable: boolean;
    cancellationReasons: Record<string, string>;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Billing settings',
        href: '/settings/billing',
    },
];

export default function Billing({ order, cancellable, cancellationReasons }: BillingProps) {
    const { flash } = usePage<SharedData & { flash?: { notice?: string | null; save_offer?: SaveOffer | null } }>().props;

    const [step, setStep] = useState<'idle' | 'survey' | 'offer'>('idle');

    const surveyForm = useForm<{ reason: string }>({ reason: '' });
    const confirmForm = useForm<{ reason: string; confirmed: boolean }>({ reason: '', confirmed: true });

    const submitSurvey: FormEventHandler = (e) => {
        e.preventDefault();

        surveyForm.post(route('settings.billing.cancel'), {
            preserveScroll: true,
            onSuccess: () => {
                confirmForm.setData('reason', surveyForm.data.reason);
                setStep('offer');
            },
        });
    };

    const confirmCancellation: FormEventHandler = (e) => {
        e.preventDefault();

        confirmForm.post(route('settings.billing.cancel'), {
            preserveScroll: true,
            onSuccess: () => {
                setStep('idle');
                surveyForm.reset();
                confirmForm.reset();
            },
        });
    };

    const saveOffer = flash?.save_offer ?? null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Billing settings" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall title="Billing" description="Your subscription, payment status and cancellation options" />

                    {flash?.notice && <p className="text-sm text-green-600 dark:text-green-400">{flash.notice}</p>}

                    {order === null ? (
                        <p className="text-sm text-neutral-500">
                            You are on the Free plan — no subscription to manage.{' '}
                            <a href="/pricing" className="underline">
                                View paid plans
                            </a>
                            .
                        </p>
                    ) : (
                        <div className="space-y-4 rounded-lg border border-neutral-200 p-4 dark:border-neutral-700">
                            <div className="flex items-center justify-between">
                                <p className="font-medium capitalize">{order.plan} plan</p>
                                <Badge variant={order.status === 'past_due' ? 'destructive' : 'secondary'} className="capitalize">
                                    {order.status.replace('_', ' ')}
                                </Badge>
                            </div>

                            {order.ends_at && (
                                <p className="text-sm text-neutral-500">
                                    {order.status === 'cancelled' ? 'Access until' : 'Current period ends'}:{' '}
                                    {new Date(order.ends_at).toLocaleDateString()}
                                </p>
                            )}

                            {order.receipt_url && (
                                <p className="text-sm">
                                    <a href={order.receipt_url} target="_blank" rel="noreferrer" className="underline">
                                        View receipt
                                    </a>
                                </p>
                            )}

                            {order.status === 'past_due' && (
                                <div className="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-200/20 dark:bg-amber-700/10 dark:text-amber-200">
                                    Your latest payment failed. We retry automatically — if it keeps failing, update the payment method via the link
                                    in Paddle's payment email or contact support.
                                </div>
                            )}
                        </div>
                    )}

                    {cancellable && order !== null && (
                        <div className="space-y-4">
                            <HeadingSmall title="Cancel subscription" description="Your access stays active until the end of the current period" />

                            {step === 'idle' && (
                                <Button
                                    variant="destructive"
                                    onClick={() => {
                                        surveyForm.reset();
                                        setStep('survey');
                                    }}
                                >
                                    Cancel subscription
                                </Button>
                            )}

                            {step === 'survey' && (
                                <form onSubmit={submitSurvey} className="space-y-4 rounded-lg border border-neutral-200 p-4 dark:border-neutral-700">
                                    <p className="font-medium">Why are you cancelling?</p>
                                    <p className="text-sm text-neutral-500">One quick question before you go — it shapes what we build next.</p>

                                    <div className="space-y-2">
                                        {Object.entries(cancellationReasons).map(([value, label]) => (
                                            <div key={value} className="flex items-center space-x-3">
                                                <input
                                                    id={`reason-${value}`}
                                                    type="radio"
                                                    name="reason"
                                                    value={value}
                                                    checked={surveyForm.data.reason === value}
                                                    onChange={(e) => surveyForm.setData('reason', e.target.value)}
                                                    className="h-4 w-4 border-neutral-300"
                                                />
                                                <Label htmlFor={`reason-${value}`} className="font-normal">
                                                    {label}
                                                </Label>
                                            </div>
                                        ))}
                                    </div>

                                    <InputError message={surveyForm.errors.reason} />

                                    <div className="flex items-center gap-4">
                                        <Button disabled={surveyForm.processing || surveyForm.data.reason === ''}>Continue</Button>
                                        <Button type="button" variant="ghost" onClick={() => setStep('idle')}>
                                            Never mind
                                        </Button>
                                    </div>
                                </form>
                            )}

                            {step === 'offer' && saveOffer !== null && (
                                <form
                                    onSubmit={confirmCancellation}
                                    className="space-y-4 rounded-lg border border-neutral-200 p-4 dark:border-neutral-700"
                                >
                                    <p className="font-medium">{saveOffer.title}</p>
                                    <p className="text-sm text-neutral-500">{saveOffer.body}</p>

                                    <div className="flex items-center gap-4">
                                        <Button variant="destructive" disabled={confirmForm.processing}>
                                            Confirm cancellation
                                        </Button>
                                        <Button type="button" variant="ghost" onClick={() => setStep('idle')}>
                                            Keep my subscription
                                        </Button>
                                    </div>
                                </form>
                            )}
                        </div>
                    )}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
