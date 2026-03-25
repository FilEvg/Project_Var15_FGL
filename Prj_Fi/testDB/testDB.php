<?php
/**
 * Automated Database Testing Suite
 * Скрипт для автоматического тестирования базы данных project_Filippov
 *
 * Запуск: php testDB.php
 * Или: открыть в браузере через веб-сервер
 */

// Включаем отображение ошибок
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Настройки отчета
$report = [];
$report['start_time'] = microtime(true);
$report['tests'] = [];
$report['errors'] = [];
$report['warnings'] = [];

// Определяем путь к корневой директории
$root_dir = dirname(__DIR__);
$config_file = $root_dir . '/config.php';

echo "========================================\n";
echo "  Тестирование базы данных\n";
echo "  Система исследования товарного рынка\n";
echo "========================================\n\n";

echo "Путь к config.php: $config_file\n";

// Проверяем существование config.php
if (!file_exists($config_file)) {
    echo "✗ Ошибка: Файл config.php не найден по пути: $config_file\n";
    echo "Текущая директория: " . __DIR__ . "\n";
    echo "Родительская директория: " . dirname(__DIR__) . "\n";
    
    // Пытаемся найти config.php
    $possible_paths = [
        __DIR__ . '/../config.php',
        __DIR__ . '/../../config.php',
        __DIR__ . '/config.php',
        dirname(__DIR__) . '/config.php'
    ];
    
    echo "\nПроверяем возможные пути:\n";
    foreach ($possible_paths as $path) {
        echo "  - $path: " . (file_exists($path) ? "НАЙДЕН" : "не найден") . "\n";
    }
    exit(1);
}

// Подключаем конфигурацию
require_once $config_file;

echo "✓ Файл config.php загружен\n";

