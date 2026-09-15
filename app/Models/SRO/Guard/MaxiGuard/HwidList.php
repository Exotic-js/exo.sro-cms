<?php

namespace App\Models\SRO\Guard\MaxiGuard;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class HwidList extends Model
{
    /**
     * The Database connection name for the model.
     *
     * @var string
     */
    protected $connection = 'maxiguard';

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
    protected $table = 'dbo.hwidlist_V2';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = ['*'];

    public static function resolveCharnameFromToken(string $token): ?string
    {
        return self::where('token', $token)->value('charname');
    }

    public static function addItemToChest(string $charname, string $itemCode, int $quantity, string $notice = 'Web Reward'): bool
    {
        DB::connection('maxiguard')->statement(
            'EXEC _AddItemToChest :charname, :itemcode, :quantity, :notice',
            [
                'charname' => $charname,
                'itemcode' => $itemCode,
                'quantity' => $quantity,
                'notice' => $notice,
            ]
        );

        return true;
    }

    public static function addSilk(string $charname, int $amount, string $executor = 'web'): bool
    {
        DB::connection('maxiguard')
            ->table('_BridgeCommands')
            ->insert([
                'CommandID' => 56,
                'Executor' => $executor,
                'Data1' => $charname,
                'Data2' => '0',
                'Data3' => (string) $amount,
                'Date' => DB::raw('GETDATE()'),
            ]);

        return true;
    }

    public static function sendCharacterNotification(string $charname, string $message, int $type = 1): bool
    {
        DB::connection('maxiguard')
            ->table('_BridgeCommands')
            ->insert([
                'CommandID' => 500,
                'Executor' => 'system',
                'Data1' => (string) $type,
                'Data2' => $message,
                'Data3' => $charname,
                'Date' => DB::raw('GETDATE()'),
            ]);

        return true;
    }
}