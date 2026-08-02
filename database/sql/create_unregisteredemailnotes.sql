-- 未登録メール Notes（Power Automate Outlook webLink キュー）
-- phpMyAdmin または mysql クライアントで適用してください。
-- mailLink は最大 998 文字のため、UNIQUE は mailLinkHash（SHA-256）に付与します。

CREATE TABLE IF NOT EXISTS `unregisteredemailnotes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `mailLink` varchar(998) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `mailLinkHash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `whoWrote` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `whenWrote` datetime NOT NULL,
  `subject` varchar(998) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `fromAddress` varchar(998) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unregisteredemailnotes_maillinkhash_unique` (`mailLinkHash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