// Функция для записи результатов теста
function addTestResult($test_name, $passed, $message = '', $details = null) {
    global $report;
    $report['tests'][] = [
        'name' => $test_name,
        'passed' => $passed,
        'message' => $message,
        'details' => $details,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    if (!$passed) {
        $report['errors'][] = $test_name;
    }
}

// Функция для добавления предупреждения
function addWarning($message, $details = null) {
    global $report;
    $report['warnings'][] = [
        'message' => $message,
        'details' => $details,
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

// Функция для безопасного выполнения запроса с обработкой ошибок
function safeQuery($conn, $sql, $error_message = '') {
    global $report;
    $result = $conn->query($sql);
    if ($result === false) {
        addWarning($error_message ?: 'Ошибка выполнения запроса', [
            'sql' => $sql,
            'error' => $conn->error
        ]);
        return false;
    }
    return $result;
}

// =====================================================
// 1. ПРОВЕРКА ПОДКЛЮЧЕНИЯ К БАЗЕ ДАННЫХ
// =====================================================
echo "\n1. ПРОВЕРКА ПОДКЛЮЧЕНИЯ К БАЗЕ ДАННЫХ\n";
echo "----------------------------------------\n";

try {
    echo "Пытаемся подключиться к БД...\n";
    $conn = getConnection();
    addTestResult('Подключение к БД', true, 'Успешное подключение к базе данных');
    echo "✓ Подключение к базе данных успешно\n";
    
    // Проверяем версию MySQL
    $version = $conn->server_info;
    echo "  Версия MySQL: $version\n";
    
} catch (Exception $e) {
    addTestResult('Подключение к БД', false, 'Ошибка подключения: ' . $e->getMessage());
    echo "✗ Ошибка подключения к базе данных: " . $e->getMessage() . "\n";

    // Сохраняем отчет даже при ошибке подключения
    generateMarkdownReport($report);
    exit(1);
}

// =====================================================
// 2. ПРОВЕРКА СУЩЕСТВОВАНИЯ ТАБЛИЦ
// =====================================================
echo "\n2. ПРОВЕРКА СУЩЕСТВОВАНИЯ ТАБЛИЦ\n";
echo "----------------------------------------\n";

$required_tables = [
    'products', 'sales', 'subdivisions', 'competitors', 
    'competitor_prices', 'alternative_products', 'users', 
    'roles', 'user_roles'
];

$missing_tables = [];
foreach ($required_tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "✓ Таблица '$table' существует\n";
        addTestResult("Таблица: $table", true, 'Таблица существует');
    } else {
        echo "✗ Таблица '$table' отсутствует\n";
        addTestResult("Таблица: $table", false, 'Таблица не найдена');
        $missing_tables[] = $table;
    }
}

// Если нет таблиц, дальнейшее тестирование бессмысленно
if (count($missing_tables) >= count($required_tables)) {
    echo "\n⚠ Нет ни одной таблицы. Возможно, база данных не инициализирована.\n";
    generateMarkdownReport($report);
    exit(0);
}

// =====================================================
// 3. ПРОВЕРКА СТРУКТУРЫ ТАБЛИЦ
// =====================================================
echo "\n3. ПРОВЕРКА СТРУКТУРЫ ТАБЛИЦ\n";
echo "----------------------------------------\n";

// Проверка структуры таблицы products
$expected_products_columns = ['id', 'name', 'internal_code', 'category', 'description', 'is_active'];
$result = $conn->query("DESCRIBE products");
if ($result && $result->num_rows > 0) {
    $actual_columns = [];
    while ($row = $result->fetch_assoc()) {
        $actual_columns[] = $row['Field'];
    }
    $missing = array_diff($expected_products_columns, $actual_columns);
    if (empty($missing)) {
        echo "✓ Структура таблицы 'products' корректна\n";
        addTestResult('Структура products', true, 'Все колонки присутствуют');
    } else {
        echo "✗ В таблице 'products' отсутствуют колонки: " . implode(', ', $missing) . "\n";
        addTestResult('Структура products', false, 'Отсутствуют колонки: ' . implode(', ', $missing));
    }
}

// Проверка структуры таблицы sales
$expected_sales_columns = ['id', 'subdivision_id', 'product_id', 'sale_date', 'quantity', 'total_amount'];
$result = $conn->query("DESCRIBE sales");
if ($result && $result->num_rows > 0) {
    $actual_columns = [];
    while ($row = $result->fetch_assoc()) {
        $actual_columns[] = $row['Field'];
    }
    $missing = array_diff($expected_sales_columns, $actual_columns);
    if (empty($missing)) {
        echo "✓ Структура таблицы 'sales' корректна\n";
        addTestResult('Структура sales', true, 'Все колонки присутствуют');
    } else {
        echo "✗ В таблице 'sales' отсутствуют колонки: " . implode(', ', $missing) . "\n";
        addTestResult('Структура sales', false, 'Отсутствуют колонки: ' . implode(', ', $missing));
    }
}

// =====================================================
// 4. ПРОВЕРКА ДАННЫХ
// =====================================================
echo "\n4. ПРОВЕРКА ДАННЫХ\n";
echo "----------------------------------------\n";

// Проверка наличия данных
$result = $conn->query("SELECT COUNT(*) as cnt FROM products");
if ($result) {
    $row = $result->fetch_assoc();
    $count = $row['cnt'];
    echo "✓ Товаров в БД: $count\n";
    if ($count == 0) {
        addWarning('В таблице products нет данных');
    } else {
        addTestResult('Данные products', true, "Найдено $count записей");
    }
}

$result = $conn->query("SELECT COUNT(*) as cnt FROM sales");
if ($result) {
    $row = $result->fetch_assoc();
    $count = $row['cnt'];
    echo "✓ Записей о продажах: $count\n";
    if ($count == 0) {
        addWarning('В таблице sales нет данных');
    } else {
        addTestResult('Данные sales', true, "Найдено $count записей");
    }
}

$result = $conn->query("SELECT COUNT(*) as cnt FROM competitor_prices");
if ($result) {
    $row = $result->fetch_assoc();
    $count = $row['cnt'];
    echo "✓ Записей о ценах конкурентов: $count\n";
    addTestResult('Данные competitor_prices', true, "Найдено $count записей");
}

// =====================================================
// 5. ПРОВЕРКА ВАЛИДНОСТИ ДАННЫХ
// =====================================================
echo "\n5. ПРОВЕРКА ВАЛИДНОСТИ ДАННЫХ\n";
echo "----------------------------------------\n";

// Проверка дат в будущем
$result = $conn->query("SELECT COUNT(*) as cnt FROM competitor_prices WHERE check_date > CURDATE()");
if ($result) {
    $row = $result->fetch_assoc();
    $future_dates = $row['cnt'];
    if ($future_dates > 0) {
        echo "⚠ Найдено $future_dates записей с датами в будущем\n";
        addWarning("Обнаружены записи с датами в будущем", [
            'count' => $future_dates,
            'table' => 'competitor_prices'
        ]);
    } else {
        echo "✓ Нет записей с датами в будущем\n";
    }
}

$result = $conn->query("SELECT COUNT(*) as cnt FROM sales WHERE sale_date > CURDATE()");
if ($result) {
    $row = $result->fetch_assoc();
    $future_sales = $row['cnt'];
    if ($future_sales > 0) {
        echo "⚠ Найдено $future_sales продаж с датами в будущем\n";
        addWarning("Обнаружены продажи с датами в будущем", [
            'count' => $future_sales,
            'table' => 'sales'
        ]);
    } else {
        echo "✓ Нет продаж с датами в будущем\n";
    }
}

// Проверка отрицательных цен
$result = $conn->query("SELECT COUNT(*) as cnt FROM competitor_prices WHERE price < 0");
if ($result) {
    $row = $result->fetch_assoc();
    $negative_prices = $row['cnt'];
    if ($negative_prices > 0) {
        echo "⚠ Найдено $negative_prices записей с отрицательными ценами\n";
        addWarning("Обнаружены отрицательные цены", [
            'count' => $negative_prices,
            'table' => 'competitor_prices'
        ]);
    } else {
        echo "✓ Нет отрицательных цен\n";
    }
}

// =====================================================
// 6. ТЕСТИРОВАНИЕ ЗАПРЕТА БУДУЩИХ ДАТ
// =====================================================
echo "\n6. ТЕСТИРОВАНИЕ ЗАПРЕТА БУДУЩИХ ДАТ\n";
echo "----------------------------------------\n";

// Проверяем существование триггеров, которые запрещают будущие даты
$date_triggers = [];
$result = $conn->query("SHOW TRIGGERS");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $trigger_name = $row['Trigger'];
        // Ищем триггеры, которые могут проверять даты
        if (strpos($trigger_name, 'before') !== false && 
            (strpos($trigger_name, 'insert') !== false || strpos($trigger_name, 'update') !== false)) {
            $date_triggers[] = $trigger_name;
        }
    }
}

