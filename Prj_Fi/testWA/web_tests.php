<?php
/**
 * Automated Web Application Testing Suite
 * Скрипт для автоматического тестирования веб-приложения с использованием Selenium
 *
 * Требования:
 * - Установленный Selenium Standalone Server или Selenium Grid
 * - Запущенный веб-сервер с приложением
 * - PHP WebDriver библиотека (php-webdriver)
 *
 * Установка зависимостей:
 *   composer install
 *
 * Запуск Selenium Server:
 *   java -jar selenium-server-standalone.jar
 *
 * Запуск тестов:
 *   php testWA.php
 */

// Включаем отображение ошибок
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Загружаем Composer autoload
$autoload_paths = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php'
];

$autoload_loaded = false;
foreach ($autoload_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $autoload_loaded = true;
        break;
    }
}

if (!$autoload_loaded) {
    echo "⚠ Ошибка: Composer autoload не найден.\n";
    echo "Установите зависимости через Composer:\n";
    echo "  composer install\n\n";
    exit(1);
}

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverWait;
use Facebook\WebDriver\Exception\WebDriverException;
use Facebook\WebDriver\Firefox\FirefoxOptions;
use Facebook\WebDriver\Chrome\ChromeOptions;

// =====================================================
// КОНФИГУРАЦИЯ
// =====================================================

// URL приложения (измените на ваш)
$base_url = getenv('APP_URL') ?: 'http://localhost:3000';

// Selenium Server URL
$selenium_host = getenv('SELENIUM_HOST') ?: 'http://localhost:4444/wd/hub';

// Браузер для тестирования (chrome, firefox)
$browser = getenv('BROWSER') ?: 'firefox';

// Таймауты
$implicit_wait = 10; // секунд
$page_load_timeout = 30; // секунд

// Учётные данные для тестирования
$test_users = [
    'admin' => [
        'username' => 'admin',
        'password' => 'admin123',
        'role' => 'admin'
    ],
    'user' => [
        'username' => 'user',
        'password' => 'user',
        'role' => 'user'
    ]
];

// =====================================================
// ИНИЦИАЛИЗАЦИЯ ОТЧЁТА
// =====================================================

$report = [
    'start_time' => microtime(true),
    'tests' => [],
    'errors' => [],
    'warnings' => [],
    'screenshots' => []
];

echo "========================================\n";
echo "  Тестирование веб-приложения\n";
echo "  Система исследования товарного рынка\n";
echo "========================================\n\n";

echo "Конфигурация:\n";
echo "  URL приложения: $base_url\n";
echo "  Selenium Server: $selenium_host\n";
echo "  Браузер: $browser\n\n";

// =====================================================
// ФУНКЦИИ ОТЧЁТА
// =====================================================

