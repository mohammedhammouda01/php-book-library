<?php
session_start();

$genres = ["Fiction", "Non-Fiction", "Science", "History", "Biography", "Technology"];

if (!isset($_SESSION['books'])) {
    $_SESSION['books'] = [
        ['id' => 1, 'title' => 'The Great Gatsby', 'author' => 'F. Scott Fitzgerald', 'genre' => 'Fiction', 'year' => 1925, 'pages' => 180, 'image_url' => 'https://covers.openlibrary.org/b/id/8432047-M.jpg'],
        ['id' => 2, 'title' => 'Clean Code', 'author' => 'Robert Martin', 'genre' => 'Technology', 'year' => 2008, 'pages' => 464, 'image_url' => 'https://covers.openlibrary.org/b/id/10235336-M.jpg'],
        ['id' => 3, 'title' => 'Sapiens', 'author' => 'Yuval Noah Harari', 'genre' => 'History', 'year' => 2011, 'pages' => 443, 'image_url' => 'https://covers.openlibrary.org/b/id/12869584-M.jpg']
    ];
}

$errors = [];
$submittedData = [];
$isEditMode = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $idToDelete = (int)$_POST['book_id'];
        $_SESSION['books'] = array_filter($_SESSION['books'], function($book) use ($idToDelete) {
            return $book['id'] !== $idToDelete;
        });
        $_SESSION['books'] = array_values($_SESSION['books']);
        $_SESSION['success'] = "تم حذف الكتاب بنجاح.";
        header("Location: index.php");
        exit;
    }

    $submittedData = [
        'title' => trim(htmlspecialchars($_POST['title'])),
        'author' => trim(htmlspecialchars($_POST['author'])),
        'genre' => $_POST['genre'] ?? '',
        'year' => $_POST['year'] ?? '',
        'pages' => $_POST['pages'] ?? '',
        'image_url' => trim(htmlspecialchars($_POST['image_url'] ?? ''))
    ];

    if (strlen($submittedData['title']) < 3 || strlen($submittedData['title']) > 120) {
        $errors['title'] = "العنوان مطلوب (3-120 حرفاً).";
    }
    if (empty($submittedData['author']) || str_word_count($submittedData['author']) < 2) {
        $errors['author'] = "اسم المؤلف يجب أن يتكون من كلمتين على الأقل.";
    }
    if (empty($submittedData['genre']) || !in_array($submittedData['genre'], $genres)) {
        $errors['genre'] = "التصنيف المختار غير صالح.";
    }
    $currentYear = (int)date("Y");
    if (empty($submittedData['year']) || !filter_var($submittedData['year'], FILTER_VALIDATE_INT) || $submittedData['year'] < 1000 || $submittedData['year'] > $currentYear) {
        $errors['year'] = "السنة غير صحيحة (1000 - $currentYear).";
    }
    if (empty($submittedData['pages']) || !filter_var($submittedData['pages'], FILTER_VALIDATE_INT) || $submittedData['pages'] <= 0) {
        $errors['pages'] = "عدد الصفحات يجب أن يكون رقماً موجباً.";
    }
    if (!empty($submittedData['image_url'])) {
        $allowedExt = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo(parse_url($submittedData['image_url'], PHP_URL_PATH), PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt)) {
            $errors['image_url'] = "يجب أن ينتهي الرابط بـ .jpg, .jpeg, .png, أو .gif";
        }
    }

    if (empty($errors)) {
        $submittedData['year'] = (int)$submittedData['year'];
        $submittedData['pages'] = (int)$submittedData['pages'];

        if (isset($_POST['edit_id'])) {
            foreach ($_SESSION['books'] as &$book) {
                if ($book['id'] === (int)$_POST['edit_id']) {
                    $book = array_merge($book, $submittedData);
                    $book['id'] = (int)$_POST['edit_id'];
                    break;
                }
            }
            $_SESSION['success'] = "تم تحديث بيانات الكتاب بنجاح.";
        } else {
            $maxId = 0;
            foreach ($_SESSION['books'] as $book) { if ($book['id'] > $maxId) $maxId = $book['id']; }
            $submittedData['id'] = $maxId + 1;
            $_SESSION['books'][] = $submittedData;
            $_SESSION['success'] = "تمت إضافة الكتاب بنجاح.";
        }
        header("Location: index.php");
        exit;
    }
}

