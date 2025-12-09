<?php
// 超级管理修复工具：绕过 IP 限制，修复后台 IP 白名单或重置管理员密码
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';

$err = '';
$success = '';
$settings = $db->getSettings();
$backendIp = $settings['ip_whitelist_backend'] ?? '';
$backendIpDisplay = $backendIp === '' ? '' : IpInputParser::jsonToDisplay($backendIp);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    if ($action === 'clear_backend_ip') {
        $db->setSetting('ip_whitelist_backend', '');
        $backendIp = '';
        $backendIpDisplay = '';
        $success = '后台IP限制已清除，现在所有IP都可以访问后台';
    } elseif ($action === 'set_backend_ip') {
        $backendIpsInput = trim($_POST['backend_ips'] ?? '');
        $backendIpsJson = $backendIpsInput === '' ? '' : IpInputParser::parseToJson($backendIpsInput);
        $db->setSetting('ip_whitelist_backend', $backendIpsJson);
        $backendIp = $backendIpsJson;
        $backendIpDisplay = $backendIpsInput;
        $success = '后台IP限制已更新';
    } elseif ($action === 'reset_password') {
        $newPassword = $_POST['password'] ?? '';
        if (strlen($newPassword) < 6) {
            $err = '密码长度至少6位';
        } else {
            Auth::setPassword($db, $newPassword);
            $success = '管理员密码已重置';
        }
    }
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>超级管理修复工具</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); min-height: 100vh; padding: 20px; }
    .container { max-width: 700px; margin: 0 auto; background: white; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); overflow: hidden; }
    .header { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; padding: 30px; text-align: center; }
    .header h1 { font-size: 28px; margin-bottom: 10px; }
    .header p { opacity: 0.9; font-size: 14px; }
    .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px 20px; margin: 20px; border-radius: 8px; color: #856404; }
    .content { padding: 40px; }
    .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; }
    .alert-error { background: #fee; color: #c33; border-left: 4px solid #c33; }
    .alert-success { background: #efe; color: #3c3; border-left: 4px solid #3c3; }
    .form-group { margin-bottom: 25px; }
    .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #495057; font-size: 14px; }
    .form-group input[type=text], .form-group input[type=password], .form-group textarea {
      width: 100%; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; transition: border-color 0.3s; font-family: inherit;
    }
    .form-group input:focus, .form-group textarea:focus { outline: none; border-color: #dc3545; }
    .form-group textarea { min-height: 100px; resize: vertical; }
    .form-group small { display: block; margin-top: 6px; color: #6c757d; font-size: 12px; line-height: 1.5; }
    .btn { display: inline-block; padding: 12px 24px; background: #dc3545; color: white; text-decoration: none; border-radius: 8px; transition: all 0.3s; border: none; cursor: pointer; font-size: 14px; font-weight: 600; font-family: inherit; }
    .btn:hover { background: #c82333; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(220,53,69,0.4); }
    .btn-block { width: 100%; text-align: center; }
    .btn-secondary { background: #6c757d; } .btn-secondary:hover { background: #5a6268; }
    .section { margin-bottom: 40px; padding-bottom: 30px; border-bottom: 1px solid #e9ecef; }
    .section:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
    .section h2 { font-size: 20px; margin-bottom: 20px; color: #495057; padding-bottom: 10px; border-bottom: 2px solid #dc3545; }
    .info-box { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; color: #495057; }
    .info-box strong { color: #dc3545; }
    .ip-examples { background: #f8f9fa; padding: 12px; border-radius: 6px; margin-top: 8px; font-size: 12px; color: #6c757d; font-family: 'Courier New', monospace; }
    @media (max-width: 768px) { .container { margin: 10px; border-radius: 8px; } .header { padding: 20px; } .content { padding: 25px; } }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>🔧 超级管理修复工具</h1>
      <p>紧急修复后台访问限制</p>
    </div>
    <div class="warning">
      <strong>⚠️ 安全提示：</strong>此工具绕过所有IP限制，可直接修改后台设置。使用后建议删除或重命名此文件。
    </div>
    <div class="content">
      <?php if ($err): ?><div class="alert alert-error"><span>❌</span><span><?php echo htmlspecialchars($err); ?></span></div><?php endif; ?>
      <?php if ($success): ?><div class="alert alert-success"><span>✅</span><span><?php echo htmlspecialchars($success); ?></span></div><?php endif; ?>

      <div class="info-box">
        <strong>当前IP：</strong><?php echo htmlspecialchars(request_ip()); ?><br>
        <strong>当前后台IP限制：</strong><?php echo $backendIp === '' ? '无限制（所有IP可访问）' : nl2br(htmlspecialchars($backendIpDisplay)); ?>
      </div>

      <div class="section">
        <h2>🔓 清除后台IP限制</h2>
        <p style="margin-bottom:15px;color:#6c757d;">清除所有后台IP限制，允许所有IP访问后台</p>
        <form method="post">
          <input type="hidden" name="action" value="clear_backend_ip">
          <button type="submit" class="btn btn-block">清除IP限制</button>
        </form>
      </div>

      <div class="section">
        <h2>🔒 设置后台IP限制</h2>
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
          <button type="submit" class="btn btn-block">💾 保存设置</button>
        </form>
      </div>

      <div class="section">
        <h2>🔑 重置管理员密码</h2>
        <form method="post">
          <input type="hidden" name="action" value="reset_password">
          <div class="form-group">
            <label>新密码</label>
            <input type="password" name="password" required placeholder="请输入新密码（至少6位）">
            <small>设置新的管理员登录密码</small>
          </div>
          <button type="submit" class="btn btn-block">重置密码</button>
        </form>
      </div>

      <div style="text-align:center;margin-top:30px;padding-top:20px;border-top:1px solid #e9ecef;">
        <a href="/admin.php" class="btn btn-secondary">返回后台管理</a>
      </div>
    </div>
  </div>
</body>
</html>


