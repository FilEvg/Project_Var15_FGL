<?php
// Включим отображение ошибок
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

// Только администраторы могут управлять пользователями
if (!isLoggedIn() || !isAdmin()) {
    $_SESSION['error'] = 'Доступ запрещен. Требуются права администратора.';
    header("Location: index.php");
    exit();
}

$conn = getConnection();
$message = '';

// Режимы работы
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'view';
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Получение списка всех ролей
$roles = [];
$result = $conn->query("SELECT id, name, description FROM roles ORDER BY id");
while ($row = $result->fetch_assoc()) {
    $roles[] = $row;
}

// ОБРАБОТКА ДЕЙСТВИЙ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Добавление пользователя
    if (isset($_POST['add_user'])) {
        $username = trim($_POST['username']);
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $role_id = intval($_POST['role_id']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Простая валидация
        if ($password !== $confirm_password) {
            $message = '<div class="alert alert-danger">Пароли не совпадают</div>';
        } elseif (strlen($password) < 6) {
            $message = '<div class="alert alert-danger">Пароль должен содержать минимум 6 символов</div>';
        } elseif (empty($username) || empty($full_name)) {
            $message = '<div class="alert alert-danger">Заполните все обязательные поля</div>';
        } else {
            // Проверяем существование пользователя
            $check_sql = "SELECT id FROM users WHERE username = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $username);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $message = '<div class="alert alert-danger">Пользователь с таким логином уже существует</div>';
            } else {
                // Добавляем пользователя в основную БД (без пароля)
                $sql = "INSERT INTO users (username, full_name, email, is_active) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssi", $username, $full_name, $email, $is_active);
                
                if ($stmt->execute()) {
                    $new_user_id = $stmt->insert_id;
                    
                    // Синхронизируем пароль в локальную БД (там он хэшируется)
                    if (syncUserToLocalDB($new_user_id, $username, $password)) {
                        // Назначаем роль
                        $role_sql = "INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)";
                        $role_stmt = $conn->prepare($role_sql);
                        $role_stmt->bind_param("ii", $new_user_id, $role_id);
                        $role_stmt->execute();
                        
                        $message = '<div class="alert alert-success">Пользователь успешно создан</div>';
                    } else {
                        // Откатываем создание пользователя, если не удалось сохранить пароль
                        $delete_sql = "DELETE FROM users WHERE id = ?";
                        $delete_stmt = $conn->prepare($delete_sql);
                        $delete_stmt->bind_param("i", $new_user_id);
                        $delete_stmt->execute();
                        
                        $message = '<div class="alert alert-danger">Ошибка при сохранении пароля</div>';
                    }
                } else {
                    $message = '<div class="alert alert-danger">Ошибка при создании пользователя: ' . $conn->error . '</div>';
                }
            }
        }
    }
    
    // Редактирование пользователя
    if (isset($_POST['edit_user'])) {
        $user_id = intval($_POST['user_id']);
        $username = trim($_POST['username']);
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $role_id = intval($_POST['role_id']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $change_password = isset($_POST['change_password']) ? 1 : 0;
        
        // Не позволяем редактировать себя
        if ($user_id == $_SESSION['user_id']) {
            $message = '<div class="alert alert-danger">Вы не можете редактировать свою собственную учетную запись</div>';
        } else {
            // Проверяем уникальность логина
            $check_sql = "SELECT id FROM users WHERE username = ? AND id != ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("si", $username, $user_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $message = '<div class="alert alert-danger">Пользователь с таким логином уже существует</div>';
            } else {
                // Обновляем данные в основной БД
                $sql = "UPDATE users SET username = ?, full_name = ?, email = ?, is_active = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssi", $username, $full_name, $email, $is_active, $user_id);
                
                if ($stmt->execute()) {
                    // Меняем пароль если нужно
                    if ($change_password && !empty($_POST['password'])) {
                        $password = $_POST['password'];
                        $confirm_password = $_POST['confirm_password'];
                        
                        if ($password === $confirm_password && strlen($password) >= 6) {
                            // Обновляем пароль в локальной БД
                            syncUserToLocalDB($user_id, $username, $password);
                        }
                    }
                    
                    // Обновляем роль
                    $role_sql = "DELETE FROM user_roles WHERE user_id = ?";
                    $role_stmt = $conn->prepare($role_sql);
                    $role_stmt->bind_param("i", $user_id);
                    $role_stmt->execute();
                    
                    $role_sql = "INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)";
                    $role_stmt = $conn->prepare($role_sql);
                    $role_stmt->bind_param("ii", $user_id, $role_id);
                    $role_stmt->execute();
                    
                    $message = '<div class="alert alert-success">Данные пользователя обновлены</div>';
                } else {
                    $message = '<div class="alert alert-danger">Ошибка при обновлении пользователя</div>';
                }
            }
        }
    }
}

