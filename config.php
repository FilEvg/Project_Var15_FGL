<?php
// config.php - подключение к базе данных
session_start();

define('DB_HOST', 'localhost');
define('DB_NAME', 'project_Filippov');
define('DB_USER', 'admin');
define('DB_PASS', 'admin');
define('SITE_NAME', 'Система исследования рынка');

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

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

function isGuest() {
    return isset($_SESSION['guest']) && $_SESSION['guest'] === true;
}

function endGuestMode() {
    if (isset($_SESSION['guest'])) {
        unset($_SESSION['guest']);
        unset($_SESSION['full_name']);
    }
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function sanitize($data) {
    $conn = getConnection();
    return mysqli_real_escape_string($conn, htmlspecialchars(trim($data)));
}

// Функция для проверки прав на редактирование
function canEdit() {
    return isLoggedIn(); // Гости не могут редактировать
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
                    </ul>
                    <div class="navbar-nav">
                        <?php if (isLoggedIn()): ?>
                            <span class="navbar-text me-3">
                                <i class="bi bi-person-circle"></i> <?php echo $_SESSION['full_name']; ?>
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
                        if (isset($_SESSION['guest']) && $_SESSION['guest'] === true) {
                            echo '<span class="badge bg-info">Гостевой режим</span>';
                        } elseif (isset($_SESSION['username'])) {
                            echo '<span class="badge bg-success">' . $_SESSION['username'] . '</span>';
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