<?php
require 'config.php';

// ส่วนการรับคำสั่งจากหน้าเว็บ (API Handlers)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'add_item') {
        $order_id = intval($_POST['order_id']);
        $item_name = $_POST['item_name'];
        $price = floatval($_POST['price']);
        
        // ตรวจสอบสต็อกก่อนเพิ่มรายการ (รองรับการเช็คผ่าน inventory_id)
        $check_stmt = $conn->prepare("
            SELECT p.id, COALESCE(inv.stock_qty, p.stock_qty) as current_stock, p.ml_per_unit 
            FROM products p 
            LEFT JOIN products inv ON p.inventory_id = inv.id 
            WHERE p.name = ?
        ");
        $check_stmt->bind_param("s", $item_name);
        $check_stmt->execute();
        $data = $check_stmt->get_result()->fetch_assoc();
        
        $needed = ($data && $data['ml_per_unit'] > 0) ? $data['ml_per_unit'] : 1;

        if ($data && $data['current_stock'] < $needed) {
            echo json_encode(['success' => false, 'message' => 'สินค้าหมด (Out of Stock)']);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO order_items (order_id, item_name, price, quantity, status) VALUES (?, ?, ?, 1, 'active')");
        $stmt->bind_param("isd", $order_id, $item_name, $price);
        echo json_encode(['success' => $stmt->execute()]);
        exit;
    }

    if ($_POST['action'] === 'remove_item') {
        $order_id = intval($_POST['order_id']);
        $item_name = $_POST['item_name'];
        
        // ลบรายการล่าสุดที่มีชื่อตรงกันออก 1 แถว (Record)
        $stmt = $conn->prepare("DELETE FROM order_items WHERE order_id = ? AND item_name = ? AND status = 'active' ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("is", $order_id, $item_name);
        echo json_encode(['success' => $stmt->execute()]);
        exit;
    }

    if ($_POST['action'] === 'add_special') {
        $order_id = $_POST['order_id'];
        $item_name = $_POST['item_name'];
        $price = $_POST['price'];
        $emp_id = 1; // สมมติพนักงาน ID 1
        
        $result = addSpecialItem($order_id, $item_name, $price, 1, $emp_id);
        
        // บันทึกเมนูพิเศษที่สร้างใหม่ลงในฐานข้อมูลสินค้า Master เพื่อให้ค้นหาหรือนำมาใช้ใหม่ได้
        addProduct($item_name, $price, 'Liquor');

        echo json_encode(['success' => $result]);
        exit;
    }

    if ($_POST['action'] === 'create_order') {
        $table_number = $_POST['table_number'];
        try {
            $new_order_id = createNewOrder($table_number);
            if ($new_order_id > 0) {
                echo json_encode(['success' => true, 'order_id' => $new_order_id]);
            } else {
                echo json_encode(['success' => false, 'message' => 'ไม่สามารถสร้างออเดอร์ได้']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($_POST['action'] === 'add_product') {
        $name = $_POST['name'];
        $price = $_POST['price'];
        $category = $_POST['category'];
        $stock = isset($_POST['stock_qty']) ? intval($_POST['stock_qty']) : 0;
        $ml = isset($_POST['ml_per_unit']) ? intval($_POST['ml_per_unit']) : 0;
        $inv_id = (!empty($_POST['inventory_id'])) ? intval($_POST['inventory_id']) : null;
        
        $result = addProduct($name, $price, $category, $stock, $ml, $inv_id);
        echo json_encode(['success' => $result]);
        exit;
    }

    if ($_POST['action'] === 'update_order_settings') {
        $order_id = intval($_POST['order_id']);
        $discount = floatval($_POST['discount']);
        $is_percent = intval($_POST['is_percent']);
        $apply_sc = intval($_POST['apply_sc']);
        $apply_tax = intval($_POST['apply_tax']);
        
        $sql = "UPDATE orders SET discount_amount = ?, is_percent = ?, apply_sc = ?, apply_tax = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("diiii", $discount, $is_percent, $apply_sc, $apply_tax, $order_id);
        echo json_encode(['success' => $stmt->execute()]);
        exit;
    }

    if ($_POST['action'] === 'split_bill_process') {
        $old_order_id = $_POST['old_order_id'];
        $table_number = $_POST['table_number'];
        $item_names = json_decode($_POST['items']); // รับรายชื่อรายการที่จะแยก
        
        // 1. สร้าง Order ใหม่
        $new_order_id = createNewOrder($table_number);
        
        // 2. ย้ายรายการ (ในระบบตัวอย่างนี้เราจะย้ายตามชื่อรายการที่เลือก)
        $result = moveItemsToNewOrder($old_order_id, $new_order_id, $item_names);
        echo json_encode(['success' => $result, 'new_order_id' => $new_order_id]);
        exit;
    }

    if ($_POST['action'] === 'update_stock') {
        $product_id = intval($_POST['product_id']);
        $quantity = intval($_POST['quantity']);
        
        // 1. อัปเดตยอดคงเหลือ
        $stmt = $conn->prepare("UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?");
        $stmt->bind_param("ii", $quantity, $product_id);
        $success = $stmt->execute();

        // 2. บันทึกประวัติการเติมสต็อก
        if ($success) {
            $log_stmt = $conn->prepare("INSERT INTO stock_logs (product_id, qty_change, type) VALUES (?, ?, 'restock')");
            $log_stmt->bind_param("ii", $product_id, $quantity);
            $log_stmt->execute();
        }

        echo json_encode(['success' => $success]);
        exit;
    }

    if ($_POST['action'] === 'get_products') {
        $category = $_POST['category'];
        $sql = "SELECT * FROM products WHERE category = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $category);
        $stmt->execute();
        echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
        exit;
    }

    if ($_POST['action'] === 'get_active_orders') {
        try {
            $sql = "SELECT id, table_number 
                    FROM orders 
                    WHERE status = 'active' 
                    ORDER BY table_number ASC, id DESC";
            $result = $conn->query($sql);
            if (!$result) {
                throw new Exception($conn->error);
            }
            echo json_encode($result->fetch_all(MYSQLI_ASSOC));
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    if ($_POST['action'] === 'get_order_details') {
        $order_id = $_POST['order_id'];
        
        // 1. ดึงหมายเลขโต๊ะโดยตรงจากตาราง orders
        $stmt_order = $conn->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt_order->bind_param("i", $order_id);
        $stmt_order->execute();
        $table_data = $stmt_order->get_result()->fetch_assoc();

        // 2. ดึงรายการสินค้า
        $sql_items = "SELECT item_name as name, price, SUM(quantity) as quantity 
                      FROM order_items 
                      WHERE order_id = ? AND status = 'active' 
                      GROUP BY item_name, price";
        $stmt_items = $conn->prepare($sql_items);
        $stmt_items->bind_param("i", $order_id);
        $stmt_items->execute();
        $items = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);

        echo json_encode(['items' => $items, 'order_info' => $table_data]);
        exit;
    }

    if ($_POST['action'] === 'void_order') {
        $order_id = $_POST['order_id'];
        voidAll($order_id); // ฟังก์ชันที่มีอยู่แล้วสำหรับยกเลิกรายการ
        $result = $conn->query("UPDATE orders SET status = 'voided' WHERE id = $order_id");
        echo json_encode(['success' => $result]);
        exit;
    }

    if ($_POST['action'] === 'checkout_order') {
        $order_id = $_POST['order_id'];
        $total = $_POST['total'];
        $discount = $_POST['discount'];
        $is_percent = isset($_POST['is_percent']) ? intval($_POST['is_percent']) : 1;
        $apply_sc = intval($_POST['apply_sc']);
        $apply_tax = intval($_POST['apply_tax']);
        
        $sql = "UPDATE orders SET status = 'paid', total_amount = ?, discount_amount = ?, is_percent = ?, apply_sc = ?, apply_tax = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ddiiii", $total, $discount, $is_percent, $apply_sc, $apply_tax, $order_id);
        $result = $stmt->execute();

        if ($result) {
            // ตัดสต็อกสินค้าตามรายการที่มีในบิล
            $items_sql = "SELECT item_name, SUM(quantity) as sum_qty FROM order_items WHERE order_id = ? AND status = 'active' GROUP BY item_name";
            $items_stmt = $conn->prepare($items_sql);
            $items_stmt->bind_param("i", $order_id);
            $items_stmt->execute();
            $items_res = $items_stmt->get_result();
            while ($item = $items_res->fetch_assoc()) {
                // ค้นหาข้อมูลสินค้าและตัวตัดสต็อก
                $p_stmt = $conn->prepare("SELECT id, inventory_id, ml_per_unit FROM products WHERE name = ?");
                $p_stmt->bind_param("s", $item['item_name']);
                $p_stmt->execute();
                $p_data = $p_stmt->get_result()->fetch_assoc();
                
                if ($p_data) {
                    $target_id = $p_data['inventory_id'] ?? $p_data['id'];
                    $ml_to_cut = ($p_data['ml_per_unit'] > 0) ? ($p_data['ml_per_unit'] * $item['sum_qty']) : $item['sum_qty'];
                    
                    // ลดสต็อก (จะลดเป็นหน่วยปกติหรือ ml ตามที่ตั้งค่าไว้)
                    $conn->query("UPDATE products SET stock_qty = stock_qty - $ml_to_cut WHERE id = $target_id");
                    
                    // บันทึก Log
                    $log_stmt = $conn->prepare("INSERT INTO stock_logs (product_id, qty_change, type) VALUES (?, ?, 'sale')");
                    $neg_ml = -$ml_to_cut;
                    $log_stmt->bind_param("ii", $target_id, $neg_ml);
                    $log_stmt->execute();
                }
            }
        }

        echo json_encode(['success' => $result]);
        exit;
    }
}

// สร้าง Order ใหม่เพื่อรองรับการแยกบิล
function createNewOrder($table_number) {
    global $conn;
    $sql = "INSERT INTO orders (table_number, status, total_amount, apply_tax, apply_sc, is_percent) VALUES (?, 'active', 0, 1, 1, 1)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception($conn->error);
    }
    $stmt->bind_param("s", $table_number);
    if ($stmt->execute()) {
        return $conn->insert_id;
    }
    return 0;
}

// ย้ายรายการสินค้าไปยัง Order ใหม่
function moveItemsToNewOrder($old_id, $new_id, $item_names) {
    global $conn;
    $success = true;
    foreach ($item_names as $name) {
        $sql = "UPDATE order_items SET order_id = ? WHERE order_id = ? AND item_name = ? AND status = 'active' LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iis", $new_id, $old_id, $name);
        if (!$stmt->execute()) $success = false;
    }
    return $success;
}

// แยกบิล: ย้ายรายการจาก Order A ไป Order B
function splitBill($item_id, $old_order_id, $new_order_id) {
    global $conn;
    $sql = "UPDATE order_items SET order_id = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $new_order_id, $item_id);
    return $stmt->execute();
}

// ยกเลิกรายการ
function voidItem($item_id) {
    global $conn;
    return $conn->query("UPDATE order_items SET status = 'voided' WHERE id = $item_id");
}

// ยกเลิกรายการทั้งหมดในบิล
function voidAll($order_id) {
    global $conn;
    return $conn->query("UPDATE order_items SET status = 'voided' WHERE order_id = $order_id");
}

// เพิ่มเมนูพิเศษ (Special Menu) กำหนดราคาเองได้ทันที
function addSpecialItem($order_id, $item_name, $price, $quantity = 1, $emp_id) {
    global $conn;
    $sql = "INSERT INTO order_items (order_id, item_name, price, quantity, status, created_by) VALUES (?, ?, ?, ?, 'active', ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isdii", $order_id, $item_name, $price, $quantity, $emp_id);
    return $stmt->execute();
}

// เพิ่มสินค้าใหม่ลงในฐานข้อมูล (Master Data)
function addProduct($name, $price, $category, $stock = 0, $ml = 0, $inv_id = null) {
    global $conn;
    $sql = "INSERT INTO products (name, price, category, stock_qty, ml_per_unit, inventory_id) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sdsiii", $name, $price, $category, $stock, $ml, $inv_id);
    return $stmt->execute();
}

// ฟังก์ชันสำหรับรายงานยอดขาย (สรุปเบื้องต้น)
function getSalesReport($start_date, $end_date, $group_by = 'daily') {
    global $conn;
    $format = ($group_by == 'daily') ? '%Y-%m-%d' : (($group_by == 'weekly') ? '%X-%V' : '%Y-%m');
    
    $sql = "SELECT DATE_FORMAT(created_at, '$format') as period, 
            SUM(total_amount) as total_sales,
            COUNT(id) as total_orders
            FROM orders 
            WHERE created_at BETWEEN ? AND ? AND status = 'paid'
            GROUP BY period";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// รายงานสินค้าขายดี
function getBestSellers($limit = 5) {
    global $conn;
    return $conn->query("SELECT item_name, SUM(quantity) as qty 
                         FROM order_items 
                         WHERE status = 'active' 
                         GROUP BY item_name 
                         ORDER BY qty DESC LIMIT $limit");
}
?>