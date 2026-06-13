<?php
require 'config.php';

// ส่วนการรับคำสั่งจากหน้าเว็บ (API Handlers)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'add_item') {
        $order_id = intval($_POST['order_id']);
        $item_name = $_POST['item_name'];
        $price = floatval($_POST['price']);
        
        // ตรวจสอบสต็อกขวดที่เปิดแล้ว สำหรับรายการที่ต้องตัดเป็น ml
        $check_stmt = $conn->prepare("
            SELECT p.id, p.ml_per_unit as req_ml, p.inventory_id,
                   inv.id as master_id, inv.stock_qty as master_stock, inv.ml_per_unit as master_cap, inv.open_ml as master_open
            FROM products p 
            LEFT JOIN products inv ON p.inventory_id = inv.id 
            WHERE p.name = ?
        ");
        $check_stmt->bind_param("s", $item_name);
        $check_stmt->execute();
        $data = $check_stmt->get_result()->fetch_assoc();
        
        if ($data && $data['inventory_id'] !== null && $data['req_ml'] > 0) {
            $master_id = $data['master_id'];
            $needed_ml = $data['req_ml'];
            $current_open = $data['master_open'];
            
            if ($current_open < $needed_ml) {
                if ($data['master_stock'] > 0) {
                    // ขวดเปิดมีไม่พอ และมีขวดเต็มในสต็อก -> ถามเพื่อเปิดขวดใหม่
                    if (!isset($_POST['confirm_open']) || $_POST['confirm_open'] !== '1') {
                        echo json_encode(['success' => false, 'require_open' => true, 'message' => "ขวดที่กำลังเปิดใช้งานมีปริมาณไม่พอ (เหลือ {$current_open} ml)\n\nต้องการเปิดขวดใหม่หรือไม่?\n(ระบบจะตัดสต็อกขวดเต็ม 1 ขวดมาเทใส่ขวดที่กำลังใช้งาน)"]);
                        exit;
                    } else {
                        // กดยืนยันแล้ว -> ลดขวดเต็ม 1 ขวด และเพิ่มปริมาณ ml เข้าไปในขวดเปิด
                        $master_cap = $data['master_cap'];
                        $conn->query("UPDATE products SET stock_qty = stock_qty - 1, open_ml = open_ml + {$master_cap} WHERE id = {$master_id}");
                        $conn->query("INSERT INTO stock_logs (product_id, qty_change, unit, type) VALUES ({$master_id}, -1, 'unit', 'sale')"); // บันทึกประวัติเปิดขวด
                    }
                }
            }
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

    if ($_POST['action'] === 'update_item_discount') {
        $order_id = intval($_POST['order_id']);
        $item_name = $_POST['item_name'];
        $discount = floatval($_POST['discount']);
        
        $stmt = $conn->prepare("UPDATE order_items SET item_discount = ? WHERE order_id = ? AND item_name = ? AND status = 'active'");
        $stmt->bind_param("dis", $discount, $order_id, $item_name);
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
        $show_on_pos = isset($_POST['show_on_pos']) ? intval($_POST['show_on_pos']) : 1;
        
        $result = addProduct($name, $price, $category, $stock, $ml, $inv_id, $show_on_pos);
        echo json_encode(['success' => $result]);
        exit;
    }

    if ($_POST['action'] === 'edit_product') {
        $id = intval($_POST['id']);
        $name = $_POST['name'];
        $category = $_POST['category'];
        $price = floatval($_POST['price']);
        $stock_qty = isset($_POST['stock_qty']) ? intval($_POST['stock_qty']) : 0;
        $ml_per_unit = isset($_POST['ml_per_unit']) ? intval($_POST['ml_per_unit']) : 0;
        $open_ml = isset($_POST['open_ml']) ? intval($_POST['open_ml']) : 0;
        $inv_id = (!empty($_POST['inventory_id'])) ? intval($_POST['inventory_id']) : null;
        $show_on_pos = isset($_POST['show_on_pos']) ? intval($_POST['show_on_pos']) : 1;
        
        $stmt = $conn->prepare("UPDATE products SET name=?, category=?, price=?, stock_qty=?, ml_per_unit=?, open_ml=?, inventory_id=?, show_on_pos=? WHERE id=?");
        $stmt->bind_param("ssdiiiiii", $name, $category, $price, $stock_qty, $ml_per_unit, $open_ml, $inv_id, $show_on_pos, $id);
        
        $success = $stmt->execute();
        echo json_encode(['success' => $success, 'error' => $conn->error]);
        exit;
    }

    if ($_POST['action'] === 'delete_product') {
        $id = intval($_POST['product_id']);
        
        // 1. ลบประวัติสต็อกของสินค้านี้ออกก่อน
        $conn->query("DELETE FROM stock_logs WHERE product_id = $id");
        
        // 2. ปลดการเชื่อมโยงสินค้าลูกที่ผูกกับสินค้านี้อยู่ (ถ้ามี) เพื่อไม่ให้บัค
        $conn->query("UPDATE products SET inventory_id = NULL WHERE inventory_id = $id");
        
        // 3. ลบสินค้าหลัก
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        
        echo json_encode(['success' => $success, 'error' => $conn->error]);
        exit;
    }

    if ($_POST['action'] === 'update_order_settings') {
        $order_id = intval($_POST['order_id']);
        $discount = floatval($_POST['discount']);
        $promo_amount = isset($_POST['promo_amount']) ? floatval($_POST['promo_amount']) : 0;
        $is_percent = intval($_POST['is_percent']);
        $apply_sc = intval($_POST['apply_sc']);
        $apply_tax = intval($_POST['apply_tax']);
        
        $sql = "UPDATE orders SET discount_amount = ?, promo_amount = ?, is_percent = ?, apply_sc = ?, apply_tax = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ddiiii", $discount, $promo_amount, $is_percent, $apply_sc, $apply_tax, $order_id);
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
            $log_stmt = $conn->prepare("INSERT INTO stock_logs (product_id, qty_change, unit, type) VALUES (?, ?, 'unit', 'restock')");
            $log_stmt->bind_param("ii", $product_id, $quantity);
            $log_stmt->execute();
        }

        echo json_encode(['success' => $success]);
        exit;
    }

    if ($_POST['action'] === 'get_products') {
        $category = $_POST['category'];
        $sql = "SELECT * FROM products WHERE category = ? AND (show_on_pos = 1 OR show_on_pos IS NULL)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $category);
        $stmt->execute();
        echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
        exit;
    }

    if ($_POST['action'] === 'get_daily_stock_details') {
        $date = $_POST['date'];
        $sql = "SELECT p.name, s.qty_change, s.unit, s.type, s.created_at 
                FROM stock_logs s
                JOIN products p ON s.product_id = p.id
                WHERE DATE(s.created_at) = ?
                ORDER BY s.created_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $date);
        $stmt->execute();
        
        $result = $stmt->get_result();
        echo json_encode($result->fetch_all(MYSQLI_ASSOC));
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
        $sql_items = "SELECT o.item_name as name, o.price, o.item_discount, SUM(o.quantity) as quantity, p.category 
                      FROM order_items o
                      LEFT JOIN products p ON o.item_name = p.name
                      WHERE o.order_id = ? AND o.status = 'active' 
                      GROUP BY o.item_name, o.price, o.item_discount, p.category";
        $stmt_items = $conn->prepare($sql_items);
        $stmt_items->bind_param("i", $order_id);
        $stmt_items->execute();
        $items = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);

        echo json_encode(['items' => $items, 'order_info' => $table_data]);
        exit;
    }

    if ($_POST['action'] === 'check_member') {
        $phone = $_POST['phone'];
        $stmt = $conn->prepare("SELECT id, name, phone, points FROM members WHERE phone = ?");
        $stmt->bind_param("s", $phone);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result) {
            echo json_encode(['success' => true, 'member' => $result]);
        } else {
            echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลสมาชิกเบอร์นี้']);
        }
        exit;
    }

    if ($_POST['action'] === 'register_member') {
        $phone = $_POST['phone'];
        $name = $_POST['name'];
        
        $stmt = $conn->prepare("INSERT INTO members (phone, name, points) VALUES (?, ?, 0)");
        $stmt->bind_param("ss", $phone, $name);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'member' => ['id' => $conn->insert_id, 'phone' => $phone, 'name' => $name, 'points' => 0]]);
        } else {
            echo json_encode(['success' => false, 'message' => 'ไม่สามารถสมัครสมาชิกได้ อาจมีเบอร์นี้ซ้ำในระบบ']);
        }
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
        $promo_amount = isset($_POST['promo_amount']) ? floatval($_POST['promo_amount']) : 0;
        $is_percent = isset($_POST['is_percent']) ? intval($_POST['is_percent']) : 1;
        $apply_sc = intval($_POST['apply_sc']);
        $apply_tax = intval($_POST['apply_tax']);
        
        // การจัดการแต้มและสมาชิกลูกค้า
        $member_id = (isset($_POST['member_id']) && $_POST['member_id'] > 0) ? intval($_POST['member_id']) : null;
        $points_used = isset($_POST['points_used']) ? intval($_POST['points_used']) : 0;
        
        // ยอดชำระสุทธิหลังหักการใช้แต้ม
        $final_paid = max(0, $total - $points_used);
        
        // ทุก 100 บาทที่จ่ายจริง ได้ 1 แต้ม
        $points_earned = $member_id ? floor($final_paid / 100) : 0;
        
        $sql = "UPDATE orders SET status = 'paid', total_amount = ?, discount_amount = ?, promo_amount = ?, is_percent = ?, apply_sc = ?, apply_tax = ?";
        if ($member_id) {
            $sql .= ", member_id = $member_id, points_earned = $points_earned, points_used = $points_used";
        }
        $sql .= " WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("dddiiii", $final_paid, $discount, $promo_amount, $is_percent, $apply_sc, $apply_tax, $order_id);
        $result = $stmt->execute();

        if ($result) {
            if ($member_id) {
                // หักแต้มที่ใช้ และบวกแต้มที่ได้ใหม่
                $conn->query("UPDATE members SET points = points - $points_used + $points_earned WHERE id = $member_id");
            }

            // ตัดสต็อกสินค้าตามรายการที่มีในบิล
            $items_sql = "SELECT item_name, SUM(quantity) as sum_qty FROM order_items WHERE order_id = ? AND status = 'active' GROUP BY item_name";
            $items_stmt = $conn->prepare($items_sql);
            $items_stmt->bind_param("i", $order_id);
            $items_stmt->execute();
            $items_res = $items_stmt->get_result();
            while ($item = $items_res->fetch_assoc()) {
                $p_stmt = $conn->prepare("SELECT id, inventory_id, ml_per_unit FROM products WHERE name = ?");
                $p_stmt->bind_param("s", $item['item_name']);
                $p_stmt->execute();
                $p_data = $p_stmt->get_result()->fetch_assoc();
                
                if ($p_data) {
                    if ($p_data['inventory_id'] !== null && $p_data['ml_per_unit'] > 0) {
                        // สินค้าลูก (ตัดเป็น ml) ให้ไปตัดจากขวดที่เปิดใช้งานแล้ว (open_ml) เท่านั้น!
                        $ml_to_cut = $p_data['ml_per_unit'] * $item['sum_qty'];
                        $target_id = $p_data['inventory_id'];
                        $conn->query("UPDATE products SET open_ml = open_ml - $ml_to_cut WHERE id = $target_id");
                        
                        // บันทึกประวัติการตัด ml
                        $log_stmt = $conn->prepare("INSERT INTO stock_logs (product_id, qty_change, unit, type) VALUES (?, ?, 'ml', 'sale')");
                        $neg_ml = -$ml_to_cut;
                        $log_stmt->bind_param("ii", $target_id, $neg_ml);
                        $log_stmt->execute();
                    } else {
                        // สินค้าหลัก หรือ สินค้าที่ขายเป็นขวด/ชิ้นเต็ม ให้ตัดสต็อกปกติ
                        $qty_to_cut = $item['sum_qty'];
                        $target_id = ($p_data['inventory_id'] !== null) ? $p_data['inventory_id'] : $p_data['id'];
                        $conn->query("UPDATE products SET stock_qty = stock_qty - $qty_to_cut WHERE id = $target_id");
                        
                        $log_stmt = $conn->prepare("INSERT INTO stock_logs (product_id, qty_change, unit, type) VALUES (?, ?, 'unit', 'sale')");
                        $neg_qty = -$qty_to_cut;
                        $log_stmt->bind_param("ii", $target_id, $neg_qty);
                        $log_stmt->execute();
                    }
                }
            }
        }

        echo json_encode(['success' => $result]);
        exit;
    }

    // ---------------------------------
    // ส่วนจัดการ API โปรโมชั่น (Automated Promotions)
    // ---------------------------------
    if ($_POST['action'] === 'get_active_promotions') {
        $sql = "SELECT * FROM promotions WHERE is_active = 1";
        $res = $conn->query($sql);
        echo json_encode($res->fetch_all(MYSQLI_ASSOC));
        exit;
    }

    if ($_POST['action'] === 'toggle_promotion') {
        $id = intval($_POST['id']);
        $is_active = intval($_POST['is_active']);
        $stmt = $conn->prepare("UPDATE promotions SET is_active = ? WHERE id = ?");
        $stmt->bind_param("ii", $is_active, $id);
        echo json_encode(['success' => $stmt->execute()]);
        exit;
    }

    if ($_POST['action'] === 'delete_promotion') {
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("DELETE FROM promotions WHERE id = ?");
        $stmt->bind_param("i", $id);
        echo json_encode(['success' => $stmt->execute()]);
        exit;
    }

    if ($_POST['action'] === 'save_promotion') {
        $id = !empty($_POST['id']) ? intval($_POST['id']) : 0;
        $name = $_POST['name'];
        $promo_type = $_POST['promo_type'];
        $target_cat = $_POST['target_category'];
        
        // รับค่า Array ของสินค้าที่เลือก และตัดค่าที่ว่างเปล่าออก
        $target_item_arr = isset($_POST['target_item']) ? $_POST['target_item'] : [];
        if (!is_array($target_item_arr)) { $target_item_arr = [$target_item_arr]; }
        $target_item_arr = array_filter($target_item_arr, function($val) { return trim($val) !== ""; });
        $target_item = !empty($target_item_arr) ? json_encode(array_values($target_item_arr), JSON_UNESCAPED_UNICODE) : null;

        $c_qty = !empty($_POST['condition_qty']) ? intval($_POST['condition_qty']) : 0;
        $r_qty = !empty($_POST['reward_qty']) ? intval($_POST['reward_qty']) : 0;
        $d_pct = !empty($_POST['discount_percent']) ? floatval($_POST['discount_percent']) : 0;
        $st = !empty($_POST['start_time']) ? $_POST['start_time'] : null;
        $et = !empty($_POST['end_time']) ? $_POST['end_time'] : null;

        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE promotions SET name=?, promo_type=?, target_category=?, target_item=?, condition_qty=?, reward_qty=?, discount_percent=?, start_time=?, end_time=? WHERE id=?");
            if (!$stmt) { echo json_encode(['success' => false, 'error' => $conn->error]); exit; }
            $stmt->bind_param("ssssiidssi", $name, $promo_type, $target_cat, $target_item, $c_qty, $r_qty, $d_pct, $st, $et, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO promotions (name, promo_type, target_category, target_item, condition_qty, reward_qty, discount_percent, start_time, end_time, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
            if (!$stmt) { echo json_encode(['success' => false, 'error' => $conn->error]); exit; }
            $stmt->bind_param("ssssiidss", $name, $promo_type, $target_cat, $target_item, $c_qty, $r_qty, $d_pct, $st, $et);
        }
        echo json_encode(['success' => $stmt->execute(), 'error' => $conn->error]);
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
function addProduct($name, $price, $category, $stock = 0, $ml = 0, $inv_id = null, $show_on_pos = 1) {
    global $conn;
    $sql = "INSERT INTO products (name, price, category, stock_qty, ml_per_unit, inventory_id, show_on_pos) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sdsiiii", $name, $price, $category, $stock, $ml, $inv_id, $show_on_pos);
    
    if ($stmt->execute()) {
        $new_product_id = $conn->insert_id;
        // หากมีการใส่จำนวนสต็อกเริ่มต้น ให้บันทึกประวัติเข้า stock_logs ด้วย
        if ($stock > 0) {
            $log_stmt = $conn->prepare("INSERT INTO stock_logs (product_id, qty_change, unit, type) VALUES (?, ?, 'unit', 'restock')");
            $log_stmt->bind_param("ii", $new_product_id, $stock);
            $log_stmt->execute();
        }
        return true;
    }
    return false;
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