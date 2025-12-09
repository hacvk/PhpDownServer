<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';

$siteName = $settings['site_name'] ?? '文件分享';

if (!$rootPath) {
    echo '<!doctype html><html><head><meta charset="UTF-8"><title>错误</title></head><body style="font-family:Arial;padding:40px;text-align:center;"><h2>尚未初始化站点根目录</h2><p>请先访问 <a href="/admin.php">/admin.php</a> 初始化。</p></body></html>';
    exit;
}

$rootReal = realpath($rootPath);
if ($rootReal === false) {
    http_response_code(500);
    exit('根目录不存在或不可访问: ' . htmlspecialchars($rootPath));
}

$reqPath = $_GET['path'] ?? '/';
$reqPath = '/' . ltrim($reqPath, '/');
$absCurrent = safe_join($rootReal, $reqPath);
if ($absCurrent === '') {
    http_response_code(400);
    exit('非法路径');
}

$pdo = $db->pdo();

// 下载
if (isset($_GET['download'])) {
    $rel = '/' . ltrim($_GET['download'], '/');
    $abs = safe_join($rootReal, $rel);
    if ($abs === '') { http_response_code(400); exit('非法文件'); }

    $stmt = $pdo->prepare("SELECT allowed_ranges, access_password FROM files WHERE path = :p");
    $stmt->execute([':p' => $rel]);
    $row = $stmt->fetch();
    if ($row && !empty($row['allowed_ranges'])) {
        if (!IpRange::matchRanges(request_ip(), $row['allowed_ranges'])) {
            http_response_code(403); exit('当前 IP 无下载权限');
        }
    }
    if ($row && !empty($row['access_password'])) {
        $pwd = $_GET['pwd'] ?? '';
        if ($pwd === '' || $pwd !== $row['access_password']) {
            http_response_code(403);
            exit('需要密码访问此文件/目录');
        }
    }
    Download::stream($abs, basename($abs));
    exit;
}

// 列出当前目录的直接子项
$prefix = rtrim($reqPath, '/');
if ($prefix === '') $prefix = '/';
$stmt = $pdo->prepare("SELECT path, is_dir, size, mtime FROM files WHERE path LIKE :p || '%' ORDER BY is_dir DESC, path");
$stmt->execute([':p' => $prefix === '/' ? '/' : $prefix . '/']);
$rows = $stmt->fetchAll();
$items = [];
foreach ($rows as $r) {
    $rem = ltrim(substr($r['path'], strlen($prefix)), '/');
    if ($rem === '') continue; // 跳过自身
    if (strpos($rem, '/') !== false) continue; // 只保留直接子项
    $items[] = $r + ['name' => $rem];
}

// 计算父目录
$parent = null;
if ($reqPath !== '/' && $reqPath !== '') {
    $parentPath = dirname($reqPath);
    if ($parentPath === '/' || $parentPath === '\\' || $parentPath === '.') {
        $parent = '/';
    } else {
        $parent = '/' . ltrim(str_replace('\\', '/', $parentPath), '/');
    }
}

