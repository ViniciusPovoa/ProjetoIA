-- Execute este arquivo apenas se o banco já foi criado com uma versão anterior do MVP.
-- Faça backup antes de aplicar em produção.

ALTER TABLE users
  MODIFY status ENUM('pending_verification','active','blocked') NOT NULL DEFAULT 'pending_verification',
  ADD COLUMN email_verified_at DATETIME NULL AFTER status,
  ADD COLUMN terms_accepted_at DATETIME NULL AFTER email_verified_at,
  ADD COLUMN privacy_accepted_at DATETIME NULL AFTER terms_accepted_at,
  ADD COLUMN terms_version VARCHAR(30) NULL AFTER privacy_accepted_at;

-- Contas existentes são consideradas verificadas para não bloquear usuários já cadastrados.
UPDATE users
SET email_verified_at = COALESCE(email_verified_at, created_at),
    terms_accepted_at = COALESCE(terms_accepted_at, created_at),
    privacy_accepted_at = COALESCE(privacy_accepted_at, created_at),
    terms_version = COALESCE(terms_version, 'legacy'),
    status = CASE WHEN status='blocked' THEN 'blocked' ELSE 'active' END;

CREATE TABLE IF NOT EXISTS email_verifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_email_verify_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_email_verify_user(user_id),
  INDEX idx_email_verify_exp(expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
