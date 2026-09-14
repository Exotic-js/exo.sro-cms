<?php

namespace App\Http\Controllers\InGame;

use App\Http\Controllers\Controller;
use App\Models\SRO\Account\BattlePass;
use App\Models\SRO\Account\TbUser;
use App\Models\SRO\Shard\Char;
use App\Services\InventoryService;
use Illuminate\Http\Request;

class BattlePassController extends Controller
{
    public function index(Request $request)
    {
        return view('in-game.battle-pass.index');
    }

    public function tiers(Request $request)
    {
        try {
            $charname = Char::resolveCharname($request);
            if ($charname === null) {
                throw new \InvalidArgumentException('Unable to resolve character name.');
            }

            $char = Char::where('CharName16', $charname)->first();
            $charID = $char?->CharID;
            if ($charID === null) {
                throw new \InvalidArgumentException('Character not found: ' . $charname);
            }

            $record = BattlePass::getOrCreateForChar($charID);
            $tiers = BattlePass::getAllTiers();
            $progress = BattlePass::getProgress($record->Points, $tiers);

            foreach ($tiers as &$tier) {
                $tier['freeClaimed'] = $record->hasClaimedItem($tier['freeItem']['itemID']);
                $tier['vipClaimed'] = $record->hasClaimedItem($tier['vipItem']['itemID']);
                $tier['canClaimFree'] = ($progress['currentLevel'] >= $tier['id']) && !$tier['freeClaimed'];
                $tier['canClaimVIP'] = ($progress['currentLevel'] >= $tier['id']) && $record->IsPremium && !$tier['vipClaimed'];
                $tier['vipLocked'] = !$record->IsPremium;

                $tier['freeItem']['tooltipHtml'] = $this->renderItemTooltip($tier['freeItem'], (int) $char->JID);
                $tier['vipItem']['tooltipHtml'] = $this->renderItemTooltip($tier['vipItem'], (int) $char->JID);
            }
            unset($tier);

            return response()->json([
                'success' => true,
                'message' => 'Battle pass data loaded successfully',
                'character' => [
                    'charID' => $charID,
                    'charname' => $charname,
                    'jid' => $char->JID,
                ],
                'record' => [
                    'id' => $record->ID,
                    'charID' => $record->CharID,
                    'isPremium' => $record->IsPremium,
                    'points' => $record->Points,
                    'claimedItems' => $record->getClaimedItemsArray(),
                ],
                'stats' => [
                    'currentLevel' => $progress['currentLevel'],
                    'currentPoints' => $record->Points,
                    'maxLevel' => $progress['maxLevel'],
                    'progressPercent' => $progress['progressPercent'],
                    'isPremium' => $record->IsPremium,
                    'nextLevelPoints' => $progress['nextLevelPoints'],
                ],
                'tiers' => $tiers,
                'premiumEnabled' => config('ingame.battlepass.premium_enabled'),
                'premiumPrice' => (int) config('ingame.battlepass.premium_price'),
                'pointsPerPurchase' => (int) config('ingame.battlepass.points_per_purchase'),
                'silkPerPoint' => (int) config('ingame.battlepass.silk_per_point'),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function claim(Request $request)
    {
        try {
            $tierID = (int) $request->input('tier_id', 0);
            $claimType = $request->input('type', 'free');

            if ($tierID <= 0 || !in_array($claimType, ['free', 'vip'], true)) {
                throw new \InvalidArgumentException('Invalid request');
            }

            $tier = BattlePass::getTierById($tierID);
            if ($tier === null) {
                throw new \InvalidArgumentException('Invalid request');
            }

            $charID = $this->resolveCharID($request);

            $record = BattlePass::getForChar($charID);
            $currentLevel = 0;
            if ($record) {
                $currentLevel = BattlePass::getProgress($record->Points, BattlePass::getAllTiers())['currentLevel'];
            }

            BattlePass::claimReward($charID, $tierID, $claimType, $currentLevel);

            return response()->json([
                'success' => true,
                'message' => 'Item claimed successfully!',
                'tierID' => $tierID,
                'claimType' => $claimType,
                'itemName' => ($claimType === 'vip') ? $tier['vipItem']['name'] : $tier['freeItem']['name'],
                'quantity' => ($claimType === 'vip') ? $tier['vipItem']['quantity'] : $tier['freeItem']['quantity'],
                'deliveryMethod' => strtolower(config('global.server.guard')),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function purchasePremium(Request $request)
    {
        try {
            $charID = $this->resolveCharID($request);
            $remainingSilk = BattlePass::purchasePremium($charID);

            return response()->json([
                'success' => true,
                'message' => 'Premium Pass purchased successfully!',
                'premiumPrice' => (int) config('ingame.battlepass.premium_price'),
                'remainingSilk' => $remainingSilk,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function purchasePoints(Request $request)
    {
        try {
            $qty = (int) $request->input('qty', 1);
            $charID = $this->resolveCharID($request);
            $remainingSilk = BattlePass::purchasePoints($charID, $qty);

            return response()->json([
                'success' => true,
                'message' => 'Points purchased successfully!',
                'pointsPurchased' => $qty * (int) config('ingame.battlepass.points_per_purchase'),
                'silkSpent' => $qty * (int) config('ingame.battlepass.silk_per_point'),
                'remainingSilk' => $remainingSilk,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function addPoints(Request $request)
    {
        try {
            $points = (int) $request->input('points', 0);
            $charID = $this->resolveCharID($request);

            $added = BattlePass::addPoints($charID, $points);

            return response()->json([
                'success' => $added,
                'message' => $added ? 'Points added successfully!' : 'Failed to add points',
                'pointsAdded' => $points,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function getSilk(Request $request)
    {
        try {
            $charID = $this->resolveCharID($request);
            $jid = Char::find($charID)?->JID;

            if (!$jid) {
                return response()->json(['success' => false, 'message' => 'Unable to get JID for this character'], 422);
            }

            $silk = (int) (TbUser::find($jid)?->muUser?->getSilk?->PremiumSilk ?? 0);

            return response()->json([
                'success' => true,
                'message' => 'Silk amount retrieved',
                'silk' => $silk,
                'jid' => $jid,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    private function resolveCharID(Request $request): int
    {
        $charname = Char::resolveCharname($request);
        if ($charname === null) {
            throw new \InvalidArgumentException('Unable to resolve character name. Missing or invalid credentials.');
        }

        $char = Char::where('CharName16', $charname)->first();
        if (!$char) {
            throw new \InvalidArgumentException('Character not found: ' . $charname);
        }

        return $char->CharID;
    }

    private function renderItemTooltip(array $item, int $jid): ?string
    {
        $itemInfo = app(InventoryService::class)->getItemInfoByRefItem(
            (int) $item['itemID'],
            ['Data' => (int) $item['quantity']],
        );

        if (!$itemInfo) {
            return null;
        }

        try {
            return view('pages.ranking.character.partials.inventory.item-blues-whites', [
                'item' => $itemInfo->ItemInfo,
                'data' => (object) ['jid' => $jid],
            ])->render();
        } catch (\Throwable $e) {
            return null;
        }
    }
}