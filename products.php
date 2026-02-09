<?php
require_once 'config.php';

// Гости могут просматривать товары
if (!isLoggedIn() && !isGuest()) {
    redirect('login.php');
}

$conn = getConnection();
$message = '';

// Определяем действие
$action = isset($_GET['action']) ? $_GET['action'] : '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Проверяем права на редактирование
if (!canEdit() && in_array($action, ['add', 'edit', 'delete'])) {
    $message = '<div class="alert alert-warning">Недостаточно прав для выполнения этого действия. <a href="login.php" class="alert-link">Войдите в систему</a>.</div>';
    $action = '';
}

// Обработка действий
switch ($action) {
    case 'add':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && canEdit()) {
            $name = sanitize($_POST['name']);
            $internal_code = sanitize($_POST['internal_code']);
            $category = sanitize($_POST['category']);
            $description = sanitize($_POST['description']);
            
            $sql = "INSERT INTO products (name, internal_code, category, description) 
                    VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssss", $name, $internal_code, $category, $description);
            
            if ($stmt->execute()) {
                $message = '<div class="alert alert-success">Товар успешно добавлен</div>';
            } else {
                $message = '<div class="alert alert-danger">Ошибка: ' . $conn->error . '</div>';
            }
        }
        break;
        
    case 'edit':
        if ($id <= 0) redirect('products.php');
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && canEdit()) {
            $name = sanitize($_POST['name']);
            $internal_code = sanitize($_POST['internal_code']);
            $category = sanitize($_POST['category']);
            $description = sanitize($_POST['description']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            $sql = "UPDATE products SET 
                    name = ?, 
                    internal_code = ?, 
                    category = ?, 
                    description = ?, 
                    is_active = ?
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssii", $name, $internal_code, $category, $description, $is_active, $id);
            
            if ($stmt->execute()) {
                $message = '<div class="alert alert-success">Товар успешно обновлен</div>';
            } else {
                $message = '<div class="alert alert-danger">Ошибка: ' . $conn->error . '</div>';
            }
        }
        break;
        
    case 'delete':
        if ($id > 0 && isset($_GET['confirm']) && $_GET['confirm'] == 'yes' && canEdit()) {
            // Проверяем, есть ли связанные продажи
            $check_sql = "SELECT COUNT(*) as count FROM sales WHERE product_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("i", $id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $row = $check_result->fetch_assoc();
            
            if ($row['count'] > 0) {
                // Деактивируем товар
                $sql = "UPDATE products SET is_active = 0 WHERE id = ?";
                $action_text = 'деактивирован';
            } else {
                // Удаляем товар
                $sql = "DELETE FROM products WHERE id = ?";
                $action_text = 'удален';
            }
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $message = '<div class="alert alert-success">Товар успешно ' . $action_text . '</div>';
            } else {
                $message = '<div class="alert alert-danger">Ошибка при удалении товара</div>';
            }
            redirect('products.php');
        }
        break;
}

// Получение данных товара для редактирования
$product = null;
if ($action == 'edit' && $id > 0) {
    $sql = "SELECT * FROM products WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $product = $result->fetch_assoc();
    } else {
        $message = '<div class="alert alert-danger">Товар не найден</div>';
    }
}

// Получение списка товаров
$products = [];
$result = $conn->query("SELECT * FROM products ORDER BY id DESC");
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

displayHeader('Товары' . ($action ? ' - ' . ucfirst($action) : ''));
?>