function addTestResult(&$report, $test_name, $passed, $message = '', $details = null) {
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

function addWarning(&$report, $message, $details = null) {
    $report['warnings'][] = [
        'message' => $message,
        'details' => $details,
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

function saveScreenshot(&$report, $driver, $test_name) {
    try {
        $screenshot_dir = __DIR__ . '/screenshots';
        if (!is_dir($screenshot_dir)) {
            mkdir($screenshot_dir, 0755, true);
        }

        $filename = $screenshot_dir . '/' . date('Ymd_His') . '_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $test_name) . '.png';
        $driver->takeScreenshot($filename);
        $report['screenshots'][] = $filename;
        return $filename;
    } catch (Exception $e) {
        return null;
    }
}

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

    $md = "# Отчёт тестирования веб-приложения\n\n";
    $md .= "**Дата и время тестирования:** " . date('Y-m-d H:i:s') . "\n\n";
    $md .= "**Время выполнения:** {$report['execution_time_ms']} мс\n\n";
    $md .= "**Конфигурация:**\n";
    $md .= "- URL приложения: " . (getenv('APP_URL') ?: 'http://localhost:3000') . "\n";
    $md .= "- Selenium Server: " . (getenv('SELENIUM_HOST') ?: 'http://localhost:4444/wd/hub') . "\n";
    $md .= "- Браузер: " . (getenv('BROWSER') ?: 'firefox') . "\n\n";

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

    if (!empty($report['screenshots'])) {
        $md .= "\n## Скриншоты\n\n";
        foreach ($report['screenshots'] as $screenshot) {
            $filename = basename($screenshot);
            $md .= "- 📸 `$filename`\n";
        }
    }

    $md .= "\n---\n*Отчёт сгенерирован автоматически системой тестирования веб-приложений*";

    file_put_contents(__DIR__ . '/test_report.md', $md);
}

// =====================================================
// ИНИЦИАЛИЗАЦИЯ WEBDRIVER
// =====================================================

echo "1. ИНИЦИАЛИЗАЦИЯ WEBDRIVER\n";
echo "----------------------------------------\n";

$driver = null;
try {
    // Определяем capabilities для браузера
    switch ($browser) {
        case 'firefox':
            $capabilities = DesiredCapabilities::firefox();
            // Добавляем опции для headless режима Firefox
            if (php_sapi_name() === 'cli') {
                $firefoxOptions = new FirefoxOptions();
                $firefoxOptions->addArguments(['-headless']);
                $capabilities->setCapability('firefoxOptions', $firefoxOptions);
            }
            break;
        case 'chrome':
        default:
            $capabilities = DesiredCapabilities::chrome();
            // Добавляем опции для headless режима Chrome
            if (php_sapi_name() === 'cli') {
                $chromeOptions = new ChromeOptions();
                $chromeOptions->addArguments(['--headless', '--no-sandbox', '--disable-gpu']);
                $capabilities->setCapability('chromeOptions', $chromeOptions);
            }
            break;
    }

    echo "Подключение к Selenium Server...\n";
    $driver = RemoteWebDriver::create($selenium_host, $capabilities);
    $driver->manage()->timeouts()->implicitlyWait($implicit_wait);
    $driver->manage()->timeouts()->pageLoadTimeout($page_load_timeout);

    echo "✓ WebDriver инициализирован\n";
    echo "  Браузер: " . $driver->getCapabilities()->getBrowserName() . "\n";
    addTestResult($report, 'Инициализация WebDriver', true, 'Успешно');

} catch (WebDriverException $e) {
    echo "✗ Ошибка WebDriver: " . $e->getMessage() . "\n";
    addTestResult($report, 'Инициализация WebDriver', false, $e->getMessage());
    generateMarkdownReport($report);
    exit(1);
} catch (Exception $e) {
    echo "✗ Ошибка инициализации WebDriver: " . $e->getMessage() . "\n";
    addTestResult($report, 'Инициализация WebDriver', false, $e->getMessage());
    generateMarkdownReport($report);
    exit(1);
}

// =====================================================
// ТЕСТ 1: ПРОВЕРКА ГЛАВНОЙ СТРАНИЦЫ
// =====================================================

echo "\n2. ПРОВЕРКА ГЛАВНОЙ СТРАНИЦЫ\n";
echo "----------------------------------------\n";

try {
    echo "Открываем главную страницу...\n";
    $driver->get($base_url . '/index.php?page=home');

    $wait = new WebDriverWait($driver, $implicit_wait);
    $wait->until(
        WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::tagName('body'))
    );

    $title = $driver->getTitle();
    echo "  Заголовок страницы: $title\n";

    // Проверяем, не перенаправило ли на login
    $currentUrl = $driver->getCurrentURL();
    if (strpos($currentUrl, 'login') !== false) {
        echo "  ℹ Страница требует авторизации, перенаправлено на login\n";
        addTestResult($report, 'Главная страница', true, "Требуется авторизация (ожидаемо)");
    } else {
        // Проверяем наличие основных элементов
        $navbarElements = $driver->findElements(WebDriverBy::className('navbar'));
        $containerElements = $driver->findElements(WebDriverBy::className('container'));
        $hasNavbar = is_countable($navbarElements) && count($navbarElements) > 0;
        $hasContent = is_countable($containerElements) && count($containerElements) > 0;

        if ($hasNavbar && $hasContent) {
            echo "✓ Главная страница загружена корректно\n";
            addTestResult($report, 'Главная страница', true, "Заголовок: $title");
        } else {
            echo "✗ Главная страница не содержит ожидаемых элементов\n";
            addTestResult($report, 'Главная страница', false, 'Отсутствуют navbar или container');
            saveScreenshot($report, $driver, 'Главная страница');
        }
    }

} catch (WebDriverException $e) {
    echo "✗ Ошибка WebDriver: " . $e->getMessage() . "\n";
    addTestResult($report, 'Главная страница', false, $e->getMessage());
    saveScreenshot($report, $driver, 'Главная страница ошибка');
} catch (Exception $e) {
    echo "✗ Ошибка при загрузке главной страницы: " . $e->getMessage() . "\n";
    addTestResult($report, 'Главная страница', false, $e->getMessage());
    saveScreenshot($report, $driver, 'Главная страница ошибка');
}

// =====================================================
// ТЕСТ 2: ПРОВЕРКА ФОРМЫ ВХОДА
// =====================================================

echo "\n3. ПРОВЕРКА ФОРМЫ ВХОДА\n";
echo "----------------------------------------\n";

try {
    echo "Открываем страницу входа...\n";
    $driver->get($base_url . '/index.php?page=login');

    $wait = new WebDriverWait($driver, $implicit_wait);

    // Проверяем наличие полей формы
    $usernameField = $driver->findElement(WebDriverBy::name('username'));
    $passwordField = $driver->findElement(WebDriverBy::name('password'));

    if ($usernameField && $passwordField) {
        echo "✓ Форма входа содержит необходимые поля\n";
        addTestResult($report, 'Форма входа: поля', true, 'username и password присутствуют');
    } else {
        echo "✗ Форма входа не содержит необходимых полей\n";
        addTestResult($report, 'Форма входа: поля', false, 'Отсутствуют поля');
    }

    // Проверяем наличие кнопки входа
    $submitButtons = $driver->findElements(WebDriverBy::cssSelector('button[type="submit"]'));
    if (is_countable($submitButtons) && count($submitButtons) > 0) {
        echo "✓ Кнопка входа присутствует\n";
        addTestResult($report, 'Форма входа: кнопка', true, 'Кнопка submit найдена');
    } else {
        echo "✗ Кнопка входа отсутствует\n";
        addTestResult($report, 'Форма входа: кнопка', false, 'Кнопка submit не найдена');
    }

} catch (WebDriverException $e) {
    echo "✗ Ошибка WebDriver при проверке формы входа: " . $e->getMessage() . "\n";
    addTestResult($report, 'Форма входа', false, $e->getMessage());
    saveScreenshot($report, $driver, 'Форма входа ошибка');
} catch (Exception $e) {
    echo "✗ Ошибка при проверке формы входа: " . $e->getMessage() . "\n";
    addTestResult($report, 'Форма входа', false, $e->getMessage());
    saveScreenshot($report, $driver, 'Форма входа ошибка');
}

// =====================================================
// ТЕСТ 3: АВТОРИЗАЦИЯ АДМИНИСТРАТОРА
// =====================================================

echo "\n4. АВТОРИЗАЦИЯ АДМИНИСТРАТОРА\n";
echo "----------------------------------------\n";

$adminCredentials = $test_users['admin'];

try {
    echo "Выполняем вход как администратор...\n";
    $driver->get($base_url . '/index.php?page=login');

    $wait = new WebDriverWait($driver, $implicit_wait);

    $usernameField = $driver->findElement(WebDriverBy::name('username'));
    $passwordField = $driver->findElement(WebDriverBy::name('password'));

    $usernameField->clear();
    $usernameField->sendKeys($adminCredentials['username']);

    $passwordField->clear();
    $passwordField->sendKeys($adminCredentials['password']);

    $submitBtn = $driver->findElement(WebDriverBy::cssSelector('button[type="submit"]'));
    $submitBtn->click();

    // Ждём перенаправления
    sleep(2);

    // Проверяем успешность входа
    $currentUrl = $driver->getCurrentURL();
    $pageSource = $driver->getPageSource();

    if (strpos($pageSource, 'Выход') !== false || strpos($pageSource, 'logout') !== false) {
        echo "✓ Авторизация администратора успешна\n";
        addTestResult($report, 'Авторизация администратора', true, 'Вход выполнен');
    } else {
        echo "⚠ Возможно, авторизация не удалась. Текущий URL: $currentUrl\n";
        addTestResult($report, 'Авторизация администратора', false, 'Не найдены признаки успешного входа');
        saveScreenshot($report, $driver, 'Авторизация ошибка');
    }

} catch (WebDriverException $e) {
    echo "✗ Ошибка WebDriver при авторизации: " . $e->getMessage() . "\n";
    addTestResult($report, 'Авторизация администратора', false, $e->getMessage());
    saveScreenshot($report, $driver, 'Авторизация ошибка');
} catch (Exception $e) {
    echo "✗ Ошибка при авторизации: " . $e->getMessage() . "\n";
    addTestResult($report, 'Авторизация администратора', false, $e->getMessage());
    saveScreenshot($report, $driver, 'Авторизация ошибка');
}

// =====================================================
// ТЕСТ 3: ПРОВЕРКА НАВИГАЦИИ
// =====================================================

echo "\n4. ПРОВЕРКА НАВИГАЦИИ\n";
echo "----------------------------------------\n";

$nav_links = [
    'Товары' => 'index.php?page=products',
    'Продажи' => 'index.php?page=sales',
    'Конкуренты' => 'index.php?page=competitors',
    'Отчеты' => 'index.php?page=reports',
    'Аналитика' => 'index.php?page=analytics'
];

foreach ($nav_links as $name => $url) {
    try {
        echo "Проверка ссылки '$name'...\n";
        $driver->get($base_url . '/' . $url);
        
        $wait = new WebDriverWait($driver, 5);
        $wait->until(
            WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::tagName('body'))
        );
        
        $currentUrl = $driver->getCurrentURL();
        if (strpos($currentUrl, $url) !== false) {
            echo "  ✓ Ссылка '$name' работает\n";
            addTestResult($report, "Навигация: $name", true, "URL: $url");
        } else {
            echo "  ⚠ Ссылка '$name' перенаправляет на: $currentUrl\n";
            addWarning($report, "Навигация: $name", ['expected' => $url, 'actual' => $currentUrl]);
        }
        
    } catch (WebDriverException $e) {
        echo "  ✗ Ошибка WebDriver при переходе по ссылке '$name': " . $e->getMessage() . "\n";
        addTestResult($report, "Навигация: $name", false, $e->getMessage());
    } catch (Exception $e) {
        echo "  ✗ Ошибка при переходе по ссылке '$name': " . $e->getMessage() . "\n";
        addTestResult($report, "Навигация: $name", false, $e->getMessage());
    }
}

