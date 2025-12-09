@echo off
chcp 65001 >nul
setlocal

set PORT=80
set ROOT=%~dp0

echo ========================================
echo 局域网文件分享系统
echo ========================================
echo.
echo 正在启动 PHP 内置服务器...
echo 监听地址: http://0.0.0.0:%PORT%
echo 文档根目录: %ROOT%public
echo 提示: 按 Ctrl+C 停止服务器
echo ========================================
echo.

REM 优先使用 php/ 子目录，兼容旧结构
set "PHP_EXE="
set "PHP_INI="
set "PHP_EXT_DIR="

if exist "%ROOT%php\php.exe" (
    set "PHP_EXE=%ROOT%php\php.exe"
    set "PHP_INI=%ROOT%php\php.ini"
    set "PHP_EXT_DIR=%ROOT%php\ext"
    echo [信息] 使用 php/ 子目录中的 PHP
) else if exist "%ROOT%php.exe" (
    set "PHP_EXE=%ROOT%php.exe"
    set "PHP_INI=%ROOT%php.ini"
    set "PHP_EXT_DIR=%ROOT%ext"
    echo [信息] 使用根目录中的 PHP
) else (
    echo [错误] 未找到 php.exe
    echo 请确保 PHP 已正确解压。推荐放置于: %ROOT%php\
    echo 并复制 libsqlite3.dll 到与 php.exe 同级目录。
    pause
    exit /b 1
)

if not exist "%PHP_INI%" (
    echo [警告] 未找到 php.ini，将使用默认配置
) else (
    echo [信息] 使用配置文件: %PHP_INI%
)

echo.
echo 检查 SQLite 依赖...
if not exist "%~dp0php\libsqlite3.dll" if not exist "%~dp0libsqlite3.dll" (
    echo [警告] 未找到 libsqlite3.dll，可能导致 SQLite 无法加载。
    echo 请从 PHP 官方 zip 包根目录复制 libsqlite3.dll 到与 php.exe 同级。
    echo.
)

echo 检查 SQLite 扩展...
"%PHP_EXE%" -m | findstr /i "sqlite" >nul
if errorlevel 1 (
    echo [警告] SQLite 扩展未加载！
    echo 请检查 php.ini 与扩展目录: %PHP_EXT_DIR%
    echo 需要文件: php_pdo_sqlite.dll, php_sqlite3.dll, libsqlite3.dll
    echo.
    pause
)

"%PHP_EXE%" -c "%PHP_INI%" -S 0.0.0.0:%PORT% -t "%ROOT%public"

pause


