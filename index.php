<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bar POS System</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;700&display=swap" rel="stylesheet">

<script>
    let currentOrder = {
        id: null,
        table: '',
        items: [],
        subtotal: 0,
        applyTax: true,
        applySC: true,
        discount: 0,
        isPercent: true
    };

    let currentOrderId = null; 

    // ฟังก์ชันสลับการคำนวณภาษี/SC
    function toggleTax() {
        currentOrder.applyTax = !currentOrder.applyTax;
        syncOrderSettings();
        updateDisplay();
    }

    function toggleSC() {
        currentOrder.applySC = !currentOrder.applySC;
        syncOrderSettings();
        updateDisplay();
    }

    // กำหนดส่วนลดแบบกำหนดเอง
    function setDiscount(value, type = 'percent') {
        // ถ้ากดซ้ำค่าเดิม ให้ถือว่าเป็นการปิดส่วนลด (Toggle Off)
        if (currentOrder.discount === value && currentOrder.isPercent === (type === 'percent')) {
            currentOrder.discount = 0;
        } else {
            currentOrder.discount = value;
            currentOrder.isPercent = (type === 'percent');
        }
        syncOrderSettings();
        updateDisplay();
    }

    // ฟังก์ชันส่งค่าการตั้งค่าบิลไปบันทึกในฐานข้อมูลทันที
    async function syncOrderSettings() {
        if (!currentOrderId) return;
        const formData = new FormData();
        formData.append('action', 'update_order_settings');
        formData.append('order_id', currentOrderId);
        formData.append('discount', currentOrder.discount);
        formData.append('is_percent', currentOrder.isPercent ? 1 : 0);
        formData.append('apply_sc', currentOrder.applySC ? 1 : 0);
        formData.append('apply_tax', currentOrder.applyTax ? 1 : 0);

        try {
            const response = await fetch('pos_action.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (!result.success) {
                console.error("Failed to sync order settings:", result);
                // alert("เกิดข้อผิดพลาดในการบันทึกการตั้งค่าบิล"); // อาจจะเปิดใช้งานเมื่อระบบเสถียร
            }
        } catch (e) { 
            console.error("Sync error:", e); 
        }
    }

    // สั่งพิมพ์ Ticket (รองรับความร้อน)
    async function printTicket(orderId) {
        if (!orderId) { alert("กรุณาเลือกบิลก่อน"); return; }
        await syncOrderSettings(); // บันทึกสถานะ VAT/SC/ส่วนลด ล่าสุดก่อน
        window.open('generate_tax_invoice.php?order_id=' + orderId, '_blank');
    }

    // ออกใบกำกับภาษี (เชื่อมโยงกับบิล)
    async function generateTaxInvoice(orderId) {
        if (!orderId) { alert("กรุณาเลือกบิลก่อน"); return; }
        await syncOrderSettings(); // บันทึกสถานะ VAT/SC/ส่วนลด ล่าสุดก่อน
        window.open('generate_tax_invoice.php?order_id=' + orderId, '_blank');
    }

    // ฟังก์ชันเพิ่มเมนูพิเศษ (กำหนดราคาเอง)
    function openSpecialModal() {
        document.getElementById('specialModal').style.display = 'flex';
    }

    function closeSpecialModal() {
        document.getElementById('specialModal').style.display = 'none';
        document.getElementById('specialName').value = '';
        document.getElementById('specialPrice').value = '';
        document.getElementById('specialSpirit').value = '';
        document.getElementById('specialMl').value = '';
        document.getElementById('specialSpirit2').value = '';
        document.getElementById('specialMl2').value = '';
        document.getElementById('specialSpirit3').value = '';
        document.getElementById('specialMl3').value = '';
    }

    function openProductModal() { 
        document.getElementById('productModal').style.display = 'flex';
        updateVariationInputs(); // รีเซ็ตช่องกรอกราคาเมื่อเปิด
    }
    function closeProductModal() { document.getElementById('productModal').style.display = 'none'; }

    // ฟังก์ชันเปลี่ยนช่องกรอกราคาตามหมวดหมู่
    function updateVariationInputs() {
        const category = document.getElementById('newCategory').value;
        const container = document.getElementById('variation-inputs');
        container.innerHTML = '';

        let inputs = '';
        if (category === 'Beer') {
            inputs = `
                <div class="input-group"><label>ราคา แก้วเล็ก:</label><input type="number" class="v-price" data-suffix="แก้วเล็ก" placeholder="0.00"></div>
                <div class="input-group"><label>ราคา แก้วใหญ่:</label><input type="number" class="v-price" data-suffix="แก้วใหญ่" placeholder="0.00"></div>
                <div class="input-group"><label>ราคา ขวด:</label><input type="number" class="v-price" data-suffix="ขวด" placeholder="0.00"></div>`;
        } else if (category === 'Wine') {
            inputs = `
                <div class="input-group"><label>ราคา แบบแก้ว:</label><input type="number" class="v-price" data-suffix="แก้ว" placeholder="0.00"></div>
                <div class="input-group"><label>ราคา แบบขวด:</label><input type="number" class="v-price" data-suffix="ขวด" placeholder="0.00"></div>`;
        } else if (category === 'Cocktail') {
            inputs = `
                <div class="input-group"><label>ราคา แบบแก้ว:</label><input type="number" class="v-price" data-suffix="แก้ว" placeholder="0.00"></div>`;
        } else if (category === 'Liquor') {
            inputs = `<div class="input-group"><label>ราคาต่อ Shot:</label><input type="number" class="v-price" data-suffix="Shot" placeholder="0.00"></div>`;
        } else {
            inputs = `<div class="input-group"><label>ราคา:</label><input type="number" class="v-price" data-suffix="" placeholder="0.00"></div>`;
        }
        container.innerHTML = inputs;
    }

    async function submitNewProduct() {
        const name = document.getElementById('newName').value;
        const category = document.getElementById('newCategory').value;
        const priceInputs = document.querySelectorAll('.v-price');

        if (!name) { alert("กรุณาระบุชื่อเมนู"); return; }

        let successCount = 0;
        for (let input of priceInputs) {
            const price = input.value;
            const suffix = input.getAttribute('data-suffix');
            
            if (price && price > 0) {
                const fullName = suffix ? `${name} (${suffix})` : name;
                
                const formData = new FormData();
                formData.append('action', 'add_product');
                formData.append('name', fullName);
                formData.append('price', price);
                formData.append('category', category);

                try {
                    const response = await fetch('pos_action.php', { method: 'POST', body: formData });
                    const result = await response.json();
                    if (result.success) successCount++;
                } catch (e) { console.error(e); }
            }
        }

        if (successCount > 0) {
            alert(`บันทึกสำเร็จ ${successCount} รายการ`);
            closeProductModal();
            loadProducts(category);
        } else {
            alert("กรุณาระบุราคาอย่างน้อย 1 รายการ");
        }
    }

    // ฟังก์ชันอัปเดตการแสดงผลบนหน้าจอ
    function updateDisplay() {
        const itemContainer = document.getElementById('order-items');
        itemContainer.innerHTML = '';
        let subtotal = 0;

        currentOrder.items.forEach((item, index) => {
            subtotal += item.price * item.quantity;
            itemContainer.innerHTML += `
                <div class="order-item-row" style="display:flex; align-items:center; justify-content:space-between; padding: 12px 0; border-bottom: 1px solid #252a3a;">
                    <div style="flex:1; display:flex; flex-direction:column;">
                        <span class="item-name" style="font-size:14px; margin-bottom:5px;">${item.name}</span>
                        <div style="display:flex; align-items:center; gap:12px;">
                            <button onclick="removeFromOrder('${item.name.replace(/'/g, "\\'")}')" 
                                style="background:#e74c3c; color:white; border:none; border-radius:4px; width:28px; height:28px; cursor:pointer; font-weight:bold;">-</button>
                            <b style="color:#00d4ff; min-width:20px; text-align:center;">${item.quantity}</b>
                            <button onclick="addToOrder('${item.name.replace(/'/g, "\\'")}', ${item.price})" 
                                style="background:#2ecc71; color:white; border:none; border-radius:4px; width:28px; height:28px; cursor:pointer; font-weight:bold;">+</button>
                        </div>
                    </div>
                    <span class="item-price">฿${(item.price * item.quantity).toLocaleString(undefined, {minimumFractionDigits: 2})}</span>
                </div>`;
        });

        let sc = currentOrder.applySC ? (subtotal * 0.10) : 0;
        let tax = currentOrder.applyTax ? ((subtotal + sc) * 0.07) : 0;
        let discountVal = parseFloat(currentOrder.discount || 0);
        let discount = currentOrder.isPercent ? (subtotal * (discountVal / 100)) : discountVal;
        let total = Math.max(0, (subtotal + sc + tax) - discount);

        document.getElementById('table-display').innerText = currentOrder.table || '--';
        document.getElementById('order-id-display').innerText = currentOrder.id || '--';
        
        // อัปเดตตัวเลขสรุปผล
        document.getElementById('summary-subtotal').innerText = '฿' + subtotal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('summary-discount').innerText = '- ฿' + discount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('summary-sc').innerText = '฿' + sc.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('summary-tax').innerText = '฿' + tax.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('summary-ispercent').innerText = currentOrder.isPercent ? '(' + currentOrder.discount + '%)' : '';
        document.getElementById('summary-total').innerText = total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});

        // อัปเดตสถานะปุ่ม (Visual Toggle)
        updateButtonState('btn-tax', currentOrder.applyTax);
        updateButtonState('btn-sc', currentOrder.applySC);
        updateButtonState('btn-disc-5', currentOrder.discount === 5 && currentOrder.isPercent);
        updateButtonState('btn-disc-10', currentOrder.discount === 10 && currentOrder.isPercent);
    }

    function updateButtonState(id, isActive) {
        const btn = document.getElementById(id);
        if (!btn) return;
        btn.style.background = isActive ? '#00d4ff' : '#34495e';
        btn.style.color = isActive ? 'black' : 'white';
        btn.style.fontWeight = isActive ? 'bold' : 'normal';
    }

    async function loadProducts(category) {
        const formData = new FormData();
        formData.append('action', 'get_products');
        formData.append('category', category);

        const response = await fetch('pos_action.php', { method: 'POST', body: formData });
        const products = await response.json();
        
        const container = document.getElementById('product-display');
        container.innerHTML = '';
        
        products.forEach(p => {
            const btn = document.createElement('button');
            btn.className = 'product-btn';
            const stockColor = p.stock_qty <= 5 ? '#ff4d4d' : '#2ecc71';
            btn.innerHTML = `${p.name}<br><b>฿${p.price}</b><br>
                             <small style="color:${stockColor}">Stock: ${p.stock_qty}</small>`;
            btn.onclick = () => addToOrder(p.name, p.price);
            container.appendChild(btn);
        });
    }

    function openTableModal() {
        document.getElementById('tableSelectionModal').style.display = 'flex';
    }

    async function confirmTable() {
        const tableNum = document.getElementById('tableInput').value;
        if (!tableNum) { alert("กรุณาระบุหมายเลขโต๊ะ"); return; }

        const formData = new FormData();
        formData.append('action', 'create_order');
        formData.append('table_number', tableNum);

        try {
            const response = await fetch('pos_action.php', { method: 'POST', body: formData });
            const text = await response.text();
            
            let result;
            try {
                result = JSON.parse(text);
            } catch (e) {
                throw new Error("PHP Output Error: " + text);
            }

            if (result && result.success) {
                currentOrderId = result.order_id;
                currentOrder.id = result.order_id;
                currentOrder.table = tableNum;
                currentOrder.items = [];
                updateDisplay();
                loadActiveOrders();
                document.getElementById('tableSelectionModal').style.display = 'none';
                document.getElementById('tableInput').value = '';
            } else {
                alert("ข้อผิดพลาดจากระบบ: " + (result ? result.message : 'Unknown error'));
            }
        } catch (error) {
            console.error("Fetch Error:", error);
            alert("เกิดข้อผิดพลาด: " + error.message);
        }
    }

    async function addToOrder(name, price) {
        if (!currentOrderId) { openTableModal(); return; }

        // 1. อัปเดต UI ทันทีเพื่อให้รู้ว่ากดติดแล้ว
        const existingItem = currentOrder.items.find(item => item.name === name);
        if (existingItem) {
            existingItem.quantity += 1;
        } else {
            currentOrder.items.push({ name: name, price: parseFloat(price || 0), quantity: 1 });
        }
        updateDisplay();

        // 2. บันทึกลงฐานข้อมูลทันทีเพื่อให้ข้อมูลไม่หายเมื่อ Refresh
        const formData = new FormData();
        formData.append('action', 'add_item');
        formData.append('order_id', currentOrderId);
        formData.append('item_name', name);
        formData.append('price', price);

        try {
            const response = await fetch('pos_action.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (!result.success) {
                alert(result.message);
                location.reload(); // รีโหลดเพื่ออัปเดตสต็อกล่าสุด
            }
        } catch (e) { 
            console.error("Failed to save item:", e); 
        }
    }

    async function removeFromOrder(name) {
        if (!currentOrderId) return;
        
        const itemIndex = currentOrder.items.findIndex(item => item.name === name);
        if (itemIndex === -1) return;

        // 1. อัปเดต UI ทันที
        if (currentOrder.items[itemIndex].quantity > 1) {
            currentOrder.items[itemIndex].quantity -= 1;
        } else {
            currentOrder.items.splice(itemIndex, 1);
        }
        updateDisplay();

        // 2. ส่งคำสั่งไปลบในฐานข้อมูล (ลบออก 1 record ล่าสุด)
        const formData = new FormData();
        formData.append('action', 'remove_item');
        formData.append('order_id', currentOrderId);
        formData.append('item_name', name);

        try {
            await fetch('pos_action.php', { method: 'POST', body: formData });
        } catch (e) { console.error("Failed to remove item:", e); }
    }

    async function submitSpecialMenu() {
        const name = document.getElementById('specialName').value;
        const price = parseFloat(document.getElementById('specialPrice').value);
        const spirit = document.getElementById('specialSpirit').value;
        const ml = document.getElementById('specialMl').value;
        const spirit2 = document.getElementById('specialSpirit2').value;
        const ml2 = document.getElementById('specialMl2').value;
        const spirit3 = document.getElementById('specialSpirit3').value;
        const ml3 = document.getElementById('specialMl3').value;
        
        if (!name || isNaN(price)) {
            alert("กรุณากรอกข้อมูลให้ครบถ้วนและถูกต้อง");
            return;
        }

        // รวมข้อมูลเหล้าเข้ากับชื่อรายการ
        let finalName = name;
        let ingredients = [];
        if (spirit && ml) ingredients.push(`${spirit} ${ml}ml`);
        if (spirit2 && ml2) ingredients.push(`${spirit2} ${ml2}ml`);
        if (spirit3 && ml3) ingredients.push(`${spirit3} ${ml3}ml`);

        if (ingredients.length > 0) {
            finalName += ` (${ingredients.join(' + ')})`;
        }

        // 1. ตรวจสอบและเพิ่มจำนวนใน UI
        const existingItem = currentOrder.items.find(item => item.name === finalName);
        if (existingItem) {
            existingItem.quantity += 1;
        } else {
            currentOrder.items.push({ name: finalName, price: price, quantity: 1 });
        }
        
        // 2. ส่งข้อมูลไปบันทึกลง Database (AJAX)
        const formData = new FormData();
        formData.append('action', 'add_special');
        formData.append('order_id', currentOrderId);
        formData.append('item_name', finalName);
        formData.append('price', price);

        try {
            const response = await fetch('pos_action.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            if (result.success) {
                console.log("บันทึกสำเร็จ");
            }
        } catch (error) {
            console.error("Error saving item:", error);
        }

        updateDisplay();
        closeSpecialModal();
    }

    async function voidAll(orderId) { 
        if(!confirm('ยืนยันการยกเลิกบิล?')) return;
        
        const formData = new FormData();
        formData.append('action', 'void_order');
        formData.append('order_id', orderId);
        
        const response = await fetch('pos_action.php', { method: 'POST', body: formData });
        const result = await response.json();
        if(result.success) {
            location.reload();
        }
    }

    async function checkoutOrder(orderId) {
        if (currentOrder.items.length === 0) {
            alert("ไม่มีรายการสินค้าในบิล");
            return;
        }

        if (!confirm('ยืนยันการชำระเงินและปิดบิลนี้?')) return;

        // คำนวณยอดรวมสุดท้ายจากหน้าจอ
        let subtotal = currentOrder.items.reduce((acc, item) => acc + (item.price * item.quantity), 0);
        let sc = currentOrder.applySC ? (subtotal * 0.10) : 0;
        let tax = currentOrder.applyTax ? ((subtotal + sc) * 0.07) : 0;
        let discountVal = parseFloat(currentOrder.discount || 0);
        let discount = currentOrder.isPercent ? (subtotal * (discountVal / 100)) : discountVal;
        let total = Math.max(0, (subtotal + sc + tax) - discount);

        const formData = new FormData();
        formData.append('action', 'checkout_order');
        formData.append('order_id', orderId);
        formData.append('total', total);
        formData.append('discount', currentOrder.discount);
        formData.append('apply_sc', currentOrder.applySC ? 1 : 0);
        formData.append('apply_tax', currentOrder.applyTax ? 1 : 0);

        try {
            const response = await fetch('pos_action.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.success) {
                alert("ชำระเงินสำเร็จ! บิลถูกบันทึกเรียบร้อย");
                location.reload(); // รีโหลดเพื่อเคลียร์คิวบิล
            }
        } catch (error) {
            console.error("Checkout Error:", error);
        }
    }

    // ฟังก์ชันเปิดหน้าต่างแยกบิล
    function splitBillAction() {
        if (currentOrder.items.length === 0) { alert("ไม่มีรายการให้แยก"); return; }
        const container = document.getElementById('split-items-list');
        container.innerHTML = '';
        
        currentOrder.items.forEach((item, index) => {
            for(let i=0; i < item.quantity; i++) {
                container.innerHTML += `
                    <div style="margin-bottom:10px; display:flex; align-items:center; background:#252a3a; padding:10px; border-radius:8px;">
                        <input type="checkbox" class="split-item-check" value="${item.name}" style="width:20px; height:20px; margin-right:15px;">
                        <label>${item.name} - ฿${item.price}</label>
                    </div>`;
            }
        });
        document.getElementById('splitModal').style.display = 'flex';
    }

    async function executeSplit() {
        const selectedItems = Array.from(document.querySelectorAll('.split-item-check:checked')).map(el => el.value);
        if (selectedItems.length === 0) { alert("กรุณาเลือกอย่างน้อย 1 รายการ"); return; }

        const formData = new FormData();
        formData.append('action', 'split_bill_process');
        formData.append('old_order_id', currentOrderId);
        formData.append('table_number', currentOrder.table); // ส่งเลขโต๊ะไปด้วยตามที่ pos_action ต้องการ
        formData.append('items', JSON.stringify(selectedItems));

        const response = await fetch('pos_action.php', { method: 'POST', body: formData });
        const result = await response.json();
        
        if (result.success) {
            alert("แยกบิลสำเร็จ! เลขที่บิลใหม่คือ: " + result.new_order_id);
            // ลบรายการที่แยกออกไปจากหน้าจอปัจจุบัน
            selectedItems.forEach(name => {
                const idx = currentOrder.items.findIndex(item => item.name === name);
                if (idx > -1) {
                    if (currentOrder.items[idx].quantity > 1) currentOrder.items[idx].quantity--;
                    else currentOrder.items.splice(idx, 1);
                }
            });
            updateDisplay();
            document.getElementById('splitModal').style.display = 'none';
            loadActiveOrders(); // โหลดรายการบิลใหม่หลังจากแยกบิลสำเร็จ
        }
    }

    // ฟังก์ชันดึงรายการบิลที่ยังไม่ได้เช็คบิล (Status = active)
    async function loadActiveOrders() {
        const formData = new FormData();
        formData.append('action', 'get_active_orders');
        
        try {
            const response = await fetch('pos_action.php', { method: 'POST', body: formData });
            const text = await response.text();
            let orders;
            
            try {
                orders = JSON.parse(text);
            } catch (e) {
                console.error("Server Response:", text);
                document.getElementById('active-orders-list').innerHTML = `<b style="color:red;">Error: ข้อมูลจาก Server ไม่ถูกต้อง (${text.substring(0, 50)}...)</b>`;
                return;
            }

            if (orders.error) {
                document.getElementById('active-orders-list').innerHTML = '<b style="color:red;">SQL Error: ' + orders.error + '</b>';
                return;
            }
            
            const container = document.getElementById('active-orders-list');
            container.innerHTML = '<b style="color:#00d4ff; margin-right:10px;">บิล (Orders):</b>';
            
            orders.forEach(order => {
                const btn = document.createElement('button');
                btn.innerText = 'โต๊ะ ' + (order.table_number || '-') + ' (#' + order.id + ')';
                btn.className = (order.id == currentOrderId) ? 'order-tab active' : 'order-tab';
                btn.onclick = () => switchOrder(order.id);
                container.appendChild(btn);
            });
        } catch (error) {
            document.getElementById('active-orders-list').innerHTML = '<b style="color:red;">ไม่สามารถเชื่อมต่อ Server ได้</b>';
        }
    }

    // ฟังก์ชันสำหรับคลิกที่เลขบิลเพื่อดูรายละเอียด
    async function switchOrder(orderId) {
        currentOrderId = orderId;
        const formData = new FormData();
        formData.append('action', 'get_order_details');
        formData.append('order_id', orderId);
        
        const response = await fetch('pos_action.php', { method: 'POST', body: formData });
        const data = await response.json();
        
        currentOrder.id = orderId;
        currentOrder.table = data.order_info ? data.order_info.table_number : '--';
        currentOrder.applyTax = data.order_info ? (parseInt(data.order_info.apply_tax) === 1) : true;
        currentOrder.applySC = data.order_info ? (parseInt(data.order_info.apply_sc) === 1) : true;
        currentOrder.discount = data.order_info ? parseFloat(data.order_info.discount_amount || 0) : 0;
        currentOrder.isPercent = data.order_info ? (parseInt(data.order_info.is_percent) === 1) : true;
        currentOrder.items = data.items ? data.items.map(i => ({...i, price: parseFloat(i.price), quantity: parseInt(i.quantity)})) : [];
        updateDisplay();
        loadActiveOrders(); // อัปเดตสีปุ่มบิลที่เลือก
    }

    // สั่งให้โหลดข้อมูลเมื่อเปิดหน้าจอ
    window.onload = () => {
        loadActiveOrders();
        loadProducts('Beer'); // โหลดสินค้าหมวดเบียร์รอไว้เลย
    };
