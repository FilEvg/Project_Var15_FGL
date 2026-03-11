-- РЕЗЕРВНАЯ КОПИЯ БАЗЫ ДАННЫХ
-- Дата создания: 2026-03-11 04:05:06
-- Кодировка: UTF-8

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET NAMES utf8mb4;

-- --------------------------------------------------------
-- Таблица: products
-- Записей: 8
-- --------------------------------------------------------

TRUNCATE TABLE `products`;

INSERT INTO `products` (`id`, `name`, `internal_code`, `category`, `description`, `is_active`) VALUES ('1', 'Смартфон iPhone 14 Pro', 'SM-001', 'Смартфоны', 'Флагманский смартфон Apple с камерой 48 Мп', '1');
INSERT INTO `products` (`id`, `name`, `internal_code`, `category`, `description`, `is_active`) VALUES ('2', 'Смартфон Samsung Galaxy S23', 'SM-002', 'Смартфоны', 'Флагман Samsung с процессором Snapdragon 8 Gen 2', '1');
INSERT INTO `products` (`id`, `name`, `internal_code`, `category`, `description`, `is_active`) VALUES ('3', 'Смартфон Xiaomi 13 Pro', 'SM-003', 'Смартфоны', 'Флагман Xiaomi с камерой Leica', '1');
INSERT INTO `products` (`id`, `name`, `internal_code`, `category`, `description`, `is_active`) VALUES ('4', 'Смартфон Google Pixel 7', 'SM-004', 'Смартфоны', 'Смартфон с лучшей камерой по версии DxOMark', '1');
INSERT INTO `products` (`id`, `name`, `internal_code`, `category`, `description`, `is_active`) VALUES ('5', 'Смартфон OnePlus 11', 'SM-005', 'Смартфоны', 'Флагманский смартфон с быстрой зарядкой', '1');
INSERT INTO `products` (`id`, `name`, `internal_code`, `category`, `description`, `is_active`) VALUES ('6', 'Смартфон Realme GT 3', 'SM-006', 'Смартфоны', 'Игровой смартфон с дисплеем 144 Гц', '1');
INSERT INTO `products` (`id`, `name`, `internal_code`, `category`, `description`, `is_active`) VALUES ('7', 'Планшет iPad Air 5', 'TAB-001', 'Планшеты', 'Планшет Apple с процессором M1', '1');
INSERT INTO `products` (`id`, `name`, `internal_code`, `category`, `description`, `is_active`) VALUES ('8', 'Планшет Samsung Tab S8', 'TAB-002', 'Планшеты', 'Флагманский планшет Samsung', '1');

-- --------------------------------------------------------
-- Таблица: subdivisions
-- Записей: 5
-- --------------------------------------------------------

TRUNCATE TABLE `subdivisions`;

INSERT INTO `subdivisions` (`id`, `name`, `city`, `address`, `is_active`) VALUES ('1', 'Центральный магазин', 'Москва', 'ул. Тверская, д. 10', '1');
INSERT INTO `subdivisions` (`id`, `name`, `city`, `address`, `is_active`) VALUES ('2', 'Магазин на Арбате', 'Москва', 'ул. Арбат, д. 25', '1');
INSERT INTO `subdivisions` (`id`, `name`, `city`, `address`, `is_active`) VALUES ('3', 'Филиал в Санкт-Петербурге', 'Санкт-Петербург', 'Невский пр-т, д. 50', '1');
INSERT INTO `subdivisions` (`id`, `name`, `city`, `address`, `is_active`) VALUES ('4', 'Филиал в Казани', 'Казань', 'ул. Баумана, д. 15', '1');
INSERT INTO `subdivisions` (`id`, `name`, `city`, `address`, `is_active`) VALUES ('5', 'Онлайн-магазин', 'Москва', 'Интернет-продажи', '1');

-- --------------------------------------------------------
-- Таблица: competitors
-- Записей: 5
-- --------------------------------------------------------

TRUNCATE TABLE `competitors`;

INSERT INTO `competitors` (`id`, `name`, `website`) VALUES ('1', 'Конкурент 1', 'https://competitor1.ru');
INSERT INTO `competitors` (`id`, `name`, `website`) VALUES ('2', 'Конкурент 2', 'https://competitor2.com');
INSERT INTO `competitors` (`id`, `name`, `website`) VALUES ('3', 'Конкурент 3', 'https://competitor3.net');
INSERT INTO `competitors` (`id`, `name`, `website`) VALUES ('4', 'Магазин \"ТехноМир\"', 'https://technomir.ru');
INSERT INTO `competitors` (`id`, `name`, `website`) VALUES ('5', 'Интернет-магазин \"ГаджетПро\"', 'https://gadgetpro.com');

-- --------------------------------------------------------
-- Таблица: sales
-- Записей: 20
-- --------------------------------------------------------

TRUNCATE TABLE `sales`;

