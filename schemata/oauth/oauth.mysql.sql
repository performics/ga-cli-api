CREATE TABLE `oauth_effective_tokens` (
  `hash` varchar(40) NOT NULL,
  `token` varchar(255) NOT NULL,
  `refresh_token` varchar(255),
  `expiration_time` int unsigned,
  `token_type` varchar(255),
  `token_scope` varchar(255),
  UNIQUE KEY `hash_index` (`hash`)
) ENGINE = InnoDB