<?php
require 'config.php';

header('Content-Type: application/json');

$order_id  = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
$item_name = isset($_POST['item_name']) ? $_POST['item_name'] : '';
$price     = isset($_POST['price']) ? floatval($_POST['price']) : 0;
$type      = isset($_POST['type']) ? $_POST['type'] : 'liquor'; // 'liquor' หรือ 'special'

if ($order_id <= 0 || empty($item_name)) {
    echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

// ปรับให้บันทึกข้อมูลทุกประเภทลงฐานข้อมูลเพื่อป้องกันข้อมูลหาย
$stmt = $conn->prepare("INSERT INTO order_items (order_id, item_name, price, quantity, status) VALUES (?, ?, ?, 1, 'active')");
$stmt->bind_param("isd", $order_id, $item_name, $price);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'บันทึกรายการเรียบร้อย']);
} else {
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $conn->error]);
}
$stmt->close();
?>