<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/products/ProductModel.php';

$model = new ProductModel();
$action = $_GET['action'] ?? 'list';

/**
 * 上傳產品相關檔案
 */
function _uploadProductFile($file, $subdir) {
    $uploadDir = __DIR__ . '/uploads/products/' . $subdir . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = array('jpg','jpeg','png','gif','webp','pdf','doc','docx','xls','xlsx');
    if (!in_array($ext, $allowed)) {
        return '';
    }
    // 限制 10MB
    if ($file['size'] > 10 * 1024 * 1024) {
        return '';
    }
    $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = $uploadDir . $filename;
    if (move_uploaded_file($file['tmp_name'], $dest)) {
        backup_to_drive($dest, 'products', $subdir);
        return '/uploads/products/' . $subdir . '/' . $filename;
    }
    return '';
}

switch ($action) {
    // ---- 產品列表 ----
    case 'list':
        $filters = array(
            'keyword'     => trim($_GET['keyword'] ?? ''),
            'category_id' => (int)($_GET['category_id'] ?? 0),
            'supplier'    => trim($_GET['supplier'] ?? ''),
            'is_active'   => isset($_GET['is_active']) && $_GET['is_active'] !== '' ? $_GET['is_active'] : '',
            'has_stock'   => isset($_GET['has_stock']) && $_GET['has_stock'] !== '' ? $_GET['has_stock'] : '',
            'sort'        => isset($_GET['sort']) ? $_GET['sort'] : 'stock_desc',
        );
        $page = max(1, (int)($_GET['page'] ?? 1));

        $result = $model->getList($filters, $page, 24);
        $categories = $model->getTopCategories();
        $suppliers = $model->getSuppliers();

        $pageTitle = '產品目錄';
        $currentPage = 'products';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/products/list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 產品詳情 ----
    case 'view':
        $id = (int)($_GET['id'] ?? 0);
        $product = $model->getById($id);
        if (!$product) {
            Session::flash('error', '產品不存在');
            redirect('/products.php');
        }

        $pageTitle = $product['name'];
        $currentPage = 'products';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/products/view.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 修改價格 ----
    case 'edit_price':
        if (!Auth::hasPermission('admin')) {
            Session::flash('error', '權限不足');
            redirect('/products.php');
        }

        $id = (int)($_GET['id'] ?? 0);
        $product = $model->getById($id);
        if (!$product) {
            Session::flash('error', '產品不存在');
            redirect('/products.php');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/products.php?action=edit_price&id=' . $id);
            }
            $model->updatePrice($id, $_POST);
            Session::flash('success', '價格已更新');
            redirect('/products.php?action=view&id=' . $id);
        }

        $pageTitle = '修改價格 - ' . $product['name'];
        $currentPage = 'products';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/products/edit_price.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 新增產品 ----
    case 'create':
        if (!Auth::hasPermission('products.manage') && !in_array(Auth::user()['role'], array('boss','manager'))) {
            Session::flash('error', '權限不足'); redirect('/products.php');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/products.php'); }
            $db = Database::getInstance();

            // 處理圖片上傳
            $imageUrl = '';
            if (!empty($_FILES['image_file']['tmp_name']) && $_FILES['image_file']['error'] === 0) {
                $imageUrl = _uploadProductFile($_FILES['image_file'], 'images');
            } elseif (!empty(trim($_POST['image_url'] ?? ''))) {
                $imageUrl = trim($_POST['image_url']);
            }

            // 處理規格書上傳
            $datasheetUrl = '';
            if (!empty($_FILES['datasheet_file']['tmp_name']) && $_FILES['datasheet_file']['error'] === 0) {
                $datasheetUrl = _uploadProductFile($_FILES['datasheet_file'], 'datasheets');
            } elseif (!empty(trim($_POST['datasheet_url'] ?? ''))) {
                $datasheetUrl = trim($_POST['datasheet_url']);
            }

            $stmt = $db->prepare("INSERT INTO products (name, model, vendor_model, brand, supplier, description, specifications, warranty_text, unit, price, cost, retail_price, labor_cost, pack_qty, pack_unit, cost_per_unit, category_id, stock, is_active, discontinue_when_empty, image, datasheet) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute(array(
                trim($_POST['name'] ?? ''),
                trim($_POST['model'] ?? ''),
                trim($_POST['vendor_model'] ?? ''),
                trim($_POST['brand'] ?? ''),
                trim($_POST['supplier'] ?? ''),
                trim($_POST['description'] ?? ''),
                trim($_POST['specifications'] ?? ''),
                trim($_POST['warranty_text'] ?? ''),
                trim($_POST['unit'] ?? '台'),
                (int)($_POST['price'] ?? 0),
                (int)($_POST['cost'] ?? 0),
                (int)($_POST['retail_price'] ?? 0),
                (int)($_POST['labor_cost'] ?? 0),
                !empty($_POST['pack_qty']) ? (float)$_POST['pack_qty'] : null,
                !empty($_POST['pack_unit']) ? trim($_POST['pack_unit']) : null,
                null, // cost_per_unit 下面後端計算
                !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
                (int)($_POST['stock'] ?? 0),
                1,
                !empty($_POST['discontinue_when_empty']) ? 1 : 0,
                $imageUrl,
                $datasheetUrl,
            ));
            $newId = (int)$db->lastInsertId();
            // 後端計算 cost_per_unit
            $createCost = (float)($_POST['cost'] ?? 0);
            $createPackQty = !empty($_POST['pack_qty']) ? (float)$_POST['pack_qty'] : 0;
            $createCpu = $createPackQty > 0 ? round($createCost / $createPackQty, 4) : ($createCost > 0 ? $createCost : null);
            $db->prepare("UPDATE products SET cost_per_unit = ? WHERE id = ?")->execute(array($createCpu, $newId));
            AuditLog::log('products', 'create', $newId, $_POST['name'] ?? '');
            Session::flash('success', '產品已新增');
            redirect('/products.php?action=view&id=' . $newId);
        }
        $product = null;
        $categories = $model->getTopCategories();
        $allCategories = $model->getAllCategoriesFlat();
        $pageTitle = '新增產品';
        $currentPage = 'products';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/products/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 編輯產品 ----
    case 'edit':
        if (!Auth::hasPermission('products.manage') && !in_array(Auth::user()['role'], array('boss','manager'))) {
            Session::flash('error', '權限不足'); redirect('/products.php');
        }
        $id = (int)($_GET['id'] ?? 0);
        $product = $model->getById($id);
        if (!$product) { Session::flash('error', '產品不存在'); redirect('/products.php'); }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/products.php?action=edit&id='.$id); }
            $db = Database::getInstance();
            $oldProduct = $product;

            // 處理圖片上傳
            $imageUrl = $product['image'];
            if (!empty($_FILES['image_file']['tmp_name']) && $_FILES['image_file']['error'] === 0) {
                $imageUrl = _uploadProductFile($_FILES['image_file'], 'images');
            } elseif (!empty(trim($_POST['image_url'] ?? ''))) {
                $imageUrl = trim($_POST['image_url']);
            }

            // 處理規格書上傳
            $datasheetUrl = isset($product['datasheet']) ? $product['datasheet'] : '';
            if (!empty($_FILES['datasheet_file']['tmp_name']) && $_FILES['datasheet_file']['error'] === 0) {
                $datasheetUrl = _uploadProductFile($_FILES['datasheet_file'], 'datasheets');
            } elseif (!empty(trim($_POST['datasheet_url'] ?? ''))) {
                $datasheetUrl = trim($_POST['datasheet_url']);
            }

            $db->prepare("UPDATE products SET name=?, model=?, vendor_model=?, brand=?, supplier=?, description=?, specifications=?, warranty_text=?, unit=?, price=?, cost=?, retail_price=?, labor_cost=?, pack_qty=?, pack_unit=?, cost_per_unit=?, category_id=?, is_active=?, discontinue_when_empty=?, image=?, datasheet=? WHERE id=?")->execute(array(
                trim($_POST['name'] ?? ''),
                trim($_POST['model'] ?? ''),
                trim($_POST['vendor_model'] ?? ''),
                trim($_POST['brand'] ?? ''),
                trim($_POST['supplier'] ?? ''),
                trim($_POST['description'] ?? ''),
                trim($_POST['specifications'] ?? ''),
                trim($_POST['warranty_text'] ?? ''),
                trim($_POST['unit'] ?? '台'),
                (int)($_POST['price'] ?? 0),
                (int)($_POST['cost'] ?? 0),
                (int)($_POST['retail_price'] ?? 0),
                (int)($_POST['labor_cost'] ?? 0),
                !empty($_POST['pack_qty']) ? (float)$_POST['pack_qty'] : null,
                !empty($_POST['pack_unit']) ? trim($_POST['pack_unit']) : null,
                null, // cost_per_unit 下面後端計算
                !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
                isset($_POST['is_active']) ? 1 : 0,
                !empty($_POST['discontinue_when_empty']) ? 1 : 0,
                $imageUrl,
                $datasheetUrl,
                $id,
            ));
            // 後端計算 cost_per_unit
            $editCost = (float)($_POST['cost'] ?? 0);
            $editPackQty = !empty($_POST['pack_qty']) ? (float)$_POST['pack_qty'] : 0;
            $editCpu = $editPackQty > 0 ? round($editCost / $editPackQty, 4) : ($editCost > 0 ? $editCost : null);
            $db->prepare("UPDATE products SET cost_per_unit = ? WHERE id = ?")->execute(array($editCpu, $id));
            AuditLog::logChange('products', $id, $product['name'], $oldProduct, $_POST, array('name','model','price','cost','category_id'));

            // 儲存歷史價格
            if (isset($_POST['ph_date_from'])) {
                // 先取得現有的 ID 列表
                $existingIds = array();
                try {
                    $exStmt = $db->prepare("SELECT id FROM product_price_history WHERE product_id = ?");
                    $exStmt->execute(array($id));
                    $existingIds = $exStmt->fetchAll(PDO::FETCH_COLUMN);
                } catch (Exception $e) {}

                $submittedIds = array();
                foreach ($_POST['ph_date_from'] as $idx => $dateFrom) {
                    $dateFrom = trim($dateFrom);
                    if (empty($dateFrom)) continue;
                    $dateTo = !empty($_POST['ph_date_to'][$idx]) ? trim($_POST['ph_date_to'][$idx]) : null;
                    $cost = (int)($_POST['ph_cost'][$idx] ?? 0);
                    $phId = (int)($_POST['ph_id'][$idx] ?? 0);

                    if ($phId > 0 && in_array($phId, $existingIds)) {
                        // 更新
                        $db->prepare("UPDATE product_price_history SET date_from=?, date_to=?, cost=? WHERE id=? AND product_id=?")->execute(array($dateFrom, $dateTo, $cost, $phId, $id));
                        $submittedIds[] = $phId;
                    } else {
                        // 新增
                        $db->prepare("INSERT INTO product_price_history (product_id, date_from, date_to, cost) VALUES (?,?,?,?)")->execute(array($id, $dateFrom, $dateTo, $cost));
                    }
                }
                // 刪除被移除的列
                $toDelete = array_diff($existingIds, $submittedIds);
                foreach ($toDelete as $delId) {
                    $db->prepare("DELETE FROM product_price_history WHERE id=? AND product_id=?")->execute(array($delId, $id));
                }
            }

            Session::flash('success', '產品已更新');
            redirect('/products.php?action=view&id=' . $id);
        }
        $categories = $model->getTopCategories();
        $allCategories = $model->getAllCategoriesFlat();
        $pageTitle = '編輯產品 - ' . $product['name'];
        $currentPage = 'products';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/products/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- AJAX: 切換星標（需管理權限）----
    case 'ajax_toggle_star':
        header('Content-Type: application/json');
        $canManage = Auth::hasPermission('products.manage') || in_array(Auth::user()['role'], array('boss','manager'));
        if (!$canManage) {
            echo json_encode(array('success' => false, 'error' => '權限不足'));
            exit;
        }
        if (!verify_csrf()) {
            echo json_encode(array('success' => false, 'error' => 'CSRF'));
            exit;
        }
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(array('success' => false, 'error' => '無效的產品 ID'));
            exit;
        }
        $newVal = $model->toggleStar($id);
        if ($newVal === null) {
            echo json_encode(array('success' => false, 'error' => '產品不存在'));
            exit;
        }
        AuditLog::log('products', 'toggle_star', $id, 'is_starred=' . $newVal);
        echo json_encode(array('success' => true, 'is_starred' => $newVal));
        exit;

    // ---- AJAX: 產品搜尋（施工回報材料用）----
    case 'ajax_search':
        $keyword = trim($_GET['keyword'] ?? '');
        if (mb_strlen($keyword) < 2) {
            json_response(array('data' => array()));
        }
        // 施工回報用：只顯示「預計線材」分類（含子孫）下的產品——工程師只填線材+耗材
        $filters = array('keyword' => $keyword);
        $materialCatIds = ProductModel::getCategoryIdsByFlag('show_in_material_estimate');
        if (!empty($materialCatIds)) {
            $filters['category_ids_in'] = $materialCatIds;
        }
        $result = $model->getList($filters, 1, 10);
        $items = array();
        foreach ($result['data'] as $p) {
            $items[] = array(
                'id' => $p['id'],
                'name' => $p['name'],
                'model_number' => $p['model_number'] ?? '',
                'price' => $p['price'] ?? 0,
                'unit' => $p['unit'] ?? '',
                'pack_qty' => $p['pack_qty'] ?? null,
                'pack_unit' => $p['pack_unit'] ?? null,
                'cost_per_unit' => $p['cost_per_unit'] ?? null,
            );
        }
        json_response(array('data' => $items));
        break;

    // ---- 分類管理 ----
    case 'categories':
        if (!Auth::hasPermission('products.manage') && !in_array(Auth::user()['role'], array('boss','manager'))) {
            Session::flash('error', '權限不足'); redirect('/products.php');
        }
        $allCategories = $model->getAllCategoriesFlat();
        $categoriesTree = $model->getAllCategoriesTree();

        $pageTitle = '產品分類管理';
        $currentPage = 'products';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/products/categories.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'category_save':
        if (!Auth::hasPermission('products.manage') && !in_array(Auth::user()['role'], array('boss','manager'))) {
            Session::flash('error', '權限不足'); redirect('/products.php');
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('/products.php?action=categories'); }
        if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/products.php?action=categories'); }

        $catId = !empty($_POST['id']) ? (int)$_POST['id'] : 0;
        $catName = trim($_POST['name'] ?? '');
        $catParent = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $excludeStockout = !empty($_POST['exclude_from_stockout']) ? 1 : 0;
        $showInMaterial = !empty($_POST['show_in_material_estimate']) ? 1 : 0;

        if (!$catName) {
            Session::flash('error', '請輸入分類名稱');
            redirect('/products.php?action=categories');
        }
        if ($catId) {
            $model->updateCategory($catId, $catName, $catParent, $excludeStockout, $showInMaterial);
            Session::flash('success', '分類已更新');
        } else {
            $model->createCategory($catName, $catParent, $excludeStockout, $showInMaterial);
            Session::flash('success', '分類已新增');
        }
        redirect('/products.php?action=categories');
        break;

    case 'category_delete':
        if (!Auth::hasPermission('products.manage') && !in_array(Auth::user()['role'], array('boss','manager'))) {
            Session::flash('error', '權限不足'); redirect('/products.php');
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('/products.php?action=categories'); }
        if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/products.php?action=categories'); }

        $catId = (int)($_POST['id'] ?? 0);
        $result = $model->deleteCategory($catId);
        if ($result === true) {
            Session::flash('success', '分類已刪除');
        } else {
            Session::flash('error', $result);
        }
        redirect('/products.php?action=categories');
        break;

    // ---- AJAX: 取得子分類 ----
    case 'ajax_subcategories':
        header('Content-Type: application/json');
        $parentId = (int)($_GET['parent_id'] ?? 0);
        $db = Database::getInstance();
        if ($parentId === 0) {
            $stmt = $db->query("SELECT id, name FROM product_categories WHERE parent_id IS NULL OR parent_id = 0 ORDER BY name");
        } else {
            $stmt = $db->prepare("SELECT id, name FROM product_categories WHERE parent_id = ? ORDER BY name");
            $stmt->execute(array($parentId));
        }
        echo json_encode($stmt->fetchAll());
        exit;

    // ---- AJAX: 新增分類（JSON回傳）----
    case 'ajax_category_create':
        header('Content-Type: application/json');
        if (!verify_csrf()) { echo json_encode(array('success' => false, 'error' => 'CSRF')); exit; }
        $catName = trim($_POST['name'] ?? '');
        $catParent = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        if (!$catName) { echo json_encode(array('success' => false, 'error' => '名稱不可為空')); exit; }
        // 檢查同層是否已存在
        $db = Database::getInstance();
        if ($catParent) {
            $chk = $db->prepare("SELECT id FROM product_categories WHERE name = ? AND parent_id = ?");
            $chk->execute(array($catName, $catParent));
        } else {
            $chk = $db->prepare("SELECT id FROM product_categories WHERE name = ? AND (parent_id IS NULL OR parent_id = 0)");
            $chk->execute(array($catName));
        }
        $existing = $chk->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            echo json_encode(array('success' => true, 'id' => (int)$existing['id'], 'name' => $catName, 'existed' => true));
        } else {
            $newId = $model->createCategory($catName, $catParent);
            echo json_encode(array('success' => true, 'id' => (int)$newId, 'name' => $catName, 'existed' => false));
        }
        exit;

    // ---- 刪除產品（僅限停用的）----
    case 'delete':
        if (!Auth::hasPermission('products.delete')) {
            Session::flash('error', '無刪除權限');
            redirect('/products.php');
        }
        if (!verify_csrf()) {
            Session::flash('error', '安全驗證失敗');
            redirect('/products.php');
        }
        $id = (int)($_GET['id'] ?? 0);
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT id, name, is_active FROM products WHERE id = ?");
        $stmt->execute(array($id));
        $prod = $stmt->fetch();
        if (!$prod) {
            Session::flash('error', '產品不存在');
        } elseif ($prod['is_active']) {
            Session::flash('error', '啟用中的產品不可刪除，請先停用');
        } else {
            $db->prepare("DELETE FROM products WHERE id = ? AND is_active = 0")->execute(array($id));
            Session::flash('success', '產品「' . $prod['name'] . '」已刪除');
        }
        redirect('/products.php?is_active=0');
        break;

    default:
        redirect('/products.php');
}