</script>

<!-- ส่วนเมนูนำทาง (Navbar) -->
<div style="width: 100%; max-width: 1200px; display: flex; justify-content: space-between; align-items: center; margin: 0 auto 10px auto; padding: 10px 0;">
    <h1 style="color: #00d4ff; margin: 0;">BAR POS</h1>
    <nav>
        <a href="index.php" style="color: #00d4ff; text-decoration: none; font-weight: bold; margin-left: 20px;">หน้าขาย (POS)</a>
        <a href="sales_report.php" style="color: white; text-decoration: none; margin-left: 20px;">รายงานยอดขาย</a>
        <a href="stock_report.php" style="color: white; text-decoration: none; margin-left: 20px;">จัดการสต็อก</a>
    </nav>
</div>

<!-- ส่วนแสดงรายการบิลที่ค้างอยู่ -->
<div id="active-orders-list" style="padding: 15px; background: #1a1d29; margin: 0 auto 15px auto; border-radius: 12px; width: 100%; max-width: 1200px; box-sizing: border-box; box-shadow: 0 4px 15px rgba(0,0,0,0.3);">
    <b style="color:#00d4ff;">บิล (Orders): กำลังโหลด...</b>
</div>

<!-- เพิ่มปุ่มเปิดโต๊ะใหม่แบบ Manual -->
<div style="width: 100%; max-width: 1200px; margin: 0 auto 20px auto;">
    <button onclick="openTableModal()" style="background:#00d4ff; color:black; font-weight:bold; padding:12px 25px; border-radius:10px; border:none; cursor:pointer; box-shadow: 0 4px 15px rgba(0,212,255,0.3);">🛎️ เปิดโต๊ะใหม่ (New Table)</button>
