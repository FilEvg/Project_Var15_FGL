<?php
require_once 'config.php';

// Гости могут просматривать главную без входа
if (!isLoggedIn() && !isGuest()) {
    redirect('login.php');
}

$conn = getConnection();
?>
<?php displayHeader('Главная'); ?>

<div class="container">
    <?php if (isGuest()): ?>
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <i class="bi bi-info-circle"></i> Вы находитесь в <strong>гостевом режиме</strong>. Для редактирования данных необходимо <a href="login.php" class="alert-link">войти в систему</a>.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <h2 class="mb-4">Панель управления</h2>
    
    <div class="row">
        <!-- Карточки функционала -->
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="bi bi-box dashboard-icon"></i>
                    <h5 class="card-title mt-2">Товары</h5>
                    <p class="card-text"><?php echo isGuest() ? 'Просмотр товаров' : 'Управление товарами компании'; ?></p>
                    <a href="products.php" class="btn btn-primary">Перейти</a>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="bi bi-cart-check dashboard-icon"></i>
                    <h5 class="card-title mt-2">Продажи</h5>
                    <p class="card-text"><?php echo isGuest() ? 'Просмотр продаж' : 'Учет продаж по подразделениям'; ?></p>
                    <a href="sales.php" class="btn btn-primary">Перейти</a>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="bi bi-bar-chart dashboard-icon"></i>
                    <h5 class="card-title mt-2">Конкуренты</h5>
                    <p class="card-text"><?php echo isGuest() ? 'Просмотр конкурентов' : 'Мониторинг цен конкурентов'; ?></p>
                    <a href="competitors.php" class="btn btn-primary">Перейти</a>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="bi bi-file-earmark-text dashboard-icon"></i>
                    <h5 class="card-title mt-2">Отчеты</h5>
                    <p class="card-text"><?php echo isGuest() ? 'Просмотр отчетов' : 'Анализ динамики продаж'; ?></p>
                    <a href="reports.php" class="btn btn-primary">Перейти</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Быстрая статистика -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-speedometer2"></i> Быстрая статистика
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <?php
                        $stats = [
                            ['query' => "SELECT COUNT(*) as count FROM products WHERE is_active = 1", 'label' => 'Активных товаров'],
                            ['query' => "SELECT COUNT(*) as count FROM subdivisions WHERE is_active = 1", 'label' => 'Подразделений'],
                            ['query' => "SELECT COUNT(*) as count FROM competitors", 'label' => 'Конкурентов'],
                            ['query' => "SELECT SUM(quantity) as total FROM sales WHERE MONTH(sale_date) = MONTH(CURDATE())", 'label' => 'Продаж за месяц']
                        ];
                        
                        foreach ($stats as $stat) {
                            $result = $conn->query($stat['query']);
                            $row = $result->fetch_assoc();
                            echo '<div class="col-md-3">
                                    <h3>' . ($row['total'] ?? $row['count']) . '</h3>
                                    <p class="text-muted">' . $stat['label'] . '</p>
                                  </div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
$conn->close();
displayFooter();
?>