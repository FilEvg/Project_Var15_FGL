-- phpMyAdmin SQL Dump
-- version 5.1.1deb5ubuntu1
-- https://www.phpmyadmin.net/
--
-- Хост: localhost:3306
-- Время создания: Мар 18 2026 г., 10:41
-- Версия сервера: 10.6.22-MariaDB-0ubuntu0.22.04.1
-- Версия PHP: 8.1.2-1ubuntu2.23

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `project_Filippov`
--

DELIMITER $$
--
-- Процедуры
--
CREATE DEFINER=`Filippov`@`%` PROCEDURE `AddCompetitorPrice` (IN `p_competitor_id` INT, IN `p_product_id` INT, IN `p_price` DECIMAL(12,2), IN `p_notes` TEXT)  BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SELECT 'Ошибка при добавлении цены' AS error_message;
    END;
    
    START TRANSACTION;
    
    INSERT INTO competitor_prices 
    (competitor_id, product_id, check_date, price, notes)
    VALUES 
    (p_competitor_id, p_product_id, CURDATE(), p_price, p_notes);
    
    SELECT CONCAT('Цена добавлена с ID = ', LAST_INSERT_ID()) AS result;
    
    COMMIT;
END$$

CREATE DEFINER=`Filippov`@`%` PROCEDURE `AnalyzePricingStrategy` (IN `category_param` VARCHAR(100), IN `threshold_percent` DECIMAL(5,2))  BEGIN
    -- Сравниваем наши цены с ценами конкурентов
    SELECT 
        p.id as product_id,
        p.name as product_name,
        p.category,
        AVG(s.total_amount / s.quantity) as our_avg_price,
        GetMinCompetitorPrice(p.id) as min_competitor_price,
        GetMinCompetitorPrice(p.id) - AVG(s.total_amount / s.quantity) as price_difference,
        CASE 
            WHEN GetMinCompetitorPrice(p.id) = 0 THEN 'Нет данных'
            WHEN AVG(s.total_amount / s.quantity) > GetMinCompetitorPrice(p.id) * (1 + threshold_percent/100) 
                THEN 'Цена выше конкурентов'
            WHEN AVG(s.total_amount / s.quantity) < GetMinCompetitorPrice(p.id) 
                THEN 'Цена ниже конкурентов'
            ELSE 'Цена в допустимом диапазоне'
        END as price_position
    FROM products p
    LEFT JOIN sales s ON p.id = s.product_id
    WHERE p.category = category_param OR category_param IS NULL
    GROUP BY p.id
    HAVING our_avg_price IS NOT NULL
    ORDER BY price_difference DESC;
END$$

CREATE DEFINER=`Filippov`@`%` PROCEDURE `BulkUpdateCompetitorPrices` (IN `competitor_id_param` INT, IN `product_id_param` INT, IN `new_price` DECIMAL(12,2), IN `update_all` BOOLEAN)  BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SELECT 'Ошибка при обновлении цен' AS error_message;
    END;
    
    START TRANSACTION;
    
    IF update_all THEN
        -- Обновляем все записи конкурента по этому товару
        UPDATE competitor_prices
        SET price = new_price,
            notes = CONCAT(notes, ' [Массовое обновление ', NOW(), ']')
        WHERE competitor_id = competitor_id_param
          AND product_id = product_id_param;
          
        SELECT CONCAT('Обновлено ', ROW_COUNT(), ' записей') AS result;
    ELSE
        -- Добавляем новую запись
        INSERT INTO competitor_prices 
        (competitor_id, product_id, check_date, price, notes)
        VALUES 
        (competitor_id_param, product_id_param, CURDATE(), new_price, 
         CONCAT('Массовое добавление ', NOW()));
         
        SELECT CONCAT('Добавлена новая запись с ID = ', LAST_INSERT_ID()) AS result;
    END IF;
    
    COMMIT;
END$$

