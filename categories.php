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
    <title>จัดการหมวดหมู่ - FROG POS</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #9b59b6; --bg: #0f111a; --card: #1a1d29; }
        body { font-family: 'Sarabun', sans-serif; background: var(--bg); color: white; padding: 20px; margin: 0; }
        .container { max-width: 800px; margin: auto; }
        .nav-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding: 10px 0; border-bottom: 1px solid #333; }
        .nav-links a { color: #aaa; text-decoration: none; margin-left: 20px; transition: 0.3s; }
        .nav-links a:hover { color: #00d4ff; }
        .nav-links a.active { color: var(--primary); font-weight: bold; }
        
        .card { background: var(--card); padding: 25px; border-radius: 18px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); border: 1px solid #252a3a; margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; }
        th { color: var(--primary); text-align: left; padding: 15px; border-bottom: 2px solid #252a3a; }
        td { padding: 15px; border-bottom: 1px solid #252a3a; }
        
        .btn-add { background: var(--primary); color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: bold; font-size: 15px; }
        .btn-edit { background: #f1c40f; color: black; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: bold; }
        .btn-delete { background: #e74c3c; color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: bold; }
        
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; justify-content: center; align-items: center; }
        .modal-box { background: var(--card); padding: 25px; border-radius: 15px; width: 400px; border: 1px solid #333; }
        .input-group { margin-bottom: 15px; }
        .input-group label { display: block; margin-bottom: 5px; color: #aaa; font-size: 14px; }
        .input-group input { width: 100%; padding: 10px; background: #0f111a; border: 1px solid #444; color: white; border-radius: 8px; box-sizing: border-box; }
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        .btn-save { background: var(--primary); color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: bold; }
        .btn-cancel { background: #444; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; }
    </style>
    <script>
        async function refreshTableData() {
            const response = await fetch(window.location.href);
            const text = await response.text();
            const doc = new DOMParser().parseFromString(text, 'text/html');
            document.getElementById('category-table-container').innerHTML = doc.getElementById('category-table-container').innerHTML;
        }

        function openCategoryModal(oldName = '') {
            document.getElementById('old_category_name').value = oldName;
            document.getElementById('new_category_name').value = oldName;
            document.getElementById('modalTitle').innerText = oldName ? '✏️ แก้ไขชื่อหมวดหมู่' : '➕ เพิ่มหมวดหมู่ใหม่';
            document.getElementById('categoryModal').style.display = 'flex';
        }

        async function saveCategory() {
            const form = document.getElementById('categoryForm');
            const formData = new FormData(form);
            formData.append('action', 'save_category');
            
            const response = await fetch('pos_action.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.success) {
                document.getElementById('categoryModal').style.display = 'none';
                await refreshTableData();
            } else {
                alert("เกิดข้อผิดพลาด: " + (result.error || 'ไม่สามารถบันทึกได้'));
            }
        }

        async function deleteCategory(categoryName) {
            if (!confirm(`ยืนยันการลบหมวดหมู่ "${categoryName}"?\n(สินค้าในหมวดหมู่นี้จะถูกเปลี่ยนเป็น "ไม่มีหมวดหมู่")`)) return;
            const formData = new FormData();
            formData.append('action', 'delete_category');
            formData.append('category_name', categoryName);
            await fetch('pos_action.php', { method: 'POST', body: formData });
            await refreshTableData();
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="nav-bar">
            <h2 style="margin:0; color:#00d4ff;">FROG POS</h2>
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
            <h1>📂 จัดการหมวดหมู่สินค้า</h1>
            <button class="btn-add" onclick="openCategoryModal()">+ เพิ่มหมวดหมู่</button>
        </div>

        <div class="card" id="category-table-container">
            <table>
                <thead>
                    <tr>
                        <th>ชื่อหมวดหมู่</th>
                        <th style="text-align: center;">จำนวนสินค้าในหมวด</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT category, COUNT(id) as item_count FROM products WHERE category IS NOT NULL AND category != '' GROUP BY category ORDER BY category ASC";
                    $res = $conn->query($sql);
                    if ($res && $res->num_rows > 0) {
                        while($cat = $res->fetch_assoc()):
                            $cat_name = htmlspecialchars($cat['category']);
                    ?>
                    <tr>
                        <td><?php echo $cat_name; ?></td>
                        <td style="text-align: center;"><?php echo $cat['item_count']; ?> รายการ</td>
                        <td>
                            <button class="btn-edit" onclick="openCategoryModal('<?php echo $cat_name; ?>')">✏️ แก้ไขชื่อ</button>
                            <button class="btn-delete" onclick="deleteCategory('<?php echo $cat_name; ?>')">🗑️ ลบ</button>
                        </td>
                    </tr>
                    <?php 
                        endwhile;
                    } else {
                        echo "<tr><td colspan='3' style='text-align:center;'>ยังไม่มีหมวดหมู่ในระบบ</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal เพิ่ม/แก้ไขหมวดหมู่ -->
    <div id="categoryModal" class="modal-overlay">
        <div class="modal-box">
            <h2 id="modalTitle" style="color: var(--primary); margin-top:0;">จัดการหมวดหมู่</h2>
            <form id="categoryForm">
                <input type="hidden" name="old_category_name" id="old_category_name">
                <div class="input-group">
                    <label>ชื่อหมวดหมู่ใหม่:</label>
                    <input type="text" name="new_category_name" id="new_category_name" required placeholder="เช่น Snack, Appetizer">
                </div>
            </form>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="document.getElementById('categoryModal').style.display='none'">ยกเลิก</button>
                <button class="btn-save" onclick="saveCategory()">บันทึก</button>
            </div>
        </div>
    </div>
</body>
</html>