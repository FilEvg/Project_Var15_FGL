<?php
// config.php - подключение к базам данных

// Запускаем сессию только в веб-режиме
if (php_sapi_name() !== 'cli') {
    session_start();

    // =============================================
    // НАСТРОЙКИ ТАЙМЕРОВ (меняйте значения здесь)
    // =============================================
    $GAME_INTERVAL = 300;   // Таймер до появления игры (в секундах)
    $GAME_DURATION = 60;   // Продолжительность игры (в секундах)
    // =============================================

    // Инициализация таймера в сессии
    if (!isset($_SESSION['game_timer_start'])) {
        $_SESSION['game_timer_start'] = time();
    }
    if (!isset($_SESSION['game_interval_seconds'])) {
        $_SESSION['game_interval_seconds'] = $GAME_INTERVAL;
    }
    if (!isset($_SESSION['game_duration_seconds'])) {
        $_SESSION['game_duration_seconds'] = $GAME_DURATION;
    }
    if (!isset($_SESSION['game_active'])) {
        $_SESSION['game_active'] = false;
    }
    if (!isset($_SESSION['game_end_time'])) {
        $_SESSION['game_end_time'] = 0;
    }
}

// Конфигурация основной (глобальной) БД
define('DB_HOST', '134.90.167.42:10306');
define('DB_NAME', 'project_Filippov');
define('DB_USER', 'Filippov');
define('DB_PASS', 'tU/aX8o(jX9q)dvq');

// Конфигурация локальной БД для паролей
define('LOCAL_DB_HOST', 'localhost');
define('LOCAL_DB_NAME', 'prj_Fi');
define('LOCAL_DB_USER', 'admin');
define('LOCAL_DB_PASS', 'admin');

define('SITE_NAME', 'Система исследования рынка');

// Функция для подключения к основной БД
function getConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
        $conn->set_charset("utf8mb4");
        return $conn;
    } catch(Exception $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

// Функция для подключения к локальной БД (для паролей)
function getLocalConnection() {
    try {
        $conn = new mysqli(LOCAL_DB_HOST, LOCAL_DB_USER, LOCAL_DB_PASS, LOCAL_DB_NAME);
        if ($conn->connect_error) {
            die("Local database connection failed: " . $conn->connect_error);
        }
        $conn->set_charset("utf8mb4");
        return $conn;
    } catch(Exception $e) {
        die("Local database connection failed: " . $e->getMessage());
    }
}

// AJAX-обработчик для обновления состояния таймера
if (isset($_GET['ajax']) && $_GET['ajax'] === 'game_timer') {
    header('Content-Type: application/json');

    $action = $_POST['action'] ?? '';

    if ($action === 'game_started') {
        $_SESSION['game_active'] = true;
        $_SESSION['game_end_time'] = time() + $_SESSION['game_duration_seconds'];
        echo json_encode(['success' => true, 'game_active' => true, 'game_end_time' => $_SESSION['game_end_time']]);
    } elseif ($action === 'game_finished') {
        $_SESSION['game_active'] = false;
        $_SESSION['game_timer_start'] = time();
        $_SESSION['game_end_time'] = 0;
        echo json_encode(['success' => true, 'game_active' => false, 'next_time' => $_SESSION['game_timer_start'] + $_SESSION['game_interval_seconds']]);
    } elseif ($action === 'get_state') {
        echo json_encode([
            'game_active' => $_SESSION['game_active'],
            'game_end_time' => $_SESSION['game_end_time'],
            'timer_start' => $_SESSION['game_timer_start'],
            'interval' => $_SESSION['game_interval_seconds'],
            'duration' => $_SESSION['game_duration_seconds']
        ]);
    } else {
        echo json_encode(['error' => 'Invalid action']);
    }
    exit;
}

// Функция для проверки пароля пользователя в локальной БД
function verifyPassword($username, $password) {
    $local_conn = getLocalConnection();
    
    $sql = "SELECT password_hash FROM users_auth WHERE username = ?";
    $stmt = $local_conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        $local_conn->close();
        // Проверяем пароль с помощью password_verify
        return password_verify($password, $row['password_hash']);
    }
    
    $local_conn->close();
    return false;
}

