/**
 * License state shared by the dashboard pages (SPEC §15.4). The value is
 * derived server-side from the §7.3 order state machine; labels and badge
 * variants live here so the overview plan card and the orders page stay
 * consistent.
 */
export type LicenseState = 'pending' | 'active' | 'past_due' | 'cancelled_valid_until' | 'expired' | 'refunded';

export const LICENSE_STATE_LABELS: Record<LicenseState, string> = {
    pending: 'Confirming payment',
    active: 'Active',
    past_due: 'Payment due',
    cancelled_valid_until: 'Cancelled — access until term end',
    expired: 'Expired',
    refunded: 'Refunded',
};

export type LicenseBadgeVariant = 'default' | 'secondary' | 'destructive' | 'outline';

export const LICENSE_STATE_BADGE_VARIANTS: Record<LicenseState, LicenseBadgeVariant> = {
    pending: 'secondary',
    active: 'default',
    past_due: 'destructive',
    cancelled_valid_until: 'secondary',
    expired: 'outline',
    refunded: 'outline',
};

export function licenseStateLabel(state: string): string {
    return LICENSE_STATE_LABELS[state as LicenseState] ?? state;
}

export function licenseStateBadgeVariant(state: string): LicenseBadgeVariant {
    return LICENSE_STATE_BADGE_VARIANTS[state as LicenseState] ?? 'outline';
}
