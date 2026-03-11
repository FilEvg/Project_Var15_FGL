<?php
// backup_functions.php - упрощенные функции для резервного копирования и восстановления
// Версия без использования Composer и внешних библиотек

// Константы для директории бэкапов
define('BACKUP_DIR', __DIR__ . '/backups/');

// Создание директории для бэкапов, если её нет
if (!file_exists(BACKUP_DIR)) {
    mkdir(BACKUP_DIR, 0755, true);
}

/**
 * Функция для создания резервной копии
 * @param string $format Формат файла (csv, json, sql, xml)
 * @param array $tables Список таблиц для бэкапа (по умолчанию все)
 * @return array Результат операции
 */
function createBackup($format = 'csv', $tables = []) {
    $result = [
        'success' => false,
        'message' => '',
        'filename' => '',
        'filepath' => ''
    ];
    
    try {
        $conn = getConnection();
        
        // Если таблицы не указаны, берём все основные
        if (empty($tables)) {
            $tables = ['products', 'subdivisions', 'competitors', 'sales', 'competitor_prices', 'users', 'roles', 'user_roles'];
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $filename = 'backup_' . $timestamp . '.' . $format;
        $filepath = BACKUP_DIR . $filename;
        
        // Собираем данные из всех таблиц
        $data = [];
        $tableCount = 0;
        $rowCount = 0;
        
        foreach ($tables as $table) {
            // Проверяем существование таблицы
            $check_query = "SHOW TABLES LIKE '$table'";
            $check_result = $conn->query($check_query);
            
            if ($check_result && $check_result->num_rows > 0) {
                $query = "SELECT * FROM $table";
                $table_result = $conn->query($query);
                
                $rows = [];
                while ($row = $table_result->fetch_assoc()) {
                    $rows[] = $row;
                    $rowCount++;
                }
                
                // Получаем структуру таблицы (только имена колонок)
                $columns = [];
                if (!empty($rows)) {
                    $columns = array_keys($rows[0]);
                } else {
                    // Если таблица пустая, получаем колонки через DESCRIBE
                    $desc_query = "DESCRIBE $table";
                    $desc_result = $conn->query($desc_query);
                    while ($col = $desc_result->fetch_assoc()) {
                        $columns[] = $col['Field'];
                    }
                }
                
                $data[$table] = [
                    'columns' => $columns,
                    'data' => $rows
                ];
                $tableCount++;
            }
        }
        
        // Сохраняем в зависимости от формата
        switch ($format) {
            case 'json':
                $json_data = json_encode([
                    'metadata' => [
                        'created' => date('Y-m-d H:i:s'),
                        'tables' => $tableCount,
                        'rows' => $rowCount,
                        'format' => 'JSON'
                    ],
                    'data' => $data
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                file_put_contents($filepath, $json_data);
                break;
                
            case 'csv':
                createCsvBackup($data, $filepath);
                break;
                
            case 'sql':
                createSqlBackup($data, $filepath, $conn);
                break;
                
            case 'xml':
                createXmlBackup($data, $filepath);
                break;
                
            default:
                throw new Exception('Неподдерживаемый формат файла');
        }
        
        $result['success'] = true;
        $result['message'] = "Резервная копия успешно создана. Таблиц: $tableCount, записей: $rowCount";
        $result['filename'] = $filename;
        $result['filepath'] = $filepath;
        
        // Логируем действие
        logBackupAction('create', $filename, $format, $tableCount, $rowCount);
        
    } catch (Exception $e) {
        $result['message'] = 'Ошибка при создании резервной копии: ' . $e->getMessage();
    }
    
    return $result;
}

/**
 * Создание CSV бэкапа
 */
function createCsvBackup($data, $filepath) {
    $fp = fopen($filepath, 'w');
    
    // Добавляем BOM для корректного отображения UTF-8 в Excel
    fprintf($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));
    
    // Метаданные
    fputcsv($fp, ['# РЕЗЕРВНАЯ КОПИЯ СОЗДАНА: ' . date('Y-m-d H:i:s')], ';');
    fputcsv($fp, ['# КОЛИЧЕСТВО ТАБЛИЦ: ' . count($data)], ';');
    fputcsv($fp, [], ';');
    
    foreach ($data as $table_name => $table_data) {
        // Заголовок таблицы
        fputcsv($fp, ['=== ТАБЛИЦА: ' . $table_name . ' ==='], ';');
        fputcsv($fp, ['# КОЛИЧЕСТВО ЗАПИСЕЙ: ' . count($table_data['data'])], ';');
        
        if (!empty($table_data['data'])) {
            // Заголовки столбцов
            fputcsv($fp, $table_data['columns'], ';');
            
            // Данные
            foreach ($table_data['data'] as $row) {
                $csv_row = [];
                foreach ($table_data['columns'] as $column) {
                    $csv_row[] = isset($row[$column]) ? $row[$column] : '';
                }
                fputcsv($fp, $csv_row, ';');
            }
        } else {
            fputcsv($fp, ['# Нет данных в таблице'], ';');
        }
        
        // Пустая строка между таблицами
        fputcsv($fp, [], ';');
    }
    
    fclose($fp);
}

/**
 * Создание SQL бэкапа (дамп базы данных)
 */
function createSqlBackup($data, $filepath, $conn) {
    $fp = fopen($filepath, 'w');
    
    // Заголовок
    fwrite($fp, "-- РЕЗЕРВНАЯ КОПИЯ БАЗЫ ДАННЫХ\n");
    fwrite($fp, "-- Дата создания: " . date('Y-m-d H:i:s') . "\n");
    fwrite($fp, "-- Кодировка: UTF-8\n\n");
    
    fwrite($fp, "SET FOREIGN_KEY_CHECKS=0;\n");
    fwrite($fp, "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n");
    fwrite($fp, "SET NAMES utf8mb4;\n\n");
    
    foreach ($data as $table_name => $table_data) {
        fwrite($fp, "-- --------------------------------------------------------\n");
        fwrite($fp, "-- Таблица: $table_name\n");
        fwrite($fp, "-- Записей: " . count($table_data['data']) . "\n");
        fwrite($fp, "-- --------------------------------------------------------\n\n");
        
        // Очистка таблицы перед вставкой
        fwrite($fp, "TRUNCATE TABLE `$table_name`;\n\n");
        
        if (!empty($table_data['data'])) {
            foreach ($table_data['data'] as $row) {
                $columns = implode('`, `', array_map(function($col) use ($conn) {
                    return $conn->real_escape_string($col);
                }, array_keys($row)));
                
                $values = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $values[] = 'NULL';
                    } else {
                        $values[] = "'" . $conn->real_escape_string($value) . "'";
                    }
                }
                $values_str = implode(', ', $values);
                
                $sql = "INSERT INTO `$table_name` (`$columns`) VALUES ($values_str);\n";
                fwrite($fp, $sql);
            }
        } else {
            // Если таблица пустая, добавляем структуру
            $create_query = "SHOW CREATE TABLE $table_name";
            $create_result = $conn->query($create_query);
            if ($create_result && $create_result->num_rows > 0) {
                $create_row = $create_result->fetch_assoc();
                fwrite($fp, $create_row['Create Table'] . ";\n\n");
            }
        }
        fwrite($fp, "\n");
    }
    
    fwrite($fp, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($fp);
}

/**
 * Создание XML бэкапа
 */
function createXmlBackup($data, $filepath) {
    $xml = new DOMDocument('1.0', 'UTF-8');
    $xml->formatOutput = true;
    
    $root = $xml->createElement('backup');
    $root->setAttribute('created', date('Y-m-d H:i:s'));
    $root->setAttribute('tables', count($data));
    $xml->appendChild($root);
    
    foreach ($data as $table_name => $table_data) {
        $table_elem = $xml->createElement('table');
        $table_elem->setAttribute('name', $table_name);
        $table_elem->setAttribute('rows', count($table_data['data']));
        
        if (!empty($table_data['data'])) {
            foreach ($table_data['data'] as $row) {
                $row_elem = $xml->createElement('row');
                
                foreach ($row as $column => $value) {
                    $col_elem = $xml->createElement($column);
                    $col_elem->appendChild($xml->createCDATASection($value));
                    $row_elem->appendChild($col_elem);
                }
                
                $table_elem->appendChild($row_elem);
            }
        }
        
        $root->appendChild($table_elem);
    }
    
    $xml->save($filepath);
}

/**
 * Функция для восстановления из бэкапа
 */
function restoreFromBackup($filepath, $truncate = true) {
    $result = [
        'success' => false,
        'message' => '',
        'tables_restored' => []
    ];
    
    try {
        if (!file_exists($filepath)) {
            throw new Exception('Файл бэкапа не найден');
        }
        
        $conn = getConnection();
        $conn->begin_transaction();
        
        $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
        $restored_count = 0;
        
        switch ($extension) {
            case 'json':
                $data = restoreFromJson($filepath);
                break;
            case 'csv':
                $data = restoreFromCsv($filepath);
                break;
            case 'sql':
                $result = restoreFromSql($filepath, $conn);
                $conn->commit();
                return $result;
            case 'xml':
                $data = restoreFromXml($filepath);
                break;
            default:
                throw new Exception('Неподдерживаемый формат файла');
        }
        
        // Восстанавливаем данные из JSON, CSV или XML
        foreach ($data as $table_name => $table_data) {
            // Проверяем существование таблицы
            $check_query = "SHOW TABLES LIKE '$table_name'";
            $check_result = $conn->query($check_query);
            
            if ($check_result->num_rows == 0) {
                // Если таблицы нет, пропускаем
                continue;
            }
            
            // Очищаем таблицу, если нужно
            if ($truncate) {
                $conn->query("TRUNCATE TABLE $table_name");
            }
            
            // Вставляем данные
            if (!empty($table_data['data'])) {
                foreach ($table_data['data'] as $row) {
                    $columns = implode('`, `', array_keys($row));
                    $values = implode("', '", array_map([$conn, 'real_escape_string'], array_values($row)));
                    $insert_query = "INSERT INTO $table_name (`$columns`) VALUES ('$values')";
                    if ($conn->query($insert_query)) {
                        $restored_count++;
                    }
                }
            }
            
            $result['tables_restored'][] = $table_name;
        }
        
        $conn->commit();
        
        $result['success'] = true;
        $result['message'] = "Восстановление успешно завершено. Восстановлено записей: $restored_count";
        
        // Логируем действие
        logBackupAction('restore', basename($filepath), $extension);
        
    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
        }
        $result['message'] = 'Ошибка при восстановлении: ' . $e->getMessage();
    }
    
    return $result;
}

