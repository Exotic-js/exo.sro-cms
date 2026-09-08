<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Download extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'desc',
        'url',
        'image',
        'category',
    ];

    protected static function booted()
    {
        static::created(fn () => self::clearCache());
        static::updated(fn () => self::clearCache());
        static::deleted(fn () => self::clearCache());
    }

    public static function getDownloads()
    {
        return Cache::rememberForever('download', function () {
            return self::all();
        });
    }

    protected static function clearCache(): void
    {
        Cache::forget('download');
    }
}
