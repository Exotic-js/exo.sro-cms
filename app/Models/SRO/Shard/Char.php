<?php

namespace App\Models\SRO\Shard;

use App\Models\SRO\Account\WebItemCertifyKey;
use App\Models\SRO\Guard\MaxiGuard\HwidList;
use App\Models\SRO\Guard\VanGuard\GameServerWebAppsKeys;
use App\Models\SRO\Guard\VPlus\OnlinePlayer;
use App\Models\SRO\Log\LogChatMessage;
use App\Models\SRO\Log\LogEventChar;
use App\Models\SRO\Log\LogInstanceWorldInfo;
use App\Services\InventoryService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Char extends Model
{
    use HasFactory;

    /**
     * The Database connection name for the model.
     *
     * @var string
     */
    protected $connection = 'shard';

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
    protected $table = 'dbo._Char';

    /**
     * The table primary Key
     *
     * @var string
     */
    protected $primaryKey = 'CharID';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'CharID',
        'RefObjID',
        'CharName16',
        'NickName16',
        'LastLogout',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array<int, string>
     */
    protected $dates = [
        'LastLogout'
    ];

    /**
     * The storage format of the model's date columns.
     *
     * @var string
     */
    protected $dateFormat = 'Y-m-d H:i:s';

    public static function getPlayerRanking($limit = 25, $CharID = 0, $CharName = '')
    {
        return Cache::remember("ranking_player_fast_{$limit}_{$CharID}_{$CharName}", config('global.cache.ranking_player', 3600), function () use ($limit, $CharID, $CharName) {
            $query = self::from(DB::raw('_Char WITH (NOLOCK)'))
            ->select(
                '_Char.CharID',
                '_Char.CharName16',
                '_Char.CurLevel',
                '_Char.RefObjID',
                '_Char.NickName16',
                '_Char.HwanLevel',
                '_Char.HP',
                '_Char.MP',
                '_Char.Strength',
                '_Char.Intellect',
                '_Guild.ID',
                '_Guild.Name',

                DB::raw("(
                    SELECT
                          SUM(ISNULL(b.nOptValue,0))
                        + SUM(ISNULL(i.OptLevel,0))
                        + SUM(ISNULL(r.ReqLevel1,0))
                        + SUM(CASE WHEN r.CodeName128 LIKE '%_A_RARE%' THEN 5 ELSE 0 END)
                        + SUM(CASE WHEN r.CodeName128 LIKE '%_B_RARE%' THEN 10 ELSE 0 END)
                        + SUM(CASE WHEN r.CodeName128 LIKE '%_C_RARE%' THEN 15 ELSE 0 END)
                    FROM _Inventory inv WITH (NOLOCK)
                    JOIN _Items i WITH (NOLOCK) ON i.ID64 = inv.ItemID
                    JOIN _RefObjCommon r WITH (NOLOCK) ON r.ID = i.RefItemID
                    LEFT JOIN _BindingOptionWithItem b WITH (NOLOCK)
                        ON b.nItemDBID = i.ID64
                       AND b.bOptType = 2
                       AND b.nOptValue > 0
                    WHERE inv.CharID = _Char.CharID
                      AND inv.Slot < 13
                      AND inv.Slot NOT IN (7,8)
                      AND inv.ItemID > 0
                ) AS ItemPoints")
            );

            if (config('global.server.version') === 'vSRO') {
                $query->addSelect('_CharTrijob.JobType', '_CharTrijob.Level')
                    ->leftJoin(DB::raw('_CharTrijob WITH (NOLOCK)'), '_Char.CharID', '=', '_CharTrijob.CharID');
            } else {
                $query->addSelect('_UserTradeConflictJob.JobType', '_CharTradeConflictJob.JobLevel')
                    ->join(DB::raw('_User WITH (NOLOCK)'), '_User.CharID', '=', '_Char.CharID')
                    ->join(DB::raw('_UserTradeConflictJob WITH (NOLOCK)'), '_UserTradeConflictJob.UserJID', '=', '_User.UserJID')
                    ->leftJoin(DB::raw('_CharTradeConflictJob WITH (NOLOCK)'), '_CharTradeConflictJob.CharID', '=', '_Char.CharID');
            }

            $query->leftJoin(DB::raw('_Guild WITH (NOLOCK)'), '_Char.GuildID', '=', '_Guild.ID')
                ->where('_Char.deleted', 0)
                ->when($CharID > 0, fn ($q) => $q->where('_Char.CharID', $CharID))
                ->when($CharName, fn ($q) => $q->where('_Char.CharName16', 'like', "%{$CharName}%"))
                ->whereNotIn('_Char.CharName16', config('ranking.hidden.characters'))
                ->orderByDesc('ItemPoints')
                ->orderByDesc('_Char.CurLevel')
                ->limit($limit);

            return $query->get();
        });
    }

    public static function getLevelRanking($limit = 25)
    {
        return Cache::remember("ranking_level_{$limit}", config('global.cache.ranking_player', 3600), function () use ($limit) {
            return self::from(DB::raw('_Char WITH (NOLOCK)'))
                ->select(
                    '_Char.CharID',
                    '_Char.CharName16',
                    '_Char.CurLevel',
                    '_Char.RefObjID',
                    '_Char.ExpOffset',
                    '_Guild.ID',
                    '_Guild.Name',
                )
                ->leftJoin(DB::raw('_Guild WITH (NOLOCK)'), '_Char.GuildID', '=', '_Guild.ID')
                ->where('_Char.deleted', 0)
                ->whereNotIn('_Char.CharName16', config('ranking.hidden.characters'))
                ->orderByDesc('_Char.CurLevel')
                ->orderByDesc('_Char.ExpOffset')
                ->limit($limit)
                ->get();
        });
    }

    public function getItemPointAttribute()
    {
        return cache()->remember("char_item_points_{$this->CharID}", config('global.cache.character_info', 86400), function () {
            return DB::connection($this->getConnectionName())
                ->table(DB::raw('_Inventory AS inv WITH (NOLOCK)'))
                ->join(DB::raw('_Items AS i WITH (NOLOCK)'), 'i.ID64', '=', 'inv.ItemID')
                ->join(DB::raw('_RefObjCommon AS r WITH (NOLOCK)'), 'r.ID', '=', 'i.RefItemID')
                ->leftJoin(DB::raw('_BindingOptionWithItem AS b WITH (NOLOCK)'), function ($join) {
                    $join->on('b.nItemDBID', '=', 'i.ID64')
                        ->where('b.bOptType', 2)
                        ->where('b.nOptValue', '>', 0);
                })
                ->where('inv.CharID', $this->CharID)
                ->where('inv.Slot', '<', 13)
                ->whereNotIn('inv.Slot', [7, 8])
                ->where('inv.ItemID', '>', 0)
                ->selectRaw("
                  SUM(ISNULL(b.nOptValue,0))
                + SUM(ISNULL(i.OptLevel,0))
                + SUM(ISNULL(r.ReqLevel1,0))
                + SUM(CASE WHEN r.CodeName128 LIKE '%_A_RARE%' THEN 5 ELSE 0 END)
                + SUM(CASE WHEN r.CodeName128 LIKE '%_B_RARE%' THEN 10 ELSE 0 END)
                + SUM(CASE WHEN r.CodeName128 LIKE '%_C_RARE%' THEN 15 ELSE 0 END)
            ")
            ->value(DB::raw(''));
        });
    }

    public function getCharJobISRO()
    {
        return cache()->remember("char_job_isro_{$this->CharID}", config('global.cache.character_info', 86400), function () {
            return DB::connection($this->getConnectionName())
                ->table(DB::raw('_User as u WITH (NOLOCK)'))
                ->join(DB::raw('_UserTradeConflictJob as j WITH (NOLOCK)'), 'j.UserJID', '=', 'u.UserJID')
                ->leftJoin(DB::raw('_CharTradeConflictJob as c WITH (NOLOCK)'), 'c.CharID', '=', 'u.CharID')
                ->where('u.CharID', $this->CharID)
                ->select(
                    'j.JobType',
                    DB::raw('ISNULL(c.JobLevel, 0) as JobLevel')
                )
                ->first();
        });
    }

    public function getCharJobVSRO()
    {
        return cache()->remember("char_job_vsro_{$this->CharID}", config('global.cache.character_info', 86400), function () {
            return DB::connection($this->getConnectionName())
                ->table(DB::raw('_CharTrijob WITH (NOLOCK)'))
                ->where('CharID', $this->CharID)
                ->select('JobType', 'Level')
                ->first();
        });
    }

    public function getCharJobAttribute()
    {
        if (config('global.server.version') === 'vSRO') {
            return $this->getCharJobVSRO();
        }

        return $this->getCharJobISRO();
    }

    public function setCharUnstuckPosition(): bool
    {
        return $this->update([
            'LatestRegion' => 25000,
            'PosX' => 969,
            'PosY' => 0,
            'PosZ' => 1369,
            'AppointedTeleport' => 2094,
            'TelPosX' => 0,
            'TelPosY' => 0,
            'TelPosZ' => 0,
            'DiedPosX' => 0,
            'DiedPosY' => 0,
            'DiedPosZ' => 0,
            'WorldID' => 1,
            'TelWorldID' => 1,
            'DiedWorldID' => 1,
        ]);
    }

    public static function getCharByName($name)
    {
        return Cache::remember("char_name_{$name}", config('global.cache.character_info', 86400), function () use ($name) {
            return self::where('CharName16', $name)->firstOrFail();
        });
    }

    public static function getCharLocations()
    {
        return self::select('CharID', 'CharName16', 'PosX', 'PosZ', 'PosY', 'LatestRegion')->get();
    }

    public function getCharStatus()
    {
        return $this->hasMany(LogEventChar::class, 'CharID', 'CharID')
            ->whereIn('EventID', [4, 6])
            ->orderByDesc('EventTime');
    }

    public function getIsOnlineAttribute(): bool
    {
        return Cache::remember("char_online_{$this->CharID}", 600, function () {
            return optional($this->getCharStatus()->first())->EventID == 4;
        });
    }

    public function getIsOfflineAttribute(): bool
    {
        return Cache::remember("char_offline_{$this->CharID}", 600, function () {
            return optional($this->getCharStatus()->first())->EventID == 6;
        });
    }

    public static function getCharCount()
    {
        return Cache::remember('char_count', 600, fn () => self::count());
    }

    public static function getGoldSum()
    {
        return Cache::remember('gold_sum', 600, fn () => self::query()->sum('RemainGold'));
    }

    protected static function booted()
    {
        static::saved(function () {Cache::forget('char_count');Cache::forget('gold_sum');});
        static::deleted(function () {Cache::forget('char_count');Cache::forget('gold_sum');});
    }

    public function getCharInventorySet(int $max = 12, int $min = 0, int $not = 8)
    {
        return app(InventoryService::class)->getInventorySet($this->CharID, $max, $min, $not);
    }

    public function getCharInventoryAvatarAttribute()
    {
        return app(InventoryService::class)->getInventoryAvatar($this->CharID);
    }

    public function getCharInventoryJobAttribute()
    {
        if (config('global.server.version') === 'vSRO') return collect();
        return app(InventoryService::class)->getInventoryJob($this->CharID);
    }

    public function getCharStorageItemsAttribute()
    {
        return app(InventoryService::class)->getStorageItems($this->user?->UserJID ?? 0, 180, 0);
    }

    public function getCharChestItemsAttribute()
    {
        return BuyCashItemListByWeb::getWebChest($this->user?->UserJID ?? 0);
    }

    public function getCharPetItems(?int $petId = null)
    {
        return app(InventoryService::class)->getPetItems($this->CharID, $petId, 196, 0);
    }

    public function getUniqueHistoryAttribute()
    {
        return LogInstanceWorldInfo::getUniquesKill(5, $this->CharID) ?? collect();
    }

    public function getGlobalHistoryAttribute()
    {
        return LogChatMessage::getGlobalsHistory(5, $this->CharName16) ?? collect();
    }

    public function getPvpKillAttribute()
    {
        return LogEventChar::getKillDeathRanking('pvp', 1, $this->CharID)->first();
    }

    public function getJobKillAttribute()
    {
        return LogEventChar::getKillDeathRanking('job', 1, $this->CharID)->first();
    }

    public function getJIDAttribute(): ?int
    {
        return cache()->remember("char_jid_{$this->CharID}", 86400, fn() => $this->user?->UserJID);
    }

    public function getGuildAttribute()
    {
        return cache()->remember("char_guild_{$this->CharID}", 86400, fn() => $this->guild()->first());
    }

    public function getHasJobSuitAttribute(): bool
    {
        return (bool) Inventory::getInventorySlot($this->CharID, 8);
    }

    public function getCharPetsAttribute()
    {
        return InvCOS::getPetNames($this->CharID);
    }

    public function getBuildInfoAttribute()
    {
        return CharSkillMastery::getCharBuildInfo($this->CharID);
    }

    public function getBuffInfoAttribute()
    {
        return TimedJob::getCharBuffInfo($this->CharID);
    }

    public function getGuildMemberUser()
    {
        return $this->hasOne(GuildMember::class, 'CharID', 'CharID');
    }

    public function getGuildUser()
    {
        return $this->hasOne(Guild::class, 'ID', 'GuildID')->where('ID', '!=', 0);
    }

    public function User()
    {
        return $this->belongsTo(User::class, 'CharID', 'CharID');
    }

    public function guild()
    {
        return $this->belongsTo(Guild::class, 'GuildID', 'ID');
    }

public function addItem(string $itemCode, int $quantity = 1, int $type = 1): int
    {
        try {
            $result = DB::connection($this->getConnectionName())->selectOne(
                'DECLARE @return_code int; EXEC @return_code = _ADD_ITEM_EXTERN @charname = ?, @codename = ?, @data = ?, @opt_level = ?, @variance = NULL; SELECT @return_code AS return_code;',
                [$this->CharName16, $itemCode, $quantity, $type]
            );

            if (is_object($result) && property_exists($result, 'return_code')) {
                return (int) $result->return_code;
            }

            return -1;
        } catch (\Exception $e) {
            return -1;
        }
    }

    private function addItemToChest(int $itemID, int $quantity, string $itemCode): void
    {
        try {
            $jid = $this->JID;
            if (!$jid) {
                throw new \InvalidArgumentException('Character not found in _User');
            }

            $accountPdo = DB::connection('account')->getPdo();

            $stmt1 = $accountPdo->prepare("INSERT INTO WEB_ITEM_GIVE_LIST VALUES (:jid, 64, :charid, 130, :itemcode, NULL, 'SN_' + :itemcodestr, 1, 0, 0, 0, 0, 0, 'Web', 0, GETDATE(), NULL, NULL, NULL)");
            $stmt1->execute([
                ':jid' => $jid,
                ':charid' => $this->CharID,
                ':itemcode' => $itemCode,
                ':itemcodestr' => $itemCode,
            ]);

            $identity = (int) $accountPdo->lastInsertId();

            $shardPdo = DB::connection('shard')->getPdo();
            $stmt2 = $shardPdo->prepare("INSERT INTO _BuyCashItemList_By_Web VALUES (:id, :jid2, 1, 'SN_' + :itemcodestr2, :count, :itemid, :totalcount, GETDATE(), NULL, NULL, NULL, NULL, NULL, NULL)");
            $stmt2->execute([
                ':id' => $identity,
                ':jid2' => $jid,
                ':itemcodestr2' => $itemCode,
                ':count' => $quantity,
                ':itemid' => $itemID,
                ':totalcount' => $quantity,
            ]);
        } catch (\InvalidArgumentException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new \InvalidArgumentException($e->getMessage());
        }
    }

    public function deliverItem(int $itemID, int $quantity): bool
    {
        $itemCode = RefObjCommon::find($itemID)?->codeName;
        if (!$itemCode) {
            throw new \InvalidArgumentException('Unknown item');
        }

        switch (strtolower(config('global.server.guard', 'isro'))) {
            case 'maxiguard':
                HwidList::addItemToChest($this->CharName16, $itemCode, $quantity);
                break;

            case 'vplus':
                OnlinePlayer::addItemToChest($this->CharID, $itemID, $quantity);
                break;

            case 'vanguard':
                GameServerWebAppsKeys::addItemViaShardManager($this->CharID, $itemID, $quantity);
                break;

            case 'none':
                $returnCode = $this->addItem($itemCode, $quantity);
                $errorMessages = [
                    1  => 'Success',
                    -1 => 'Unknown item',
                    -2 => 'Character does not exist',
                    -3 => 'Inventory is full',
                    -4 => 'Item ID is NULL',
                    -5 => 'Item reference link is NULL',
                    -6 => 'Not an item',
                    -7 => 'Failed for unknown reason',
                ];
                if ($returnCode !== 1) {
                    throw new \InvalidArgumentException($errorMessages[$returnCode] ?? 'Unknown error code: ' . $returnCode);
                }
                break;

            case 'isro':
            default:
                $this->addItemToChest($itemID, $quantity, $itemCode);
                break;
        }

        return true;
    }

    public function sendNotification(string $message, int $type = 1): bool
    {
        switch (strtolower(config('global.server.guard', 'isro'))) {
            case 'maxiguard':
                HwidList::sendCharacterNotification($this->CharName16, $message, $type);
                break;

            case 'vplus':
                OnlinePlayer::sendCharacterNotification($this->CharID, $message, $type);
                break;

            case 'vanguard':
                GameServerWebAppsKeys::sendCharacterNotification($this->CharID, $message, $type);
                break;

            case 'none':
            case 'isro':
            default:
                throw new \InvalidArgumentException('Notifications are not supported for the current guard system.');
        }

        return true;
    }

    public static function resolveCharname(Request $request): ?string
    {
        $guardSystem = strtolower(config('global.server.guard', 'isro'));

        switch ($guardSystem) {
            case 'maxiguard':
                $token = $request->query('webtoken');

                return $token ? HwidList::resolveCharnameFromToken($token) : null;

            case 'vplus':
                $token = $request->header('X-Session-Token');

                return $token ? OnlinePlayer::resolveCharnameFromToken($token) : null;

            case 'vanguard':
                $key = $request->query('key');

                return $key ? GameServerWebAppsKeys::resolveCharnameFromKey($key) : null;

            case 'none':
                $charname = $request->query('charname');
                if (empty($charname)) {
                    return null;
                }

                // Verify the character actually exists in the _Char table
                return self::where('CharName16', $charname)->exists() ? $charname : null;

            case 'isro':
            default:
                $jid = $request->query('jid');
                $key = $request->query('key');
                if (empty($jid) || empty($key)) {
                    return null;
                }

                $certifyKeyRecord = WebItemCertifyKey::getCertifyKey($jid);
                if (!$certifyKeyRecord) {
                    return null;
                }

                $generatedKey = strtoupper(md5($jid . $certifyKeyRecord->Certifykey . config('global.server.saltKey')));
                if ($generatedKey !== strtoupper($key)) {
                    return null;
                }

                return DB::connection('shard')
                    ->table('_Char as c')
                    ->join('_User as u', 'c.CharID', '=', 'u.CharID')
                    ->where('u.UserJID', $jid)
                    ->orderByDesc('c.LastLogout')
                    ->value('c.CharName16');
        }
    }
}
