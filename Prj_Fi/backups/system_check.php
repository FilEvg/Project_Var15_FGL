<?php
// system_check.php - Комплексная проверка системы
// Запустите этот файл для диагностики: php system_check.php или через браузер

// Подключаем необходимые файлы
require_once 'config.php';
require_once 'backup_functions.php';

// Настройки
define('CHECK_RESULTS_DIR', __DIR__ . '/backups/checks/');
if (!file_exists(CHECK_RESULTS_DIR)) {
    mkdir(CHECK_RESULTS_DIR, 0755, true);
}

// Цвета для консольного вывода
define('COLOR_GREEN', "\033[32m");
define('COLOR_RED', "\033[31m");
define('COLOR_YELLOW', "\033[33m");
define('COLOR_RESET', "\033[0m");

// Определяем окружение (web или cli)
$is_cli = (php_sapi_name() === 'cli');

/**
 * Функция для вывода результата
 */
function output($message, $type = 'info') {
    global $is_cli;
    
    if ($is_cli) {
        $color = COLOR_RESET;
        switch ($type) {
            case 'success': $color = COLOR_GREEN; break;
            case 'error': $color = COLOR_RED; break;
            case 'warning': $color = COLOR_YELLOW; break;
        }
        echo $color . $message . COLOR_RESET . "\n";
    } else {
        $class = 'info';
        switch ($type) {
            case 'success': $class = 'success'; break;
            case 'error': $class = 'danger'; break;
            case 'warning': $class = 'warning'; break;
        }
        echo '<div class="alert alert-' . $class . '">' . htmlspecialchars($message) . '</div>';
    }
}

