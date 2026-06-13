<?php
require 'config.php';
header('Content-Type: application/json');

// รับค่าจากหน้า POS (แนะนำให้ส่งผ่าน POST หรือ GET เมื่อกดปุ่ม)
$order_id  = isset($_REQUEST['order_id']) ? intval($_REQUEST['order_id']) : 0;
$apply_tax = isset($_REQUEST['apply_tax']) ? intval($_REQUEST['apply_tax']) : 0;
$apply_sc  = isset($_REQUEST['apply_sc']) ? intval($_REQUEST['apply_sc']) : 0;
$discount  = isset($_REQUEST['discount']) ? floatval($_REQUEST['discount']) : 0;
$promo_amount = isset($_REQUEST['promo_amount']) ? floatval($_REQUEST['promo_amount']) : 0;
$is_percent = isset($_REQUEST['is_percent']) ? intval($_REQUEST['is_percent']) : 1;

if ($order_id > 0) {
    $stmt = $conn->prepare("UPDATE orders SET apply_tax = ?, apply_sc = ?, discount_amount = ?, promo_amount = ?, is_percent = ? WHERE id = ?");
    $stmt->bind_param("iiddii", $apply_tax, $apply_sc, $discount, $promo_amount, $is_percent, $order_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid Order ID']);
}
?>