CREATE DEFINER=`Filippov`@`%` PROCEDURE `FindBestAlternatives` (IN `product_id_param` INT)  BEGIN
    -- Ищем альтернативы в той же категории
    SELECT 
        p.id,
        p.name,
        p.category,
        p.internal_code,
        (SELECT AVG(s.total_amount/s.quantity) 
         FROM sales s 
         WHERE s.product_id = p.id 
         LIMIT 1) as avg_price,
        (SELECT COUNT(*) FROM sales WHERE product_id = p.id) as sales_count,
        (SELECT SUM(quantity) FROM sales WHERE product_id = p.id) as total_sold
    FROM products p
    WHERE p.category = (SELECT category FROM products WHERE id = product_id_param)
      AND p.id != product_id_param
      AND p.is_active = 1
    ORDER BY sales_count DESC, total_sold DESC
    LIMIT 5;
END$$

CREATE DEFINER=`Filippov`@`%` PROCEDURE `GenerateSalesReport` (IN `start_date` DATE, IN `end_date` DATE, IN `group_by_level` VARCHAR(20))  BEGIN
    -- Отчет по городам
    IF group_by_level = 'city' THEN
        SELECT 
            sub.city,
            COUNT(DISTINCT s.id) as total_sales,
            SUM(s.quantity) as total_items,
            SUM(s.total_amount) as total_revenue,
            AVG(s.total_amount / s.quantity) as avg_price,
            COUNT(DISTINCT s.product_id) as unique_products
        FROM sales s
        JOIN subdivisions sub ON s.subdivision_id = sub.id
        WHERE s.sale_date BETWEEN start_date AND end_date
        GROUP BY sub.city
        ORDER BY total_revenue DESC;
    
    -- Отчет по категориям товаров
    ELSEIF group_by_level = 'category' THEN
        SELECT 
            p.category,
            COUNT(DISTINCT s.id) as total_sales,
            SUM(s.quantity) as total_items,
            SUM(s.total_amount) as total_revenue,
            AVG(s.total_amount / s.quantity) as avg_price,
            COUNT(DISTINCT s.product_id) as unique_products
        FROM sales s
        JOIN products p ON s.product_id = p.id
        WHERE s.sale_date BETWEEN start_date AND end_date
        GROUP BY p.category
        ORDER BY total_revenue DESC;
    
    -- Детальный отчет по подразделениям и товарам
    ELSE
        SELECT 
            sub.name as subdivision,
            p.name as product,
            p.category,
            SUM(s.quantity) as quantity,
            SUM(s.total_amount) as revenue,
            AVG(s.total_amount / s.quantity) as unit_price
        FROM sales s
        JOIN subdivisions sub ON s.subdivision_id = sub.id
        JOIN products p ON s.product_id = p.id
        WHERE s.sale_date BETWEEN start_date AND end_date
        GROUP BY sub.id, p.id
        WITH ROLLUP;
    END IF;
END$$

CREATE DEFINER=`Filippov`@`%` PROCEDURE `GetSalesStatistics` (IN `start_date` DATE, IN `end_date` DATE)  BEGIN
    -- Общая статистика
    SELECT 
        COUNT(DISTINCT s.id) as total_sales_count,
        SUM(s.quantity) as total_items_sold,
        SUM(s.total_amount) as total_revenue,
        AVG(s.total_amount) as avg_sale_amount,
        COUNT(DISTINCT s.product_id) as unique_products_sold,
        COUNT(DISTINCT s.subdivision_id) as active_subdivisions
    FROM sales s
    WHERE s.sale_date BETWEEN start_date AND end_date;
    
    -- Статистика по дням
    SELECT 
        DATE(s.sale_date) as sale_date,
        COUNT(*) as sales_count,
        SUM(s.quantity) as items_sold,
        SUM(s.total_amount) as daily_revenue
    FROM sales s
    WHERE s.sale_date BETWEEN start_date AND end_date
    GROUP BY DATE(s.sale_date)
    ORDER BY sale_date;
END$$

--
-- Функции
--
CREATE DEFINER=`Filippov`@`%` FUNCTION `GetMarketShare` (`product_id_param` INT, `month_param` INT, `year_param` INT) RETURNS DECIMAL(5,2) READS SQL DATA
    DETERMINISTIC
