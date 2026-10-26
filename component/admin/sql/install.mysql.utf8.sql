CREATE TABLE IF NOT EXISTS `#__identitymanager_providers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `alias` varchar(100) NOT NULL,
  `type` varchar(20) NOT NULL DEFAULT 'oauth2',
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `client_id` varchar(255) NOT NULL DEFAULT '',
  `client_secret_encrypted` text NULL,
  `authorization_url` varchar(512) NOT NULL DEFAULT '',
  `token_url` varchar(512) NOT NULL DEFAULT '',
  `userinfo_url` varchar(512) NOT NULL DEFAULT '',
  `jwks_url` varchar(512) NOT NULL DEFAULT '',
  `issuer` varchar(512) NOT NULL DEFAULT '',
  `scope` varchar(512) NOT NULL DEFAULT '',
  `options_json` longtext NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_alias` (`alias`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__identitymanager_accounts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `provider_id` int unsigned NOT NULL,
  `provider_name` varchar(100) NOT NULL,
  `provider_user_id` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL DEFAULT '',
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `display_name` varchar(255) NOT NULL DEFAULT '',
  `avatar_url` varchar(512) NOT NULL DEFAULT '',
  `access_token_encrypted` text NULL,
  `refresh_token_encrypted` text NULL,
  `token_expires_at` datetime NULL,
  `revoked` tinyint(1) NOT NULL DEFAULT 0,
  `revoked_at` datetime NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NULL,
  `last_login_at` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_provider_subject` (`provider_id`, `provider_user_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__identitymanager_logs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NULL,
  `provider_id` int unsigned NULL,
  `provider_name` varchar(100) NOT NULL DEFAULT '',
  `event_type` varchar(50) NOT NULL,
  `status` varchar(20) NOT NULL,
  `message` text NULL,
  `ip_address` varchar(64) NOT NULL DEFAULT '',
  `user_agent` varchar(512) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_event_type` (`event_type`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__identitymanager_clients` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `client_id` varchar(120) NOT NULL,
  `client_secret_encrypted` text NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `redirect_uris_json` longtext NULL,
  `scope` varchar(512) NOT NULL DEFAULT 'openid profile email',
  `trusted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_client_id` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__identitymanager_oauth_codes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `client_id` varchar(120) NOT NULL,
  `user_id` int unsigned NOT NULL,
  `code_hash` char(64) NOT NULL,
  `redirect_uri` varchar(1024) NOT NULL,
  `scope` varchar(512) NOT NULL DEFAULT '',
  `nonce` varchar(255) NOT NULL DEFAULT '',
  `code_challenge` varchar(128) NOT NULL DEFAULT '',
  `expires_at` datetime NOT NULL,
  `used_at` datetime NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_code_hash` (`code_hash`),
  KEY `idx_client_id` (`client_id`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__identitymanager_oauth_requests` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `request_id` char(32) NOT NULL,
  `payload_json` longtext NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_request_id` (`request_id`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `#__identitymanager_providers`
(`name`, `alias`, `type`, `enabled`, `authorization_url`, `token_url`, `userinfo_url`, `scope`, `options_json`, `created_at`)
VALUES
('Google', 'google', 'hybridauth', 0, '', '', '', 'openid email profile', '{\"adapter\":\"Google\"}', NOW()),
('Microsoft', 'microsoft', 'hybridauth', 0, '', '', '', '', '{\"adapter\":\"MicrosoftGraph\"}', NOW()),
('GitHub', 'github', 'hybridauth', 0, '', '', '', '', '{\"adapter\":\"GitHub\"}', NOW()),
('Facebook', 'facebook', 'hybridauth', 0, '', '', '', '', '{\"adapter\":\"Facebook\"}', NOW()),
('Generic OAuth2/OIDC', 'generic', 'oauth2', 0, '', '', '', 'openid email profile', '{}', NOW());
