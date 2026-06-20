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

// อัพเดทฐานข้อมูลเพิ่ม column 'unit' อัตโนมัติ (เพื่อรองรับการบันทึก ml/ขวด แยกกัน)
$chk = $conn->query("SHOW COLUMNS FROM stock_logs LIKE 'unit'");
if ($chk && $chk->num_rows == 0) {
    $conn->query("ALTER TABLE stock_logs ADD unit VARCHAR(20) NOT NULL DEFAULT 'unit' AFTER qty_change");
}

// อัพเดทฐานข้อมูลเพิ่ม column 'cost_price' อัตโนมัติ
$chk_cost = $conn->query("SHOW COLUMNS FROM products LIKE 'cost_price'");
if ($chk_cost && $chk_cost->num_rows == 0) {
    $conn->query("ALTER TABLE products ADD cost_price DECIMAL(10,2) DEFAULT 0.00 AFTER price");
}

// อัพเดทฐานข้อมูลเพิ่ม column 'notes' สำหรับบันทึกรายละเอียดเพิ่มเติม
$chk_notes = $conn->query("SHOW COLUMNS FROM stock_logs LIKE 'notes'");
if ($chk_notes && $chk_notes->num_rows == 0) {
    $conn->query("ALTER TABLE stock_logs ADD notes VARCHAR(255) NULL AFTER type");
}
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
        .modal-box { background: var(--card); padding: 25px; border-radius: 20px; width: 480px; max-height: 85vh; overflow-y: auto; border: 1px solid #333; animation: slideUp 0.3s ease-out; }
        .modal-box::-webkit-scrollbar { width: 6px; }
        .modal-box::-webkit-scrollbar-track { background: var(--card); border-radius: 10px; }
        .modal-box::-webkit-scrollbar-thumb { background: #444; border-radius: 10px; }
        .modal-box::-webkit-scrollbar-thumb:hover { background: var(--primary); }
        @keyframes slideUp { from { transform: translateY(30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .modal-box h2 { margin-top: 0; color: var(--primary); margin-bottom: 15px; }
        .input-group { margin-bottom: 15px; }
        .input-group label { display: block; margin-bottom: 8px; color: #888; font-size: 13px; }
        .input-group input, .input-group select { width: 100%; padding: 10px; background: #0f111a; border: 1px solid #333; color: white; border-radius: 8px; box-sizing: border-box; font-size: 15px; }
        .modal-footer { display: flex; justify-content: flex-end; gap: 12px; margin-top: 20px; }
        .btn-save { background: var(--primary); color: #000; border: none; padding: 12px 25px; border-radius: 8px; font-weight: bold; cursor: pointer; }
        .btn-cancel { background: #333; color: white; border: none; padding: 12px 25px; border-radius: 8px; cursor: pointer; }
        
        /* Detail Modal Styles */
        .detail-table { width: 100%; text-align: left; border-collapse: collapse; margin-top: 15px; }
        .detail-table th { background: #252a3a; padding: 10px; font-size: 13px; color: var(--primary); border-bottom: none; }
        .detail-table td { padding: 10px; border-bottom: 1px solid #252a3a; font-size: 14px; }
        .qty-in { color: var(--success); font-weight: bold; }
        .qty-out { color: var(--danger); font-weight: bold; }
    </style>
    <script>
        // ฟังก์ชันอัปเดตตารางโดยไม่ต้องรีเฟรชหน้า
        async function refreshTableData() {
            const response = await fetch(window.location.href);
            const text = await response.text();
            const doc = new DOMParser().parseFromString(text, 'text/html');
            document.getElementById('stock-table-container').innerHTML = doc.getElementById('stock-table-container').innerHTML;
            document.getElementById('movement-table-container').innerHTML = doc.getElementById('movement-table-container').innerHTML;
        }

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
                    qtyInput.value = '0';
                    await refreshTableData();
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
                    await refreshTableData();
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
                    closeAddModal();
                    document.getElementById('addProductForm').reset();
                    await refreshTableData();
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
            document.getElementById('edit_cost_price').value = product.cost_price || '0.00';
            document.getElementById('edit_stock_qty').value = product.stock_qty;
            document.getElementById('edit_open_ml').value = product.open_ml || 0;
            document.getElementById('edit_ml_per_unit').value = product.ml_per_unit;
            document.getElementById('edit_inventory_id').value = product.inventory_id || '';
            document.getElementById('edit_show_on_pos').value = product.show_on_pos !== undefined ? product.show_on_pos : 1;
            
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
                    closeEditModal();
                    await refreshTableData();
                } else {
                    alert('เกิดข้อผิดพลาด: ' + (result.error || ''));
                }
            } catch (e) {
                console.error(e);
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
            }
        }

        async function viewStockDetail(date) {
            document.getElementById('detailModalDate').innerText = date;
            const tableBody = document.getElementById('detail-table-body');
            tableBody.innerHTML = '<tr><td colspan="4" style="text-align:center;">กำลังโหลด...</td></tr>';
            document.getElementById('detailModal').style.display = 'flex';

            const formData = new FormData();
            formData.append('action', 'get_daily_stock_details');
            formData.append('date', date);

            try {
                const response = await fetch('pos_action.php', { method: 'POST', body: formData });
                const result = await response.json();
                
                tableBody.innerHTML = '';
                if (result.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="4" style="text-align:center;">ไม่มีข้อมูลในวันนี้</td></tr>';
                } else {
                    let totalInUnit = 0, totalOutUnit = 0, totalOutMl = 0, costChanges = 0;
                    result.forEach(row => {
                        const isOut = parseInt(row.qty_change) < 0;
                        let qtyText = '';
                        if (row.unit === 'cost') {
                            qtyText = `<span style="color:#f39c12;">เปลี่ยนต้นทุน</span>`;
                            costChanges += parseFloat(row.qty_change);
                        } else {
                            qtyText = (isOut ? '' : '+') + row.qty_change + ' ' + (row.unit === 'ml' ? 'ml' : 'ชิ้น/ขวด');
                        }

                        const qtyClass = isOut ? 'qty-out' : 'qty-in';
                        
                        if (!isOut && row.unit === 'unit') totalInUnit += parseInt(row.qty_change);
                        if (isOut && row.unit === 'unit') totalOutUnit += Math.abs(parseInt(row.qty_change));
                        if (isOut && row.unit === 'ml') totalOutMl += Math.abs(parseInt(row.qty_change));

                        let typeText = row.notes ? row.notes : (row.type === 'sale' ? 'ตัด/ลดสต็อก' : 'เพิ่ม/เติมสต็อก');
                        const timeMatch = row.created_at.match(/ (\d{2}:\d{2})/);
                        const time = timeMatch ? timeMatch[1] : '-';
                        const productName = row.name ? row.name : `<span style="color:#aaa; font-style:italic;">[สินค้าถูกลบ (ID: ${row.product_id})]</span>`;

                        tableBody.innerHTML += `<tr><td>${time}</td><td>${productName}</td><td><span class="${qtyClass}">${qtyText}</span></td><td><small style="color:#aaa;">${typeText}</small></td></tr>`;
                    });
                    document.getElementById('summary-detail').innerHTML = `<b>สรุปวันนี้:</b> เติมเข้า <span class="qty-in">+${totalInUnit} ชิ้น</span> | ขายออก <span class="qty-out">-${totalOutUnit} ชิ้น</span> และขายออก <span class="qty-out">-${totalOutMl} ml</span>`;
                }
            } catch (e) { console.error(e); tableBody.innerHTML = '<tr><td colspan="4" style="text-align:center; color:red;">เกิดข้อผิดพลาดในการดึงข้อมูล</td></tr>'; }
        }
        function closeDetailModal() { document.getElementById('detailModal').style.display = 'none'; }
    </script>
</head>
<body>
    <div class="container">
        <!-- Navigation Bar -->
        <div class="nav-bar">
            <h2 style="margin:0; color:var(--primary);">BAR POS</h2>
            <div class="nav-links">
                <a href="index.php">หน้าขาย</a>
                <a href="sales_report.php">รายงาน</a>
                <a href="dashboard.php">แดชบอร์ดจัดการ</a>
            </div>
            <div style="display: flex; align-items: center; gap: 15px; color: #aaa; font-size: 14px;">
                <span>👤 <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                <a href="logout.php" style="background: #e74c3c; color: white; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 13px; transition: 0.2s;">ออกจากระบบ</a>
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
        
        <div class="card" id="stock-table-container">
            <table>
            <thead>
                <tr>
                    <th>หมวดหมู่</th>
                    <th>ชื่อสินค้า</th>
                    <th>ราคาขาย / <span style="color:#f1c40f;">ต้นทุน</span></th>
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
                        if (isset($row['show_on_pos']) && $row['show_on_pos'] == 0) {
                            $displayName .= " <br><span style='background:#ff4d4d; color:white; padding:2px 5px; border-radius:3px; font-size:10px;'>ซ่อนจาก POS</span>";
                        }

                        echo "<tr>";
                        echo "<td><small>" . htmlspecialchars($row['category']) . "</small></td>";
                        echo "<td>" . $displayName . "</td>";                        
                        echo "<td>฿" . number_format($row['price'], 2) . "<br><span style='color:#f1c40f; font-size:13px;'>ต้นทุน: ฿" . number_format($row['cost_price'], 2) . "</span></td>";
                        echo "<td style='text-align: center;' class='$stockClass'>" . number_format($row['stock_qty']) . "</td>";
                        echo "<td style='text-align: center; color:#f1c40f; font-weight:bold;'>" . ($row['open_ml'] > 0 ? number_format($row['open_ml']) . " ml" : "-") . "</td>";
                        echo "<td style='text-align: center; color:#00d4ff;'>" . ($row['ml_per_unit'] > 0 ? $row['ml_per_unit'] . " ml" : "-") . "</td>";
                        echo "<td style='text-align: center; white-space: nowrap;'>
                                <input type='number' id='qty-{$row['id']}' class='stock-input' placeholder='จำนวน' value='0' min='0'>
                                <button onclick='deleteProduct({$row['id']})' class='btn-decrease' title='ลบสินค้านี้ออกจากระบบ'>ลบทิ้ง</button>
                                <button onclick='updateStock({$row['id']}, \"add\")' class='btn-update'>เพิ่ม</button>
                                <button onclick='openEditModal({$safeRowData})' class='btn-edit'>แก้ไข</button>
                              </td>";
                        echo "</tr>";
                    }
                } else {
                    echo '<tr><td colspan="7" style="text-align:center;">ไม่พบข้อมูลสินค้าในระบบ</td></tr>';
                }
                ?>
            </tbody>
        </table>

        <!-- ส่วนสรุปความเคลื่อนไหวรายวัน -->
        <h2>📈 ความเคลื่อนไหวสต็อกรายวัน (Daily Stock Movement)</h2>
        <div id="movement-table-container">
            <table>
            <thead>
                <tr>
                    <th>วันที่</th>
                    <th>เติมเข้า (+)</th>
                    <th>ขายออก (-)</th>
                    <th>ขายออกแบบเท (ml)</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sql_move = "SELECT DATE(created_at) as move_date, 
                             SUM(CASE WHEN qty_change > 0 AND unit = 'unit' THEN qty_change ELSE 0 END) as total_in_unit,
                             SUM(CASE WHEN qty_change < 0 AND unit = 'unit' THEN ABS(qty_change) ELSE 0 END) as total_out_unit,
                             SUM(CASE WHEN qty_change < 0 AND unit = 'ml' THEN ABS(qty_change) ELSE 0 END) as total_out_ml
                             FROM stock_logs 
                             GROUP BY DATE(created_at) ORDER BY move_date DESC LIMIT 7";
                $res_move = $conn->query($sql_move);
                if($res_move) {
                    while($m = $res_move->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>" . date('d/m/Y', strtotime($m['move_date'])) . "</td>";
                        echo "<td style='color:#2ecc71;'>+" . $m['total_in_unit'] . " ชิ้น</td>";
                        echo "<td style='color:#ff4d4d;'>-" . $m['total_out_unit'] . " ชิ้น</td>";
                        echo "<td style='color:#f1c40f;'>" . ($m['total_out_ml'] > 0 ? "-".$m['total_out_ml']." ml" : "-") . "</td>";
                        echo "<td><button onclick='viewStockDetail(\"{$m['move_date']}\")' class='btn-edit' style='background:#3498db; color:white;'>🔍 ดูรายละเอียด</button></td>";
                        echo "</tr>";
                    }
                }
                ?>
            </tbody>
            </table>
        </div>

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
                        <label>ราคาต้นทุน/หน่วย (฿):</label>
                        <input type="number" name="cost_price" step="0.01" value="0.00" required>
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
                    <div class="input-group">
                        <label>แสดงบนหน้าขาย (POS) หรือไม่?:</label>
                        <select name="show_on_pos">
                            <option value="1">✅ แสดงเป็นเมนูให้กดขาย</option>
                            <option value="0">❌ ซ่อนไว้ (ใช้เป็นสต็อกหลักอย่างเดียว)</option>
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
                        <label>ราคาต้นทุน/หน่วย (฿):</label>
                        <input type="number" name="cost_price" id="edit_cost_price" step="0.01" required>
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
                    <div class="input-group">
                        <label>แสดงบนหน้าขาย (POS) หรือไม่?:</label>
                        <select name="show_on_pos" id="edit_show_on_pos">
                            <option value="1">✅ แสดงเป็นเมนูให้กดขาย</option>
                            <option value="0">❌ ซ่อนไว้ (ใช้เป็นสต็อกหลักอย่างเดียว)</option>
                        </select>
                    </div>
                </form>
                <div class="modal-footer">
                    <button class="btn-cancel" onclick="closeEditModal()">ยกเลิก</button>
                    <button class="btn-save" style="background: #f1c40f; color: black;" onclick="submitEditProduct()">บันทึกการแก้ไข</button>
                </div>
            </div>
        </div>
        
        <!-- Modal สำหรับดูรายละเอียดความเคลื่อนไหวสต็อกรายวัน -->
        <div id="detailModal" class="modal-overlay">
            <div class="modal-box" style="width: 600px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 15px;">
                    <h2 style="margin:0;">📅 รายละเอียดสต็อก: <span id="detailModalDate"></span></h2>
                    <button class="btn-cancel" style="padding: 5px 10px;" onclick="closeDetailModal()">ปิด</button>
                </div>
                <div id="summary-detail" style="background:#252a3a; padding: 10px; border-radius: 8px; font-size:14px; margin-bottom: 15px;"></div>
                <div style="max-height: 400px; overflow-y: auto;">
                    <table class="detail-table">
                        <thead>
                            <tr>
                                <th width="15%">เวลา</th>
                                <th width="45%">สินค้า</th>
                                <th width="20%">เข้า/ออก</th>
                                <th width="20%">ประเภท</th>
                            </tr>
                        </thead>
                        <tbody id="detail-table-body"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>