// Обработка удаления пользователя
if ($action == 'delete' && $user_id > 0) {
    // Не позволяем удалить себя
    if ($user_id == $_SESSION['user_id']) {
        $message = '<div class="alert alert-danger">Вы не можете удалить свою собственную учетную запись</div>';
    } else {
        // Проверяем подтверждение удаления
        if (isset($_GET['confirm']) && $_GET['confirm'] == 'yes') {
            // Удаляем пользователя из основной БД
            $sql = "DELETE FROM users WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            
            if ($stmt->execute()) {
                // Удаляем запись из локальной БД
                deleteUserFromLocalDB($user_id);
                
                $message = '<div class="alert alert-success">Пользователь успешно удален</div>';
                // Очищаем параметры для возврата к списку
                $action = '';
                $user_id = 0;
            } else {
                $message = '<div class="alert alert-danger">Ошибка при удалении пользователя</div>';
            }
        }
    }
}

// Получение данных пользователя для редактирования
$selected_user = null;
if ($mode == 'edit' && $user_id > 0) {
    $sql = "SELECT id, username, full_name, email, is_active FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $selected_user = $result->fetch_assoc();
        
        // Получаем роль пользователя
        $role_sql = "SELECT r.id FROM user_roles ur JOIN roles r ON ur.role_id = r.id WHERE ur.user_id = ?";
        $role_stmt = $conn->prepare($role_sql);
        $role_stmt->bind_param("i", $user_id);
        $role_stmt->execute();
        $role_result = $role_stmt->get_result();
        if ($role_result->num_rows === 1) {
            $role_row = $role_result->fetch_assoc();
            $selected_user['current_role_id'] = $role_row['id'];
        } else {
            $selected_user['current_role_id'] = 0;
        }
    }
}

// Получение данных пользователя для подтверждения удаления
$user_to_delete = null;
if ($action == 'delete' && $user_id > 0 && (!isset($_GET['confirm']) || $_GET['confirm'] != 'yes')) {
    $sql = "SELECT id, username, full_name FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $user_to_delete = $result->fetch_assoc();
    }
}

// Получение списка всех пользователей
$users = [];
$sql = "SELECT u.id, u.username, u.full_name, u.email, u.is_active, 
               r.name as role_name
        FROM users u
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        LEFT JOIN roles r ON ur.role_id = r.id
        ORDER BY u.id";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

// Отображение страницы
displayHeader('Управление пользователями');
?>

