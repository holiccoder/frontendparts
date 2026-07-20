<?php

namespace App\Enums;

/**
 * Exit-survey answers for the cancel flow (SPEC §16.2 B7). The survey is
 * required before a user-initiated cancellation completes, and each reason
 * maps to a save offer presented before the cancellation is finalized:
 *
 * - price           → discount/downgrade
 * - not using       → pause
 * - missing feature → roadmap
 * - project ended   → pause
 * - just testing    → let go (no offer — straight to confirmation)
 */
enum CancellationReason: string
{
    case Price = 'price';
    case NotUsing = 'not_using';
    case MissingFeature = 'missing_feature';
    case ProjectEnded = 'project_ended';
    case JustTesting = 'just_testing';

    public function label(): string
    {
        return match ($this) {
            self::Price => 'It is too expensive',
            self::NotUsing => 'I am not using it enough',
            self::MissingFeature => 'A feature I need is missing',
            self::ProjectEnded => 'My project ended',
            self::JustTesting => 'I was just testing',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $reason): array => [$reason->value => $reason->label()])
            ->all();
    }

    /**
     * The save offer presented for this reason before the cancellation is
     * finalized (SPEC §16.2). `none` means "let go" — the flow skips the
     * offer step and asks for confirmation directly.
     *
     * @return array{type: string, title: string, body: string}
     */
    public function saveOffer(): array
    {
        return match ($this) {
            self::Price => [
                'type' => 'discount',
                'title' => 'Stay for less',
                'body' => 'We can offer you a discount on your next billing period, or a downgrade to a lower plan — reply to the confirmation email and we will set it up before your next charge.',
            ],
            self::NotUsing => [
                'type' => 'pause',
                'title' => 'Pause instead of cancelling',
                'body' => 'Keep your library access and billing history intact — reply to the confirmation email and we will pause your subscription instead of cancelling it.',
            ],
            self::MissingFeature => [
                'type' => 'roadmap',
                'title' => 'Tell us what is missing',
                'body' => 'Feature requests go straight to the roadmap. Open a support ticket with what you need — if it ships, you will be the first to know.',
            ],
            self::ProjectEnded => [
                'type' => 'pause',
                'title' => 'Pause until your next project',
                'body' => 'Between projects? Reply to the confirmation email and we will pause your subscription, so your next project starts with the full library ready.',
            ],
            self::JustTesting => [
                'type' => 'none',
                'title' => 'Thanks for trying FrontendParts',
                'body' => 'No hard feelings — confirm below to cancel. Your access stays active until the end of the current billing period.',
            ],
        };
    }
}