function format_size($bytes) {
    if ($bytes == 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}
// 页面标题：当前层级名称 - 站点名称
$trimmed = trim($reqPath, '/');
$currentName = $trimmed === '' ? '根目录' : basename($trimmed);
$pageTitle = $currentName . ' - ' . $siteName;

?>
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($pageTitle); ?></title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      padding: 20px;
    }
    .container {
      max-width: 1200px;
      margin: 0 auto;
      background: white;
      border-radius: 12px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.1);
      overflow: hidden;
    }
    .header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 30px;
    }
    .header h1 { font-size: 28px; margin-bottom: 10px; }
    .breadcrumb { font-size: 14px; opacity: 0.9; margin-top: 10px; }
    .breadcrumb a { color: white; text-decoration: none; opacity: 0.8; }
    .breadcrumb a:hover { opacity: 1; text-decoration: underline; }
    .content { padding: 30px; }
    .nav-bar { margin-bottom: 20px; display: flex; gap: 10px; align-items: center; }
    .btn {
      display: inline-block; padding: 10px 20px; background: #667eea; color: white;
      text-decoration: none; border-radius: 6px; transition: all 0.3s; border: none; cursor: pointer; font-size: 14px;
    }
    .btn:hover { background: #5568d3; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(102,126,234,0.4); }
    .btn-secondary { background: #6c757d; }
    .btn-secondary:hover { background: #5a6268; }
    .table-wrapper { overflow-x: auto; border-radius: 8px; border: 1px solid #e0e0e0; }
    table { width: 100%; border-collapse: collapse; background: white; }
    thead { background: #f8f9fa; }
    th { padding: 16px; text-align: left; font-weight: 600; color: #495057; border-bottom: 2px solid #dee2e6; font-size: 14px; text-transform: uppercase; letter-spacing: .5px; }
    td { padding: 14px 16px; border-bottom: 1px solid #e9ecef; color: #495057; }
    tbody tr { transition: background .2s; }
    tbody tr:hover { background: #f8f9fa; }
    tbody tr:last-child td { border-bottom: none; }
    .file-icon { display: inline-block; width: 24px; height: 24px; margin-right: 10px; vertical-align: middle; text-align: center; line-height: 24px; border-radius: 4px; font-size: 14px; }
    .file-icon.folder { background: #fff3cd; color: #856404; }
    .file-icon.file { background: #d1ecf1; color: #0c5460; }
    a.file-link { color: #667eea; text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; transition: color .2s; }
    a.file-link:hover { color: #5568d3; text-decoration: underline; }
    .size { color: #6c757d; font-family: 'Courier New', monospace; }
    .time { color: #6c757d; font-size: 13px; }
    .empty { text-align: center; padding: 60px 20px; color: #6c757d; }
    .empty-icon { font-size: 64px; margin-bottom: 20px; opacity: 0.5; }
    @media (max-width: 768px) {
      .container { margin: 10px; border-radius: 8px; }
      .header { padding: 20px; }
      .content { padding: 20px; }
      table { font-size: 14px; }
      th, td { padding: 10px 8px; }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>📁 文件列表</h1>
      <div class="breadcrumb">
        <a href="/">首页</a>
        <?php
        $parts = explode('/', trim($reqPath, '/'));
        $currentPath = '';
        foreach ($parts as $part) {
            if ($part === '') continue;
            $currentPath .= '/' . $part;
            echo ' / <a href="?path=' . urlencode($currentPath) . '">' . htmlspecialchars($part) . '</a>';
        }
        ?>
      </div>
    </div>
    <div class="content">
      <div class="nav-bar">
        <?php if ($parent !== null): ?>
          <a href="?path=<?php echo urlencode($parent); ?>" class="btn btn-secondary">⬆ 返回上级</a>
        <?php endif; ?>
        <a href="/admin.php" class="btn btn-secondary" style="margin-left:auto;">⚙ 管理后台</a>
      </div>
      
      <?php if (empty($items)): ?>
        <div class="empty">
          <div class="empty-icon">📂</div>
          <p>此目录为空</p>
        </div>
      <?php else: ?>
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th style="width:60px;">#</th>
                <th>名称</th>
                <th style="width:120px;">大小</th>
                <th style="width:180px;">修改时间</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($items as $i => $row): ?>
                <?php 
                $basePath = rtrim($reqPath, '/'); if ($basePath === '') $basePath = '/';
                $itemPath = $basePath . '/' . $row['name'];
                ?>
                <tr>
                  <td><?php echo $i + 1; ?></td>
                  <td>
                    <?php if ($row['is_dir']): ?>
                      <a href="?path=<?php echo urlencode($itemPath); ?>" class="file-link">
                        <span class="file-icon folder">📁</span>
                        <?php echo htmlspecialchars($row['name']); ?>
                      </a>
                    <?php else: ?>
                      <a href="?download=<?php echo urlencode($itemPath); ?>" class="file-link" target="_blank" rel="noopener noreferrer">
                        <span class="file-icon file">📄</span>
                        <?php echo htmlspecialchars($row['name']); ?>
                      </a>
                    <?php endif; ?>
                  </td>
                  <td class="size"><?php echo $row['is_dir'] ? '<span style="color:#6c757d;">-</span>' : format_size((int)$row['size']); ?></td>
                  <td class="time"><?php echo date('Y-m-d H:i:s', (int)$row['mtime']); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>


