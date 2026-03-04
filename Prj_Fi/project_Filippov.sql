-- phpMyAdmin SQL Dump
-- version 5.2.3-1.red80
-- https://www.phpmyadmin.net/
--
-- Хост: localhost
-- Время создания: Фев 10 2026 г., 03:02
-- Версия сервера: 10.11.15-MariaDB
-- Версия PHP: 8.1.32

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
(1, 1, 1, '2024-01-15', 97999.00, 'iPhone 14 Pro 128GB', 'В наличии, доставка завтра'),
(2, 2, 1, '2024-01-15', 98999.00, 'Apple iPhone 14 Pro', 'Скидка 5% при оплате онлайн'),
(3, 3, 1, '2024-01-16', 99999.00, 'iPhone 14 Pro', 'Последний экземпляр'),
(4, 4, 2, '2024-01-17', 87999.00, 'Samsung Galaxy S23', 'Акция: чехол в подарок'),
(5, 5, 2, '2024-01-18', 89999.00, 'Galaxy S23 256GB', 'Рассрочка 0%'),
(6, 1, 3, '2024-01-19', 68999.00, 'Xiaomi 13 Pro', 'Предзаказ'),
(7, 2, 3, '2024-01-20', 69999.00, 'Xiaomi 13 Pro Global', 'В наличии'),
(8, 3, 4, '2024-01-21', 59999.00, 'Google Pixel 7', 'Уцененный товар'),
(9, 4, 5, '2024-01-22', 62999.00, 'OnePlus 11', 'Бесплатная доставка'),
(10, 5, 6, '2024-01-23', 43999.00, 'Realme GT 3', 'Скидка 10% до конца недели'),
(11, 1, 7, '2024-01-24', 72999.00, 'iPad Air 5', 'Wi-Fi 64GB'),
(12, 2, 8, '2024-01-25', 63999.00, 'Samsung Tab S8', '11\" 128GB');

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
(1, 'admin', 'Администратор системы'),
(2, 'manager', 'Менеджер отдела маркетинга'),
(3, 'analyst', 'Аналитик'),
(4, 'viewer', 'Просмотр отчетов');

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
(1, 1, 1, '2024-01-15', 2, 199999.98),
(2, 1, 2, '2024-01-16', 1, 89999.99),
(3, 2, 1, '2024-01-17', 1, 99999.99),
(4, 3, 3, '2024-01-18', 3, 209999.97),
(5, 4, 4, '2024-01-19', 2, 119999.98),
(6, 1, 5, '2024-01-20', 1, 64999.99),
(7, 2, 2, '2024-01-21', 2, 179999.98),
(8, 3, 1, '2024-01-22', 1, 99999.99),
(9, 4, 6, '2024-01-23', 3, 134999.97),
(10, 5, 7, '2024-01-24', 1, 74999.99),
(11, 1, 8, '2024-01-25', 2, 129999.98),
(12, 2, 3, '2024-01-26', 1, 69999.99),
(13, 3, 5, '2024-01-27', 2, 129999.98),
(14, 4, 1, '2024-02-01', 1, 99999.99),
(15, 5, 2, '2024-02-02', 1, 89999.99),
(16, 1, 4, '2024-02-03', 2, 119999.98),
(17, 2, 6, '2024-02-04', 1, 44999.99),
(18, 3, 7, '2024-02-05', 1, 74999.99),
(19, 4, 8, '2024-02-06', 3, 194999.97),
(20, 5, 1, '2024-02-07', 2, 199999.98);

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
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(20) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `email`, `is_active`, `created_at`) VALUES
(1, 'admin', 'admin123', 'Администратор', 'admin@system.local', 1, '2026-02-09 11:54:35');

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
(1, 2);

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `alternative_products`
--
ALTER TABLE `alternative_products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_relation` (`main_product_id`,`alternative_product_id`,`relation_type`),
  ADD KEY `alternative_product_id` (`alternative_product_id`);

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
  ADD KEY `idx_check_date` (`check_date`);

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
  ADD KEY `idx_sale_date` (`sale_date`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT для таблицы `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT для таблицы `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