</div>

<div class="pos-container">
    <!-- ส่วนแสดงหมวดหมู่ -->
    <div class="categories">
        <button onclick="openProductModal()" style="background:#2ecc71; color:white; font-weight:bold; border-radius:8px;">➕ เพิ่มเมนูใหม่</button>
        <button onclick="loadProducts('Beer')">🍺 Beer</button>
        <button onclick="loadProducts('Wine')">🍷 Wine</button>
        <button onclick="loadProducts('Cocktail')">🍸 Cocktail</button>
        <button onclick="loadProducts('Food')">🍽️ Food</button>
        <button onclick="loadProducts('Liquor')">🥃 Liquor</button>
        <button onclick="openSpecialModal()" style="background:#ff9800; color:white; font-weight:bold; border-radius:8px;">✨ Special Menu</button>
        
        <!-- พื้นที่แสดงปุ่มสินค้าที่ดึงมาจาก DB -->
        <div id="product-display" class="product-grid"></div>
    </div>

    <!-- ส่วนสรุปบิล -->
    <div class="bill-summary">
        <div class="bill-header" style="border-bottom: 2px solid #00d4ff; padding-bottom: 10px; margin-bottom: 15px;">
            <h2 style="margin:0; color:#00d4ff; font-size: 24px;">TABLE <span id="table-display">--</span></h2>
            <small style="color:#999;">Order ID: #<span id="order-id-display">--</span></small>
        </div>

        <div id="order-items" style="height: 250px; overflow-y: auto; margin-bottom: 15px; padding-right: 5px;"></div>
        
        <div class="bill-calculation" style="background: #0f111a; border-radius: 12px; padding: 15px; margin-bottom: 15px;">
            <div class="summary-line" style="display:flex; justify-content:space-between; margin-bottom:5px; font-size:14px; color:#bbb;">
                <span>Subtotal:</span> <span id="summary-subtotal">฿0.00</span>
            </div>
            <div class="summary-line" style="display:flex; justify-content:space-between; margin-bottom:5px; font-size:14px; color:#ff4d4d;">
                <span>Discount <small id="summary-ispercent"></small>:</span> <span id="summary-discount">- ฿0.00</span>
            </div>
            <div class="summary-line" style="display:flex; justify-content:space-between; margin-bottom:5px; font-size:14px; color:#bbb;">
                <span>Service Charge (10%):</span> <span id="summary-sc">฿0.00</span>
            </div>
            <div class="summary-line" style="display:flex; justify-content:space-between; margin-bottom:10px; font-size:14px; color:#bbb;">
                <span>VAT (7%):</span> <span id="summary-tax">฿0.00</span>
            </div>
            <div class="total-line" style="display:flex; justify-content:space-between; border-top: 1px dashed #333; padding-top: 10px; margin-top: 5px;">
                <span style="font-size:18px; font-weight:bold; color:#fff;">NET TOTAL:</span>
                <span style="font-size:24px; font-weight:bold; color:#00d4ff;">฿<span id="summary-total">0.00</span></span>
            </div>
        </div>

        <div class="bill-controls" style="display:grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 15px;">
            <button id="btn-tax" onclick="toggleTax()" style="font-size:11px; padding:8px; border-radius:6px; transition: 0.3s; border:none; cursor:pointer;">VAT (7%)</button>
            <button id="btn-sc" onclick="toggleSC()" style="font-size:11px; padding:8px; border-radius:6px; transition: 0.3s; border:none; cursor:pointer;">S.C. (10%)</button>
            <button id="btn-disc-5" onclick="setDiscount(5, 'percent')" style="font-size:11px; padding:8px; border-radius:6px; transition: 0.3s; border:none; cursor:pointer;">ลด 5%</button>
            <button id="btn-disc-10" onclick="setDiscount(10, 'percent')" style="font-size:11px; padding:8px; border-radius:6px; transition: 0.3s; border:none; cursor:pointer;">ลด 10%</button>
        </div>

        <div class="bill-actions" style="display:grid; grid-template-columns: 1fr 1fr; gap: 10px;">
            <button onclick="printTicket(currentOrderId)" style="background:#3498db; color:white; border:none; padding:12px; border-radius:8px; font-weight:bold; cursor:pointer;">
                🖨️ แจ้งหนี้
            </button>
            <button onclick="generateTaxInvoice(currentOrderId)" style="background:#95a5a6; color:white; border:none; padding:12px; border-radius:8px; font-weight:bold; cursor:pointer;">
                🧾 กำกับภาษี
            </button>
            <button onclick="splitBillAction()" style="background:#e67e22; color:white; border:none; padding:12px; border-radius:8px; font-weight:bold; cursor:pointer;">
                ✂️ แยกบิล
            </button>
            <button onclick="voidAll(currentOrderId)" style="background:#e74c3c; color:white; border:none; padding:12px; border-radius:8px; font-weight:bold; cursor:pointer;">
                ❌ ยกเลิก
            </button>
            <button onclick="checkoutOrder(currentOrderId)" style="background:#2ecc71; color:white; border:none; padding:15px; border-radius:8px; font-weight:bold; font-size:20px; cursor:pointer; grid-column: span 2; box-shadow: 0 4px 10px rgba(46,204,113,0.3);">
                💵 CHECKOUT
            </button>
        </div>
    </div>