BEGIN
    DECLARE product_sales DECIMAL(12,2);
    DECLARE total_sales DECIMAL(12,2);
    DECLARE market_share DECIMAL(5,2);
    
    -- Продажи конкретного товара
    SELECT COALESCE(SUM(total_amount), 0) INTO product_sales
    FROM sales
    WHERE product_id = product_id_param
      AND MONTH(sale_date) = month_param
      AND YEAR(sale_date) = year_param;
    
    -- Общие продажи
    SELECT COALESCE(SUM(total_amount), 0) INTO total_sales
    FROM sales
    WHERE MONTH(sale_date) = month_param
      AND YEAR(sale_date) = year_param;
    
    IF total_sales > 0 THEN
        SET market_share = (product_sales / total_sales) * 100;
    ELSE
        SET market_share = 0;
    END IF;
    
    RETURN ROUND(market_share, 2);
END$$

CREATE DEFINER=`Filippov`@`%` FUNCTION `GetMinCompetitorPrice` (`product_id_param` INT) RETURNS DECIMAL(12,2) READS SQL DATA
    DETERMINISTIC
BEGIN
    DECLARE min_price DECIMAL(12,2);
    
    SELECT MIN(price) INTO min_price
    FROM competitor_prices
    WHERE product_id = product_id_param
      AND check_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY);
    
    RETURN COALESCE(min_price, 0);
END$$

CREATE DEFINER=`Filippov`@`%` FUNCTION `GetSubdivisionRating` (`subdivision_id_param` INT, `year_param` INT) RETURNS VARCHAR(10) CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci READS SQL DATA
    DETERMINISTIC
BEGIN
    DECLARE total_sales DECIMAL(12,2);
    DECLARE avg_sales DECIMAL(12,2);
    DECLARE rating VARCHAR(10);
    
    -- Сумма продаж подразделения за год
    SELECT COALESCE(SUM(total_amount), 0) INTO total_sales
    FROM sales
    WHERE subdivision_id = subdivision_id_param
      AND YEAR(sale_date) = year_param;
    
    -- Средние продажи по всем подразделениям
    SELECT COALESCE(AVG(monthly_total), 0) INTO avg_sales
    FROM (
        SELECT subdivision_id, SUM(total_amount) as monthly_total
        FROM sales
        WHERE YEAR(sale_date) = year_param
        GROUP BY subdivision_id, MONTH(sale_date)
    ) as monthly_sales;
    
    -- Определяем рейтинг
    IF avg_sales = 0 THEN
        SET rating = 'Нет данных';
    ELSEIF total_sales > avg_sales * 2 THEN
        SET rating = 'Отлично';
    ELSEIF total_sales > avg_sales THEN
        SET rating = 'Хорошо';
    ELSEIF total_sales > avg_sales * 0.5 THEN
        SET rating = 'Средне';
    ELSE
        SET rating = 'Низко';
    END IF;
    
    RETURN rating;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Структура таблицы `alternative_products`
--

