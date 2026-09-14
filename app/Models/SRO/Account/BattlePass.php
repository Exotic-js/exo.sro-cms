<?php

namespace App\Models\SRO\Account;

use App\Models\SRO\Shard\Char;
use App\Services\InventoryService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class BattlePass extends Model
{
    /**
     * The Database connection name for the model.
     *
     * @var string
     */
    protected $connection = 'account';

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
    protected $table = 'dbo.WEB_BattlePassRecords';

    /**
     * The table primary Key.
     *
     * @var string
     */
    protected $primaryKey = 'ID';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'CharID',
        'IsPremium',
        'Points',
        'ClaimedItems',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'CharID' => 'integer',
        'IsPremium' => 'boolean',
        'Points' => 'integer',
    ];

    // ============================================================
    // TIER DEFINITIONS (from config/ingame.php)
    // ============================================================

    public static function getAllTiers(): array
    {
        $tiers = config('ingame.battlepass.tiers', []);
        if (empty($tiers)) {
            return [];
        }

        return Cache::remember('battle_pass_tiers', config('global.cache.character_info', 3600), function () use ($tiers) {
            $itemIds = collect($tiers)
                ->flatMap(fn ($tier) => [
                    $tier['free_item']['id'],
                    $tier['vip_item']['id'],
                ])
                ->filter()
                ->unique()
                ->values()
                ->all();

            $itemInfo = [];
            $inventoryService = app(InventoryService::class);
            foreach ($itemIds as $itemId) {
                $infoItem = $inventoryService->getItemInfoByRefItem($itemId);
                $itemInfo[$itemId] = $infoItem ? [
                    'name' => $infoItem->ItemInfo->ItemName ?? 'Unknown Item',
                    'icon' => preg_replace('/\.png$/i', '', $infoItem->ImgPath),
                    'CodeName128' => $infoItem->ItemInfo->CodeName128 ?? '',
                ] : null;
            }

            return array_values(array_map(function ($tier) use ($itemInfo) {
                $freeItem = $itemInfo[$tier['free_item']['id']] ?? null;
                $vipItem = $itemInfo[$tier['vip_item']['id']] ?? null;

                return [
                    'id' => (int) $tier['id'],
                    'level' => (int) $tier['id'],
                    'pointsRequired' => (int) $tier['points'],
                    'freeItem' => [
                        'itemID' => (int) $tier['free_item']['id'],
                        'quantity' => (int) $tier['free_item']['qty'],
                        'name' => $freeItem['name'] ?? 'Unknown Item',
                        'icon' => $freeItem['icon'] ?? '',
                        'CodeName128' => $freeItem['CodeName128'] ?? '',
                    ],
                    'vipItem' => [
                        'itemID' => (int) $tier['vip_item']['id'],
                        'quantity' => (int) $tier['vip_item']['qty'],
                        'name' => $vipItem['name'] ?? 'Unknown Item',
                        'icon' => $vipItem['icon'] ?? '',
                        'CodeName128' => $vipItem['CodeName128'] ?? '',
                    ],
                ];
            }, $tiers));
        });
    }

    public static function getTierById(int $tierID): ?array
    {
        foreach (self::getAllTiers() as $tier) {
            if ($tier['id'] === $tierID) {
                return $tier;
            }
        }

        return null;
    }

    // ============================================================
    // RECORD HELPERS
    // ============================================================

    public static function getForChar(int $charID): ?self
    {
        return self::where('CharID', $charID)->first();
    }

    public static function getOrCreateForChar(int $charID): self
    {
        $record = self::getForChar($charID);
        if ($record) {
            return $record;
        }

        return self::create([
            'CharID' => $charID,
            'IsPremium' => 0,
            'Points' => 0,
            'ClaimedItems' => '',
        ]);
    }

    public function getClaimedItemsArray(): array
    {
        return $this->ClaimedItems ? explode(',', $this->ClaimedItems) : [];
    }

    public function hasClaimedItem(int $itemID): bool
    {
        return in_array((string) $itemID, $this->getClaimedItemsArray(), true);
    }

    // ============================================================
    // PROGRESS CALCULATION
    // ============================================================

    public static function getProgress(int $currentPoints, array $tiers): array
    {
        $currentLevel = 0;
        $nextLevelPoints = 0;
        $maxLevel = count($tiers);

        foreach ($tiers as $tier) {
            if ($currentPoints >= $tier['pointsRequired']) {
                $currentLevel = $tier['level'];
            } else {
                $nextLevelPoints = $tier['pointsRequired'];
                break;
            }
        }

        if ($currentLevel < $maxLevel) {
            $prevLevelPoints = $currentLevel > 0 ? $tiers[$currentLevel - 1]['pointsRequired'] : 0;
            $pointsInCurrentLevel = $currentPoints - $prevLevelPoints;
            $pointsNeededForNext = $nextLevelPoints - $prevLevelPoints;
            $progressPercent = $pointsNeededForNext > 0
                ? round(($pointsInCurrentLevel / $pointsNeededForNext) * 100, 1)
                : 100;
        } else {
            $currentLevel = $maxLevel;
            $progressPercent = 100;
        }

        return [
            'currentLevel' => $currentLevel,
            'nextLevelPoints' => $nextLevelPoints,
            'maxLevel' => $maxLevel,
            'progressPercent' => $progressPercent,
        ];
    }

    // ============================================================
    // BATTLE PASS ACTIONS
    // ============================================================

    public static function claimReward(int $charID, int $tierID, string $claimType, int $currentLevel): self
    {
        $targetTier = self::getTierById($tierID);

        if (!$targetTier) {
            throw new \InvalidArgumentException('Tier not found');
        }

        $record = self::getOrCreateForChar($charID);

        if ($claimType === 'vip' && !$record->IsPremium) {
            throw new \InvalidArgumentException('You need Premium Pass to claim VIP rewards!');
        }

        $itemID = ($claimType === 'vip') ? $targetTier['vipItem']['itemID'] : $targetTier['freeItem']['itemID'];
        $quantity = ($claimType === 'vip') ? $targetTier['vipItem']['quantity'] : $targetTier['freeItem']['quantity'];

        if ($record->hasClaimedItem($itemID)) {
            throw new \InvalidArgumentException('You already claimed this item!');
        }

        if ($currentLevel < $tierID) {
            throw new \InvalidArgumentException('Not enough level to claim this item!');
        }

        $char = Char::find($charID);
        if (!$char) {
            throw new \InvalidArgumentException('Character does not exist');
        }

        $char->deliverItem($itemID, $quantity);

        $currentClaimed = $record->getClaimedItemsArray();
        $newClaimed = empty($currentClaimed)
            ? (string) $itemID
            : implode(',', $currentClaimed) . ',' . $itemID;

        $record->update(['ClaimedItems' => $newClaimed]);

        return $record;
    }

    public static function purchasePremium(int $charID): int
    {
        if (!config('ingame.battlepass.premium_enabled')) {
            throw new \InvalidArgumentException('Premium Pass is currently disabled.');
        }

        $record = self::getOrCreateForChar($charID);
        if ($record->IsPremium) {
            throw new \InvalidArgumentException('You are already premium!');
        }

        $jid = Char::find($charID)?->JID;
        if (!$jid) {
            throw new \InvalidArgumentException('Unable to get JID for this character');
        }

        $premiumPrice = (int) config('ingame.battlepass.premium_price');
        $currentSilk = (int) (TbUser::find($jid)?->muUser?->getSilk?->PremiumSilk ?? 0);
        if ($currentSilk < $premiumPrice) {
            throw new \InvalidArgumentException('Insufficient Silk amount. You need ' . $premiumPrice . ' Silk.');
        }

        TbUser::updateSilk($jid, 3, -$premiumPrice);
        $record->update(['IsPremium' => 1]);

        return $currentSilk - $premiumPrice;
    }

    public static function purchasePoints(int $charID, int $qty): int
    {
        if ($qty <= 0 || $qty > 99) {
            throw new \InvalidArgumentException('Invalid quantity (1-99 allowed)');
        }

        $jid = Char::find($charID)?->JID;
        if (!$jid) {
            throw new \InvalidArgumentException('Unable to get JID for this character');
        }

        $record = self::getOrCreateForChar($charID);

        $pointsPerPurchase = (int) config('ingame.battlepass.points_per_purchase');
        $silkPerPoint = (int) config('ingame.battlepass.silk_per_point');
        $totalCost = $qty * $silkPerPoint;
        $currentSilk = (int) (TbUser::find($jid)?->muUser?->getSilk?->PremiumSilk ?? 0);
        if ($currentSilk < $totalCost) {
            throw new \InvalidArgumentException('Insufficient Silk amount. You need ' . $totalCost . ' Silk.');
        }

        TbUser::updateSilk($jid, 3, -$totalCost);
        $record->increment('Points', $qty * $pointsPerPurchase);

        return $currentSilk - $totalCost;
    }

    public static function addPoints(int $charID, int $points): bool
    {
        if ($points <= 0) {
            throw new \InvalidArgumentException('Invalid points amount');
        }

        $record = self::getOrCreateForChar($charID);

        return (bool) $record->increment('Points', $points);
    }
}