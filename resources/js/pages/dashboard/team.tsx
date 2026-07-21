import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Mail, UserPlus, Users, X } from 'lucide-react';

import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Team',
        href: '/dashboard/team',
    },
];

interface MemberRow {
    id: number;
    name: string;
    email: string;
    role: string;
    you: boolean;
}

interface InvitationRow {
    id: number;
    email: string;
    invited_at: string | null;
}

interface OrganizationProps {
    id: number;
    name: string;
    seats_used: number;
    seat_limit: number | null;
    plan_active: boolean;
    checkout_url: string;
    members: MemberRow[];
    invitations: InvitationRow[];
}

interface MembershipRow {
    name: string;
    role: string;
    owner: string;
    plan_active: boolean;
}

interface TeamPageProps {
    organization: OrganizationProps | null;
    memberships: MembershipRow[];
}

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString();
}

function roleBadgeVariant(role: string): 'default' | 'secondary' | 'outline' {
    return role === 'owner' ? 'default' : role === 'admin' ? 'secondary' : 'outline';
}

/**
 * Team page (task 5.2, CSR): the owner manages their organization — member
 * list with live seat usage, pending invitations with revoke, and the
 * invite form capped by the purchased seats. Members without an
 * organization of their own see the teams they belong to; everyone else
 * gets the create-organization form.
 */