/**
 * Восстановление из JSON
 */
function restoreFromJson($filepath) {
    $content = file_get_contents($filepath);
    $data = json_decode($content, true);
    return isset($data['data']) ? $data['data'] : $data;
}

/**
 * Восстановление из CSV
 */
function restoreFromCsv($filepath) {
    $data = [];
    $current_table = null;
    $columns = [];
    $row_number = 0;
    
    if (($handle = fopen($filepath, 'r')) !== FALSE) {
        // Пропускаем BOM если есть
        $bom = fread($handle, 3);
        if ($bom !== chr(0xEF) . chr(0xBB) . chr(0xBF)) {
            rewind($handle);
        }
        
        while (($row = fgetcsv($handle, 10000, ';')) !== FALSE) {
            $row_number++;
            
            if (empty($row) || (count($row) == 1 && empty($row[0]))) {
                continue;
            }
            
            // Пропускаем строки с комментариями
            if (!empty($row[0]) && $row[0][0] === '#') {
                continue;
            }
            
            // Проверяем, является ли строка заголовком таблицы
            if (strpos($row[0], '=== ТАБЛИЦА:') === 0) {
                preg_match('/=== ТАБЛИЦА: (.*?) ===/', $row[0], $matches);
                $current_table = isset($matches[1]) ? $matches[1] : null;
                $data[$current_table] = [
                    'columns' => [],
                    'data' => []
                ];
                $columns = [];
                continue;
            }
            
            if ($current_table && !empty($row)) {
                // Если это строка с заголовками столбцов
                if (empty($columns)) {
                    $columns = $row;
                    $data[$current_table]['columns'] = $columns;
                } else {
                    // Это данные
                    $row_data = [];
                    foreach ($columns as $index => $column) {
                        if (isset($row[$index])) {
                            $row_data[$column] = $row[$index];
                        }
                    }
                    if (!empty($row_data)) {
                        $data[$current_table]['data'][] = $row_data;
                    }
                }
            }
        }
        fclose($handle);
    }
    
    return $data;
}

