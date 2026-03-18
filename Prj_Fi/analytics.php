<?php
require_once 'config.php';

// Проверка доступа
if (!isLoggedIn() && !isGuest()) {
    redirect('index.php?page=login');
}

$conn = getConnection();
$message = '';
$action = isset($_GET['action']) ? $_GET['action'] : 'dashboard';

// Обработка вызова процедур
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_competitor_price'])) {
        $competitor_id = intval($_POST['competitor_id']);
        $product_id = intval($_POST['product_id']);
        $price = floatval($_POST['price']);
        $check_date = sanitize($_POST['check_date']);
        $notes = sanitize($_POST['notes']);

        // Проверка даты на стороне PHP (дополнительная защита)
        $today = date('d.m.Y');
        if ($check_date > $today) {
            $message = '<div class="alert alert-danger">Ошибка: Нельзя добавить товар с датой в будущем (сегодня ' . $today . ')</div>';
        } else {
            // Устанавливаем переменную пользователя для триггера
            $current_username = isset($_SESSION['username']) ? $_SESSION['username'] : 'unknown';
            $conn->query("SET @current_user = '$current_username'");

            // Добавляем запись о цене конкурента
            $sql = "INSERT INTO competitor_prices (competitor_id, product_id, price, check_date, notes) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iidss", $competitor_id, $product_id, $price, $check_date, $notes);

            if ($stmt->execute()) {
                $message = '<div class="alert alert-success">Цена конкурента успешно добавлена</div>';
            } else {
                // Проверяем, является ли ошибка результатом триггера
                if (strpos($conn->error, 'future') !== false) {
                    $message = '<div class="alert alert-danger">Ошибка: Нельзя добавить товар с датой в будущем</div>';
                } else {
                    $message = '<div class="alert alert-danger">Ошибка базы данных: ' . $conn->error . '</div>';
                }
            }
        }
    }

    if (isset($_POST['update_competitor_price'])) {
        $price_id = intval($_POST['price_id']);
        $price = floatval($_POST['edit_price']);
        $check_date = sanitize($_POST['edit_check_date']);
        $notes = sanitize($_POST['edit_notes']);

        // Проверка даты на стороне PHP
        $today = date('Y-m-d');
        if ($check_date > $today) {
            $message = '<div class="alert alert-danger">Ошибка: Нельзя установить дату в будущем (сегодня ' . $today . ')</div>';
        } else {
            // Устанавливаем переменную пользователя для триггера
            $current_username = isset($_SESSION['username']) ? $_SESSION['username'] : 'unknown';
            $conn->query("SET @current_user = '$current_username'");

            // Обновляем запись о цене конкурента
            $sql = "UPDATE competitor_prices SET price = ?, check_date = ?, notes = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("dssi", $price, $check_date, $notes, $price_id);

            if ($stmt->execute()) {
                $message = '<div class="alert alert-success">Цена конкурента успешно обновлена</div>';
            } else {
                if (strpos($conn->error, 'future') !== false) {
                    $message = '<div class="alert alert-danger">Ошибка: Нельзя установить дату в будущем</div>';
                } else {
                    $message = '<div class="alert alert-danger">Ошибка базы данных: ' . $conn->error . '</div>';
                }
            }
        }
    }

    if (isset($_POST['get_sales_stats'])) {
        $date_from = sanitize($_POST['date_from']);
        $date_to = sanitize($_POST['date_to']);
        $_SESSION['stats_date_from'] = $date_from;
        $_SESSION['stats_date_to'] = $date_to;
    }

    if (isset($_POST['find_alternatives'])) {
        $product_id = intval($_POST['product_id']);
        $_SESSION['alternative_product_id'] = $product_id;
    }
}

displayHeader('Аналитика');
?>