// =====================================================
// ТЕСТ 5: ПРОВЕРКА ДОСТУПА АДМИНА
// =====================================================

echo "\n6. ПРОВЕРКА ДОСТУПА АДМИНА\n";
echo "----------------------------------------\n";

$admin_pages = [
    'Пользователи' => 'index.php?page=admin_users',
    'Бэкап' => 'index.php?page=backup'
];

foreach ($admin_pages as $name => $url) {
    try {
        echo "Проверка доступа к '$name'...\n";
        $driver->get($base_url . '/' . $url);
        
        $wait = new WebDriverWait($driver, 5);
        $wait->until(
            WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::tagName('body'))
        );
        
        $pageSource = $driver->getPageSource();
        
        // Проверяем, нет ли сообщения об ошибке доступа
        if (strpos($pageSource, 'Доступ запрещён') === false && 
            strpos($pageSource, 'Unauthorized') === false &&
            strpos($pageSource, 'access_denied') === false) {
            echo "  ✓ Доступ к '$name' разрешён\n";
            addTestResult($report, "Доступ админа: $name", true, 'Доступ разрешён');
        } else {
            echo "  ✗ Доступ к '$name' запрещён\n";
            addTestResult($report, "Доступ админа: $name", false, 'Доступ запрещён');
        }
        
    } catch (WebDriverException $e) {
        echo "  ✗ Ошибка WebDriver при проверке доступа к '$name': " . $e->getMessage() . "\n";
        addTestResult($report, "Доступ админа: $name", false, $e->getMessage());
    } catch (Exception $e) {
        echo "  ✗ Ошибка при проверке доступа к '$name': " . $e->getMessage() . "\n";
        addTestResult($report, "Доступ админа: $name", false, $e->getMessage());
    }
}

