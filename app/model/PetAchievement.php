<?php

namespace app\model;

use support\think\Model;

class PetAchievement extends Model
{
    protected $table = 'pet_achievements';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;
}
