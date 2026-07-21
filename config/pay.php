<?php

use Yansongda\Pay\Pay;

/**
 * Domestic payments (SPEC §7.5): Alipay + WeChat Pay via yansongda/pay.
 *
 * Every SDK call goes through App\Services\Billing\DomesticGateway — this
 * config is consumed there only. Merchant credentials come from the
 * 个体工商户 merchant accounts; certs are file paths (or key contents for
 * ALIPAY_APP_SECRET_CERT). Keep the sandbox flags on until go-live.
 */
return [
    'alipay' => [
        'default' => [
            // 支付宝开放平台 app id.
            'app_id' => env('ALIPAY_APP_ID', ''),
            // 应用私钥 (RSA2), contents — not a path.
            'app_secret_cert' => env('ALIPAY_APP_SECRET_CERT', ''),
            // 应用公钥证书 / 支付宝公钥证书 / 支付宝根证书 paths.
            'app_public_cert_path' => env('ALIPAY_APP_PUBLIC_CERT_PATH', ''),
            'alipay_public_cert_path' => env('ALIPAY_PUBLIC_CERT_PATH', ''),
            'alipay_root_cert_path' => env('ALIPAY_ROOT_CERT_PATH', ''),
            // Server-to-server notify endpoint (route: pay.domestic.alipay.notify).
            'notify_url' => env('ALIPAY_NOTIFY_URL', ''),
            'return_url' => env('ALIPAY_RETURN_URL', ''),
            'mode' => env('ALIPAY_SANDBOX', true) ? Pay::MODE_SANDBOX : Pay::MODE_NORMAL,
        ],
    ],

    'wechat' => [
        'default' => [
            // 微信支付商户号 + the app id the QR/native trade is bound to.
            'mch_id' => env('WECHAT_MCH_ID', ''),
            'app_id' => env('WECHAT_APP_ID', ''),
            // API v3 key + merchant API certificate (apiclient_key.pem
            // contents or path) + certificate path (apiclient_cert.pem).
            'mch_secret_key' => env('WECHAT_MCH_SECRET_KEY', ''),
            'mch_secret_cert' => env('WECHAT_MCH_SECRET_CERT', ''),
            'mch_public_cert_path' => env('WECHAT_MCH_PUBLIC_CERT_PATH', ''),
            // Platform certificate(s), keyed by serial no; empty means the
            // SDK fetches & caches them on first notify verification.
            'wechat_public_cert_path' => [],
            // Server-to-server notify endpoint (route: pay.domestic.wechat.notify).
            'notify_url' => env('WECHAT_NOTIFY_URL', ''),
            'mode' => env('WECHAT_SANDBOX', true) ? Pay::MODE_SANDBOX : Pay::MODE_NORMAL,
        ],
    ],

    // The SDK logs through its own logger when enabled; keep it off — the
    // gateway surfaces everything the app needs.
    'logger' => [
        'enable' => false,
    ],
];