INSERT INTO `sales` (`id`, `subdivision_id`, `product_id`, `sale_date`, `quantity`, `total_amount`) VALUES ('1', '1', '1', '2024-01-15', '2', '199999.98');
INSERT INTO `sales` (`id`, `subdivision_id`, `product_id`, `sale_date`, `quantity`, `total_amount`) VALUES ('2', '1', '2', '2024-01-16', '1', '89999.99');
INSERT INTO `sales` (`id`, `subdivision_id`, `product_id`, `sale_date`, `quantity`, `total_amount`) VALUES ('3', '2', '1', '2024-01-17', '1', '99999.99');
INSERT INTO `sales` (`id`, `subdivision_id`, `product_id`, `sale_date`, `quantity`, `total_amount`) VALUES ('4', '3', '3', '2024-01-18', '3', '209999.97');
INSERT INTO `sales` (`id`, `subdivision_id`, `product_id`, `sale_date`, `quantity`, `total_amount`) VALUES ('5', '4', '4', '2024-01-19', '2', '119999.98');
INSERT INTO `sales` (`id`, `subdivision_id`, `product_id`, `sale_date`, `quantity`, `total_amount`) VALUES ('6', '1', '5', '2024-01-20', '1', '64999.99');
INSERT INTO `sales` (`id`, `subdivision_id`, `product_id`, `sale_date`, `quantity`, `total_amount`) VALUES ('7', '2', '2', '2024-01-21', '2', '179999.98');
INSERT INTO `sales` (`id`, `subdivision_id`, `product_id`, `sale_date`, `quantity`, `total_amount`) VALUES ('8', '3', '1', '2024-01-22', '1', '99999.99');
INSERT INTO `sales` (`id`, `subdivision_id`, `product_id`, `sale_date`, `quantity`, `total_amount`) VALUES ('9', '4', '6', '2024-01-23', '3', '134999.97');
INSERT INTO `sales` (`id`, `subdivision_id`, `product_id`, `sale_date`, `quantity`, `total_amount`) VALUES ('10', '5', '7', '2024-01-24', '1', '74999.99');
INSERT INTO `sales` (`id`, `subdivision_id`, `product_id`, `sale_date`, `quantity`, `total_amount`) VALUES ('11', '1', '8', '2024-01-25', '2', '129999.98');
INSERT INTO `sales` (`id`, `subdivision_id`, `product_id`, `sale_date`, `quantity`, `total_amount`) VALUES ('12', '2', '3', '2024-01-26', '1', '69999.99');
INSERT INTO `sales` (`id`, `subdivision_id`, `product_id`, `sale_date`, `quantity`, `total_amount`) VALUES ('13', '3', '5', '2024-01-27', '2', '129999.98');
INSERT INTO `sales` (`id`, `subdivision_id`, `product_id`, `sale_date`, `quantity`, `total_amount`) VALUES ('14', '4', '1', '2024-02-01', '1', '99999.99');
INSERT INTO `sales` (`id`, `subdivision_id`, `product_id`, `sale_date`, `quantity`, `total_amount`) VALUES ('15', '5', '2', '2024-02-02', '1', '89999.99');
INSERT INTO `sales` (`id`, `subdivision_id`, `product_id`, `sale_date`, `quantity`, `total_amount`) VALUES ('16', '1', '4', '2024-02-03', '2', '119999.98');
INSERT INTO `sales` (`id`, `subdivision_id`, `product_id`, `sale_date`, `quantity`, `total_amount`) VALUES ('17', '2', '6', '2024-02-04', '1', '44999.99');
INSERT INTO `sales` (`id`, `subdivision_id`, `product_id`, `sale_date`, `quantity`, `total_amount`) VALUES ('18', '3', '7', '2024-02-05', '1', '74999.99');
INSERT INTO `sales` (`id`, `subdivision_id`, `product_id`, `sale_date`, `quantity`, `total_amount`) VALUES ('19', '4', '8', '2024-02-06', '3', '194999.97');
INSERT INTO `sales` (`id`, `subdivision_id`, `product_id`, `sale_date`, `quantity`, `total_amount`) VALUES ('20', '5', '1', '2024-02-07', '2', '199999.98');

-- --------------------------------------------------------
-- Таблица: competitor_prices
-- Записей: 12
-- --------------------------------------------------------

TRUNCATE TABLE `competitor_prices`;