<!-- Modal สำหรับเลือกหมายเลขโต๊ะ (สำคัญมาก: ถ้าไม่มีส่วนนี้ระบบจะกดเพิ่มออเดอร์ไม่ได้) -->
<div id="tableSelectionModal" class="modal-overlay">
    <div class="modal-box" style="border-color: #00d4ff; background: #1a1d29; color: white;">
        <div class="modal-header">
            <h3 style="color: #00d4ff; margin-bottom: 20px;">🛎️ เลือกหมายเลขโต๊ะ</h3>
        </div>
        <div class="modal-body">
            <div class="input-group">
                <label style="color: #a0a0a0;">หมายเลขโต๊ะ (Table Number):</label>
                <input type="text" id="tableInput" placeholder="ระบุเบอร์โต๊ะ..." style="background: #0f111a; border: 1px solid #333; color: white; padding: 15px; width: 100%; box-sizing: border-box; border-radius: 8px; font-size: 20px;" autofocus>
            </div>
        </div>
        <div class="modal-footer">
            <button onclick="document.getElementById('tableSelectionModal').style.display='none'" class="btn-cancel">ยกเลิก</button>
            <button onclick="confirmTable()" class="btn-save" style="background:#00d4ff; color:black;">เปิดโต๊ะ</button>
        </div>
    </div>
