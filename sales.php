<?php
require_once 'config.php';

// Гости могут просматривать продажи
if (!isLoggedIn() && !isGuest()) {
    redirect('login.php');
}

$conn = getConnection();
$message = '';

// Определяем действие
$action = isset($_GET['action']) ? $_GET['action'] : '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Проверяем права на редактирование
if (!canEdit() && in_array($action, ['add', 'delete'])) {
    $message = '<div class="alert alert-warning">Недостаточно прав для выполнения этого действия. <a href="login.php" class="alert-link">Войдите в систему</a>.</div>';
    $action = '';
}

// Обработка действий
switch ($action) {
    case 'add':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && canEdit()) {
            $subdivision_id = intval($_POST['subdivision_id']);
            $product_id = intval($_POST['product_id']);
            $sale_date = sanitize($_POST['sale_date']);
            $quantity = intval($_POST['quantity']);
            $total_amount = floatval($_POST['total_amount']);
            
            $sql = "INSERT INTO sales (subdivision_id, product_id, sale_date, quantity, total_amount) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iisid", $subdivision_id, $product_id, $sale_date, $quantity, $total_amount);
            
            if ($stmt->execute()) {
                $message = '<div class="alert alert-success">Продажа успешно добавлена</div>';
            } else {
                $message = '<div class="alert alert-danger">Ошибка: ' . $conn->error . '</div>';
            }
        }
        break;
        
    case 'delete':
        if ($id > 0 && isset($_GET['confirm']) && $_GET['confirm'] == 'yes' && canEdit()) {
            $sql = "DELETE FROM sales WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $message = '<div class="alert alert-success">Продажа успешно удалена</div>';
            } else {
                $message = '<div class="alert alert-danger">Ошибка при удалении</div>';
            }
            redirect('sales.php');
        }
        break;
}

// Получение списка продаж
$sales = [];
$sql = "SELECT s.*, 
               sub.name as subdivision_name,
               p.name as product_name,
               p.internal_code
        FROM sales s
        JOIN subdivisions sub ON s.subdivision_id = sub.id
        JOIN products p ON s.product_id = p.id
        ORDER BY s.sale_date DESC, s.id DESC
        LIMIT 50";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $sales[] = $row;
}

// Получение списка подразделений
$subdivisions = [];
$result = $conn->query("SELECT id, name FROM subdivisions WHERE is_active = 1 ORDER BY name");
while ($row = $result->fetch_assoc()) {
    $subdivisions[] = $row;
}

// Получение списка товаров
$products = [];
$result = $conn->query("SELECT id, name, internal_code FROM products WHERE is_active = 1 ORDER BY name");
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

displayHeader('Продажи' . ($action == 'delete' ? ' - Удаление' : ''));
?>

