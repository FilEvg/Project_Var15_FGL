<?php
require_once 'config.php';

// Определяем запрашиваемую страницу
$page = isset($_GET['page']) ? $_GET['page'] : 'home';

// Маршрутизация
switch ($page) {
    case 'login':
        page_login();
        break;
    case 'logout':
        page_logout();
        break;
    case 'products':
        page_products();
        break;
    case 'sales':
        page_sales();
        break;
    case 'competitors':
        page_competitors();
        break;
    case 'reports':
        page_reports();
        break;
    case 'admin_users':
        page_admin_users();
        break;
    default:
        page_home();
}

// -------------------------------------------------------------------
// Функции страниц
// -------------------------------------------------------------------

function page_home() {
    // Проверка доступа
    if (!isLoggedIn() && !isGuest()) {
        redirect('index.php?page=login');
    }
    $conn = getConnection();

    displayHeader('Главная');
    ?>
    <div class="container">
        <?php if (isGuest()): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="bi bi-info-circle"></i> Вы находитесь в <strong>гостевом режиме</strong>. Для редактирования данных необходимо <a href="index.php?page=login" class="alert-link">войти в систему</a>.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php elseif (isLoggedIn()): ?>
        <div class="alert alert-<?php echo isAdmin() ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert">
            <i class="bi bi-person-badge"></i> Вы вошли как 
            <strong><?php echo isAdmin() ? 'Администратор' : 'Пользователь'; ?></strong>. 
            <?php echo isAdmin() ? 'У вас есть полный доступ ко всем функциям системы.' : 'Вы можете редактировать данные, но не управлять пользователями.'; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <h2 class="mb-4">Панель управления</h2>
        
        <div class="row">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="bi bi-box dashboard-icon"></i>
                        <h5 class="card-title mt-2">Товары</h5>
                        <p class="card-text"><?php echo isGuest() ? 'Просмотр товаров' : (isAdmin() ? 'Полное управление товарами' : 'Работа с товарами компании'); ?></p>
                        <a href="index.php?page=products" class="btn btn-primary">Перейти</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="bi bi-cart-check dashboard-icon"></i>
                        <h5 class="card-title mt-2">Продажи</h5>
                        <p class="card-text"><?php echo isGuest() ? 'Просмотр продаж' : (isAdmin() ? 'Полный учет продаж' : 'Учет продаж по подразделениям'); ?></p>
                        <a href="index.php?page=sales" class="btn btn-primary">Перейти</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="bi bi-bar-chart dashboard-icon"></i>
                        <h5 class="card-title mt-2">Конкуренты</h5>
                        <p class="card-text"><?php echo isGuest() ? 'Просмотр конкурентов' : (isAdmin() ? 'Полный мониторинг цен' : 'Мониторинг цен конкурентов'); ?></p>
                        <a href="index.php?page=competitors" class="btn btn-primary">Перейти</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="bi bi-file-earmark-text dashboard-icon"></i>
                        <h5 class="card-title mt-2">Отчеты</h5>
                        <p class="card-text"><?php echo isGuest() ? 'Просмотр отчетов' : (isAdmin() ? 'Полный анализ данных' : 'Анализ динамики продаж'); ?></p>
                        <a href="index.php?page=reports" class="btn btn-primary">Перейти</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Быстрая статистика -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-speedometer2"></i> Быстрая статистика
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <?php
                            $stats = [
                                ['query' => "SELECT COUNT(*) as count FROM products WHERE is_active = 1", 'label' => 'Активных товаров'],
                                ['query' => "SELECT COUNT(*) as count FROM subdivisions WHERE is_active = 1", 'label' => 'Подразделений'],
                                ['query' => "SELECT COUNT(*) as count FROM competitors", 'label' => 'Конкурентов'],
                                ['query' => "SELECT SUM(quantity) as total FROM sales WHERE MONTH(sale_date) = MONTH(CURDATE())", 'label' => 'Продаж за месяц']
                            ];
                            
                            foreach ($stats as $stat) {
                                $result = $conn->query($stat['query']);
                                $row = $result->fetch_assoc();
                                echo '<div class="col-md-3">
                                        <h3>' . ($row['total'] ?? $row['count']) . '</h3>
                                        <p class="text-muted">' . $stat['label'] . '</p>
                                      </div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    $conn->close();
    displayFooter();
}

function page_products() {
    // Проверка доступа
    if (!isLoggedIn() && !isGuest()) {
        redirect('index.php?page=login');
    }
    $conn = getConnection();
    $message = '';

    $action = isset($_GET['action']) ? $_GET['action'] : '';
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    $canEditProducts = hasPermission('edit_data');

    if (!$canEditProducts && in_array($action, ['add', 'edit', 'delete'])) {
        $message = '<div class="alert alert-warning">Недостаточно прав для выполнения этого действия.</div>';
        $action = '';
    }

    // Обработка действий
    switch ($action) {
        case 'add':
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEditProducts) {
                $name = sanitize($_POST['name']);
                $internal_code = sanitize($_POST['internal_code']);
                $category = sanitize($_POST['category']);
                $description = sanitize($_POST['description']);
                
                $sql = "INSERT INTO products (name, internal_code, category, description) VALUES (?, ?, ?, ?)";
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
            if ($id <= 0) redirect('index.php?page=products');
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEditProducts) {
                $name = sanitize($_POST['name']);
                $internal_code = sanitize($_POST['internal_code']);
                $category = sanitize($_POST['category']);
                $description = sanitize($_POST['description']);
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                $sql = "UPDATE products SET name=?, internal_code=?, category=?, description=?, is_active=? WHERE id=?";
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
            if ($id > 0 && isset($_GET['confirm']) && $_GET['confirm'] == 'yes' && $canEditProducts) {
                // Проверяем наличие продаж
                $check_sql = "SELECT COUNT(*) as count FROM sales WHERE product_id = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("i", $id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                $row = $check_result->fetch_assoc();
                
                if ($row['count'] > 0) {
                    $sql = "UPDATE products SET is_active = 0 WHERE id = ?";
                    $action_text = 'деактивирован';
                } else {
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
                redirect('index.php?page=products');
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
            <i class="bi bi-eye"></i> Вы находитесь в гостевом режиме. Для редактирования товаров необходимо <a href="index.php?page=login" class="alert-link">войти в систему</a>.
        </div>
        <?php elseif (!hasPermission('edit_data')): ?>
        <div class="alert alert-warning">
            <i class="bi bi-shield-exclamation"></i> У вас есть права только на просмотр товаров. Для редактирования нужны соответствующие права.
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
                        <?php if ($canEditProducts): ?>
                        <form method="POST" action="index.php?page=products&action=edit&id=<?php echo $id; ?>">
                            <div class="mb-3">
                                <label class="form-label">Название товара:</label>
                                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Артикул:</label>
                                <input type="text" name="internal_code" class="form-control" value="<?php echo htmlspecialchars($product['internal_code']); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Категория:</label>
                                <input type="text" name="category" class="form-control" value="<?php echo htmlspecialchars($product['category']); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Описание:</label>
                                <textarea name="description" class="form-control" rows="4"><?php echo htmlspecialchars($product['description']); ?></textarea>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" name="is_active" class="form-check-input" id="is_active" <?php echo $product['is_active'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_active">Активный товар</label>
                            </div>
                            <div class="d-grid gap-2 d-md-flex justify-content-md-between">
                                <button type="submit" class="btn btn-warning"><i class="bi bi-check-circle"></i> Сохранить изменения</button>
                                <a href="index.php?page=products" class="btn btn-secondary"><i class="bi bi-x-circle"></i> Отмена</a>
                            </div>
                        </form>
                        <?php else: ?>
                        <div class="alert alert-warning"><i class="bi bi-shield-lock"></i> Недостаточно прав для редактирования товаров.</div>
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
                        <p><strong>Статус:</strong> <span class="badge bg-<?php echo $product['is_active'] ? 'success' : 'danger'; ?>"><?php echo $product['is_active'] ? 'Активен' : 'Неактивен'; ?></span></p>
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
                                <li class="list-group-item"><small><?php echo date('d.m.Y', strtotime($sale['sale_date'])); ?> | <?php echo $sale['subdivision_name']; ?> | <?php echo $sale['quantity']; ?> шт. | <?php echo number_format($sale['total_amount'], 2); ?> ₽</small></li>
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
                        <?php if ($canEditProducts): ?>
                        <div class="mt-4">
                            <a href="index.php?page=products&action=delete&id=<?php echo $id; ?>&confirm=yes" class="btn btn-danger btn-lg me-3"><i class="bi bi-trash"></i> Да, удалить</a>
                            <a href="index.php?page=products" class="btn btn-secondary btn-lg"><i class="bi bi-x-circle"></i> Отмена</a>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-warning mt-3"><i class="bi bi-shield-lock"></i> Недостаточно прав для удаления товаров.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Основной интерфейс: список товаров -->
        <div class="row">
            <?php if ($canEditProducts): ?>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <i class="bi bi-plus-circle"></i> Добавить товар
                    </div>
                    <div class="card-body">
                        <form method="POST" action="index.php?page=products&action=add">
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
                            <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Сохранить</button>
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
                                        <?php if ($canEditProducts): ?>
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
                                        <td><span class="badge bg-<?php echo $product['is_active'] ? 'success' : 'danger'; ?>"><?php echo $product['is_active'] ? 'Активен' : 'Неактивен'; ?></span></td>
                                        <?php if ($canEditProducts): ?>
                                        <td>
                                            <a href="index.php?page=products&action=edit&id=<?php echo $product['id']; ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a>
                                            <a href="index.php?page=products&action=delete&id=<?php echo $product['id']; ?>" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></a>
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
        <a href="index.php?page=home" class="btn btn-outline-secondary mt-3"><i class="bi bi-arrow-left"></i> Назад в панель</a>
        <?php endif; ?>
    </div>
    <?php
    $conn->close();
    displayFooter();
}

function page_sales() {
    if (!isLoggedIn() && !isGuest()) {
        redirect('index.php?page=login');
    }
    $conn = getConnection();
    $message = '';

    $action = isset($_GET['action']) ? $_GET['action'] : '';
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    $canEditSales = hasPermission('edit_data');

    if (!$canEditSales && in_array($action, ['add', 'delete'])) {
        $message = '<div class="alert alert-warning">Недостаточно прав для выполнения этого действия.</div>';
        $action = '';
    }

    switch ($action) {
        case 'add':
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEditSales) {
                $subdivision_id = intval($_POST['subdivision_id']);
                $product_id = intval($_POST['product_id']);
                $sale_date = sanitize($_POST['sale_date']);
                $quantity = intval($_POST['quantity']);
                $total_amount = floatval($_POST['total_amount']);
                
                $sql = "INSERT INTO sales (subdivision_id, product_id, sale_date, quantity, total_amount) VALUES (?, ?, ?, ?, ?)";
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
            if ($id > 0 && isset($_GET['confirm']) && $_GET['confirm'] == 'yes' && $canEditSales) {
                $sql = "DELETE FROM sales WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $id);
                
                if ($stmt->execute()) {
                    $message = '<div class="alert alert-success">Продажа успешно удалена</div>';
                } else {
                    $message = '<div class="alert alert-danger">Ошибка при удалении</div>';
                }
                redirect('index.php?page=sales');
            }
            break;
    }

    // Получение списка продаж
    $sales = [];
    $sql = "SELECT s.*, sub.name as subdivision_name, p.name as product_name, p.internal_code
            FROM sales s
            JOIN subdivisions sub ON s.subdivision_id = sub.id
            JOIN products p ON s.product_id = p.id
            ORDER BY s.sale_date DESC, s.id DESC
            LIMIT 50";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $sales[] = $row;
    }

    // Подразделения
    $subdivisions = [];
    $result = $conn->query("SELECT id, name FROM subdivisions WHERE is_active = 1 ORDER BY name");
    while ($row = $result->fetch_assoc()) {
        $subdivisions[] = $row;
    }

    // Товары
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
            <i class="bi bi-eye"></i> Вы находитесь в гостевом режиме. Для редактирования продаж необходимо <a href="index.php?page=login" class="alert-link">войти в систему</a>.
        </div>
        <?php elseif (!hasPermission('edit_data')): ?>
        <div class="alert alert-warning">
            <i class="bi bi-shield-exclamation"></i> У вас есть права только на просмотр продаж. Для редактирования нужны соответствующие права.
        </div>
        <?php endif; ?>
        
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
                        <?php if ($canEditSales): ?>
                        <div class="mt-4">
                            <a href="index.php?page=sales&action=delete&id=<?php echo $id; ?>&confirm=yes" class="btn btn-danger btn-lg me-3"><i class="bi bi-trash"></i> Да, удалить</a>
                            <a href="index.php?page=sales" class="btn btn-secondary btn-lg"><i class="bi bi-x-circle"></i> Отмена</a>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-warning mt-3"><i class="bi bi-shield-lock"></i> Недостаточно прав для удаления записей.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Основной интерфейс -->
        <div class="row">
            <?php if ($canEditSales): ?>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <i class="bi bi-plus-circle"></i> Новая продажа
                    </div>
                    <div class="card-body">
                        <form method="POST" action="index.php?page=sales&action=add">
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
                                    <option value="<?php echo $product['id']; ?>"><?php echo $product['name']; ?> (<?php echo $product['internal_code']; ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Дата продажи:</label>
                                <input type="date" name="sale_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Количество:</label>
                                        <input type="number" name="quantity" class="form-control" min="1" step="1" value="1" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Сумма (руб.):</label>
                                        <input type="number" name="total_amount" class="form-control" min="0.01" step="0.01" required>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Сохранить продажу</button>
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
                        $sql = "SELECT COUNT(*) as total_sales, SUM(quantity) as total_quantity, SUM(total_amount) as total_amount
                                FROM sales WHERE MONTH(sale_date) = MONTH(CURDATE()) AND YEAR(sale_date) = YEAR(CURDATE())";
                        $result = $conn->query($sql);
                        $stats = $result->fetch_assoc();
                        ?>
                        <p><strong>Продаж:</strong> <?php echo $stats['total_sales']; ?></p>
                        <p><strong>Товаров:</strong> <?php echo $stats['total_quantity'] ?: 0; ?> шт.</p>
                        <p><strong>Выручка:</strong> <?php echo number_format($stats['total_amount'], 2); ?> руб.</p>
                    </div>
                </div>
            </div>
            
            <div class="<?php echo $canEditSales ? 'col-md-8' : 'col-md-12'; ?>">
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
                                        <?php if ($canEditSales): ?>
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
                                        <td><small><?php echo $sale['product_name']; ?></small></td>
                                        <td><?php echo $sale['quantity']; ?></td>
                                        <td><?php echo number_format($sale['total_amount'], 2); ?> ₽</td>
                                        <td><?php echo number_format($price_per_unit, 2); ?> ₽</td>
                                        <?php if ($canEditSales): ?>
                                        <td>
                                            <a href="index.php?page=sales&action=delete&id=<?php echo $sale['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Удалить запись о продаже?')"><i class="bi bi-trash"></i></a>
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
        
        <a href="index.php?page=home" class="btn btn-outline-secondary mt-3"><i class="bi bi-arrow-left"></i> Назад в панель</a>
    </div>
    <?php
    $conn->close();
    displayFooter();
}

function page_competitors() {
    if (!isLoggedIn() && !isGuest()) {
        redirect('index.php?page=login');
    }
    $conn = getConnection();
    $message = '';

    $canEditCompetitors = hasPermission('edit_data');

    // Обработка добавления конкурента
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_competitor']) && $canEditCompetitors) {
        $name = sanitize($_POST['name']);
        $website = sanitize($_POST['website']);
        
        $sql = "INSERT INTO competitors (name, website) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $name, $website);
        
        if ($stmt->execute()) {
            $message = '<div class="alert alert-success">Конкурент успешно добавлен</div>';
        } else {
            $message = '<div class="alert alert-danger">Ошибка: ' . $conn->error . '</div>';
        }
    }

    // Обработка добавления цены конкурента
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_price']) && $canEditCompetitors) {
        $competitor_id = intval($_POST['competitor_id']);
        $product_id = intval($_POST['product_id']);
        $check_date = sanitize($_POST['check_date']);
        $price = floatval($_POST['price']);
        $product_name_at_competitor = sanitize($_POST['product_name_at_competitor']);
        $notes = sanitize($_POST['notes']);
        
        $sql = "INSERT INTO competitor_prices (competitor_id, product_id, check_date, price, product_name_at_competitor, notes) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iisdss", $competitor_id, $product_id, $check_date, $price, $product_name_at_competitor, $notes);
        
        if ($stmt->execute()) {
            $message = '<div class="alert alert-success">Цена конкурента сохранена</div>';
        } else {
            $message = '<div class="alert alert-danger">Ошибка: ' . $conn->error . '</div>';
        }
    }

    // Обработка удаления
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($action == 'delete' && $id > 0 && isset($_GET['confirm']) && $_GET['confirm'] == 'yes' && $canEditCompetitors) {
        $type = isset($_GET['type']) ? $_GET['type'] : '';
        
        if ($type == 'competitor') {
            $sql = "DELETE FROM competitors WHERE id = ?";
        } else {
            $sql = "DELETE FROM competitor_prices WHERE id = ?";
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $message = '<div class="alert alert-success">Запись успешно удалена</div>';
        } else {
            $message = '<div class="alert alert-danger">Ошибка при удалении</div>';
        }
        redirect('index.php?page=competitors');
    }

    // Получение данных
    $competitors = [];
    $result = $conn->query("SELECT * FROM competitors ORDER BY name");
    while ($row = $result->fetch_assoc()) {
        $competitors[] = $row;
    }

    $products = [];
    $result = $conn->query("SELECT id, name, internal_code FROM products WHERE is_active = 1 ORDER BY name");
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }

    $prices = [];
    $sql = "SELECT cp.*, c.name as competitor_name, p.name as product_name, p.internal_code
            FROM competitor_prices cp
            JOIN competitors c ON cp.competitor_id = c.id
            JOIN products p ON cp.product_id = p.id
            ORDER BY cp.check_date DESC, cp.id DESC
            LIMIT 20";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $prices[] = $row;
    }

    displayHeader('Конкуренты');
    ?>
    <div class="container mt-4">
        <h2><i class="bi bi-bar-chart"></i> Мониторинг конкурентов</h2>
        
        <?php if (isGuest()): ?>
        <div class="alert alert-info">
            <i class="bi bi-eye"></i> Вы находитесь в гостевом режиме. Для редактирования данных необходимо <a href="index.php?page=login" class="alert-link">войти в систему</a>.
        </div>
        <?php elseif (!hasPermission('edit_data')): ?>
        <div class="alert alert-warning">
            <i class="bi bi-shield-exclamation"></i> У вас есть права только на просмотр данных конкурентов. Для редактирования нужны соответствующие права.
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
                        <h5 class="card-title">Вы уверены, что хотите удалить эту запись?</h5>
                        <?php if ($canEditCompetitors): ?>
                        <div class="mt-4">
                            <a href="index.php?page=competitors&action=delete&id=<?php echo $id; ?>&type=<?php echo $_GET['type']; ?>&confirm=yes" class="btn btn-danger btn-lg me-3"><i class="bi bi-trash"></i> Да, удалить</a>
                            <a href="index.php?page=competitors" class="btn btn-secondary btn-lg"><i class="bi bi-x-circle"></i> Отмена</a>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-warning mt-3"><i class="bi bi-shield-lock"></i> Недостаточно прав для удаления записей.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Основной интерфейс -->
        <div class="row mt-3">
            <?php if ($canEditCompetitors): ?>
            <!-- Левая колонка: формы добавления -->
            <div class="col-md-4">
                <div class="card mb-3">
                    <div class="card-header bg-primary text-white">
                        <i class="bi bi-building-add"></i> Новый конкурент
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Название конкурента:</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Веб-сайт:</label>
                                <input type="url" name="website" class="form-control" placeholder="https://example.com">
                            </div>
                            <button type="submit" name="add_competitor" class="btn btn-primary"><i class="bi bi-save"></i> Добавить</button>
                        </form>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <i class="bi bi-tag"></i> Новая цена конкурента
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Конкурент:</label>
                                <select name="competitor_id" class="form-select" required>
                                    <option value="">Выберите конкурента</option>
                                    <?php foreach ($competitors as $comp): ?>
                                    <option value="<?php echo $comp['id']; ?>"><?php echo $comp['name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Наш товар:</label>
                                <select name="product_id" class="form-select" required>
                                    <option value="">Выберите товар</option>
                                    <?php foreach ($products as $product): ?>
                                    <option value="<?php echo $product['id']; ?>"><?php echo $product['name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Дата проверки:</label>
                                <input type="date" name="check_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Цена у конкурента (руб.):</label>
                                <input type="number" name="price" class="form-control" min="0.01" step="0.01" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Название у конкурента:</label>
                                <input type="text" name="product_name_at_competitor" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Примечания:</label>
                                <textarea name="notes" class="form-control" rows="2" placeholder="Акция, наличие, условия..."></textarea>
                            </div>
                            <button type="submit" name="add_price" class="btn btn-success"><i class="bi bi-save"></i> Сохранить цену</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Правая колонка: таблицы -->
            <div class="<?php echo $canEditCompetitors ? 'col-md-8' : 'col-md-12'; ?>">
                <div class="row">
                    <!-- Список конкурентов -->
                    <div class="col-md-6">
                        <div class="card mb-3" style="height: 100%;">
                            <div class="card-header bg-secondary text-white">
                                <i class="bi bi-buildings"></i> Конкуренты (<?php echo count($competitors); ?>)
                            </div>
                            <div class="card-body">
                                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                    <table class="table table-hover table-sm">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Название</th>
                                                <?php if ($canEditCompetitors): ?>
                                                <th>Действия</th>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($competitors as $competitor): ?>
                                            <tr>
                                                <td><?php echo $competitor['id']; ?></td>
                                                <td>
                                                    <strong><?php echo $competitor['name']; ?></strong>
                                                    <?php if ($competitor['website']): ?>
                                                    <br><small><a href="<?php echo $competitor['website']; ?>" target="_blank" class="text-decoration-none"><i class="bi bi-link-45deg"></i> Сайт</a></small>
                                                    <?php endif; ?>
                                                </td>
                                                <?php if ($canEditCompetitors): ?>
                                                <td>
                                                    <a href="index.php?page=competitors&action=delete&id=<?php echo $competitor['id']; ?>&type=competitor" class="btn btn-sm btn-danger" onclick="return confirm('Удалить конкурента?')"><i class="bi bi-trash"></i></a>
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
                    
                    <!-- Последние цены -->
                    <div class="col-md-6">
                        <div class="card mb-3" style="height: 100%;">
                            <div class="card-header bg-info text-white">
                                <i class="bi bi-tags"></i> Последние цены (20 записей)
                            </div>
                            <div class="card-body">
                                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                    <table class="table table-hover table-sm">
                                        <thead>
                                            <tr>
                                                <th>Дата</th>
                                                <th>Конкурент</th>
                                                <th>Цена</th>
                                                <?php if ($canEditCompetitors): ?>
                                                <th>Действия</th>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($prices as $price): ?>
                                            <tr>
                                                <td><small><?php echo date('d.m', strtotime($price['check_date'])); ?></small></td>
                                                <td><small><?php echo $price['competitor_name']; ?></small><br><small class="text-muted"><?php echo $price['product_name']; ?></small></td>
                                                <td><strong><?php echo number_format($price['price'], 0); ?> ₽</strong></td>
                                                <?php if ($canEditCompetitors): ?>
                                                <td>
                                                    <a href="index.php?page=competitors&action=delete&id=<?php echo $price['id']; ?>&type=price" class="btn btn-sm btn-danger" onclick="return confirm('Удалить запись о цене?')"><i class="bi bi-trash"></i></a>
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
                
                <!-- Детали цен -->
                <div class="row mt-3">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header bg-warning text-dark">
                                <i class="bi bi-info-circle"></i> Детальная информация о ценах
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-sm">
                                        <thead>
                                            <tr>
                                                <th>Дата</th>
                                                <th>Конкурент</th>
                                                <th>Товар</th>
                                                <th>Цена</th>
                                                <th>Название у конкурента</th>
                                                <th>Примечания</th>
                                                <?php if ($canEditCompetitors): ?>
                                                <th>Действия</th>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($prices as $price): ?>
                                            <tr>
                                                <td><?php echo date('d.m.Y', strtotime($price['check_date'])); ?></td>
                                                <td><?php echo $price['competitor_name']; ?></td>
                                                <td><small><?php echo $price['product_name']; ?></small><br><small class="text-muted"><?php echo $price['internal_code']; ?></small></td>
                                                <td><strong><?php echo number_format($price['price'], 2); ?> ₽</strong></td>
                                                <td><small><?php echo $price['product_name_at_competitor']; ?></small></td>
                                                <td><small><?php echo $price['notes']; ?></small></td>
                                                <?php if ($canEditCompetitors): ?>
                                                <td>
                                                    <a href="index.php?page=competitors&action=delete&id=<?php echo $price['id']; ?>&type=price" class="btn btn-sm btn-danger" onclick="return confirm('Удалить запись о цене?')"><i class="bi bi-trash"></i></a>
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
            </div>
        </div>
        <?php endif; ?>
        
        <a href="index.php?page=home" class="btn btn-outline-secondary mt-3"><i class="bi bi-arrow-left"></i> Назад в панель</a>
    </div>
    <?php
    $conn->close();
    displayFooter();
}

function page_reports() {
    if (!isLoggedIn() && !isGuest()) {
        redirect('index.php?page=login');
    }
    $conn = getConnection();

    $canExport = hasPermission('export_reports');

    $report_type = isset($_GET['report_type']) ? sanitize($_GET['report_type']) : 'sales';
    $date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : date('Y-m-01');
    $date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : date('Y-m-d');
    $format = isset($_GET['format']) ? sanitize($_GET['format']) : 'html';

    if (empty($date_from)) $date_from = date('Y-m-01');
    if (empty($date_to)) $date_to = date('Y-m-d');

    $report_data = [];
    $report_title = '';

    if (isset($_GET['export'])) {
        $format = $_GET['export'];
    }

    switch ($report_type) {
        case 'sales':
            $report_title = 'Динамика продаж';
            $sql = "SELECT DATE(s.sale_date) as sale_date, p.name as product_name, sub.name as subdivision_name,
                           SUM(s.quantity) as total_quantity, SUM(s.total_amount) as total_amount,
                           AVG(s.total_amount / s.quantity) as avg_price
                    FROM sales s
                    JOIN products p ON s.product_id = p.id
                    JOIN subdivisions sub ON s.subdivision_id = sub.id
                    WHERE s.sale_date BETWEEN ? AND ?
                    GROUP BY DATE(s.sale_date), s.product_id, s.subdivision_id
                    ORDER BY s.sale_date DESC, total_amount DESC";
            break;
        case 'competitors':
            $report_title = 'Цены конкурентов';
            $sql = "SELECT cp.check_date, c.name as competitor_name, p.name as product_name, cp.price,
                           cp.product_name_at_competitor, cp.notes
                    FROM competitor_prices cp
                    JOIN competitors c ON cp.competitor_id = c.id
                    JOIN products p ON cp.product_id = p.id
                    WHERE cp.check_date BETWEEN ? AND ?
                    ORDER BY cp.check_date DESC, cp.price";
            break;
        case 'products':
            $report_title = 'Движение товара';
            $sql = "SELECT p.name as product_name, p.internal_code, p.category,
                           COUNT(s.id) as sales_count, SUM(s.quantity) as total_sold,
                           SUM(s.total_amount) as total_revenue,
                           MIN(s.sale_date) as first_sale, MAX(s.sale_date) as last_sale
                    FROM products p
                    LEFT JOIN sales s ON p.id = s.product_id
                    WHERE s.sale_date BETWEEN ? AND ? OR s.sale_date IS NULL
                    GROUP BY p.id
                    ORDER BY total_revenue DESC";
            break;
        default:
            $report_title = 'Неизвестный отчет';
    }

    // Получение данных для отчетов на главной
    $sales_report = [];
    $result = $conn->query("SELECT p.name as product_name, SUM(s.quantity) as total_quantity,
                                   SUM(s.total_amount) as total_amount,
                                   AVG(s.total_amount / s.quantity) as avg_price
                            FROM sales s
                            JOIN products p ON s.product_id = p.id
                            GROUP BY s.product_id
                            ORDER BY total_amount DESC
                            LIMIT 10");
    while ($row = $result->fetch_assoc()) {
        $sales_report[] = $row;
    }

    $competitors_report = [];
    $result = $conn->query("SELECT c.name as competitor_name, COUNT(cp.id) as price_checks,
                                   MIN(cp.price) as min_price, MAX(cp.price) as max_price
                            FROM competitor_prices cp
                            JOIN competitors c ON cp.competitor_id = c.id
                            GROUP BY cp.competitor_id
                            ORDER BY price_checks DESC");
    while ($row = $result->fetch_assoc()) {
        $competitors_report[] = $row;
    }

    if (isset($_GET['generate']) || isset($_GET['export'])) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $date_from, $date_to);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $report_data[] = $row;
        }
        
        if ($format == 'csv' && isset($_GET['export'])) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="report_' . $report_type . '_' . date('Y-m-d') . '.csv"');
            
            $output = fopen('php://output', 'w');
            
            if (!empty($report_data)) {
                fputcsv($output, array_keys($report_data[0]), ';');
                foreach ($report_data as $row) {
                    fputcsv($output, $row, ';');
                }
            }
            
            fclose($output);
            exit();
        }
    }

    displayHeader('Отчеты');
    ?>
    <div class="container mt-4">
        <h2><i class="bi bi-file-earmark-text"></i> Аналитические отчеты</h2>
        
        <?php if (!hasPermission('export_reports') && !isGuest()): ?>
        <div class="alert alert-warning">
            <i class="bi bi-shield-exclamation"></i> У вас есть права только на просмотр отчетов. Для экспорта данных нужны соответствующие права.
        </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['generate'])): ?>
        <!-- Полный отчет -->
        <div class="report-header">
            <h3><i class="bi bi-file-earmark-text"></i> Отчет: <?php echo $report_title; ?></h3>
            <p class="text-muted">
                Период: <?php echo date('d.m.Y', strtotime($date_from)); ?> - <?php echo date('d.m.Y', strtotime($date_to)); ?> | 
                Сформирован: <?php echo date('d.m.Y H:i'); ?> | 
                Пользователь: <?php echo isGuest() ? 'Гость' : $_SESSION['full_name']; ?>
            </p>
        </div>
        
        <div class="card mt-3">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-table"></i> Данные отчета (<?php echo count($report_data); ?> записей)
            </div>
            <div class="card-body">
                <?php if (empty($report_data)): ?>
                    <div class="alert alert-info">Нет данных за выбранный период</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead class="table-dark">
                                <tr>
                                    <?php foreach (array_keys($report_data[0]) as $column): ?>
                                    <th><?php echo ucfirst(str_replace('_', ' ', $column)); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data as $row): ?>
                                <tr>
                                    <?php foreach ($row as $value): ?>
                                    <td>
                                        <?php 
                                        if (is_numeric($value) && strpos($value, '.') !== false) {
                                            echo number_format($value, 2);
                                        } elseif (strtotime($value) !== false && strlen($value) == 10) {
                                            echo date('d.m.Y', strtotime($value));
                                        } else {
                                            echo htmlspecialchars($value);
                                        }
                                        ?>
                                    </td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Сводная статистика -->
                    <div class="mt-4">
                        <h5><i class="bi bi-graph-up"></i> Сводная статистика</h5>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="card text-center bg-light">
                                    <div class="card-body">
                                        <h6>Количество записей</h6>
                                        <h3><?php echo count($report_data); ?></h3>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($report_type == 'sales' && !empty($report_data)): 
                                $total_amount = array_sum(array_column($report_data, 'total_amount'));
                                $total_quantity = array_sum(array_column($report_data, 'total_quantity'));
                            ?>
                            <div class="col-md-3">
                                <div class="card text-center bg-light">
                                    <div class="card-body">
                                        <h6>Общая выручка</h6>
                                        <h3><?php echo number_format($total_amount, 2); ?> ₽</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card text-center bg-light">
                                    <div class="card-body">
                                        <h6>Проданно товаров</h6>
                                        <h3><?php echo $total_quantity; ?> шт.</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card text-center bg-light">
                                    <div class="card-body">
                                        <h6>Средний чек</h6>
                                        <h3><?php echo $total_quantity > 0 ? number_format($total_amount / $total_quantity, 2) : 0; ?> ₽</h3>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="mt-4">
            <div class="d-flex justify-content-between">
                <a href="index.php?page=reports" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Назад к отчетам</a>
                <div>
                    <button onclick="window.print()" class="btn btn-primary me-2"><i class="bi bi-printer"></i> Печать</button>
                    <?php if ($canExport): ?>
                    <a href="index.php?page=reports&<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" class="btn btn-success"><i class="bi bi-file-earmark-excel"></i> Экспорт в CSV</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Основная страница отчетов -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <i class="bi bi-cart-check"></i> Топ-10 товаров по продажам
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Товар</th>
                                        <th>Кол-во</th>
                                        <th>Сумма</th>
                                        <th>Ср. цена</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sales_report as $item): ?>
                                    <tr>
                                        <td><?php echo $item['product_name']; ?></td>
                                        <td><?php echo $item['total_quantity']; ?></td>
                                        <td><?php echo number_format($item['total_amount'], 2); ?> ₽</td>
                                        <td><?php echo number_format($item['avg_price'], 2); ?> ₽</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <i class="bi bi-bar-chart"></i> Активность конкурентов
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Конкурент</th>
                                        <th>Проверок цен</th>
                                        <th>Мин. цена</th>
                                        <th>Макс. цена</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($competitors_report as $item): ?>
                                    <tr>
                                        <td><?php echo $item['competitor_name']; ?></td>
                                        <td><?php echo $item['price_checks']; ?></td>
                                        <td><?php echo number_format($item['min_price'], 2); ?> ₽</td>
                                        <td><?php echo number_format($item['max_price'], 2); ?> ₽</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Генератор отчетов -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <i class="bi bi-graph-up"></i> Генератор отчетов
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <input type="hidden" name="page" value="reports">
                            <input type="hidden" name="generate" value="1">
                            <div class="col-md-3">
                                <label class="form-label">Тип отчета:</label>
                                <select name="report_type" class="form-select">
                                    <option value="sales">Динамика продаж</option>
                                    <option value="competitors">Цены конкурентов</option>
                                    <option value="products">Движение товара</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Дата с:</label>
                                <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Дата по:</label>
                                <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Формат вывода:</label>
                                <select name="format" class="form-select">
                                    <option value="html">HTML (просмотр)</option>
                                    <option value="csv">CSV (Excel)</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary form-control"><i class="bi bi-file-earmark-pdf"></i> Сформировать</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <a href="index.php?page=home" class="btn btn-outline-secondary mt-3"><i class="bi bi-arrow-left"></i> Назад в панель</a>
        <?php endif; ?>
    </div>
    <?php
    $conn->close();
    displayFooter();
}

