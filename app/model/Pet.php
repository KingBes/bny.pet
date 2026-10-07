<?php

namespace app\model;

use support\think\Model;

class Pet extends Model
{
    protected $table = 'pet';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;
}
