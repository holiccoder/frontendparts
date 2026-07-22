<?php

namespace App\Filament\Pages;

use App\Support\Settings as PlatformSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Platform settings: every tunable chassis value editable in the panel —
 * never hardcoded. Form fields map onto the registered keys of the Settings
 * service; saving writes through the service (which invalidates the cached
 * values), so changes take effect without a deploy. New products register
 * their own keys in App\Support\Settings and add fields here.
 */
class Settings extends Page
{
    protected string $view = 'filament.pages.settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 10;

    /**
     * Form field name → registered settings key.
     *
     * @var array<string, string>
     */
    private const FIELD_MAP = [
        'billing_refund_window_days' => 'billing.refund_window_days',
        'fx_cny_to_usd' => 'fx.cny_to_usd',
        'affiliate_commission_rate' => 'affiliate.commission_rate',
        'affiliate_cookie_days' => 'affiliate.cookie_days',
        'affiliate_recurring_months' => 'affiliate.recurring_months',
        'affiliate_holding_days' => 'affiliate.holding_days',
        'affiliate_payout_threshold' => 'affiliate.payout_threshold',
    ];

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $settings = app(PlatformSettings::class);

        $state = [];

        foreach (self::FIELD_MAP as $field => $key) {
            $state[$field] = $settings->get($key);
        }

        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Section::make('Billing')
                        ->description('Refund policy window and the CNY → USD rate used to normalize domestic revenue.')
                        ->columns(2)
                        ->components([
                            TextInput::make('billing_refund_window_days')
                                ->label('Refund window (days)')
                                ->numeric()
                                ->minValue(0)
                                ->required(),
                            TextInput::make('fx_cny_to_usd')
                                ->label('FX rate: CNY → USD')
                                ->numeric()
                                ->minValue(0)
                                ->required(),
                        ]),
                    Section::make('Affiliate')
                        ->description('Affiliate program knobs — commission rate, attribution cookie, renewal window, holding period and payout threshold.')
                        ->columns(2)
                        ->components([
                            TextInput::make('affiliate_commission_rate')
                                ->label('Commission rate (% of net)')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100)
                                ->required(),
                            TextInput::make('affiliate_cookie_days')
                                ->label('Attribution cookie (days)')
                                ->numeric()
                                ->minValue(0)
                                ->required(),
                            TextInput::make('affiliate_recurring_months')
                                ->label('Renewal commission window (months)')
                                ->numeric()
                                ->minValue(0)
                                ->required(),
                            TextInput::make('affiliate_holding_days')
                                ->label('Holding period after refund window (days)')
                                ->numeric()
                                ->minValue(0)
                                ->required(),
                            TextInput::make('affiliate_payout_threshold')
                                ->label('Payout threshold (USD)')
                                ->numeric()
                                ->minValue(0)
                                ->required(),
                        ]),
                ])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Save settings')
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $settings = app(PlatformSettings::class);

        foreach (self::FIELD_MAP as $field => $key) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $settings->set($key, $data[$field]);
        }

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }
}
