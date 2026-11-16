<?php
require_once __DIR__ . '/includes/helpers.php';
header('Content-Type: application/json');

$user = getCurrentUser();
if (!$user) {
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

$rawInput = json_decode(file_get_contents('php://input'), true);
$input = $rawInput ?: $_POST;

$title = trim($input['title'] ?? '');
$condition = $input['condition_status'] ?? $input['item_condition'] ?? 'Like New';
$price = (float)($input['price'] ?? 0);
$description = trim($input['description'] ?? '');
$categoryInput = $input['category_id'] ?? $input['category'] ?? 1;

$categoryId = 1;
if (is_numeric($categoryInput)) {
    $categoryId = (int)$categoryInput;
} else {
    $categoryMap = [
        'Books' => 1,
        'Electronics' => 2,
        'Lifestyle' => 3,
        'Fashion' => 4,
        'Sports' => 3
    ];
    $categoryId = $categoryMap[$categoryInput] ?? 1;
}

if (empty($title) || $price <= 0) {
    echo json_encode(['success' => false, 'message' => '请输入有效的商品标题和价格']);
    exit;
}

$uploadedFiles = [];
if (!empty($_FILES['images']['name'])) {
    $names = array_filter((array)$_FILES['images']['name']);
    foreach ($names as $index => $name) {
        $uploadedFiles[] = [
            'name' => $_FILES['images']['name'][$index] ?? $name,
            'type' => $_FILES['images']['type'][$index] ?? 'application/octet-stream',
            'tmp_name' => $_FILES['images']['tmp_name'][$index] ?? '',
            'error' => $_FILES['images']['error'][$index] ?? 0,
            'size' => $_FILES['images']['size'][$index] ?? 0,
        ];
    }
} elseif (!empty($_FILES['image']['name'])) {
    $uploadedFiles = [$_FILES['image']];
}

if (count($uploadedFiles) < 1 || count($uploadedFiles) > 3) {
    echo json_encode(['success' => false, 'message' => 'Please upload at least 1 photo and no more than 3 photos.']);
    exit;
}

$uploadDir = __DIR__ . '/uploads/products';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$storedImagePaths = [];
foreach ($uploadedFiles as $index => $file) {
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid uploaded image detected.']);
        exit;
    }

    $fileName = basename($file['name']);
    $safeName = time() . '_' . $index . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $fileName);
    $tmpPath = $file['tmp_name'];
    $targetPath = $uploadDir . '/' . $safeName;

    $mime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $tmpPath);
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $allowed, true)) {
        echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, and WEBP images are allowed.']);
        exit;
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Each image must be smaller than 2MB.']);
        exit;
    }

    if (!move_uploaded_file($tmpPath, $targetPath)) {
        echo json_encode(['success' => false, 'message' => 'Unable to save uploaded image.']);
        exit;
    }

    $storedImagePaths[] = 'uploads/products/' . $safeName;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO products (seller_id, university_id, category_id, title, description, price, item_condition, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'Available')
    ");
    $stmt->execute([
        $user['user_id'] ?? $user['id'],
        $user['university_id'] ?? 1,
        $categoryId,
        $title,
        $description,
        $price,
        $condition
    ]);
    $productId = $pdo->lastInsertId();

    $stmtImg = $pdo->prepare("
        INSERT INTO product_images (product_id, image_url, is_primary)
        VALUES (?, ?, ?)
    ");

    foreach ($storedImagePaths as $index => $imagePath) {
        $stmtImg->execute([$productId, $imagePath, $index === 0 ? 1 : 0]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'images' => $storedImagePaths]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>