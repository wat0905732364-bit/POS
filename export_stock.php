<?php
require '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// ดึงข้อมูลสต็อกปัจจุบัน
$query = $conn->query("SELECT name, stock_qty FROM products");
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$sheet->setCellValue('A1', 'ชื่อสินค้า')->setCellValue('B1', 'จำนวนคงเหลือ');
$i = 2;
while($row = $query->fetch_assoc()) {
    $sheet->setCellValue('A'.$i, $row['name']);
    $sheet->setCellValue('B'.$i, $row['stock_qty']);
    $i++;
}

$writer = new Xlsx($spreadsheet);
$writer->save('stock_report_'.date('Ymd').'.xlsx');
echo "Export Success!";
?>