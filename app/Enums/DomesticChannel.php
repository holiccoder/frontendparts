<?php

namespace App\Enums;

/**
 * Domestic payment rail (SPEC §7.5): which of the two CNY providers a
 * domestic pre-order / notify belongs to.
 */
enum DomesticChannel: string
{
    case Alipay = 'alipay';
    case Wechat = 'wechat';
}
