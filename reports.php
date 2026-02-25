<?php
require_once 'config.php';

// Гости могут просматривать отчеты
if (!isLoggedIn() && !isGuest()) {
    redirect('login.php');
}

$conn = getConnection();

// Проверяем права на экспорт
$canExport = hasPermission('export_reports'); // Админ и пользователь

// Параметры отчета
$report_type = isset($_GET['report_type']) ? sanitize($_GET['report_type']) : 'sales';
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : date('Y-m-d');
$format = isset($_GET['format']) ? sanitize($_GET['format']) : 'html';

// Валидация дат
if (empty($date_from)) $date_from = date('Y-m-01');
if (empty($date_to)) $date_to = date('Y-m-d');

// Генерация отчета
$report_data = [];
$report_title = '';

// Если запрошен экспорт
if (isset($_GET['export'])) {
    $format = $_GET['export'];
}

// Определение типа отчета
switch ($report_type) {
    case 'sales':
        $report_title = 'Динамика продаж';
        $sql = "SELECT 
                    DATE(s.sale_date) as sale_date,
                    p.name as product_name,
                    sub.name as subdivision_name,
                    SUM(s.quantity) as total_quantity,
                    SUM(s.total_amount) as total_amount,
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
        $sql = "SELECT 
                    cp.check_date,
                    c.name as competitor_name,
                    p.name as product_name,
                    cp.price,
                    cp.product_name_at_competitor,
                    cp.notes
                FROM competitor_prices cp
                JOIN competitors c ON cp.competitor_id = c.id
                JOIN products p ON cp.product_id = p.id
                WHERE cp.check_date BETWEEN ? AND ?
                ORDER BY cp.check_date DESC, cp.price";
        break;
        
    case 'products':
        $report_title = 'Движение товара';
        $sql = "SELECT 
                    p.name as product_name,
                    p.internal_code,
                    p.category,
                    COUNT(s.id) as sales_count,
                    SUM(s.quantity) as total_sold,
                    SUM(s.total_amount) as total_revenue,
                    MIN(s.sale_date) as first_sale,
                    MAX(s.sale_date) as last_sale
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
$result = $conn->query("SELECT p.name as product_name, 
                               SUM(s.quantity) as total_quantity,
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
$result = $conn->query("SELECT c.name as competitor_name,
                               COUNT(cp.id) as price_checks,
                               MIN(cp.price) as min_price,
                               MAX(cp.price) as max_price
                        FROM competitor_prices cp
                        JOIN competitors c ON cp.competitor_id = c.id
                        GROUP BY cp.competitor_id
                        ORDER BY price_checks DESC");
while ($row = $result->fetch_assoc()) {
    $competitors_report[] = $row;
}

// Если запрошен полный отчет
if (isset($_GET['generate']) || isset($_GET['export'])) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $date_from, $date_to);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $report_data[] = $row;
    }
    
    // Экспорт в CSV
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
            <a href="reports.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Назад к отчетам
            </a>
            <div>
                <button onclick="window.print()" class="btn btn-primary me-2">
                    <i class="bi bi-printer"></i> Печать
                </button>
                <?php if ($canExport): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" 
                   class="btn btn-success">
                    <i class="bi bi-file-earmark-excel"></i> Экспорт в CSV
                </a>
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
                            <button type="submit" class="btn btn-primary form-control">
                                <i class="bi bi-file-earmark-pdf"></i> Сформировать
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <a href="index.php" class="btn btn-outline-secondary mt-3">
        <i class="bi bi-arrow-left"></i> Назад в панель
    </a>
    <?php endif; ?>
</div>

<?php 
$conn->close();
displayFooter();
?>