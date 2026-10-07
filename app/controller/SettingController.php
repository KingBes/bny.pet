<?php

namespace app\controller;

use app\exception\BizException;
use app\services\DbInit;
use app\services\PetService;
use support\Request;
use support\Response;
use Throwable;

class SettingController
{
    public function save(Request $request): Response
    {
        try {
            DbInit::ensure();
            $key = (string)$request->post('key', '');
            $value = (string)$request->post('value', '0');
            PetService::setSetting($key, $value);
            // 与其他动作接口一致：返回全量状态，前端 applyState 依赖 has_pet 字段
            return json_ok(PetService::state());
        } catch (BizException $e) {
            return json_err($e->getMessage());
        } catch (Throwable $e) {
            return json_err($e->getMessage(), 500);
        }
    }
}
