<?php
// code_complexity_checker.php - Анализатор сложности кода с сохранением в MD
// Запустите: php code_complexity_checker.php или через браузер

// Определяем окружение
$is_cli = (php_sapi_name() === 'cli');

// Цвета для консоли
define('CLR_RESET', "\033[0m");
define('CLR_RED', "\033[31m");
define('CLR_GREEN', "\033[32m");
define('CLR_YELLOW', "\033[33m");
define('CLR_BLUE', "\033[34m");
define('CLR_MAGENTA', "\033[35m");
define('CLR_CYAN', "\033[36m");
define('CLR_BOLD', "\033[1m");

// Функция вывода
function out($text, $color = '') {
    global $is_cli;
    if ($is_cli) {
        echo $color . $text . CLR_RESET . "\n";
    } else {
        $class = '';
        switch ($color) {
            case CLR_RED: $class = 'danger'; break;
            case CLR_GREEN: $class = 'success'; break;
            case CLR_YELLOW: $class = 'warning'; break;
            case CLR_BLUE: $class = 'info'; break;
            case CLR_MAGENTA: $class = 'primary'; break;
            case CLR_CYAN: $class = 'info'; break;
            case CLR_BOLD: $class = 'dark'; break;
        }
        echo '<div class="alert alert-' . $class . '">' . htmlspecialchars($text) . '</div>';
    }
}

