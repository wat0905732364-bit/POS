<?php
require 'config.php';

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

// ดึงข้อมูลบิล
$stmt_order = $conn->prepare("SELECT * FROM orders WHERE id = ?");
$stmt_order->bind_param("i", $order_id);
$stmt_order->execute();
$order = $stmt_order->get_result()->fetch_assoc();

if (!$order) {
    die("ไม่พบข้อมูลบิล");
}

// --- Debugging: ตรวจสอบข้อมูลที่ดึงมาจากฐานข้อมูล ---
// var_dump($order);
// die();
// ---------------------------------------------------
// ดึงรายการอาหาร
$stmt_items = $conn->prepare("SELECT item_name, price, item_discount, SUM(quantity) as quantity FROM order_items WHERE order_id = ? AND status = 'active' GROUP BY item_name, price, item_discount");
$stmt_items->bind_param("i", $order_id);
$stmt_items->execute();
$items_result = $stmt_items->get_result();

$subtotal = 0;
$items_list = [];
while($item = $items_result->fetch_assoc()) {
    $price_after_disc = $item['price'] - ($item['item_discount'] ?? 0);
    $subtotal += $price_after_disc * $item['quantity'];
    $items_list[] = $item;
}

// คำนวณยอดต่างๆ (อ้างอิงจากยอดรวมใน DB หรือคำนวณใหม่ตามมาตรฐาน)
$discount_amt = floatval($order['discount_amount'] ?? 0);
$promo_amount = floatval($order['promo_amount'] ?? 0);
$is_percent   = (isset($order['is_percent']) && (int)$order['is_percent'] == 1);

// คำนวณ Service Charge (เช็คทั้ง snake_case และ camelCase ที่อาจเกิดขึ้นจากโค้ดเก่า)
$has_sc = (isset($order['apply_sc']) && (int)$order['apply_sc'] == 1) || (isset($order['applySc']) && (int)$order['applySc'] == 1);
$sc     = $has_sc ? ($subtotal * 0.10) : 0;

// คำนวณ VAT (เช็คทั้ง apply_tax และ applyTax)
$has_tax = (isset($order['apply_tax']) && (int)$order['apply_tax'] == 1) || (isset($order['applyTax']) && (int)$order['applyTax'] == 1);
$tax     = $has_tax ? (($subtotal + $sc) * 0.07) : 0;

$discount = ($is_percent ? ($subtotal * ($discount_amt / 100)) : $discount_amt);

$points_used = isset($order['points_used']) ? floatval($order['points_used']) : 0;
$total = max(0, ($subtotal + $sc + $tax) - $discount - $promo_amount - $points_used);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice #<?php echo htmlspecialchars($order['receipt_no'] ?? $order['id']); ?></title>
    <style>
        body { font-family: 'Sarabun', sans-serif; display: flex; justify-content: center; background: #f5f5f5; padding: 20px; margin: 0; }
        .receipt { background: white; width: 80mm; padding: 10mm 5mm; box-shadow: 0 0 10px rgba(0,0,0,0.1); color: #000; }
        .header { text-align: center; margin-bottom: 5mm; }
        .header h2 { margin: 0; font-size: 18px; text-transform: uppercase; }
        .header p { margin: 2px 0; font-size: 11px; }
        .info { font-size: 12px; margin-bottom: 4mm; line-height: 1.4; border-bottom: 1px dashed #000; padding-bottom: 2mm; }
        .items { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 4mm; }
        .items th { text-align: left; border-bottom: 1px solid #000; padding-bottom: 1mm; }
        .items td { padding: 1.5mm 0; vertical-align: top; }
        .text-right { text-align: right; }
        .totals { border-top: 1px dashed #000; padding-top: 2mm; font-size: 13px; }
        .total-row { display: flex; justify-content: space-between; margin-bottom: 1mm; }
        .grand-total { font-weight: bold; font-size: 16px; border-top: 1px solid #000; padding-top: 2mm; margin-top: 1mm; }
        .footer { text-align: center; margin-top: 8mm; font-size: 11px; }
        @media print {
            body { background: none; padding: 0; }
            .receipt { box-shadow: none; width: 80mm; }
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="header">
            <h2>Bar POS System</h2>
            <p>123 Restaurant Street, Bangkok</p>
            <p>Tax ID: 0123456789012</p>
        </div>

        <div class="info">
            <div><strong>โต๊ะ: <?php echo htmlspecialchars($order['table_number']); ?></strong></div>
            <div>เลขที่บิล: #<?php echo htmlspecialchars($order['receipt_no'] ?? $order['id']); ?></div>
            <div>วันที่: <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></div>
            <?php if (!empty($order['cashier_name'])): ?>
            <div>พนักงาน: <?php echo htmlspecialchars($order['cashier_name']); ?></div>
            <?php endif; ?>
        </div>

        <table class="items">
            <thead>
                <tr>
                    <th width="10%">Qty</th>
                    <th width="60%">Item</th>
                    <th width="30%" class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($items_list as $item): ?>
                <tr>
                    <td><?php echo $item['quantity']; ?></td>
                    <td>
                        <?php echo htmlspecialchars($item['item_name']); ?>
                        <?php if(($item['item_discount'] ?? 0) > 0): ?>
                            <br><small style="font-size: 10px; color: #666;">(Discount -<?php echo number_format($item['item_discount'], 2); ?>)</small>
                        <?php endif; ?>
                    </td>
                    <td class="text-right"><?php echo number_format(($item['price'] - ($item['item_discount'] ?? 0)) * $item['quantity'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totals">
            <div class="total-row"><span>Subtotal</span><span><?php echo number_format($subtotal, 2); ?></span></div>
            <div class="total-row"><span>Discount</span><span>-<?php echo number_format($discount, 2); ?></span></div>
            <?php if($promo_amount > 0): ?>
            <div class="total-row"><span>Promo Discount</span><span>-<?php echo number_format($promo_amount, 2); ?></span></div>
            <?php endif; ?>
            <div class="total-row"><span>Service Charge (10%)</span><span><?php echo number_format($sc, 2); ?></span></div>
            <div class="total-row"><span>VAT (7%)</span><span><?php echo number_format($tax, 2); ?></span></div>
            <?php if($points_used > 0): ?>
            <div class="total-row"><span>Points Discount</span><span>-<?php echo number_format($points_used, 2); ?></span></div>
            <?php endif; ?>
            <div class="total-row grand-total"><span>TOTAL</span><span><?php echo number_format($total, 2); ?></span></div>
        </div>

        <div class="footer">
            <p>ราคาสินค้ารวมภาษีมูลค่าเพิ่มแล้ว</p>
            <p>ขอบคุณที่ใช้บริการ / Thank you!</p>
        </div>
    </div>
    <script>
        window.onload = function() { window.print(); }
    </script>
</body>
</html>