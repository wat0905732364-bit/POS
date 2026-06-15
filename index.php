<?php
require 'config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FROG POS System</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;700&display=swap" rel="stylesheet">

<script>
    let currentOrder = {
        id: null,
        receipt_no: null,
        table: '',
        items: [],
        subtotal: 0,
        applyTax: true,
        applySC: true,
        discount: 0,
        isPercent: true,
        member: null,
        pointsUsed: 0
    };

    let lineDiscState = {
        name: '',
        price: 0,
        type: 'amount' // 'amount' หรือ 'percent'
    };

    let currentOrderId = null; 
    let activePromotions = []; // เก็บโปรโมชั่นที่เปิดใช้งาน

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

    // --- ระบบโปรโมชั่นอัตโนมัติ (Automated Promotions) ---
    function isTimeActive(start, end) {
        if (!start || !end) return true; // ถ้าไม่ได้ระบุเวลา ถือว่าใช้ได้ตลอด
        const now = new Date();
        const current = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0') + ':00';
        
        if (start <= end) {
            return current >= start && current <= end;
        } else {
            return current >= start || current <= end; // ข้ามคืน
        }
    }

    function applyAutomatedPromotions() {
        let promoDiscount = 0;
        let promoMessages = [];

        activePromotions.forEach(promo => {
            if (!isTimeActive(promo.start_time, promo.end_time)) return; 

            let targetItemsArray = null;
            if (promo.target_item) {
                try {
                    let parsed = JSON.parse(promo.target_item);
                    targetItemsArray = Array.isArray(parsed) ? parsed : [promo.target_item];
                } catch(e) {
                    targetItemsArray = [promo.target_item]; // รองรับข้อมูลเก่า
                }
            }

            if (promo.promo_type === 'discount_percent') {
                let catTotal = 0;
                currentOrder.items.forEach(item => {
                    let matchCategory = item.category === promo.target_category;
                    let matchItem = targetItemsArray ? targetItemsArray.includes(item.name) : true;
                    if (matchCategory && matchItem) {
                        catTotal += ((item.price - (item.item_discount || 0)) * item.quantity);
                    }
                });
                if (catTotal > 0) {
                    let discount = catTotal * (parseFloat(promo.discount_percent) / 100);
                    promoDiscount += discount;
                    promoMessages.push(`${promo.name}: -฿${discount.toLocaleString(undefined, {minimumFractionDigits: 2})}`);
                }
            } else if (promo.promo_type === 'buy_x_get_y') {
                let condition = parseInt(promo.condition_qty);
                let reward = parseInt(promo.reward_qty);
                let requiredTotal = condition + reward; 

                currentOrder.items.forEach(item => {
                    let matchCategory = item.category === promo.target_category;
                    let matchItem = targetItemsArray ? targetItemsArray.includes(item.name) : true;
                    if (matchCategory && matchItem && item.quantity >= requiredTotal) {
                        let freeCount = Math.floor(item.quantity / requiredTotal) * reward;
                        let discount = freeCount * (item.price - (item.item_discount || 0));
                        promoDiscount += discount;
                        promoMessages.push(`${promo.name} (${item.name}): -฿${discount.toLocaleString(undefined, {minimumFractionDigits: 2})}`);
                    }
                });
            }
        });

        return { discount: promoDiscount, messages: promoMessages };
    }

    // ฟังก์ชันส่งค่าการตั้งค่าบิลไปบันทึกในฐานข้อมูลทันที
    async function syncOrderSettings() {
        if (!currentOrderId) return;
        
        let promos = applyAutomatedPromotions();

        const formData = new FormData();
        formData.append('action', 'update_order_settings');
        formData.append('order_id', currentOrderId);
        formData.append('discount', currentOrder.discount);
        formData.append('promo_amount', promos.discount);
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

    // --- ระบบสมาชิก (CRM) ---
    async function checkMember() {
        const phone = document.getElementById('memberPhone').value;
        if (!phone) { alert("กรุณากรอกเบอร์โทรศัพท์"); return; }
        
        const formData = new FormData();
        formData.append('action', 'check_member');
        formData.append('phone', phone);
        
        try {
            const response = await fetch('pos_action.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.success) {
                setMember(result.member);
            } else {
                alert(result.message);
            }
        } catch (e) { console.error(e); }
    }

    function setMember(member) {
        currentOrder.member = member;
        currentOrder.pointsUsed = 0;
        
        document.getElementById('member-search-box').style.display = 'none';
        document.getElementById('member-info-box').style.display = 'flex';
        document.getElementById('display-member-name').innerText = "👤 " + member.name + " (" + member.phone + ")";
        document.getElementById('display-member-points').innerText = member.points;
        document.getElementById('memberPhone').value = '';
        
        updateDisplay();
    }

    function removeMember() {
        currentOrder.member = null;
        currentOrder.pointsUsed = 0;
        document.getElementById('member-search-box').style.display = 'flex';
        document.getElementById('member-info-box').style.display = 'none';
        updateDisplay();
    }

    function usePoints() {
        if (!currentOrder.member) return;
        
        if (currentOrder.pointsUsed > 0) {
            currentOrder.pointsUsed = 0;
            document.getElementById('btn-use-points').innerText = "ใช้แต้มเป็นส่วนลด";
            document.getElementById('btn-use-points').style.background = "#3498db";
        } else {
            let subtotal = currentOrder.items.reduce((acc, item) => acc + ((item.price - (item.item_discount || 0)) * item.quantity), 0);
            let sc = currentOrder.applySC ? (subtotal * 0.10) : 0;
            let tax = currentOrder.applyTax ? ((subtotal + sc) * 0.07) : 0;
            let discountVal = parseFloat(currentOrder.discount || 0);
            let discount = currentOrder.isPercent ? (subtotal * (discountVal / 100)) : discountVal;
            
            let promos = applyAutomatedPromotions();
            let promoDiscount = promos.discount;
            
            let totalBeforePoints = Math.max(0, (subtotal + sc + tax) - discount - promoDiscount);
            
            let maxPointsToUse = Math.min(currentOrder.member.points, Math.floor(totalBeforePoints));
            
            if (maxPointsToUse <= 0) {
                alert("ไม่มีแต้มเพียงพอ หรือยอดบิลเป็น 0");
                return;
            }
            
            const useAmt = prompt(`มีแต้ม ${currentOrder.member.points} แต้ม\nแลกเป็นส่วนลดได้สูงสุด ฿${maxPointsToUse}\nต้องการใช้กี่แต้ม? (1 แต้ม = 1 บาท)`, maxPointsToUse);
            
            if (useAmt !== null) {
                let parsedAmt = parseInt(useAmt);
                if (isNaN(parsedAmt) || parsedAmt <= 0) return;
                if (parsedAmt > currentOrder.member.points) { alert("แต้มที่มีไม่พอ"); return; }
                if (parsedAmt > totalBeforePoints) { alert("ใช้แต้มเกินยอดสุทธิของบิลไม่ได้"); return; }
                
                currentOrder.pointsUsed = parsedAmt;
                document.getElementById('btn-use-points').innerText = `ยกเลิกใช้แต้ม (-฿${parsedAmt})`;
                document.getElementById('btn-use-points').style.background = "#e67e22";
            }
        }
        updateDisplay();
    }

    function openRegisterMemberModal() {
        document.getElementById('regMemberPhone').value = document.getElementById('memberPhone').value;
        document.getElementById('regMemberName').value = '';
        document.getElementById('registerMemberModal').style.display = 'flex';
    }

    async function submitRegisterMember() {
        const phone = document.getElementById('regMemberPhone').value;
        const name = document.getElementById('regMemberName').value;
        
        if (!phone || !name) { alert("กรุณากรอกข้อมูลให้ครบถ้วน"); return; }
        const formData = new FormData();
        formData.append('action', 'register_member');
        formData.append('phone', phone);
        formData.append('name', name);

        try {
            const response = await fetch('pos_action.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.success) {
                alert("สมัครสมาชิกสำเร็จ!");
                document.getElementById('registerMemberModal').style.display = 'none';
                setMember(result.member);
            } else { alert("ข้อผิดพลาด: " + result.message); }
        } catch (e) { console.error(e); }
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
    function closeProductModal() { 
        document.getElementById('productModal').style.display = 'none'; 
        document.getElementById('newName').value = '';
        document.getElementById('newMl').value = '';
        document.getElementById('newInventoryId').value = '';
        document.getElementById('newCategory').value = '';
    }

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
        const ml = document.getElementById('newMl').value;
        const inventoryId = document.getElementById('newInventoryId').value;

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
                formData.append('ml_per_unit', ml);
                formData.append('inventory_id', inventoryId);

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
            let itemDisc = item.item_discount || 0;
            let discountedPrice = item.price - itemDisc;
            subtotal += discountedPrice * item.quantity;

            itemContainer.innerHTML += `
                <div class="order-item-row" style="display:flex; align-items:center; justify-content:space-between; padding: 12px 0; border-bottom: 1px solid #252a3a;">
                    <div style="flex:1; display:flex; flex-direction:column;">
                        <span class="item-name" style="font-size:14px; margin-bottom:5px;">${item.name} ${itemDisc > 0 ? `<small style="color:#ff4d4d;">(ลด -฿${itemDisc})</small>` : ''}</span>
                        <div style="display:flex; align-items:center; gap:12px;">
                            <button onclick="removeFromOrder('${item.name.replace(/'/g, "\\'")}')" 
                                style="background:#e74c3c; color:white; border:none; border-radius:4px; width:28px; height:28px; cursor:pointer; font-weight:bold;">-</button>
                            <b style="color:#00d4ff; min-width:20px; text-align:center;">${item.quantity}</b>
                            <button onclick="addToOrder('${item.name.replace(/'/g, "\\'")}', ${item.price}, 0, '${item.category || ''}')" 
                                style="background:#2ecc71; color:white; border:none; border-radius:4px; width:28px; height:28px; cursor:pointer; font-weight:bold;">+</button>
                            <button onclick="promptItemDiscount('${item.name.replace(/'/g, "\\'")}', ${item.price}, ${itemDisc})" 
                                style="background:#f1c40f; color:black; border:none; border-radius:4px; padding:2px 8px; cursor:pointer; font-size:11px; font-weight:bold;">💸 ส่วนลด</button>
                        </div>
                    </div>
                    <span class="item-price">฿${(discountedPrice * item.quantity).toLocaleString(undefined, {minimumFractionDigits: 2})}</span>
                </div>`;
        });

        let sc = currentOrder.applySC ? (subtotal * 0.10) : 0;
        let tax = currentOrder.applyTax ? ((subtotal + sc) * 0.07) : 0;
        let discountVal = parseFloat(currentOrder.discount || 0);
        let discount = currentOrder.isPercent ? (subtotal * (discountVal / 100)) : discountVal;
        
        let promos = applyAutomatedPromotions();
        let promoDiscount = promos.discount;
        
        let totalBeforePoints = Math.max(0, (subtotal + sc + tax) - discount - promoDiscount);
        let pointsDiscount = currentOrder.pointsUsed || 0;
        let total = Math.max(0, totalBeforePoints - pointsDiscount);

        document.getElementById('table-display').innerText = currentOrder.table || '--';
        document.getElementById('order-id-display').innerText = currentOrder.receipt_no ? currentOrder.receipt_no : (currentOrder.id ? currentOrder.id : '--');
        
        // อัปเดตตัวเลขสรุปผล
        document.getElementById('summary-subtotal').innerText = '฿' + subtotal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('summary-discount').innerText = '- ฿' + discount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('summary-sc').innerText = '฿' + sc.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('summary-tax').innerText = '฿' + tax.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('summary-ispercent').innerText = currentOrder.isPercent ? '(' + currentOrder.discount + '%)' : '';
        
        let promoHTML = '';
        if (promos.messages.length > 0) {
            promos.messages.forEach(msg => {
                promoHTML += `<div style="display:flex; justify-content:space-between; color:#ff9800; font-weight:bold; margin-bottom:5px;"><span>🎁 ${msg}</span></div>`;
            });
        }
        document.getElementById('promo-messages-container').innerHTML = promoHTML;

        const linePoints = document.getElementById('line-points-discount');
        if (pointsDiscount > 0) {
            linePoints.style.display = 'flex';
            document.getElementById('summary-points-discount').innerText = '- ฿' + pointsDiscount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        } else {
            linePoints.style.display = 'none';
        }

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
            btn.innerHTML = `<span style="font-size: 18px; display: block; margin-bottom: 8px;">${p.name}</span>
                             <b style="font-size: 16px; color: #00d4ff;">฿${p.price}</b><br>
                             <small style="color:${stockColor}; font-size: 13px;">Stock: ${p.stock_qty}</small>`;
            btn.onclick = () => addToOrder(p.name, p.price, 0, p.category);
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

    async function addToOrder(name, price, confirmOpen = 0, category = '') {
        if (!currentOrderId) { openTableModal(); return; }

        const formData = new FormData();
        formData.append('action', 'add_item');
        formData.append('order_id', currentOrderId);
        formData.append('item_name', name);
        formData.append('price', price);
        if (confirmOpen) formData.append('confirm_open', '1');

        try {
            const response = await fetch('pos_action.php', { method: 'POST', body: formData });
            const result = await response.json();
            
            // ถ้าระบบแจ้งว่าขวดเปิดมี ml ไม่พอ ให้ถามพนักงานเพื่อเปิดขวดใหม่
            if (result.require_open) {
                if (confirm(result.message)) {
                    await addToOrder(name, price, 1); // กดยืนยัน ให้ส่งคำสั่งไปอีกครั้งพร้อม flag เปิดขวด
                }
                return; // หยุดการทำงานชั่วคราว รอจนกว่าจะเปิดขวด
            }

            if (!result.success) {
                alert(result.message);
                return;
            }

            // เมื่อบันทึกลง Database สำเร็จแล้ว ค่อยนำมาแสดงบนหน้าจอ
            const existingItem = currentOrder.items.find(item => item.name === name);
            if (existingItem) {
                existingItem.quantity += 1;
            } else {
                currentOrder.items.push({ name: name, price: parseFloat(price || 0), quantity: 1, category: category });
            }
            updateDisplay();
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

    function promptItemDiscount(name, price, currentDisc) {
        if (!currentOrderId) return;
        lineDiscState.name = name;
        lineDiscState.price = price;
        lineDiscState.type = 'amount'; // เริ่มต้นที่แบบบาท
        
        document.getElementById('lineDiscountValue').value = currentDisc;
        document.getElementById('discount-item-name-display').innerText = name + " (ราคา ฿" + price.toLocaleString() + ")";
        document.getElementById('lineItemDiscountModal').style.display = 'flex';
        updateLineDiscUI();
    }

    function setLineDiscType(type) {
        lineDiscState.type = type;
        updateLineDiscUI();
    }

    function updateLineDiscUI() {
        updateButtonState('btn-line-type-amount', lineDiscState.type === 'amount');
        updateButtonState('btn-line-type-percent', lineDiscState.type === 'percent');
    }

    function closeLineDiscModal() {
        document.getElementById('lineItemDiscountModal').style.display = 'none';
    }

    async function applyLineItemDiscount() {
        let val = parseFloat(document.getElementById('lineDiscountValue').value) || 0;
        let finalDiscountAmount = val;

        // ถ้าเลือกเป็น % ให้คำนวณเป็นยอดเงินบาทก่อนส่งไปบันทึก
        if (lineDiscState.type === 'percent') {
            finalDiscountAmount = (lineDiscState.price * val) / 100;
        }

        const formData = new FormData();
        formData.append('action', 'update_item_discount');
        formData.append('order_id', currentOrderId);
        formData.append('item_name', lineDiscState.name);
        formData.append('discount', finalDiscountAmount);

        try {
            const response = await fetch('pos_action.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.success) {
                closeLineDiscModal();
                switchOrder(currentOrderId);
            }
        } catch (e) { console.error("Item discount error:", e); }
    }

    function resetCurrentOrder() {
        currentOrderId = null;
        currentOrder.id = null;
        currentOrder.receipt_no = null;
        currentOrder.table = '';
        currentOrder.items = [];
        currentOrder.discount = 0;
        currentOrder.isPercent = true;
        currentOrder.applyTax = true;
        currentOrder.applySC = true;
        removeMember(); // รีเซ็ตสมาชิกและอัปเดตหน้าจออัตโนมัติ
    }

    async function voidAll(orderId) { 
        if(!confirm('ยืนยันการยกเลิกบิล?')) return;
        
        const formData = new FormData();
        formData.append('action', 'void_order');
        formData.append('order_id', orderId);
        
        const response = await fetch('pos_action.php', { method: 'POST', body: formData });
        const result = await response.json();
        if(result.success) {
            resetCurrentOrder();
            loadActiveOrders();
        }
    }

    async function checkoutOrder(orderId) {
        if (currentOrder.items.length === 0) {
            alert("ไม่มีรายการสินค้าในบิล");
            return;
        }

        if (!confirm('ยืนยันการชำระเงินและปิดบิลนี้?')) return;

        // คำนวณยอดรวมสุดท้ายจากหน้าจอ
        let subtotal = currentOrder.items.reduce((acc, item) => acc + ((item.price - (item.item_discount || 0)) * item.quantity), 0);
        let sc = currentOrder.applySC ? (subtotal * 0.10) : 0;
        let tax = currentOrder.applyTax ? ((subtotal + sc) * 0.07) : 0;
        let discountVal = parseFloat(currentOrder.discount || 0);
        let discount = currentOrder.isPercent ? (subtotal * (discountVal / 100)) : discountVal;
        
        let promos = applyAutomatedPromotions();
        let promoDiscount = promos.discount;
        
        let totalBeforePoints = Math.max(0, (subtotal + sc + tax) - discount - promoDiscount);

        const formData = new FormData();
        formData.append('action', 'checkout_order');
        formData.append('order_id', orderId);
        formData.append('total', totalBeforePoints);
        formData.append('discount', currentOrder.discount);
        formData.append('promo_amount', promoDiscount);
        formData.append('is_percent', currentOrder.isPercent ? 1 : 0);
        formData.append('apply_sc', currentOrder.applySC ? 1 : 0);
        formData.append('apply_tax', currentOrder.applyTax ? 1 : 0);
        
        if (currentOrder.member) {
            formData.append('member_id', currentOrder.member.id);
            formData.append('points_used', currentOrder.pointsUsed || 0);
        }

        try {
            const response = await fetch('pos_action.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.success) {
                alert("ชำระเงินสำเร็จ! บิลถูกบันทึกเรียบร้อย");
                resetCurrentOrder();
                loadActiveOrders(); // โหลดรายการคิวบิลใหม่
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
                btn.innerText = 'โต๊ะ ' + (order.table_number || '-') + ' (#' + (order.receipt_no || order.id) + ')';
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
        currentOrder.receipt_no = data.order_info ? data.order_info.receipt_no : null;
        currentOrder.table = data.order_info ? data.order_info.table_number : '--';
        currentOrder.applyTax = data.order_info ? (parseInt(data.order_info.apply_tax) === 1) : true;
        currentOrder.applySC = data.order_info ? (parseInt(data.order_info.apply_sc) === 1) : true;
        currentOrder.discount = data.order_info ? parseFloat(data.order_info.discount_amount || 0) : 0;
        currentOrder.isPercent = data.order_info ? (parseInt(data.order_info.is_percent) === 1) : true;
        currentOrder.items = data.items ? data.items.map(i => ({
            ...i, 
            price: parseFloat(i.price), 
            quantity: parseInt(i.quantity),
            item_discount: parseFloat(i.item_discount || 0),
            category: i.category || ''
        })) : [];
        
        currentOrder.member = null;
        currentOrder.pointsUsed = 0;
        document.getElementById('member-search-box').style.display = 'flex';
        document.getElementById('member-info-box').style.display = 'none';
        
        updateDisplay();
        loadActiveOrders(); // อัปเดตสีปุ่มบิลที่เลือก
    }

    async function loadPromotions() {
        try {
            const formData = new FormData();
            formData.append('action', 'get_active_promotions');
            const response = await fetch('pos_action.php', { method: 'POST', body: formData });
            activePromotions = await response.json();
        } catch (e) { console.error("Error loading promos:", e); }
    }

    // สั่งให้โหลดข้อมูลเมื่อเปิดหน้าจอ
    window.onload = () => {
        loadActiveOrders();
        loadProducts('Beer'); // โหลดสินค้าหมวดเบียร์รอไว้เลย
        loadPromotions();
    };
</script>

<!-- ส่วนเมนูนำทาง (Navbar) -->
<div style="width: 100%; max-width: 1300px; display: flex; justify-content: space-between; align-items: center; margin: 0 auto 10px auto; padding: 10px 0; border-bottom: 1px solid #333;">
    <h1 style="color: #00d4ff; margin: 0;">FROG POS</h1>
    <nav>
        <a href="index.php" style="color: #00d4ff; text-decoration: none; font-weight: bold; margin-left: 20px;">หน้าขาย (POS)</a>
        <?php if($_SESSION['role'] === 'manager' || $_SESSION['role'] === 'admin'): ?>
            <a href="sales_report.php" style="color: white; text-decoration: none; margin-left: 20px;">รายงานยอดขาย</a>
            <a href="dashboard.php" style="color: white; text-decoration: none; margin-left: 20px;">แดชบอร์ดจัดการ</a>
        <?php endif; ?>
    </nav>
    <div style="display: flex; align-items: center; gap: 15px; color: #aaa; font-size: 14px;">
        <span>👤 <?php echo htmlspecialchars($_SESSION['name']); ?> (<?php echo $_SESSION['role'] === 'manager' ? 'ผู้จัดการ' : 'แคชเชียร์'; ?>)</span>
        <a href="logout.php" style="background: #e74c3c; color: white; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 13px; transition: 0.2s;">ออกจากระบบ</a>
    </div>
</div>

<!-- ส่วนแสดงรายการบิลที่ค้างอยู่ -->
<div id="active-orders-list" style="padding: 15px; background: #1a1d29; margin: 0 auto 15px auto; border-radius: 12px; width: 100%; max-width: 1300px; box-sizing: border-box; box-shadow: 0 4px 15px rgba(0,0,0,0.3);">
    <b style="color:#00d4ff;">บิล (Orders): กำลังโหลด...</b>
</div>

<!-- เพิ่มปุ่มเปิดโต๊ะใหม่แบบ Manual -->
<div style="width: 100%; max-width: 1300px; margin: 0 auto 20px auto;">
    <button onclick="openTableModal()" style="background:#00d4ff; color:black; font-weight:bold; padding:12px 25px; border-radius:10px; border:none; cursor:pointer; box-shadow: 0 4px 15px rgba(0,212,255,0.3);">🛎️ เปิดโต๊ะใหม่ (New Table)</button>
</div>

<div class="pos-container">
    <!-- แถบหมวดหมู่ด้านซ้าย (Left Sidebar) -->
    <div class="category-sidebar">
        <button onclick="openProductModal()" style="background:#2ecc71; color:white; font-weight:bold;">➕ เพิ่มเมนูใหม่</button>
        
        <?php
        // ดึงหมวดหมู่ทั้งหมดที่มีในระบบมาสร้างเป็นปุ่มโดยอัตโนมัติ
        $cat_res = $conn->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
        if ($cat_res && $cat_res->num_rows > 0) {
            while($c = $cat_res->fetch_assoc()) {
                $catName = htmlspecialchars($c['category']);
                echo "<button onclick=\"loadProducts('$catName')\">$catName</button>\n";
            }
        } else {
            echo '<button onclick="loadProducts(\'Beer\')">Beer</button>';
        }
        ?>
        
        <button onclick="openSpecialModal()" style="background:#ff9800; color:white; font-weight:bold;">✨ Special Menu</button>
    </div>

    <!-- พื้นที่แสดงปุ่มสินค้าตรงกลาง -->
    <div class="product-area">
        <div id="product-display" class="product-grid"></div>
    </div>

    <!-- ส่วนสรุปบิล -->
    <div class="bill-summary">
        <div class="bill-header" style="border-bottom: 2px solid #00d4ff; padding-bottom: 10px; margin-bottom: 15px;">
            <h2 style="margin:0; color:#00d4ff; font-size: 24px;">TABLE <span id="table-display">--</span></h2>
            <small style="color:#999;">Order ID: #<span id="order-id-display">--</span></small>
            
            <!-- ส่วนสมาชิกลูกค้า (CRM) -->
            <div style="margin-top: 10px; padding: 10px; background: #252a3a; border-radius: 8px; font-size: 13px;">
                <div id="member-search-box" style="display: flex; gap: 5px;">
                    <input type="text" id="memberPhone" placeholder="เบอร์โทรสมาชิก..." style="flex: 1; padding: 8px; border-radius: 6px; border: 1px solid #444; background: #0f111a; color: white;">
                    <button onclick="checkMember()" style="background: #f1c40f; color: black; border: none; border-radius: 6px; padding: 5px 15px; cursor: pointer; font-weight: bold;">ค้นหา</button>
                    <button onclick="openRegisterMemberModal()" style="background: #2ecc71; color: white; border: none; border-radius: 6px; padding: 5px 15px; cursor: pointer; font-weight: bold;">+ สมัคร</button>
                </div>
                <div id="member-info-box" style="display: none; justify-content: space-between; align-items: center;">
                    <div style="color: #00d4ff;">
                        <b id="display-member-name" style="font-size: 15px;"></b><br>
                        <small style="color: #aaa;">แต้มสะสม: <span id="display-member-points" style="color: #f1c40f; font-weight: bold; font-size: 14px;"></span> แต้ม</small>
                    </div>
                    <div style="display: flex; gap: 5px; flex-direction: column; align-items: flex-end;">
                        <button onclick="usePoints()" id="btn-use-points" style="background: #3498db; color: white; border: none; border-radius: 6px; padding: 6px 10px; cursor: pointer; font-size: 11px;">ใช้แต้มเป็นส่วนลด</button>
                        <button onclick="removeMember()" style="background: #e74c3c; color: white; border: none; border-radius: 6px; padding: 4px 8px; cursor: pointer; font-size: 10px;">นำออก</button>
                    </div>
                </div>
            </div>
        </div>

        <div id="order-items" style="height: 250px; overflow-y: auto; margin-bottom: 15px; padding-right: 5px;"></div>
        
        <div class="bill-calculation" style="background: #0f111a; border-radius: 12px; padding: 15px; margin-bottom: 15px;">
            <div class="summary-line" style="display:flex; justify-content:space-between; margin-bottom:5px; font-size:14px; color:#bbb;">
                <span>Subtotal:</span> <span id="summary-subtotal">฿0.00</span>
            </div>
            <div class="summary-line" style="display:flex; justify-content:space-between; margin-bottom:5px; font-size:14px; color:#ff4d4d;">
                <span>Discount <small id="summary-ispercent"></small>:</span> <span id="summary-discount">- ฿0.00</span>
            </div>
            <div id="promo-messages-container" style="font-size:14px;"></div>
            <div class="summary-line" style="display:flex; justify-content:space-between; margin-bottom:5px; font-size:14px; color:#bbb;">
                <span>Service Charge (10%):</span> <span id="summary-sc">฿0.00</span>
            </div>
            <div class="summary-line" style="display:flex; justify-content:space-between; margin-bottom:10px; font-size:14px; color:#bbb;">
                <span>VAT (7%):</span> <span id="summary-tax">฿0.00</span>
            </div>
            <div class="summary-line" id="line-points-discount" style="display:none; justify-content:space-between; margin-bottom:10px; font-size:14px; color:#f1c40f;">
                <span>ส่วนลดจากแต้ม (Points):</span> <span id="summary-points-discount">- ฿0.00</span>
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

<!-- Modal สำหรับส่วนลดรายรายการ -->
<div id="lineItemDiscountModal" class="modal-overlay">
    <div class="modal-box" style="border-color: #f1c40f;">
        <div class="modal-header">
            <h3 style="color: #f1c40f;">💸 ส่วนลดรายการสินค้า</h3>
            <p id="discount-item-name-display" style="color: #aaa; font-size: 14px; margin-bottom: 15px;"></p>
        </div>
        <div class="modal-body">
            <div style="display: flex; gap: 10px; margin-bottom: 20px;">
                <button onclick="setLineDiscType('amount')" id="btn-line-type-amount" style="flex:1; padding:12px; border-radius:8px; border:none; cursor:pointer; font-weight:bold;">฿ บาท</button>
                <button onclick="setLineDiscType('percent')" id="btn-line-type-percent" style="flex:1; padding:12px; border-radius:8px; border:none; cursor:pointer; font-weight:bold;">% เปอร์เซ็นต์</button>
            </div>
            <div class="input-group">
                <label style="color: #888;">ระบุจำนวนส่วนลด:</label>
                <input type="number" id="lineDiscountValue" placeholder="0.00" step="0.01" style="font-size: 24px; text-align: center; padding: 15px;">
            </div>
        </div>
        <div class="modal-footer">
            <button onclick="closeLineDiscModal()" class="btn-cancel">ยกเลิก</button>
            <button onclick="applyLineItemDiscount()" class="btn-save" style="background:#f1c40f; color: black;">ตกลง</button>
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
                <input type="text" id="newCategory" list="cat_list_pos" oninput="updateVariationInputs()" placeholder="เลือกหรือพิมพ์เพิ่มหมวดหมู่ใหม่..." style="width: 100%; padding: 12px; background: #0f111a; border: 1px solid #333; border-radius: 8px; color: white;">
                <datalist id="cat_list_pos">
                    <option value="Beer">
                    <option value="Wine">
                    <option value="Cocktail">
                    <option value="Food">
                    <option value="Liquor">
                    <?php
                    // ดึงหมวดหมู่ที่ผู้ใช้เคยสร้างไว้มาเป็นตัวเลือกอัตโนมัติ
                    $cat_res = $conn->query("SELECT DISTINCT category FROM products WHERE category NOT IN ('Beer','Wine','Cocktail','Food','Liquor') AND category IS NOT NULL AND category != ''");
                    if($cat_res) {
                        while($c = $cat_res->fetch_assoc()) {
                            echo "<option value='".htmlspecialchars($c['category'])."'>\n";
                        }
                    }
                    ?>
                </datalist>
            </div>
            <div class="input-group">
                <label>ปริมาณต่อหน่วย (ml):</label>
                <input type="number" id="newMl" placeholder="เช่น 30 (Shot) หรือ 750 (ขวด)">
            </div>
            <div class="input-group">
                <label>เชื่อมสต็อกกับสินค้าหลัก (ถ้ามี):</label>
                <select id="newInventoryId" style="width: 100%; padding: 12px; background: #0f111a; border: 1px solid #333; border-radius: 8px; color: white;">
                    <option value="">-- เป็นสินค้าหลักเอง --</option>
                    <?php
                    $p_res = $conn->query("SELECT id, name FROM products WHERE inventory_id IS NULL ORDER BY name ASC");
                    while($p = $p_res->fetch_assoc()) echo "<option value='{$p['id']}'>".htmlspecialchars($p['name'])."</option>";
                    ?>
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

<!-- Modal สำหรับสมัครสมาชิกใหม่ -->
<div id="registerMemberModal" class="modal-overlay">
    <div class="modal-box" style="border-color: #2ecc71;">
        <div class="modal-header">
            <h3 style="color: #2ecc71;">👤 สมัครสมาชิกใหม่</h3>
        </div>
        <div class="modal-body">
            <div class="input-group">
                <label style="color: #a0a0a0;">เบอร์โทรศัพท์:</label>
                <input type="text" id="regMemberPhone" placeholder="08xxxxxxxx" maxlength="10" style="background: #0f111a; border: 1px solid #333; color: white;">
            </div>
            <div class="input-group">
                <label style="color: #a0a0a0;">ชื่อลูกค้า:</label>
                <input type="text" id="regMemberName" placeholder="ชื่อ-นามสกุล หรือชื่อเล่น" style="background: #0f111a; border: 1px solid #333; color: white;">
            </div>
        </div>
        <div class="modal-footer">
            <button onclick="document.getElementById('registerMemberModal').style.display='none'" class="btn-cancel">ยกเลิก</button>
            <button onclick="submitRegisterMember()" class="btn-save" style="background:#2ecc71;">สมัครสมาชิก</button>
        </div>
    </div>
</div>

<style>
    body { font-family: 'Sarabun', sans-serif; background: #0f111a; color: white; display: flex; flex-direction: column; align-items: center; padding: 20px; }
    .pos-container { display: flex; gap: 20px; width: 100%; max-width: 1300px; align-items: flex-start; }
    
    .category-sidebar { display: flex; flex-direction: column; gap: 10px; width: 180px; flex-shrink: 0; }
    .category-sidebar button { padding: 15px; font-size: 15px; cursor: pointer; border-radius: 8px; border: none; background: #252a3a; color: white; text-align: left; transition: 0.2s; box-shadow: 0 4px 6px rgba(0,0,0,0.3); }
    .category-sidebar button:hover { background: #00d4ff; color: black; font-weight: bold; transform: translateX(5px); }
    
    .product-area { flex: 1; }
    .bill-summary { background: #1a1d29; border-radius: 15px; padding: 20px; width: 400px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); flex-shrink: 0; }
    .controls button { margin: 2px; font-size: 12px; }

    .product-grid { 
        display: grid; 
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); 
        gap: 15px; 
    }
    .product-btn { 
        background: #252a3a; color: white; border: 1px solid #444; padding: 25px 15px; border-radius: 12px; cursor: pointer; text-align: center; transition: 0.2s;
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
        max-height: 85vh;
        overflow-y: auto;
        box-shadow: 0 10px 40px rgba(0,0,0,0.6);
        border: 1px solid #333;
        animation: fadeIn 0.3s ease;
    }
    .modal-box::-webkit-scrollbar { width: 6px; }
    .modal-box::-webkit-scrollbar-track { background: #1a1d29; border-radius: 10px; }
    .modal-box::-webkit-scrollbar-thumb { background: #444; border-radius: 10px; }
    .modal-box::-webkit-scrollbar-thumb:hover { background: #00d4ff; }
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