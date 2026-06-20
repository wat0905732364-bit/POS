<?php
require 'config.php';

// เรียกใช้ไลบรารี PhpSpreadsheet
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// Security check: เฉพาะผู้จัดการเท่านั้นที่สามารถ Export ได้
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'manager' && $_SESSION['role'] !== 'admin')) {
    die('Access Denied. You do not have permission to access this page.');
}

// 1. สร้างอ็อบเจ็กต์ Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Sales Report');

// 2. กำหนดหัวข้อคอลัมน์
$sheet->setCellValue('A1', 'เลขที่บิล');
$sheet->setCellValue('B1', 'โต๊ะ');
$sheet->setCellValue('C1', 'ยอดสุทธิ (฿)');
$sheet->setCellValue('D1', 'ต้นทุนรวม (฿)');
$sheet->setCellValue('E1', 'กำไรสุทธิ (฿)');
$sheet->setCellValue('F1', 'วันที่-เวลา');
$sheet->setCellValue('G1', 'แคชเชียร์');

// 3. ตกแต่งหัวข้อคอลัมน์
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '34495e']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
];
$sheet->getStyle('A1:G1')->applyFromArray($headerStyle);

// รับค่าตัวกรองถ้ามีการส่งมา
$cashier_filter = isset($_GET['cashier']) ? $_GET['cashier'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

$where = "status = 'paid'";
if ($cashier_filter !== '') {
    $safe_cashier = $conn->real_escape_string($cashier_filter);
    $where .= " AND cashier_name = '$safe_cashier'";
}
if ($start_date !== '' && $end_date !== '') {
    $where .= " AND DATE(created_at) BETWEEN '{$start_date}' AND '{$end_date}'";
}

// 4. ดึงข้อมูลจากฐานข้อมูล
$sql = "SELECT id, receipt_no, table_number, total_amount, total_cost, created_at, cashier_name FROM orders WHERE $where ORDER BY created_at DESC";
$result = $conn->query($sql);

// 5. วนลูปใส่ข้อมูลลงในแต่ละแถว
$rowNumber = 2;
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $display_no = $row['receipt_no'] ? "#" . htmlspecialchars($row['receipt_no']) : "#" . $row['id'];
        $profit = $row['total_amount'] - $row['total_cost'];
        $sheet->setCellValue('A' . $rowNumber, $display_no);
        $sheet->setCellValue('B' . $rowNumber, $row['table_number']);
        $sheet->setCellValue('C' . $rowNumber, $row['total_amount']);
        $sheet->setCellValue('D' . $rowNumber, $row['total_cost']);
        $sheet->setCellValue('E' . $rowNumber, $profit);
        $sheet->setCellValue('F' . $rowNumber, $row['created_at']);
        $sheet->setCellValue('G' . $rowNumber, $row['cashier_name']);
        $rowNumber++;
    }
}

// 6. ปรับความกว้างคอลัมน์และรูปแบบตัวเลข
foreach (range('A', 'G') as $columnID) { $sheet->getColumnDimension($columnID)->setAutoSize(true); }
$sheet->getStyle('C2:E'.($rowNumber-1))->getNumberFormat()->setFormatCode('#,##0.00');

// 7. สั่งให้บราวเซอร์ดาวน์โหลดไฟล์
$filename = 'sales_report_' . date('Y-m-d') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>