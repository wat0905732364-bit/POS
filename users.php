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
    <title>จัดการพนักงาน - FROG POS</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #00d4ff; --bg: #0f111a; --card: #1a1d29; }
        body { font-family: 'Sarabun', sans-serif; background: var(--bg); color: white; padding: 20px; margin: 0; }
        .container { max-width: 1000px; margin: auto; }
        .nav-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding: 10px 0; border-bottom: 1px solid #333; }
        .nav-links a { color: #aaa; text-decoration: none; margin-left: 20px; transition: 0.3s; }
        .nav-links a:hover { color: var(--primary); }
        .nav-links a.active { color: var(--primary); font-weight: bold; }
        
        .card { background: var(--card); padding: 25px; border-radius: 18px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); border: 1px solid #252a3a; margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; }
        th { color: var(--primary); text-align: left; padding: 15px; border-bottom: 2px solid #252a3a; }
        td { padding: 15px; border-bottom: 1px solid #252a3a; }
        
        .btn-add { background: #2ecc71; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: bold; font-size: 15px; }
        .btn-edit { background: #f1c40f; color: black; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: bold; }
        .btn-delete { background: #e74c3c; color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: bold; }
        
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; justify-content: center; align-items: center; }
        .modal-box { background: var(--card); padding: 25px; border-radius: 15px; width: 400px; border: 1px solid #333; }
        .input-group { margin-bottom: 15px; }
        .input-group label { display: block; margin-bottom: 5px; color: #aaa; font-size: 14px; }
        .input-group input, .input-group select { width: 100%; padding: 10px; background: #0f111a; border: 1px solid #444; color: white; border-radius: 8px; box-sizing: border-box; }
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        .btn-save { background: var(--primary); color: black; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: bold; }
        .btn-cancel { background: #444; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; }
    </style>
    <script>
        async function refreshTableData() {
            const response = await fetch(window.location.href);
            const text = await response.text();
            const doc = new DOMParser().parseFromString(text, 'text/html');
            document.getElementById('users-table-container').innerHTML = doc.getElementById('users-table-container').innerHTML;
        }

        function openPasswordModal(id, name) {
            document.getElementById('pwd_user_id').value = id;
            document.getElementById('pwd_display_name').innerText = name;
            document.getElementById('new_password').value = '';
            document.getElementById('passwordModal').style.display = 'flex';
        }
        
        function openAddUserModal() {
            document.getElementById('addUserForm').reset();
            document.getElementById('addUserModal').style.display = 'flex';
        }

        async function changePassword() {
            const pwd = document.getElementById('new_password').value;
            if (!pwd || pwd.length < 4) { alert("รหัสผ่านต้องมีอย่างน้อย 4 ตัวอักษร"); return; }
            
            const formData = new FormData(document.getElementById('changePwdForm'));
            formData.append('action', 'change_password');
            const response = await fetch('pos_action.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.success) { 
                alert("เปลี่ยนรหัสผ่านสำเร็จ!"); 
                document.getElementById('passwordModal').style.display = 'none';
            }
        }

        async function addUser() {
            const form = document.getElementById('addUserForm');
            const formData = new FormData(form);
            formData.append('action', 'add_user');
            const response = await fetch('pos_action.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.success) { 
                document.getElementById('addUserModal').style.display = 'none';
                await refreshTableData(); 
            } else { 
                alert("ชื่อผู้ใช้งาน (Username) นี้อาจมีคนใช้แล้ว"); 
            }
        }

        async function deleteUser(id) {
            if (!confirm("ยืนยันการลบพนักงานคนนี้?")) return;
            const formData = new FormData();
            formData.append('action', 'delete_user');
            formData.append('user_id', id);
            await fetch('pos_action.php', { method: 'POST', body: formData });
            await refreshTableData();
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="nav-bar">
            <h2 style="margin:0; color:var(--primary);">FROG POS</h2>
            <div class="nav-links">
                <a href="index.php">หน้าขาย</a>
                <a href="sales_report.php">รายงาน</a>
                <a href="dashboard.php">แดชบอร์ดจัดการ</a>
            </div>
            <div style="display: flex; align-items: center; gap: 15px; color: #aaa; font-size: 14px;">
                <span>👤 <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                <a href="logout.php" style="background: #e74c3c; color: white; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-weight: bold;">ออกจากระบบ</a>
            </div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h1>👥 จัดการพนักงาน</h1>
            <button class="btn-add" onclick="openAddUserModal()">+ เพิ่มพนักงาน</button>
        </div>

        <div class="card" id="users-table-container">
            <table>
                <thead>
                    <tr>
                        <th>ชื่อ-นามสกุล</th>
                        <th>Username (สำหรับล็อกอิน)</th>
                        <th>ตำแหน่ง</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $res = $conn->query("SELECT * FROM users ORDER BY role DESC, name ASC");
                    while($u = $res->fetch_assoc()):
                        $role_text = $u['role'] === 'manager' ? '<span style="color:#f1c40f;">ผู้จัดการ</span>' : 'แคชเชียร์';
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($u['name']); ?></td>
                        <td><?php echo htmlspecialchars($u['username']); ?></td>
                        <td><?php echo $role_text; ?></td>
                        <td>
                            <button class="btn-edit" onclick="openPasswordModal(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['name']); ?>')">🔑 เปลี่ยนรหัสผ่าน</button>
                            <?php if($u['id'] !== $_SESSION['user_id']): ?>
                                <button class="btn-delete" onclick="deleteUser(<?php echo $u['id']; ?>)">ลบ</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal เปลี่ยนรหัสผ่าน -->
    <div id="passwordModal" class="modal-overlay">
        <div class="modal-box">
            <h2 style="color: #f1c40f; margin-top:0;">🔑 เปลี่ยนรหัสผ่าน</h2>
            <p style="color:#aaa; font-size:14px;">พนักงาน: <strong id="pwd_display_name" style="color:white;"></strong></p>
            <form id="changePwdForm">
                <input type="hidden" name="user_id" id="pwd_user_id">
                <div class="input-group"><label>รหัสผ่านใหม่:</label><input type="password" name="new_password" id="new_password" placeholder="อย่างน้อย 4 ตัวอักษร"></div>
            </form>
            <div class="modal-footer"><button class="btn-cancel" onclick="document.getElementById('passwordModal').style.display='none'">ยกเลิก</button><button class="btn-save" onclick="changePassword()">บันทึก</button></div>
        </div>
    </div>

    <!-- Modal เพิ่มพนักงาน -->
    <div id="addUserModal" class="modal-overlay">
        <div class="modal-box">
            <h2 style="color: #2ecc71; margin-top:0;">➕ เพิ่มพนักงานใหม่</h2>
            <form id="addUserForm">
                <div class="input-group"><label>ชื่อ-นามสกุล / ชื่อเล่น:</label><input type="text" name="name" required placeholder="เช่น พนักงาน เอ"></div>
                <div class="input-group"><label>Username (ใช้ล็อกอิน):</label><input type="text" name="username" required placeholder="เช่น staff_a (ห้ามซ้ำกับคนอื่น)"></div>
                <div class="input-group"><label>รหัสผ่าน:</label><input type="password" name="password" required placeholder="ตั้งรหัสผ่าน"></div>
                <div class="input-group">
                    <label>สิทธิ์การใช้งาน (Role):</label>
                    <select name="role">
                        <option value="cashier">พนักงานขาย (แคชเชียร์)</option>
                        <option value="manager">ผู้จัดการร้าน (เข้าถึงได้ทุกหน้า)</option>
                    </select>
                </div>
            </form>
            <div class="modal-footer"><button class="btn-cancel" onclick="document.getElementById('addUserModal').style.display='none'">ยกเลิก</button><button class="btn-save" onclick="addUser()">เพิ่มพนักงาน</button></div>
        </div>
    </div>
</body>
</html>