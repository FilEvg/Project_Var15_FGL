<?php
require_once 'config.php';

// Завершение гостевого режима
if (isset($_GET['end_guest'])) {
    endGuestMode();
    // Не делаем редирект, чтобы пользователь мог войти в систему
}

// Если пользователь уже авторизован (не гость), перенаправляем на главную
if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    
    $conn = getConnection();
    $sql = "SELECT id, username, password, full_name FROM users WHERE username = ? AND is_active = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if ($password === $user['password']) {
            // Завершаем гостевой режим, если он был
            endGuestMode();
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            redirect('index.php');
        } else {
            $error = 'Неверный пароль';
        }
    } else {
        $error = 'Пользователь не найден';
    }
    $conn->close();
}

// Гостевой вход
if (isset($_GET['guest'])) {
    $_SESSION['guest'] = true;
    $_SESSION['full_name'] = 'Гость';
    redirect('index.php');
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Вход</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); height: 100vh; }
        .login-card { max-width: 400px; margin: 100px auto; border-radius: 15px; }
        .login-header { background-color: #007bff; color: white; border-radius: 15px 15px 0 0; }
        .guest-notice { 
            background-color: rgba(13, 202, 240, 0.1); 
            border-left: 4px solid #0dcaf0;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card login-card shadow">
            <div class="card-header login-header text-center py-3">
                <h4><i class="bi bi-graph-up"></i> <?php echo SITE_NAME; ?></h4>
                <p class="mb-0">Система исследования товарного рынка</p>
            </div>
            <div class="card-body p-4">
                <h5 class="card-title text-center mb-4">Вход в систему</h5>
                
                <?php if (isset($_GET['end_guest'])): ?>
                <div class="guest-notice">
                    <i class="bi bi-info-circle"></i> Гостевой режим завершен. Войдите в систему для редактирования данных.
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="username" class="form-label">Логин:</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Пароль:</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-box-arrow-in-right"></i> Войти
                        </button>
                        
                        <a href="?guest=true" class="btn btn-outline-secondary btn-lg">
                            <i class="bi bi-eye"></i> Продолжить без входа
                        </a>
                    </div>
                </form>
                
                <div class="mt-3 text-center">
                    <small class="text-muted">Тестовые данные: admin / admin123</small>
                    <div class="mt-2">
                        <small class="text-info">
                            <i class="bi bi-info-circle"></i> В гостевом режиме доступен только просмотр данных
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>