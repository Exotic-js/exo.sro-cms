<?php

namespace App\Services;

use App\Models\SRO\Account\ItemNameDesc;
use App\Models\SRO\Shard\Chest;
use App\Models\SRO\Shard\InvCOS;
use App\Models\SRO\Shard\Inventory;
use App\Models\SRO\Shard\InventoryForAvatar;
use App\Models\SRO\Shard\RefObjCommon;
use App\Models\SRO\Shard\TradeEquipInventory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryService
{
    protected array $itemNames = [];

    /**
     * Get inventory items for a character within a slot range.
     */
    public function getInventorySet(int $CharID, $max, $min, $not): object
    {
        $inventory = Inventory::getInventory($CharID, $max, $min, $not);
        return $this->convertItemList($inventory);
    }

    /**
     * Get avatar inventory for a character.
     */
    public function getInventoryAvatar(int $CharID): object
    {
        $inventory = InventoryForAvatar::getInventoryForAvatar($CharID);
        return $this->convertItemList($inventory);
    }

    /**
     * Get job inventory for a character.
     */
    public function getInventoryJob(int $CharID): object
    {
        $inventory = TradeEquipInventory::getInventoryForJob($CharID);
        return $this->convertItemList($inventory);
    }

    /**
     * Get storage items for a character within a slot range.
     */
    public function getStorageItems(int $UserJID, $max, $min): object
    {
        $inventory = Chest::getChest($UserJID, $max, $min);
        return $this->convertItemList($inventory);
    }

    /**
     * Get Pet items for a character within a slot range.
     */
    public function getPetItems(int $CharID, $PetID, $max, $min): object
    {
        $inventory = InvCOS::getPetItems($CharID, $PetID, $max, $min);
        return $this->convertItemList($inventory);
    }

    /**
     * Build a tooltip-ready item object from a _RefObjCommon ID.
     */
    public function getItemInfoByRefItem(int $refItemID, array $instance = []): ?object
    {
        $refItem = RefObjCommon::where('ID', $refItemID)->first();
        if (!$refItem) {
            return null;
        }

        $item = $refItem->newFromBuilder($refItem->getAttributes());

        $refItemData = DB::connection('shard')
            ->table('_RefObjItem')
            ->where('ID', $refItem->Link)
            ->first();

        $item->MaxStack = $refItemData->MaxStack ?? 0;
        $item->ItemClass = $refItemData->ItemClass ?? 0;

        $item->ID64 = $instance['ID64'] ?? 0;
        $item->RefItemID = $refItemID;
        $item->Serial64 = $instance['Serial64'] ?? 0;
        $item->Slot = $instance['Slot'] ?? 0;
        $item->Data = $instance['Data'] ?? 0;
        $item->OptLevel = $instance['OptLevel'] ?? 0;
        $item->nOptValue = $instance['nOptValue'] ?? 0;
        $item->Variance = $instance['Variance'] ?? 0;

        for ($i = 1; $i <= ($refItem->MagParamNum ?? 12); $i++) {
            $item->{"MagParam{$i}"} = $instance["MagParam{$i}"] ?? 0;
        }

        $this->loadItemName($item);

        return $this->processItem($item);
    }

    /**
     * Load the localized display name for a single item.
     */
    private function loadItemName(object $item): void
    {
        $this->itemNames = ItemNameDesc::getItemNames([$item->NameStrID128] ?? []);
    }

    /**
     * Convert raw inventory data into object format.
     */
    public function convertItemList(?object $inventory): object
    {
        $convertedItems = new \stdClass();

        if (!$inventory) {
            return $convertedItems;
        }

        $this->loadItemNames($inventory);

        $index = 0;
        foreach ($inventory as $item) {
            try {
                $convertedItems->{$index} = $this->processItem($item);
                $index++;
            } catch (\Throwable $e) {
                Log::error('Error processing inventory item', [
                    'item' => $item,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $convertedItems;
    }

    /**
     * Process a single inventory item into object.
     */
    private function processItem(object $item): object
    {
        $obj = new \stdClass();
        $obj->Slot = $item->Slot ?? $item->ID64;
        $obj->Amount = $item->MaxStack > 1 ? $item->Data : 0;
        $obj->ImgPath = $this->getItemIcon($item->AssocFileIcon128);
        $obj->ItemInfo = $this->getItemInfo($item);

        return $obj;
    }

    /**
     * Get detailed item information as object.
     */
    private function getItemInfo(object $item): object
    {
        $info = new \stdClass();

        $info->ID64 = $item->ID64;
        $info->RefItemID = $item->RefItemID;
        $info->Serial64 = $item->Serial64;
        $info->CodeName128 = $item->CodeName128;
        $info->ReqLevelType1 = $item->ReqLevelType1;
        $info->ReqLevelType2 = $item->ReqLevelType2;
        $info->ReqLevelType3 = $item->ReqLevelType3;
        $info->ReqLevelType4 = $item->ReqLevelType4;
        $info->ReqLevel1 = $item->ReqLevel1;
        $info->ReqLevel2 = $item->ReqLevel2;
        $info->ReqLevel3 = $item->ReqLevel3;
        $info->ReqLevel4 = $item->ReqLevel4;
        $info->ItemClass = $item->ItemClass;
        $info->MagParamNum = $item->MagParamNum;
        $info->MagParam1 = $item->MagParam1;
        $info->MaxMagicOptCount = $item->MaxMagicOptCount;
        $info->ChildItemCount = $item->ChildItemCount;
        $info->Slot = $item->Slot;
        $info->Data = $item->Data;
        $info->TypeID1 = $item->TypeID1;
        $info->TypeID2 = $item->TypeID2;
        $info->TypeID3 = $item->TypeID3;
        $info->TypeID4 = $item->TypeID4;

        $info->ItemName = $this->itemNames[$item->NameStrID128] ?? $item->NameStrID128 ?? 'Unknown';
        $info->ItemDesc = config('itemdesc')[$item->DescStrID128] ?? null;
        $info->Amount = $item->MaxStack > 1 ? $item->Data : 0;
        $info->OptLevel = $item->OptLevel ?? 0;
        $info->nOptValue = $item->nOptValue ?? 0;
        $info->Country = $item->Country == 0 ? 'Chinese' : 'European';
        $info->Gender = $item->ReqGender == 0 ? 'Female' : 'Male';
        $info->Degree = (int)ceil($item->ItemClass / 3);
        $info->JobDegree = config('item.job_degree')[$item->ItemClass] ?? null;
        $info->Type = config('item.types')[$item->TypeID1][$item->TypeID2][$item->TypeID3][$item->TypeID4] ?? null;
        $info->Detail = config('item.detail')[$item->Slot] ?? null;
        $info->SoxType = $this->getSoxType($item);
        $info->SoxName = $this->getSoxName($item);
        $info->DevilMaxHP = $this->getDevilMaxHP($item);
        $info->WhiteInfo = $this->getWhiteInfo($item);
        $info->BlueInfo = $this->getBlueInfo($item);
        $info->TimeEnd = $this->getTimeEnd($item);

        return $info;
    }

    private function loadItemNames(object $inventory): void
    {
        $ids = collect($inventory)->pluck('NameStrID128')->filter()->unique()->values()->all();
        if (empty($ids)) {
            return;
        }

        $this->itemNames = ItemNameDesc::getItemNames($ids);
    }

    private function getItemIcon(?string $assocFile): string
    {
        $iconPath = str_replace('\\', '/', trim($assocFile));
        $iconPath = preg_replace('/\.ddj$/i', '', $iconPath);
        $iconPath = strtolower($iconPath . '.png');

        if (!file_exists(public_path('images/sro/'.$iconPath))) {
            return 'icon_default.png';
        }

        return $iconPath;
    }

    private function getSoxType(object $item): ?string
    {
        $config = config('item.sox_type');
        foreach ($config as $itemClass => $CodeName) {
            if ($item->ItemClass > $itemClass) {
                foreach ($CodeName as $key => $value) {
                    if (str_contains($item->CodeName128, $key)) {
                        return $value;
                    }
                }
            }
        }

        return 'Normal';
    }

    private function getSoxName(object $item): ?string
    {
        $config = config('item.sox_name');
        foreach ($config as $key => $values) {
            if (str_contains($item->CodeName128, $key)) {
                return $values[$item->Slot] ?? '';
            }
        }

        return '';
    }

    private function getDevilMaxHP(object $item): ?int
    {
        $config = config('item.devil_type');
        uksort($config, function ($a, $b) {
            return strlen($b) - strlen($a);
        });

        foreach ($config as $key => $value) {
            if (str_contains($item->CodeName128, $key)) {
                return $value;
            }
        }

        return 0;
    }

    public function getTimeEnd(object $item): string
    {
        if ($item->Data === 0) {
            return '28Day';
        }

        if (time() > $item->Data) {
            return 'Awaken period is over';
        }

        $difference = $item->Data - time();
        $days = intdiv($difference, 3600 * 24);
        $difference %= 3600 * 24;
        $hours = intdiv($difference, 3600);
        $difference %= 3600;
        $minutes = intdiv($difference, 60);
        $seconds = $difference % 60;

        return sprintf('%dDay %02dHour %02dMinute', $days, $hours, $minutes);
    }

    private function getBlueInfo($item): object
    {
        $config = config('magopt');
        $blueInfo = new \stdClass();

        $exclude = [
            'MATTR_PET_RESIST_FEAR',
            'MATTR_PET_RESIST_SLEEP',
        ];

        $bits = [512, 64, 8, 1];
        $param1 = (int)(($item['MagParam1'] ?? 0) > 4611686018427387904 ? $item['MagParam1'] - 4611686018427387904 : ($item['MagParam1'] ?? 0));
        $index = 0;

        if (config('global.server.version') !== 'vSRO') {
            foreach ($bits as $bit) {
                $count = intdiv($param1, $bit);
                if ($count > 6) {
                    $param1 -= $count * $bit;
                    continue;
                }

                if ($count > 0) {
                    $param1 -= $count * $bit;
                    foreach ($config as $id => $opt) {
                        if (
                            ($bit === 512 && $opt['name'] === 'MATTR_ASTRAL') ||
                            ($bit === 64 && $opt['name'] === 'MATTR_LUCK') ||
                            ($bit === 8 && $opt['name'] === 'MATTR_SOLID') ||
                            ($bit === 1 && $opt['name'] === 'MATTR_ATHANASIA')
                        ) {
                            $obj = new \stdClass();
                            $obj->id = $id;
                            $obj->code = $opt['name'];
                            $obj->name = str_replace('%desc%', $count, $opt['desc']);
                            $obj->value = $count;
                            $obj->mLevel = $opt['mLevel'];
                            $obj->mValue = 0;
                            $obj->sortkey = $opt['sortkey'];

                            $blueInfo->{$index} = $obj;
                            $index++;
                            break;
                        }
                    }
                }
            }
        }

        for ($i = 1; $i <= ($item['MagParamNum'] ?? 12); $i++) {
            $prop = "MagParam{$i}";
            if (!isset($item[$prop]) || $item[$prop] <= 1) {
                continue;
            }

            $obj = new \stdClass();

            if ($item[$prop] === 65) {
                $obj->id = 0;
                $obj->code = 'MATTR_DUR';
                $obj->name = 'Repair invalid (Maximum durability 400% increase)';
                $obj->value = 400;
                $obj->mLevel = 0;
                $obj->mValue = 0;
                $obj->sortkey = 0;

                $blueInfo->{$index} = $obj;
                $index++;
                continue;
            }

            $hexParam = str_pad(dechex($item[$prop]), 11, '0', STR_PAD_LEFT);
            $id = hexdec(substr($hexParam, 3));
            $value = hexdec(substr($hexParam, 0, 3));

            if (!isset($config[$id])) {
                continue;
            }

            if (in_array($config[$id]['name'], $exclude, true)) {
                continue;
            }

            if ($config[$id]['name'] === 'MATTR_REPAIR') {
                $value--;
            }

            $obj->id = $id;
            $obj->code = $config[$id]['name'];
            $obj->name = str_replace('%desc%', $value, $config[$id]['desc']);
            $obj->value = $value;
            $obj->mLevel = $config[$id]['mLevel'];
            $obj->mValue = $config[$id]['mValue'] ?? 0;
            $obj->sortkey = $config[$id]['sortkey'];

            $blueInfo->{$index} = $obj;
            $index++;
        }

        $props = get_object_vars($blueInfo);
        uasort($props, fn($a, $b) => $a->sortkey <=> $b->sortkey);
        $blueInfo = (object) $props;

        return collect($blueInfo);
    }

    private function getWhiteInfo(object $item): object
    {
        $OptLevel = $item->OptLevel ?? 0;
        $Variance = $item->Variance ?? 0;

        $percentage = function ($variance, $index) {
            return (int) floor(((int) ($variance / pow(32, $index)) & 0x1F) * 3.23);
        };

        $whiteInfo = new \stdClass();

        $whiteInfo->PAtack = ($item->PAttackMin_L > 0 && $item->PAttackMax_L > 0)
            ? sprintf(
                'Phy. atk. pwr. %d ~ %d (+%d%%)',
                round(($item->PAttackMin_L + $item->PAttackInc * $OptLevel) + (($item->PAttackMin_U - $item->PAttackMin_L) * $percentage($Variance, 4) / 100)),
                round(($item->PAttackMax_L + $item->PAttackInc * $OptLevel) + (($item->PAttackMax_U - $item->PAttackMax_L) * $percentage($Variance, 4) / 100)),
                $percentage($Variance, 4)
            )
            : '';

        $whiteInfo->MAtack = ($item->MAttackMin_L > 0 && $item->MAttackMax_L > 0)
            ? sprintf(
                'Mag. atk. pwr. %d ~ %d (+%d%%)',
                (int)(($item->MAttackMin_L + $item->MAttackInc * $OptLevel) + (($item->MAttackMin_U - $item->MAttackMin_L) * $percentage($Variance, 5) / 100)),
                (int)(($item->MAttackMax_L + $item->MAttackInc * $OptLevel) + (($item->MAttackMax_U - $item->MAttackMax_L) * $percentage($Variance, 5) / 100)),
                $percentage($Variance, 5)
            )
            : '';

        $whiteInfo->PDefance = ($item->PD_L > 0)
            ? sprintf(
                'Phy. def. pwr. %.1f (+%d%%)',
                round(($item->PD_L + $item->PDInc * $OptLevel) + (($item->PD_U - $item->PD_L) * $percentage($Variance, 3) / 100), 1),
                $percentage($Variance, 3)
            )
            : '';

        $whiteInfo->MDefance = ($item->MD_L > 0)
            ? sprintf(
                'Mag. def. pwr. %.1f (+%d%%)',
                round(($item->MD_L + $item->MDInc * $OptLevel) + (($item->MD_U - $item->MD_L) * $percentage($Variance, 4) / 100), 1),
                $percentage($Variance, 4)
            )
            : '';

        $whiteInfo->Durability = ($item->Dur_U > 0)
            ? sprintf(
                'Durability %d/%d (+%d%%)',
                $item->Data,
                $item->Data,
                $percentage($Variance, 0)
            )
            : '';

        $whiteInfo->BlockRate = ($item->BR_L > 0)
            ? sprintf(
                'Block Rate %d (+%d%%)',
                (int)(($item->BR_L) + (($item->BR_U - $item->BR_L) * $percentage($Variance, 3) / 100)),
                $percentage($Variance, 3)
            )
            : '';

        $whiteInfo->AtackDist = ($item->Range > 0)
            ? sprintf('Attack distance %.1f m', $item->Range / 10)
            : '';

        $whiteInfo->AtackRate = ($item->HR_L > 0)
            ? sprintf(
                'Attack rate %d (+%d%%)',
                (int)(($item->HR_L + $item->HRInc * $OptLevel) + (($item->HR_U - $item->HR_L) * $percentage($Variance, 3) / 100)),
                $percentage($Variance, 3)
            )
            : '';

        $whiteInfo->Critical = ($item->CHR_L > 0)
            ? sprintf(
                'Critical %d (+%d%%)',
                (int)(($item->CHR_L) + (($item->CHR_U - $item->CHR_L) * $percentage($Variance, 6) / 100)),
                $percentage($Variance, 6)
            )
            : '';

        $whiteInfo->ParryRate = ($item->ER_L > 0)
            ? sprintf(
                'Parry rate %d (+%d%%)',
                (int)(($item->ER_L + $item->ERInc * $OptLevel) + (($item->ER_U - $item->ER_L) * $percentage($Variance, 5) / 100)),
                $percentage($Variance, 5)
            )
            : '';

        $whiteInfo->PReinforceWep = ($item->PAStrMin_L > 0 && $item->PAStrMax_L > 0)
            ? sprintf(
                'Phy. reinforce %.1f ~ %.1f (+%d%%)',
                (float)(($item->PAStrMin_L) + (($item->PAStrMin_U - $item->PAStrMin_L) * $percentage($Variance, 1) / 100)) / 10,
                (float)(($item->PAStrMax_L) + (($item->PAStrMax_U - $item->PAStrMax_L) * $percentage($Variance, 1) / 100)) / 10,
                $percentage($Variance, 1)
            )
            : '';

        $whiteInfo->MReinforceWep = ($item->MAInt_Min_L > 0 && $item->MAInt_Max_L > 0)
            ? sprintf(
                'Mag. reinforce %.1f ~ %.1f (+%d%%)',
                (float)(($item->MAInt_Min_L) + (($item->MAInt_Min_U - $item->MAInt_Min_L) * $percentage($Variance, 2) / 100)) / 10,
                (float)(($item->MAInt_Max_L) + (($item->MAInt_Max_U - $item->MAInt_Max_L) * $percentage($Variance, 2) / 100)) / 10,
                $percentage($Variance, 2)
            )
            : '';

        $whiteInfo->PReinforceSet = ($item->PDStr_L > 0)
            ? sprintf(
                'Phy. reinforce %.1f (+%d%%)',
                (float)(($item->PDStr_L) + (($item->PDStr_U - $item->PDStr_L) * $percentage($Variance, 1) / 100)) / 10,
                $percentage($Variance, 1)
            )
            : '';

        $whiteInfo->MReinforceSet = ($item->MDInt_L > 0)
            ? sprintf(
                'Mag. reinforce %.1f (+%d%%)',
                (float)(($item->MDInt_L) + (($item->MDInt_U - $item->MDInt_L) * $percentage($Variance, 2) / 100)) / 10,
                $percentage($Variance, 2)
            )
            : '';

        $whiteInfo->Pabsorp = ($item->PAR_L > 0)
            ? sprintf(
                'Phy. absorption %.1f (+%d%%)',
                round(($item->PAR_L + $item->PARInc * $OptLevel) + (($item->PAR_U - $item->PAR_L) * $percentage($Variance, 0) / 100), 1),
                $percentage($Variance, 0)
            )
            : '';

        $whiteInfo->Mabsorp = ($item->MAR_L > 0)
            ? sprintf(
                'Mag. absorption %.1f (+%d%%)',
                round(($item->MAR_L + $item->MARInc * $OptLevel) + (($item->MAR_U - $item->MAR_L) * $percentage($Variance, 1) / 100), 1),
                $percentage($Variance, 1)
            )
            : '';

        return $whiteInfo;
    }
}
