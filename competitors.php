<?php
require_once 'config.php';

// Гости могут просматривать конкурентов
if (!isLoggedIn() && !isGuest()) {
    redirect('login.php');
}

$conn = getConnection();
$message = '';

// Обработка добавления конкурента
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_competitor']) && canEdit()) {
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_price']) && canEdit()) {
    $competitor_id = intval($_POST['competitor_id']);
    $product_id = intval($_POST['product_id']);
    $check_date = sanitize($_POST['check_date']);
    $price = floatval($_POST['price']);
    $product_name_at_competitor = sanitize($_POST['product_name_at_competitor']);
    $notes = sanitize($_POST['notes']);
    
    $sql = "INSERT INTO competitor_prices (competitor_id, product_id, check_date, price, 
            product_name_at_competitor, notes) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisdss", $competitor_id, $product_id, $check_date, $price, 
                      $product_name_at_competitor, $notes);
    
    if ($stmt->execute()) {
        $message = '<div class="alert alert-success">Цена конкурента сохранена</div>';
    } else {
        $message = '<div class="alert alert-danger">Ошибка: ' . $conn->error . '</div>';
    }
}

// Обработка удаления
$action = isset($_GET['action']) ? $_GET['action'] : '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($action == 'delete' && $id > 0 && isset($_GET['confirm']) && $_GET['confirm'] == 'yes' && canEdit()) {
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
    redirect('competitors.php');
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
$sql = "SELECT cp.*, 
               c.name as competitor_name,
               p.name as product_name,
               p.internal_code
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
        <i class="bi bi-eye"></i> Вы находитесь в гостевом режиме. Для редактирования данных необходимо <a href="login.php" class="alert-link">войти в систему</a>.
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
                    
                    <?php if (canEdit()): ?>
                    <div class="mt-4">
                        <a href="?action=delete&id=<?php echo $id; ?>&type=<?php echo $_GET['type']; ?>&confirm=yes" 
                           class="btn btn-danger btn-lg me-3">
                            <i class="bi bi-trash"></i> Да, удалить
                        </a>
                        <a href="competitors.php" class="btn btn-secondary btn-lg">
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
    <div class="row mt-3">
        <?php if (canEdit()): ?>
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
                            <input type="url" name="website" class="form-control" 
                                   placeholder="https://example.com">
                        </div>
                        <button type="submit" name="add_competitor" class="btn btn-primary">
                            <i class="bi bi-save"></i> Добавить
                        </button>
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
                                <option value="<?php echo $product['id']; ?>">
                                    <?php echo $product['name']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Дата проверки:</label>
                            <input type="date" name="check_date" class="form-control" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Цена у конкурента (руб.):</label>
                            <input type="number" name="price" class="form-control" 
                                   min="0.01" step="0.01" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Название у конкурента:</label>
                            <input type="text" name="product_name_at_competitor" class="form-control">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Примечания:</label>
                            <textarea name="notes" class="form-control" rows="2" 
                                      placeholder="Акция, наличие, условия..."></textarea>
                        </div>
                        
                        <button type="submit" name="add_price" class="btn btn-success">
                            <i class="bi bi-save"></i> Сохранить цену
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Правая колонка: таблицы -->
        <div class="<?php echo canEdit() ? 'col-md-8' : 'col-md-12'; ?>">
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
                                            <?php if (canEdit()): ?>
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
                                                <br>
                                                <small>
                                                    <a href="<?php echo $competitor['website']; ?>" target="_blank" class="text-decoration-none">
                                                        <i class="bi bi-link-45deg"></i> Сайт
                                                    </a>
                                                </small>
                                                <?php endif; ?>
                                            </td>
                                            <?php if (canEdit()): ?>
                                            <td>
                                                <a href="?action=delete&id=<?php echo $competitor['id']; ?>&type=competitor" 
                                                   class="btn btn-sm btn-danger"
                                                   onclick="return confirm('Удалить конкурента?')">
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
                                            <?php if (canEdit()): ?>
                                            <th>Действия</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($prices as $price): ?>
                                        <tr>
                                            <td><small><?php echo date('d.m', strtotime($price['check_date'])); ?></small></td>
                                            <td>
                                                <small><?php echo $price['competitor_name']; ?></small>
                                                <br>
                                                <small class="text-muted"><?php echo $price['product_name']; ?></small>
                                            </td>
                                            <td><strong><?php echo number_format($price['price'], 0); ?> ₽</strong></td>
                                            <?php if (canEdit()): ?>
                                            <td>
                                                <a href="?action=delete&id=<?php echo $price['id']; ?>&type=price" 
                                                   class="btn btn-sm btn-danger"
                                                   onclick="return confirm('Удалить запись о цене?')">
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
                                            <?php if (canEdit()): ?>
                                            <th>Действия</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($prices as $price): ?>
                                        <tr>
                                            <td><?php echo date('d.m.Y', strtotime($price['check_date'])); ?></td>
                                            <td><?php echo $price['competitor_name']; ?></td>
                                            <td>
                                                <small><?php echo $price['product_name']; ?></small>
                                                <br>
                                                <small class="text-muted"><?php echo $price['internal_code']; ?></small>
                                            </td>
                                            <td><strong><?php echo number_format($price['price'], 2); ?> ₽</strong></td>
                                            <td><small><?php echo $price['product_name_at_competitor']; ?></small></td>
                                            <td><small><?php echo $price['notes']; ?></small></td>
                                            <?php if (canEdit()): ?>
                                            <td>
                                                <a href="?action=delete&id=<?php echo $price['id']; ?>&type=price" 
                                                   class="btn btn-sm btn-danger"
                                                   onclick="return confirm('Удалить запись о цене?')">
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