// HTML шапка для браузера
if (!$is_cli) {
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Анализатор сложности кода</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { padding: 20px; background: #f8f9fa; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
            .metric-card { background: white; border-radius: 10px; padding: 15px; margin-bottom: 15px; box-shadow: 0 2px 4px rgba(0,0,0,.1); }
            .metric-value { font-size: 24px; font-weight: bold; }
            .metric-name { color: #6c757d; font-size: 14px; }
            .progress { height: 10px; margin-top: 10px; }
            .function-list { max-height: 400px; overflow-y: auto; }
            .function-item { padding: 8px; border-bottom: 1px solid #eee; }
            .function-item:hover { background: #f8f9fa; }
            .complexity-badge { float: right; }
            .low { color: #28a745; }
            .medium { color: #ffc107; }
            .high { color: #dc3545; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1><i class="bi bi-code-square"></i> Анализатор сложности кода</h1>
                <p>Метрики: цикломатическая сложность, вложенность, длина функций, Холстед</p>
            </div>
    <?php
}

out("==============================================", CLR_BOLD);
out("   АНАЛИЗАТОР СЛОЖНОСТИ КОДА", CLR_BOLD);
out("==============================================", CLR_BOLD);
out("");

// Файлы для анализа
$files_to_analyze = [
    'index.php' => 'Основной контроллер',
    'config.php' => 'Конфигурация и авторизация',
    'backup_functions.php' => 'Функции резервного копирования'
];

$total_stats = [
    'files' => 0,
    'lines' => 0,
    'functions' => 0,
    'complexity' => 0,
    'max_nesting' => 0,
    'halstead_volume' => 0
];

// ============================================
// МЕТРИКИ СЛОЖНОСТИ
// ============================================

/**
 * Анализ цикломатической сложности функции
 */
function calculateCyclomaticComplexity($code) {
    $score = 1; // Базовая сложность
    
    // Условия
    $score += substr_count($code, 'if ');
    $score += substr_count($code, 'elseif ');
    $score += substr_count($code, 'else if ');
    $score += substr_count($code, 'case ');
    $score += substr_count($code, 'default:');
    
    // Циклы
    $score += substr_count($code, 'for ');
    $score += substr_count($code, 'foreach ');
    $score += substr_count($code, 'while ');
    $score += substr_count($code, 'do ');
    
    // Логические операторы
    $score += substr_count($code, '&&');
    $score += substr_count($code, '||');
    $score += substr_count($code, 'and ');
    $score += substr_count($code, 'or ');
    
    // Тернарные операторы
    $score += substr_count($code, '? :');
    $score += substr_count($code, '?:');
    
    // Обработка исключений
    $score += substr_count($code, 'catch ');
    $score += substr_count($code, 'finally ');
    
    return $score;
}

/**
 * Анализ максимальной глубины вложенности
 */
function calculateNestingDepth($code) {
    $max_depth = 0;
    $current_depth = 0;
    $lines = explode("\n", $code);
    
    foreach ($lines as $line) {
        $current_depth += substr_count($line, '{');
        $current_depth -= substr_count($line, '}');
        $max_depth = max($max_depth, $current_depth);
    }
    
    return $max_depth;
}

/**
 * Метрики Холстеда
 */
function calculateHalsteadMetrics($code) {
    // Удаляем строковые литералы и комментарии для чистоты анализа
    $code = preg_replace('/".*?"/', '""', $code);
    $code = preg_replace("/'.*?'/", "''", $code);
    $code = preg_replace('/\/\/.*?\n/', "\n", $code);
    $code = preg_replace('/\/\*.*?\*\//s', '', $code);
    
    // Операторы
    $operators = [
        '=', '+', '-', '*', '/', '%', '++', '--', '==', '!=', '===', '!==',
        '<', '>', '<=', '>=', '&&', '||', '!', '&', '|', '^', '~', '<<', '>>',
        '+=', '-=', '*=', '/=', '%=', '.', '.=', '??', '?:', '->', '::', 'new',
        'instanceof', 'clone', 'yield', 'print', 'echo', 'return', 'if', 'else',
        'elseif', 'for', 'foreach', 'while', 'do', 'switch', 'case', 'break',
        'continue', 'function', 'class', 'interface', 'trait', 'use', 'namespace',
        'include', 'require', 'include_once', 'require_once', 'try', 'catch',
        'throw', 'finally', 'abstract', 'final', 'static', 'public', 'private',
        'protected', 'const', 'extends', 'implements', 'as', 'insteadof', 'global',
        'isset', 'unset', 'empty', 'eval', 'die', 'exit'
    ];
    
    // Операнды (переменные, функции, константы)
    preg_match_all('/\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/', $code, $variables);
    preg_match_all('/[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*\(/', $code, $functions);
    preg_match_all('/[A-Z_][A-Z0-9_]+/', $code, $constants);
    
    $unique_operators = [];
    $unique_operands = [];
    
    // Собираем уникальные операторы
    foreach ($operators as $op) {
        if (strpos($code, $op) !== false) {
            $unique_operators[$op] = true;
        }
    }
    
    // Собираем уникальные операнды
    foreach ($variables[0] as $var) {
        $unique_operands[$var] = true;
    }
    foreach ($functions[0] as $func) {
        $unique_operands[$func] = true;
    }
    foreach ($constants[0] as $const) {
        $unique_operands[$const] = true;
    }
    
    $n1 = count($unique_operators); // Количество уникальных операторов
    $n2 = count($unique_operands);   // Количество уникальных операндов
    $N1 = substr_count($code, ';');  // Примерное количество операторов
    $N2 = count($variables[0]) + count($functions[0]) + count($constants[0]); // Количество операндов
    
    // Словарь программы
    $vocabulary = $n1 + $n2;
    
    // Длина программы
    $length = $N1 + $N2;
    
    // Объем программы (биты)
    $volume = $length * log(max($vocabulary, 1), 2);
    
    return [
        'vocabulary' => $vocabulary,
        'length' => $length,
        'volume' => round($volume, 2)
    ];
}

/**
 * Анализ файла
 */
function analyzeFile($filename, $description) {
    global $total_stats;
    
    if (!file_exists($filename)) {
        out("  Файл не найден: $filename", CLR_RED);
        return null;
    }
    
    $content = file_get_contents($filename);
    $lines = count(file($filename));
    $total_stats['lines'] += $lines;
    $total_stats['files']++;
    
    out("");
    out("📁 {$filename} - {$description}", CLR_CYAN);
    out(str_repeat('─', 60), CLR_CYAN);
    out("  Строк кода: {$lines}", CLR_BLUE);
    
    // Извлекаем функции
    preg_match_all('/function\s+([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*\([^)]*\)\s*\{/', $content, $matches);
    $functions = $matches[1];
    
    out("  Функций: " . count($functions), CLR_BLUE);
    
    $file_stats = [
        'name' => $filename,
        'description' => $description,
        'lines' => $lines,
        'function_count' => count($functions),
        'functions' => [],
        'total_complexity' => 0,
        'max_complexity' => 0,
        'max_nesting' => 0,
        'total_halstead' => 0
    ];
    
    // Анализируем каждую функцию
    foreach ($functions as $func) {
        // Ищем тело функции
        $pattern = '/function\s+' . preg_quote($func) . '\s*\([^)]*\)\s*\{(.*?)\}/s';
        preg_match($pattern, $content, $func_match);
        
        if (isset($func_match[1])) {
            $func_code = $func_match[1];
            $func_lines = substr_count($func_code, "\n") + 1;
            
            $complexity = calculateCyclomaticComplexity($func_code);
            $nesting = calculateNestingDepth($func_code);
            $halstead = calculateHalsteadMetrics($func_code);
            
            $file_stats['functions'][] = [
                'name' => $func,
                'lines' => $func_lines,
                'complexity' => $complexity,
                'nesting' => $nesting,
                'halstead' => $halstead
            ];
            
            $file_stats['total_complexity'] += $complexity;
            $file_stats['max_complexity'] = max($file_stats['max_complexity'], $complexity);
            $file_stats['max_nesting'] = max($file_stats['max_nesting'], $nesting);
            $file_stats['total_halstead'] += $halstead['volume'];
        }
    }
    
    $total_stats['functions'] += count($file_stats['functions']);
    $total_stats['complexity'] += $file_stats['total_complexity'];
    $total_stats['max_nesting'] = max($total_stats['max_nesting'], $file_stats['max_nesting']);
    $total_stats['halstead_volume'] += $file_stats['total_halstead'];
    
    out("  Общая сложность: {$file_stats['total_complexity']}", CLR_YELLOW);
    out("  Средняя сложность функции: " . round($file_stats['total_complexity'] / max(1, count($file_stats['functions'])), 2), CLR_YELLOW);
    out("  Макс. сложность: {$file_stats['max_complexity']}", 
        $file_stats['max_complexity'] > 20 ? CLR_RED : ($file_stats['max_complexity'] > 10 ? CLR_YELLOW : CLR_GREEN));
    out("  Макс. вложенность: {$file_stats['max_nesting']}", 
        $file_stats['max_nesting'] > 5 ? CLR_RED : ($file_stats['max_nesting'] > 3 ? CLR_YELLOW : CLR_GREEN));
    out("  Объем Холстеда: " . round($file_stats['total_halstead'], 2) . " бит", CLR_MAGENTA);
    
    return $file_stats;
}

/**
 * Сохранение отчёта в Markdown
 */
function saveToMarkdown($results, $total_stats, $all_functions, $score, $issues) {
    $timestamp = date('Y-m-d H:i:s');
    $filename = __DIR__ . '/complexity_report_' . date('Y-m-d_H-i-s') . '.md';
    
    $md = "# Отчёт о сложности кода\n\n";
    $md .= "**Дата анализа:** $timestamp\n\n";
    $md .= "## Общая статистика\n\n";
    $md .= "| Метрика | Значение |\n";
    $md .= "|---------|----------|\n";
    $md .= "| Файлов проанализировано | {$total_stats['files']} |\n";
    $md .= "| Всего строк кода | {$total_stats['lines']} |\n";
    $md .= "| Всего функций | {$total_stats['functions']} |\n";
    $md .= "| Суммарная сложность | {$total_stats['complexity']} |\n";
    $md .= "| Средняя сложность на файл | " . round($total_stats['complexity'] / max(1, $total_stats['files']), 2) . " |\n";
    $md .= "| Средняя сложность функции | " . round($total_stats['complexity'] / max(1, $total_stats['functions']), 2) . " |\n";
    $md .= "| Максимальная вложенность | {$total_stats['max_nesting']} |\n";
    $md .= "| Общий объем Холстеда | " . round($total_stats['halstead_volume'], 2) . " бит |\n\n";
    
    $md .= "## Детальный анализ по файлам\n\n";
    foreach ($results as $file => $stats) {
        $md .= "### {$file} - {$stats['description']}\n\n";
        $md .= "- Строк кода: {$stats['lines']}\n";
        $md .= "- Функций: {$stats['function_count']}\n";
        $md .= "- Общая сложность: {$stats['total_complexity']}\n";
        $md .= "- Средняя сложность функции: " . round($stats['total_complexity'] / max(1, $stats['function_count']), 2) . "\n";
        $md .= "- Максимальная сложность: {$stats['max_complexity']}\n";
        $md .= "- Максимальная вложенность: {$stats['max_nesting']}\n";
        $md .= "- Объем Холстеда: " . round($stats['total_halstead'], 2) . " бит\n\n";
    }
    
    $md .= "## Топ-10 самых сложных функций\n\n";
    $md .= "| № | Функция | Файл | Строк | Сложность | Вложенность | Объем (бит) |\n";
    $md .= "|---|---------|------|-------|-----------|-------------|-------------|\n";
    
    $top_functions = array_slice($all_functions, 0, 10);
    foreach ($top_functions as $index => $func) {
        $md .= "| " . ($index + 1) . " | `{$func['name']}` | " . basename($func['file']) . " | {$func['lines']} | ";
        $md .= "{$func['complexity']} | {$func['nesting']} | {$func['halstead']['volume']} |\n";
    }
    $md .= "\n";
    
    $md .= "## Оценка качества кода\n\n";
    $md .= "**Общая оценка:** {$score}%\n\n";
    
    if (empty($issues)) {
        $md .= "✅ **Отлично!** Код отличного качества. Проблем не обнаружено.\n\n";
    } else {
        $md .= "⚠️ **Обнаруженные проблемы:**\n\n";
        foreach ($issues as $issue) {
            $md .= "- $issue\n";
        }
        $md .= "\n";
    }
    
    $md .= "## Расшифровка метрик\n\n";
    $md .= "### Цикломатическая сложность\n";
    $md .= "- **0-10**: Хорошо, код простой и понятный\n";
    $md .= "- **11-20**: Умеренно сложный, требует внимания\n";
    $md .= "- **21-50**: Сложный, рекомендуется рефакторинг\n";
    $md .= "- **>50**: Критически сложный, требует немедленного рефакторинга\n\n";
    
    $md .= "### Глубина вложенности\n";
    $md .= "- **1-3**: Хорошо\n";
    $md .= "- **4-5**: Умеренно\n";
    $md .= "- **>5**: Плохо, слишком глубокая вложенность\n\n";
    
    $md .= "### Метрики Холстеда\n";
    $md .= "- **Объем программы**: показывает, сколько бит нужно для хранения программы\n";
    $md .= "- **Чем меньше объем, тем проще программа**\n\n";
    
    if ($total_stats['max_nesting'] > 5) {
        $md .= "- Уменьшите глубину вложенности, выделите вложенные блоки в отдельные функции\n";
    }
    if ($total_stats['complexity'] / max(1, $total_stats['functions']) > 15) {
        $md .= "- Разбейте сложные функции на несколько более мелких\n";
    }
    foreach ($top_functions as $func) {
        if ($func['complexity'] > 20) {
            $md .= "- Функция `{$func['name']}` в файле " . basename($func['file']) . " требует рефакторинга (сложность {$func['complexity']})\n";
        }
    }
    
    file_put_contents($filename, $md);
    
    return $filename;
}

// ============================================
// АНАЛИЗ ФАЙЛОВ
// ============================================

$results = [];
foreach ($files_to_analyze as $file => $desc) {
    $stats = analyzeFile($file, $desc);
    if ($stats) {
        $results[$file] = $stats;
    }
}

// ============================================
// ИТОГОВАЯ СТАТИСТИКА
// ============================================

out("");
out("==============================================", CLR_BOLD);
out("   ИТОГОВАЯ СТАТИСТИКА", CLR_BOLD);
out("==============================================", CLR_BOLD);
out("");

out("📊 Общие метрики:", CLR_CYAN);
out("  Файлов проанализировано: {$total_stats['files']}", CLR_BLUE);
out("  Всего строк кода: {$total_stats['lines']}", CLR_BLUE);
out("  Всего функций: {$total_stats['functions']}", CLR_BLUE);
out("  Суммарная сложность: {$total_stats['complexity']}", CLR_YELLOW);
out("  Средняя сложность на файл: " . round($total_stats['complexity'] / max(1, $total_stats['files']), 2), CLR_YELLOW);
out("  Средняя сложность функции: " . round($total_stats['complexity'] / max(1, $total_stats['functions']), 2), CLR_YELLOW);
out("  Максимальная вложенность: {$total_stats['max_nesting']}", 
    $total_stats['max_nesting'] > 5 ? CLR_RED : ($total_stats['max_nesting'] > 3 ? CLR_YELLOW : CLR_GREEN));
out("  Общий объем Холстеда: " . round($total_stats['halstead_volume'], 2) . " бит", CLR_MAGENTA);

// ============================================
// ТОП СЛОЖНЫХ ФУНКЦИЙ
// ============================================

out("");
out("==============================================", CLR_BOLD);
out("   ТОП-10 САМЫХ СЛОЖНЫХ ФУНКЦИЙ", CLR_BOLD);
out("==============================================", CLR_BOLD);
out("");

$all_functions = [];
foreach ($results as $file => $stats) {
    foreach ($stats['functions'] as $func) {
        $func['file'] = $file;
        $all_functions[] = $func;
    }
}

// Сортируем по сложности
usort($all_functions, function($a, $b) {
    return $b['complexity'] - $a['complexity'];
});

$top_functions = array_slice($all_functions, 0, 10);
foreach ($top_functions as $index => $func) {
    $complexity_color = $func['complexity'] > 20 ? CLR_RED : ($func['complexity'] > 10 ? CLR_YELLOW : CLR_GREEN);
    $nesting_color = $func['nesting'] > 5 ? CLR_RED : ($func['nesting'] > 3 ? CLR_YELLOW : CLR_GREEN);
    
    out(sprintf("%2d. %-25s (в %s)", 
        $index + 1, 
        $func['name'], 
        basename($func['file'])
    ), CLR_CYAN);
    out("    └─ Сложность: {$func['complexity']} | Вложенность: {$func['nesting']} | Строк: {$func['lines']} | Объем: {$func['halstead']['volume']} бит", 
        CLR_YELLOW);
}

// ============================================
// ОЦЕНКА КАЧЕСТВА КОДА
// ============================================

out("");
out("==============================================", CLR_BOLD);
out("   ОЦЕНКА КАЧЕСТВА КОДА", CLR_BOLD);
out("==============================================", CLR_BOLD);
out("");

$score = 100;
$issues = [];

// Критерии оценки
if ($total_stats['functions'] > 0) {
    $avg_complexity = $total_stats['complexity'] / $total_stats['functions'];
    if ($avg_complexity > 15) {
        $score -= 30;
        $issues[] = "Высокая средняя сложность функций (" . round($avg_complexity, 2) . ")";
    } elseif ($avg_complexity > 10) {
        $score -= 15;
        $issues[] = "Средняя сложность функций выше нормы (" . round($avg_complexity, 2) . ")";
    }
}

if ($total_stats['max_nesting'] > 5) {
    $score -= 20;
    $issues[] = "Глубокая вложенность (> {$total_stats['max_nesting']})";
} elseif ($total_stats['max_nesting'] > 3) {
    $score -= 10;
    $issues[] = "Вложенность выше нормы ({$total_stats['max_nesting']})";
}

// Проверка на очень большие функции
foreach ($all_functions as $func) {
    if ($func['lines'] > 100) {
        $score -= 5;
        $issues[] = "Функция {$func['name']} слишком большая ({$func['lines']} строк)";
        break;
    }
}

// Проверка на очень сложные функции
foreach ($all_functions as $func) {
    if ($func['complexity'] > 30) {
        $score -= 10;
        $issues[] = "Критически сложная функция {$func['name']} ({$func['complexity']})";
        break;
    }
}

out("📈 Общая оценка: {$score}%", 
    $score >= 80 ? CLR_GREEN : ($score >= 60 ? CLR_YELLOW : CLR_RED));

if (empty($issues)) {
    out("✅ Код отличного качества! Проблем не обнаружено.", CLR_GREEN);
} else {
    out("⚠️ Обнаруженные проблемы:", CLR_YELLOW);
    foreach ($issues as $issue) {
        out("  • " . $issue, CLR_YELLOW);
    }
}

// ============================================
// СОХРАНЕНИЕ В MARKDOWN
// ============================================

$md_file = saveToMarkdown($results, $total_stats, $all_functions, $score, $issues);
out("");
out("==============================================", CLR_BOLD);
out("   СОХРАНЕНИЕ ОТЧЁТА", CLR_BOLD);
out("==============================================", CLR_BOLD);
out("");
out("✅ Отчёт сохранён в файл: {$md_file}", CLR_GREEN);
out("   Откройте его в любом Markdown-редакторе", CLR_BLUE);

// ============================================
// ДЕТАЛЬНЫЙ АНАЛИЗ ПО ФАЙЛАМ (ДЛЯ БРАУЗЕРА)
// ============================================

if (!$is_cli) {
    // В браузере показываем детальную таблицу
    echo '<div class="row">';
    foreach ($results as $file => $stats) {
        echo '<div class="col-md-4">';
        echo '<div class="metric-card">';
        echo '<h5>' . htmlspecialchars($file) . '</h5>';
        echo '<p><small>' . htmlspecialchars($stats['description']) . '</small></p>';
        echo '<div class="metric-value">' . count($stats['functions']) . '</div>';
        echo '<div class="metric-name">функций</div>';
        echo '<div class="progress"><div class="progress-bar bg-info" style="width: ' . min(100, $stats['total_complexity']) . '%"></div></div>';
        echo '<div class="mt-2">Сложность: ' . $stats['total_complexity'] . '</div>';
        echo '<div>Вложенность: ' . $stats['max_nesting'] . '</div>';
        echo '<div>Объем: ' . round($stats['total_halstead'], 2) . ' бит</div>';
        echo '</div>';
        echo '</div>';
    }
    echo '</div>';
    
    echo '<div class="function-list metric-card">';
    echo '<h5>Детальный анализ функций</h5>';
    echo '<table class="table table-sm">';
    echo '<thead><tr><th>Функция</th><th>Файл</th><th>Строк</th><th>Сложность</th><th>Вложенность</th><th>Объем</th></tr></thead>';
    echo '<tbody>';
    foreach ($all_functions as $func) {
        $row_class = $func['complexity'] > 20 ? 'table-danger' : ($func['complexity'] > 10 ? 'table-warning' : '');
        echo '<tr class="' . $row_class . '">';
        echo '<td><code>' . htmlspecialchars($func['name']) . '</code></td>';
        echo '<td>' . htmlspecialchars(basename($func['file'])) . '</td>';
        echo '<td>' . $func['lines'] . '</td>';
        echo '<td>' . $func['complexity'] . '</td>';
        echo '<td>' . $func['nesting'] . '</td>';
        echo '<td>' . $func['halstead']['volume'] . '</td>';
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
    
    echo '<div class="alert alert-success">';
    echo '<i class="bi bi-check-circle"></i> ';
    echo 'Отчёт также сохранён в файл: <code>' . htmlspecialchars($md_file) . '</code>';
    echo '</div>';
}

out("");
out("==============================================", CLR_BOLD);
out("   АНАЛИЗ ЗАВЕРШЕН", CLR_BOLD);
out("==============================================", CLR_BOLD);

// Закрываем HTML
if (!$is_cli) {
    echo '</div></body></html>';
}
?>