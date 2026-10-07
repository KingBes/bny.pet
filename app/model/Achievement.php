<?php

namespace app\model;

use support\think\Model;

class Achievement extends Model
{
    protected $table = 'achievements';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;
}