<div class="container mt-4">
    <h2><i class="bi bi-cart-check"></i> Учет продаж</h2>
    
    <?php if (isGuest()): ?>
    <div class="alert alert-info">
        <i class="bi bi-eye"></i> Вы находитесь в гостевом режиме. Для редактирования продаж необходимо <a href="login.php" class="alert-link">войти в систему</a>.
    </div>
    <?php endif; ?>
    
    <?php echo $message; ?>
    
    <?php if ($action == 'delete' && $id > 0): ?>
    <!-- Подтверждение удаления -->
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <i class="bi bi-exclamation-triangle"></i> Подтверждение удаления
                </div>
                <div class="card-body text-center">
                    <h5 class="card-title">Вы уверены, что хотите удалить запись о продаже?</h5>
                    
                    <?php if (canEdit()): ?>
                    <div class="mt-4">
                        <a href="?action=delete&id=<?php echo $id; ?>&confirm=yes" 
                           class="btn btn-danger btn-lg me-3">
                            <i class="bi bi-trash"></i> Да, удалить
                        </a>
                        <a href="sales.php" class="btn btn-secondary btn-lg">
                            <i class="bi bi-x-circle"></i> Отмена
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-warning mt-3">
                        <i class="bi bi-shield-lock"></i> Недостаточно прав для удаления записей.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php else: ?>
    <!-- Основной интерфейс -->
    <div class="row">
        <?php if (canEdit()): ?>
        <!-- Форма добавления продажи (только для авторизованных) -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-plus-circle"></i> Новая продажа
                </div>
                <div class="card-body">
                    <form method="POST" action="?action=add">
                        <div class="mb-3">
                            <label class="form-label">Подразделение:</label>
                            <select name="subdivision_id" class="form-select" required>
                                <option value="">Выберите подразделение</option>
                                <?php foreach ($subdivisions as $sub): ?>
                                <option value="<?php echo $sub['id']; ?>"><?php echo $sub['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Товар:</label>
                            <select name="product_id" class="form-select" required>
                                <option value="">Выберите товар</option>
                                <?php foreach ($products as $product): ?>
                                <option value="<?php echo $product['id']; ?>">
                                    <?php echo $product['name']; ?> (<?php echo $product['internal_code']; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Дата продажи:</label>
                            <input type="date" name="sale_date" class="form-control" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Количество:</label>
                                    <input type="number" name="quantity" class="form-control" 
                                           min="1" step="1" value="1" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Сумма (руб.):</label>
                                    <input type="number" name="total_amount" class="form-control" 
                                           min="0.01" step="0.01" required>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Сохранить продажу
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
            
            <!-- Быстрая статистика -->
            <div class="card mt-3">
                <div class="card-header bg-info text-white">
                    <i class="bi bi-calculator"></i> Статистика за месяц
                </div>
                <div class="card-body">
                    <?php
                    $sql = "SELECT 
                            COUNT(*) as total_sales,
                            SUM(quantity) as total_quantity,
                            SUM(total_amount) as total_amount
                            FROM sales 
                            WHERE MONTH(sale_date) = MONTH(CURDATE()) 
                            AND YEAR(sale_date) = YEAR(CURDATE())";
                    $result = $conn->query($sql);
                    $stats = $result->fetch_assoc();
                    ?>
                    <p><strong>Продаж:</strong> <?php echo $stats['total_sales']; ?></p>
                    <p><strong>Товаров:</strong> <?php echo $stats['total_quantity'] ?: 0; ?> шт.</p>
                    <p><strong>Выручка:</strong> <?php echo number_format($stats['total_amount'], 2); ?> руб.</p>
                </div>
            </div>
        </div>
        
        <div class="<?php echo canEdit() ? 'col-md-8' : 'col-md-12'; ?>">
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <i class="bi bi-list"></i> Последние продажи (50 записей)
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm">
                            <thead>
                                <tr>
                                    <th>Дата</th>
                                    <th>Подразделение</th>
                                    <th>Товар</th>
                                    <th>Кол-во</th>
                                    <th>Сумма</th>
                                    <th>Цена/шт</th>
                                    <?php if (canEdit()): ?>
                                    <th>Действия</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sales as $sale): 
                                    $price_per_unit = $sale['total_amount'] / $sale['quantity'];
                                ?>
                                <tr>
                                    <td><?php echo date('d.m.Y', strtotime($sale['sale_date'])); ?></td>
                                    <td><?php echo $sale['subdivision_name']; ?></td>
                                    <td>
                                        <small><?php echo $sale['product_name']; ?></small>
                                    </td>
                                    <td><?php echo $sale['quantity']; ?></td>
                                    <td><?php echo number_format($sale['total_amount'], 2); ?> ₽</td>
                                    <td><?php echo number_format($price_per_unit, 2); ?> ₽</td>
                                    <?php if (canEdit()): ?>
                                    <td>
                                        <a href="?action=delete&id=<?php echo $sale['id']; ?>" 
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Удалить запись о продаже?')">
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
    
    <a href="index.php" class="btn btn-outline-secondary mt-3">
        <i class="bi bi-arrow-left"></i> Назад в панель
    </a>
</div>

<?php 
$conn->close();
displayFooter();
?>