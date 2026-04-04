<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=vhost158992;charset=utf8mb4', 'vhost158992', 'Kss9227456');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}

$sqls = array(
    // 基礎人事欄位
    "ALTER TABLE users ADD COLUMN employee_id VARCHAR(20) DEFAULT NULL COMMENT '資料編號' AFTER real_name",
    "ALTER TABLE users ADD COLUMN id_number VARCHAR(20) DEFAULT NULL COMMENT '身分證字號' AFTER employee_id",
    "ALTER TABLE users ADD COLUMN birth_date DATE DEFAULT NULL COMMENT '出生日期' AFTER id_number",
    "ALTER TABLE users ADD COLUMN gender VARCHAR(10) DEFAULT NULL COMMENT '性別' AFTER birth_date",
    "ALTER TABLE users ADD COLUMN marital_status VARCHAR(20) DEFAULT NULL COMMENT '婚姻狀態' AFTER gender",
    "ALTER TABLE users ADD COLUMN blood_type VARCHAR(5) DEFAULT NULL COMMENT '血型' AFTER marital_status",
    "ALTER TABLE users ADD COLUMN education_level VARCHAR(20) DEFAULT NULL COMMENT '最高教育程度' AFTER blood_type",
    "ALTER TABLE users ADD COLUMN job_title VARCHAR(50) DEFAULT NULL COMMENT '職稱' AFTER education_level",
    "ALTER TABLE users ADD COLUMN address VARCHAR(255) DEFAULT NULL COMMENT '地址' AFTER job_title",
    "ALTER TABLE users ADD COLUMN bank_name VARCHAR(50) DEFAULT NULL COMMENT '銀行名稱' AFTER address",
    "ALTER TABLE users ADD COLUMN bank_account VARCHAR(50) DEFAULT NULL COMMENT '銀行帳號' AFTER bank_name",
    "ALTER TABLE users ADD COLUMN hire_date DATE DEFAULT NULL COMMENT '到職日期' AFTER bank_account",
    "ALTER TABLE users ADD COLUMN resignation_date DATE DEFAULT NULL COMMENT '離職日期' AFTER hire_date",
    "ALTER TABLE users ADD COLUMN employment_status VARCHAR(20) DEFAULT 'active' COMMENT '在職狀態' AFTER resignation_date",
    "ALTER TABLE users ADD COLUMN labor_insurance_company VARCHAR(100) DEFAULT NULL COMMENT '勞保投保公司' AFTER employment_status",
    "ALTER TABLE users ADD COLUMN labor_insurance_date DATE DEFAULT NULL COMMENT '勞保投保日期' AFTER labor_insurance_company",
    "ALTER TABLE users ADD COLUMN dependent_insurance TEXT DEFAULT NULL COMMENT '眷屬加保' AFTER labor_insurance_date",
    "ALTER TABLE users ADD COLUMN annual_leave_days INT DEFAULT 0 COMMENT '享有特休天數' AFTER dependent_insurance",
    // 緊急聯絡人表
    "CREATE TABLE IF NOT EXISTS staff_emergency_contacts (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        contact_name VARCHAR(50) NOT NULL COMMENT '姓名',
        relationship VARCHAR(30) DEFAULT NULL COMMENT '關係',
        home_phone VARCHAR(30) DEFAULT NULL COMMENT '住家電話',
        work_phone VARCHAR(30) DEFAULT NULL COMMENT '公司電話',
        mobile VARCHAR(30) DEFAULT NULL COMMENT '手機',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='緊急聯絡人'",
);

echo "<pre>";
foreach ($sqls as $sql) {
    try {
        $pdo->exec($sql);
        echo "OK: " . substr($sql, 0, 80) . "\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "SKIP: " . substr($sql, 0, 60) . "\n";
        } else {
            echo "ERR: " . $e->getMessage() . "\n";
        }
    }
}
echo "\nAll migrations done.\n</pre>";
