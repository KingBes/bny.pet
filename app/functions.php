<?php
/**
 * 全局助手函数
 */

use support\Response;

/** 统一成功响应：HTTP 恒为 200，业务码放 body.code */
function json_ok(array $data = [], string $msg = ''): Response
{
    return json_body(['code' => 0, 'msg' => $msg, 'data' => $data]);
}

/** 统一失败响应 */
function json_err(string $msg, int $code = 1): Response
{
    return json_body(['code' => $code, 'msg' => $msg, 'data' => []]);
}

function json_body(array $payload): Response
{
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    // 库里若混入非 UTF-8 字节，json_encode 会静默失败返回 false；
    // 兜底替换非法序列，保证接口永远有响应体
    if ($json === false) {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            $json = '{"code":500,"msg":"响应编码失败","data":[]}';
        }
    }
    return new Response(
        200,
        ['Content-Type' => 'application/json; charset=utf-8'],
        $json
    );
}