<div class="container mt-4">
    <h2><i class="bi bi-graph-up"></i> Аналитический модуль</h2>
    
    <?php if (isGuest()): ?>
    <div class="alert alert-info">
        <i class="bi bi-eye"></i> Вы находитесь в гостевом режиме. Данные представлены для ознакомления.
    </div>
    <?php endif; ?>
    
    <?php echo $message; ?>
    
    <!-- Навигация по аналитике -->
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?php echo $action == 'dashboard' ? 'active' : ''; ?>" 
               href="?page=analytics&action=dashboard">
                <i class="bi bi-speedometer2"></i> Дашборд
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $action == 'market_share' ? 'active' : ''; ?>" 
               href="?page=analytics&action=market_share">
                <i class="bi bi-pie-chart"></i> Доля рынка
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $action == 'competitor_prices' ? 'active' : ''; ?>" 
               href="?page=analytics&action=competitor_prices">
                <i class="bi bi-tags"></i> Цены конкурентов
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $action == 'subdivision_rating' ? 'active' : ''; ?>" 
               href="?page=analytics&action=subdivision_rating">
                <i class="bi bi-building"></i> Рейтинг подразделений
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $action == 'sales_stats' ? 'active' : ''; ?>" 
               href="?page=analytics&action=sales_stats">
                <i class="bi bi-bar-chart"></i> Статистика продаж
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $action == 'alternatives' ? 'active' : ''; ?>" 
               href="?page=analytics&action=alternatives">
                <i class="bi bi-arrow-left-right"></i> Поиск альтернатив
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $action == 'price_log' ? 'active' : ''; ?>" 
               href="?page=analytics&action=price_log">
                <i class="bi bi-journal-text"></i> История цен
            </a>
        </li>
    </ul>
    
    <?php
    // Отображение соответствующей страницы
    switch ($action) {
        case 'dashboard':
            showDashboard($conn);
            break;
        case 'market_share':
            showMarketShare($conn);
            break;
        case 'competitor_prices':
            showCompetitorPrices($conn);
            break;
        case 'subdivision_rating':
            showSubdivisionRating($conn);
            break;
        case 'sales_stats':
            showSalesStats($conn);
            break;
        case 'alternatives':
            showAlternatives($conn);
            break;
        case 'price_log':
            showPriceLog($conn);
            break;
        default:
            showDashboard($conn);
    }
    ?>
    
    <a href="index.php?page=home" class="btn btn-outline-secondary mt-3">
        <i class="bi bi-arrow-left"></i> Назад в панель
    </a>
</div>

<?php
$conn->close();
displayFooter();

// =====================================================
// ФУНКЦИИ ОТОБРАЖЕНИЯ
// =====================================================

function showDashboard($conn) {
    ?>
    <div class="row">
        <div class="col-md-12 mb-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-speedometer2"></i> Аналитический дашборд
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Быстрая статистика -->
                        <div class="col-md-3">
                            <div class="card text-center bg-light">
                                <div class="card-body">
                                    <h5>Товаров</h5>
                                    <h3><?php 
                                        $result = $conn->query("SELECT COUNT(*) as cnt FROM products WHERE is_active=1");
                                        echo $result->fetch_assoc()['cnt'];
                                    ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center bg-light">
                                <div class="card-body">
                                    <h5>Подразделений</h5>
                                    <h3><?php 
                                        $result = $conn->query("SELECT COUNT(*) as cnt FROM subdivisions WHERE is_active=1");
                                        echo $result->fetch_assoc()['cnt'];
                                    ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center bg-light">
                                <div class="card-body">
                                    <h5>Конкурентов</h5>
                                    <h3><?php 
                                        $result = $conn->query("SELECT COUNT(*) as cnt FROM competitors");
                                        echo $result->fetch_assoc()['cnt'];
                                    ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center bg-light">
                                <div class="card-body">
                                    <h5>Продаж за месяц</h5>
                                    <h3><?php 
                                        $result = $conn->query("SELECT COUNT(*) as cnt FROM sales WHERE MONTH(sale_date)=MONTH(CURDATE())");
                                        echo $result->fetch_assoc()['cnt'];
                                    ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Топ товаров -->
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <h5>Топ-5 товаров по выручке</h5>
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Товар</th>
                                        <th>Выручка</th>
                                        <th>Доля рынка</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $sql = "SELECT p.id, p.name, SUM(s.total_amount) as revenue
                                            FROM sales s
                                            JOIN products p ON s.product_id = p.id
                                            WHERE s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
                                            GROUP BY s.product_id
                                            ORDER BY revenue DESC
                                            LIMIT 5";
                                    $result = $conn->query($sql);
                                    while ($row = $result->fetch_assoc()):
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td><?php echo number_format($row['revenue'], 2); ?> ₽</td>
                                        <td>
                                            <?php 
                                            $share = $conn->query("SELECT GetMarketShare({$row['id']}, MONTH(CURDATE()), YEAR(CURDATE())) as share")->fetch_assoc();
                                            echo $share['share'] . '%';
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="col-md-6">
                            <h5>Активность конкурентов</h5>
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Конкурент</th>
                                        <th>Последняя цена</th>
                                        <th>Дата</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $sql = "SELECT * FROM current_competitor_prices 
                                            ORDER BY last_check_date DESC LIMIT 5";
                                    $result = $conn->query($sql);
                                    while ($row = $result->fetch_assoc()):
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['competitor_name']); ?></td>
                                        <td><?php echo number_format($row['price'], 2); ?> ₽</td>
                                        <td><?php echo date('d.m.Y', strtotime($row['last_check_date'])); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Рейтинг подразделений -->
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <h5>Рейтинг подразделений</h5>
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Подразделение</th>
                                        <th>Город</th>
                                        <th>Продаж</th>
                                        <th>Выручка</th>
                                        <th>Рейтинг</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $sql = "SELECT *, GetSubdivisionRating(id, YEAR(CURDATE())) as rating 
                                            FROM subdivision_efficiency 
                                            ORDER BY revenue DESC LIMIT 5";
                                    $result = $conn->query($sql);
                                    while ($row = $result->fetch_assoc()):
                                        $badge_color = 'secondary';
                                        if ($row['rating'] == 'Отлично') $badge_color = 'success';
                                        elseif ($row['rating'] == 'Хорошо') $badge_color = 'primary';
                                        elseif ($row['rating'] == 'Средне') $badge_color = 'warning';
                                        elseif ($row['rating'] == 'Низко') $badge_color = 'danger';
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['city']); ?></td>
                                        <td><?php echo $row['sales_count']; ?></td>
                                        <td><?php echo number_format($row['revenue'], 2); ?> ₽</td>
                                        <td><span class="badge bg-<?php echo $badge_color; ?>"><?php echo $row['rating']; ?></span></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

