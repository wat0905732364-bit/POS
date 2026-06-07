<?php
require '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * ฟังก์ชันตัดสต็อกในไฟล์ Excel
 * @param string $itemName ชื่อสินค้าที่ต้องการตัด
 * @param int $quantity จำนวนที่ขายได้
 * @param string $filePath พาธของไฟล์ Excel
 */
function cutStockExcel($itemName, $quantity, $filePath = null) {
    // กำหนด Path เริ่มต้นไปที่โฟลเดอร์ปัจจุบันถ้าไม่ได้ระบุมา
    if ($filePath === null) {
        $filePath = __DIR__ . '/Par.xlsx';
    }

    try {
        if (!file_exists($filePath)) {
            throw new Exception("ไม่พบไฟล์สต็อก: " . $filePath);
        }

        // 1. โหลดไฟล์ Excel เดิมขึ้นมา
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();

        $found = false;
        // 2. วนลูปหาแถวที่มีชื่อสินค้าตรงกัน (สมมติ Column A คือชื่อสินค้า, B คือจำนวน)
        for ($row = 2; $row <= $highestRow; $row++) {
            $currentName = $sheet->getCell('A' . $row)->getValue();
            
            if ($currentName == $itemName) {
                $currentStock = $sheet->getCell('B' . $row)->getValue();
                $newStock = $currentStock - $quantity;
                
                // ป้องกันสต็อกติดลบ
                if ($newStock < 0) $newStock = 0;
                
                // 3. อัปเดตค่าใหม่ลงใน Cell
                $sheet->setCellValue('B' . $row, $newStock);
                $found = true;
                break;
            }
        }

        if ($found) {
            // 4. บันทึกไฟล์กลับลงไป
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save($filePath);
            return true;
        }
        return false;
    } catch (Exception $e) {
        error_log("Error updating excel stock: " . $e->getMessage());
        return false;
    }
}
?>