</div>

<!-- Modal สำหรับการแยกบิล -->
<div id="splitModal" class="modal-overlay">
    <div class="modal-box" style="width: 450px; border-color: orange;">
        <div class="modal-header">
            <h3 style="color: orange;">✂️ แยกรายการไปยังบิลใหม่</h3>
            <p style="font-size: 12px; color: #ccc;">เลือกรายการที่ต้องการย้ายไปอีกบิล</p>
        </div>
        <div id="split-items-list" style="max-height: 300px; overflow-y: auto; margin: 20px 0;">
            <!-- รายการสินค้าจะแสดงที่นี่ -->
        </div>
        <div class="modal-footer">
            <button onclick="document.getElementById('splitModal').style.display='none'" class="btn-cancel">ปิด</button>
            <button onclick="executeSplit()" class="btn-save" style="background: orange;">ยืนยันแยกบิล</button>
        </div>
    </div>
</div>

<!-- Modal สำหรับ Special Menu -->
<div id="specialModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <h3>✨ เพิ่มเมนูพิเศษ (Special Item)</h3>
        </div>
        <div class="modal-body">
            <div class="input-group">
                <label>ชื่อรายการ:</label>
                <input type="text" id="specialName" placeholder="เช่น เครื่องดื่มพิเศษ, ของหวาน...">
            </div>
            <div class="input-group">
                <label>หมวดหมู่เหล้าพื้นฐาน (1):</label>
                <select id="specialSpirit" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; font-size: 16px;">
                    <option value="">-- ไม่ระบุ --</option>
                    <option value="Gin">Gin</option>
                    <option value="Vodka">Vodka</option>
                    <option value="Rum">Rum</option>
                    <option value="Tequila">Tequila</option>
                    <option value="Whiskey">Whiskey</option>
                    <option value="Brandy">Brandy</option>
                    <option value="Liqueur">Liqueur</option>
                </select>
            </div>
            <div class="input-group">
                <label>ปริมาณที่ใช้ (1) (ml):</label>
                <input type="number" id="specialMl" placeholder="เช่น 30, 45, 60" min="1">
            </div>
            <div class="input-group">
                <label>หมวดหมู่เหล้าพื้นฐาน (2):</label>
                <select id="specialSpirit2" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; font-size: 16px;">
                    <option value="">-- ไม่ระบุ --</option>
                    <option value="Gin">Gin</option>
                    <option value="Vodka">Vodka</option>
                    <option value="Rum">Rum</option>
                    <option value="Tequila">Tequila</option>
                    <option value="Whiskey">Whiskey</option>
                    <option value="Brandy">Brandy</option>
                    <option value="Liqueur">Liqueur</option>
                </select>
            </div>
            <div class="input-group">
                <label>ปริมาณที่ใช้ (2) (ml):</label>
                <input type="number" id="specialMl2" placeholder="เช่น 15, 30" min="1">
            </div>
            <div class="input-group">
                <label>หมวดหมู่เหล้าพื้นฐาน (3):</label>
                <select id="specialSpirit3" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; font-size: 16px;">
                    <option value="">-- ไม่ระบุ --</option>
                    <option value="Gin">Gin</option>
                    <option value="Vodka">Vodka</option>
                    <option value="Rum">Rum</option>
                    <option value="Tequila">Tequila</option>
                    <option value="Whiskey">Whiskey</option>
                    <option value="Brandy">Brandy</option>
                    <option value="Liqueur">Liqueur</option>
                </select>
            </div>
            <div class="input-group">
                <label>ปริมาณที่ใช้ (3) (ml):</label>
                <input type="number" id="specialMl3" placeholder="เช่น 5, 10" min="1">
            </div>
            <div class="input-group">
                <label>ราคา (บาท):</label>
                <input type="number" id="specialPrice" placeholder="0.00" step="0.01">
            </div>
        </div>
        <div class="modal-footer">
            <button onclick="closeSpecialModal()" class="btn-cancel">ยกเลิก</button>
            <button onclick="submitSpecialMenu()" class="btn-save">เพิ่มรายการ</button>
        </div>
    </div>
