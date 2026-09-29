CREATE TABLE IF NOT EXISTS proposal_documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  agent_run_id BIGINT UNSIGNED NULL,
  template_slug VARCHAR(60) NOT NULL,
  title VARCHAR(190) NOT NULL,
  client_name VARCHAR(190) NULL,
  filename VARCHAR(220) NOT NULL,
  storage_path VARCHAR(500) NOT NULL,
  public_token CHAR(48) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_proposal_token(public_token),
  INDEX idx_proposals_company(company_id,created_at),
  CONSTRAINT fk_proposals_company FOREIGN KEY(company_id) REFERENCES companies(id) ON DELETE CASCADE,
  CONSTRAINT fk_proposals_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_proposals_run FOREIGN KEY(agent_run_id) REFERENCES agent_runs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proposal_deliveries (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  proposal_id BIGINT UNSIGNED NOT NULL,
  company_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  channel ENUM('email','whatsapp') NOT NULL,
  recipient VARCHAR(190) NOT NULL,
  status ENUM('sent','error') NOT NULL,
  provider_message_id VARCHAR(255) NULL,
  error_message VARCHAR(500) NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_proposal_deliveries(proposal_id,created_at),
  CONSTRAINT fk_delivery_proposal FOREIGN KEY(proposal_id) REFERENCES proposal_documents(id) ON DELETE CASCADE,
  CONSTRAINT fk_delivery_company FOREIGN KEY(company_id) REFERENCES companies(id) ON DELETE CASCADE,
  CONSTRAINT fk_delivery_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