if (count($date_triggers) > 0) {
    echo "✓ Найдены триггеры, которые могут проверять даты:\n";
    foreach ($date_triggers as $trigger) {
        echo "  - $trigger\n";
    }
    addTestResult('Триггеры проверки дат', true, 'Найдены триггеры: ' . implode(', ', $date_triggers));
} else {
    echo "⚠ Триггеры для проверки дат не найдены\n";
    addWarning("Триггеры для проверки дат не обнаружены");
}

// Проверяем работу запрета будущих дат на конкретном примере
echo "\nПроверка работы запрета будущих дат:\n";

// Тест 1: Попытка вставить будущую дату в competitor_prices
$future_date = date('Y-m-d', strtotime('+1 day'));
$conn->query("SET @current_user = 'test_user'");

echo "  Тест 1: Попытка добавить цену с датой {$future_date}...\n";

// Используем try-catch для обработки исключения
try {
    $test_sql = "INSERT INTO competitor_prices (competitor_id, product_id, check_date, price) VALUES (1, 1, '$future_date', 1000)";
    if ($conn->query($test_sql) === true) {
        echo "  ✗ Триггер before_competitor_prices_insert НЕ РАБОТАЕТ - запись добавлена!\n";
        addTestResult('Запрет будущих дат (competitor_prices)', false, 'Удалось вставить дату в будущем');
        // Удаляем тестовую запись
        $conn->query("DELETE FROM competitor_prices WHERE check_date = '$future_date' AND price = 1000");
    }
} catch (mysqli_sql_exception $e) {
    // Проверяем, что ошибка связана с датой в будущем
    $error_message = $e->getMessage();
    if (strpos($error_message, 'будущем') !== false || strpos($error_message, 'future') !== false) {
        echo "  ✓ Триггер before_competitor_prices_insert корректно блокирует будущие даты\n";
        echo "    Ошибка: " . $error_message . "\n";
        addTestResult('Запрет будущих дат (competitor_prices)', true, 'Триггер работает - блокирует будущие даты');
    } else {
        echo "  ⚠ Ошибка при вставке, но не связанная с датой: " . $error_message . "\n";
        addTestResult('Запрет будущих дат (competitor_prices)', false, 'Ошибка: ' . $error_message);
    }
}

// Тест 2: Проверка триггера before_sales_insert
$future_sale_date = date('Y-m-d', strtotime('+1 day'));
echo "\n  Тест 2: Попытка добавить продажу с датой {$future_sale_date}...\n";

// Сначала получаем среднюю цену товара, чтобы триггер не сработал из-за отклонения цены
$avg_price_result = $conn->query("SELECT AVG(total_amount/quantity) as avg_price FROM sales WHERE product_id = 1");
$avg_price = 1000; // значение по умолчанию
if ($avg_price_result && $row = $avg_price_result->fetch_assoc()) {
    $avg_price = $row['avg_price'] ?? 1000;
}
$test_total_amount = round($avg_price * 1, 2); // quantity = 1

