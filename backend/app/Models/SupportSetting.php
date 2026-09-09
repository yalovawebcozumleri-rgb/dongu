<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportSetting extends Model
{
    protected $fillable = ['whatsapp_phone', 'changed_by_user_id'];

    public static function phone(): string
    {
        return static::find(1)?->whatsapp_phone ?? config('support.whatsapp_phone');
    }

    public static function displayPhone(): string
    {
        $phone = static::phone();

        if (preg_match('/^90([0-9]{3})([0-9]{3})([0-9]{2})([0-9]{2})$/', $phone, $matches)) {
            return "+90 {$matches[1]} {$matches[2]} {$matches[3]} {$matches[4]}";
        }

        return '+'.$phone;
    }

    public static function whatsappUrl(?string $message = null): string
    {
        return 'https://wa.me/'.static::phone().'?text='.rawurlencode($message ?? config('support.whatsapp_message'));
    }
}
