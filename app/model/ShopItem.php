<?php

namespace app\model;

use support\think\Model;

class ShopItem extends Model
{
    protected $table = 'shop_items';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;
}
