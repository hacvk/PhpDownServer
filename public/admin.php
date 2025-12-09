<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';

$auth = new Auth($db);
$settings = $db->getSettings();
$backendIp = $settings['ip_whitelist_backend'] ?? '';
$siteName = $settings['site_name'] ?? '文件分享';
$rootPath = $settings['root_path'] ?? null;
$err = '';
$success = '';

// 后台 IP 白名单检查（即便未登录也生效）
if (!empty($settings['admin_hash']) && $backendIp !== '') {
    if (!IpRange::matchRanges(request_ip(), $backendIp)) {
        http_response_code(403);
        exit('<!doctype html><html><head><meta charset="UTF-8"><title>拒绝访问</title></head><body style="font-family:Arial;padding:40px;text-align:center;"><h2 style="color:#dc3545;">403 拒绝访问</h2><p>您的 IP 地址不在后台访问白名单中。</p><p style="margin-top:20px;font-size:12px;color:#666;">如需修改，请使用超级管理修复工具：<a href="/admin_fix.php">admin_fix.php</a></p></body></html>');
    }
}

// 处理退出登录
if (isset($_GET['logout'])) {
    unset($_SESSION['admin']);
    header('Location: /admin.php');
    exit;
}

// 处理提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    if ($action === 'init') {
        $rootInput = rtrim($_POST['root'] ?? '', "\\/");
        if ($rootInput === '' || !is_dir($rootInput)) {
            $err = '根目录无效或不存在';
        } else {
            Auth::setPassword($db, $_POST['password'] ?? '');
            $db->setSetting('root_path', $rootInput);
            $db->setSetting('site_name', trim($_POST['site_name'] ?? '文件分享'));
            $backendIpsInput = trim($_POST['backend_ips'] ?? '');
            $backendIpsJson = $backendIpsInput === '' ? '' : IpInputParser::parseToJson($backendIpsInput);
            $db->setSetting('ip_whitelist_backend', $backendIpsJson);
            (new Scanner($db, $rootInput))->rescan();
            header('Location: /admin.php?init=ok');
            exit;
        }
    } elseif ($action === 'login') {
        if ($auth->login($_POST['password'] ?? '')) {
            header('Location: /admin.php');
            exit;
        }
        $err = '登录失败，密码错误';
    } else {
        // 已初始化的操作需要管理员身份
        $auth->ensureAdmin($backendIp);
        if ($action === 'upload') {
            if (!empty($_FILES['file']['tmp_name'])) {
                $targetDir = rtrim($rootPath ?? '', "\\/");
                if ($targetDir === '' || !is_dir($targetDir)) {
                    $err = '根目录无效，无法上传';
                } else {
                    $filename = basename($_FILES['file']['name']);
                    $dest = $targetDir . DIRECTORY_SEPARATOR . $filename;
                    if (!move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
                        $err = '上传失败，请检查目录权限';
                    } else {
                        (new Scanner($db, $targetDir))->rescan();
                        $success = '文件上传成功';
                    }
                }
            }
        } elseif ($action === 'allow') {
            $pathsInput = trim($_POST['paths_input'] ?? '');
            $rangesInput = trim($_POST['ranges_input'] ?? '');
            $pwdInput    = trim($_POST['access_password'] ?? '');
            if ($pathsInput === '') {
                $err = '请填写路径';
            } else {
                $rangesJson = $rangesInput === '' ? '[]' : IpInputParser::parseToJson($rangesInput);
                $paths = preg_split('/[\r\n]+/', $pathsInput);
                $stmt = $db->pdo()->prepare("UPDATE files SET allowed_ranges = :r, access_password = :pwd WHERE path = :p");
                foreach ($paths as $p) {
                    $p = '/' . ltrim(trim($p), '/');
                    if ($p === '') continue;
                    $stmt->execute([
                        ':r'   => $rangesJson,
                        ':pwd' => ($pwdInput === '' ? null : $pwdInput),
                        ':p'   => $p
                    ]);
                }
                $success = '访问控制已更新';
            }
        } elseif ($action === 'set_backend_ip') {
            $backendIpsInput = trim($_POST['backend_ips'] ?? '');
            $backendIpsJson = $backendIpsInput === '' ? '' : IpInputParser::parseToJson($backendIpsInput);
            $db->setSetting('ip_whitelist_backend', $backendIpsJson);
            $backendIp = $backendIpsJson;
            $success = '后台访问IP限制设置成功';
        } elseif ($action === 'set_site_name') {
            $siteName = trim($_POST['site_name'] ?? '');
            if ($siteName === '') $siteName = '文件分享';
            $db->setSetting('site_name', $siteName);
            $success = '站点名称已更新';
        } elseif ($action === 'rescan') {
            if ($rootPath && is_dir($rootPath)) {
                (new Scanner($db, $rootPath))->rescan();
                $success = '目录扫描完成';
            } else {
                $err = '根目录无效，无法扫描';
            }
        }
    }
}