/**
 * Восстановление из SQL
 */
function restoreFromSql($filepath, $conn) {
    $result = [
        'success' => false,
        'message' => '',
        'tables_restored' => []
    ];
    
    $sql = file_get_contents($filepath);
    
    // Убираем комментарии
    $sql = preg_replace('/--.*?\n/', "\n", $sql);
    $sql = preg_replace('/#.*?\n/', "\n", $sql);
    
    // Разделяем на отдельные запросы
    $queries = explode(';', $sql);
    $query_count = 0;
    
    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query)) {
            if ($conn->query($query)) {
                $query_count++;
            }
        }
    }
    
    $result['success'] = true;
    $result['message'] = "SQL восстановление выполнено. Выполнено запросов: $query_count";
    
    return $result;
}

/**
 * Восстановление из XML
 */
function restoreFromXml($filepath) {
    $xml = simplexml_load_file($filepath);
    $data = [];
    
    foreach ($xml->table as $table_elem) {
        $table_name = (string)$table_elem['name'];
        $data[$table_name] = [
            'columns' => [],
            'data' => []
        ];
        
        foreach ($table_elem->row as $row_elem) {
            $row_data = [];
            foreach ($row_elem->children() as $col_name => $col_value) {
                $row_data[$col_name] = (string)$col_value;
                if (!in_array($col_name, $data[$table_name]['columns'])) {
                    $data[$table_name]['columns'][] = $col_name;
                }
            }
            if (!empty($row_data)) {
                $data[$table_name]['data'][] = $row_data;
            }
        }
    }
    
    return $data;
}

