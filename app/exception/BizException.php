<?php

namespace app\exception;

/** 业务逻辑错误，控制器捕获后转成 json_err 返回给前端 */
class BizException extends \RuntimeException
{
}
