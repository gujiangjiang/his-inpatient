#!/bin/bash
# 启动脚本
# 支持两种模式:
# 1. 系统 PHP + 内置服务器: php -S 127.0.0.1:8000 router.php
# 2. FrankenPHP: frankenphp php-server --root public/

PORT=${PORT:-8000}
FRANKENPHP_BIN="${FRANKENPHP_BIN:-$HOME/.local/bin/frankenphp}"

if command -v php &> /dev/null && [ "$1" != "franken" ]; then
    # 系统 PHP
    php -S "127.0.0.1:${PORT}" router.php
elif [ -x "$FRANKENPHP_BIN" ]; then
    # FrankenPHP
    "$FRANKENPHP_BIN" php-server --root public/ --listen "0.0.0.0:${PORT}"
else
    echo "错误: 未找到 PHP 环境"
    echo "请安装 PHP 或设置 FRANKENPHP_BIN=$HOME/.local/bin/frankenphp"
    exit 1
fi