export default function TeamPage({ organization, memberships }: TeamPageProps) {
    const { flash } = usePage<SharedData & { flash?: { notice?: string | null } }>().props;

    const createForm = useForm({ name: '' });
    const inviteForm = useForm({ email: '' });

    const seatsFull =
        organization !== null &&
        organization.seat_limit !== null &&
        organization.seats_used + organization.invitations.length >= organization.seat_limit;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Team" />

            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <HeadingSmall
                    title="Team"
                    description="Your organization and its seats. Every member gets the full library, scaffolding and exports while your team plan is active."
                />

                {flash?.notice && <p className="text-sm text-green-600 dark:text-green-400">{flash.notice}</p>}

                {organization === null ? (
                    <div className="border-sidebar-border/70 dark:border-sidebar-border flex max-w-lg flex-col gap-4 rounded-xl border p-6">
                        <h2 className="text-base font-semibold">Create your organization</h2>
                        <p className="text-sm text-neutral-500">
                            One organization per account. You take the first seat — add the Team plan on{' '}
                            <Link href="/pricing" className="underline underline-offset-2">
                                pricing
                            </Link>{' '}
                            and invite up to your purchased seats.
                        </p>
                        <form
                            className="flex flex-col gap-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                createForm.post('/dashboard/team');
                            }}
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="name">Organization name</Label>
                                <Input
                                    id="name"
                                    value={createForm.data.name}
                                    onChange={(event) => createForm.setData('name', event.target.value)}
                                    placeholder="Acme Studios"
                                    maxLength={80}
                                    required
                                />
                                <InputError message={createForm.errors.name} />
                            </div>
                            <div>
                                <Button type="submit" disabled={createForm.processing}>
                                    Create organization
                                </Button>
                            </div>
                        </form>
                    </div>
                ) : (
                    <>
                        {/* Organization header + seat usage */}
                        <div className="border-sidebar-border/70 dark:border-sidebar-border flex flex-wrap items-center justify-between gap-3 rounded-xl border p-4">
                            <div className="flex flex-col gap-1">
                                <span className="text-base font-semibold">{organization.name}</span>
                                <span className="text-sm text-neutral-500">
                                    {organization.seats_used}
                                    {organization.seat_limit !== null ? ` / ${organization.seat_limit}` : ''} seats used
                                    {organization.invitations.length > 0 && ` · ${organization.invitations.length} invited`}
                                </span>
                            </div>
                            <Badge variant={organization.plan_active ? 'default' : 'secondary'}>
                                {organization.plan_active ? 'Team plan active' : 'Team plan inactive'}
                            </Badge>
                        </div>

                        {!organization.plan_active && (
                            <div className="border-sidebar-border/70 dark:border-sidebar-border flex flex-wrap items-center justify-between gap-3 rounded-xl border border-dashed p-4">
                                <p className="text-sm text-neutral-500">
                                    Buy team seats to activate Pro-level access for every member and unlock invitations.
                                </p>
                                <Button asChild size="sm">
                                    <Link href={organization.checkout_url}>Buy seats</Link>
                                </Button>
                            </div>
                        )}

                        {/* Invite form */}
                        <div className="border-sidebar-border/70 dark:border-sidebar-border flex max-w-lg flex-col gap-3 rounded-xl border p-4">
                            <h2 className="flex items-center gap-2 text-sm font-semibold">
                                <UserPlus className="size-4" /> Invite a member
                            </h2>
                            <form
                                className="flex flex-col gap-3"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    inviteForm.post('/dashboard/team/invitations', {
                                        onSuccess: () => inviteForm.reset(),
                                    });
                                }}
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email address</Label>
                                    <div className="flex gap-2">
                                        <Input
                                            id="email"
                                            type="email"
                                            value={inviteForm.data.email}
                                            onChange={(event) => inviteForm.setData('email', event.target.value)}
                                            placeholder="teammate@example.com"
                                            disabled={seatsFull}
                                            required
                                        />
                                        <Button type="submit" disabled={inviteForm.processing || seatsFull}>
                                            Invite
                                        </Button>
                                    </div>
                                    <InputError message={inviteForm.errors.email} />
                                    {seatsFull && (
                                        <p className="text-sm text-neutral-500">
                                            All seats are taken —{' '}
                                            <Link href={organization.checkout_url} className="underline underline-offset-2">
                                                buy more seats
                                            </Link>{' '}
                                            to invite another member.
                                        </p>
                                    )}
                                </div>
                            </form>
                        </div>

                        {/* Members */}
                        <div className="flex flex-col gap-2">
                            <h2 className="text-sm font-semibold">Members</h2>
                            <ul className="flex flex-col gap-2">
                                {organization.members.map((member) => (
                                    <li
                                        key={member.id}
                                        className="border-sidebar-border/70 dark:border-sidebar-border flex flex-wrap items-center justify-between gap-2 rounded-xl border p-3"
                                    >
                                        <div className="flex min-w-0 flex-col">
                                            <span className="font-medium">
                                                {member.name}
                                                {member.you && <span className="ml-1 text-sm text-neutral-400">(you)</span>}
                                            </span>
                                            <span className="text-sm text-neutral-500">{member.email}</span>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Badge variant={roleBadgeVariant(member.role)}>{member.role}</Badge>
                                            {member.role !== 'owner' && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => router.delete(`/dashboard/team/members/${member.id}`)}
                                                >
                                                    <X className="size-4" />
                                                    Remove
                                                </Button>
                                            )}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </div>

                        {/* Pending invitations */}
                        {organization.invitations.length > 0 && (
                            <div className="flex flex-col gap-2">
                                <h2 className="text-sm font-semibold">Pending invitations</h2>
                                <ul className="flex flex-col gap-2">
                                    {organization.invitations.map((invitation) => (
                                        <li
                                            key={invitation.id}
                                            className="border-sidebar-border/70 dark:border-sidebar-border flex flex-wrap items-center justify-between gap-2 rounded-xl border p-3"
                                        >
                                            <div className="flex min-w-0 items-center gap-2">
                                                <Mail className="size-4 shrink-0 text-neutral-400" />
                                                <span className="text-sm">{invitation.email}</span>
                                                {invitation.invited_at && (
                                                    <span className="text-sm text-neutral-400">· invited {formatDate(invitation.invited_at)}</span>
                                                )}
                                            </div>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => router.delete(`/dashboard/team/invitations/${invitation.id}`)}
                                            >
                                                <X className="size-4" />
                                                Revoke
                                            </Button>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </>
                )}

                {/* Memberships in other organizations */}
                {memberships.length > 0 && (
                    <div className="flex flex-col gap-2">
                        <h2 className="text-sm font-semibold">Teams you belong to</h2>
                        <ul className="flex flex-col gap-2">
                            {memberships.map((membership) => (
                                <li
                                    key={membership.name}
                                    className="border-sidebar-border/70 dark:border-sidebar-border flex flex-wrap items-center justify-between gap-2 rounded-xl border p-3"
                                >
                                    <div className="flex min-w-0 flex-col">
                                        <span className="font-medium">{membership.name}</span>
                                        <span className="text-sm text-neutral-500">Owned by {membership.owner}</span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Badge variant={roleBadgeVariant(membership.role)}>{membership.role}</Badge>
                                        <Badge variant={membership.plan_active ? 'default' : 'secondary'}>
                                            {membership.plan_active ? 'Active' : 'Inactive'}
                                        </Badge>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

                {organization === null && memberships.length === 0 && (
                    <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed border-neutral-300 p-8 text-center dark:border-neutral-700">
                        <Users className="size-8 text-neutral-400" />
                        <p className="text-sm text-neutral-500">
                            Not part of a team yet — create an organization above or ask a team owner to invite you.
                        </p>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
