<?php

namespace App\Models\SRO\Guard\VanGuard;

use App\Models\SRO\Shard\Char;
use App\Models\SRO\Shard\RefObjCommon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class GameServerWebAppsKeys extends Model
{
    /**
     * The Database connection name for the model.
     *
     * @var string
     */
    protected $connection = 'vanguard';

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
    protected $table = 'dbo._GameServerWebAppsKeys';

    /**
     * The primary Key column.
     *
     * @var string
     */
    protected $primaryKey = 'CharID';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = ['*'];

    public static function resolveCharnameFromKey(string $key): ?string
    {
        $charID = self::where('Key', $key)->value('CharID');

        if (!$charID) {
            return null;
        }

        return Char::where('CharID', (int) $charID)->value('CharName16');
    }

    public static function addItemViaShardManager(int $charID, int $itemID, int $quantity): bool
    {
        $itemCode = RefObjCommon::find($itemID)?->codeName;
        if (!$itemCode) {
            throw new \InvalidArgumentException('Item not found in _RefObjCommon');
        }

        DB::connection('vanguard')->statement(
            'EXEC _ShardManagerAddItem :charid, 0, :itemcode, :quantity, 0, 0',
            [
                'charid' => $charID,
                'itemcode' => $itemCode,
                'quantity' => $quantity,
            ]
        );

        return true;
    }

    public static function addItemChestBox(int $jid, int $charID, int $itemID, int $count, string $referer = 'web'): bool
    {
        $itemCode = RefObjCommon::find($itemID)?->codeName;
        if (!$itemCode) {
            throw new \InvalidArgumentException('Item not found in _RefObjCommon');
        }

        DB::connection('vanguard')->statement(
            'EXEC [dbo].[_SharedAddItemChestBox] @ItemCode = :itemcode, @JID = :jid, @CharID = :charid, @Count = :count, @Referer = :referer',
            [
                'itemcode' => $itemCode,
                'jid' => $jid,
                'charid' => $charID,
                'count' => $count,
                'referer' => $referer,
            ]
        );

        return true;
    }

    public static function getSilkAmount(int $jid): int
    {
        try {
            $result = DB::connection('vanguard')->selectOne(
                'SET NOCOUNT ON; DECLARE @ret INT; EXEC @ret = _SharedGetSilkAmount :jid; SELECT @ret AS CurrentSilkAmount',
                ['jid' => $jid]
            );

            return $result ? (int) $result->CurrentSilkAmount : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    public static function sendCharacterNotification(int $charID, string $message, int $type = 0): bool
    {
        DB::connection('vanguard')->statement(
            'EXEC _ShardManagerSendNotice :charid, :type, :message',
            [
                'charid' => $charID,
                'type' => $type,
                'message' => $message,
            ]
        );

        return true;
    }

    public static function updateSilkAmount(int $jid, int $amount): bool
    {
        try {
            $stmt = DB::connection('vanguard')->statement(
                'EXEC [dbo].[_SharedUpdateSilkAmount] @JID = :jid, @SilkAmount = :amount',
                ['jid' => $jid, 'amount' => $amount]
            );

            return $stmt;
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function getPremiumSilkAmount(int $jid): int
    {
        try {
            $result = DB::connection('vanguard')->selectOne(
                'SET NOCOUNT ON; DECLARE @ret INT; EXEC @ret = _SharedGetPremiumSilkAmount :jid; SELECT @ret AS CurrentPremiumSilkAmount',
                ['jid' => $jid]
            );

            return $result ? (int) $result->CurrentPremiumSilkAmount : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    public static function updatePremiumSilkAmount(int $jid, int $amount): bool
    {
        try {
            $stmt = DB::connection('vanguard')->statement(
                'EXEC [dbo].[_SharedUpdatePremiumSilkAmount] @JID = :jid, @SilkAmount = :amount',
                ['jid' => $jid, 'amount' => $amount]
            );

            return $stmt;
        } catch (\Exception $e) {
            return false;
        }
    }
}
