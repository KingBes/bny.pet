<?php

namespace app\controller;

use app\exception\BizException;
use app\services\DbInit;
use app\services\PetService;
use support\Request;
use support\Response;
use Throwable;

class ShopController
{
    public function buy(Request $request): Response
    {
        try {
            DbInit::ensure();
            $msg = PetService::buy(
                (int)$request->post('item_id', 0),
                (int)$request->post('qty', 1)
            );
            return json_ok(PetService::state(), $msg);
        } catch (BizException $e) {
            return json_err($e->getMessage());
        } catch (Throwable $e) {
            return json_err($e->getMessage(), 500);
        }
    }
}