// =====================================================
// ТЕСТ 6: ВЫХОД ИЗ СИСТЕМЫ
// =====================================================

echo "\n7. ВЫХОД ИЗ СИСТЕМЫ\n";
echo "----------------------------------------\n";

try {
    echo "Выполняем выход...\n";
    $driver->get($base_url . '/index.php?page=logout');
    
    sleep(1);
    
    $pageSource = $driver->getPageSource();
    
    if (strpos($pageSource, 'Вход') !== false || strpos($pageSource, 'login') !== false) {
        echo "✓ Выход выполнен успешно\n";
        addTestResult($report, 'Выход из системы', true, 'Сессия завершена');
    } else {
        echo "⚠ Возможно, выход не выполнен\n";
        addTestResult($report, 'Выход из системы', false, 'Не найдены признаки выхода');
    }
    
} catch (WebDriverException $e) {
    echo "✗ Ошибка WebDriver при выходе: " . $e->getMessage() . "\n";
    addTestResult($report, 'Выход из системы', false, $e->getMessage());
} catch (Exception $e) {
    echo "✗ Ошибка при выходе: " . $e->getMessage() . "\n";
    addTestResult($report, 'Выход из системы', false, $e->getMessage());
}

// =====================================================
// ТЕСТ 7: ПРОВЕРКА ОТВЕТОВ СЕРВЕРА
// =====================================================

