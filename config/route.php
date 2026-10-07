<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

use Webman\Route;
use app\controller\PetController;
use app\controller\ShopController;
use app\controller\SettingController;

// 桌宠 API：所有接口返回统一结构 {code, msg, data}，动作类接口成功时 data 携带全量状态
Route::get('/api/state', [PetController::class, 'state']);
Route::post('/api/pet/create', [PetController::class, 'create']);
Route::post('/api/pet/reset', [PetController::class, 'reset']);
Route::post('/api/pet/feed', [PetController::class, 'feed']);
Route::post('/api/pet/play', [PetController::class, 'play']);
Route::post('/api/pet/rest', [PetController::class, 'rest']);
Route::post('/api/pet/work', [PetController::class, 'work']);
Route::post('/api/pet/work/collect', [PetController::class, 'workCollect']);
Route::post('/api/shop/buy', [ShopController::class, 'buy']);
Route::post('/api/settings', [SettingController::class, 'save']);






