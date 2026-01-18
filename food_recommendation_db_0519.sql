-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- 主機： 127.0.0.1
-- 產生時間： 2025-05-18 18:14:25
-- 伺服器版本： 10.4.32-MariaDB
-- PHP 版本： 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 資料庫： `food_recommendation_db`
--

-- --------------------------------------------------------

--
-- 資料表結構 `food_diary_entries`
--

CREATE TABLE `food_diary_entries` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `entry_date` date DEFAULT NULL,
  `restaurant_name` varchar(255) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `image_caption` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- 資料表結構 `meal_arrangements`
--

CREATE TABLE `meal_arrangements` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `gcal_event_id` varchar(255) DEFAULT NULL,
  `event_summary` varchar(255) NOT NULL,
  `event_start_datetime` datetime NOT NULL,
  `event_end_datetime` datetime NOT NULL,
  `event_location_text` varchar(255) DEFAULT NULL,
  `event_description_user` text DEFAULT NULL,
  `event_description_full` text DEFAULT NULL,
  `selected_restaurant_name` varchar(255) DEFAULT NULL,
  `selected_restaurant_address` text DEFAULT NULL,
  `route_origin` text DEFAULT NULL,
  `route_destination` text DEFAULT NULL,
  `route_mode` varchar(50) DEFAULT NULL,
  `route_duration_text` varchar(100) DEFAULT NULL,
  `route_duration_value` int(11) DEFAULT NULL COMMENT '秒',
  `route_distance_text` varchar(100) DEFAULT NULL,
  `route_google_maps_url` text DEFAULT NULL,
  `route_summary_text` text DEFAULT NULL,
  `route_steps_json` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- 資料表結構 `pets`
--

CREATE TABLE `pets` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `image_url` varchar(2083) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- 資料表結構 `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `google_id` varchar(255) DEFAULT NULL COMMENT 'Google user “sub”',
  `google_display_name` varchar(100) DEFAULT NULL,
  `google_email` varchar(150) DEFAULT NULL,
  `google_access_token` text DEFAULT NULL,
  `google_refresh_token` text DEFAULT NULL,
  `google_token_expires_at` bigint(20) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'UNIX 秒',
  `travel_mode_preference` enum('driving','motorcycle','walking','bicycling','transit') NOT NULL DEFAULT 'driving',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- 資料表結構 `user_preferences`
--

CREATE TABLE `user_preferences` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `preference_type` varchar(50) NOT NULL,
  `preference_value` varchar(100) NOT NULL,
  `type` enum('like','dislike') NOT NULL DEFAULT 'like',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- 已傾印資料表的索引
--

--
-- 資料表索引 `food_diary_entries`
--
ALTER TABLE `food_diary_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_fd_user` (`user_id`);

--
-- 資料表索引 `meal_arrangements`
--
ALTER TABLE `meal_arrangements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ma_user` (`user_id`),
  ADD KEY `idx_ma_gcal` (`gcal_event_id`),
  ADD KEY `idx_ma_start` (`event_start_datetime`);

--
-- 資料表索引 `pets`
--
ALTER TABLE `pets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pets_user` (`user_id`);

--
-- 資料表索引 `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_username` (`username`),
  ADD UNIQUE KEY `uk_google_id` (`google_id`),
  ADD UNIQUE KEY `uk_email` (`email`);

--
-- 資料表索引 `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pref_user` (`user_id`);

--
-- 在傾印的資料表使用自動遞增(AUTO_INCREMENT)
--

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `food_diary_entries`
--
ALTER TABLE `food_diary_entries`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `meal_arrangements`
--
ALTER TABLE `meal_arrangements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `pets`
--
ALTER TABLE `pets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `user_preferences`
--
ALTER TABLE `user_preferences`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- 已傾印資料表的限制式
--

--
-- 資料表的限制式 `food_diary_entries`
--
ALTER TABLE `food_diary_entries`
  ADD CONSTRAINT `fk_fd_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- 資料表的限制式 `meal_arrangements`
--
ALTER TABLE `meal_arrangements`
  ADD CONSTRAINT `fk_ma_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- 資料表的限制式 `pets`
--
ALTER TABLE `pets`
  ADD CONSTRAINT `fk_pets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- 資料表的限制式 `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD CONSTRAINT `fk_pref_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
