<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
if ($_SESSION['role'] !== 'manager' && $_SESSION['role'] !== 'admin') {
    echo "<script>alert('❌ เฉพาะผู้จัดการเท่านั้นที่สามารถเข้าหน้านี้ได้'); window.location.href='index.php';</script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แดชบอร์ดผู้จัดการ - FROG POS</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #00d4ff; --bg: #0f111a; --card: #1a1d29; }
        body { font-family: 'Sarabun', sans-serif; background: var(--bg); color: white; padding: 20px; margin: 0; }
        .container { max-width: 1100px; margin: auto; }
        
        .nav-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding: 10px 0; border-bottom: 1px solid #333; }
        .nav-links a { color: #aaa; text-decoration: none; margin-left: 20px; transition: 0.3s; }
        .nav-links a:hover { color: var(--primary); }
        .nav-links a.active { color: var(--primary); font-weight: bold; }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
        }
        .dashboard-card {
            background: var(--card);
            padding: 30px;
            border-radius: 18px;
            text-decoration: none;
            color: white;
            border: 1px solid #252a3a;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(0,0,0,0.4);
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: 0 12px 30px rgba(0, 212, 255, 0.2);
        }
        .dashboard-card h3 { margin-top: 0; font-size: 22px; color: var(--primary); }
        .dashboard-card p { color: #aaa; font-size: 14px; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav-bar">
            <h2 style="margin:0; color:var(--primary);">FROG POS</h2>
            <div class="nav-links">
                <a href="index.php">หน้าขาย (POS)</a>
                <a href="sales_report.php">รายงานยอดขาย</a>
                <a href="dashboard.php" class="active">แดชบอร์ดจัดการ</a>
            </div>
            <div style="display: flex; align-items: center; gap: 15px; color: #aaa; font-size: 14px;">
                <span>👤 <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                <a href="logout.php" style="background: #e74c3c; color: white; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-weight: bold;">ออกจากระบบ</a>
            </div>
        </div>
        <h1>⚙️ แดชบอร์ดผู้จัดการ</h1>
        <div class="dashboard-grid">
            <a href="stock_report.php" class="dashboard-card" style="--primary: #2ecc71;"><h3>📦 จัดการสต็อก</h3><p>เพิ่ม/แก้ไขสินค้า, อัปเดตยอดคงเหลือ, และดูความเคลื่อนไหวของสต็อก</p></a>
            <a href="promotions.php" class="dashboard-card" style="--primary: #ff9800;"><h3>🎁 จัดการโปรโมชั่น</h3><p>สร้างโปรโมชั่นลดราคา, ซื้อ X แถม Y, และกำหนดเวลาเปิด-ปิดโปรโมชั่น</p></a>
            <a href="users.php" class="dashboard-card" style="--primary: #3498db;"><h3>👥 จัดการพนักงาน</h3><p>เพิ่ม/ลบบัญชีพนักงาน, เปลี่ยนรหัสผ่าน, และกำหนดสิทธิ์การใช้งาน</p></a>
            <a href="categories.php" class="dashboard-card" style="--primary: #9b59b6;"><h3>📂 จัดการหมวดหมู่</h3><p>แก้ไขหรือลบชื่อหมวดหมู่สินค้าทั้งหมดในระบบ</p></a>
        </div>
    </div>
</body>
</html>