function showMarketShare($conn) {
    $selected_month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
    $selected_year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
    ?>
    <div class="card">
        <div class="card-header bg-primary text-white">
            <i class="bi bi-pie-chart"></i> Анализ доли рынка
        </div>
        <div class="card-body">
            <form method="GET" class="row mb-4">
                <input type="hidden" name="page" value="analytics">
                <input type="hidden" name="action" value="market_share">
                <div class="col-md-4">
                    <label>Месяц:</label>
                    <select name="month" class="form-select">
                        <?php
                        $months = [
                            1 => 'Январь',
                            2 => 'Февраль',
                            3 => 'Март',
                            4 => 'Апрель',
                            5 => 'Май',
                            6 => 'Июнь',
                            7 => 'Июль',
                            8 => 'Август',
                            9 => 'Сентябрь',
                            10 => 'Октябрь',
                            11 => 'Ноябрь',
                            12 => 'Декабрь'
                        ];
                        foreach ($months as $m => $name):
                        ?>
                        <option value="<?php echo $m; ?>" <?php echo $m == $selected_month ? 'selected' : ''; ?>>
                            <?php echo $name; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label>Год:</label>
                    <select name="year" class="form-select">
                        <?php for ($y = 2024; $y <= 2026; $y++): ?>
                        <option value="<?php echo $y; ?>" <?php echo $y == $selected_year ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary form-control">Обновить</button>
                </div>
            </form>
            
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Товар</th>
                        <th>Категория</th>
                        <th>Продажи (шт)</th>
                        <th>Выручка</th>
                        <th>Доля рынка</th>
                        <th>Позиция</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT p.*, 
                                   COALESCE(SUM(s.quantity), 0) as total_qty,
                                   COALESCE(SUM(s.total_amount), 0) as total_revenue,
                                   GetMarketShare(p.id, $selected_month, $selected_year) as market_share
                            FROM products p
                            LEFT JOIN sales s ON p.id = s.product_id 
                                AND MONTH(s.sale_date) = $selected_month 
                                AND YEAR(s.sale_date) = $selected_year
                            WHERE p.is_active = 1
                            GROUP BY p.id
                            ORDER BY market_share DESC";
                    $result = $conn->query($sql);
                    $rank = 1;
                    while ($row = $result->fetch_assoc()):
                        $position_class = $rank == 1 ? 'success' : ($rank == 2 ? 'primary' : ($rank == 3 ? 'info' : 'secondary'));
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['category']); ?></td>
                        <td><?php echo $row['total_qty']; ?></td>
                        <td><?php echo number_format($row['total_revenue'], 2); ?> ₽</td>
                        <td>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar bg-<?php echo $position_class; ?>" 
                                     style="width: <?php echo $row['market_share']; ?>%">
                                    <?php echo $row['market_share']; ?>%
                                </div>
                            </div>
                        </td>
                        <td><span class="badge bg-<?php echo $position_class; ?>">#<?php echo $rank++; ?></span></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