CREATE TABLE `alternative_products` (
  `id` int(11) NOT NULL,
  `main_product_id` int(11) NOT NULL,
  `alternative_product_id` int(11) NOT NULL,
  `relation_type` enum('substitute','complement','analog') NOT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Дамп данных таблицы `alternative_products`
--

INSERT INTO `alternative_products` (`id`, `main_product_id`, `alternative_product_id`, `relation_type`, `notes`) VALUES
(1, 1, 2, 'substitute', 'Альтернатива флагманскому смартфону'),
(2, 1, 3, 'analog', 'Аналогичный флагман от другого производителя'),
(3, 2, 1, 'substitute', 'Альтернатива iPhone'),
(4, 3, 4, 'analog', 'Аналогичный сегмент премиум-смартфонов'),
(5, 5, 6, 'substitute', 'Игровые смартфоны'),
(6, 7, 8, 'substitute', 'Планшеты премиум-класса');

-- --------------------------------------------------------

--
-- Структура таблицы `competitors`
--

CREATE TABLE `competitors` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `website` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Дамп данных таблицы `competitors`
--

INSERT INTO `competitors` (`id`, `name`, `website`) VALUES
(1, 'Конкурент 1', 'https://competitor1.ru'),
(2, 'Конкурент 2', 'https://competitor2.com'),
(3, 'Конкурент 3', 'https://competitor3.net'),
(4, 'Магазин \"ТехноМир\"', 'https://technomir.ru'),
(5, 'Интернет-магазин \"ГаджетПро\"', 'https://gadgetpro.com');

-- --------------------------------------------------------

--
-- Структура таблицы `competitor_prices`
--

CREATE TABLE `competitor_prices` (
  `id` int(11) NOT NULL,
  `competitor_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `check_date` date NOT NULL,
  `price` decimal(12,2) NOT NULL,
  `product_name_at_competitor` varchar(200) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Дамп данных таблицы `competitor_prices`
--

INSERT INTO `competitor_prices` (`id`, `competitor_id`, `product_id`, `check_date`, `price`, `product_name_at_competitor`, `notes`) VALUES
(1, 1, 1, '2025-11-01', '97999.00', 'iPhone 14 Pro 128GB', 'В наличии, доставка завтра'),
(2, 2, 1, '2025-11-13', '98999.00', 'Apple iPhone 14 Pro', 'Скидка 5% при оплате онлайн'),
(3, 3, 1, '2025-11-26', '99999.00', 'iPhone 14 Pro', 'Последний экземпляр'),
(4, 4, 2, '2025-12-08', '87999.00', 'Samsung Galaxy S23', 'Акция: чехол в подарок'),
(5, 5, 2, '2025-12-21', '89999.00', 'Galaxy S23 256GB', 'Рассрочка 0%'),
(6, 1, 3, '2026-01-02', '68999.00', 'Xiaomi 13 Pro', 'Предзаказ'),
(7, 2, 3, '2026-01-15', '69999.00', 'Xiaomi 13 Pro Global', 'В наличии'),
(8, 3, 4, '2026-01-27', '59991.00', 'Google Pixel 7', 'Уцененный товар'),
(9, 4, 5, '2026-02-09', '62999.00', 'OnePlus 11', 'Бесплатная доставка'),
(10, 5, 6, '2026-02-21', '43999.00', 'Realme GT 3', 'Скидка 10% до конца недели'),
(11, 1, 7, '2026-03-06', '72999.00', 'iPad Air 5', 'Wi-Fi 64GB'),
(12, 2, 8, '2026-03-18', '63995.00', 'Samsung Tab S8', '11&amp;quot; 128GB'),
(16, 1, 4, '2026-03-19', '50.00', NULL, 'гг');

--
-- Триггеры `competitor_prices`
--
DELIMITER $$
CREATE TRIGGER `after_competitor_prices_insert` AFTER INSERT ON `competitor_prices` FOR EACH ROW BEGIN
    INSERT INTO price_change_log 
    (competitor_price_id, old_price, new_price, change_date, changed_by, action_type)
    VALUES 
    (NEW.id, NULL, NEW.price, NOW(), 
     COALESCE(@current_user, 'system'), 'INSERT');
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_competitor_prices_update` AFTER UPDATE ON `competitor_prices` FOR EACH ROW BEGIN
    IF OLD.price != NEW.price THEN
        INSERT INTO price_change_log 
        (competitor_price_id, old_price, new_price, change_date, changed_by, action_type)
        VALUES 
        (NEW.id, OLD.price, NEW.price, NOW(), 
         COALESCE(@current_user, 'system'), 'UPDATE');
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `before_competitor_prices_insert` BEFORE INSERT ON `competitor_prices` FOR EACH ROW BEGIN
    DECLARE min_existing_price DECIMAL(12,2);
    
    -- Проверяем, не пытаются ли вставить цену ниже минимальной за последние 30 дней
    SELECT MIN(price) INTO min_existing_price
    FROM competitor_prices
    WHERE product_id = NEW.product_id
      AND competitor_id = NEW.competitor_id
      AND check_date >= DATE_SUB(NEW.check_date, INTERVAL 30 DAY);
    
    IF min_existing_price IS NOT NULL AND NEW.price < min_existing_price * 0.7 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Цена слишком низкая по сравнению с историческими данными';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Дублирующая структура для представления `current_competitor_prices`
-- (См. Ниже фактическое представление)
--
CREATE TABLE `current_competitor_prices` (
`id` int(11)
,`competitor_name` varchar(200)
,`product_name` varchar(200)
,`category` varchar(100)
,`price` decimal(12,2)
,`last_check_date` date
,`product_name_at_competitor` varchar(200)
,`notes` text
,`days_ago` int(8)
);

-- --------------------------------------------------------

--
-- Структура таблицы `price_change_log`
--

CREATE TABLE `price_change_log` (
  `id` int(11) NOT NULL,
  `competitor_price_id` int(11) DEFAULT NULL,
  `old_price` decimal(12,2) DEFAULT NULL,
  `new_price` decimal(12,2) DEFAULT NULL,
  `change_date` datetime DEFAULT NULL,
  `changed_by` varchar(50) DEFAULT NULL,
  `action_type` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Дамп данных таблицы `price_change_log`
--

INSERT INTO `price_change_log` (`id`, `competitor_price_id`, `old_price`, `new_price`, `change_date`, `changed_by`, `action_type`) VALUES
(1, 13, NULL, '5055505.00', '2026-03-18 09:48:53', 'system', 'INSERT'),
(2, 14, NULL, '5.00', '2026-03-18 09:49:27', 'system', 'INSERT'),
(3, 15, NULL, '666.00', '2026-03-18 10:06:55', 'system', 'INSERT'),
(4, 12, '63999.00', '63991.00', '2026-03-18 10:30:10', 'system', 'UPDATE'),
(5, 8, '59999.00', '59991.00', '2026-03-18 10:30:28', 'system', 'UPDATE'),
(6, 12, '63991.00', '63995.00', '2026-03-18 10:40:07', 'admin', 'UPDATE'),
(7, 16, NULL, '50.00', '2026-03-18 10:40:57', 'admin', 'INSERT');

-- --------------------------------------------------------

--
-- Структура таблицы `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `internal_code` varchar(50) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Дамп данных таблицы `products`
--

INSERT INTO `products` (`id`, `name`, `internal_code`, `category`, `description`, `is_active`) VALUES
(1, 'Смартфон iPhone 14 Pro', 'SM-001', 'Смартфоны', 'Флагманский смартфон Apple с камерой 48 Мп', 1),
(2, 'Смартфон Samsung Galaxy S23', 'SM-002', 'Смартфоны', 'Флагман Samsung с процессором Snapdragon 8 Gen 2', 1),
(3, 'Смартфон Xiaomi 13 Pro', 'SM-003', 'Смартфоны', 'Флагман Xiaomi с камерой Leica', 1),
(4, 'Смартфон Google Pixel 7', 'SM-004', 'Смартфоны', 'Смартфон с лучшей камерой по версии DxOMark', 1),
(5, 'Смартфон OnePlus 11', 'SM-005', 'Смартфоны', 'Флагманский смартфон с быстрой зарядкой', 1),
(6, 'Смартфон Realme GT 3', 'SM-006', 'Смартфоны', 'Игровой смартфон с дисплеем 144 Гц', 1),
(7, 'Планшет iPad Air 5', 'TAB-001', 'Планшеты', 'Планшет Apple с процессором M1', 1),
(8, 'Планшет Samsung Tab S8', 'TAB-002', 'Планшеты', 'Флагманский планшет Samsung', 1);

--
-- Триггеры `products`
--
DELIMITER $$
CREATE TRIGGER `after_product_insert` AFTER INSERT ON `products` FOR EACH ROW BEGIN
    DECLARE similar_count INT;
    
    -- Ищем товары в той же категории
    SELECT COUNT(*) INTO similar_count
    FROM products
    WHERE category = NEW.category 
      AND id != NEW.id 
      AND is_active = 1;
    
    -- Если есть похожие товары, добавляем их как аналоги
    IF similar_count > 0 THEN
        INSERT INTO alternative_products (main_product_id, alternative_product_id, relation_type, notes)
        SELECT NEW.id, id, 'analog', CONCAT('Автоматически добавленный аналог из категории ', category)
        FROM products
        WHERE category = NEW.category AND id != NEW.id AND is_active = 1
        LIMIT 3; -- Ограничиваем количество аналогов
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Дублирующая структура для представления `product_sales_stats`
-- (См. Ниже фактическое представление)
--
CREATE TABLE `product_sales_stats` (
`id` int(11)
,`name` varchar(200)
,`category` varchar(100)
,`internal_code` varchar(50)
,`total_sales_count` bigint(21)
,`total_quantity_sold` decimal(32,0)
,`total_revenue` decimal(34,2)
,`avg_sale_price` decimal(20,10)
,`first_sale_date` date
,`last_sale_date` date
,`activity_status` varchar(18)
);

-- --------------------------------------------------------

--
-- Структура таблицы `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(30) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Дамп данных таблицы `roles`
--

INSERT INTO `roles` (`id`, `name`, `description`) VALUES
(1, 'admin', 'Администратор системы - полный доступ'),
(2, 'user', 'Обычный пользователь - работа с данными'),
(3, 'guest', 'Гость - только просмотр');

-- --------------------------------------------------------

--
-- Структура таблицы `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `subdivision_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `sale_date` date NOT NULL,
  `quantity` int(11) NOT NULL CHECK (`quantity` > 0),
  `total_amount` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Дамп данных таблицы `sales`
--

INSERT INTO `sales` (`id`, `subdivision_id`, `product_id`, `sale_date`, `quantity`, `total_amount`) VALUES
(1, 1, 1, '2025-11-01', 2, '199999.98'),
(2, 1, 2, '2025-11-08', 1, '89999.99'),
(3, 2, 1, '2025-11-16', 1, '99999.99'),
(4, 3, 3, '2025-11-23', 3, '209999.97'),
(5, 4, 4, '2025-12-01', 2, '119999.98'),
(6, 1, 5, '2025-12-08', 1, '64999.99'),
(7, 2, 2, '2025-12-16', 2, '179999.98'),
(8, 3, 1, '2025-12-23', 1, '99999.99'),
(9, 4, 6, '2025-12-31', 3, '134999.97'),
(10, 5, 7, '2026-01-07', 1, '74999.99'),
(11, 1, 8, '2026-01-15', 2, '129999.98'),
(12, 2, 3, '2026-01-22', 1, '69999.99'),
(13, 3, 5, '2026-01-30', 2, '129999.98'),
(14, 4, 1, '2026-02-06', 1, '99999.99'),
(15, 5, 2, '2026-02-14', 1, '89999.99'),
(16, 1, 4, '2026-02-21', 2, '119999.98'),
(17, 2, 6, '2026-03-01', 1, '44999.99'),
(18, 3, 7, '2026-03-08', 1, '74999.99'),
(19, 4, 8, '2026-03-16', 3, '194999.97'),
(20, 5, 1, '2026-03-23', 2, '199999.98');

--
-- Триггеры `sales`
--
DELIMITER $$
CREATE TRIGGER `before_sales_insert` BEFORE INSERT ON `sales` FOR EACH ROW BEGIN
    DECLARE avg_price DECIMAL(12,2);
    
    -- Проверка количества
    IF NEW.quantity <= 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Количество товара должно быть положительным';
    END IF;
    
    -- Проверка суммы
    IF NEW.total_amount <= 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Сумма продажи должна быть положительной';
    END IF;
    
    -- Получаем среднюю цену товара из последних продаж
    SELECT AVG(total_amount/quantity) INTO avg_price
    FROM sales
    WHERE product_id = NEW.product_id
    AND sale_date >= DATE_SUB(NEW.sale_date, INTERVAL 30 DAY);
    
    -- Если сумма продажи значительно отличается от средней цены (более чем на 50%)
    IF avg_price IS NOT NULL AND 
       ABS((NEW.total_amount/NEW.quantity - avg_price) / avg_price) > 0.5 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Предупреждение: Цена продажи значительно отличается от средней';
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `before_sales_update` BEFORE UPDATE ON `sales` FOR EACH ROW BEGIN
    IF NEW.quantity <= 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Количество товара должно быть положительным';
    END IF;
    
    IF NEW.total_amount <= 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Сумма продажи должна быть положительной';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Структура таблицы `subdivisions`
--

CREATE TABLE `subdivisions` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `city` varchar(50) NOT NULL,
  `address` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Дамп данных таблицы `subdivisions`
--

INSERT INTO `subdivisions` (`id`, `name`, `city`, `address`, `is_active`) VALUES
(1, 'Центральный магазин', 'Москва', 'ул. Тверская, д. 10', 1),
(2, 'Магазин на Арбате', 'Москва', 'ул. Арбат, д. 25', 1),
(3, 'Филиал в Санкт-Петербурге', 'Санкт-Петербург', 'Невский пр-т, д. 50', 1),
(4, 'Филиал в Казани', 'Казань', 'ул. Баумана, д. 15', 1),
(5, 'Онлайн-магазин', 'Москва', 'Интернет-продажи', 1);

-- --------------------------------------------------------

--
-- Дублирующая структура для представления `subdivision_efficiency`
-- (См. Ниже фактическое представление)
--
CREATE TABLE `subdivision_efficiency` (
`id` int(11)
,`name` varchar(100)
,`city` varchar(50)
,`sales_count` bigint(21)
,`items_sold` decimal(32,0)
,`revenue` decimal(34,2)
,`unique_products_sold` bigint(21)
,`avg_sale_amount` decimal(38,6)
,`products_last_week` bigint(21)
);

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `username`, `full_name`, `email`, `is_active`, `created_at`) VALUES
(1, 'admin', 'Администратор', 'admin@system.local', 1, '2025-11-10 03:00:00'),
(2, 'user', 'User1', 'gg@mail.gg', 1, '2025-12-15 07:30:00'),
(3, 'guest', 'Guest/User', 'guest@guest.gg', 1, '2026-01-20 02:15:00');

-- --------------------------------------------------------

--
-- Структура таблицы `user_roles`
--

CREATE TABLE `user_roles` (
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Дамп данных таблицы `user_roles`
--

INSERT INTO `user_roles` (`user_id`, `role_id`) VALUES
(1, 1),
(2, 2),
(3, 3);

-- --------------------------------------------------------

--
-- Структура для представления `current_competitor_prices`
--
DROP TABLE IF EXISTS `current_competitor_prices`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Filippov`@`%` SQL SECURITY DEFINER VIEW `current_competitor_prices`  AS SELECT `cp`.`id` AS `id`, `c`.`name` AS `competitor_name`, `p`.`name` AS `product_name`, `p`.`category` AS `category`, `cp`.`price` AS `price`, `cp`.`check_date` AS `last_check_date`, `cp`.`product_name_at_competitor` AS `product_name_at_competitor`, `cp`.`notes` AS `notes`, to_days(curdate()) - to_days(`cp`.`check_date`) AS `days_ago` FROM ((`competitor_prices` `cp` join `competitors` `c` on(`cp`.`competitor_id` = `c`.`id`)) join `products` `p` on(`cp`.`product_id` = `p`.`id`)) WHERE `cp`.`check_date` = (select max(`cp2`.`check_date`) from `competitor_prices` `cp2` where `cp2`.`competitor_id` = `cp`.`competitor_id` AND `cp2`.`product_id` = `cp`.`product_id`) ;

-- --------------------------------------------------------

--
-- Структура для представления `product_sales_stats`
--
DROP TABLE IF EXISTS `product_sales_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Filippov`@`%` SQL SECURITY DEFINER VIEW `product_sales_stats`  AS SELECT `p`.`id` AS `id`, `p`.`name` AS `name`, `p`.`category` AS `category`, `p`.`internal_code` AS `internal_code`, count(`s`.`id`) AS `total_sales_count`, coalesce(sum(`s`.`quantity`),0) AS `total_quantity_sold`, coalesce(sum(`s`.`total_amount`),0) AS `total_revenue`, coalesce(avg(`s`.`total_amount` / `s`.`quantity`),0) AS `avg_sale_price`, min(`s`.`sale_date`) AS `first_sale_date`, max(`s`.`sale_date`) AS `last_sale_date`, CASE WHEN max(`s`.`sale_date`) >= curdate() - interval 30 day THEN 'Активен' WHEN max(`s`.`sale_date`) >= curdate() - interval 90 day THEN 'Средняя активность' WHEN max(`s`.`sale_date`) is not null THEN 'Низкая активность' ELSE 'Нет продаж' END AS `activity_status` FROM (`products` `p` left join `sales` `s` on(`p`.`id` = `s`.`product_id`)) WHERE `p`.`is_active` = 1 GROUP BY `p`.`id` ;

-- --------------------------------------------------------

--
-- Структура для представления `subdivision_efficiency`
--
DROP TABLE IF EXISTS `subdivision_efficiency`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Filippov`@`%` SQL SECURITY DEFINER VIEW `subdivision_efficiency`  AS SELECT `sub`.`id` AS `id`, `sub`.`name` AS `name`, `sub`.`city` AS `city`, count(distinct `s`.`id`) AS `sales_count`, coalesce(sum(`s`.`quantity`),0) AS `items_sold`, coalesce(sum(`s`.`total_amount`),0) AS `revenue`, count(distinct `s`.`product_id`) AS `unique_products_sold`, coalesce(sum(`s`.`total_amount`) / nullif(count(distinct `s`.`id`),0),0) AS `avg_sale_amount`, (select count(distinct `s2`.`product_id`) from `sales` `s2` where `s2`.`subdivision_id` = `sub`.`id` and `s2`.`sale_date` >= curdate() - interval 7 day) AS `products_last_week` FROM (`subdivisions` `sub` left join `sales` `s` on(`sub`.`id` = `s`.`subdivision_id`)) WHERE `sub`.`is_active` = 1 GROUP BY `sub`.`id` ;

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `alternative_products`
--
ALTER TABLE `alternative_products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_relation` (`main_product_id`,`alternative_product_id`,`relation_type`),
  ADD KEY `alternative_product_id` (`alternative_product_id`),
  ADD KEY `idx_alternative_products_main` (`main_product_id`),
  ADD KEY `idx_alternative_products_alt` (`alternative_product_id`);

--
-- Индексы таблицы `competitors`
--
ALTER TABLE `competitors`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `competitor_prices`
--
ALTER TABLE `competitor_prices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `competitor_id` (`competitor_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `idx_check_date` (`check_date`),
  ADD KEY `idx_competitor_prices_product` (`product_id`,`check_date`),
  ADD KEY `idx_competitor_prices_competitor` (`competitor_id`,`check_date`);

--
-- Индексы таблицы `price_change_log`
--
ALTER TABLE `price_change_log`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `internal_code` (`internal_code`);

--
-- Индексы таблицы `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Индексы таблицы `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `subdivision_id` (`subdivision_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `idx_sale_date` (`sale_date`),
  ADD KEY `idx_sales_product_date` (`product_id`,`sale_date`),
  ADD KEY `idx_sales_subdivision_date` (`subdivision_id`,`sale_date`);

--
-- Индексы таблицы `subdivisions`
--
ALTER TABLE `subdivisions`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Индексы таблицы `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`user_id`,`role_id`),
  ADD KEY `role_id` (`role_id`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `alternative_products`
--
ALTER TABLE `alternative_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT для таблицы `competitors`
--
ALTER TABLE `competitors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT для таблицы `competitor_prices`
--
ALTER TABLE `competitor_prices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT для таблицы `price_change_log`
--
ALTER TABLE `price_change_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT для таблицы `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT для таблицы `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT для таблицы `subdivisions`
--
ALTER TABLE `subdivisions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `alternative_products`
--
ALTER TABLE `alternative_products`
  ADD CONSTRAINT `alternative_products_ibfk_1` FOREIGN KEY (`main_product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `alternative_products_ibfk_2` FOREIGN KEY (`alternative_product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `competitor_prices`
--
ALTER TABLE `competitor_prices`
  ADD CONSTRAINT `competitor_prices_ibfk_1` FOREIGN KEY (`competitor_id`) REFERENCES `competitors` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `competitor_prices_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`subdivision_id`) REFERENCES `subdivisions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sales_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `user_roles`
--
ALTER TABLE `user_roles`
  ADD CONSTRAINT `user_roles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_roles_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