</div>

<!-- Modal สำหรับเพิ่มสินค้าใหม่ (Master Data) -->
<div id="productModal" class="modal-overlay">
    <div class="modal-box" style="border-color: #2ecc71;">
        <div class="modal-header">
            <h3 style="color: #2ecc71;">➕ เพิ่มเมนูใหม่เข้าสู่ระบบ</h3>
        </div>
        <div class="modal-body">
            <div class="input-group">
                <label>ชื่อเมนู:</label>
                <input type="text" id="newName" placeholder="ระบุชื่ออาหารหรือเครื่องดื่ม">
            </div>
            <div class="input-group">
                <label>หมวดหมู่:</label>
                <select id="newCategory" onchange="updateVariationInputs()" style="width: 100%; padding: 12px; background: #0f111a; border: 1px solid #333; border-radius: 8px; color: white;">
                    <option value="Beer">🍺 Beer</option>
                    <option value="Wine">🍷 Wine</option>
                    <option value="Cocktail">🍸 Cocktail</option>
                    <option value="Food">🍽️ Food</option>
                    <option value="Liquor">🥃 Liquor</option>
                </select>
            </div>
            <div id="variation-inputs" style="margin-top:15px;">
                <!-- ช่องกรอกราคาจะเปลี่ยนไปตามหมวดหมู่ -->
            </div>
        </div>
        <div class="modal-footer">
            <button onclick="closeProductModal()" class="btn-cancel">ยกเลิก</button>
            <button onclick="submitNewProduct()" class="btn-save" style="background:#2ecc71;">บันทึกลงระบบ</button>
        </div>
    </div>
