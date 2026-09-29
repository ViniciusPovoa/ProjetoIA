CREATE TABLE IF NOT EXISTS instagram_integrations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  ig_user_id VARCHAR(80) NOT NULL,
  username VARCHAR(150) NULL,
  account_type VARCHAR(80) NULL,
  access_token_enc TEXT NOT NULL,
  token_expires_at DATETIME NULL,
  scopes VARCHAR(500) NULL,
  connected_by_user_id BIGINT UNSIGNED NULL,
  last_publish_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_instagram_company(company_id),
  INDEX idx_instagram_user(ig_user_id),
  CONSTRAINT fk_instagram_company FOREIGN KEY(company_id) REFERENCES companies(id) ON DELETE CASCADE,
  CONSTRAINT fk_instagram_connected_user FOREIGN KEY(connected_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS generated_media_assets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  token CHAR(48) NOT NULL,
  storage_path VARCHAR(500) NOT NULL,
  mime VARCHAR(80) NOT NULL DEFAULT 'image/jpeg',
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_generated_media_token(token),
  INDEX idx_generated_media_company(company_id),
  INDEX idx_generated_media_expiry(expires_at),
  CONSTRAINT fk_generated_media_company FOREIGN KEY(company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS social_publish_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  platform VARCHAR(40) NOT NULL,
  ig_media_id VARCHAR(100) NULL,
  media_type VARCHAR(40) NOT NULL,
  caption TEXT NULL,
  permalink VARCHAR(500) NULL,
  status VARCHAR(40) NOT NULL,
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_social_publish_company(company_id,created_at),
  INDEX idx_social_publish_user(user_id,created_at),
  CONSTRAINT fk_social_publish_company FOREIGN KEY(company_id) REFERENCES companies(id) ON DELETE CASCADE,
  CONSTRAINT fk_social_publish_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