INSERT INTO `competitor_prices` (`id`, `competitor_id`, `product_id`, `check_date`, `price`, `product_name_at_competitor`, `notes`) VALUES ('1', '1', '1', '2024-01-15', '97999.00', 'iPhone 14 Pro 128GB', 'В наличии, доставка завтра');
INSERT INTO `competitor_prices` (`id`, `competitor_id`, `product_id`, `check_date`, `price`, `product_name_at_competitor`, `notes`) VALUES ('2', '2', '1', '2024-01-15', '98999.00', 'Apple iPhone 14 Pro', 'Скидка 5% при оплате онлайн');
INSERT INTO `competitor_prices` (`id`, `competitor_id`, `product_id`, `check_date`, `price`, `product_name_at_competitor`, `notes`) VALUES ('3', '3', '1', '2024-01-16', '99999.00', 'iPhone 14 Pro', 'Последний экземпляр');
INSERT INTO `competitor_prices` (`id`, `competitor_id`, `product_id`, `check_date`, `price`, `product_name_at_competitor`, `notes`) VALUES ('4', '4', '2', '2024-01-17', '87999.00', 'Samsung Galaxy S23', 'Акция: чехол в подарок');
INSERT INTO `competitor_prices` (`id`, `competitor_id`, `product_id`, `check_date`, `price`, `product_name_at_competitor`, `notes`) VALUES ('5', '5', '2', '2024-01-18', '89999.00', 'Galaxy S23 256GB', 'Рассрочка 0%');
INSERT INTO `competitor_prices` (`id`, `competitor_id`, `product_id`, `check_date`, `price`, `product_name_at_competitor`, `notes`) VALUES ('6', '1', '3', '2024-01-19', '68999.00', 'Xiaomi 13 Pro', 'Предзаказ');
INSERT INTO `competitor_prices` (`id`, `competitor_id`, `product_id`, `check_date`, `price`, `product_name_at_competitor`, `notes`) VALUES ('7', '2', '3', '2024-01-20', '69999.00', 'Xiaomi 13 Pro Global', 'В наличии');
INSERT INTO `competitor_prices` (`id`, `competitor_id`, `product_id`, `check_date`, `price`, `product_name_at_competitor`, `notes`) VALUES ('8', '3', '4', '2024-01-21', '59999.00', 'Google Pixel 7', 'Уцененный товар');
INSERT INTO `competitor_prices` (`id`, `competitor_id`, `product_id`, `check_date`, `price`, `product_name_at_competitor`, `notes`) VALUES ('9', '4', '5', '2024-01-22', '62999.00', 'OnePlus 11', 'Бесплатная доставка');
INSERT INTO `competitor_prices` (`id`, `competitor_id`, `product_id`, `check_date`, `price`, `product_name_at_competitor`, `notes`) VALUES ('10', '5', '6', '2024-01-23', '43999.00', 'Realme GT 3', 'Скидка 10% до конца недели');
INSERT INTO `competitor_prices` (`id`, `competitor_id`, `product_id`, `check_date`, `price`, `product_name_at_competitor`, `notes`) VALUES ('11', '1', '7', '2024-01-24', '72999.00', 'iPad Air 5', 'Wi-Fi 64GB');
INSERT INTO `competitor_prices` (`id`, `competitor_id`, `product_id`, `check_date`, `price`, `product_name_at_competitor`, `notes`) VALUES ('12', '2', '8', '2024-01-25', '63999.00', 'Samsung Tab S8', '11\" 128GB');

-- --------------------------------------------------------
-- Таблица: users
-- Записей: 3
-- --------------------------------------------------------

TRUNCATE TABLE `users`;

INSERT INTO `users` (`id`, `username`, `full_name`, `email`, `is_active`, `created_at`) VALUES ('1', 'admin', 'Администратор', 'admin@system.local', '1', '2026-02-09 18:54:35');
INSERT INTO `users` (`id`, `username`, `full_name`, `email`, `is_active`, `created_at`) VALUES ('2', 'user', 'User1', 'gg@mail.gg', '1', '2026-02-11 09:32:22');
INSERT INTO `users` (`id`, `username`, `full_name`, `email`, `is_active`, `created_at`) VALUES ('3', 'guest', 'Guest/User', 'guest@guest.gg', '1', '2026-02-11 11:33:19');

-- --------------------------------------------------------
-- Таблица: roles
-- Записей: 3
-- --------------------------------------------------------

TRUNCATE TABLE `roles`;

INSERT INTO `roles` (`id`, `name`, `description`) VALUES ('1', 'admin', 'Администратор системы - полный доступ');
INSERT INTO `roles` (`id`, `name`, `description`) VALUES ('2', 'user', 'Обычный пользователь - работа с данными');
INSERT INTO `roles` (`id`, `name`, `description`) VALUES ('3', 'guest', 'Гость - только просмотр');

-- --------------------------------------------------------
-- Таблица: user_roles
-- Записей: 3
-- --------------------------------------------------------

TRUNCATE TABLE `user_roles`;

INSERT INTO `user_roles` (`user_id`, `role_id`) VALUES ('1', '1');
INSERT INTO `user_roles` (`user_id`, `role_id`) VALUES ('2', '2');
INSERT INTO `user_roles` (`user_id`, `role_id`) VALUES ('3', '3');

SET FOREIGN_KEY_CHECKS=1;
