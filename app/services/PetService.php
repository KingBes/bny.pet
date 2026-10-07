<?php

namespace app\services;

use app\exception\BizException;
use app\model\Achievement;
use app\model\Bag;
use app\model\Pet;
use app\model\PetAchievement;
use app\model\Setting;
use app\model\ShopItem;
use support\think\Db;

/**
 * 桌宠核心业务：时间结算、养成动作、升级进化、成就
 */
class PetService
{
    /** 各数值每分钟衰减量（清醒时） */
    private const DECAY_HUNGER = 1 / 3;   // 每 3 分钟 -1
    private const DECAY_MOOD = 1 / 5;     // 每 5 分钟 -1
    private const DECAY_ENERGY = 1 / 4;   // 每 4 分钟 -1

    private const SLEEP_ENERGY_REGEN = 2;    // 睡眠每分钟回体力
    private const SLEEP_DECAY_FACTOR = 0.5;  // 睡眠中饱食/心情衰减减半

    /** 离线结算封顶：重新打开应用一次性结算，最多按 12 小时计 */
    private const OFFLINE_CAP_MINUTES = 12 * 60;

    private const MAX_LEVEL = 50;

    /** 进化阶段：等级上限 => 阶段名 */
    private const STAGES = [
        3 => '幼生期',
        9 => '成长期',
        19 => '成年期',
        PHP_INT_MAX => '完全体',
    ];

    /** 玩耍：消耗体力/饱食，换心情和经验 */
    private const PLAY_ENERGY_COST = 15;
    private const PLAY_HUNGER_COST = 5;
    private const PLAY_MIN_HUNGER = 10;
    private const PLAY_MOOD_GAIN = 20;
    private const PLAY_EXP_GAIN = 6;

    /** 打工岗位：key => [显示名, 时长(分钟), 耗体力, 报酬金币, 报酬经验] */
    public const JOBS = [
        'errand' => ['name' => '帮跑腿', 'minutes' => 10, 'energy' => 20, 'coins' => 30, 'exp' => 15],
        'cashier' => ['name' => '看小店', 'minutes' => 20, 'energy' => 35, 'coins' => 70, 'exp' => 35],
        'delivery' => ['name' => '送外卖', 'minutes' => 40, 'energy' => 50, 'coins' => 150, 'exp' => 80],
    ];

    /** 创建时的初始状态 */
    private const INIT = [
        'hunger' => 80, 'mood' => 80, 'energy' => 80, 'coins' => 50,
    ];

    /** 新手礼包：code => 数量 */
    private const STARTER_PACK = ['carrot' => 3];

    /** 可写设置项白名单 */
    public const SETTING_KEYS = ['always_on_top', 'click_through', 'skin'];

    /** 设置项默认值 */
    private const SETTING_DEFAULTS = [
        'always_on_top' => '0',
        'click_through' => '0',
        'skin' => '少年',
    ];

    /** 皮肤素材：皮肤目录下必须存在的精灵图文件名 */
    private const SKIN_SHEET = '行走.png';

    // ---------- 查询 ----------

    public static function getPet(): ?Pet
    {
        /** @var Pet|null $pet */
        $pet = Pet::order('id', 'asc')->find();
        return $pet;
    }

    public static function hasPet(): bool
    {
        return self::getPet() !== null;
    }

    private static function requirePet(): Pet
    {
        $pet = self::getPet();
        if ($pet === null) {
            throw new BizException('还没有宠物，先创建一只吧');
        }
        return $pet;
    }

    // ---------- 创建 / 重置 ----------

    public static function create(string $name): Pet
    {
        $name = trim($name);
        if (!mb_check_encoding($name, 'UTF-8')) {
            throw new BizException('名字编码有误，请用 UTF-8 文本');
        }
        $len = mb_strlen($name);
        if ($len < 1 || $len > 12) {
            throw new BizException('名字要 1~12 个字哦');
        }
        if (self::hasPet()) {
            throw new BizException('已经有宠物了，不能重复创建');
        }
        $now = time();
        $pet = new Pet();
        $pet->name = $name;
        $pet->level = 1;
        $pet->exp = 0;
        $pet->hunger = self::INIT['hunger'];
        $pet->mood = self::INIT['mood'];
        $pet->energy = self::INIT['energy'];
        $pet->coins = self::INIT['coins'];
        $pet->stage = self::stageFor(1);
        $pet->created_at = $now;
        $pet->last_tick_at = $now;
        $pet->save();

        // 新手礼包
        $items = ShopItem::where('code', 'in', array_keys(self::STARTER_PACK))->select();
        foreach ($items as $item) {
            $qty = self::STARTER_PACK[$item->code] ?? 0;
            if ($qty > 0) {
                self::addToBag((int)$item->id, $qty);
            }
        }
        return $pet;
    }

