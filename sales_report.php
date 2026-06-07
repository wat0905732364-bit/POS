<?php
require 'config.php';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงานยอดขาย - Bar POS</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; background: #0f111a; color: white; padding: 40px; }
        .container { max-width: 900px; margin: auto; background: #1a1d29; padding: 30px; border-radius: 15px; }
        a { color: #00d4ff; text-decoration: none; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border-bottom: 1px solid #333; text-align: left; }
        th { color: #00d4ff; }
        h2 { margin-top: 40px; color: #00d4ff; border-bottom: 1px solid #333; padding-bottom: 10px; }
        .summary-total { color: #2ecc71; font-weight: bold; text-align: right; }
    </style>
</head>
<body>
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <a href="index.php">← กลับหน้าขาย (POS)</a>
            <a href="stock_report.php">📦 จัดการสต็อกสินค้า</a>
        </div>
        <h1>รายงานยอดขาย</h1>

        <!-- ส่วนสรุปยอดรายวัน -->
        <h2>📊 สรุปยอดขายรายวัน (Daily Summary)</h2>
        <table>
            <thead>
                <tr>
                    <th>วันที่</th>
                    <th>จำนวนบิล</th>
                    <th style="text-align: right;">ยอดขายรวม</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sql_daily = "SELECT DATE(created_at) as sale_date, COUNT(id) as bill_count, SUM(total_amount) as daily_total 
                              FROM orders WHERE status = 'paid' 
                              GROUP BY DATE(created_at) ORDER BY sale_date DESC";
                $res_daily = $conn->query($sql_daily);
                if ($res_daily && $res_daily->num_rows > 0) {
                    while($row = $res_daily->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>" . date('d/m/Y', strtotime($row['sale_date'])) . "</td>";
                        echo "<td>" . $row['bill_count'] . " บิล</td>";
                        echo "<td class='summary-total'>฿" . number_format($row['daily_total'], 2) . "</td>";
                        echo "</tr>";
                    }
                } else {
                    echo '<tr><td colspan="3" style="text-align:center;">ยังไม่มีข้อมูลยอดขาย</td></tr>';
                }
                ?>
            </tbody>
        </table>

        <!-- ส่วนรายละเอียดรายการ -->
        <h2>📝 รายละเอียดบิลรายรายการ</h2>
        <table>
            <thead>
                <tr>
                    <th>เลขที่บิล</th>
                    <th>โต๊ะ</th>
                    <th>ยอดสุทธิ</th>
                    <th>เวลา</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sql = "SELECT id, table_number, total_amount, created_at FROM orders WHERE status = 'paid' ORDER BY created_at DESC";
                $result = $conn->query($sql);

                if ($result && $result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>#" . $row['id'] . "</td>";
                        echo "<td>โต๊ะ " . htmlspecialchars($row['table_number']) . "</td>";
                        echo "<td>฿" . number_format($row['total_amount'], 2) . "</td>";
                        echo "<td>" . date('d/m/Y H:i', strtotime($row['created_at'])) . "</td>";
                        echo "</tr>";
                    }
                } else {
                    echo '<tr><td colspan="4" style="text-align:center;">ยังไม่มีข้อมูลยอดขายในระบบ</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
</body>
</html>