<div class="container mt-4">
    <h2><i class="bi bi-box"></i> Управление товарами</h2>
    
    <?php if (isGuest()): ?>
    <div class="alert alert-info">
        <i class="bi bi-eye"></i> Вы находитесь в гостевом режиме. Для редактирования товаров необходимо <a href="login.php" class="alert-link">войти в систему</a>.
    </div>
    <?php endif; ?>
    
    <?php echo $message; ?>
    
    <?php if ($action == 'edit' && $product): ?>
    <!-- Форма редактирования -->
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <i class="bi bi-pencil-square"></i> Редактирование товара
                </div>
                <div class="card-body">
                    <?php if (canEdit()): ?>
                    <form method="POST" action="?action=edit&id=<?php echo $id; ?>">
                        <div class="mb-3">
                            <label class="form-label">Название товара:</label>
                            <input type="text" name="name" class="form-control" 
                                   value="<?php echo htmlspecialchars($product['name']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Артикул:</label>
                            <input type="text" name="internal_code" class="form-control" 
                                   value="<?php echo htmlspecialchars($product['internal_code']); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Категория:</label>
                            <input type="text" name="category" class="form-control" 
                                   value="<?php echo htmlspecialchars($product['category']); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Описание:</label>
                            <textarea name="description" class="form-control" rows="4"><?php echo htmlspecialchars($product['description']); ?></textarea>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" name="is_active" class="form-check-input" id="is_active"
                                <?php echo $product['is_active'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_active">Активный товар</label>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-between">
                            <button type="submit" class="btn btn-warning">
                                <i class="bi bi-check-circle"></i> Сохранить изменения
                            </button>
                            <a href="products.php" class="btn btn-secondary">
                                <i class="bi bi-x-circle"></i> Отмена
                            </a>
                        </div>
                    </form>
                    <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-shield-lock"></i> Недостаточно прав для редактирования товаров.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <i class="bi bi-info-circle"></i> Информация о товаре
                </div>
                <div class="card-body">
                    <p><strong>ID товара:</strong> <?php echo $product['id']; ?></p>
                    <p><strong>Статус:</strong> 
                        <span class="badge bg-<?php echo $product['is_active'] ? 'success' : 'danger'; ?>">
                            <?php echo $product['is_active'] ? 'Активен' : 'Неактивен'; ?>
                        </span>
                    </p>
                    
                    <h6>Последние продажи:</h6>
                    <?php
                    $sql = "SELECT s.sale_date, s.quantity, s.total_amount, sub.name as subdivision_name
                            FROM sales s
                            JOIN subdivisions sub ON s.subdivision_id = sub.id
                            WHERE s.product_id = ?
                            ORDER BY s.sale_date DESC
                            LIMIT 5";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $sales_result = $stmt->get_result();
                    
                    if ($sales_result->num_rows > 0): ?>
                        <ul class="list-group list-group-flush">
                        <?php while ($sale = $sales_result->fetch_assoc()): ?>
                            <li class="list-group-item">
                                <small>
                                    <?php echo date('d.m.Y', strtotime($sale['sale_date'])); ?> | 
                                    <?php echo $sale['subdivision_name']; ?> | 
                                    <?php echo $sale['quantity']; ?> шт. | 
                                    <?php echo number_format($sale['total_amount'], 2); ?> ₽
                                </small>
                            </li>
                        <?php endwhile; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted">Нет данных о продажах</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php elseif ($action == 'delete' && $id > 0): ?>
    <!-- Подтверждение удаления -->
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <i class="bi bi-exclamation-triangle"></i> Подтверждение удаления
                </div>
                <div class="card-body text-center">
                    <h5 class="card-title">Вы уверены, что хотите удалить товар?</h5>
                    
                    <div class="alert alert-warning">
                        <i class="bi bi-info-circle"></i> 
                        <strong>Внимание!</strong><br>
                        Если у товара есть история продаж, он будет деактивирован вместо удаления.
                    </div>
                    
                    <?php if (canEdit()): ?>
                    <div class="mt-4">
                        <a href="?action=delete&id=<?php echo $id; ?>&confirm=yes" 
                           class="btn btn-danger btn-lg me-3">
                            <i class="bi bi-trash"></i> Да, удалить
                        </a>
                        <a href="products.php" class="btn btn-secondary btn-lg">
                            <i class="bi bi-x-circle"></i> Отмена
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-warning mt-3">
                        <i class="bi bi-shield-lock"></i> Недостаточно прав для удаления товаров.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php else: ?>
    <!-- Основной интерфейс: список товаров -->
    <div class="row">
        <?php if (canEdit()): ?>
        <!-- Форма добавления товара (только для авторизованных) -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-plus-circle"></i> Добавить товар
                </div>
                <div class="card-body">
                    <form method="POST" action="?action=add">
                        <div class="mb-3">
                            <label class="form-label">Название товара:</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Артикул:</label>
                            <input type="text" name="internal_code" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Категория:</label>
                            <input type="text" name="category" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Описание:</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Сохранить
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-8">
        <?php else: ?>
        <div class="col-md-12">
        <?php endif; ?>
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <i class="bi bi-list"></i> Список товаров (<?php echo count($products); ?>)
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Название</th>
                                    <th>Артикул</th>
                                    <th>Категория</th>
                                    <th>Статус</th>
                                    <?php if (canEdit()): ?>
                                    <th>Действия</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                <tr>
                                    <td><?php echo $product['id']; ?></td>
                                    <td><strong><?php echo $product['name']; ?></strong></td>
                                    <td><?php echo $product['internal_code']; ?></td>
                                    <td><?php echo $product['category']; ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $product['is_active'] ? 'success' : 'danger'; ?>">
                                            <?php echo $product['is_active'] ? 'Активен' : 'Неактивен'; ?>
                                        </span>
                                    </td>
                                    <?php if (canEdit()): ?>
                                    <td>
                                        <a href="?action=edit&id=<?php echo $product['id']; ?>" 
                                           class="btn btn-sm btn-warning">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="?action=delete&id=<?php echo $product['id']; ?>" 
                                           class="btn btn-sm btn-danger">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (!$action || $action == 'add'): ?>
    <a href="index.php" class="btn btn-outline-secondary mt-3">
        <i class="bi bi-arrow-left"></i> Назад в панель
    </a>
    <?php endif; ?>
</div>

<?php 
$conn->close();
displayFooter();
?>