    /** 重置：清空宠物、背包、成就记录（保留商店/成就种子与设置） */
    public static function reset(): void
    {
        if (!self::hasPet()) {
            throw new BizException('还没有宠物，无需重置');
        }
        Db::execute('DELETE FROM pet');
        Db::execute('DELETE FROM bag');
        Db::execute('DELETE FROM pet_achievements');
    }

    // ---------- 时间结算 ----------

    /**
     * 按真实时间结算衰减（关应用也生效），离线按 OFFLINE_CAP_MINUTES 封顶。
     * 不足一分钟不结算，last_tick_at 保留零头。
     */
    public static function tick(Pet $pet): void
    {
        $now = time();
        $last = (int)$pet->last_tick_at;
        if ($last <= 0) {
            $pet->last_tick_at = $now;
            $pet->save();
            return;
        }
        $rawMinutes = (int)(($now - $last) / 60);
        if ($rawMinutes < 1) {
            return;
        }
        $capped = $rawMinutes > self::OFFLINE_CAP_MINUTES;
        $minutes = min($rawMinutes, self::OFFLINE_CAP_MINUTES);

        $sleeping = (int)$pet->sleeping === 1;
        $factor = $sleeping ? self::SLEEP_DECAY_FACTOR : 1.0;

        $pet->hunger = self::clamp((int)$pet->hunger - (int)floor($minutes * self::DECAY_HUNGER * $factor));
        $pet->mood = self::clamp((int)$pet->mood - (int)floor($minutes * self::DECAY_MOOD * $factor));
        if ($sleeping) {
            $pet->energy = self::clamp((int)$pet->energy + $minutes * self::SLEEP_ENERGY_REGEN);
        } else {
            $pet->energy = self::clamp((int)$pet->energy - (int)floor($minutes * self::DECAY_ENERGY));
        }
        // 离线封顶时直接对齐到当前时间，多出的离线时长一笔勾销
        $pet->last_tick_at = $capped ? $now : $last + $minutes * 60;
        $pet->save();
    }

    public static function expMax(int $level): int
    {
        return 100 + ($level - 1) * 50;
    }

    public static function stageFor(int $level): string
    {
        foreach (self::STAGES as $max => $name) {
            if ($level <= $max) {
                return $name;
            }
        }
        return '完全体';
    }

    /** 加经验并处理升级/进化 */
    private static function addExp(Pet $pet, int $exp): void
    {
        if ($exp <= 0) {
            return;
        }
        $pet->exp = (int)$pet->exp + $exp;
        while ((int)$pet->level < self::MAX_LEVEL && $pet->exp >= self::expMax((int)$pet->level)) {
            $pet->exp = (int)$pet->exp - self::expMax((int)$pet->level);
            $pet->level = (int)$pet->level + 1;
        }
        $pet->stage = self::stageFor((int)$pet->level);
    }

    // ---------- 养成动作 ----------

    /** 喂食/使用背包物品：food 回饱食，toy 回心情（玩具耗体力） */
    public static function feed(int $bagId): string
    {
        $pet = self::requirePet();
        self::tick($pet);
        self::assertFree($pet);

        $bag = Bag::find($bagId);
        if ($bag === null || (int)$bag->qty < 1) {
            throw new BizException('背包里没有这个物品了');
        }
        /** @var ShopItem|null $item */
        $item = ShopItem::find((int)$bag->item_id);
        if ($item === null) {
            throw new BizException('物品信息不存在');
        }

        $isFood = $item->type === 'food';
        if ($isFood && (int)$pet->hunger >= 100) {
            throw new BizException('小bny 已经很饱了，吃不下啦');
        }
        if (!$isFood && (int)$pet->mood >= 100) {
            throw new BizException('小bny 已经玩得很开心了');
        }

        $pet->hunger = self::clamp((int)$pet->hunger + (int)$item->hunger);
        $pet->mood = self::clamp((int)$pet->mood + (int)$item->mood);
        $pet->energy = self::clamp((int)$pet->energy + (int)$item->energy);
        self::addExp($pet, (int)$item->exp);
        $pet->feed_count = (int)$pet->feed_count + 1;

        if ((int)$bag->qty <= 1) {
            $bag->delete();
        } else {
            $bag->qty = (int)$bag->qty - 1;
            $bag->save();
        }

        self::checkAchievements($pet);
        $pet->save();
        return "给小bny 用了「{$item->name}」";
    }

