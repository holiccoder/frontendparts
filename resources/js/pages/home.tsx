import SeoHead from '@/components/seo-head';
import PublicLayout from '@/layouts/public-layout';
import { type SharedData } from '@/types';
import type { PageMeta } from '@/types/shared';
import { Link, usePage } from '@inertiajs/react';
import { CreditCard, Mail, Users } from 'lucide-react';

interface HomeProps {
    meta: PageMeta;
}

const FEATURES = [
    {
        icon: CreditCard,
        title: 'Billing that just works',
        description:
            'Plans, per-seat team pricing and checkout out of the box — Paddle for international cards, Alipay & WeChat Pay for domestic buyers, with refunds and dunning handled.',
    },
    {
        icon: Users,
        title: 'Accounts & teams',
        description:
            'Registration, email verification, profile settings, organizations with seats and invitations, and a dashboard your users land on from day one.',
    },
    {
        icon: Mail,
        title: 'Lifecycle email engine',
        description:
            'Onboarding drips, renewal reminders, dunning and win-back sequences with a preference center and one-click unsubscribe — marketing and transactional mail kept cleanly apart.',
    },
];

/**
 * Home page: minimal marketing landing — hero, three feature cards and
 * CTAs to pricing and registration, inside the public marketing shell.
 */
export default function Home({ meta }: HomeProps) {
    const { name } = usePage<SharedData>().props;

    return (
        <PublicLayout>
            <SeoHead meta={meta} />

            <section className="mx-auto w-full max-w-7xl px-4 pt-20 pb-16 text-center sm:px-6 lg:px-8 lg:pt-28">
                <h1 className="mx-auto max-w-3xl text-4xl font-semibold tracking-tight text-balance sm:text-5xl lg:text-6xl">
                    Build your product on {name}
                </h1>
                <p className="mx-auto mt-6 max-w-2xl text-lg leading-8 text-neutral-500">
                    {name} comes with subscriptions, team seats, lifecycle email and an admin panel already wired up — so you spend
                    your time on the product, not the plumbing.
                </p>
                <div className="mt-10 flex items-center justify-center gap-3">
                    <Link
                        href="/register"
                        className="rounded-md bg-neutral-900 px-6 py-3 text-sm font-medium text-white transition hover:bg-neutral-700"
                    >
                        Create your account
                    </Link>
                    <Link
                        href="/pricing"
                        className="rounded-md border border-neutral-300 px-6 py-3 text-sm font-medium text-neutral-700 transition hover:border-neutral-400 hover:bg-neutral-50"
                    >
                        View pricing
                    </Link>
                </div>
            </section>

            <section className="mx-auto w-full max-w-7xl px-4 pb-24 sm:px-6 lg:px-8">
                <div className="grid gap-6 md:grid-cols-3">
                    {FEATURES.map((feature) => (
                        <div key={feature.title} className="rounded-2xl border border-neutral-200 bg-white p-8">
                            <feature.icon className="h-6 w-6 text-neutral-900" aria-hidden="true" />
                            <h2 className="mt-5 text-lg font-semibold tracking-tight">{feature.title}</h2>
                            <p className="mt-3 text-sm leading-6 text-neutral-500">{feature.description}</p>
                        </div>
                    ))}
                </div>

                <div className="mt-16 rounded-2xl bg-neutral-900 px-8 py-12 text-center">
                    <h2 className="text-2xl font-semibold tracking-tight text-white">Ready when you are</h2>
                    <p className="mx-auto mt-3 max-w-xl text-sm leading-6 text-neutral-300">
                        Start on the free plan, upgrade when you need more — every purchase is covered by the refund window.
                    </p>
                    <div className="mt-8 flex items-center justify-center gap-3">
                        <Link
                            href="/pricing"
                            className="rounded-md bg-white px-6 py-3 text-sm font-medium text-neutral-900 transition hover:bg-neutral-100"
                        >
                            See plans
                        </Link>
                        <Link
                            href="/register"
                            className="rounded-md border border-neutral-600 px-6 py-3 text-sm font-medium text-white transition hover:bg-neutral-800"
                        >
                            Get started
                        </Link>
                    </div>
                </div>
            </section>
        </PublicLayout>
    );
}