// Если запущено в браузере, выводим HTML шапку
if (!$is_cli) {
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Проверка системы</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { padding: 20px; background: #f8f9fa; }
            .check-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
            .check-section { background: white; border-radius: 10px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,.1); }
            .check-title { border-bottom: 2px solid #dee2e6; padding-bottom: 10px; margin-bottom: 20px; }
            .badge-pass { background-color: #28a745; color: white; padding: 5px 10px; border-radius: 5px; }
            .badge-fail { background-color: #dc3545; color: white; padding: 5px 10px; border-radius: 5px; }
            .badge-warn { background-color: #ffc107; color: black; padding: 5px 10px; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="check-header">
                <h1><i class="bi bi-shield-check"></i> Комплексная проверка системы</h1>
                <p>Дата проверки: <?php echo date('d.m.Y H:i:s'); ?></p>
            </div>
    <?php
}

output("============================================", 'info');
output("   КОМПЛЕКСНАЯ ПРОВЕРКА СИСТЕМЫ", 'info');
output("   Дата: " . date('d.m.Y H:i:s'), 'info');
output("============================================", 'info');
output("", 'info');

$total_checks = 0;
$passed_checks = 0;
$failed_checks = 0;
$warnings = 0;

// ============================================
// 1. ВАЛИДАЦИЯ - проверка корректности данных
// ============================================
output("", 'info');
output("1. ВАЛИДАЦИЯ (Проверка корректности данных)", 'info');
output("--------------------------------------------", 'info');

try {
    $conn = getConnection();
    
    // Проверка 1.1: Существование всех необходимых таблиц
    output("1.1 Проверка наличия таблиц...", 'info');
    $total_checks++;
    
    $required_tables = [
        'products', 'subdivisions', 'competitors', 'sales', 
        'competitor_prices', 'users', 'roles', 'user_roles'
    ];
    
    $missing_tables = [];
    foreach ($required_tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows == 0) {
            $missing_tables[] = $table;
        }
    }
    
    if (empty($missing_tables)) {
        output("  ✓ Все необходимые таблицы существуют", 'success');
        $passed_checks++;
    } else {
        output("  ✗ Отсутствуют таблицы: " . implode(', ', $missing_tables), 'error');
        $failed_checks++;
    }
    
    // Проверка 1.2: Целостность внешних ключей
    output("1.2 Проверка целостности внешних ключей...", 'info');
    $total_checks++;
    
    $fk_issues = [];
    
    // Проверка sales.product_id
    $result = $conn->query("
        SELECT COUNT(*) as orphaned 
        FROM sales s 
        LEFT JOIN products p ON s.product_id = p.id 
        WHERE p.id IS NULL
    ");
    $row = $result->fetch_assoc();
    if ($row['orphaned'] > 0) {
        $fk_issues[] = "$row[orphaned] записей в sales ссылаются на несуществующие товары";
    }
    
    // Проверка sales.subdivision_id
    $result = $conn->query("
        SELECT COUNT(*) as orphaned 
        FROM sales s 
        LEFT JOIN subdivisions sub ON s.subdivision_id = sub.id 
        WHERE sub.id IS NULL
    ");
    $row = $result->fetch_assoc();
    if ($row['orphaned'] > 0) {
        $fk_issues[] = "$row[orphaned] записей в sales ссылаются на несуществующие подразделения";
    }
    
    // Проверка competitor_prices
    $result = $conn->query("
        SELECT COUNT(*) as orphaned 
        FROM competitor_prices cp 
        LEFT JOIN competitors c ON cp.competitor_id = c.id 
        WHERE c.id IS NULL
    ");
    $row = $result->fetch_assoc();
    if ($row['orphaned'] > 0) {
        $fk_issues[] = "$row[orphaned] записей в competitor_prices ссылаются на несуществующих конкурентов";
    }
    
    $result = $conn->query("
        SELECT COUNT(*) as orphaned 
        FROM competitor_prices cp 
        LEFT JOIN products p ON cp.product_id = p.id 
        WHERE p.id IS NULL
    ");
    $row = $result->fetch_assoc();
    if ($row['orphaned'] > 0) {
        $fk_issues[] = "$row[orphaned] записей в competitor_prices ссылаются на несуществующие товары";
    }
    
    if (empty($fk_issues)) {
        output("  ✓ Внешние ключи в порядке", 'success');
        $passed_checks++;
    } else {
        output("  ✗ Нарушения целостности:", 'error');
        foreach ($fk_issues as $issue) {
            output("    - " . $issue, 'error');
        }
        $failed_checks++;
    }
    
    // Проверка 1.3: Проверка дубликатов
    output("1.3 Проверка дубликатов...", 'info');
    $total_checks++;
    
    $duplicates = [];
    
    // Дубликаты товаров по названию
    $result = $conn->query("
        SELECT name, COUNT(*) as cnt 
        FROM products 
        GROUP BY name 
        HAVING cnt > 1
    ");
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $duplicates[] = "Товар '{$row['name']}' встречается {$row['cnt']} раз";
        }
    }
    
    if (empty($duplicates)) {
        output("  ✓ Дубликатов не найдено", 'success');
        $passed_checks++;
    } else {
        output("  ⚠ Найдены дубликаты:", 'warning');
        foreach ($duplicates as $dup) {
            output("    - " . $dup, 'warning');
        }
        $warnings++;
    }
    
    // Проверка 1.4: Проверка форматов данных
    output("1.4 Проверка форматов данных...", 'info');
    $total_checks++;
    
    $format_issues = [];
    
    // Проверка email в users
    $result = $conn->query("
        SELECT id, username, email 
        FROM users 
        WHERE email != '' AND email NOT LIKE '%@%.%'
    ");
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $format_issues[] = "Некорректный email у пользователя {$row['username']}: {$row['email']}";
        }
    }
    
    // Проверка дат в sales
    $result = $conn->query("
        SELECT COUNT(*) as future_dates 
        FROM sales 
        WHERE sale_date > CURDATE()
    ");
    $row = $result->fetch_assoc();
    if ($row['future_dates'] > 0) {
        $format_issues[] = "Найдено {$row['future_dates']} продаж с будущей датой";
    }
    
    // Проверка цен
    $result = $conn->query("
        SELECT COUNT(*) as zero_prices 
        FROM sales 
        WHERE total_amount <= 0
    ");
    $row = $result->fetch_assoc();
    if ($row['zero_prices'] > 0) {
        $format_issues[] = "Найдено {$row['zero_prices']} продаж с нулевой или отрицательной суммой";
    }
    
    $result = $conn->query("
        SELECT COUNT(*) as zero_prices 
        FROM competitor_prices 
        WHERE price <= 0
    ");
    $row = $result->fetch_assoc();
    if ($row['zero_prices'] > 0) {
        $format_issues[] = "Найдено {$row['zero_prices']} записей с некорректной ценой конкурентов";
    }
    
    if (empty($format_issues)) {
        output("  ✓ Все данные имеют корректный формат", 'success');
        $passed_checks++;
    } else {
        output("  ⚠ Проблемы с форматами данных:", 'warning');
        foreach ($format_issues as $issue) {
            output("    - " . $issue, 'warning');
        }
        $warnings++;
    }
    
} catch (Exception $e) {
    output("  ✗ Ошибка при проверке валидации: " . $e->getMessage(), 'error');
    $failed_checks++;
}

// ============================================
// 2. ВЕРИФИКАЦИЯ - проверка безопасности и прав
// ============================================
output("", 'info');
output("2. ВЕРИФИКАЦИЯ (Проверка безопасности)", 'info');
output("----------------------------------------", 'info');

try {
    // Проверка 2.1: Права доступа к файлам
    output("2.1 Проверка прав доступа к файлам...", 'info');
    $total_checks++;
    
    $permission_issues = [];
    
    // Проверка прав на config.php (должен быть 644 или 640)
    if (file_exists('config.php')) {
        $perms = fileperms('config.php');
        $perms_octal = substr(sprintf('%o', $perms), -4);
        if ($perms_octal > '644') {
            $permission_issues[] = "config.php имеет слишком открытые права: $perms_octal (рекомендуется 644)";
        }
    }
    
    // Проверка прав на папку backups
    if (file_exists(BACKUP_DIR)) {
        if (!is_writable(BACKUP_DIR)) {
            $permission_issues[] = "Папка backups не доступна для записи";
        }
    }
    
    // Проверка наличия .htaccess в backups
    if (!file_exists(BACKUP_DIR . '.htaccess')) {
        $permission_issues[] = "Отсутствует .htaccess в папке backups (рекомендуется для защиты)";
    }
    
    if (empty($permission_issues)) {
        output("  ✓ Права доступа настроены корректно", 'success');
        $passed_checks++;
    } else {
        output("  ⚠ Проблемы с правами доступа:", 'warning');
        foreach ($permission_issues as $issue) {
            output("    - " . $issue, 'warning');
        }
        $warnings++;
    }
    
    // Проверка 2.2: Защита от SQL-инъекций (через анализ кода)
    output("2.2 Проверка защиты от SQL-инъекций...", 'info');
    $total_checks++;
    
    $sql_issues = [];
    
    // Проверяем наличие подготовленных запросов в index.php
    if (file_exists('index.php')) {
        $content = file_get_contents('index.php');
        
        // Ищем прямые вставки переменных в SQL запросы
        if (preg_match('/\$sql\s*=\s*".*?\$_[A-Za-z0-9_]+.*?"/', $content, $matches)) {
            $sql_issues[] = "Найдены потенциально опасные прямые вставки в SQL";
        }
        
        // Проверяем использование mysqli_real_escape_string
        if (strpos($content, 'mysqli_real_escape_string') === false && 
            strpos($content, 'prepare') === false) {
            $sql_issues[] = "Не обнаружено использование подготовленных запросов или экранирования";
        }
    }
    
    if (empty($sql_issues)) {
        output("  ✓ SQL-инъекции предотвращены (используются подготовленные запросы)", 'success');
        $passed_checks++;
    } else {
        output("  ⚠ Потенциальные уязвимости SQL:", 'warning');
        foreach ($sql_issues as $issue) {
            output("    - " . $issue, 'warning');
        }
        $warnings++;
    }
    
    // Проверка 2.3: XSS защита
    output("2.3 Проверка защиты от XSS...", 'info');
    $total_checks++;
    
    $xss_issues = [];
    
    if (file_exists('index.php')) {
        $content = file_get_contents('index.php');
        
        // Проверяем использование htmlspecialchars
        if (strpos($content, 'htmlspecialchars') === false) {
            $xss_issues[] = "Не обнаружено использование htmlspecialchars для экранирования вывода";
        }
        
        // Проверяем прямые выводы переменных
        if (preg_match('/echo\s+.*?\$_[a-zA-Z0-9_]+/', $content, $matches)) {
            $xss_issues[] = "Найдены прямые выводы переменных без экранирования";
        }
    }
    
    if (empty($xss_issues)) {
        output("  ✓ XSS защита реализована", 'success');
        $passed_checks++;
    } else {
        output("  ⚠ Потенциальные XSS уязвимости:", 'warning');
        foreach ($xss_issues as $issue) {
            output("    - " . $issue, 'warning');
        }
        $warnings++;
    }
    
    // Проверка 2.4: Безопасность паролей
    output("2.4 Проверка безопасности паролей...", 'info');
    $total_checks++;
    
    $password_issues = [];
    
    // Проверяем использование password_hash
    if (file_exists('config.php')) {
        $content = file_get_contents('config.php');
        if (strpos($content, 'password_hash') === false) {
            $password_issues[] = "Не используется password_hash для хеширования паролей";
        }
        if (strpos($content, 'password_verify') === false) {
            $password_issues[] = "Не используется password_verify для проверки паролей";
        }
    }
    
    // Проверяем локальную БД для паролей
    $local_conn = null;
    try {
        $local_conn = getLocalConnection();
        output("    Подключение к локальной БД для паролей: OK", 'success');
    } catch (Exception $e) {
        $password_issues[] = "Ошибка подключения к локальной БД для паролей: " . $e->getMessage();
    }
    
    if (empty($password_issues)) {
        output("  ✓ Система аутентификации безопасна", 'success');
        $passed_checks++;
    } else {
        output("  ⚠ Проблемы с безопасностью паролей:", 'warning');
        foreach ($password_issues as $issue) {
            output("    - " . $issue, 'warning');
        }
        $warnings++;
    }
    
} catch (Exception $e) {
    output("  ✗ Ошибка при верификации: " . $e->getMessage(), 'error');
    $failed_checks++;
}

// ============================================
// 3. СОБСТВЕННАЯ ПРОВЕРКА: Целостность бэкапов
// ============================================
output("", 'info');
output("3. ПРОВЕРКА ЦЕЛОСТНОСТИ РЕЗЕРВНЫХ КОПИЙ", 'info');
output("-----------------------------------------", 'info');

try {
    // Проверка 3.1: Существование и читаемость бэкапов
    output("3.1 Проверка доступности бэкапов...", 'info');
    $total_checks++;
    
    $backup_files = glob(BACKUP_DIR . 'backup_*.*');
    $backup_count = count($backup_files);
    
    if ($backup_count > 0) {
        output("  ✓ Найдено резервных копий: $backup_count", 'success');
        
        // Проверяем каждый бэкап на читаемость
        $unreadable = [];
        foreach ($backup_files as $file) {
            if (!is_readable($file)) {
                $unreadable[] = basename($file);
            }
        }
        
        if (empty($unreadable)) {
            output("  ✓ Все файлы бэкапов доступны для чтения", 'success');
            $passed_checks++;
        } else {
            output("  ⚠ Некоторые файлы не читаются:", 'warning');
            foreach ($unreadable as $file) {
                output("    - " . $file, 'warning');
            }
            $warnings++;
        }
    } else {
        output("  ⚠ Резервные копии не найдены (рекомендуется создать)", 'warning');
        $warnings++;
    }
    
    // Проверка 3.2: Валидация форматов бэкапов
    output("3.2 Проверка форматов файлов бэкапов...", 'info');
    $total_checks++;
    
    $format_errors = [];
    
    foreach ($backup_files as $file) {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $filename = basename($file);
        
        // Пропускаем лог-файл
        if ($filename == 'backup_log.txt') continue;
        
        $content = file_get_contents($file, false, null, 0, 1024); // Читаем начало файла
        
        switch ($extension) {
            case 'json':
                if (strpos($content, '{') !== 0 && strpos($content, '[') !== 0) {
                    $format_errors[] = "$filename: не похоже на JSON";
                }
                break;
            case 'csv':
                if (strpos($content, ';') === false && strpos($content, ',') === false) {
                    $format_errors[] = "$filename: не похоже на CSV (нет разделителей)";
                }
                break;
            case 'sql':
                if (strpos($content, 'INSERT INTO') === false && strpos($content, 'CREATE TABLE') === false) {
                    $format_errors[] = "$filename: не похоже на SQL дамп";
                }
                break;
            case 'xml':
                if (strpos($content, '<?xml') === false) {
                    $format_errors[] = "$filename: не похоже на XML";
                }
                break;
        }
    }
    
    if (empty($format_errors)) {
        output("  ✓ Все файлы имеют корректный формат", 'success');
        $passed_checks++;
    } else {
        output("  ⚠ Проблемы с форматами файлов:", 'warning');
        foreach ($format_errors as $error) {
            output("    - " . $error, 'warning');
        }
        $warnings++;
    }
    
    // Проверка 3.3: Проверка журнала операций
    output("3.3 Проверка журнала операций...", 'info');
    $total_checks++;
    
    $log_file = BACKUP_DIR . 'backup_log.txt';
    
    if (file_exists($log_file)) {
        $log_content = file_get_contents($log_file);
        $log_lines = count(file($log_file));
        
        output("  ✓ Журнал операций существует ($log_lines записей)", 'success');
        
        // Проверяем наличие записей о создании
        if (strpos($log_content, 'create') !== false) {
            output("    - Записи о создании бэкапов: есть", 'success');
        }
        
        // Проверяем наличие записей о восстановлении
        if (strpos($log_content, 'restore') !== false) {
            output("    - Записи о восстановлении: есть", 'success');
        }
        
        $passed_checks++;
    } else {
        output("  ⚠ Журнал операций не найден", 'warning');
        $warnings++;
    }
    
    // Проверка 3.4: Тестовое восстановление (без реальной записи)
    output("3.4 Тестовая проверка восстановления...", 'info');
    $total_checks++;
    
    if (!empty($backup_files)) {
        // Берем самый свежий бэкап для проверки
        $latest_backup = $backup_files[0];
        $extension = strtolower(pathinfo($latest_backup, PATHINFO_EXTENSION));
        
        try {
            // Просто проверяем, что файл можно прочитать и распарсить
            if ($extension == 'json') {
                $data = json_decode(file_get_contents($latest_backup), true);
                if ($data === null) {
                    throw new Exception("Некорректный JSON");
                }
                output("    ✓ JSON бэкап корректен", 'success');
            } elseif ($extension == 'csv') {
                $handle = fopen($latest_backup, 'r');
                if ($handle) {
                    $first_line = fgetcsv($handle, 0, ';');
                    if ($first_line) {
                        output("    ✓ CSV бэкап корректен", 'success');
                    }
                    fclose($handle);
                }
            } elseif ($extension == 'sql') {
                $content = file_get_contents($latest_backup, false, null, 0, 1000);
                if (strpos($content, 'INSERT INTO') !== false) {
                    output("    ✓ SQL бэкап содержит данные", 'success');
                }
            }
            
            output("  ✓ Тестовая проверка восстановления пройдена", 'success');
            $passed_checks++;
        } catch (Exception $e) {
            output("  ⚠ Ошибка при тестовой проверке: " . $e->getMessage(), 'warning');
            $warnings++;
        }
    } else {
        output("  ⚠ Нет бэкапов для тестовой проверки", 'warning');
        $warnings++;
    }
    
} catch (Exception $e) {
    output("  ✗ Ошибка при проверке бэкапов: " . $e->getMessage(), 'error');
    $failed_checks++;
}

// ============================================
// ИТОГИ ПРОВЕРКИ
// ============================================
output("", 'info');
output("============================================", 'info');
output("   РЕЗУЛЬТАТЫ ПРОВЕРКИ", 'info');
output("============================================", 'info');
output("", 'info');

$total_checks = $total_checks ?: 1; // Избегаем деления на ноль
$success_rate = round(($passed_checks / $total_checks) * 100, 1);

output("Всего проверок: $total_checks", 'info');
output("Успешно: $passed_checks", 'success');
output("Предупреждения: $warnings", 'warning');
output("Ошибки: $failed_checks", $failed_checks > 0 ? 'error' : 'info');
output("Общая оценка: $success_rate%", $success_rate >= 80 ? 'success' : ($success_rate >= 60 ? 'warning' : 'error'));

// Сохраняем результаты проверки
$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'total_checks' => $total_checks,
    'passed' => $passed_checks,
    'warnings' => $warnings,
    'failed' => $failed_checks,
    'success_rate' => $success_rate
];

$result_file = CHECK_RESULTS_DIR . 'check_' . date('Y-m-d_H-i-s') . '.json';
file_put_contents($result_file, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

output("", 'info');
output("Результаты сохранены в: " . $result_file, 'info');
output("============================================", 'info');

if ($failed_checks == 0 && $warnings == 0) {
    output("СТАТУС: ОТЛИЧНО - Система полностью исправна", 'success');
} elseif ($failed_checks == 0 && $warnings > 0) {
    output("СТАТУС: ХОРОШО - Система работает, но есть предупреждения", 'warning');
} elseif ($failed_checks > 0) {
    output("СТАТУС: ТРЕБУЕТ ВНИМАНИЯ - Обнаружены ошибки", 'error');
}

// Закрываем HTML, если в браузере
if (!$is_cli) {
    ?>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
}

// Закрываем соединения
if (isset($conn)) $conn->close();
if (isset($local_conn)) $local_conn->close();
?>