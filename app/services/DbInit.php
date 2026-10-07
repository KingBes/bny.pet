<?php

namespace app\services;

use support\think\Db;
use app\model\Pet;

/**
 * sql.db 建表与种子数据（幂等，可重复执行）
 */
class DbInit
{
    /** 是否已确认过表结构，避免每请求重复探测 */
    private static bool $checked = false;

    private const TABLES = [
        "CREATE TABLE IF NOT EXISTS pet (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL DEFAULT '',
            level INTEGER NOT NULL DEFAULT 1,
            exp INTEGER NOT NULL DEFAULT 0,
            hunger INTEGER NOT NULL DEFAULT 80,
            mood INTEGER NOT NULL DEFAULT 80,
            energy INTEGER NOT NULL DEFAULT 80,
            coins INTEGER NOT NULL DEFAULT 50,
            stage TEXT NOT NULL DEFAULT '幼生期',
            sleeping INTEGER NOT NULL DEFAULT 0,
            work_type TEXT NOT NULL DEFAULT '',
            work_start_at INTEGER NOT NULL DEFAULT 0,
            work_end_at INTEGER NOT NULL DEFAULT 0,
            feed_count INTEGER NOT NULL DEFAULT 0,
            play_count INTEGER NOT NULL DEFAULT 0,
            work_count INTEGER NOT NULL DEFAULT 0,
            created_at INTEGER NOT NULL DEFAULT 0,
            last_tick_at INTEGER NOT NULL DEFAULT 0
        )",
        "CREATE TABLE IF NOT EXISTS shop_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL,
            type TEXT NOT NULL DEFAULT 'food',
            price INTEGER NOT NULL DEFAULT 0,
            hunger INTEGER NOT NULL DEFAULT 0,
            mood INTEGER NOT NULL DEFAULT 0,
            energy INTEGER NOT NULL DEFAULT 0,
            exp INTEGER NOT NULL DEFAULT 0,
            intro TEXT NOT NULL DEFAULT ''
        )",
        "CREATE TABLE IF NOT EXISTS bag (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            item_id INTEGER NOT NULL,
            qty INTEGER NOT NULL DEFAULT 0
        )",
        "CREATE TABLE IF NOT EXISTS achievements (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL,
            intro TEXT NOT NULL DEFAULT '',
            condition_type TEXT NOT NULL,
            condition_value INTEGER NOT NULL DEFAULT 1,
            reward_coins INTEGER NOT NULL DEFAULT 0,
            reward_exp INTEGER NOT NULL DEFAULT 0,
            sort INTEGER NOT NULL DEFAULT 0
        )",
        "CREATE TABLE IF NOT EXISTS pet_achievements (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL,
            unlocked_at INTEGER NOT NULL DEFAULT 0
        )",
        "CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL DEFAULT ''
        )",
    ];

    private const SHOP_SEED = [
        ['carrot', '胡萝卜', 'food', 5, 20, 0, 0, 2, '小bny 最爱，恢复少量饱食'],
        ['apple', '苹果', 'food', 8, 15, 10, 0, 3, '甜脆可口，顺带哄哄心情'],
        ['pellet', '兔粮', 'food', 12, 40, 0, 0, 4, '营养均衡的主食'],
        ['cake', '精心蛋糕', 'food', 22, 30, 20, 0, 6, '难得的甜点，饱食心情双补'],
        ['yarn', '毛线球', 'toy', 18, 0, 25, -8, 8, '滚来滚去玩不停，消耗一点体力'],
        ['ball', '小皮球', 'toy', 35, 0, 45, -15, 12, '踢出去叼回来，玩得最尽兴'],
    ];

    private const ACHIEVEMENT_SEED = [
        ['first_feed', '初次投喂', '第一次给小bny喂食', 'feed_count', 1, 10, 0, 1],
        ['feed_30', '喂食达人', '累计喂食 30 次', 'feed_count', 30, 30, 20, 2],
        ['first_play', '玩耍初体验', '第一次陪小bny玩耍', 'play_count', 1, 10, 0, 3],
        ['play_50', '玩耍狂魔', '累计玩耍 50 次', 'play_count', 50, 50, 30, 4],
        ['first_work', '打工第一课', '第一次完成打工', 'work_count', 1, 15, 0, 5],
        ['work_20', '打工标兵', '累计完成 20 次打工', 'work_count', 20, 60, 40, 6],
        ['level_5', '迈入成长期', '等级达到 Lv.5', 'level', 5, 40, 30, 7],
        ['level_10', '长大成人', '等级达到 Lv.10', 'level', 10, 80, 60, 8],
        ['level_20', '完全体', '等级达到 Lv.20', 'level', 20, 200, 150, 9],
        ['rich_500', '小有积蓄', '持有金币达到 500', 'coins', 500, 50, 0, 10],
        ['days_7', '七日陪伴', '和小bny相伴 7 天', 'days', 7, 100, 80, 11],
    ];

    public static function run(): void
    {
        foreach (self::TABLES as $ddl) {
            Db::execute($ddl);
        }
        // 种子只补缺：按 code 逐条 insert or ignore，便于后续加新商品/成就
        foreach (self::SHOP_SEED as $item) {
            Db::execute(
                'INSERT INTO shop_items (code, name, type, price, hunger, mood, energy, exp, intro)'
                    . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    . ' ON CONFLICT(code) DO NOTHING',
                $item
            );
        }
        foreach (self::ACHIEVEMENT_SEED as $ach) {
            Db::execute(
                'INSERT INTO achievements (code, name, intro, condition_type, condition_value, reward_coins, reward_exp, sort)'
                    . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                    . ' ON CONFLICT(code) DO NOTHING',
                $ach
            );
        }
        self::$checked = true;
    }

    /**
     * 轻量探测：表没建好时自动补建，正常路径只有一次 count 查询开销
     */
    public static function ensure(): void
    {
        if (self::$checked) {
            return;
        }
        try {
            Pet::count();
            self::$checked = true;
        } catch (\Throwable) {
            self::run();
        }
    }
}
