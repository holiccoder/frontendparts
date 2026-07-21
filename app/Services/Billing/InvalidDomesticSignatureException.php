<?php

namespace App\Services\Billing;

use RuntimeException;

/**
 * A domestic notify failed verification (bad signature or undecryptable
 * resource — SPEC §7.5). The notify controllers answer 403, exactly like an
 * invalid Paddle-Signature, so the provider keeps retrying a legitimately
 * signed notification while forged ones are dropped.
 */
class InvalidDomesticSignatureException extends RuntimeException {}
