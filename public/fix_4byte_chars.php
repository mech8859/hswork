<?php
// 修正含 4-byte UTF8 字元的記錄
require_once __DIR__ . "/../includes/bootstrap.php";
Auth::requireLogin();
if (Session::getUser()["role"] !== "boss") { die("需要管理員權限"); }
header("Content-Type: text/plain; charset=utf-8");

$db = Database::getInstance();
$db->exec("SET NAMES utf8mb4");

$fixes = array(
    array('name', '豐𪝞企業(股)公司蚵寮廠(2廠)', 'A-001832'),
    array('name', '豐𪝞企業(股)公司西濱廠(1廠)', 'A-001833'),
    array('name', '豐𪝞企業(股)公司西濱廠(1廠)', 'A-002197'),
    array('name', '豐𪝞企業(股)公司蚵寮廠(2廠)', 'A-002465'),
    array('name', '豐𪝞企業(股)公司西濱廠(1廠)', 'A-003571'),
    array('name', '豐𪝞企業(股)公司蚵寮廠(2廠)', 'A-004447'),
    array('name', '豐𪝞企業(股)公司蚵寮廠(2廠)', 'A-004448'),
    array('name', '豐𪝞企業(股)公司西濱廠(1廠)', 'A-004465'),
    array('name', '蔡佩宜 - 豐𪝞企業股份有限公司', 'A-004927'),
    array('name', '蔡佩宜 - 豐𪝞企業股份有限公司', 'A-004928'),
    array('name', '豐𪝞企業(股)公司蚵寮廠', 'A-004974'),
    array('name', '豐𪝞企業(股)公司西濱廠(1廠)', 'A-005456'),
    array('name', '豐𪝞企業(股)公司西濱廠(1廠)', 'A-006645'),
    array('name', '豐𪝞企業(股)公司西濱廠(1廠)', 'A-006995'),
    array('name', '豐𪝞企業(股)公司西濱廠(1廠)', 'A-007086'),
    array('name', '豐𪝞企業(股)公司西濱廠(1廠)', 'A-007492'),
    array('name', '豐𪝞企業(股)公司西濱廠(1廠)', 'A-007529'),
    array('name', '蔡佩宜 - 豐𪝞企業股份有限公司', 'A-007683'),
    array('name', '豐𪝞企業(股)公司西濱廠(1廠)', 'A-007985'),
    array('name', '豐𪝞企業(股)公司西濱廠(1廠)', 'A-008157'),
    array('name', '豐𪝞企業(股)公司-蚵寮廠', 'A-009044'),
    array('name', '吳國𨛦', 'A-009436'),
    array('contact_person', '吳國𨛦', 'A-009436'),
    array('name', '豐𪝞企業(股)公司-西濱廠', 'A-009874'),
    array('name', '豐𪝞企業(股)公司-蚵寮廠', 'A-010370'),
    array('name', '豐𪝞企業(股)公司-蚵寮廠', 'A-010512'),
    array('name', '豐𪝞企業(股)公司-蚵寮廠', 'A-010811'),
    array('name', '豐𪝞企業(股)公司-西濱廠', 'A-010887'),
    array('name', '豐𪝞企業(股)公司-蚵寮廠', 'A-011212'),
    array('name', '豐𪝞企業(股)公司-西濱廠', 'A-011219'),
    array('name', '豐𪝞企業(股)公司-西濱廠', 'A-011895'),
    array('name', '豐𪝞企業(股)公司-西濱廠', 'A-012137'),
    array('name', '豐𪝞企業(股)公司-蚵寮廠', 'A-012243'),
    array('name', '豐𪝞企業(股)公司-西濱廠', 'A-012553'),
    array('name', '豐𪝞企業(股)公司-蚵寮廠', 'A-013722'),
    array('name', '豐𪝞企業(股)公司-西濱廠', 'A-014632'),
    array('name', '豐𪝞企業(股)公司-蚵寮廠', 'A-015196'),
    array('name', '豐𪝞企業(股)公司-西濱廠', 'A-015198'),
    array('name', '豐𪝞企業(股)公司-西濱廠', 'A-015946'),
    array('name', '豐𪝞企業(股)公司-蚵寮廠', 'A-016110'),
    array('name', '豐𪝞企業(股)公司西濱廠(1廠)', 'A-016375')
);

$ok = 0; $err = 0;
foreach ($fixes as $f) {
    try {
        $stmt = $db->prepare("UPDATE customers SET " . $f[0] . " = ? WHERE customer_no = ?");
        $stmt->execute(array($f[1], $f[2]));
        $ok++;
        echo "OK: " . $f[2] . " " . $f[0] . "
";
    } catch (PDOException $e) {
        $err++;
        echo "ERR: " . $f[2] . " " . $e->getMessage() . "
";
    }
}
echo "
修正: $ok 成功, $err 錯誤
";