try {
    $test_sql2 = "INSERT INTO sales (subdivision_id, product_id, sale_date, quantity, total_amount) VALUES (1, 1, '$future_sale_date', 1, $test_total_amount)";
    if ($conn->query($test_sql2) === true) {
        echo "  ✗ Триггер before_sales_insert НЕ РАБОТАЕТ - запись добавлена!\n";
        addTestResult('Запрет будущих дат (sales)', false, 'Удалось вставить дату в будущем');
        // Удаляем тестовую запись
        $conn->query("DELETE FROM sales WHERE sale_date = '$future_sale_date' AND quantity = 1");
    }
} catch (mysqli_sql_exception $e) {
    $error_message = $e->getMessage();
    if (strpos($error_message, 'будущем') !== false || strpos($error_message, 'future') !== false) {
        echo "  ✓ Триггер before_sales_insert корректно блокирует будущие даты\n";
        echo "    Ошибка: " . $error_message . "\n";
        addTestResult('Запрет будущих дат (sales)', true, 'Триггер работает - блокирует будущие даты');
    } else {
        echo "  ⚠ Ошибка при вставке, но не связанная с датой: " . $error_message . "\n";
        addTestResult('Запрет будущих дат (sales)', false, 'Ошибка: ' . $error_message);
    }
}

// Тест 3: Проверка, что можно добавить дату сегодня
$today = date('Y-m-d');
echo "\n  Тест 3: Попытка добавить цену с сегодняшней датой {$today}...\n";

try {
    $conn->query("SET @current_user = 'test_user'");
    $test_sql3 = "INSERT INTO competitor_prices (competitor_id, product_id, check_date, price, notes) VALUES (1, 1, '$today', 1000, 'Тестовая запись - можно удалить')";
    if ($conn->query($test_sql3) === true) {
        echo "  ✓ Сегодняшнюю дату добавить можно (как и должно быть)\n";
        addTestResult('Добавление сегодняшней даты', true, 'Сегодняшняя дата добавляется успешно');
        // Удаляем тестовую запись
        $conn->query("DELETE FROM competitor_prices WHERE notes = 'Тестовая запись - можно удалить'");
    } else {
        echo "  ✗ Не удалось добавить сегодняшнюю дату\n";
        addTestResult('Добавление сегодняшней даты', false, 'Ошибка: ' . $conn->error);
    }
} catch (mysqli_sql_exception $e) {
    echo "  ✗ Не удалось добавить сегодняшнюю дату: " . $e->getMessage() . "\n";
    addTestResult('Добавление сегодняшней даты', false, 'Ошибка: ' . $e->getMessage());
}

// =====================================================
// 7. ПРОВЕРКА ТАБЛИЦЫ ЛОГОВ
// =====================================================
echo "\n7. ПРОВЕРКА ТАБЛИЦЫ ЛОГОВ\n";
echo "----------------------------------------\n";

$log_table_exists = $conn->query("SHOW TABLES LIKE 'price_change_log'");
if ($log_table_exists && $log_table_exists->num_rows > 0) {
    $result = $conn->query("SELECT COUNT(*) as cnt FROM price_change_log");
    if ($result) {
        $row = $result->fetch_assoc();
        $log_count = $row['cnt'];
        echo "✓ Таблица price_change_log существует, записей: $log_count\n";
        addTestResult('Таблица логов', true, "Таблица существует, записей: $log_count");
    } else {
        echo "⚠ Таблица price_change_log существует, но не удалось получить количество записей\n";
        addTestResult('Таблица логов', true, "Таблица существует");
    }
} else {
    echo "✗ Таблица price_change_log отсутствует\n";
    addTestResult('Таблица логов', false, 'Таблица не найдена');
}

// =====================================================
// 8. ПРОВЕРКА РОЛЕЙ ПОЛЬЗОВАТЕЛЕЙ
// =====================================================
echo "\n8. ПРОВЕРКА РОЛЕЙ ПОЛЬЗОВАТЕЛЕЙ\n";
echo "----------------------------------------\n";

$result = $conn->query("SELECT COUNT(*) as cnt FROM users");
if ($result) {
    $row = $result->fetch_assoc();
    $users = $row['cnt'];
    echo "✓ Пользователей в системе: $users\n";
}

