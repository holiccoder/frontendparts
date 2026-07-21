import { Head, router } from '@inertiajs/react';
import QRCode from 'qrcode';
import { useEffect, useState } from 'react';

import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';

interface DomesticPaymentProps {
    order: {
        plan: string;
        billing_period: string;
        amount: string;
        currency: string;
    };
    channel: string;
    channels: string[];
    qrContent: string;
    wakeUpUrl: string;
    statusUrl: string;
    successUrl: string;
}

const CHANNEL_LABELS: Record<string, string> = {
    alipay: '支付宝 Alipay',
    wechat: '微信支付 WeChat Pay',
};

const PERIOD_LABELS: Record<string, string> = {
    monthly: 'Monthly',
    quarterly: 'Quarterly',
    yearly: 'Yearly',
    lifetime: 'Lifetime',
};

const POLL_INTERVAL_MS = 2500;

/**
 * `/pay/domestic/{order}` (CSR, noindex — SPEC §7.5, §15.3): the domestic
 * QR payment page. Desktop renders the provider QR for scan-to-pay; mobile
 * gets the app wake-up deep link. The page polls the status endpoint and
 * forwards to the success page the moment the paid notify (or the poll's
 * own live query) activates the order.
 */
export default function DomesticPayment({ order, channel, channels, qrContent, wakeUpUrl, statusUrl, successUrl }: DomesticPaymentProps) {
    const [qrImage, setQrImage] = useState<string | null>(null);

    useEffect(() => {
        let cancelled = false;

        QRCode.toDataURL(qrContent, { width: 320, margin: 1 }).then((url) => {
            if (!cancelled) {
                setQrImage(url);
            }
        });

        return () => {
            cancelled = true;
        };
    }, [qrContent]);

    useEffect(() => {
        const timer = setInterval(async () => {
            try {
                const response = await fetch(statusUrl, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    return;
                }

                const state = (await response.json()) as { status: string; paid: boolean };

                if (state.paid) {
                    clearInterval(timer);
                    router.visit(successUrl);
                }
            } catch {
                // Transient network error — the next poll retries.
            }
        }, POLL_INTERVAL_MS);

        return () => clearInterval(timer);
    }, [statusUrl, successUrl]);

    const selectChannel = (option: string) => {
        if (option !== channel) {
            router.get(window.location.pathname, { channel: option }, { preserveState: false });
        }
    };

    return (
        <AuthLayout title="扫码支付 Domestic payment" description="Scan the QR code with your payment app to complete the purchase.">
            <Head title="Domestic payment" />

            <div className="flex flex-col gap-6">
                <div className="grid grid-cols-2 gap-2" role="tablist" aria-label="Payment method">
                    {channels.map((option) => (
                        <button
                            key={option}
                            type="button"
                            role="tab"
                            aria-selected={option === channel}
                            onClick={() => selectChannel(option)}
                            className={`rounded-lg border px-3 py-2.5 text-sm font-medium transition ${
                                option === channel
                                    ? 'border-neutral-900 bg-neutral-900 text-white dark:border-neutral-100 dark:bg-neutral-100 dark:text-neutral-900'
                                    : 'border-neutral-300 bg-white text-neutral-700 hover:border-neutral-400 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300'
                            }`}
                        >
                            {CHANNEL_LABELS[option] ?? option}
                        </button>
                    ))}
                </div>

                <p className="text-center text-sm text-neutral-600 dark:text-neutral-400">
                    {order.plan.charAt(0).toUpperCase() + order.plan.slice(1)} · {PERIOD_LABELS[order.billing_period] ?? order.billing_period} ·{' '}
                    <span className="font-semibold text-neutral-900 dark:text-neutral-100">
                        ¥{order.amount} {order.currency}
                    </span>
                </p>

                {/* Desktop: scan-to-pay QR. */}
                <div className="hidden flex-col items-center gap-3 sm:flex">
                    {qrImage ? (
                        <img
                            src={qrImage}
                            alt={`${CHANNEL_LABELS[channel] ?? channel} QR code`}
                            className="h-64 w-64 rounded-lg border border-neutral-200 dark:border-neutral-700"
                        />
                    ) : (
                        <div className="h-64 w-64 animate-pulse rounded-lg bg-neutral-100 dark:bg-neutral-800" aria-label="Loading QR code" />
                    )}
                    <p className="text-sm text-neutral-600 dark:text-neutral-400">打开{CHANNEL_LABELS[channel] ?? channel}，扫一扫完成支付</p>
                </div>

                {/* Mobile: app wake-up deep link, QR kept as a fallback for scanning from another device. */}
                <div className="flex flex-col gap-4 sm:hidden">
                    <Button asChild size="lg" className="w-full">
                        <a href={wakeUpUrl}>打开{CHANNEL_LABELS[channel] ?? channel}支付</a>
                    </Button>
                    {qrImage && (
                        <img
                            src={qrImage}
                            alt={`${CHANNEL_LABELS[channel] ?? channel} QR code`}
                            className="mx-auto h-48 w-48 rounded-lg border border-neutral-200 dark:border-neutral-700"
                        />
                    )}
                    <p className="text-center text-xs text-neutral-500">或使用另一台设备扫码支付 · Or scan from another device</p>
                </div>

                <p className="text-center text-xs text-neutral-500">
                    等待支付结果… 支付成功后页面将自动跳转 · Waiting for payment — this page continues automatically. 14-day refund window.
                </p>
            </div>
        </AuthLayout>
    );
}