/**
 * Получение списка доступных бэкапов
 */
function getBackupList() {
    $backups = [];
    $files = glob(BACKUP_DIR . 'backup_*.*');
    
    foreach ($files as $file) {
        $filename = basename($file);
        $filesize = filesize($file);
        $filetime = filemtime($file);
        $extension = strtoupper(pathinfo($file, PATHINFO_EXTENSION));
        
        // Пытаемся получить дополнительную информацию из файла
        $info = getBackupInfo($file);
        
        $backups[] = [
            'filename' => $filename,
            'size' => formatFileSize($filesize),
            'date' => date('d.m.Y H:i:s', $filetime),
            'format' => $extension,
            'path' => $file,
            'tables' => is_numeric($info['tables']) ? $info['tables'] : '?',
            'rows' => is_numeric($info['rows']) ? $info['rows'] : '?',
            'created' => $info['created']
        ];
    }
    
    // Сортируем по дате (сначала новые)
    usort($backups, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
    
    return $backups;
}

/**
 * Получение информации о бэкапе
 */
function getBackupInfo($filepath) {
    $info = [
        'tables' => '?',
        'rows' => '?',
        'created' => ''
    ];
    
    $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
    
    try {
        if ($extension == 'json') {
            $content = file_get_contents($filepath);
            $data = json_decode($content, true);
            if (isset($data['metadata'])) {
                // Валидируем данные из JSON перед использованием
                $info['tables'] = isset($data['metadata']['tables']) && is_numeric($data['metadata']['tables']) 
                    ? intval($data['metadata']['tables']) 
                    : '?';
                $info['rows'] = isset($data['metadata']['rows']) && is_numeric($data['metadata']['rows']) 
                    ? intval($data['metadata']['rows']) 
                    : '?';
                $info['created'] = isset($data['metadata']['created']) && is_string($data['metadata']['created'])
                    ? substr(preg_replace('/[^0-9:\-\s]/', '', $data['metadata']['created']), 0, 20)
                    : '';
            }
        }
    } catch (Exception $e) {}
    
    return $info;
}

/**
 * Форматирование размера файла
 */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        return $bytes . ' bytes';
    } elseif ($bytes == 1) {
        return '1 byte';
    } else {
        return '0 bytes';
    }
}

/**
 * Удаление файла бэкапа
 */
function deleteBackup($filename) {
    $filepath = BACKUP_DIR . $filename;
    
    if (file_exists($filepath) && strpos($filename, 'backup_') === 0) {
        unlink($filepath);
        logBackupAction('delete', $filename, pathinfo($filename, PATHINFO_EXTENSION));
        return true;
    }
    
    return false;
}

/**
 * Логирование действий с бэкапами
 */
function logBackupAction($action, $filename, $format, $tables = 0, $rows = 0) {
    $log_file = BACKUP_DIR . 'backup_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    $user = isset($_SESSION['username']) ? preg_replace('/[^a-zA-Z0-9_@.-]/', '', $_SESSION['username']) : 'Guest';
    $action = preg_replace('/[^a-zA-Z]/', '', $action);
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
    $format = preg_replace('/[^a-zA-Z]/', '', $format);
    
    $log_entry = "[$timestamp] $user | $action | $filename | $format";
    if ($tables > 0) {
        $log_entry .= " | таблиц: " . intval($tables) . " | записей: " . intval($rows);
    }
    $log_entry .= "\n";
    
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

/**
 * Скачивание файла бэкапа
 */
function downloadBackup($filename) {
    $filepath = BACKUP_DIR . $filename;
    
    if (file_exists($filepath) && strpos($filename, 'backup_') === 0) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filepath));
        
        readfile($filepath);
        logBackupAction('download', $filename, pathinfo($filename, PATHINFO_EXTENSION));
        exit();
    }
}

/**
 * Автоматическое создание бэкапа
 */
function autoBackup($format = 'csv') {
    $result = createBackup($format);
    
    // Удаляем старые бэкапы (оставляем последние 10)
    $backups = getBackupList();
    if (count($backups) > 10) {
        $to_delete = array_slice($backups, 10);
        foreach ($to_delete as $backup) {
            deleteBackup($backup['filename']);
        }
    }
    
    return $result;
}
?>