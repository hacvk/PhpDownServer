# PowerShell 启动脚本 - 兼容 Windows Server 2012
$port = 80
$root = Split-Path -Parent $MyInvocation.MyCommand.Path

Write-Host "========================================"
Write-Host "局域网文件分享系统"
Write-Host "========================================"
Write-Host ""
Write-Host "正在启动 PHP 内置服务器..."
Write-Host "监听地址: http://0.0.0.0:$port"
Write-Host "文档根目录: $root\public"
Write-Host "提示: 按 Ctrl+C 停止服务器"
Write-Host "========================================"
Write-Host ""

# 优先使用 php/ 子目录
$phpExe = $null
$phpIni = $null
$phpExtDir = $null

if (Test-Path "$root\php\php.exe") {
    $phpExe = "$root\php\php.exe"
    $phpIni = "$root\php\php.ini"
    $phpExtDir = "$root\php\ext"
    Write-Host "[信息] 使用 php/ 子目录中的 PHP" -ForegroundColor Green
} elseif (Test-Path "$root\php.exe") {
    $phpExe = "$root\php.exe"
    $phpIni = "$root\php.ini"
    $phpExtDir = "$root\ext"
    Write-Host "[信息] 使用根目录中的 PHP" -ForegroundColor Green
} else {
    Write-Host "[错误] 未找到 php.exe" -ForegroundColor Red
    Write-Host "请确保 PHP 已正确解压。推荐放置于: $root\php\" -ForegroundColor Yellow
    Write-Host "并复制 libsqlite3.dll 到与 php.exe 同级目录。" -ForegroundColor Yellow
    Read-Host "按 Enter 退出"
    exit 1
}

if (-not (Test-Path $phpIni)) {
    Write-Host "[警告] 未找到 php.ini，将使用默认配置" -ForegroundColor Yellow
} else {
    Write-Host "[信息] 使用配置文件: $phpIni" -ForegroundColor Green
}

Write-Host ""
Write-Host "检查 SQLite 依赖..." -ForegroundColor Cyan
if (-not (Test-Path "$root\php\libsqlite3.dll") -and -not (Test-Path "$root\libsqlite3.dll")) {
    Write-Host "[警告] 未找到 libsqlite3.dll，可能导致 SQLite 无法加载。" -ForegroundColor Yellow
    Write-Host "请从 PHP 官方 zip 包根目录复制 libsqlite3.dll 到与 php.exe 同级。" -ForegroundColor Yellow
}

Write-Host "检查 SQLite 扩展..." -ForegroundColor Cyan
$extensions = & $phpExe -m 2>$null
if ($extensions -match "sqlite") {
    Write-Host "[✓] SQLite 扩展已加载" -ForegroundColor Green
} else {
    Write-Host "[警告] SQLite 扩展未加载！" -ForegroundColor Yellow
    Write-Host "请检查 php.ini 配置和扩展目录中的 DLL 文件" -ForegroundColor Yellow
    Write-Host "扩展目录: $phpExtDir" -ForegroundColor Yellow
    Write-Host ""
    Read-Host "按 Enter 继续（可能无法正常工作）"
}

& $phpExe -c $phpIni -S 0.0.0.0:$port -t "$root\public"