    /** 陪玩：耗体力换心情 */
    public static function play(): string
    {
        $pet = self::requirePet();
        self::tick($pet);
        self::assertFree($pet);

        if ((int)$pet->hunger < self::PLAY_MIN_HUNGER) {
            throw new BizException('小bny 饿得没力气了，先喂点吃的吧');
        }
        if ((int)$pet->energy <= self::PLAY_ENERGY_COST) {
            throw new BizException('体力不够了，让它先休息一下吧');
        }

        // 状态低落（饿肚子/心情见底）时收益减半
        $low = self::isLow($pet);
        $moodGain = $low ? intdiv(self::PLAY_MOOD_GAIN, 2) : self::PLAY_MOOD_GAIN;
        $expGain = $low ? intdiv(self::PLAY_EXP_GAIN, 2) : self::PLAY_EXP_GAIN;

        $pet->mood = self::clamp((int)$pet->mood + $moodGain);
        $pet->energy = self::clamp((int)$pet->energy - self::PLAY_ENERGY_COST);
        $pet->hunger = self::clamp((int)$pet->hunger - self::PLAY_HUNGER_COST);
        self::addExp($pet, $expGain);
        $pet->play_count = (int)$pet->play_count + 1;

        self::checkAchievements($pet);
        $pet->save();
        return '和小bny 玩得开心极了' . ($low ? '（状态低落，收益减半）' : '');
    }

    /** 睡觉/唤醒 */
    public static function rest(bool $on): string
    {
        $pet = self::requirePet();
        self::tick($pet);
        if ($on) {
            if ($pet->work_type !== '') {
                throw new BizException('小bny 正在打工，下班再睡吧');
            }
            if ((int)$pet->energy >= 100) {
                throw new BizException('小bny 精力充沛，还不想睡');
            }
            $pet->sleeping = 1;
            $pet->save();
            return '小bny 睡着了，体力正在恢复…';
        }
        $pet->sleeping = 0;
        $pet->save();
        return '小bny 醒过来啦';
    }

    /** 接打工单 */
    public static function work(string $jobKey): string
    {
        $pet = self::requirePet();
        self::tick($pet);
        self::assertFree($pet);

        $job = self::JOBS[$jobKey] ?? null;
        if ($job === null) {
            throw new BizException('没有这个打工岗位');
        }
        if ((int)$pet->energy <= $job['energy']) {
            throw new BizException('体力不够打这份工，先休息一下吧');
        }

        $now = time();
        $pet->energy = self::clamp((int)$pet->energy - $job['energy']);
        $pet->work_type = $jobKey;
        $pet->work_start_at = $now;
        $pet->work_end_at = $now + $job['minutes'] * 60;
        $pet->save();
        return "小bny 出发去「{$job['name']}」了";
    }

    /** 打工收工：结算报酬 */
    public static function workCollect(): string
    {
        $pet = self::requirePet();
        self::tick($pet);

        $jobKey = (string)$pet->work_type;
        if ($jobKey === '') {
            throw new BizException('小bny 没有在打工哦');
        }
        if (time() < (int)$pet->work_end_at) {
            throw new BizException('还没到下班时间，再等等吧');
        }
        $job = self::JOBS[$jobKey];

        $low = self::isLow($pet);
        $coins = $low ? intdiv($job['coins'], 2) : $job['coins'];
        $exp = $low ? intdiv($job['exp'], 2) : $job['exp'];

        $pet->coins = (int)$pet->coins + $coins;
        self::addExp($pet, $exp);
        $pet->work_count = (int)$pet->work_count + 1;
        $pet->work_type = '';
        $pet->work_start_at = 0;
        $pet->work_end_at = 0;

        self::checkAchievements($pet);
        $pet->save();
        return "打工结束，赚了 {$coins} 金币" . ($low ? '（状态低落，报酬减半）' : '');
    }

