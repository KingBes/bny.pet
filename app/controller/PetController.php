<?php

namespace app\controller;

use app\exception\BizException;
use app\services\DbInit;
use app\services\PetService;
use support\Request;
use support\Response;
use Throwable;

class PetController
{
    /** 全量状态（前端唯一数据入口） */
    public function state(): Response
    {
        try {
            DbInit::ensure();
            return json_ok(PetService::state());
        } catch (Throwable $e) {
            return json_err($e->getMessage(), 500);
        }
    }

    public function create(Request $request): Response
    {
        try {
            DbInit::ensure();
            PetService::create((string)$request->post('name', ''));
            return json_ok(PetService::state(), '与' . $request->post('name', '') . ' 相遇啦');
        } catch (BizException $e) {
            return json_err($e->getMessage());
        } catch (Throwable $e) {
            return json_err($e->getMessage(), 500);
        }
    }

    public function reset(): Response
    {
        try {
            DbInit::ensure();
            PetService::reset();
            return json_ok(['has_pet' => false], '存档已重置，迎接新的宠物');
        } catch (BizException $e) {
            return json_err($e->getMessage());
        } catch (Throwable $e) {
            return json_err($e->getMessage(), 500);
        }
    }

    public function feed(Request $request): Response
    {
        try {
            DbInit::ensure();
            $msg = PetService::feed((int)$request->post('bag_id', 0));
            return json_ok(PetService::state(), $msg);
        } catch (BizException $e) {
            return json_err($e->getMessage());
        } catch (Throwable $e) {
            return json_err($e->getMessage(), 500);
        }
    }

    public function play(): Response
    {
        try {
            DbInit::ensure();
            $msg = PetService::play();
            return json_ok(PetService::state(), $msg);
        } catch (BizException $e) {
            return json_err($e->getMessage());
        } catch (Throwable $e) {
            return json_err($e->getMessage(), 500);
        }
    }

    public function rest(Request $request): Response
    {
        try {
            DbInit::ensure();
            $on = $request->post('on', '1') === '1' || $request->post('on', '1') === 'true';
            $msg = PetService::rest($on);
            return json_ok(PetService::state(), $msg);
        } catch (BizException $e) {
            return json_err($e->getMessage());
        } catch (Throwable $e) {
            return json_err($e->getMessage(), 500);
        }
    }

    public function work(Request $request): Response
    {
        try {
            DbInit::ensure();
            $msg = PetService::work((string)$request->post('job', ''));
            return json_ok(PetService::state(), $msg);
        } catch (BizException $e) {
            return json_err($e->getMessage());
        } catch (Throwable $e) {
            return json_err($e->getMessage(), 500);
        }
    }

    public function workCollect(): Response
    {
        try {
            DbInit::ensure();
            $msg = PetService::workCollect();
            return json_ok(PetService::state(), $msg);
        } catch (BizException $e) {
            return json_err($e->getMessage());
        } catch (Throwable $e) {
            return json_err($e->getMessage(), 500);
        }
    }
}
