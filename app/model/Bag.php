<?php

namespace app\model;

use support\think\Model;

class Bag extends Model
{
    protected $table = 'bag';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;
}