    // ---------- 商店 / 背包 ----------

    public static function buy(int $itemId, int $qty = 1): string
    {
        $qty = max(1, min(99, $qty));
        $pet = self::requirePet();
        self::tick($pet);

        /** @var ShopItem|null $item */
        $item = ShopItem::find($itemId);
        if ($item === null) {
            throw new BizException('商品不存在');
        }
        $cost = (int)$item->price * $qty;
        if ((int)$pet->coins < $cost) {
            throw new BizException('金币不够，去打打工吧');
        }

        $pet->coins = (int)$pet->coins - $cost;
        self::addToBag($itemId, $qty);

        self::checkAchievements($pet);
        $pet->save();
        return "买下「{$item->name}」×{$qty}，花了 {$cost} 金币";
    }

    private static function addToBag(int $itemId, int $qty): void
    {
        /** @var Bag|null $bag */
        $bag = Bag::where('item_id', $itemId)->find();
        if ($bag === null) {
            $bag = new Bag();
            $bag->item_id = $itemId;
            $bag->qty = $qty;
        } else {
            $bag->qty = min(99, (int)$bag->qty + $qty);
        }
        $bag->save();
    }

    // ---------- 成就 ----------

    /**
     * 检查并解锁达标成就（含奖励发放）。返回本次新解锁的成就列表。
     */
    public static function checkAchievements(Pet $pet): array
    {
        $unlocked = PetAchievement::column('code');
        $unlocked = is_array($unlocked) ? array_flip($unlocked) : [];
        $days = (int)((time() - (int)$pet->created_at) / 86400);
        $metrics = [
            'feed_count' => (int)$pet->feed_count,
            'play_count' => (int)$pet->play_count,
            'work_count' => (int)$pet->work_count,
            'level' => (int)$pet->level,
            'coins' => (int)$pet->coins,
            'days' => $days,
        ];

        $new = [];
        /** @var Achievement $ach */
        foreach (Achievement::order('sort', 'asc')->select() as $ach) {
            $code = (string)$ach->code;
            if (isset($unlocked[$code])) {
                continue;
            }
            $metric = $metrics[$ach->condition_type] ?? null;
            if ($metric === null || $metric < (int)$ach->condition_value) {
                continue;
            }
            PetAchievement::create([
                'code' => $code,
                'unlocked_at' => time(),
            ]);
            $pet->coins = (int)$pet->coins + (int)$ach->reward_coins;
            self::addExp($pet, (int)$ach->reward_exp);
            $new[] = [
                'code' => $code,
                'name' => $ach->name,
                'reward_coins' => (int)$ach->reward_coins,
                'reward_exp' => (int)$ach->reward_exp,
            ];
        }
        if ($new) {
            $pet->save();
        }
        return $new;
    }

    // ---------- 设置 ----------

    /**
     * 扫描 public/assets 下含行走精灵图的子目录作为皮肤列表（id 即目录名）。
     */
    public static function skins(): array
    {
        $skins = [];
        $assetsDir = public_path('assets');
        $names = @scandir($assetsDir);
        if ($names === false) {
            return $skins;
        }
        foreach ($names as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $path = $assetsDir . DIRECTORY_SEPARATOR . $name;
            if (!is_dir($path) || !is_file($path . DIRECTORY_SEPARATOR . self::SKIN_SHEET)) {
                continue;
            }
            $skins[] = ['id' => $name, 'name' => $name];
        }
        return $skins;
    }

    public static function getSettings(): array
    {
        $settings = self::SETTING_DEFAULTS;
        foreach (Setting::select() as $row) {
            if (array_key_exists($row->key, $settings)) {
                $settings[$row->key] = (string)$row->value;
            }
        }
        return $settings;
    }

    public static function setSetting(string $key, string $value): void
    {
        if (!in_array($key, self::SETTING_KEYS, true)) {
            throw new BizException('不支持的设置项');
        }
        if ($key === 'skin') {
            // 皮肤值必须是实际存在的皮肤目录
            $skinIds = array_column(self::skins(), 'id');
            if (!in_array($value, $skinIds, true)) {
                throw new BizException('皮肤不存在');
            }
        } else {
            $value = $value === '1' || $value === 'true' ? '1' : '0';
        }
        Db::execute(
            'INSERT INTO settings (`key`, `value`) VALUES (?, ?)'
                . ' ON CONFLICT(`key`) DO UPDATE SET value = excluded.value',
            [$key, $value]
        );
    }