<div class="container mt-4">
    <h2><i class="bi bi-people"></i> Управление пользователями</h2>
    
    <?php echo $message; ?>
    
    <?php if ($action == 'delete' && $user_to_delete): ?>
    <!-- ПОДТВЕРЖДЕНИЕ УДАЛЕНИЯ -->
    <div class="row">
        <div class="col-md-6 offset-md-3">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <i class="bi bi-exclamation-triangle"></i> Подтверждение удаления
                </div>
                <div class="card-body text-center">
                    <h5 class="card-title">Вы уверены, что хотите удалить пользователя?</h5>
                    
                    <div class="alert alert-warning mt-3">
                        <i class="bi bi-info-circle"></i> 
                        <strong>Внимание!</strong> Это действие нельзя отменить. Будут удалены:
                        <ul class="text-start mt-2">
                            <li>Данные пользователя из основной БД</li>
                            <li>Данные авторизации из локальной БД</li>
                            <li>Назначенные роли пользователя</li>
                        </ul>
                    </div>
                    
                    <div class="user-info p-3 border rounded mb-4">
                        <p><strong>Логин:</strong> <?php echo htmlspecialchars($user_to_delete['username']); ?></p>
                        <p><strong>ФИО:</strong> <?php echo htmlspecialchars($user_to_delete['full_name']); ?></p>
                        <p><strong>ID:</strong> <?php echo $user_to_delete['id']; ?></p>
                    </div>
                    
                    <div class="mt-4">
                        <a href="?action=delete&user_id=<?php echo $user_to_delete['id']; ?>&confirm=yes" 
                           class="btn btn-danger btn-lg me-3">
                            <i class="bi bi-trash"></i> Да, удалить
                        </a>
                        <a href="admin_users.php" class="btn btn-secondary btn-lg">
                            <i class="bi bi-x-circle"></i> Отмена
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php elseif ($mode == 'add' || $mode == 'edit'): ?>
    <!-- ФОРМА ДОБАВЛЕНИЯ/РЕДАКТИРОВАНИЯ -->
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header <?php echo $mode == 'add' ? 'bg-success' : 'bg-warning'; ?> text-white">
                    <?php echo $mode == 'add' ? 'Добавление нового пользователя' : 'Редактирование пользователя'; ?>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?php if ($mode == 'edit' && isset($selected_user)): ?>
                        <input type="hidden" name="user_id" value="<?php echo $selected_user['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Логин *</label>
                            <input type="text" name="username" class="form-control" 
                                   value="<?php echo isset($selected_user['username']) ? htmlspecialchars($selected_user['username']) : ''; ?>" 
                                   required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">ФИО *</label>
                            <input type="text" name="full_name" class="form-control" 
                                   value="<?php echo isset($selected_user['full_name']) ? htmlspecialchars($selected_user['full_name']) : ''; ?>" 
                                   required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?php echo isset($selected_user['email']) ? htmlspecialchars($selected_user['email']) : ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Роль *</label>
                            <select name="role_id" class="form-select" required>
                                <option value="">-- Выберите роль --</option>
                                <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>"
                                    <?php if (isset($selected_user['current_role_id']) && $selected_user['current_role_id'] == $role['id']) echo 'selected'; ?>>
                                    <?php echo $role['name']; ?> - <?php echo $role['description']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <?php if ($mode == 'add'): ?>
                        <div class="mb-3">
                            <label class="form-label">Пароль *</label>
                            <input type="password" name="password" class="form-control" required minlength="6">
                            <small class="text-muted">Минимум 6 символов</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Подтверждение пароля *</label>
                            <input type="password" name="confirm_password" class="form-control" required minlength="6">
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($mode == 'edit'): ?>
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="change_password" class="form-check-input" id="change_password">
                                <label class="form-check-label" for="change_password">Сменить пароль</label>
                            </div>
                            
                            <div id="password_fields" style="display: none; margin-top: 10px;">
                                <div class="mb-3">
                                    <label class="form-label">Новый пароль</label>
                                    <input type="password" name="password" class="form-control" minlength="6">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Подтверждение пароля</label>
                                    <input type="password" name="confirm_password" class="form-control" minlength="6">
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" class="form-check-input" id="is_active"
                                    <?php 
                                    if (isset($selected_user['is_active']) && $selected_user['is_active'] == 1) echo 'checked'; 
                                    elseif ($mode == 'add') echo 'checked'; 
                                    ?>>
                                <label class="form-check-label" for="is_active">Активен</label>
                            </div>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" name="<?php echo $mode == 'add' ? 'add_user' : 'edit_user'; ?>" 
                                    class="btn <?php echo $mode == 'add' ? 'btn-success' : 'btn-warning'; ?>">
                                <?php echo $mode == 'add' ? 'Создать пользователя' : 'Сохранить изменения'; ?>
                            </button>
                            <a href="admin_users.php" class="btn btn-secondary">Отмена</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <?php else: ?>
    <!-- СПИСОК ПОЛЬЗОВАТЕЛЕЙ -->
    <div class="card">
        <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
            <div>
                <i class="bi bi-people"></i> Список пользователей (<?php echo count($users); ?>)
            </div>
            <a href="?mode=add" class="btn btn-success btn-sm">
                <i class="bi bi-person-plus"></i> Добавить пользователя
            </a>
        </div>
        <div class="card-body">
            <?php if (empty($users)): ?>
                <p class="text-muted">Пользователи не найдены</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Логин</th>
                                <th>ФИО</th>
                                <th>Email</th>
                                <th>Роль</th>
                                <th>Статус</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): 
                                $role_color = '';
                                if ($user['role_name'] == 'admin') {
                                    $role_color = 'danger';
                                } elseif ($user['role_name'] == 'user') {
                                    $role_color = 'primary';
                                } elseif ($user['role_name'] == 'guest') {
                                    $role_color = 'info';
                                } else {
                                    $role_color = 'secondary';
                                }
                            ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $role_color; ?>">
                                        <?php echo $user['role_name'] ?: 'Не назначена'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $user['is_active'] ? 'success' : 'danger'; ?>">
                                        <?php echo $user['is_active'] ? 'Активен' : 'Неактивен'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <a href="?mode=edit&user_id=<?php echo $user['id']; ?>" 
                                           class="btn btn-warning" title="Редактировать">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="?action=delete&user_id=<?php echo $user['id']; ?>" 
                                           class="btn btn-danger" title="Удалить"
                                           onclick="return confirm('Вы действительно хотите удалить пользователя <?php echo addslashes($user['username']); ?>?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                        <?php else: ?>
                                        <span class="btn btn-outline-secondary disabled" title="Это ваш аккаунт">
                                            <i class="bi bi-person-check"></i>
                                        </span>
                                        <span class="btn btn-outline-secondary disabled" title="Нельзя удалить свой аккаунт">
                                            <i class="bi bi-trash"></i>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="mt-3">
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Назад в панель
        </a>
    </div>
    <?php endif; ?>
</div>

<?php if ($mode == 'edit'): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkbox = document.getElementById('change_password');
    const fields = document.getElementById('password_fields');
    
    if (checkbox && fields) {
        checkbox.addEventListener('change', function() {
            fields.style.display = this.checked ? 'block' : 'none';
        });
    }
});
</script>
<?php endif; ?>

<?php 
$conn->close();
displayFooter();
?>