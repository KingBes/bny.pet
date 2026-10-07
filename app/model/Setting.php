<?php

namespace app\model;

use support\think\Model;

class Setting extends Model
{
    protected $table = 'settings';
    protected $pk = 'key';
    protected $autoWriteTimestamp = false;
}
