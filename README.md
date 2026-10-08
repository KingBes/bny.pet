# pebman-pet 桌宠物

> 桌宠物是一个基于 webman 框架 + pebview 拓展的项目，用于模拟一个的宠物。

## 环境要求

- PHP >= 8.2
- PHP-FFI: *
- Windows x86_64: webview2
- Linux x86_64/arm64: GTK+3
- MacOS x86_64/arm64: Cocoa

## 安装

```sh
git clone https://github.com/KingBes/bny-pet.git
cd bny-pet
composer install

# 启动项目
php start.php start # linux/macos
php ./vendor/kingbes/pebview/windows.php # windows
```

## 功能

- [X] 创建宠物
- [X] 重置宠物
- [X] 喂食
- [X] 玩耍
- [X] 休息
- [X] 打工
- [X] 商店
- [X] 成就
- [X] 换肤

## 截图

![demo](demo.png)
