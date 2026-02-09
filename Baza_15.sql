-- phpMyAdmin SQL Dump
-- version 5.1.3-3.red80
-- https://www.phpmyadmin.net/
--
-- Хост: localhost
-- Время создания: Фев 06 2026 г., 08:02
-- Версия сервера: 10.11.11-MariaDB
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

-- --------------------------------------------------------

--
-- Структура таблицы `competitors`
--

CREATE TABLE `competitors` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `website` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

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

-- --------------------------------------------------------

--
-- Структура таблицы `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(30) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

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

-- --------------------------------------------------------

--
-- Структура таблицы `user_roles`
--

CREATE TABLE `user_roles` (
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `competitors`
--
ALTER TABLE `competitors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `competitor_prices`
--
ALTER TABLE `competitor_prices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `subdivisions`
--
ALTER TABLE `subdivisions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
