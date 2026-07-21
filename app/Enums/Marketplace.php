<?php

namespace App\Enums;

enum Marketplace: string
{
    case Shopee = 'shopee';
    case Tiktokshop = 'tiktokshop';
    case Whatsapp = 'whatsapp';
    case Web = 'web';

    public function label(): string
    {
        return match ($this) {
            self::Shopee => 'Shopee',
            self::Tiktokshop => 'TikTok Shop',
            self::Whatsapp => 'WhatsApp',
            self::Web => 'Web',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Shopee => 'bg-amber-100 text-amber-800',
            self::Tiktokshop => 'bg-sand-200 text-sand-800',
            self::Whatsapp => 'bg-green-100 text-green-800',
            self::Web => 'bg-violet-100 text-violet-800',
        };
    }

    /** Channel langsung (WhatsApp/Web) — dana langsung diterima, order langsung LUNAS. */
    public function isLangsungLunas(): bool
    {
        return in_array($this, [self::Whatsapp, self::Web], true);
    }
}
