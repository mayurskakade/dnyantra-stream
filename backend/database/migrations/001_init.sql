CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','viewer') NOT NULL DEFAULT 'viewer',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS refresh_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  revoked_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_refresh_user (user_id),
  INDEX idx_refresh_token (token_hash),
  CONSTRAINT fk_refresh_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(160) NOT NULL UNIQUE,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS genres LIKE categories;
ALTER TABLE genres MODIFY slug VARCHAR(160) NOT NULL UNIQUE;

CREATE TABLE IF NOT EXISTS media_assets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider ENUM('cloudflare_stream','r2_hls') NOT NULL,
  provider_uid VARCHAR(190) NOT NULL,
  status ENUM('uploading','processing','ready','failed') NOT NULL DEFAULT 'uploading',
  duration_seconds INT NULL,
  thumbnail_url VARCHAR(500) NULL,
  require_signed_playback TINYINT(1) NOT NULL DEFAULT 1,
  original_filename VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_provider_uid(provider, provider_uid)
);

CREATE TABLE IF NOT EXISTS series (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  slug VARCHAR(255) NOT NULL UNIQUE,
  synopsis TEXT NULL,
  poster_url VARCHAR(500) NULL,
  banner_url VARCHAR(500) NULL,
  release_year SMALLINT NULL,
  status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  visibility ENUM('private','authenticated','unlisted','public') NOT NULL DEFAULT 'private',
  rights_status ENUM('personal_only','owned_by_me','licensed_private','licensed_public','public_domain','creative_commons') NOT NULL DEFAULT 'personal_only',
  public_streaming_enabled TINYINT(1) NOT NULL DEFAULT 0,
  public_from DATETIME NULL,
  public_until DATETIME NULL,
  is_listed_publicly TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS seasons (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  series_id BIGINT UNSIGNED NOT NULL,
  season_number INT NOT NULL,
  title VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_series_season(series_id, season_number),
  CONSTRAINT fk_season_series FOREIGN KEY (series_id) REFERENCES series(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS episodes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  series_id BIGINT UNSIGNED NOT NULL,
  season_id BIGINT UNSIGNED NOT NULL,
  episode_number INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  synopsis TEXT NULL,
  duration_seconds INT NULL,
  media_asset_id BIGINT UNSIGNED NULL,
  status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  visibility ENUM('private','authenticated','unlisted','public') NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_season_episode(season_id, episode_number),
  CONSTRAINT fk_episode_series FOREIGN KEY (series_id) REFERENCES series(id) ON DELETE CASCADE,
  CONSTRAINT fk_episode_season FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE,
  CONSTRAINT fk_episode_media FOREIGN KEY (media_asset_id) REFERENCES media_assets(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS movies (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  slug VARCHAR(255) NOT NULL UNIQUE,
  synopsis TEXT NULL,
  duration_seconds INT NULL,
  media_asset_id BIGINT UNSIGNED NULL,
  poster_url VARCHAR(500) NULL,
  banner_url VARCHAR(500) NULL,
  release_year SMALLINT NULL,
  status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  visibility ENUM('private','authenticated','unlisted','public') NOT NULL DEFAULT 'private',
  rights_status ENUM('personal_only','owned_by_me','licensed_private','licensed_public','public_domain','creative_commons') NOT NULL DEFAULT 'personal_only',
  public_streaming_enabled TINYINT(1) NOT NULL DEFAULT 0,
  public_from DATETIME NULL,
  public_until DATETIME NULL,
  is_listed_publicly TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_movie_media FOREIGN KEY (media_asset_id) REFERENCES media_assets(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS watch_progress (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  playable_type ENUM('movie','episode') NOT NULL,
  playable_id BIGINT UNSIGNED NOT NULL,
  series_id BIGINT UNSIGNED NULL,
  season_id BIGINT UNSIGNED NULL,
  duration_seconds INT NULL,
  position_seconds INT NOT NULL DEFAULT 0,
  progress_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
  completed TINYINT(1) NOT NULL DEFAULT 0,
  completed_at DATETIME NULL,
  last_watched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_watch_user_playable(user_id, playable_type, playable_id)
);
