<?php
$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    die('ملف الإعدادات config/config.php غير موجود. انسخ config.sample.php وعدّل بيانات الاتصال بقاعدة البيانات.');
}
require $configFile;

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    die('فشل الاتصال بقاعدة البيانات. تحقق من إعدادات config/config.php');
}
