<?php
require 'config.php';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการสต็อกสินค้า - Bar POS</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #00d4ff; --success: #2ecc71; --danger: #ff4d4d; --bg: #0f111a; --card: #1a1d29; }
        body { font-family: 'Sarabun', sans-serif; background: var(--bg); color: white; padding: 20px; margin: 0; }
        .container { max-width: 1000px; margin: auto; }
        
        /* Header & Navigation */
        .nav-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding: 10px 0; border-bottom: 1px solid #333; }
        .nav-links a { color: #aaa; text-decoration: none; margin-left: 20px; transition: 0.3s; }
        .nav-links a:hover { color: var(--primary); }
        .nav-links a.active { color: var(--primary); font-weight: bold; }

        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .page-header h1 { margin: 0; font-size: 28px; display: flex; align-items: center; gap: 10px; }
        
        /* Button Styles */
        .btn-add-main { background: var(--primary); color: #000; border: none; padding: 12px 24px; border-radius: 10px; font-weight: bold; cursor: pointer; transition: 0.3s; box-shadow: 0 4px 15px rgba(0,212,255,0.3); font-size: 16px; }
        .btn-add-main:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,212,255,0.4); }
        .btn-update { background: var(--success); color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 13px; transition: 0.2s; }
        .btn-update:hover { opacity: 0.8; }
        .btn-decrease { background: var(--danger); color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 13px; transition: 0.2s; margin-right: 5px; }
        .btn-decrease:hover { opacity: 0.8; }
        .btn-edit { background: #f1c40f; color: black; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 13px; transition: 0.2s; margin-left: 5px; font-weight: bold; }
        .btn-edit:hover { opacity: 0.8; }

        /* Table Styles */
        .card { background: var(--card); padding: 25px; border-radius: 18px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); margin-bottom: 30px; border: 1px solid #252a3a; }
        table { width: 100%; border-collapse: collapse; }
        th { color: var(--primary); text-align: left; padding: 15px; font-size: 14px; text-transform: uppercase; border-bottom: 2px solid #252a3a; }
        td { padding: 15px; border-bottom: 1px solid #252a3a; font-size: 15px; }
        tr:hover td { background: rgba(255,255,255,0.02); }
        
        .stock-input { background: #0f111a; border: 1px solid #333; color: white; padding: 8px; width: 70px; border-radius: 6px; text-align: center; margin-right: 5px; outline: none; }
        .stock-input:focus { border-color: var(--primary); }
        .low-stock { color: var(--danger); font-weight: bold; position: relative; }
        .low-stock::after { content: ''; font-size: 10px; margin-left: 5px; vertical-align: middle; }

        /* Modal Styles */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 1000; justify-content: center; align-items: center; backdrop-filter: blur(5px); }
        .modal-box { background: var(--card); padding: 35px; border-radius: 20px; width: 480px; border: 1px solid #333; animation: slideUp 0.3s ease-out; }
        @keyframes slideUp { from { transform: translateY(30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .modal-box h2 { margin-top: 0; color: var(--primary); margin-bottom: 25px; }
        .input-group { margin-bottom: 20px; }
        .input-group label { display: block; margin-bottom: 8px; color: #888; font-size: 13px; }
        .input-group input, .input-group select { width: 100%; padding: 12px; background: #0f111a; border: 1px solid #333; color: white; border-radius: 8px; box-sizing: border-box; font-size: 15px; }
        .modal-footer { display: flex; justify-content: flex-end; gap: 12px; margin-top: 30px; }
        .btn-save { background: var(--primary); color: #000; border: none; padding: 12px 25px; border-radius: 8px; font-weight: bold; cursor: pointer; }
        .btn-cancel { background: #333; color: white; border: none; padding: 12px 25px; border-radius: 8px; cursor: pointer; }
    </style>
    <script>
        async function updateStock(productId, actionType = 'add') {
            const qtyInput = document.getElementById('qty-' + productId);
            let qty = parseInt(qtyInput.value);
            if (isNaN(qty) || qty === 0) return;

            // ตรวจสอบว่ากดปุ่ม ลด หรือ เพิ่ม
            if (actionType === 'decrease') {
                qty = -Math.abs(qty); // แปลงค่าเป็นลบเพื่อนำไปหักสต็อก
            } else {
                qty = Math.abs(qty); // บังคับให้เป็นบวก
            }

            const formData = new FormData();
            formData.append('action', 'update_stock');
            formData.append('product_id', productId);
            formData.append('quantity', qty);

            try {
                const response = await fetch('pos_action.php', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) {
                    alert('อัปเดตสต็อกสำเร็จ');
                    location.reload();
                } else {
                    alert('เกิดข้อผิดพลาด');
                }
            } catch (e) {
                console.error(e);
            }
        }

        async function deleteProduct(productId) {
            if (!confirm('⚠️ ยืนยันการลบสินค้านี้ออกจากระบบอย่างถาวรหรือไม่?')) return;
            
            const formData = new FormData();
            formData.append('action', 'delete_product');
            formData.append('product_id', productId);

            try {
                const response = await fetch('pos_action.php', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) {
                    alert('🗑️ ลบสินค้าเรียบร้อยแล้ว');
                    location.reload();
                } else {
                    alert('เกิดข้อผิดพลาด: ' + (result.error || ''));
                }
            } catch (e) {
                console.error(e);
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
            }
        }

        function openAddModal() {
            document.getElementById('addModal').style.display = 'flex';
        }

        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }

        async function submitNewProduct() {
            const form = document.getElementById('addProductForm');
            const formData = new FormData(form);
            formData.append('action', 'add_product');

            try {
                const response = await fetch('pos_action.php', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) {
                    alert('เพิ่มสินค้าสำเร็จ');
                    location.reload();
                } else {
                    alert('เกิดข้อผิดพลาดในการบันทึกข้อมูล');
                }
            } catch (e) {
                console.error(e);
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
            }
        }

        function openEditModal(product) {
            document.getElementById('edit_id').value = product.id;
            document.getElementById('edit_name').value = product.name;
            document.getElementById('edit_category').value = product.category;
            document.getElementById('edit_price').value = product.price;
            document.getElementById('edit_stock_qty').value = product.stock_qty;
            document.getElementById('edit_open_ml').value = product.open_ml || 0;
            document.getElementById('edit_ml_per_unit').value = product.ml_per_unit;
            document.getElementById('edit_inventory_id').value = product.inventory_id || '';
            
            document.getElementById('editModal').style.display = 'flex';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        async function submitEditProduct() {
            const form = document.getElementById('editProductForm');
            const formData = new FormData(form);
            formData.append('action', 'edit_product');

            try {
                const response = await fetch('pos_action.php', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) {
                    alert('แก้ไขสินค้าสำเร็จ');
                    location.reload();
                } else {
                    alert('เกิดข้อผิดพลาด: ' + (result.error || ''));
                }
            } catch (e) {
                console.error(e);
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
            }
        }
    </script>
</head>
<body>
    <div class="container">
        <!-- Navigation Bar -->
        <div class="nav-bar">
            <h2 style="margin:0; color:var(--primary);">BAR POS</h2>
            <div class="nav-links">
                <a href="index.php">หน้าขาย (POS)</a>
                <a href="sales_report.php">รายงานยอดขาย</a>
                <a href="stock_report.php" class="active">จัดการสต็อก</a>
            </div>
        </div>

        <!-- Page Header with Primary Action -->
        <div class="page-header">
            <h1>📦 จัดการสต็อกสินค้า</h1>
            <div style="display:flex; gap:15px; align-items:center;">
                <a href="export_stock.php" style="color: var(--success); font-size:14px; text-decoration:none;">📥 Export Excel</a>
                <button class="btn-add-main" onclick="openAddModal()">+ เพิ่มสินค้าใหม่</button>
            </div>
        </div>
        
        <div class="card">
            <table>
            <thead>
                <tr>
                    <th>หมวดหมู่</th>
                    <th>ชื่อสินค้า</th>
                    <th style="text-align: center;">คงเหลือ (Units/ml)</th>
                    <th style="text-align: center;">ขวดเปิดใช้งาน (ml)</th>
                    <th style="text-align: center;">ปริมาณ/ชิ้น</th>
                    <th style="text-align: center;">เติมสต็อก</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sql = "SELECT p.*, inv.name as parent_name 
                        FROM products p 
                        LEFT JOIN products inv ON p.inventory_id = inv.id 
                        ORDER BY p.category ASC, p.name ASC";
                $result = $conn->query($sql);

                if ($result && $result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                        $stockClass = ($row['stock_qty'] <= 500 && $row['ml_per_unit'] > 0) ? 'low-stock' : (($row['stock_qty'] <= 5) ? 'low-stock' : '');
                        $displayName = $row['name'] . ($row['parent_name'] ? " <br><small style='color:#777'>(ตัดสต็อกจาก: {$row['parent_name']})</small>" : "");
                        
                        // เพิ่มข้อความในช่อง Input เพื่อเตือนพนักงานให้ใส่หน่วยที่ถูกต้อง
                        $placeholder = "จำนวน";
                        if ($row['inventory_id'] === null && $row['category'] !== 'Food') {
                            $placeholder = "ขวด/ชิ้น หรือ ml";
                        }
                        $safeRowData = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');

                        echo "<tr>";
                        echo "<td><small>" . htmlspecialchars($row['category']) . "</small></td>";
                        echo "<td>" . $displayName . "</td>";
                        echo "<td style='text-align: center;' class='$stockClass'>" . number_format($row['stock_qty']) . "</td>";
                        echo "<td style='text-align: center; color:#f1c40f; font-weight:bold;'>" . ($row['open_ml'] > 0 ? number_format($row['open_ml']) . " ml" : "-") . "</td>";
                        echo "<td style='text-align: center; color:#00d4ff;'>" . ($row['ml_per_unit'] > 0 ? $row['ml_per_unit'] . " ml" : "-") . "</td>";
                        echo "<td style='text-align: center; white-space: nowrap;'>
                                <input type='number' id='qty-{$row['id']}' class='stock-input' placeholder='$placeholder' value='0' min='0'>
                                <button onclick='deleteProduct({$row['id']})' class='btn-decrease' title='ลบสินค้านี้ออกจากระบบ'>ลบทิ้ง</button>
                                <button onclick='updateStock({$row['id']}, \"add\")' class='btn-update'>เพิ่ม</button>
                                <button onclick='openEditModal({$safeRowData})' class='btn-edit'>แก้ไข</button>
                              </td>";
                        echo "</tr>";
                    }
                } else {
                    echo '<tr><td colspan="4" style="text-align:center;">ไม่พบข้อมูลสินค้าในระบบ</td></tr>';
                }
                ?>
            </tbody>
        </table>

        <!-- ส่วนสรุปความเคลื่อนไหวรายวัน -->
        <h2>📈 ความเคลื่อนไหวสต็อกรายวัน (Daily Stock Movement)</h2>
        <table>
            <thead>
                <tr>
                    <th>วันที่</th>
                    <th>เติมเข้า (+)</th>
                    <th>ขายออก (-)</th>
                    <th>สถานะสุทธิ</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sql_move = "SELECT DATE(created_at) as move_date, 
                             SUM(CASE WHEN qty_change > 0 THEN qty_change ELSE 0 END) as total_in,
                             SUM(CASE WHEN qty_change < 0 THEN ABS(qty_change) ELSE 0 END) as total_out
                             FROM stock_logs 
                             GROUP BY DATE(created_at) ORDER BY move_date DESC LIMIT 7";
                $res_move = $conn->query($sql_move);
                while($m = $res_move->fetch_assoc()) {
                    $net = $m['total_in'] - $m['total_out'];
                    echo "<tr>";
                    echo "<td>" . date('d/m/Y', strtotime($m['move_date'])) . "</td>";
                    echo "<td style='color:#2ecc71;'>+" . $m['total_in'] . "</td>";
                    echo "<td style='color:#ff4d4d;'>-" . $m['total_out'] . "</td>";
                    echo "<td>" . ($net >= 0 ? "+$net" : "$net") . "</td>";
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>

        <!-- Modal สำหรับเพิ่มสินค้าใหม่ -->
        <div id="addModal" class="modal-overlay">
            <div class="modal-box">
                <h2>➕ เพิ่มสินค้าใหม่</h2>
                <form id="addProductForm">
                    <div class="input-group">
                        <label>ชื่อสินค้า:</label>
                        <input type="text" name="name" required placeholder="เช่น Wine Red (ขวด)">
                    </div>
                    <div class="input-group">
                        <label>หมวดหมู่:</label>
                        <input type="text" name="category" list="cat_list" required placeholder="เลือกหรือพิมพ์เพิ่มหมวดหมู่ใหม่...">
                        <datalist id="cat_list">
                            <option value="Liquor">
                            <option value="Wine">
                            <option value="Beer">
                            <option value="Cocktail">
                            <option value="Food">
                            <?php
                            $cat_res = $conn->query("SELECT DISTINCT category FROM products WHERE category NOT IN ('Beer','Wine','Cocktail','Food','Liquor') AND category IS NOT NULL AND category != ''");
                            if($cat_res) {
                                while($c = $cat_res->fetch_assoc()) echo "<option value='".htmlspecialchars($c['category'])."'>\n";
                            }
                            ?>
                        </datalist>
                    </div>
                    <div class="input-group">
                        <label>ราคาขาย (฿):</label>
                        <input type="number" name="price" step="0.01" required>
                    </div>
                    <div class="input-group">
                        <label>จำนวนสต็อกเริ่มต้น:</label>
                        <input type="number" name="stock_qty" value="0">
                    </div>
                    <div class="input-group">
                        <label>ปริมาณต่อหน่วย (ถ้าตัดเป็น ml):</label>
                        <input type="number" name="ml_per_unit" placeholder="เช่น 750 หรือ 30">
                    </div>
                    <div class="input-group">
                        <label>เชื่อมสต็อกกับสินค้าหลัก (ถ้ามี):</label>
                        <select name="inventory_id">
                            <option value="">-- เป็นสินค้าหลักเอง --</option>
                            <?php
                            $p_res = $conn->query("SELECT id, name FROM products WHERE inventory_id IS NULL ORDER BY name ASC");
                            while($p = $p_res->fetch_assoc()) echo "<option value='{$p['id']}'>{$p['name']}</option>";
                            ?>
                        </select>
                    </div>
                </form>
                <div class="modal-footer">
                    <button class="btn-cancel" onclick="closeAddModal()">ยกเลิก</button>
                    <button class="btn-save" onclick="submitNewProduct()">บันทึกสินค้า</button>
                </div>
            </div>
        </div>

        <!-- Modal สำหรับแก้ไขสินค้า -->
        <div id="editModal" class="modal-overlay">
            <div class="modal-box">
                <h2 style="color: #f1c40f;">✏️ แก้ไขข้อมูลสต็อกสินค้า</h2>
                <form id="editProductForm">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="input-group">
                        <label>ชื่อสินค้า:</label>
                        <input type="text" name="name" id="edit_name" required>
                    </div>
                    <div class="input-group">
                        <label>หมวดหมู่:</label>
                        <input type="text" name="category" id="edit_category" list="cat_list" required placeholder="เลือกหรือพิมพ์เพิ่มหมวดหมู่ใหม่...">
                    </div>
                    <div class="input-group">
                        <label>ราคาขาย (฿):</label>
                        <input type="number" name="price" id="edit_price" step="0.01" required>
                    </div>
                    <div class="input-group">
                        <label>แก้ไขยอดคงเหลือ (อัปเดตทับยอดเดิม):</label>
                        <input type="number" name="stock_qty" id="edit_stock_qty">
                    </div>
                    <div class="input-group">
                        <label>แก้ไขปริมาณขวดที่เปิดใช้งานแล้ว (ml):</label>
                        <input type="number" name="open_ml" id="edit_open_ml">
                    </div>
                    <div class="input-group">
                        <label>ปริมาณต่อหน่วย (ถ้าตัดเป็น ml):</label>
                        <input type="number" name="ml_per_unit" id="edit_ml_per_unit">
                    </div>
                    <div class="input-group">
                        <label>เชื่อมสต็อกกับสินค้าหลัก (ถ้ามี):</label>
                        <select name="inventory_id" id="edit_inventory_id">
                            <option value="">-- เป็นสินค้าหลักเอง --</option>
                            <?php
                            $p_res = $conn->query("SELECT id, name FROM products WHERE inventory_id IS NULL ORDER BY name ASC");
                            while($p = $p_res->fetch_assoc()) echo "<option value='{$p['id']}'>{$p['name']}</option>";
                            ?>
                        </select>
                    </div>
                </form>
                <div class="modal-footer">
                    <button class="btn-cancel" onclick="closeEditModal()">ยกเลิก</button>
                    <button class="btn-save" style="background: #f1c40f; color: black;" onclick="submitEditProduct()">บันทึกการแก้ไข</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>