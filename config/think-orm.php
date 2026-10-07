<?php

return [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            // 数据库类型
            'type' => 'sqlite',
            // DSN 连接字符串：必须以 sqlite: 开头，后接绝对路径
            'dsn'         => 'sqlite:' . base_path("sql.db"),
            // 服务器地址
            'hostname' => '',
            // 数据库名
            'database' => '',
            // 数据库用户名
            'username' => '',
            // 数据库密码
            'password' => '',
            // 数据库连接端口
            'hostport' => '',
            // 数据库连接参数
            'params' => [
                // 连接超时3秒
                \PDO::ATTR_TIMEOUT => 3,
            ],
            // 数据库编码默认采用utf8
            'charset' => 'utf8',
            // 数据库表前缀
            'prefix' => '',
            // 断线重连
            'break_reconnect' => true,
            // 连接池配置
            'pool' => [
                'max_connections' => 5, // 最大连接数
                'min_connections' => 1, // 最小连接数
                'wait_timeout' => 3,    // 从连接池获取连接等待超时时间
                'idle_timeout' => 60,   // 连接最大空闲时间，超过该时间会被回收
                'heartbeat_interval' => 50, // 心跳检测间隔，需要小于60秒
            ],
        ],
    ],
    // 自定义分页类
    'paginator' =>  '',
];
