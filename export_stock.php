<?php
require 'config.php';
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// Security check: เฉพาะผู้จัดการเท่านั้นที่สามารถ Export ได้
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'manager' && $_SESSION['role'] !== 'admin')) {
    die('Access Denied. You do not have permission to access this page.');
}

// ดึงข้อมูลสต็อกปัจจุบัน
$query = $conn->query("SELECT category, name, stock_qty, open_ml, ml_per_unit FROM products ORDER BY category, name");
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Stock Report');

// กำหนดหัวข้อ
$sheet->setCellValue('A1', 'หมวดหมู่');
$sheet->setCellValue('B1', 'ชื่อสินค้า');
$sheet->setCellValue('C1', 'คงเหลือ (ชิ้น)');
$sheet->setCellValue('D1', 'ขวดเปิด (ml)');
$sheet->setCellValue('E1', 'ปริมาณ/หน่วย (ml)');

$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '27ae60']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
];
$sheet->getStyle('A1:E1')->applyFromArray($headerStyle);

$rowNumber = 2;
while($row = $query->fetch_assoc()) {
    $sheet->setCellValue('A' . $rowNumber, $row['category']);
    $sheet->setCellValue('B' . $rowNumber, $row['name']);
    $sheet->setCellValue('C' . $rowNumber, $row['stock_qty']);
    $sheet->setCellValue('D' . $rowNumber, $row['open_ml']);
    $sheet->setCellValue('E' . $rowNumber, $row['ml_per_unit']);
    $rowNumber++;
}

foreach (range('A', 'E') as $columnID) { $sheet->getColumnDimension($columnID)->setAutoSize(true); }

$filename = 'stock_report_' . date('Y-m-d') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>