function showCompetitorPrices($conn) {
    ?>
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-tags"></i> Текущие цены конкурентов
                </div>
                <div class="card-body">
                    <!-- Форма добавления цены (только для авторизованных) -->
                    <?php if (!isGuest() && hasPermission('edit_data')): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <i class="bi bi-plus-circle"></i> Добавить новую цену конкурента
                        </div>
                        <div class="card-body">
                            <form method="POST" class="row">
                                <div class="col-md-3">
                                    <select name="competitor_id" class="form-select" required>
                                        <option value="">Конкурент</option>
                                        <?php
                                        $comp = $conn->query("SELECT id, name FROM competitors ORDER BY name");
                                        while ($c = $comp->fetch_assoc()):
                                        ?>
                                        <option value="<?php echo $c['id']; ?>"><?php echo $c['name']; ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <select name="category" id="category_select" class="form-select" onchange="filterProducts()">
                                        <option value="">Категория</option>
                                        <?php
                                        $cats = $conn->query("SELECT DISTINCT category FROM products WHERE is_active=1 ORDER BY category");
                                        while ($cat = $cats->fetch_assoc()):
                                        ?>
                                        <option value="<?php echo htmlspecialchars($cat['category']); ?>"><?php echo htmlspecialchars($cat['category']); ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <select name="product_id" id="product_select" class="form-select" required>
                                        <option value="">Товар</option>
                                        <?php
                                        $prod = $conn->query("SELECT id, name, category FROM products WHERE is_active=1 ORDER BY category, name");
                                        while ($p = $prod->fetch_assoc()):
                                        ?>
                                        <option value="<?php echo $p['id']; ?>" data-category="<?php echo htmlspecialchars($p['category']); ?>"><?php echo htmlspecialchars($p['name']); ?> (<?php echo htmlspecialchars($p['category']); ?>)</option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <input type="number" name="price" class="form-control" placeholder="Цена" step="0.01" required>
                                </div>
                                <div class="col-md-2">
                                    <input type="date" name="check_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <input type="text" name="notes" class="form-control" placeholder="Примечание">
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" name="add_competitor_price" class="btn btn-success form-control">
                                        <i class="bi bi-plus"></i> Добавить
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>
                    <script>
                    function filterProducts() {
                        var category = document.getElementById('category_select').value;
                        var options = document.getElementById('product_select').options;
                        for (var i = 0; i < options.length; i++) {
                            var optCategory = options[i].getAttribute('data-category');
                            if (category === '' || optCategory === category) {
                                options[i].style.display = '';
                            } else {
                                options[i].style.display = 'none';
                            }
                        }
                        document.getElementById('product_select').value = '';
                    }
                    </script>
                    
                    <!-- Таблица цен -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Конкурент</th>
                                    <th>Товар</th>
                                    <th>Категория</th>
                                    <th>Цена</th>
                                    <th>Дата проверки</th>
                                    <th>Дней назад</th>
                                    <?php if (!isGuest() && hasPermission('edit_data')): ?>
                                    <th>Действия</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $sql = "SELECT cp.*, c.name as competitor_name, p.name as product_name, p.category
                                        FROM competitor_prices cp
                                        JOIN competitors c ON cp.competitor_id = c.id
                                        JOIN products p ON cp.product_id = p.id
                                        ORDER BY cp.check_date DESC";
                                $result = $conn->query($sql);
                                while ($row = $result->fetch_assoc()):
                                    $days = floor((time() - strtotime($row['check_date'])) / 86400);
                                    $badge = $days <= 7 ? 'success' : ($days <= 30 ? 'warning' : 'danger');
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['competitor_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['category']); ?></td>
                                    <td><strong><?php echo number_format($row['price'], 2); ?> ₽</strong></td>
                                    <td><?php echo date('d.m.Y', strtotime($row['check_date'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $badge; ?>"><?php echo $days; ?> дн.</span>
                                    </td>
                                    <?php if (!isGuest() && hasPermission('edit_data')): ?>
                                    <td>
                                        <button class="btn btn-sm btn-primary edit-price-btn"
                                                data-id="<?php echo $row['id']; ?>"
                                                data-competitor="<?php echo htmlspecialchars($row['competitor_name']); ?>"
                                                data-product="<?php echo htmlspecialchars($row['product_name']); ?>"
                                                data-price="<?php echo $row['price']; ?>"
                                                data-date="<?php echo $row['check_date']; ?>"
                                                data-notes="<?php echo htmlspecialchars($row['notes']); ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Модальное окно редактирования цены -->
                    <?php if (!isGuest() && hasPermission('edit_data')): ?>
                    <div class="modal fade" id="editPriceModal" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header bg-primary text-white">
                                    <h5 class="modal-title"><i class="bi bi-pencil"></i> Редактировать цену</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <form method="POST">
                                    <div class="modal-body">
                                        <input type="hidden" name="price_id" id="edit_price_id">
                                        <div class="mb-3">
                                            <label>Конкурент:</label>
                                            <input type="text" class="form-control" id="edit_competitor" readonly>
                                        </div>
                                        <div class="mb-3">
                                            <label>Товар:</label>
                                            <input type="text" class="form-control" id="edit_product" readonly>
                                        </div>
                                        <div class="mb-3">
                                            <label>Цена:</label>
                                            <input type="number" name="edit_price" id="edit_price_input" class="form-control" step="0.01" required>
                                        </div>
                                        <div class="mb-3">
                                            <label>Дата проверки:</label>
                                            <input type="date" name="edit_check_date" id="edit_check_date" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label>Примечание:</label>
                                            <textarea name="edit_notes" id="edit_notes" class="form-control" rows="3"></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                                        <button type="submit" name="update_competitor_price" class="btn btn-primary">Сохранить</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        var editModal = new bootstrap.Modal(document.getElementById('editPriceModal'));
                        document.querySelectorAll('.edit-price-btn').forEach(function(btn) {
                            btn.addEventListener('click', function() {
                                document.getElementById('edit_price_id').value = this.dataset.id;
                                document.getElementById('edit_competitor').value = this.dataset.competitor;
                                document.getElementById('edit_product').value = this.dataset.product;
                                document.getElementById('edit_price_input').value = this.dataset.price;
                                document.getElementById('edit_check_date').value = this.dataset.date;
                                document.getElementById('edit_notes').value = this.dataset.notes;
                                editModal.show();
                            });
                        });
                    });
                    </script>
                    <?php endif; ?>
                    
                    <!-- Минимальные цены -->
                    <div class="mt-4">
                        <h5>Минимальные цены на товары</h5>
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Товар</th>
                                    <th>Категория</th>
                                    <th>Наша средняя цена</th>
                                    <th>Мин. цена конкурента</th>
                                    <th>Конкурент</th>
                                    <th>Разница</th>
                                    <th>Рекомендация</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $sql = "SELECT p.id, p.name, p.category,
                                               AVG(s.total_amount/s.quantity) as our_price,
                                               (SELECT cp.price FROM competitor_prices cp 
                                                WHERE cp.product_id = p.id 
                                                ORDER BY cp.price ASC 
                                                LIMIT 1) as min_comp_price,
                                               (SELECT c.name FROM competitor_prices cp 
                                                JOIN competitors c ON cp.competitor_id = c.id
                                                WHERE cp.product_id = p.id 
                                                ORDER BY cp.price ASC 
                                                LIMIT 1) as min_competitor_name
                                        FROM products p
                                        LEFT JOIN sales s ON p.id = s.product_id
                                        WHERE p.is_active = 1
                                        GROUP BY p.id
                                        HAVING our_price IS NOT NULL";
                                $result = $conn->query($sql);
                                while ($row = $result->fetch_assoc()):
                                    $min_price = $row['min_comp_price'] ? $row['min_comp_price'] : 0;
                                    $diff = $row['our_price'] - $min_price;
                                    $diff_percent = $min_price > 0 ? ($diff / $row['our_price']) * 100 : 100;
                                    
                                    if ($min_price == 0) {
                                        $badge = 'secondary';
                                        $recommendation = 'Нет данных о ценах конкурентов';
                                    } elseif ($diff_percent > 10) {
                                        $badge = 'danger';
                                        $recommendation = 'Цена выше конкурентов';
                                    } elseif ($diff_percent < -10) {
                                        $badge = 'success';
                                        $recommendation = 'Цена ниже конкурентов';
                                    } else {
                                        $badge = 'warning';
                                        $recommendation = 'Цена в норме';
                                    }
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                                    <td><small><?php echo htmlspecialchars($row['category']); ?></small></td>
                                    <td><?php echo number_format($row['our_price'], 2); ?> ₽</td>
                                    <td>
                                        <?php if ($min_price > 0): ?>
                                            <strong><?php echo number_format($min_price, 2); ?> ₽</strong>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['min_competitor_name']): ?>
                                            <small><?php echo htmlspecialchars($row['min_competitor_name']); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $badge; ?>">
                                            <?php if ($min_price > 0): ?>
                                                <?php echo number_format($diff, 2); ?> ₽ (<?php echo number_format($diff_percent, 1); ?>%)
                                            <?php else: ?>
                                                Нет данных
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td><small><?php echo $recommendation; ?></small></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

function showSubdivisionRating($conn) {
    $year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
    ?>
    <div class="card">
        <div class="card-header bg-primary text-white">
            <i class="bi bi-building"></i> Рейтинг подразделений за <?php echo $year; ?> год
        </div>
        <div class="card-body">
            <form method="GET" class="row mb-4">
                <input type="hidden" name="page" value="analytics">
                <input type="hidden" name="action" value="subdivision_rating">
                <div class="col-md-3">
                    <select name="year" class="form-select">
                        <?php for ($y = 2024; $y <= 2026; $y++): ?>
                        <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary">Обновить</button>
                </div>
            </form>
            
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Подразделение</th>
                        <th>Город</th>
                        <th>Продаж</th>
                        <th>Товаров</th>
                        <th>Выручка</th>
                        <th>Ср. чек</th>
                        <th>Рейтинг</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT *, GetSubdivisionRating(id, $year) as rating 
                            FROM subdivision_efficiency 
                            ORDER BY revenue DESC";
                    $result = $conn->query($sql);
                    while ($row = $result->fetch_assoc()):
                        $badge_color = 'secondary';
                        if ($row['rating'] == 'Отлично') $badge_color = 'success';
                        elseif ($row['rating'] == 'Хорошо') $badge_color = 'primary';
                        elseif ($row['rating'] == 'Средне') $badge_color = 'warning';
                        elseif ($row['rating'] == 'Низко') $badge_color = 'danger';
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['city']); ?></td>
                        <td><?php echo $row['sales_count']; ?></td>
                        <td><?php echo $row['unique_products_sold']; ?></td>
                        <td><?php echo number_format($row['revenue'], 2); ?> ₽</td>
                        <td><?php echo number_format($row['avg_sale_amount'], 2); ?> ₽</td>
                        <td><span class="badge bg-<?php echo $badge_color; ?>" style="font-size: 1em;"><?php echo $row['rating']; ?></span></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            
            <!-- Легенда -->
            <div class="alert alert-info mt-3">
                <h6>Как определяется рейтинг:</h6>
                <ul class="mb-0">
                    <li><span class="badge bg-success">Отлично</span> - продажи в 2+ раза выше среднего</li>
                    <li><span class="badge bg-primary">Хорошо</span> - продажи выше среднего</li>
                    <li><span class="badge bg-warning">Средне</span> - продажи от 50% до 100% от среднего</li>
                    <li><span class="badge bg-danger">Низко</span> - продажи ниже 50% от среднего</li>
                </ul>
            </div>
        </div>
    </div>
    <?php
}

function showSalesStats($conn) {
    $date_from = isset($_SESSION['stats_date_from']) ? $_SESSION['stats_date_from'] : date('Y-m-01');
    $date_to = isset($_SESSION['stats_date_to']) ? $_SESSION['stats_date_to'] : date('Y-m-d');
    ?>
    <div class="card">
        <div class="card-header bg-primary text-white">
            <i class="bi bi-bar-chart"></i> Статистика продаж
        </div>
        <div class="card-body">
            <form method="POST" class="row mb-4">
                <div class="col-md-4">
                    <label>Дата с:</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>" required>
                </div>
                <div class="col-md-4">
                    <label>Дата по:</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>" required>
                </div>
                <div class="col-md-4">
                    <label>&nbsp;</label>
                    <button type="submit" name="get_sales_stats" class="btn btn-primary form-control">
                        <i class="bi bi-search"></i> Показать статистику
                    </button>
                </div>
            </form>
            
            <?php
            // Вызываем хранимую процедуру
            $sql = "CALL GetSalesStatistics('$date_from', '$date_to')";
            if ($conn->multi_query($sql)) {
                // Первый результат - общая статистика
                if ($result = $conn->store_result()) {
                    $stats = $result->fetch_assoc();
                    $result->free();
                    $conn->next_result();
                    
                    // Второй результат - статистика по дням
                    $daily_stats = [];
                    if ($result = $conn->store_result()) {
                        while ($row = $result->fetch_assoc()) {
                            $daily_stats[] = $row;
                        }
                        $result->free();
                    }
            ?>
            
            <!-- Общая статистика -->
            <div class="row mb-4">
                <div class="col-md-2">
                    <div class="card text-center bg-light">
                        <div class="card-body">
                            <h6>Всего продаж</h6>
                            <h3><?php echo $stats['total_sales_count']; ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-center bg-light">
                        <div class="card-body">
                            <h6>Товаров продано</h6>
                            <h3><?php echo $stats['total_items_sold']; ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-center bg-light">
                        <div class="card-body">
                            <h6>Выручка</h6>
                            <h3><?php echo number_format($stats['total_revenue'], 0); ?> ₽</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-center bg-light">
                        <div class="card-body">
                            <h6>Средний чек</h6>
                            <h3><?php echo number_format($stats['avg_sale_amount'], 2); ?> ₽</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-center bg-light">
                        <div class="card-body">
                            <h6>Уникальных товаров</h6>
                            <h3><?php echo $stats['unique_products_sold']; ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-center bg-light">
                        <div class="card-body">
                            <h6>Активных подразделений</h6>
                            <h3><?php echo $stats['active_subdivisions']; ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Статистика по дням -->
            <h5>Динамика по дням</h5>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Дата</th>
                        <th>Продаж</th>
                        <th>Товаров</th>
                        <th>Выручка</th>
                        <th>Ср. чек</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($daily_stats as $day): ?>
                    <tr>
                        <td><?php echo date('d.m.Y', strtotime($day['sale_date'])); ?></td>
                        <td><?php echo $day['sales_count']; ?></td>
                        <td><?php echo $day['items_sold']; ?></td>
                        <td><?php echo number_format($day['daily_revenue'], 2); ?> ₽</td>
                        <td><?php echo $day['items_sold'] > 0 ? number_format($day['daily_revenue'] / $day['items_sold'], 2) : 0; ?> ₽</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php
                }
            }
            ?>
        </div>
    </div>
    <?php
}

function showAlternatives($conn) {
    $selected_product = isset($_SESSION['alternative_product_id']) ? intval($_SESSION['alternative_product_id']) : 0;
    ?>
    <div class="card">
        <div class="card-header bg-primary text-white">
            <i class="bi bi-arrow-left-right"></i> Поиск альтернативных товаров
        </div>
        <div class="card-body">
            <form method="POST" class="row mb-4">
                <div class="col-md-6">
                    <select name="product_id" class="form-select" required>
                        <option value="">Выберите товар</option>
                        <?php
                        $products = $conn->query("SELECT id, name, category FROM products WHERE is_active=1 ORDER BY name");
                        while ($p = $products->fetch_assoc()):
                        ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo $p['id'] == $selected_product ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($p['name']); ?> (<?php echo htmlspecialchars($p['category']); ?>)
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" name="find_alternatives" class="btn btn-primary">
                        <i class="bi bi-search"></i> Найти альтернативы
                    </button>
                </div>
            </form>
            
            <?php if ($selected_product > 0): 
                $product_info = $conn->query("SELECT * FROM products WHERE id = $selected_product")->fetch_assoc();
            ?>
                <h5>Альтернативы для: <?php echo htmlspecialchars($product_info['name']); ?></h5>
                
                <!-- Существующие альтернативы из таблицы alternative_products -->
                <h6 class="mt-3">Ручные альтернативы:</h6>
                <?php
                $sql = "SELECT ap.*, p.name as alt_name, p.category 
                        FROM alternative_products ap
                        JOIN products p ON ap.alternative_product_id = p.id
                        WHERE ap.main_product_id = $selected_product";
                $result = $conn->query($sql);
                if ($result->num_rows > 0):
                ?>
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Альтернатива</th>
                            <th>Категория</th>
                            <th>Тип связи</th>
                            <th>Примечания</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['alt_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['category']); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $row['relation_type'] == 'substitute' ? 'warning' : 
                                        ($row['relation_type'] == 'complement' ? 'success' : 'info'); 
                                ?>">
                                    <?php echo $row['relation_type']; ?>
                                </span>
                            </td>
                            <td><small><?php echo htmlspecialchars($row['notes']); ?></small></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p class="text-muted">Нет ручных альтернатив</p>
                <?php endif; ?>
                
                <!-- Автоматический поиск альтернатив через процедуру -->
                <h6 class="mt-3">Рекомендуемые альтернативы (на основе продаж):</h6>
                <?php
                $sql = "CALL FindBestAlternatives($selected_product)";
                if ($conn->multi_query($sql)) {
                    if ($result = $conn->store_result()) {
                        if ($result->num_rows > 0):
                ?>
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Товар</th>
                            <th>Категория</th>
                            <th>Средняя цена</th>
                            <th>Продаж</th>
                            <th>Всего продано</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><?php echo htmlspecialchars($row['category']); ?></td>
                            <td><?php echo number_format($row['avg_price'], 2); ?> ₽</td>
                            <td><?php echo $row['sales_count']; ?></td>
                            <td><?php echo $row['total_sold']; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php 
                        else:
                            echo '<p class="text-muted">Не найдено альтернатив в этой категории</p>';
                        endif;
                        $result->free();
                    }
                }
                ?>
                
            <?php endif; ?>
        </div>
    </div>
    <?php
}

function showPriceLog($conn) {
    ?>
    <div class="card">
        <div class="card-header bg-primary text-white">
            <i class="bi bi-journal-text"></i> История изменений цен конкурентов
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Дата</th>
                            <th>Пользователь</th>
                            <th>Тип</th>
                            <th>Старая цена</th>
                            <th>Новая цена</th>
                            <th>Изменение</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT l.*, cp.competitor_id, cp.product_id,
                                       c.name as competitor_name,
                                       p.name as product_name
                                FROM price_change_log l
                                JOIN competitor_prices cp ON l.competitor_price_id = cp.id
                                JOIN competitors c ON cp.competitor_id = c.id
                                JOIN products p ON cp.product_id = p.id
                                ORDER BY l.change_date DESC
                                LIMIT 100";
                        $result = $conn->query($sql);
                        while ($row = $result->fetch_assoc()):
                            $change = $row['new_price'] - $row['old_price'];
                            $change_class = $change > 0 ? 'text-danger' : ($change < 0 ? 'text-success' : '');
                            $change_sign = $change > 0 ? '+' : '';
                        ?>
                        <tr>
                            <td><?php echo date('d.m.Y H:i', strtotime($row['change_date'])); ?></td>
                            <td><small><?php echo htmlspecialchars($row['changed_by']); ?></small></td>
                            <td>
                                <span class="badge bg-<?php echo $row['action_type'] == 'INSERT' ? 'success' : 'warning'; ?>">
                                    <?php echo $row['action_type']; ?>
                                </span>
                            </td>
                            <td><?php echo $row['old_price'] ? number_format($row['old_price'], 2) . ' ₽' : '-'; ?></td>
                            <td><?php echo number_format($row['new_price'], 2); ?> ₽</td>
                            <td class="<?php echo $change_class; ?>">
                                <?php if ($row['old_price']): ?>
                                    <?php echo $change_sign . number_format($change, 2); ?> ₽
                                    (<?php echo $change_sign . number_format(($change / $row['old_price']) * 100, 1); ?>%)
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="6" class="text-muted small">
                                <em><?php echo htmlspecialchars($row['competitor_name']); ?> - <?php echo htmlspecialchars($row['product_name']); ?></em>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
}
?>