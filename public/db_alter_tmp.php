<?php
// PHP 語法檢查
header('Content-Type: text/plain; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "PHP error check:\n";
$output = shell_exec('php -l /var/www/templates/cases/form.php 2>&1');
echo $output ?: "shell_exec not available\n";

echo "\n--- Try including ---\n";
try {
    // 模擬變數
    $case = array('id' => 1, 'case_number' => 'test');
    ob_start();
    // 不實際 include，只檢查語法
    $code = file_get_contents(__DIR__ . '/../templates/cases/form.php');
    $tokens = @token_get_all($code);
    echo "Tokenize OK, " . count($tokens) . " tokens\n";
    ob_end_clean();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// 直接看 form.php 最後幾行
$lines = file(__DIR__ . '/../templates/cases/form.php');
echo "\nTotal lines: " . count($lines) . "\n";
echo "Last 5 lines:\n";
for ($i = max(0, count($lines)-5); $i < count($lines); $i++) {
    echo ($i+1) . ': ' . $lines[$i];
}