function page_admin_users() {
    // Только администраторы
    if (!isLoggedIn() || !isAdmin()) {
        $_SESSION['error'] = 'Доступ запрещен. Требуются права администратора.';
        redirect('index.php?page=home');
    }
    $conn = getConnection();
    $message = '';

    $mode = isset($_GET['mode']) ? $_GET['mode'] : 'view';
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    $action = isset($_GET['action']) ? $_GET['action'] : '';

    // Получение списка всех ролей
    $roles = [];
    $result = $conn->query("SELECT id, name, description FROM roles ORDER BY id");
    while ($row = $result->fetch_assoc()) {
        $roles[] = $row;
    }

    // ОБРАБОТКА ДЕЙСТВИЙ
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['add_user'])) {
            $username = trim($_POST['username']);
            $full_name = trim($_POST['full_name']);
            $email = trim($_POST['email']);
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];
            $role_id = intval($_POST['role_id']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if ($password !== $confirm_password) {
                $message = '<div class="alert alert-danger">Пароли не совпадают</div>';
            } elseif (strlen($password) < 6) {
                $message = '<div class="alert alert-danger">Пароль должен содержать минимум 6 символов</div>';
            } elseif (empty($username) || empty($full_name)) {
                $message = '<div class="alert alert-danger">Заполните все обязательные поля</div>';
            } else {
                $check_sql = "SELECT id FROM users WHERE username = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("s", $username);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    $message = '<div class="alert alert-danger">Пользователь с таким логином уже существует</div>';
                } else {
                    $sql = "INSERT INTO users (username, full_name, email, is_active) VALUES (?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sssi", $username, $full_name, $email, $is_active);
                    
                    if ($stmt->execute()) {
                        $new_user_id = $stmt->insert_id;
                        
                        if (syncUserToLocalDB($new_user_id, $username, $password)) {
                            $role_sql = "INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)";
                            $role_stmt = $conn->prepare($role_sql);
                            $role_stmt->bind_param("ii", $new_user_id, $role_id);
                            $role_stmt->execute();
                            
                            $message = '<div class="alert alert-success">Пользователь успешно создан</div>';
                        } else {
                            $delete_sql = "DELETE FROM users WHERE id = ?";
                            $delete_stmt = $conn->prepare($delete_sql);
                            $delete_stmt->bind_param("i", $new_user_id);
                            $delete_stmt->execute();
                            
                            $message = '<div class="alert alert-danger">Ошибка при сохранении пароля</div>';
                        }
                    } else {
                        $message = '<div class="alert alert-danger">Ошибка при создании пользователя: ' . $conn->error . '</div>';
                    }
                }
            }
        }
        
        if (isset($_POST['edit_user'])) {
            $user_id = intval($_POST['user_id']);
            $username = trim($_POST['username']);
            $full_name = trim($_POST['full_name']);
            $email = trim($_POST['email']);
            $role_id = intval($_POST['role_id']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $change_password = isset($_POST['change_password']) ? 1 : 0;
            
            if ($user_id == $_SESSION['user_id']) {
                $message = '<div class="alert alert-danger">Вы не можете редактировать свою собственную учетную запись</div>';
            } else {
                $check_sql = "SELECT id FROM users WHERE username = ? AND id != ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("si", $username, $user_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    $message = '<div class="alert alert-danger">Пользователь с таким логином уже существует</div>';
                } else {
                    $sql = "UPDATE users SET username=?, full_name=?, email=?, is_active=? WHERE id=?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssssi", $username, $full_name, $email, $is_active, $user_id);
                    
                    if ($stmt->execute()) {
                        if ($change_password && !empty($_POST['password'])) {
                            $password = $_POST['password'];
                            $confirm_password = $_POST['confirm_password'];
                            
                            if ($password === $confirm_password && strlen($password) >= 6) {
                                syncUserToLocalDB($user_id, $username, $password);
                            }
                        }
                        
                        $role_sql = "DELETE FROM user_roles WHERE user_id = ?";
                        $role_stmt = $conn->prepare($role_sql);
                        $role_stmt->bind_param("i", $user_id);
                        $role_stmt->execute();
                        
                        $role_sql = "INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)";
                        $role_stmt = $conn->prepare($role_sql);
                        $role_stmt->bind_param("ii", $user_id, $role_id);
                        $role_stmt->execute();
                        
                        $message = '<div class="alert alert-success">Данные пользователя обновлены</div>';
                    } else {
                        $message = '<div class="alert alert-danger">Ошибка при обновлении пользователя</div>';
                    }
                }
            }
        }
    }

    // Удаление пользователя
    if ($action == 'delete' && $user_id > 0) {
        if ($user_id == $_SESSION['user_id']) {
            $message = '<div class="alert alert-danger">Вы не можете удалить свою собственную учетную запись</div>';
        } else {
            if (isset($_GET['confirm']) && $_GET['confirm'] == 'yes') {
                $sql = "DELETE FROM users WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $user_id);
                
                if ($stmt->execute()) {
                    deleteUserFromLocalDB($user_id);
                    $message = '<div class="alert alert-success">Пользователь успешно удален</div>';
                    $action = '';
                    $user_id = 0;
                } else {
                    $message = '<div class="alert alert-danger">Ошибка при удалении пользователя</div>';
                }
            }
        }
    }

    // Получение данных пользователя для редактирования
    $selected_user = null;
    if ($mode == 'edit' && $user_id > 0) {
        $sql = "SELECT id, username, full_name, email, is_active FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $selected_user = $result->fetch_assoc();
            
            $role_sql = "SELECT r.id FROM user_roles ur JOIN roles r ON ur.role_id = r.id WHERE ur.user_id = ?";
            $role_stmt = $conn->prepare($role_sql);
            $role_stmt->bind_param("i", $user_id);
            $role_stmt->execute();
            $role_result = $role_stmt->get_result();
            if ($role_result->num_rows === 1) {
                $role_row = $role_result->fetch_assoc();
                $selected_user['current_role_id'] = $role_row['id'];
            } else {
                $selected_user['current_role_id'] = 0;
            }
        }
    }

    // Получение данных пользователя для подтверждения удаления
    $user_to_delete = null;
    if ($action == 'delete' && $user_id > 0 && (!isset($_GET['confirm']) || $_GET['confirm'] != 'yes')) {
        $sql = "SELECT id, username, full_name FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $user_to_delete = $result->fetch_assoc();
        }
    }

    // Получение списка всех пользователей
    $users = [];
    $sql = "SELECT u.id, u.username, u.full_name, u.email, u.is_active, r.name as role_name
            FROM users u
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            ORDER BY u.id";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }

    displayHeader('Управление пользователями');
    ?>
    <div class="container mt-4">
        <h2><i class="bi bi-people"></i> Управление пользователями</h2>
        
        <?php echo $message; ?>
        
        <?php if ($action == 'delete' && $user_to_delete): ?>
        <!-- ПОДТВЕРЖДЕНИЕ УДАЛЕНИЯ -->
        <div class="row">
            <div class="col-md-6 offset-md-3">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <i class="bi bi-exclamation-triangle"></i> Подтверждение удаления
                    </div>
                    <div class="card-body text-center">
                        <h5 class="card-title">Вы уверены, что хотите удалить пользователя?</h5>
                        <div class="alert alert-warning mt-3">
                            <i class="bi bi-info-circle"></i> 
                            <strong>Внимание!</strong> Это действие нельзя отменить. Будут удалены:
                            <ul class="text-start mt-2">
                                <li>Данные пользователя из основной БД</li>
                                <li>Данные авторизации из локальной БД</li>
                                <li>Назначенные роли пользователя</li>
                            </ul>
                        </div>
                        <div class="user-info p-3 border rounded mb-4">
                            <p><strong>Логин:</strong> <?php echo htmlspecialchars($user_to_delete['username']); ?></p>
                            <p><strong>ФИО:</strong> <?php echo htmlspecialchars($user_to_delete['full_name']); ?></p>
                            <p><strong>ID:</strong> <?php echo $user_to_delete['id']; ?></p>
                        </div>
                        <div class="mt-4">
                            <a href="index.php?page=admin_users&action=delete&user_id=<?php echo $user_to_delete['id']; ?>&confirm=yes" class="btn btn-danger btn-lg me-3"><i class="bi bi-trash"></i> Да, удалить</a>
                            <a href="index.php?page=admin_users" class="btn btn-secondary btn-lg"><i class="bi bi-x-circle"></i> Отмена</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php elseif ($mode == 'add' || $mode == 'edit'): ?>
        <!-- ФОРМА ДОБАВЛЕНИЯ/РЕДАКТИРОВАНИЯ -->
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-header <?php echo $mode == 'add' ? 'bg-success' : 'bg-warning'; ?> text-white">
                        <?php echo $mode == 'add' ? 'Добавление нового пользователя' : 'Редактирование пользователя'; ?>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?php if ($mode == 'edit' && isset($selected_user)): ?>
                            <input type="hidden" name="user_id" value="<?php echo $selected_user['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label class="form-label">Логин *</label>
                                <input type="text" name="username" class="form-control" value="<?php echo isset($selected_user['username']) ? htmlspecialchars($selected_user['username']) : ''; ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">ФИО *</label>
                                <input type="text" name="full_name" class="form-control" value="<?php echo isset($selected_user['full_name']) ? htmlspecialchars($selected_user['full_name']) : ''; ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" value="<?php echo isset($selected_user['email']) ? htmlspecialchars($selected_user['email']) : ''; ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Роль *</label>
                                <select name="role_id" class="form-select" required>
                                    <option value="">-- Выберите роль --</option>
                                    <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo $role['id']; ?>" <?php if (isset($selected_user['current_role_id']) && $selected_user['current_role_id'] == $role['id']) echo 'selected'; ?>>
                                        <?php echo $role['name']; ?> - <?php echo $role['description']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <?php if ($mode == 'add'): ?>
                            <div class="mb-3">
                                <label class="form-label">Пароль *</label>
                                <input type="password" name="password" class="form-control" required minlength="6">
                                <small class="text-muted">Минимум 6 символов</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Подтверждение пароля *</label>
                                <input type="password" name="confirm_password" class="form-control" required minlength="6">
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($mode == 'edit'): ?>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input type="checkbox" name="change_password" class="form-check-input" id="change_password">
                                    <label class="form-check-label" for="change_password">Сменить пароль</label>
                                </div>
                                
                                <div id="password_fields" style="display: none; margin-top: 10px;">
                                    <div class="mb-3">
                                        <label class="form-label">Новый пароль</label>
                                        <input type="password" name="password" class="form-control" minlength="6">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Подтверждение пароля</label>
                                        <input type="password" name="confirm_password" class="form-control" minlength="6">
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input type="checkbox" name="is_active" class="form-check-input" id="is_active"
                                        <?php 
                                        if (isset($selected_user['is_active']) && $selected_user['is_active'] == 1) echo 'checked'; 
                                        elseif ($mode == 'add') echo 'checked'; 
                                        ?>>
                                    <label class="form-check-label" for="is_active">Активен</label>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" name="<?php echo $mode == 'add' ? 'add_user' : 'edit_user'; ?>" class="btn <?php echo $mode == 'add' ? 'btn-success' : 'btn-warning'; ?>">
                                    <?php echo $mode == 'add' ? 'Создать пользователя' : 'Сохранить изменения'; ?>
                                </button>
                                <a href="index.php?page=admin_users" class="btn btn-secondary">Отмена</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <!-- СПИСОК ПОЛЬЗОВАТЕЛЕЙ -->
        <div class="card">
            <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                <div>
                    <i class="bi bi-people"></i> Список пользователей (<?php echo count($users); ?>)
                </div>
                <a href="index.php?page=admin_users&mode=add" class="btn btn-success btn-sm">
                    <i class="bi bi-person-plus"></i> Добавить пользователя
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($users)): ?>
                    <p class="text-muted">Пользователи не найдены</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Логин</th>
                                    <th>ФИО</th>
                                    <th>Email</th>
                                    <th>Роль</th>
                                    <th>Статус</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): 
                                    $role_color = '';
                                    if ($user['role_name'] == 'admin') {
                                        $role_color = 'danger';
                                    } elseif ($user['role_name'] == 'manager') {
                                        $role_color = 'primary';
                                    } elseif ($user['role_name'] == 'analyst') {
                                        $role_color = 'info';
                                    } elseif ($user['role_name'] == 'viewer') {
                                        $role_color = 'secondary';
                                    } else {
                                        $role_color = 'dark';
                                    }
                                ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><span class="badge bg-<?php echo $role_color; ?>"><?php echo $user['role_name'] ?: 'Не назначена'; ?></span></td>
                                    <td><span class="badge bg-<?php echo $user['is_active'] ? 'success' : 'danger'; ?>"><?php echo $user['is_active'] ? 'Активен' : 'Неактивен'; ?></span></td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <a href="index.php?page=admin_users&mode=edit&user_id=<?php echo $user['id']; ?>" class="btn btn-warning" title="Редактировать"><i class="bi bi-pencil"></i></a>
                                            <a href="index.php?page=admin_users&action=delete&user_id=<?php echo $user['id']; ?>" class="btn btn-danger" title="Удалить" onclick="return confirm('Вы действительно хотите удалить пользователя <?php echo addslashes($user['username']); ?>?')"><i class="bi bi-trash"></i></a>
                                            <?php else: ?>
                                            <span class="btn btn-outline-secondary disabled" title="Это ваш аккаунт"><i class="bi bi-person-check"></i></span>
                                            <span class="btn btn-outline-secondary disabled" title="Нельзя удалить свой аккаунт"><i class="bi bi-trash"></i></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="mt-3">
            <a href="index.php?page=home" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Назад в панель</a>
        </div>
        <?php endif; ?>
    </div>
    <?php
    if ($mode == 'edit') {
        echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            const checkbox = document.getElementById("change_password");
            const fields = document.getElementById("password_fields");
            if (checkbox && fields) {
                checkbox.addEventListener("change", function() {
                    fields.style.display = this.checked ? "block" : "none";
                });
            }
        });
        </script>';
    }
    $conn->close();
    displayFooter();
}

