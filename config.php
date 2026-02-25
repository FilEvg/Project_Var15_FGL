<?php
// config.php - подключение к базам данных
session_start();

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
        return 'Администратор';
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
        <link rel="stylesheet" href="assets/css/style.css">
    </head>
    <body>
        <?php if (isLoggedIn() || isGuest()): ?>
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
            <div class="container-fluid">
                <a class="navbar-brand" href="index.php">
                    <i class="bi bi-graph-up"></i> <?php echo SITE_NAME; ?>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php"><i class="bi bi-house"></i> Главная</a>
                        </li>
                        
                        <!-- Все роли видят эти пункты -->
                        <li class="nav-item">
                            <a class="nav-link" href="products.php"><i class="bi bi-box"></i> Товары</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="sales.php"><i class="bi bi-cart-check"></i> Продажи</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="competitors.php"><i class="bi bi-bar-chart"></i> Конкуренты</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php"><i class="bi bi-file-earmark-text"></i> Отчеты</a>
                        </li>
                        
                        <!-- Только админы видят управление пользователями -->
                        <?php if (isAdmin()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="admin_users.php"><i class="bi bi-people"></i> Пользователи</a>
                        </li>
                        <?php endif; ?>
                    </ul>
                    
                    <div class="navbar-nav">
                        <?php if (isLoggedIn()): ?>
                            <span class="navbar-text me-3">
                                <i class="bi bi-person-circle"></i> 
                                <?php echo $_SESSION['full_name']; ?>
                                <small class="ms-1 badge bg-<?php echo getRoleBadgeColor(); ?>">
                                    <?php echo getRoleDisplayName(); ?>
                                </small>
                            </span>
                            <a href="logout.php" class="btn btn-outline-light btn-sm">
                                <i class="bi bi-box-arrow-right"></i> Выход
                            </a>
                        <?php elseif (isGuest()): ?>
                            <span class="navbar-text me-3">
                                <i class="bi bi-eye"></i> Гостевой режим
                            </span>
                            <a href="login.php?end_guest=true" class="btn btn-outline-light btn-sm">
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
                <?php echo $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
        <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
        <div class="container">
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success']; ?>
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
    <footer class="mt-5 pt-4 border-top">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="bi bi-graph-up"></i> <?php echo SITE_NAME; ?></h5>
                    <p class="text-muted">Система исследования товарного рынка</p>
                </div>
                <div class="col-md-6 text-end">
                    <p class="text-muted">
                        &copy; <?php echo date('Y'); ?> Все права защищены.<br>
                        <small>Версия 1.0 | 
                        <?php 
                        if (isGuestUser()) {
                            echo '<span class="badge bg-secondary">Гостевой режим</span>';
                        } elseif (isLoggedIn()) {
                            echo '<span class="badge bg-' . getRoleBadgeColor() . '">' . $_SESSION['username'] . ' (' . getRoleDisplayName() . ')</span>';
                        }
                        ?>
                        </small>
                    </p>
                </div>
            </div>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
}
?>