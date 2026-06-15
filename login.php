<?php
require 'config.php';

// --- สร้างตารางและผู้ใช้งานเริ่มต้นอัตโนมัติ (หากยังไม่มี) ---
$chk_table = $conn->query("SHOW TABLES LIKE 'users'");
if ($chk_table && $chk_table->num_rows == 0) {
    $conn->query("CREATE TABLE `users` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `username` varchar(50) NOT NULL,
      `password` varchar(255) NOT NULL,
      `role` enum('cashier','manager') NOT NULL DEFAULT 'cashier',
      `name` varchar(100) NOT NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `username` (`username`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    // สร้าง User เริ่มต้น (รหัสผ่านคือ frog ทั้งคู่)
    $hashed = password_hash('frog', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO `users` (`username`, `password`, `role`, `name`) VALUES 
    ('admin', '$hashed', 'manager', 'ผู้จัดการร้าน'), 
    ('staff', '$hashed', 'cashier', 'พนักงานขาย (แคชเชียร์)')");
}
// --------------------------------------------------------

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $stmt = $conn->prepare("SELECT id, username, password, role, name FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // 1. ตรวจสอบว่าเป็นรหัส PIN ฉุกเฉินจาก config.php หรือไม่ (เฉพาะผู้จัดการ)
        if (isset($recovery_pin) && $password === $recovery_pin && ($row['role'] === 'manager' || $row['role'] === 'admin')) {
            $hashed_default = password_hash('frog', PASSWORD_DEFAULT);
            $conn->query("UPDATE users SET password = '$hashed_default' WHERE id = " . $row['id']);
            $error = '✅ รีเซ็ตรหัสผ่านสำเร็จ! รหัสผ่านกลับเป็นค่าเริ่มต้นแล้ว';
            $error_color = '#2ecc71'; // แจ้งเตือนสีเขียว
        }
        // 2. ตรวจสอบรหัสผ่านปกติ
        else if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['name'] = $row['name'];
            header("Location: index.php");
            exit;
        } else {
            $error = 'รหัสผ่านไม่ถูกต้อง!';
        }
    } else {
        $error = 'ไม่พบชื่อผู้ใช้งานนี้ในระบบ!';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เข้าสู่ระบบ - FROG POS</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; background: #0f111a; color: white; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-box { background: #1a1d29; padding: 40px; border-radius: 20px; width: 350px; box-shadow: 0 10px 40px rgba(0,0,0,0.8); border: 1px solid #333; text-align: center; }
        .login-box h1 { color: #00d4ff; margin-top: 0; margin-bottom: 5px; font-size: 32px; }
        .login-box p.subtitle { color: #aaa; margin-bottom: 30px; font-size: 14px; }
        .input-group { margin-bottom: 20px; text-align: left; }
        .input-group label { display: block; margin-bottom: 8px; color: #ccc; font-size: 14px; }
        .input-group input { width: 100%; padding: 12px; background: #0f111a; border: 1px solid #444; color: white; border-radius: 8px; box-sizing: border-box; font-size: 16px; transition: 0.3s; }
        .input-group input:focus { border-color: #00d4ff; outline: none; }
        .btn-login { background: #00d4ff; color: black; border: none; padding: 14px; width: 100%; border-radius: 8px; font-weight: bold; font-size: 16px; cursor: pointer; transition: 0.3s; margin-top: 10px; }
        .btn-login:hover { background: #00b8e6; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,212,255,0.3); }
        .error { background: rgba(255,255,255,0.1); color: #ff4d4d; padding: 10px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; border: 1px solid currentColor; }
    </style>
</head>
<body>
    <div class="login-box">
        <h1>FROG POS</h1>
        <p class="subtitle">ระบบจัดการร้านบาร์และเครื่องดื่ม</p>
        <?php if($error): ?><div class="error" style="color: <?php echo isset($error_color) ? $error_color : '#ff4d4d'; ?>;">⚠️ <?php echo $error; ?></div><?php endif; ?>
        <form method="POST">
            <div class="input-group">
                <label>ชื่อผู้ใช้งาน (Username)</label>
                <input type="text" name="username" required autofocus placeholder="กรอกชื่อผู้ใช้งาน">
            </div>
            <div class="input-group">
                <label>รหัสผ่าน (Password)</label>
                <input type="password" name="password" required placeholder="••••••••">
            </div>
            <button type="submit" class="btn-login">เข้าสู่ระบบ</button>
        </form>
    </div>
</body>
</html>