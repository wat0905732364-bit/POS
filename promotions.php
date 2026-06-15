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
    <title>จัดการโปรโมชั่น - Bar POS</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #ff9800; --success: #2ecc71; --danger: #ff4d4d; --bg: #0f111a; --card: #1a1d29; }
        body { font-family: 'Sarabun', sans-serif; background: var(--bg); color: white; padding: 20px; margin: 0; }
        .container { max-width: 1000px; margin: auto; }
        
        .nav-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding: 10px 0; border-bottom: 1px solid #333; }
        .nav-links a { color: #aaa; text-decoration: none; margin-left: 20px; transition: 0.3s; }
        .nav-links a:hover { color: #00d4ff; }
        .nav-links a.active { color: var(--primary); font-weight: bold; }

        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .btn-add-main { background: var(--primary); color: white; border: none; padding: 12px 24px; border-radius: 10px; font-weight: bold; cursor: pointer; transition: 0.3s; font-size: 16px; }
        .btn-add-main:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(255,152,0,0.4); }

        .card { background: var(--card); padding: 25px; border-radius: 18px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); border: 1px solid #252a3a; }
        table { width: 100%; border-collapse: collapse; }
        th { color: var(--primary); text-align: left; padding: 15px; font-size: 14px; border-bottom: 2px solid #252a3a; }
        td { padding: 15px; border-bottom: 1px solid #252a3a; font-size: 15px; }
        tr:hover td { background: rgba(255,255,255,0.02); }
        
        /* Switch Toggle */
        .switch { position: relative; display: inline-block; width: 44px; height: 24px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #555; transition: .4s; border-radius: 24px; }
        .slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: var(--success); }
        input:checked + .slider:before { transform: translateX(20px); }

        /* Modal */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 1000; justify-content: center; align-items: center; }
        .modal-box { background: var(--card); padding: 35px; border-radius: 20px; width: 500px; border: 1px solid #333; }
        .input-group { margin-bottom: 15px; }
        .input-group label { display: block; margin-bottom: 8px; color: #aaa; font-size: 13px; }
        .input-group input, .input-group select { width: 100%; padding: 10px; background: #0f111a; border: 1px solid #333; color: white; border-radius: 8px; box-sizing: border-box; }
        .modal-footer { display: flex; justify-content: flex-end; gap: 12px; margin-top: 25px; }
        .btn-save { background: var(--primary); color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: bold; }
        .btn-cancel { background: #333; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; }
        .btn-edit { background: #3498db; color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 13px; }
        .btn-delete { background: var(--danger); color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 13px; }
    </style>
    <script>
        function togglePromoType() {
            const type = document.getElementById('promo_type').value;
            document.getElementById('div_percent').style.display = type === 'discount_percent' ? 'block' : 'none';
            document.getElementById('div_bogo').style.display = type === 'buy_x_get_y' ? 'block' : 'none';
        }

        async function refreshTableData() {
            const response = await fetch(window.location.href);
            const text = await response.text();
            const doc = new DOMParser().parseFromString(text, 'text/html');
            document.getElementById('promo-table-container').innerHTML = doc.getElementById('promo-table-container').innerHTML;
        }

        // ดึงรายการสินค้าตามหมวดหมู่มาใส่ใน Dropdown
        async function updateTargetItems(selectedItemsStr = '') {
            let selectedItems = [];
            if (selectedItemsStr) {
                try { selectedItems = JSON.parse(selectedItemsStr); }
                catch(e) { selectedItems = [selectedItemsStr]; } // รองรับข้อมูลเก่าที่เป็น string ธรรมดา
            }

            const category = document.getElementById('target_category').value;
            const itemContainer = document.getElementById('target_item_container');
            
            itemContainer.innerHTML = '<div style="color: #aaa; padding: 5px;">กำลังโหลดข้อมูล...</div>';
            
            const formData = new FormData();
            formData.append('action', 'get_products');
            formData.append('category', category);

            try {
                const response = await fetch('pos_action.php', { method: 'POST', body: formData });
                const products = await response.json();
                
                itemContainer.innerHTML = '';
                if (products.length === 0) {
                    itemContainer.innerHTML = '<div style="color: #aaa; padding: 5px;">-- ไม่มีสินค้าในหมวดหมู่นี้ --</div>';
                } else {
                    products.forEach(p => {
                        const isChecked = selectedItems.includes(p.name) ? 'checked' : '';
                        itemContainer.innerHTML += `
                            <label style="display: block; padding: 8px 10px; color: #fff; cursor: pointer; border-bottom: 1px solid #252a3a; font-weight: normal; margin-bottom: 0;">
                                <input type="checkbox" name="target_item[]" value="${p.name}" ${isChecked} style="width: auto; margin-right: 10px; transform: scale(1.2);">
                                ${p.name}
                            </label>
                        `;
                    });
                }
            } catch (e) {
                console.error(e);
                itemContainer.innerHTML = '<div style="color: #ff4d4d; padding: 5px;">โหลดข้อมูลล้มเหลว</div>';
            }
        }

        async function toggleActive(id, isChecked) {
            const formData = new FormData();
            formData.append('action', 'toggle_promotion');
            formData.append('id', id);
            formData.append('is_active', isChecked ? 1 : 0);
            await fetch('pos_action.php', { method: 'POST', body: formData });
        }

        async function deletePromotion(id) {
            if(!confirm("ยืนยันการลบโปรโมชั่นนี้?")) return;
            const formData = new FormData();
            formData.append('action', 'delete_promotion');
            formData.append('id', id);
            await fetch('pos_action.php', { method: 'POST', body: formData });
            await refreshTableData();
        }

        async function openAddModal() {
            document.getElementById('promoForm').reset();
            document.getElementById('promo_id').value = '';
            document.getElementById('modalTitle').innerText = '✨ เพิ่มโปรโมชั่นใหม่';
            togglePromoType();
            await updateTargetItems();
            document.getElementById('promoModal').style.display = 'flex';
        }

        async function openEditModal(promo) {
            document.getElementById('promo_id').value = promo.id;
            document.getElementById('name').value = promo.name;
            document.getElementById('promo_type').value = promo.promo_type;
            document.getElementById('target_category').value = promo.target_category;
            document.getElementById('discount_percent').value = promo.discount_percent;
            document.getElementById('condition_qty').value = promo.condition_qty;
            document.getElementById('reward_qty').value = promo.reward_qty;
            document.getElementById('start_time').value = promo.start_time;
            document.getElementById('end_time').value = promo.end_time;
            
            document.getElementById('modalTitle').innerText = '✏️ แก้ไขโปรโมชั่น';
            togglePromoType();
            await updateTargetItems(promo.target_item || '');
            document.getElementById('promoModal').style.display = 'flex';
        }

        function closePromoModal() {
            document.getElementById('promoModal').style.display = 'none';
        }

        async function submitPromotion() {
            const form = document.getElementById('promoForm');
            const formData = new FormData(form);
            formData.append('action', 'save_promotion');

            try {
                const response = await fetch('pos_action.php', { method: 'POST', body: formData });
                const text = await response.text(); // อ่านค่าเป็นข้อความก่อน
                try {
                    const result = JSON.parse(text);
                    if (result.success) {
                        closePromoModal();
                        await refreshTableData();
                    } else {
                        alert('เกิดข้อผิดพลาดจากฐานข้อมูล: ' + result.error);
                    }
                } catch (jsonErr) {
                    console.error("PHP Error:", text);
                    alert('ข้อผิดพลาดจากเซิร์ฟเวอร์ (เช็ค Console): ' + text.substring(0, 100));
                }
            } catch(e) {
                console.error(e);
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์ หรือ API ไม่ถูกต้อง');
            }
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="nav-bar">
            <h2 style="margin:0; color:#00d4ff;">BAR POS</h2>
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

        <div class="page-header">
            <h1>🎁 แดชบอร์ดโปรโมชั่น</h1>
            <button class="btn-add-main" onclick="openAddModal()">+ สร้างโปรโมชั่น</button>
        </div>

        <div class="card" id="promo-table-container">
            <table>
                <thead>
                    <tr>
                        <th>เปิด/ปิด</th>
                        <th>ชื่อโปรโมชั่น</th>
                        <th>รายละเอียด</th>
                        <th>ช่วงเวลา</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT * FROM promotions ORDER BY id DESC";
                    $result = $conn->query($sql);
                    if($result && $result->num_rows > 0) {
                        while($row = $result->fetch_assoc()) {
                            $checked = $row['is_active'] ? 'checked' : '';
                            $details = "";
                            
                            $item_text = "";
                            if (!empty($row['target_item'])) {
                                $decoded = json_decode($row['target_item'], true);
                                if (is_array($decoded)) {
                                    $item_text = ", สินค้า: " . implode(', ', $decoded);
                                } else {
                                    $item_text = ", สินค้า: " . $row['target_item'];
                                }
                            }
                            if($row['promo_type'] == 'discount_percent') {
                                $details = "ลด {$row['discount_percent']}% (หมวด: {$row['target_category']}{$item_text})";
                            } else {
                                $details = "ซื้อ {$row['condition_qty']} แถม {$row['reward_qty']} (หมวด: {$row['target_category']}{$item_text})";
                            }
                            $timeStr = ($row['start_time'] && $row['end_time']) 
                                ? date('H:i', strtotime($row['start_time'])) . " - " . date('H:i', strtotime($row['end_time']))
                                : "ตลอดเวลา";

                            $safeRow = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');

                            echo "<tr>";
                            echo "<td>
                                    <label class='switch'>
                                        <input type='checkbox' $checked onchange='toggleActive({$row['id']}, this.checked)'>
                                        <span class='slider'></span>
                                    </label>
                                  </td>";
                            echo "<td><strong style='color:#ff9800;'>{$row['name']}</strong></td>";
                            echo "<td>$details</td>";
                            echo "<td>$timeStr</td>";
                            echo "<td>
                                    <button class='btn-edit' onclick='openEditModal($safeRow)'>แก้ไข</button>
                                    <button class='btn-delete' onclick='deletePromotion({$row['id']})'>ลบ</button>
                                  </td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' style='text-align:center;'>ยังไม่มีโปรโมชั่นในระบบ</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal เพิ่ม/แก้ไข -->
    <div id="promoModal" class="modal-overlay">
        <div class="modal-box">
            <h2 id="modalTitle" style="color: var(--primary); margin-top:0;">✨ โปรโมชั่น</h2>
            <form id="promoForm">
                <input type="hidden" name="id" id="promo_id">
                
                <div class="input-group">
                    <label>ชื่อโปรโมชั่น:</label>
                    <input type="text" name="name" id="name" required placeholder="เช่น ซื้อเบียร์ 2 แถม 1">
                </div>
                
                <div class="input-group">
                    <label>หมวดหมู่เป้าหมาย (Target Category):</label>
                    <select name="target_category" id="target_category" onchange="updateTargetItems()">
                        <option value="Beer">Beer</option>
                        <option value="Cocktail">Cocktail</option>
                        <option value="Wine">Wine</option>
                        <option value="Liquor">Liquor</option>
                        <option value="Food">Food</option>
                        <?php
                        $cat_res = $conn->query("SELECT DISTINCT category FROM products WHERE category NOT IN ('Beer','Wine','Cocktail','Food','Liquor') AND category IS NOT NULL AND category != '' ORDER BY category ASC");
                        if($cat_res) {
                            while($c = $cat_res->fetch_assoc()) {
                                echo "<option value='".htmlspecialchars($c['category'])."'>".htmlspecialchars($c['category'])."</option>\n";
                            }
                        }
                        ?>
                    </select>
                </div>
                
                <div class="input-group">
                    <label>สินค้าเป้าหมาย (ติ๊กเลือกรายการที่ต้องการจัดโปร / ปล่อยว่างเพื่อจัดโปรให้ทั้งหมวดหมู่):</label>
                    <div id="target_item_container" style="max-height: 85px; overflow-y: auto; background: #0f111a; border: 1px solid #333; border-radius: 8px;">
                        <div style="color: #aaa; padding: 10px;">-- กรุณาเลือกหมวดหมู่ก่อน --</div>
                    </div>
                </div>

                <div class="input-group">
                    <label>รูปแบบโปรโมชั่น:</label>
                    <select name="promo_type" id="promo_type" onchange="togglePromoType()">
                        <option value="buy_x_get_y">ซื้อ X แถม Y (เช่น ซื้อ 2 ฟรี 1)</option>
                        <option value="discount_percent">ลดราคาเป็นเปอร์เซ็นต์ (%)</option>
                    </select>
                </div>

                <!-- โชว์เมื่อเลือกซื้อ X แถม Y -->
                <div id="div_bogo" style="display:flex; gap:25px; margin-bottom:15px; align-items:center; background:#1a1d29; padding:10px 15px; border-radius:8px; border:1px solid #333;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <label style="color:#aaa; font-size:13px; margin:0; white-space:nowrap;">ซื้อครบจำนวน (ชิ้น):</label>
                        <input type="number" name="condition_qty" id="condition_qty" placeholder="เช่น 2" style="width: 80px; padding: 8px; background: #0f111a; border: 1px solid #444; color: white; border-radius: 6px; text-align:center;">
                    </div>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <label style="color:#aaa; font-size:13px; margin:0; white-space:nowrap;">แถมฟรี (ชิ้น):</label>
                        <input type="number" name="reward_qty" id="reward_qty" placeholder="เช่น 1" style="width: 80px; padding: 8px; background: #0f111a; border: 1px solid #444; color: white; border-radius: 6px; text-align:center;">
                    </div>
                </div>

                <!-- โชว์เมื่อเลือกลด % -->
                <div id="div_percent" class="input-group" style="display:none;">
                    <label>ส่วนลด (%):</label>
                    <input type="number" step="0.01" name="discount_percent" id="discount_percent" placeholder="เช่น 10">
                </div>

                <div style="display:flex; gap:10px;">
                    <div class="input-group" style="flex:1;">
                        <label>เวลาเริ่ม (Start Time):</label>
                        <input type="time" name="start_time" id="start_time">
                    </div>
                    <div class="input-group" style="flex:1;">
                        <label>เวลาสิ้นสุด (End Time):</label>
                        <input type="time" name="end_time" id="end_time">
                    </div>
                </div>
                <small style="color:#666;">*เว้นว่างเวลาไว้ หากต้องการให้โปรโมชั่นใช้งานได้ตลอดทั้งวัน</small>

            </form>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closePromoModal()">ยกเลิก</button>
                <button class="btn-save" onclick="submitPromotion()">บันทึกข้อมูล</button>
            </div>
        </div>
    </div>
</body>
</html>