// Функция для добавления/обновления пользователя в локальной БД
function syncUserToLocalDB($user_id, $username, $password) {
    $local_conn = getLocalConnection();
    
    // Хешируем пароль
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    $sql = "INSERT INTO users_auth (user_id, username, password_hash) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            password_hash = VALUES(password_hash),
            last_updated = CURRENT_TIMESTAMP";
    
    $stmt = $local_conn->prepare($sql);
    $stmt->bind_param("iss", $user_id, $username, $password_hash);
    $result = $stmt->execute();
    
    $local_conn->close();
    return $result;
}

// Функция для удаления пользователя из локальной БД
function deleteUserFromLocalDB($user_id) {
    $local_conn = getLocalConnection();
    
    $sql = "DELETE FROM users_auth WHERE user_id = ?";
    $stmt = $local_conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $result = $stmt->execute();
    
    $local_conn->close();
    return $result;
}

// ============================================
// СИСТЕМА РОЛЕЙ И ПРАВ ДОСТУПА
// ============================================

// Функция для получения роли пользователя
function getUserRole($user_id) {
    $conn = getConnection();
    $sql = "SELECT r.name as role_name 
            FROM user_roles ur 
            JOIN roles r ON ur.role_id = r.id 
            WHERE ur.user_id = ? 
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        $conn->close();
        return $row['role_name'];
    }
    $conn->close();
    return 'guest'; // По умолчанию, если роль не назначена
}

// Основные функции проверки ролей
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isRegularUser() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'user';
}

function isGuestRole() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'guest';
}

// Функции для проверки состояния пользователя
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

function isGuestUser() {
    return isset($_SESSION['guest']) && $_SESSION['guest'] === true;
}

function isGuest() {
    // Проверяем и гостевой режим, и роль гостя
    return isGuestUser() || (isset($_SESSION['role']) && $_SESSION['role'] === 'guest');
}

function endGuestMode() {
    if (isset($_SESSION['guest'])) {
        unset($_SESSION['guest']);
        unset($_SESSION['full_name']);
    }
}

// Функция для проверки прав на редактирование с учетом ролей
function canEdit() {
    if (isGuestUser() || (isset($_SESSION['role']) && $_SESSION['role'] === 'guest')) {
        return false; // Гости не могут редактировать
    }
    if (isAdmin() || isRegularUser()) {
        return true; // Админ и пользователь могут редактировать
    }
    return false;
}

// Функция для проверки конкретных разрешений
function hasPermission($permission) {
    switch($permission) {
        case 'manage_users':
            return isAdmin(); // Только админ
        case 'edit_data':
            return isAdmin() || isRegularUser(); // Админ и пользователь
        case 'view_data':
            return true; // Все могут просматривать
        case 'export_reports':
            return isAdmin() || isRegularUser(); // Админ и пользователь
        case 'delete_data':
            return isAdmin() || isRegularUser(); // Админ и пользователь
        default:
            return false;
    }
}

// Функция для получения текстового представления роли
function getRoleDisplayName() {
    if (isGuestUser()) {
        return 'Гость (неавторизованный)';
    } elseif (isAdmin()) {
        return 'Админ';
    } elseif (isRegularUser()) {
        return 'Пользователь';
    } elseif (isGuestRole()) {
        return 'Гость (авторизованный)';
    } else {
        return 'Неизвестно';
    }
}

// Функция для получения цвета badge для роли
function getRoleBadgeColor() {
    if (isAdmin()) {
        return 'danger';
    } elseif (isRegularUser()) {
        return 'primary';
    } elseif (isGuestRole()) {
        return 'info';
    } elseif (isGuestUser()) {
        return 'secondary';
    } else {
        return 'dark';
    }
}

// ============================================
// ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
// ============================================

function redirect($url) {
    header("Location: $url");
    exit();
}

function sanitize($data) {
    $conn = getConnection();
    return mysqli_real_escape_string($conn, htmlspecialchars(trim($data)));
}