echo "\n8. ПРОВЕРКА ОТВЕТОВ СЕРВЕРА\n";
echo "----------------------------------------\n";

$test_urls = [
    'Главная' => '/index.php?page=home',
    'Товары' => '/index.php?page=products',
    'Продажи' => '/index.php?page=sales',
];

foreach ($test_urls as $name => $url) {
    try {
        echo "Проверка ответа для '$name'...\n";
        $driver->get($base_url . $url);
        
        // Используем JavaScript для получения статуса
        $statusCode = $driver->executeScript('return performance.getEntriesByType("navigation")[0]?.responseStatus || 200;');
        
        if ($statusCode >= 200 && $statusCode < 400) {
            echo "  ✓ Статус ответа: $statusCode\n";
            addTestResult($report, "HTTP статус: $name", true, "Код: $statusCode");
        } else {
            echo "  ✗ Статус ответа: $statusCode\n";
            addTestResult($report, "HTTP статус: $name", false, "Код: $statusCode");
        }
        
    } catch (WebDriverException $e) {
        echo "  ✗ Ошибка WebDriver при проверке '$name': " . $e->getMessage() . "\n";
        addTestResult($report, "HTTP статус: $name", false, $e->getMessage());
    } catch (Exception $e) {
        echo "  ✗ Ошибка при проверке '$name': " . $e->getMessage() . "\n";
        addTestResult($report, "HTTP статус: $name", false, $e->getMessage());
    }
}

// =====================================================
// ЗАВЕРШЕНИЕ
// =====================================================

echo "\n========================================\n";
echo "  ЗАВЕРШЕНИЕ ТЕСТИРОВАНИЯ\n";
echo "========================================\n";

if ($driver) {
    echo "Закрытие WebDriver...\n";
    $driver->quit();
    echo "✓ WebDriver закрыт\n";
}

// =====================================================
// ИТОГОВЫЙ ОТЧЁТ
// =====================================================

echo "\n========================================\n";
echo "  ИТОГОВЫЙ ОТЧЁТ ТЕСТИРОВАНИЯ\n";
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

if (!empty($report['screenshots'])) {
    echo "\n📸 Скриншоты сохранены:\n";
    foreach ($report['screenshots'] as $screenshot) {
        echo "  - $screenshot\n";
    }
}

// Сохраняем отчёт
generateMarkdownReport($report);

echo "\n✓ Отчёт сохранён в: " . __DIR__ . "/test_report.md\n";
?>