$result = $conn->query("SELECT COUNT(*) as cnt FROM roles");
if ($result) {
    $row = $result->fetch_assoc();
    $roles = $row['cnt'];
    echo "✓ Ролей в системе: $roles\n";
    
    $expected_roles = ['admin', 'user', 'guest'];
    $roles_list = [];
    $roles_result = $conn->query("SELECT name FROM roles");
    if ($roles_result) {
        while ($row = $roles_result->fetch_assoc()) {
            $roles_list[] = $row['name'];
        }
        foreach ($expected_roles as $role) {
            if (in_array($role, $roles_list)) {
                echo "  → Роль '$role' присутствует\n";
            } else {
                echo "  → Роль '$role' отсутствует\n";
                addWarning("Отсутствует роль: $role");
            }
        }
    }
}

// =====================================================
// 9. ИТОГОВЫЙ ОТЧЕТ
// =====================================================
echo "\n========================================\n";
echo "  ИТОГОВЫЙ ОТЧЕТ ТЕСТИРОВАНИЯ\n";
echo "========================================\n";

$total_tests = count($report['tests']);
$passed_tests = $total_tests - count($report['errors']);
$passed_percent = $total_tests > 0 ? ($passed_tests / $total_tests) * 100 : 0;

echo "\nВсего тестов: $total_tests\n";
echo "Успешно пройдено: $passed_tests\n";
echo "Ошибок: " . count($report['errors']) . "\n";
echo "Предупреждений: " . count($report['warnings']) . "\n";
echo "Успешность: " . round($passed_percent, 2) . "%\n";

$execution_time = round((microtime(true) - $report['start_time']) * 1000, 2);
echo "Время выполнения: {$execution_time} мс\n";

if (count($report['errors']) > 0) {
    echo "\n⚠ Список ошибок:\n";
    foreach ($report['errors'] as $error) {
        echo "  - $error\n";
    }
}

if (count($report['warnings']) > 0) {
    echo "\n⚠ Список предупреждений:\n";
    foreach ($report['warnings'] as $warning) {
        echo "  - " . $warning['message'] . "\n";
    }
}

// Сохраняем отчет в Markdown
generateMarkdownReport($report);

$conn->close();

echo "\n✓ Отчет сохранен в: " . __DIR__ . "/test_report.md\n";

// =====================================================
// Функция сохранения отчета
// =====================================================
function generateMarkdownReport($report) {
    $report['end_time'] = microtime(true);
    $report['execution_time_ms'] = round(($report['end_time'] - $report['start_time']) * 1000, 2);
    $report['summary'] = [
        'total_tests' => count($report['tests']),
        'passed_tests' => count($report['tests']) - count($report['errors']),
        'failed_tests' => count($report['errors']),
        'warnings' => count($report['warnings']),
        'success_rate' => count($report['tests']) > 0 ?
            round((count($report['tests']) - count($report['errors'])) / count($report['tests']) * 100, 2) : 0
    ];

    $md = "# Отчет тестирования базы данных\n\n";
    $md .= "**Дата и время тестирования:** " . date('Y-m-d H:i:s') . "\n\n";
    $md .= "**Время выполнения:** {$report['execution_time_ms']} мс\n\n";

    $md .= "## Результаты тестирования\n\n";
    $md .= "| Показатель | Значение |\n";
    $md .= "|------------|----------|\n";
    $md .= "| Всего тестов | {$report['summary']['total_tests']} |\n";
    $md .= "| Успешно пройдено | {$report['summary']['passed_tests']} |\n";
    $md .= "| Ошибок | {$report['summary']['failed_tests']} |\n";
    $md .= "| Предупреждений | {$report['summary']['warnings']} |\n";
    $md .= "| Успешность | {$report['summary']['success_rate']}% |\n\n";

    if (!empty($report['tests'])) {
        $md .= "## Детальные результаты\n\n";
        $md .= "| Тест | Результат | Сообщение |\n";
        $md .= "|------|-----------|-----------|\n";

        foreach ($report['tests'] as $test) {
            $status = $test['passed'] ? '✅ Успешно' : '❌ Ошибка';
            $md .= "| {$test['name']} | {$status} | {$test['message']} |\n";
        }
    }

    if (!empty($report['warnings'])) {
        $md .= "\n## Предупреждения\n\n";
        foreach ($report['warnings'] as $warning) {
            $md .= "- **{$warning['message']}**\n";
            if (!empty($warning['details'])) {
                $md .= "  - Подробности: `" . json_encode($warning['details'], JSON_UNESCAPED_UNICODE) . "`\n";
            }
        }
    }

    $md .= "\n---\n*Отчет сгенерирован автоматически системой тестирования*";

    file_put_contents(__DIR__ . '/test_report.md', $md);
}