// Функция для отображения шапки
function displayHeader($title = '') {
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo SITE_NAME . ($title ? ' - ' . $title : ''); ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
        <link rel="stylesheet" href="style.css">
    </head>
    <body class="d-flex flex-column min-vh-100">
        <div class="content-wrapper flex-grow-1 d-flex flex-column">
        <?php if (isLoggedIn() || isGuest()): ?>
        <nav class="navbar navbar-expand-lg navbar-dark bg-gradient-primary shadow-sm">
            <div class="container-fluid">
                <a class="navbar-brand fw-bold" href="index.php?page=home">
                    <i class="bi bi-graph-up"></i> <?php echo SITE_NAME; ?>
                </a>
                <span class="navbar-text ms-3 d-none d-md-inline" id="timerContainer" style="background:rgba(255,255,255,0.15);border-radius:6px;padding:4px 12px;font-size:0.9rem;">
                    <i class="bi bi-clock"></i> Игра через: <span id="mainTimerDisplay" class="fw-bold">30</span> сек.
                </span>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php?page=home"><i class="bi bi-house"></i> Главная</a>
                        </li>

                        <!-- Все роли видят эти пункты -->
                        <li class="nav-item">
                            <a class="nav-link" href="index.php?page=products"><i class="bi bi-box"></i> Товары</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="index.php?page=sales"><i class="bi bi-cart-check"></i> Продажи</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="index.php?page=competitors"><i class="bi bi-bar-chart"></i> Конкуренты</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="index.php?page=reports"><i class="bi bi-file-earmark-text"></i> Отчеты</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="index.php?page=analytics"><i class="bi bi-graph-up"></i> Аналитика</a>
                        </li>

                        <!-- Только админы видят управление пользователями -->
                        <?php if (isAdmin()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="index.php?page=admin_users"><i class="bi bi-people"></i> Пользователи</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="index.php?page=backup"><i class="bi bi-database"></i> Бэкап</a>
                        </li>
                        <?php endif; ?>
                    </ul>

                    <div class="navbar-nav">
                        <?php if (isLoggedIn()): ?>
                            <span class="navbar-text me-3">
                                <i class="bi bi-person-circle"></i>
                                    <?php echo htmlspecialchars($_SESSION['full_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                <small class="ms-1 badge bg-<?php echo htmlspecialchars(getRoleBadgeColor(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars(getRoleDisplayName(), ENT_QUOTES, 'UTF-8'); ?>
                                </small>
                            </span>
                            <a href="index.php?page=logout" class="btn btn-outline-light btn-sm">
                                <i class="bi bi-box-arrow-right"></i> Выход
                            </a>
                        <?php elseif (isGuest()): ?>
                            <span class="navbar-text me-3">
                                <i class="bi bi-eye"></i> Гостевой режим
                            </span>
                            <a href="index.php?page=login&end_guest=true" class="btn btn-outline-light btn-sm">
                                <i class="bi bi-box-arrow-in-right"></i> Войти
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </nav>
        
        <?php if (isset($_SESSION['error'])): ?>
        <div class="container">
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_SESSION['error'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
        <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
        <div class="container">
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_SESSION['success'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
        <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php endif; ?>
    <?php
}

// Функция для отображения подвала
function displayFooter() {
    ?>
    <footer class="footer mt-auto py-4 border-top shadow-sm">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="mb-1 fw-bold text-dark"><i class="bi bi-graph-up"></i> <?php echo SITE_NAME; ?></h5>
                    <p class="text-muted mb-0">Система исследования товарного рынка</p>
                </div>
                <div class="col-md-6 text-md-end mt-3 mt-md-0">
                    <p class="text-muted mb-1">
                        &copy; <?php echo date('Y'); ?> Все права защищены.
                    </p>
                    <p class="mb-0">
                        <small class="text-muted">Версия 1.0 |
                        <?php
                        if (isGuestUser()) {
                            echo '<span class="badge bg-secondary">Гостевой режим</span>';
                        } elseif (isLoggedIn()) {
                            echo '<span class="badge bg-' . htmlspecialchars(getRoleBadgeColor(), ENT_QUOTES, 'UTF-8') . '">' .
                                htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8') . ' (' .
                                     htmlspecialchars(getRoleDisplayName(), ENT_QUOTES, 'UTF-8') . ')</span>';
                        }
                        ?>
                        </small>
                    </p>
                </div>
            </div>
        </div>
    </footer>
    </div>

    <!-- Модальное окно с игрой -->
    <div class="modal fade" id="gameModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" data-bs-focus="false">
        <div class="modal-dialog modal-dialog-centered" style="max-width:95vw;width:95vw;">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title"><i class="bi bi-clock-fill"></i> Время отдыха!</h5>
                </div>
                <div class="modal-body p-0">
                    <iframe id="gameFrame" src="" tabindex="0" allow="autoplay; fullscreen; picture-in-picture" style="width:100%;height:75vh;min-height:500px;border:none;"></iframe>
                    <div class="text-center py-3 bg-light">
                        <p class="mb-1 fw-bold">Рабочий доступ будет восстановлен через:</p>
                        <div class="d-flex align-items-center justify-content-center gap-2">
                            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                            <span id="gameTimerDisplay" class="fs-4 fw-bold text-primary">30</span>
                            <span class="text-muted">сек.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Скрипт таймера -->
    <script>
    (function() {
        let gameModalInstance = null;
        let gameActive = false;
        let gameCountdownInterval = null;

        // Принудительный сброс iframe при закрытии модалки
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('gameModal');
            modal.addEventListener('hidden.bs.modal', function() {
                const frame = document.getElementById('gameFrame');
                // Полностью уничтожаем и пересоздаём iframe для сброса прогресса
                const parent = frame.parentNode;
                const newFrame = document.createElement('iframe');
                newFrame.id = 'gameFrame';
                newFrame.tabIndex = 0;
                newFrame.src = '';
                newFrame.style.cssText = 'width:100%;height:75vh;min-height:500px;border:none;';
                parent.replaceChild(newFrame, frame);
            });
        });

        // Обновление отображения основного таймера
        function updateMainTimerDisplay(seconds) {
            let el = document.getElementById('mainTimerDisplay');
            if (el) {
                el.textContent = Math.max(0, seconds);
            }
        }

        // Обновление отображения игрового таймера
        function updateGameTimerDisplay(seconds) {
            let el = document.getElementById('gameTimerDisplay');
            if (el) {
                el.textContent = Math.max(0, seconds);
            }
        }

        // Открыть игру
        function startGame(gameEndTime) {
            if (gameActive) return;
            gameActive = true;

            if (!gameModalInstance) {
                gameModalInstance = new bootstrap.Modal(document.getElementById('gameModal'), {
                    focus: false
                });
            }

            // Показываем модалку сразу
            gameModalInstance.show();

            const frame = document.getElementById('gameFrame');

            // Загружаем игру
            frame.src = 'https://boolean.method.ac/';

            // После загрузки передаём фокус
            frame.onload = function() {
                let focusAttempts = 0;
                const focusInterval = setInterval(function() {
                    frame.focus();
                    focusAttempts++;
                    if (focusAttempts >= 5) {
                        clearInterval(focusInterval);
                    }
                }, 200);
            };

            // Отправить на сервер что игра началась
            fetch('index.php?ajax=game_timer', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=game_started'
            });

            updateGameTimerDisplay(gameEndTime - Math.floor(Date.now() / 1000));

            // Запускаем интервал проверки
            gameCountdownInterval = setInterval(function() {
                const now = Math.floor(Date.now() / 1000);
                const remaining = gameEndTime - now;
                updateGameTimerDisplay(remaining);

                if (remaining <= 0) {
                    clearInterval(gameCountdownInterval);
                    gameModalInstance.hide();
                    gameActive = false;

                    // Сообщить серверу что игра завершена
                    fetch('index.php?ajax=game_timer', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'action=game_finished'
                    }).then(function() {
                        return fetch('index.php?ajax=game_timer', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: 'action=get_state'
                        });
                    }).then(function(response) {
                        return response.json();
                    }).then(function(data) {
                        startCountdown(data);
                    });
                }
            }, 1000);
        }

        // Обратный отсчёт
        function startCountdown(data) {
            const timerStart = data.timer_start;
            const interval = data.interval;
            const targetTime = timerStart + interval;

            function tick() {
                const now = Math.floor(Date.now() / 1000);
                const remaining = targetTime - now;

                if (remaining <= 0) {
                    // Время пришло — открыть игру
                    const duration = data.duration;
                    const gameEndTime = now + duration;
                    startGame(gameEndTime);
                    return;
                }

                updateMainTimerDisplay(remaining);
                setTimeout(tick, 1000);
            }

            tick();
        }

        // Получаем начальное состояние
        function init() {
            fetch('index.php?ajax=game_timer', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=get_state'
            }).then(function(response) {
                return response.json();
            }).then(function(data) {
                const now = Math.floor(Date.now() / 1000);

                if (data.game_active && data.game_end_time) {
                    // Игра уже активна (началась на другой вкладке)
                    startGame(data.game_end_time);
                } else {
                    // Игра не активна — считаем обратный отсчёт
                    startCountdown(data);
                }
            });
        }

        // Запуск
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    })();
    </script>

    </body>
    </html>
    <?php
}
?>