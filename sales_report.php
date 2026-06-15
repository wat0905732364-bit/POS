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

// ตรวจสอบและสร้างคอลัมน์ receipt_no อัตโนมัติ
$chk_col = $conn->query("SHOW COLUMNS FROM orders LIKE 'receipt_no'");
if ($chk_col && $chk_col->num_rows == 0) {
    $conn->query("ALTER TABLE orders ADD receipt_no VARCHAR(20) NULL AFTER id");
}

// รับค่าตัวกรองแคชเชียร์
$cashier_filter = isset($_GET['cashier']) ? $_GET['cashier'] : '';
$where_orders = "status = 'paid'";
$where_items = "o.status = 'paid' AND oi.status = 'active'";

if ($cashier_filter !== '') {
    $safe_cashier = $conn->real_escape_string($cashier_filter);
    $where_orders .= " AND cashier_name = '$safe_cashier'";
    $where_items .= " AND o.cashier_name = '$safe_cashier'";
}

// ดึงรายชื่อแคชเชียร์ที่มีการทำรายการขายเพื่อมาใส่ใน Dropdown
$cashier_list = $conn->query("SELECT DISTINCT cashier_name FROM orders WHERE status = 'paid' AND cashier_name IS NOT NULL AND cashier_name != '' ORDER BY cashier_name ASC");

// เตรียมข้อมูลสรุปยอดขายรายวันสำหรับกราฟและตาราง (ย้อนหลัง 30 วัน)
$sql_daily = "SELECT DATE(created_at) as sale_date, COUNT(id) as bill_count, SUM(total_amount) as daily_total 
              FROM orders WHERE $where_orders 
              GROUP BY DATE(created_at) ORDER BY sale_date DESC LIMIT 30";
$res_daily = $conn->query($sql_daily);

$daily_rows = [];
$chart_labels = [];
$chart_data = [];

if ($res_daily && $res_daily->num_rows > 0) {
    while($row = $res_daily->fetch_assoc()) {
        $daily_rows[] = $row;
        $chart_labels[] = date('d/m', strtotime($row['sale_date'])); // วัน/เดือน
        $chart_data[] = $row['daily_total'];
    }
    // กลับข้อมูลให้เรียงจากวันเก่าไปวันใหม่สำหรับแสดงในกราฟ
    $chart_labels = array_reverse($chart_labels);
    $chart_data = array_reverse($chart_data);
}

// เตรียมข้อมูลสัดส่วนยอดขายตามหมวดหมู่ (ดึงยอดขายสุทธิที่ถูกจ่ายแล้วมาคำนวณ)
$sql_cat = "SELECT COALESCE(p.category, 'อื่นๆ (ไม่มีหมวด)') as category_name, 
                   SUM((oi.price - COALESCE(oi.item_discount, 0)) * oi.quantity) as cat_total 
            FROM order_items oi 
            JOIN orders o ON oi.order_id = o.id 
            LEFT JOIN products p ON oi.item_name = p.name 
            WHERE $where_items 
            GROUP BY category_name 
            ORDER BY cat_total DESC";
