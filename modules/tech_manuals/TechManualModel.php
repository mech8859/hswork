<?php
/**
 * 技術手冊 Model
 * 師傅施工時遇到問題可查閱的產品手冊/規格書/操作說明（PDF/圖片）
 */
class TechManualModel
{
    /** @var PDO */
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->ensureTable();
    }

    /**
     * 首次使用時自動建表（避免需要額外執行 migration）
     */
    private function ensureTable()
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS tech_manuals (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(200) NOT NULL COMMENT '標題',
                category VARCHAR(50) DEFAULT NULL COMMENT '設備類型(對講機/門禁/監視器/電子鎖/火警/...)',
                brand VARCHAR(50) DEFAULT NULL COMMENT '品牌',
                model VARCHAR(100) DEFAULT NULL COMMENT '型號',
                description TEXT DEFAULT NULL COMMENT '說明',
                tags VARCHAR(255) DEFAULT NULL COMMENT '關鍵字(逗號分隔)',
                file_path VARCHAR(500) NOT NULL COMMENT '檔案相對路徑',
                file_name VARCHAR(255) DEFAULT NULL COMMENT '原始檔名',
                file_size INT UNSIGNED DEFAULT 0 COMMENT '檔案大小(bytes)',
                file_ext VARCHAR(10) DEFAULT NULL COMMENT '副檔名(pdf/jpg/png)',
                uploaded_by INT UNSIGNED DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_category (category),
                KEY idx_brand (brand),
                FULLTEXT KEY ft_search (title, description, tags, model)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='技術手冊'
        ");
    }

    public function getList(array $filters = array(), $page = 1, $perPage = 50)
    {
        $where = array('1=1');
        $params = array();

        if (!empty($filters['category'])) {
            $where[] = 'm.category = ?';
            $params[] = $filters['category'];
        }
        if (!empty($filters['brand'])) {
            $where[] = 'm.brand = ?';
            $params[] = $filters['brand'];
        }
        if (!empty($filters['keyword'])) {
            $kw = '%' . $filters['keyword'] . '%';
            $where[] = '(m.title LIKE ? OR m.model LIKE ? OR m.description LIKE ? OR m.tags LIKE ? OR m.brand LIKE ?)';
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM tech_manuals m WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $sql = "SELECT m.*, u.real_name AS uploader_name
                FROM tech_manuals m
                LEFT JOIN users u ON m.uploaded_by = u.id
                WHERE {$whereSql}
                ORDER BY m.updated_at DESC, m.id DESC
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array(
            'data' => $rows,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => (int)ceil($total / $perPage),
        );
    }

    public function getById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM tech_manuals WHERE id = ?");
        $stmt->execute(array((int)$id));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create(array $data)
    {
        $stmt = $this->db->prepare("INSERT INTO tech_manuals
            (title, category, brand, model, description, tags,
             file_path, file_name, file_size, file_ext, uploaded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute(array(
            trim($data['title']),
            !empty($data['category']) ? trim($data['category']) : null,
            !empty($data['brand']) ? trim($data['brand']) : null,
            !empty($data['model']) ? trim($data['model']) : null,
            !empty($data['description']) ? trim($data['description']) : null,
            !empty($data['tags']) ? trim($data['tags']) : null,
            $data['file_path'],
            isset($data['file_name']) ? $data['file_name'] : null,
            isset($data['file_size']) ? (int)$data['file_size'] : 0,
            isset($data['file_ext']) ? strtolower($data['file_ext']) : null,
            Auth::id(),
        ));
        return (int)$this->db->lastInsertId();
    }

    /**
     * 更新 metadata（不一定換檔）；若有新檔，另外以 updateFile 更新檔案欄位
     */
    public function update($id, array $data)
    {
        $stmt = $this->db->prepare("UPDATE tech_manuals SET
            title = ?, category = ?, brand = ?, model = ?, description = ?, tags = ?
            WHERE id = ?");
        $stmt->execute(array(
            trim($data['title']),
            !empty($data['category']) ? trim($data['category']) : null,
            !empty($data['brand']) ? trim($data['brand']) : null,
            !empty($data['model']) ? trim($data['model']) : null,
            !empty($data['description']) ? trim($data['description']) : null,
            !empty($data['tags']) ? trim($data['tags']) : null,
            (int)$id,
        ));
    }

    public function updateFile($id, array $fileData)
    {
        $stmt = $this->db->prepare("UPDATE tech_manuals SET
            file_path = ?, file_name = ?, file_size = ?, file_ext = ?
            WHERE id = ?");
        $stmt->execute(array(
            $fileData['file_path'],
            isset($fileData['file_name']) ? $fileData['file_name'] : null,
            isset($fileData['file_size']) ? (int)$fileData['file_size'] : 0,
            isset($fileData['file_ext']) ? strtolower($fileData['file_ext']) : null,
            (int)$id,
        ));
    }

    public function delete($id)
    {
        $this->db->prepare("DELETE FROM tech_manuals WHERE id = ?")->execute(array((int)$id));
    }

    /**
     * 取得所有已使用的分類（for 篩選下拉與輸入建議）
     */
    public function getDistinctCategories()
    {
        $stmt = $this->db->query("SELECT DISTINCT category FROM tech_manuals WHERE category IS NOT NULL AND category <> '' ORDER BY category");
        $out = array();
        while ($r = $stmt->fetch(PDO::FETCH_NUM)) { $out[] = $r[0]; }
        return $out;
    }

    public function getDistinctBrands()
    {
        $stmt = $this->db->query("SELECT DISTINCT brand FROM tech_manuals WHERE brand IS NOT NULL AND brand <> '' ORDER BY brand");
        $out = array();
        while ($r = $stmt->fetch(PDO::FETCH_NUM)) { $out[] = $r[0]; }
        return $out;
    }
}