if (isset($_GET['init']) && $_GET['init'] === 'ok') {
    $success = '初始化成功！目录扫描已完成。';
}

$isInit = empty($settings['admin_hash']);
$backendIpDisplay = $backendIp === '' ? '' : IpInputParser::jsonToDisplay($backendIp);
?>
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>后台管理 - 文件分享系统</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      padding: 20px;
    }
    .container { max-width: 800px; margin: 0 auto; background: white; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); overflow: hidden; }
    .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
    .header h1 { font-size: 28px; margin-bottom: 10px; }
    .header p { opacity: 0.9; font-size: 14px; }
    .content { padding: 40px; }
    .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; }
    .alert-error { background: #fee; color: #c33; border-left: 4px solid #c33; }
    .alert-success { background: #efe; color: #3c3; border-left: 4px solid #3c3; }
    .alert-info { background: #e7f3ff; color: #0066cc; border-left: 4px solid #0066cc; }
    .form-group { margin-bottom: 25px; }
    .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #495057; font-size: 14px; }
    .form-group input[type=text], .form-group input[type=password], .form-group input[type=file], .form-group textarea {
      width: 100%; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; transition: border-color 0.3s; font-family: inherit;
    }
    .form-group input[type=text]:focus, .form-group input[type=password]:focus, .form-group textarea:focus { outline: none; border-color: #667eea; }
    .form-group textarea { min-height: 100px; resize: vertical; }
    .form-group small { display: block; margin-top: 6px; color: #6c757d; font-size: 12px; line-height: 1.5; }
    .btn { display: inline-block; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; transition: all 0.3s; border: none; cursor: pointer; font-size: 14px; font-weight: 600; font-family: inherit; }
    .btn:hover { background: #5568d3; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(102,126,234,0.4); }
    .btn-block { width: 100%; text-align: center; }
    .btn-secondary { background: #6c757d; } .btn-secondary:hover { background: #5a6268; }
    .btn-danger { background: #dc3545; } .btn-danger:hover { background: #c82333; }
    .section { margin-bottom: 40px; padding-bottom: 30px; border-bottom: 1px solid #e9ecef; }
    .section:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
    .section h2 { font-size: 20px; margin-bottom: 20px; color: #495057; padding-bottom: 10px; border-bottom: 2px solid #667eea; }
    .info-box { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; color: #495057; }
    .info-box strong { color: #667eea; }
    .ip-examples { background: #f8f9fa; padding: 12px; border-radius: 6px; margin-top: 8px; font-size: 12px; color: #6c757d; font-family: 'Courier New', monospace; }
    .nav-links { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e9ecef; }
    .nav-links a { color: #667eea; text-decoration: none; margin: 0 15px; font-size: 14px; }
    .nav-links a:hover { text-decoration: underline; }
    @media (max-width: 768px) { .container { margin: 10px; border-radius: 8px; } .header { padding: 20px; } .content { padding: 25px; } }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>⚙️ 后台管理</h1>
      <p>文件分享系统管理控制台</p>
    </div>
    <div class="content">
      <?php if ($err): ?>
        <div class="alert alert-error"><span>❌</span><span><?php echo htmlspecialchars($err); ?></span></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="alert alert-success"><span>✅</span><span><?php echo htmlspecialchars($success); ?></span></div>
      <?php endif; ?>

      <?php if ($isInit): ?>
        <div class="section">
          <h2>🚀 系统初始化</h2>
          <p style="margin-bottom:20px;color:#6c757d;">首次使用需要初始化系统配置</p>
          <form method="post">
            <input type="hidden" name="action" value="init">
            <div class="form-group">
              <label>文件根目录</label>
              <input type="text" name="root" required placeholder="如 D:\share 或 E:\files">
              <small>设置要分享的文件根目录路径</small>
            </div>
            <div class="form-group">
              <label>站点名称</label>
              <input type="text" name="site_name" placeholder="如：公司资料库" value="<?php echo htmlspecialchars($siteName); ?>">
              <small>前台页面标题显示为「路径 - 站点名称」，默认“文件分享”</small>
            </div>
            <div class="form-group">
              <label>管理员密码</label>
              <input type="password" name="password" required placeholder="设置管理员登录密码">
              <small>请设置一个强密码</small>
            </div>
            <div class="form-group">
              <label>后台访问 IP 限制（可选）</label>
              <textarea name="backend_ips" placeholder="192.168.1.0/24&#10;10.0.0.1-10.0.0.100"></textarea>
              <small>每行一个，支持 CIDR、IP范围、单IP；留空表示不限制。</small>
              <div class="ip-examples">
CIDR: 192.168.1.0/24<br>
范围: 192.168.1.10-192.168.1.50<br>
单IP: 192.168.1.100
              </div>
            </div>
            <button type="submit" class="btn btn-block">💾 保存并扫描目录</button>
          </form>
        </div>
      <?php elseif (empty($_SESSION['admin'])): ?>
        <div class="section">
          <h2>🔐 管理员登录</h2>
          <form method="post">
            <input type="hidden" name="action" value="login">
            <div class="form-group">
              <label>管理员密码</label>
              <input type="password" name="password" required placeholder="请输入管理员密码" autofocus>
            </div>
            <button type="submit" class="btn btn-block">登录</button>
          </form>
        </div>
      <?php else: ?>
        <div class="info-box">
          <strong>当前根目录：</strong><?php echo htmlspecialchars($rootPath ?? '未设置'); ?><br>
          <strong>当前IP：</strong><?php echo htmlspecialchars(request_ip()); ?>
        </div>

        <div class="section">
          <h2>📝 站点名称</h2>
          <form method="post">
            <input type="hidden" name="action" value="set_site_name">
            <div class="form-group">
              <label>站点名称</label>
              <input type="text" name="site_name" value="<?php echo htmlspecialchars($siteName); ?>" placeholder="如：公司资料库">
              <small>前台标题格式：当前路径 - 站点名称；默认“文件分享”</small>
            </div>
            <button type="submit" class="btn">💾 保存名称</button>
          </form>
        </div>

        <div class="section">
          <h2>📂 目录管理</h2>
          <form method="post">
            <input type="hidden" name="action" value="rescan">
            <p style="margin-bottom:15px;color:#6c757d;">重新扫描文件根目录，更新文件索引</p>
            <button type="submit" class="btn">🔄 重扫目录</button>
          </form>
        </div>

        <div class="section">
          <h2>📤 文件上传</h2>
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload">
            <div class="form-group">
              <label>选择文件</label>
              <input type="file" name="file" required>
              <small>文件将上传到根目录</small>
            </div>
            <button type="submit" class="btn">上传文件</button>
          </form>
        </div>

        <div class="section">
          <h2>🔒 后台访问 IP 限制</h2>
          <form method="post">
            <input type="hidden" name="action" value="set_backend_ip">
            <div class="form-group">
              <label>允许访问后台的 IP 段</label>
              <textarea name="backend_ips" placeholder="192.168.1.0/24&#10;10.0.0.1-10.0.0.100"><?php echo htmlspecialchars($backendIpDisplay); ?></textarea>
              <small>每行一个，支持 CIDR、IP范围、单IP；留空表示不限制。</small>
              <div class="ip-examples">
CIDR: 192.168.1.0/24<br>
范围: 192.168.1.10-192.168.1.50<br>
单IP: 192.168.1.100<br>
留空: 不限制
              </div>
            </div>
            <button type="submit" class="btn">💾 保存设置</button>
          </form>
        </div>

        <div class="section">
          <h2>🔒 文件/目录 IP 与密码访问控制</h2>
          <form method="post">
            <input type="hidden" name="action" value="allow">
            <div class="form-group">
              <label>文件/目录路径（可多行）</label>
              <textarea name="paths_input" placeholder="/subdir&#10;/docs&#10;/docs/a.pdf" required></textarea>
              <small>每行一个相对路径，如 /subdir、/docs/a.pdf</small>
            </div>
            <div class="form-group">
              <label>允许访问的 IP 段</label>
              <textarea name="ranges_input" placeholder="192.168.1.0/24&#10;10.0.0.1-10.0.0.100"></textarea>
              <small>每行一个，支持 CIDR、IP范围、单IP；留空表示不限制。</small>
              <div class="ip-examples">
CIDR: 192.168.1.0/24<br>
范围: 192.168.1.10-192.168.1.50<br>
单IP: 192.168.1.100<br>
留空: 不限制
              </div>
            </div>
            <div class="form-group">
              <label>访问密码（可选）</label>
              <input type="text" name="access_password" placeholder="设置访问密码，可留空">
              <small>设置后访问该路径需在下载链接添加 ?pwd=密码；IP 与密码需同时满足。</small>
            </div>
            <button type="submit" class="btn">💾 保存设置</button>
          </form>
          <div class="alert alert-info" style="margin-top:15px;">
            <span>ℹ️</span>
            <span>支持一次输入多条路径；若不同路径需不同策略，请分多次提交。</span>
          </div>
        </div>

        <div class="nav-links">
          <a href="/">← 返回前台</a>
          <a href="/admin.php?logout=1">退出登录</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>

