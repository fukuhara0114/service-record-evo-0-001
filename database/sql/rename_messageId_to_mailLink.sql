-- 既存テーブルを messageId → mailLink にリネームする場合

ALTER TABLE `unregisteredemailnotes`
  CHANGE COLUMN `messageId` `mailLink` varchar(998) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL;

ALTER TABLE `unregisteredemailnotes`
  CHANGE COLUMN `messageIdHash` `mailLinkHash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL;

-- インデックス名が古い場合のみ実行
-- ALTER TABLE `unregisteredemailnotes`
--   RENAME INDEX `unregisteredemailnotes_messageidhash_unique` TO `unregisteredemailnotes_maillinkhash_unique`;