</div>

<style>
    body { font-family: 'Sarabun', sans-serif; background: #0f111a; color: white; display: flex; flex-direction: column; align-items: center; padding: 20px; }
    .pos-container { display: flex; gap: 20px; width: 100%; max-width: 1200px; }
    .categories button { padding: 15px; margin: 5px; font-size: 16px; cursor: pointer; }
    .bill-summary { background: #1a1d29; border-radius: 15px; padding: 20px; width: 400px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
    .controls button { margin: 2px; font-size: 12px; }

    .product-grid { 
        display: grid; 
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); 
        gap: 10px; 
        margin-top: 20px; 
        border-top: 1px solid #333; 
        padding-top: 20px; 
    }
    .product-btn { 
        background: #252a3a; color: white; border: 1px solid #444; padding: 20px 10px; border-radius: 10px; cursor: pointer; text-align: center;
    }
    .product-btn:hover { border-color: #00d4ff; background: #2d3446; }

    .order-tab { background: #252a3a; color: white; border: 1px solid #444; padding: 5px 15px; margin-right: 5px; border-radius: 5px; cursor: pointer; }
    .order-tab.active { background: #00d4ff; color: black; border-color: #00d4ff; font-weight: bold; }

    /* Modal Styles */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.6);
        z-index: 9999;
        justify-content: center;
        align-items: center;
    }
    .modal-box {
        background: #1a1d29;
        padding: 25px;
        border-radius: 15px;
        width: 350px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.6);
        border: 1px solid #333;
        animation: fadeIn 0.3s ease;
    }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
    
    .modal-header h3 { margin-top: 0; color: #333; text-align: center; }
    .input-group { margin-bottom: 15px; }
    .input-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #666; }
    .input-group input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; font-size: 16px; }
    .modal-footer { display: flex; justify-content: space-between; margin-top: 20px; }
    .modal-footer button { padding: 10px 20px; border-radius: 8px; border: none; cursor: pointer; font-weight: bold; }
    .btn-save { background: #4CAF50; color: white; flex: 1; margin-left: 10px; }
    .btn-cancel { background: #eee; color: #333; flex: 1; }
</style>