    // ---------- 全量状态 ----------

    /**
     * 前端唯一状态来源：先结算再取数，包含宠物/背包/商店/成就/设置。
     */
    public static function state(): array
    {
        $pet = self::getPet();
        if ($pet === null) {
            // 未创建宠物也要带皮肤信息：创建遮罩需要按当前皮肤渲染
            return ['has_pet' => false] + self::skinState();
        }
        self::tick($pet);
        $pet = self::getPet() ?? $pet;

        // 背包 + 商品信息
        $shopItems = [];
        foreach (ShopItem::order('id', 'asc')->select() as $item) {
            $shopItems[(int)$item->id] = $item->toArray();
        }
        $bag = [];
        foreach (Bag::order('id', 'asc')->select() as $row) {
            $item = $shopItems[(int)$row->item_id] ?? null;
            if ($item === null) {
                continue;
            }
            $bag[] = [
                'bag_id' => (int)$row->id,
                'qty' => (int)$row->qty,
                'id' => $item['id'],
                'code' => $item['code'],
                'name' => $item['name'],
                'type' => $item['type'],
                'price' => (int)$item['price'],
                'hunger' => (int)$item['hunger'],
                'mood' => (int)$item['mood'],
                'energy' => (int)$item['energy'],
                'exp' => (int)$item['exp'],
                'intro' => $item['intro'],
            ];
        }

        // 成就（含解锁状态）
        $unlockedAt = [];
        foreach (PetAchievement::select() as $row) {
            $unlockedAt[$row->code] = (int)$row->unlocked_at;
        }
        $achievements = [];
        foreach (Achievement::order('sort', 'asc')->select() as $ach) {
            $achievements[] = [
                'code' => $ach->code,
                'name' => $ach->name,
                'intro' => $ach->intro,
                'condition_type' => $ach->condition_type,
                'condition_value' => (int)$ach->condition_value,
                'reward_coins' => (int)$ach->reward_coins,
                'reward_exp' => (int)$ach->reward_exp,
                'unlocked_at' => $unlockedAt[$ach->code] ?? 0,
            ];
        }

        $work = null;
        if ((string)$pet->work_type !== '') {
            $job = self::JOBS[$pet->work_type] ?? null;
            $end = (int)$pet->work_end_at;
            $work = [
                'type' => $pet->work_type,
                'name' => $job['name'] ?? $pet->work_type,
                'end_at' => $end,
                'done' => time() >= $end,
            ];
        }

        return [
            'has_pet' => true,
            'pet' => [
                'name' => $pet->name,
                'level' => (int)$pet->level,
                'exp' => (int)$pet->exp,
                'exp_max' => self::expMax((int)$pet->level),
                'hunger' => (int)$pet->hunger,
                'mood' => (int)$pet->mood,
                'energy' => (int)$pet->energy,
                'coins' => (int)$pet->coins,
                'stage' => $pet->stage,
                'sleeping' => (int)$pet->sleeping === 1,
                'low' => self::isLow($pet),
                'feed_count' => (int)$pet->feed_count,
                'play_count' => (int)$pet->play_count,
                'work_count' => (int)$pet->work_count,
                'created_at' => (int)$pet->created_at,
            ],
            'work' => $work,
            'bag' => $bag,
            'shop' => array_values($shopItems),
            'achievements' => $achievements,
            'jobs' => self::JOBS,
            'now' => time(),
        ] + self::skinState();
    }

    /** 皮肤列表 + 当前皮肤，供前端渲染精灵图 */
    private static function skinState(): array
    {
        return [
            'skins' => self::skins(),
            'settings' => self::getSettings(),
        ];
    }

    // ---------- 内部 ----------

    private static function assertFree(Pet $pet): void
    {
        if ((string)$pet->work_type !== '') {
            throw new BizException('小bny 正在打工，等它下班吧');
        }
        if ((int)$pet->sleeping === 1) {
            throw new BizException('小bny 睡着了，先叫醒它吧');
        }
    }

    /** 状态低落：饱食或心情见底（打工/玩耍收益减半） */
    private static function isLow(Pet $pet): bool
    {
        return (int)$pet->hunger <= 0 || (int)$pet->mood <= 0;
    }

    private static function clamp(int $value): int
    {
        return max(0, min(100, $value));
    }
}
