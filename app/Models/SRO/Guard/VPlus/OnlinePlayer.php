<?php

namespace App\Models\SRO\Guard\VPlus;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class OnlinePlayer extends Model
{
    /**
     * The Database connection name for the model.
     *
     * @var string
     */
    protected $connection = 'vplus';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'dbo._OnlinePlayers';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = ['*'];

    public static function resolveCharnameFromToken(string $token): ?string
    {
        return self::where('WebToken', $token)->value('CharName16');
    }

    public static function addItemToChest(int $charID, int $itemID, int $quantity): bool
    {
        DB::connection('vplus')->statement(
            'EXEC _Char_AddItemToChest :charid, :itemid, :quantity, 0',
            [
                'charid' => $charID,
                'itemid' => $itemID,
                'quantity' => $quantity,
            ]
        );

        return true;
    }

    public static function addSilk(int $userJID, int $amount, int $silkType = 1, string $referer = 'Web'): bool
    {
        DB::connection('vplus')->statement(
            'EXEC _Account_AddSilkLive :jid, :silktype, :amount, :referer',
            [
                'jid' => $userJID,
                'silktype' => $silkType,
                'amount' => $amount,
                'referer' => $referer,
            ]
        );

        return true;
    }

    public static function addSilkToChar(int $charID, int $amount, int $silkType = 1, string $referer = 'Web'): bool
    {
        DB::connection('vplus')->statement(
            'EXEC _Char_AddSilkLive :charid, :silktype, :amount, :referer',
            [
                'charid' => $charID,
                'silktype' => $silkType,
                'amount' => $amount,
                'referer' => $referer,
            ]
        );

        return true;
    }
}