if (isset($_GET['edit_id'])) {
    $isEditMode = true;
    foreach ($_SESSION['books'] as $book) {
        if ($book['id'] == $_GET['edit_id']) { $submittedData = $book; break; }
    }
}

$displayBooks = $_SESSION['books'];
$searchTerm = $_GET['search'] ?? '';
if (!empty($searchTerm)) {
    $displayBooks = array_filter($displayBooks, function($book) use ($searchTerm) {
        return stripos($book['title'], $searchTerm) !== false || stripos($book['author'], $searchTerm) !== false;
    });
}

$allowedSortColumns = ['id', 'title', 'author', 'year'];
$sortColumn = $_GET['sort'] ?? 'id';
if (!in_array($sortColumn, $allowedSortColumns)) {
    $sortColumn = 'id';
}
$sortOrder = $_GET['order'] ?? 'asc';
if (!in_array($sortOrder, ['asc', 'desc'])) {
    $sortOrder = 'asc';
}
usort($displayBooks, function($a, $b) use ($sortColumn, $sortOrder) {
    $valA = $a[$sortColumn] ?? '';
    $valB = $b[$sortColumn] ?? '';
    return ($sortOrder === 'asc') ? $valA <=> $valB : $valB <=> $valA;
});
$nextOrder = ($sortOrder === 'asc') ? 'desc' : 'asc';
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>مكتبة الكتب الشخصية</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sort-link { text-decoration: none; color: white; }
        .book-img { width: 45px; height: 65px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd; }
        .is-invalid { border-color: #dc3545 !important; }
    </style>
</head>
<body class="bg-light">
<div class="container mt-5">
    <h2 class="text-center mb-4">إدارة مكتبة الكتب الشخصية</h2>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm">
            <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm p-3 mb-4">
                <h5 class="mb-3 text-primary"><?= $isEditMode ? "تعديل بيانات الكتاب" : "إضافة كتاب جديد" ?></h5>
                <form method="POST">
                    <?php if ($isEditMode): ?> <input type="hidden" name="edit_id" value="<?= $submittedData['id'] ?>"> <?php endif; ?>
                    
                    <div class="mb-2">
                        <label class="form-label">رابط غلاف الكتاب (URL)</label>
                        <input type="text" name="image_url" class="form-control <?= isset($errors['image_url']) ? 'is-invalid' : '' ?>" 
                               value="<?= htmlspecialchars($submittedData['image_url'] ?? '') ?>" placeholder="https://example.com/cover.jpg">
                        <div class="invalid-feedback"><?= $errors['image_url'] ?? '' ?></div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">عنوان الكتاب</label>
                        <input type="text" name="title" class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>" 
                               value="<?= htmlspecialchars($submittedData['title'] ?? '') ?>" required>
                        <div class="invalid-feedback"><?= $errors['title'] ?? '' ?></div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">اسم المؤلف</label>
                        <input type="text" name="author" class="form-control <?= isset($errors['author']) ? 'is-invalid' : '' ?>" 
                               value="<?= htmlspecialchars($submittedData['author'] ?? '') ?>" required>
                        <div class="invalid-feedback"><?= $errors['author'] ?? '' ?></div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">التصنيف</label>
                        <select name="genre" class="form-select <?= isset($errors['genre']) ? 'is-invalid' : '' ?>">
                            <?php foreach ($genres as $g): ?>
                                <option value="<?= $g ?>" <?= (isset($submittedData['genre']) && $submittedData['genre'] == $g) ? 'selected' : '' ?>><?= $g ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback"><?= $errors['genre'] ?? '' ?></div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label">سنة النشر</label>
                            <input type="number" name="year" class="form-control <?= isset($errors['year']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($submittedData['year'] ?? '') ?>">
                            <div class="invalid-feedback"><?= $errors['year'] ?? '' ?></div>
                        </div>
                        <div class="col-6">
                            <label class="form-label">عدد الصفحات</label>
                            <input type="number" name="pages" class="form-control <?= isset($errors['pages']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($submittedData['pages'] ?? '') ?>">
                            <div class="invalid-feedback"><?= $errors['pages'] ?? '' ?></div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 shadow-sm"><?= $isEditMode ? "تحديث الكتاب" : "إضافة للمكتبة" ?></button>
                    <?php if ($isEditMode): ?> 
                        <a href="index.php" class="btn btn-outline-secondary w-100 mt-2">إلغاء التعديل</a> 
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm p-2 mb-3">
                <form class="d-flex" method="GET">
                    <input class="form-control me-2" type="search" name="search" placeholder="ابحث بالعنوان أو المؤلف..." value="<?= htmlspecialchars($searchTerm) ?>">
                    <button class="btn btn-primary px-4" type="submit">بحث</button>
                    <?php if (!empty($searchTerm)): ?>
                        <a href="index.php" class="btn btn-outline-danger ms-2">إلغاء</a>
                    <?php endif; ?>
                </form>
            </div>

            <table class="table table-bordered bg-white shadow-sm align-middle text-center">
                <thead class="table-dark">
                    <tr>
                        <th>الغلاف</th>
                        <th><a class="sort-link" href="?search=<?= urlencode($searchTerm) ?>&sort=id&order=<?= $nextOrder ?>">#</a></th>
                        <th><a class="sort-link" href="?search=<?= urlencode($searchTerm) ?>&sort=title&order=<?= $nextOrder ?>">العنوان</a></th>
                        <th>التصنيف</th> <th><a class="sort-link" href="?search=<?= urlencode($searchTerm) ?>&sort=author&order=<?= $nextOrder ?>">المؤلف</a></th>
                        <th><a class="sort-link" href="?search=<?= urlencode($searchTerm) ?>&sort=year&order=<?= $nextOrder ?>">السنة</a></th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($displayBooks)): ?>
                        <tr><td colspan="7" class="py-4 text-muted">لا توجد كتب مطابقة لنتائج البحث.</td></tr>
                    <?php endif; ?>
                    
                    <?php foreach ($displayBooks as $book): ?>
                    <tr>
                        <td>
                            <?php if (!empty($book['image_url'])): ?>
                                <img src="<?= htmlspecialchars($book['image_url']) ?>" class="book-img" alt="غلاف">
                            <?php else: ?>
                                <div class="book-img d-flex align-items-center justify-content-center bg-light text-muted mx-auto" style="font-size: 10px;">N/A</div>
                            <?php endif; ?>
                        </td>
                        <td><?= $book['id'] ?></td>
                        <td class="fw-bold"><?= htmlspecialchars($book['title']) ?></td>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars($book['genre']) ?></span></td> <td><?= htmlspecialchars($book['author']) ?></td>
                        <td><?= $book['year'] ?></td>
                        <td>
                            <div class="btn-group">
                                <a href="?edit_id=<?= $book['id'] ?>" class="btn btn-sm btn-warning">تعديل</a>
                                <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#del<?= $book['id'] ?>">حذف</button>
                            </div>

                            <div class="modal fade" id="del<?= $book['id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content text-start">
                                        <div class="modal-body p-4">
                                            <h6>هل أنت متأكد من حذف هذا الكتاب؟</h6>
                                            <p class="text-muted mb-0 small">كتاب: <?= htmlspecialchars($book['title']) ?></p>
                                        </div>
                                        <div class="modal-footer border-0">
                                            <form method="POST">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="book_id" value="<?= $book['id'] ?>">
                                                <button type="submit" class="btn btn-danger px-4">نعم، احذف</button>
                                            </form>
                                            <button class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