$res_cat = $conn->query($sql_cat);
$cat_labels = []; $cat_data = []; $chart_cat_colors = [];
$colors = ['#ff9800', '#2ecc71', '#3498db', '#e74c3c', '#9b59b6', '#f1c40f', '#1abc9c', '#e67e22', '#34495e'];
if ($res_cat && $res_cat->num_rows > 0) {
    $c_idx = 0;
    while($row = $res_cat->fetch_assoc()) {
        $cat_labels[] = $row['category_name'];
        $cat_data[] = floatval($row['cat_total']);
        $chart_cat_colors[] = $colors[$c_idx % count($colors)];
        $c_idx++;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงานยอดขาย - Bar POS</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Sarabun', sans-serif; background: #0f111a; color: white; padding: 40px; }
        .container { max-width: 900px; margin: auto; background: #1a1d29; padding: 30px; border-radius: 15px; }
        a { color: #00d4ff; text-decoration: none; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border-bottom: 1px solid #333; text-align: left; }
        th { color: #00d4ff; }
        h2 { margin-top: 40px; color: #00d4ff; border-bottom: 1px solid #333; padding-bottom: 10px; }
        .summary-total { color: #2ecc71; font-weight: bold; text-align: right; }
        
        /* Modal Styles สำหรับดูรายละเอียดบิล */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 1000; justify-content: center; align-items: center; backdrop-filter: blur(5px); }
        .modal-box { background: #1a1d29; padding: 25px; border-radius: 15px; width: 500px; max-width: 90%; border: 1px solid #333; animation: slideUp 0.3s ease-out; }
        @keyframes slideUp { from { transform: translateY(30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .modal-header h3 { margin-top: 0; color: #00d4ff; border-bottom: 1px dashed #333; padding-bottom: 10px; display: inline-block; }
        .close-btn { background: #333; color: white; border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; float: right; font-family: 'Sarabun', sans-serif; }
        .close-btn:hover { background: #555; }
        .item-list { margin-top: 15px; max-height: 300px; overflow-y: auto; padding-right: 10px; }
        .item-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #252a3a; font-size: 15px; }
    </style> 
    <script>
        // ฟังก์ชันสำหรับเปิดดูรายการสินค้าภายในบิล
        async function viewOrderDetails(orderId) {
            const formData = new FormData();
            formData.append('action', 'get_order_details');
            formData.append('order_id', orderId);

            try {
                const response = await fetch('pos_action.php', { method: 'POST', body: formData });
                const data = await response.json();
                
                const listContainer = document.getElementById('modal-item-list');
                listContainer.innerHTML = '';
                
                if (data.items && data.items.length > 0) {
                    data.items.forEach(item => {
                        const price = parseFloat(item.price);
                        const qty = parseInt(item.quantity);
                        const discount = parseFloat(item.item_discount || 0);
                        const total = (price - discount) * qty;
                        
                        let discText = discount > 0 ? `<br><small style="color:#ff4d4d;">(ส่วนลดต่อรายการ ฿${discount})</small>` : '';
                        
                        listContainer.innerHTML += `
                            <div class="item-row">
                                <div style="flex:2;">${item.name} <span style="color:#00d4ff; font-weight:bold;">x${qty}</span> ${discText}</div>
                                <div style="flex:1; text-align:right;">฿${total.toLocaleString(undefined, {minimumFractionDigits: 2})}</div>
                            </div>
                        `;
                    });
                } else {
                    listContainer.innerHTML = '<div style="text-align:center; padding: 20px; color:#aaa;">ไม่มีรายการสินค้าในบิลนี้</div>';
                }

                document.getElementById('modal-order-id').innerText = orderId;
                document.getElementById('orderDetailModal').style.display = 'flex';
            } catch (e) {
                console.error("Error fetching bill details:", e);
                alert("เกิดข้อผิดพลาดในการดึงข้อมูลบิล");
            }
        }

        function closeDetailModal() {
            document.getElementById('orderDetailModal').style.display = 'none';
        }

        // ฟังก์ชันเปิดดูรายการบิลทั้งหมดในแต่ละวัน
        async function viewDailyDetails(dateStr, displayDate) {
            const formData = new FormData();
            formData.append('action', 'get_daily_orders');
            formData.append('date', dateStr);
            const cashierFilter = new URLSearchParams(window.location.search).get('cashier') || '';
            if (cashierFilter) {
                formData.append('cashier', cashierFilter);
            }

            try {
                const response = await fetch('pos_action.php', { method: 'POST', body: formData });
                const data = await response.json();
                
                const listContainer = document.getElementById('modal-daily-list');
                listContainer.innerHTML = '';
                
                if (data && data.length > 0) {
                    data.forEach(order => {
                        const displayNo = order.receipt_no ? "#" + order.receipt_no : "#" + order.id;
                        const time = new Date(order.created_at).toLocaleTimeString('th-TH', {hour: '2-digit', minute:'2-digit'});
                        listContainer.innerHTML += `
                            <div class="item-row" style="align-items: center;">
                                <div style="flex:1;"><button onclick="viewOrderDetails(${order.id})" style="background:none; border:1px solid #00d4ff; color:#00d4ff; padding:4px 8px; border-radius:4px; cursor:pointer; font-size: 13px;">🔍 ${displayNo}</button></div>
                                <div style="flex:1;">โต๊ะ ${order.table_number}</div>
                                <div style="flex:1; text-align:right; color:#2ecc71;">฿${parseFloat(order.total_amount).toLocaleString(undefined, {minimumFractionDigits: 2})}</div>
                                <div style="flex:1; text-align:right; font-size:13px; color:#aaa;">เวลา ${time}</div>
                            </div>
                        `;
                    });
                } else {
                    listContainer.innerHTML = '<div style="text-align:center; padding: 20px; color:#aaa;">ไม่มีข้อมูลบิลในวันนี้</div>';
                }

                document.getElementById('modal-daily-date').innerText = displayDate;
                document.getElementById('dailyDetailModal').style.display = 'flex';
            } catch (e) { console.error(e); alert("เกิดข้อผิดพลาดในการดึงข้อมูล"); }
        }
        function closeDailyModal() {
            document.getElementById('dailyDetailModal').style.display = 'none';
        }
    </script>
</head>
<body>
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <a href="index.php">← กลับหน้าขาย (POS)</a>
            <div class="nav-links">
                <a href="dashboard.php">⚙️ กลับไปแดชบอร์ด</a>
            </div>
            <div style="display: flex; align-items: center; gap: 15px; color: #aaa; font-size: 14px;">
                <span>👤 <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                <a href="logout.php" style="background: #e74c3c; color: white; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 13px; transition: 0.2s;">ออกจากระบบ</a>
            </div>
        </div>
        <h1>รายงานยอดขาย</h1>

        <!-- ตัวกรองข้อมูลแคชเชียร์ -->
        <div style="margin-bottom: 25px; background: #252a3a; padding: 15px; border-radius: 10px; display: inline-block;">
            <form method="GET" style="display: flex; gap: 10px; align-items: center; margin: 0;">
                <label for="cashier" style="color: #aaa; margin: 0;">👤 ยอดขายของแคชเชยร์:</label>
                <select name="cashier" id="cashier" onchange="this.form.submit()" style="padding: 8px 12px; border-radius: 6px; background: #0f111a; color: white; border: 1px solid #444; outline: none; cursor: pointer;">
                    <option value=> All </option>
                    <?php
                    if ($cashier_list && $cashier_list->num_rows > 0) {
                        while($c = $cashier_list->fetch_assoc()) {
                            $c_name = htmlspecialchars($c['cashier_name']);
                            $selected = ($cashier_filter === $c['cashier_name']) ? 'selected' : '';
                            echo "<option value='{$c_name}' {$selected}>{$c_name}</option>";
                        }
                    }
                    ?>
                </select>
            </form>
        </div>

        <!-- ส่วนแสดงกราฟแบบแบ่งฝั่ง (Flexbox) -->
        <div style="display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 30px;">
            <!-- กราฟยอดขายรายวัน -->
            <div style="flex: 2; min-width: 450px; background: #1a1d29; padding: 20px; border-radius: 15px; border: 1px solid #333; box-shadow: 0 4px 15px rgba(0,0,0,0.3);">
                <h3 style="color: #00d4ff; margin-top: 0; text-align: center;">📈 ยอดขายรายวัน (30 วันย้อนหลัง)</h3>
                <canvas id="salesChart" height="120"></canvas>
            </div>
            <!-- กราฟหมวดหมู่ -->
            <div style="flex: 1; min-width: 280px; background: #1a1d29; padding: 20px; border-radius: 15px; border: 1px solid #333; box-shadow: 0 4px 15px rgba(0,0,0,0.3); display: flex; flex-direction: column; align-items: center;">
                <h3 style="color: #ff9800; margin-top: 0; text-align: center;">🍕 สัดส่วนยอดขายตามหมวดหมู่</h3>
                <div style="width: 100%; max-width: 250px; flex-grow: 1; display: flex; align-items: center; justify-content: center; margin-top: 10px;">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener("DOMContentLoaded", function() {
                const ctx = document.getElementById('salesChart').getContext('2d');
                new Chart(ctx, {
                    type: 'line', // หากต้องการกราฟแท่งสามารถเปลี่ยนจาก 'line' เป็น 'bar' ได้ครับ
                    data: {
                        labels: <?php echo json_encode($chart_labels); ?>,
                        datasets: [{
                            label: 'ยอดขายรายวัน (บาท)',
                            data: <?php echo json_encode($chart_data); ?>,
                            backgroundColor: 'rgba(0, 212, 255, 0.2)',
                            borderColor: '#00d4ff',
                            borderWidth: 2,
                            pointBackgroundColor: '#f1c40f',
                            pointBorderColor: '#fff',
                            pointRadius: 4,
                            tension: 0.3, // ทำให้เส้นโค้งสวยงาม
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#aaa', font: { family: 'Sarabun' } } },
                            x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#aaa', font: { family: 'Sarabun' } } }
                        },
                        plugins: { legend: { labels: { color: '#fff', font: { family: 'Sarabun', size: 14 } } } }
                    }
                });

                // วาดกราฟหมวดหมู่ (Doughnut Chart)
                const ctxCat = document.getElementById('categoryChart').getContext('2d');
                new Chart(ctxCat, {
                    type: 'doughnut',
                    data: {
                        labels: <?php echo json_encode($cat_labels); ?>,
                        datasets: [{
                            data: <?php echo json_encode($cat_data); ?>,
                            backgroundColor: <?php echo json_encode($chart_cat_colors); ?>,
                            borderColor: '#1a1d29', // สีกรอบของแต่ละชิ้นให้กลืนกับพื้นหลัง
                            borderWidth: 2,
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { 
                            legend: { 
                                position: 'bottom', 
                                labels: { color: '#fff', font: { family: 'Sarabun', size: 12 }, padding: 15 } 
                            }
                        }
                    }
                });
            });
        </script>

        <!-- ส่วนสรุปยอดรายวัน -->
        <h2>📊 สรุปยอดขายรายวัน (Daily Summary)</h2>
        <table>
            <thead>
                <tr>
                    <th>วันที่</th>
                    <th>จำนวนบิล</th>
                    <th style="text-align: right;">ยอดรวม</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (isset($daily_rows) && !empty($daily_rows)) {
                    foreach($daily_rows as $row) {
                        $raw_date = $row['sale_date'];
                        $display_date = date('d/m/Y', strtotime($raw_date));
                        echo "<tr>";
                        echo "<td>" . $display_date . "</td>";
                        echo "<td>" . $row['bill_count'] . " บิล</td>";
                        echo "<td class='summary-total'>฿" . number_format($row['daily_total'], 2) . "</td>";
                        echo "<td style='text-align:center;'><button onclick='viewDailyDetails(\"{$raw_date}\", \"{$display_date}\")' style='background:#3498db; color:white; border:none; padding:6px 12px; border-radius:6px; cursor:pointer; font-family: \"Sarabun\", sans-serif; font-size:13px;'>🔍 ดูบิลวันนี้</button></td>";
                        echo "</tr>";
                    }
                } else {
                    echo '<tr><td colspan="3" style="text-align:center;">ยังไม่มีข้อมูลยอดขาย</td></tr>';
                }
                ?>
            </tbody>
        </table>

        <!-- ส่วนรายละเอียดรายการ -->
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h2>📝 รายละเอียดบิลรายรายการ</h2>
            <a href="export_sales.php?cashier=<?php echo urlencode($cashier_filter); ?>" style="background: #27ae60; color: white; padding: 8px 15px; border-radius: 6px; font-size: 14px; font-weight: bold;">📥 Export to Excel</a>
        </div>
        <table>
            <thead>
                <tr>
                    <th>เลขที่บิล</th>
                    <th>โต๊ะ</th>
                    <th>ยอดสุทธิ</th>
                    <th>เวลา</th>
                    <th>แคชเชียร์</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sql = "SELECT id, receipt_no, table_number, total_amount, created_at, cashier_name FROM orders WHERE $where_orders ORDER BY created_at DESC";
                $result = $conn->query($sql);

                if ($result && $result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                        $display_no = $row['receipt_no'] ? "#" . htmlspecialchars($row['receipt_no']) : "#" . $row['id'];
                        echo "<tr>";
                        echo "<td><button onclick='viewOrderDetails(" . $row['id'] . ")' style='background:none; border:1px solid #00d4ff; color:#00d4ff; padding:5px 12px; border-radius:6px; cursor:pointer; font-family: \"Sarabun\", sans-serif; font-weight:bold; transition: 0.3s;' onmouseover='this.style.background=\"#00d4ff\"; this.style.color=\"#000\";' onmouseout='this.style.background=\"none\"; this.style.color=\"#00d4ff\";'>🔍 " . $display_no . "</button></td>";
                        echo "<td>โต๊ะ " . htmlspecialchars($row['table_number']) . "</td>";
                        echo "<td>฿" . number_format($row['total_amount'], 2) . "</td>";
                        echo "<td>" . date('d/m/Y H:i', strtotime($row['created_at'])) . "</td>";
                        echo "<td><span style='background:#34495e; padding:3px 8px; border-radius:4px; font-size:13px;'>" . htmlspecialchars($row['cashier_name'] ?? '-') . "</span></td>";
                        echo "</tr>";
                    }
                } else {
                    echo '<tr><td colspan="5" style="text-align:center;">ยังไม่มีข้อมูลยอดขายในระบบ</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>

    <!-- Modal สำหรับดูรายละเอียดสินค้าในบิล (z-index: 1001) -->
    <div id="orderDetailModal" class="modal-overlay" style="z-index: 1001;">
        <div class="modal-box">
            <div class="modal-header">
                <button class="close-btn" onclick="closeDetailModal()">ปิด</button>
                <h3>🧾 รายละเอียดบิล #<span id="modal-order-id"></span></h3>
            </div>
            <div id="modal-item-list" class="item-list">
                <!-- รายการสินค้าจะถูกนำมาแสดงที่นี่ผ่าน JavaScript -->
            </div>
        </div>
    </div>

    <!-- Modal สำหรับดูบิลรายวัน (z-index: 1000) -->
    <div id="dailyDetailModal" class="modal-overlay" style="z-index: 1000;">
        <div class="modal-box" style="width: 550px;">
            <div class="modal-header">
                <button class="close-btn" onclick="closeDailyModal()">ปิด</button>
                <h3>📅 บิลประจำวันที่: <span id="modal-daily-date"></span></h3>
            </div>
            <div id="modal-daily-list" class="item-list">
                <!-- รายการบิลจะถูกนำมาแสดงที่นี่ผ่าน JavaScript -->
            </div>
        </div>
    </div>
</body>
</html>