function page_login() {
    // Завершение гостевого режима
    if (isset($_GET['end_guest'])) {
        endGuestMode();
    }

    if (isLoggedIn()) {
        redirect('index.php?page=home');
    }

    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = sanitize($_POST['username']);
        $password = $_POST['password'];
        
        $conn = getConnection();
        $sql = "SELECT id, username, full_name, email, is_active FROM users WHERE username = ? AND is_active = 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (verifyPassword($username, $password)) {
                endGuestMode();
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = getUserRole($user['id']);
                redirect('index.php?page=home');
            } else {
                $error = 'Неверный пароль';
            }
        } else {
            $error = 'Пользователь не найден или неактивен';
        }
        $conn->close();
    }

    if (isset($_GET['guest'])) {
        $_SESSION['guest'] = true;
        $_SESSION['full_name'] = 'Гость';
        redirect('index.php?page=home');
    }

    // Выводим страницу входа без шапки и подвала
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo SITE_NAME; ?> - Вход</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="assets/css/style.css">
        <style>
            body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); height: 100vh; }
            .login-card { max-width: 400px; margin: 100px auto; border-radius: 15px; }
            .login-header { background-color: #007bff; color: white; border-radius: 15px 15px 0 0; }
            .guest-notice { background-color: rgba(13, 202, 240, 0.1); border-left: 4px solid #0dcaf0; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="card login-card shadow">
                <div class="card-header login-header text-center py-3">
                    <h4><i class="bi bi-graph-up"></i> <?php echo SITE_NAME; ?></h4>
                    <p class="mb-0">Система исследования товарного рынка</p>
                </div>
                <div class="card-body p-4">
                    <h5 class="card-title text-center mb-4">Вход в систему</h5>

                    <?php if (isset($_GET['end_guest'])): ?>
                    <div class="guest-notice">
                        <i class="bi bi-info-circle"></i> Гостевой режим завершен. Войдите в систему для редактирования данных.
                    </div>
                    <?php endif; ?>
                
                    <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="username" class="form-label">Логин:</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                        </div>
                    
                        <div class="mb-3">
                            <label for="password" class="form-label">Пароль:</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                        </div>
                    
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-box-arrow-in-right"></i> Войти</button>
                            <a href="index.php?page=login&guest=true" class="btn btn-outline-secondary btn-lg">
                                <i class="bi bi-eye"></i> Продолжить без входа
                            </a>
                        </div>
                    </form>
                
                    <div class="mt-3 text-center">
                        <small class="text-muted">Тестовые данные: admin / admin123</small>
                        <div class="mt-2">
                            <small class="text-info"><i class="bi bi-info-circle"></i> В гостевом режиме доступен только просмотр данных</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
}

function page_logout() {
    session_start();
    endGuestMode();
    $_SESSION = array();
    session_destroy();
    redirect('index.php?page=login');
}
?>