<?php
function calculateTotal($subtotal, $discount_amount, $is_percent = true, $apply_sc = true, $sc_rate = 10, $apply_tax = true, $tax_rate = 7) {
    // คำนวณ Service Charge จากยอดเต็ม
    $service_charge = $apply_sc ? ($subtotal * ($sc_rate / 100)) : 0;
    
    // คำนวณภาษี จาก (ยอดเต็ม + SC)
    $tax = $apply_tax ? (($subtotal + $service_charge) * ($tax_rate / 100)) : 0;

    // คำนวณส่วนลดจากยอดเต็ม
    $discount = $is_percent ? ($subtotal * ($discount_amount / 100)) : $discount_amount;
    
    return [
        'total' => max(0, ($subtotal + $service_charge + $tax) - $discount),
        'discount' => $discount,
        'sc' => $service_charge,
        'tax' => $tax
    ];
}
?>