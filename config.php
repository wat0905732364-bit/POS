<?php
// ตั้งค่าโซนเวลาให้เป็นเวลาประเทศไทยเสมอ
date_default_timezone_set('Asia/Bangkok');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$host = "localhost";
$user = "root";
$pass = "";
$db   = "bar_pos_db";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// รหัส PIN ฉุกเฉินสำหรับรีเซ็ตรหัสผ่านของผู้จัดการ (ห้ามบอกพนักงาน)
// วิธีใช้: นำ PIN นี้ไปกรอกในช่อง "รหัสผ่าน" ที่หน้าเข้าสู่ระบบพร้อมกับ Username ผู้จัดการ
$recovery_pin = "FROG9999"; 
?>