<?php
require 'config.php';

// ส่วนการรับคำสั่งจากหน้าเว็บ (API Handlers)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // ตรวจสอบและอัปเดตฐานข้อมูลให้รองรับการเก็บต้นทุนในบิล
    $chk_item_cost = $conn->query("SHOW COLUMNS FROM order_items LIKE 'item_cost'");
    if ($chk_item_cost && $chk_item_cost->num_rows == 0) {
        $conn->query("ALTER TABLE order_items ADD item_cost DECIMAL(10,2) DEFAULT 0.00 AFTER price");
    }
    $chk_order_cost = $conn->query("SHOW COLUMNS FROM orders LIKE 'total_cost'");
    if ($chk_order_cost && $chk_order_cost->num_rows == 0) {
        $conn->query("ALTER TABLE orders ADD total_cost DECIMAL(10,2) DEFAULT 0.00 AFTER total_amount");
    }

    if ($_POST['action'] === 'add_item') {
        $order_id = intval($_POST['order_id']);
        $item_name = $_POST['item_name'];
        $price = floatval($_POST['price']);
        
        $check_stmt = $conn->prepare("
            SELECT p.id, p.cost_price, p.ml_per_unit as req_ml, p.inventory_id,
                   inv.id as master_id, inv.cost_price as master_cost, inv.stock_qty as master_stock, inv.ml_per_unit as master_cap, inv.open_ml as master_open
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

        // การคำนวณต้นทุน: ถ้าระบุว่าตัดจากขวดหลัก ให้คิด (ต้นทุนหลัก / ความจุหลัก) * ปริมาณที่ใช้
        $item_cost = floatval($data['cost_price'] ?? 0);
        if ($data && $data['inventory_id'] !== null && $data['master_cap'] > 0 && $data['req_ml'] > 0) {
            $item_cost = (floatval($data['master_cost']) / intval($data['master_cap'])) * intval($data['req_ml']);
        }

        $stmt = $conn->prepare("INSERT INTO order_items (order_id, item_name, price, item_cost, quantity, status) VALUES (?, ?, ?, ?, 1, 'active')");
        $stmt->bind_param("isdd", $order_id, $item_name, $price, $item_cost);
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
        $cost_price = isset($_POST['cost_price']) ? floatval($_POST['cost_price']) : 0;
        $category = $_POST['category'];
        $stock = isset($_POST['stock_qty']) ? intval($_POST['stock_qty']) : 0;
        $ml = isset($_POST['ml_per_unit']) ? intval($_POST['ml_per_unit']) : 0;
        $inv_id = (!empty($_POST['inventory_id'])) ? intval($_POST['inventory_id']) : null;
        $show_on_pos = isset($_POST['show_on_pos']) ? intval($_POST['show_on_pos']) : 1;
        
        $result = addProduct($name, $price, $cost_price, $category, $stock, $ml, $inv_id, $show_on_pos);
        echo json_encode(['success' => $result]);
        exit;
    }

    if ($_POST['action'] === 'edit_product') {
        $id = intval($_POST['id']);
        
        // ดึงข้อมูลเดิมก่อนเพื่อเปรียบเทียบว่าสต็อกเปลี่ยนแปลงไปเท่าไหร่
        $old_stmt = $conn->prepare("SELECT stock_qty, open_ml, cost_price FROM products WHERE id = ?");
        $old_stmt->bind_param("i", $id);
        $old_stmt->execute();
        $old_data = $old_stmt->get_result()->fetch_assoc();

        $name = $_POST['name'];
        $category = $_POST['category'];
        $price = floatval($_POST['price']);
        $cost_price = isset($_POST['cost_price']) ? floatval($_POST['cost_price']) : 0;
        $stock_qty = isset($_POST['stock_qty']) ? intval($_POST['stock_qty']) : 0;
        $ml_per_unit = isset($_POST['ml_per_unit']) ? intval($_POST['ml_per_unit']) : 0;
        $open_ml = isset($_POST['open_ml']) ? intval($_POST['open_ml']) : 0;
        $inv_id = (!empty($_POST['inventory_id'])) ? intval($_POST['inventory_id']) : null;
        $show_on_pos = isset($_POST['show_on_pos']) ? intval($_POST['show_on_pos']) : 1;
        
        $stmt = $conn->prepare("UPDATE products SET name=?, category=?, price=?, cost_price=?, stock_qty=?, ml_per_unit=?, open_ml=?, inventory_id=?, show_on_pos=? WHERE id=?");
        $stmt->bind_param("ssddiiiiii", $name, $category, $price, $cost_price, $stock_qty, $ml_per_unit, $open_ml, $inv_id, $show_on_pos, $id);
        
        $success = $stmt->execute();
        
        // บันทึกประวัติการเปลี่ยนแปลงสต็อก
        if ($success && $old_data) {
            $diff_qty = $stock_qty - $old_data['stock_qty'];
            if ($diff_qty != 0) {
                $type_qty = ($diff_qty > 0) ? 'restock' : 'sale';
                $log_stmt = $conn->prepare("INSERT INTO stock_logs (product_id, qty_change, unit, type) VALUES (?, ?, 'unit', ?)");
                $log_stmt->bind_param("iis", $id, $diff_qty, $type_qty);
                $log_stmt->execute();
            }
            
            $diff_ml = $open_ml - $old_data['open_ml'];
            if ($diff_ml != 0) {
                $type_ml = ($diff_ml > 0) ? 'restock' : 'sale';
                $log_stmt = $conn->prepare("INSERT INTO stock_logs (product_id, qty_change, unit, type) VALUES (?, ?, 'ml', ?)");
                $log_stmt->bind_param("iis", $id, $diff_ml, $type_ml);
                $log_stmt->execute();
            }

            $cost_diff = $cost_price - $old_data['cost_price'];
            if ($cost_diff != 0) {
                // เราจะใช้ type 'restock' สำหรับการเปลี่ยนแปลงต้นทุน
                $log_stmt = $conn->prepare("INSERT INTO stock_logs (product_id, qty_change, unit, type, notes) VALUES (?, ?, 'cost', 'restock', ?)");
                $note = "Cost changed from {$old_data['cost_price']} to {$cost_price}";
                $log_stmt->bind_param("ids", $id, $cost_diff, $note);
                $log_stmt->execute();
            }
        }
        
        echo json_encode(['success' => $success, 'error' => $conn->error]);
        exit;
    }

    if ($_POST['action'] === 'delete_product') {
        $id = intval($_POST['product_id']);
        
        // ดึงข้อมูลก่อนลบเพื่อเก็บ Log ว่าสินค้าถูกนำออกไปเท่าไหร่
        $old_stmt = $conn->prepare("SELECT stock_qty, open_ml FROM products WHERE id = ?");
        $old_stmt->bind_param("i", $id);
        $old_stmt->execute();
        $old_data = $old_stmt->get_result()->fetch_assoc();

        if ($old_data) {
            if ($old_data['stock_qty'] > 0) {
                $diff_qty = -$old_data['stock_qty'];
                $log_stmt = $conn->prepare("INSERT INTO stock_logs (product_id, qty_change, unit, type) VALUES (?, ?, 'unit', 'sale')");
                $log_stmt->bind_param("ii", $id, $diff_qty);
                $log_stmt->execute();
            }
            if ($old_data['open_ml'] > 0) {
                $diff_ml = -$old_data['open_ml'];
                $log_stmt = $conn->prepare("INSERT INTO stock_logs (product_id, qty_change, unit, type) VALUES (?, ?, 'ml', 'sale')");
                $log_stmt->bind_param("ii", $id, $diff_ml);
                $log_stmt->execute();
            }
        }
        
        // ปลดการเชื่อมโยงสินค้าลูกที่ผูกกับสินค้านี้อยู่ (ถ้ามี) เพื่อไม่ให้บัค
        $conn->query("UPDATE products SET inventory_id = NULL WHERE inventory_id = $id");
        
        // ลบสินค้าหลัก
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

        // 2. บันทึกประวัติการเติม/ลดสต็อก
        if ($success && $quantity != 0) {
            $type = ($quantity > 0) ? 'restock' : 'sale';
            $log_stmt = $conn->prepare("INSERT INTO stock_logs (product_id, qty_change, unit, type) VALUES (?, ?, 'unit', ?)");
            $log_stmt->bind_param("iis", $product_id, $quantity, $type);
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
        $sql = "SELECT p.name, s.qty_change, s.unit, s.type, s.created_at, s.product_id 
                FROM stock_logs s
                LEFT JOIN products p ON s.product_id = p.id
                WHERE DATE(s.created_at) = ?
                ORDER BY s.created_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $date);
        $stmt->execute();
        
        $result = $stmt->get_result();
        echo json_encode($result->fetch_all(MYSQLI_ASSOC));
        exit;
    }

    if ($_POST['action'] === 'get_daily_orders') {
        $date = $_POST['date'];
        $cashier = isset($_POST['cashier']) ? $_POST['cashier'] : '';
        
        $sql = "SELECT id, receipt_no, table_number, total_amount, total_cost, created_at, cashier_name 
                FROM orders 
                WHERE DATE(created_at) = ? AND status = 'paid'";
                
        if ($cashier !== '') {
            $sql .= " AND cashier_name = ?";
            $sql .= " ORDER BY created_at DESC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $date, $cashier);
        } else {
            $sql .= " ORDER BY created_at DESC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $date);
        }
        $stmt->execute();
        
        echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
        exit;
    }

    if ($_POST['action'] === 'get_monthly_orders') {
        $month = $_POST['month'];
        $cashier = isset($_POST['cashier']) ? $_POST['cashier'] : '';
        
        $sql = "SELECT id, receipt_no, table_number, total_amount, total_cost, created_at, cashier_name 
                FROM orders 
                WHERE DATE_FORMAT(created_at, '%Y-%m') = ? AND status = 'paid'";
                
        if ($cashier !== '') {
            $sql .= " AND cashier_name = ?";
            $sql .= " ORDER BY created_at DESC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $month, $cashier);
        } else {
            $sql .= " ORDER BY created_at DESC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $month);
        }
        $stmt->execute();
        
        echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
        exit;
    }

    if ($_POST['action'] === 'get_active_orders') {
        try {
            // ตรวจสอบและสร้างคอลัมน์ receipt_no อัตโนมัติ ป้องกัน Error ทันทีที่เปิดหน้าเว็บ
            $chk_col = $conn->query("SHOW COLUMNS FROM orders LIKE 'receipt_no'");
            if ($chk_col && $chk_col->num_rows == 0) {
                $conn->query("ALTER TABLE orders ADD receipt_no VARCHAR(20) NULL AFTER id");
            }

            $sql = "SELECT id, receipt_no, table_number 
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
        
        // คำนวณต้นทุนรวมของบิลนี้
        $cost_res = $conn->query("SELECT SUM(item_cost * quantity) as sum_cost FROM order_items WHERE order_id = $order_id AND status = 'active'");
        $total_cost = 0;
        if ($cost_res && $cost_row = $cost_res->fetch_assoc()) {
            $total_cost = floatval($cost_row['sum_cost']);
        }

        // ตรวจสอบและสร้างคอลัมน์ cashier_name อัตโนมัติหากยังไม่มี
        $chk_col_cashier = $conn->query("SHOW COLUMNS FROM orders LIKE 'cashier_name'");
        if ($chk_col_cashier && $chk_col_cashier->num_rows == 0) {
            $conn->query("ALTER TABLE orders ADD cashier_name VARCHAR(100) NULL AFTER table_number");
        }
        $cashier_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Unknown';

        $sql = "UPDATE orders SET status = 'paid', total_amount = ?, total_cost = ?, discount_amount = ?, promo_amount = ?, is_percent = ?, apply_sc = ?, apply_tax = ?, cashier_name = ?";
        if ($member_id) {
            $sql .= ", member_id = $member_id, points_earned = $points_earned, points_used = $points_used";
        }
        $sql .= " WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ddddiiisi", $final_paid, $total_cost, $discount, $promo_amount, $is_percent, $apply_sc, $apply_tax, $cashier_name, $order_id);
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
    // ส่วนจัดการหมวดหมู่ (Categories)
    // ---------------------------------
    if ($_POST['action'] === 'save_category') {
        $old_name = $_POST['old_category_name'];
        $new_name = $_POST['new_category_name'];

        if (empty($new_name)) {
            echo json_encode(['success' => false, 'error' => 'ชื่อหมวดหมู่ห้ามว่าง']);
            exit;
        }

        // ถ้ามี old_name แปลว่าเป็นการแก้ไข
        if (!empty($old_name)) {
            $stmt = $conn->prepare("UPDATE products SET category = ? WHERE category = ?");
            $stmt->bind_param("ss", $new_name, $old_name);
            echo json_encode(['success' => $stmt->execute()]);
        }
        // ถ้าไม่มี old_name ถือว่าเป็นการเพิ่มหมวดหมู่ใหม่ (ซึ่งจริงๆ ไม่ต้องทำอะไร เพราะหมวดหมู่จะถูกสร้างตอนเพิ่มสินค้า)
        // แค่ส่งค่า success กลับไปเพื่อให้ Modal ปิด
        else {
            echo json_encode(['success' => true]);
        }
        exit;
    }
    if ($_POST['action'] === 'delete_category') {
        $name = $_POST['category_name'];
        $stmt = $conn->prepare("UPDATE products SET category = NULL WHERE category = ?");
        $stmt->bind_param("s", $name);
        echo json_encode(['success' => $stmt->execute()]);
        exit;
    }

    // ---------------------------------
    // ส่วนจัดการผู้ใช้งาน (Users / Staff)
    // ---------------------------------
    if ($_POST['action'] === 'change_password') {
        $user_id = intval($_POST['user_id']);
        $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $new_password, $user_id);
        echo json_encode(['success' => $stmt->execute()]);
        exit;
    }

    if ($_POST['action'] === 'add_user') {
        $username = $_POST['username'];
        $name = $_POST['name'];
        $role = $_POST['role'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("INSERT INTO users (username, password, role, name) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $username, $password, $role, $name);
        echo json_encode(['success' => $stmt->execute(), 'error' => $conn->error]);
        exit;
    }

    if ($_POST['action'] === 'delete_user') {
        $user_id = intval($_POST['user_id']);
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        echo json_encode(['success' => $stmt->execute()]);
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
    
    // ตรวจสอบและสร้างคอลัมน์ receipt_no อัตโนมัติหากยังไม่มี
    $chk_col = $conn->query("SHOW COLUMNS FROM orders LIKE 'receipt_no'");
    if ($chk_col && $chk_col->num_rows == 0) {
        $conn->query("ALTER TABLE orders ADD receipt_no VARCHAR(20) NULL AFTER id");
    }
    
    // รันเลขบิลรายวัน โดยจะเริ่มวันใหม่ตอน "06:00 โมงเช้า" (หักลบ 6 ชม.)
    $date_prefix = date('Y-m-d', strtotime('-6 hours'));
    
    // ค้นหาบิลล่าสุดของวันนั้นๆ (อิงตามเวลาตี 6)
    $sql_last = "SELECT receipt_no FROM orders WHERE DATE(DATE_SUB(created_at, INTERVAL 6 HOUR)) = '$date_prefix' AND receipt_no IS NOT NULL ORDER BY id DESC LIMIT 1";
    $res_last = $conn->query($sql_last);
    $next_queue = 1;
    if ($res_last && $res_last->num_rows > 0) {
        $last_no = $res_last->fetch_assoc()['receipt_no'];
        // รองรับกรณีมีข้อมูลเก่าแบบเดิมที่ติดขีด (-) มาด้วย
        if (strpos($last_no, '-') !== false) {
            $parts = explode('-', $last_no);
            $next_queue = intval(end($parts)) + 1;
        } else {
            $next_queue = intval($last_no) + 1;
        }
    }
    $receipt_no = sprintf("%03d", $next_queue); // สร้างเป็น 001, 002
    
    $sql = "INSERT INTO orders (receipt_no, table_number, status, total_amount, apply_tax, apply_sc, is_percent) VALUES (?, ?, 'active', 0, 1, 1, 1)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception($conn->error);
    }
    $stmt->bind_param("ss", $receipt_no, $table_number);
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
    $sql = "INSERT INTO order_items (order_id, item_name, price, item_cost, quantity, status, created_by) VALUES (?, ?, ?, 0, ?, 'active', ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isddii", $order_id, $item_name, $price, $quantity, $emp_id);
    return $stmt->execute();
}

// เพิ่มสินค้าใหม่ลงในฐานข้อมูล (Master Data)
function addProduct($name, $price, $cost_price, $category, $stock = 0, $ml = 0, $inv_id = null, $show_on_pos = 1) {
    global $conn;
    $sql = "INSERT INTO products (name, price, cost_price, category, stock_qty, ml_per_unit, inventory_id, show_on_pos) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sddsiiii", $name, $price, $cost_price, $category, $stock, $ml, $inv_id, $show_on_pos);
    
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