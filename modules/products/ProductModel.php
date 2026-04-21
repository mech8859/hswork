<?php
/**
 * 產品資料模型
 */
class ProductModel
{
    /** @var PDO */
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * 取得產品清單 (含分頁/篩選)
     */
    public function getList(array $filters = array(), $page = 1, $perPage = 24)
    {
        // 排序
        $sortMap = array(
            'name_asc'      => 'p.name ASC',
            'name_desc'     => 'p.name DESC',
            'price_asc'     => 'p.price ASC',
            'price_desc'    => 'p.price DESC',
            'stock_desc'    => 'COALESCE(inv.total_stock, 0) DESC, p.name ASC',
            'stock_asc'     => 'COALESCE(inv.total_stock, 0) ASC, p.name ASC',
            'starred_first' => 'p.is_starred DESC, p.name ASC',
        );
        $sort = isset($filters['sort']) ? $filters['sort'] : 'stock_desc';
        $orderBy = isset($sortMap[$sort]) ? $sortMap[$sort] : $sortMap['stock_desc'];

        // 預設顯示全部（含停用），除非指定 active_only
        if (!empty($filters['active_only'])) {
            $where = 'p.is_active = 1';
        } elseif (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $where = 'p.is_active = ' . ((int)$filters['is_active']);
        } else {
            $where = '1=1';
        }
        $params = array();

        if (!empty($filters['keyword'])) {
            $where .= ' AND (p.name LIKE ? OR p.model LIKE ? OR p.vendor_model LIKE ? OR p.brand LIKE ?)';
            $kw = '%' . $filters['keyword'] . '%';
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
        }
        if (!empty($filters['category_id'])) {
            // 包含子分類
            $catIds = $this->getCategoryDescendants((int)$filters['category_id']);
            $catIds[] = (int)$filters['category_id'];
            $placeholders = implode(',', array_fill(0, count($catIds), '?'));
            $where .= " AND p.category_id IN ($placeholders)";
            $params = array_merge($params, $catIds);
        }
        if (!empty($filters['supplier'])) {
            $where .= ' AND p.supplier = ?';
            $params[] = $filters['supplier'];
        }
        if (isset($filters['has_stock']) && $filters['has_stock'] !== '') {
            if ($filters['has_stock'] === '1') {
                $where .= ' AND EXISTS (SELECT 1 FROM inventory inv2 WHERE inv2.product_id = p.id AND inv2.stock_qty > 0)';
            } else {
                $where .= ' AND NOT EXISTS (SELECT 1 FROM inventory inv2 WHERE inv2.product_id = p.id AND inv2.stock_qty > 0)';
            }
        }

        // 計算總數
        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM products p WHERE $where");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $page = max(1, (int)$page);
        $offset = ($page - 1) * $perPage;

        $stmt = $this->db->prepare("
            SELECT p.*, pc.name AS category_name,
                   pc.parent_id AS cat_parent_id,
                   pc2.name AS cat_parent_name,
                   pc2.parent_id AS cat_grandparent_id,
                   pc3.name AS cat_grandparent_name,
                   COALESCE(inv.total_stock, 0) AS total_stock,
                   COALESCE(inv.total_available, 0) AS total_available
            FROM products p
            LEFT JOIN product_categories pc ON p.category_id = pc.id
            LEFT JOIN product_categories pc2 ON pc.parent_id = pc2.id
            LEFT JOIN product_categories pc3 ON pc2.parent_id = pc3.id
            LEFT JOIN (
                SELECT product_id,
                       SUM(stock_qty) AS total_stock,
                       SUM(available_qty) AS total_available
                FROM inventory GROUP BY product_id
            ) inv ON p.id = inv.product_id
            WHERE $where
            ORDER BY $orderBy
            LIMIT $perPage OFFSET $offset
        ");
        $stmt->execute($params);

        return array(
            'data'     => $stmt->fetchAll(),
            'total'    => $total,
            'page'     => $page,
            'perPage'  => $perPage,
            'lastPage' => (int)ceil($total / max($perPage, 1)),
        );
    }

    /**
     * 取得單一產品
     */
    public function getById($id)
    {
        $stmt = $this->db->prepare('
            SELECT p.*, pc.name AS category_name,
                   COALESCE(inv.total_stock, 0) AS total_stock,
                   COALESCE(inv.total_available, 0) AS total_available
            FROM products p
            LEFT JOIN product_categories pc ON p.category_id = pc.id
            LEFT JOIN (
                SELECT product_id,
                       SUM(stock_qty) AS total_stock,
                       SUM(available_qty) AS total_available
                FROM inventory GROUP BY product_id
            ) inv ON p.id = inv.product_id
            WHERE p.id = ?
        ');
        $stmt->execute(array($id));
        $product = $stmt->fetch();
        if (!$product) return null;

        // 取得分類路徑
        if ($product['category_id']) {
            $product['category_path'] = $this->getCategoryPath($product['category_id']);
        }

        return $product;
    }

    /**
     * 切換星標（產品分類用）
     * 回傳新的 is_starred 值（0 或 1）
     */
    public function toggleStar($id)
    {
        $id = (int)$id;
        $stmt = $this->db->prepare('SELECT is_starred FROM products WHERE id = ?');
        $stmt->execute(array($id));
        $row = $stmt->fetch();
        if (!$row) return null;
        $newVal = ((int)$row['is_starred']) ? 0 : 1;
        $this->db->prepare('UPDATE products SET is_starred = ? WHERE id = ?')
            ->execute(array($newVal, $id));
        return $newVal;
    }

    /**
     * 更新價格
     */
    public function updatePrice($id, array $data)
    {
        $stmt = $this->db->prepare('
            UPDATE products SET price = ?, cost = ?, retail_price = ?, labor_cost = ?
            WHERE id = ?
        ');
        $stmt->execute(array(
            (float)$data['price'],
            (float)$data['cost'],
            (float)$data['retail_price'],
            !empty($data['labor_cost']) ? (float)$data['labor_cost'] : null,
            (int)$id
        ));
    }

    /**
     * 取得頂層分類（供篩選用）
     */
    public function getTopCategories()
    {
        $stmt = $this->db->query('
            SELECT pc.*, COUNT(p.id) AS product_count
            FROM product_categories pc
            LEFT JOIN product_categories sub ON sub.parent_id = pc.id
            LEFT JOIN products p ON (p.category_id = pc.id OR p.category_id = sub.id) AND p.is_active = 1
            WHERE pc.parent_id IS NULL
            GROUP BY pc.id
            HAVING product_count > 0
            ORDER BY pc.name
        ');
        return $stmt->fetchAll();
    }

    /**
     * 取得分類的所有子分類 ID
     */
    public function getCategoryDescendants($parentId)
    {
        $ids = array();
        $stmt = $this->db->prepare('SELECT id FROM product_categories WHERE parent_id = ?');
        $stmt->execute(array($parentId));
        $children = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($children as $childId) {
            $ids[] = (int)$childId;
            $ids = array_merge($ids, $this->getCategoryDescendants((int)$childId));
        }
        return $ids;
    }

    /**
     * 取得分類路徑 (如: 監控系統 > 攝影機 > 球型)
     */
    public function getCategoryPath($categoryId)
    {
        $path = array();
        $maxDepth = 5;
        $currentId = $categoryId;

        while ($currentId && $maxDepth-- > 0) {
            $stmt = $this->db->prepare('SELECT id, name, parent_id FROM product_categories WHERE id = ?');
            $stmt->execute(array($currentId));
            $cat = $stmt->fetch();
            if (!$cat) break;
            array_unshift($path, $cat['name']);
            $currentId = $cat['parent_id'];
        }

        return implode(' > ', $path);
    }

    /**
     * 取得所有分類（扁平化含完整路徑，供表單 select 用）
     * 使用靜態快取 + 迭代式 top-down 路徑建構，避免重複計算
     */
    public function getAllCategoriesFlat()
    {
        // 靜態快取：同一請求中多次呼叫只計算一次
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $stmt = $this->db->query("SELECT id, name, parent_id FROM product_categories ORDER BY name");
        $all = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 建立 id => row map 及 children map
        $map = array();
        $children = array();
        $roots = array();
        foreach ($all as $cat) {
            $map[$cat['id']] = $cat;
            $pid = $cat['parent_id'];
            if (empty($pid)) {
                $roots[] = $cat['id'];
            } else {
                if (!isset($children[$pid])) {
                    $children[$pid] = array();
                }
                $children[$pid][] = $cat['id'];
            }
        }

        // 迭代式 BFS 建構路徑（避免遞迴）
        $paths = array();   // id => full_path string
        $depths = array();  // id => depth int
        $stack = array();
        foreach ($roots as $rid) {
            $stack[] = array($rid, $map[$rid]['name'], 0);
        }

        while (!empty($stack)) {
            $item = array_pop($stack);
            $id = $item[0];
            $path = $item[1];
            $depth = $item[2];

            $paths[$id] = $path;
            $depths[$id] = $depth;

            if (isset($children[$id])) {
                foreach ($children[$id] as $childId) {
                    $stack[] = array($childId, $path . ' > ' . $map[$childId]['name'], $depth + 1);
                }
            }
        }

        // 處理孤兒節點（parent_id 指向不存在的分類）
        foreach ($all as $cat) {
            if (!isset($paths[$cat['id']])) {
                $paths[$cat['id']] = $cat['name'];
                $depths[$cat['id']] = 0;
            }
        }

        // 組合結果
        $result = array();
        foreach ($all as $cat) {
            $result[] = array(
                'id' => $cat['id'],
                'name' => $cat['name'],
                'parent_id' => $cat['parent_id'],
                'full_path' => $paths[$cat['id']],
                'depth' => $depths[$cat['id']],
            );
        }

        // 依完整路徑排序
        usort($result, function ($a, $b) {
            return strcmp($a['full_path'], $b['full_path']);
        });

        $cache = $result;
        return $cache;
    }

    /**
     * 取得所有分類（含層級，供管理用）
     */
    public function getAllCategoriesTree()
    {
        $stmt = $this->db->query("
            SELECT pc.*,
                   (SELECT COUNT(*) FROM product_categories WHERE parent_id = pc.id) AS child_count,
                   (SELECT COUNT(*) FROM products WHERE category_id = pc.id AND is_active = 1) AS product_count
            FROM product_categories pc
            ORDER BY pc.name
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 建立分類
     */
    public function createCategory($name, $parentId, $excludeStockout = 0, $showInMaterialEstimate = 0)
    {
        $stmt = $this->db->prepare("INSERT INTO product_categories (name, parent_id, exclude_from_stockout, show_in_material_estimate) VALUES (?, ?, ?, ?)");
        $stmt->execute(array(trim($name), $parentId ? (int)$parentId : null, (int)$excludeStockout, (int)$showInMaterialEstimate));
        return $this->db->lastInsertId();
    }

    /**
     * 更新分類
     */
    public function updateCategory($id, $name, $parentId, $excludeStockout = 0, $showInMaterialEstimate = 0)
    {
        $stmt = $this->db->prepare("UPDATE product_categories SET name = ?, parent_id = ?, exclude_from_stockout = ?, show_in_material_estimate = ? WHERE id = ?");
        $stmt->execute(array(trim($name), $parentId ? (int)$parentId : null, (int)$excludeStockout, (int)$showInMaterialEstimate, (int)$id));
    }

    /**
     * 刪除分類（僅無子分類且無產品時可刪）
     */
    public function deleteCategory($id)
    {
        // 檢查是否有子分類
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM product_categories WHERE parent_id = ?");
        $stmt->execute(array($id));
        if ((int)$stmt->fetchColumn() > 0) {
            return '此分類下還有子分類，無法刪除';
        }
        // 檢查是否有產品
        $stmt2 = $this->db->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
        $stmt2->execute(array($id));
        if ((int)$stmt2->fetchColumn() > 0) {
            return '此分類下還有產品，無法刪除';
        }
        $this->db->prepare("DELETE FROM product_categories WHERE id = ?")->execute(array($id));
        return true;
    }

    /**
     * 取得不重複供應商列表
     */
    public function getSuppliers()
    {
        $stmt = $this->db->query("
            SELECT DISTINCT supplier FROM products
            WHERE supplier IS NOT NULL AND supplier != '' AND is_active = 1
            ORDER BY supplier
        ");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
