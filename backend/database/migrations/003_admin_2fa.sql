ALTER TABLE users
  ADD COLUMN totp_secret VARCHAR(64) NULL AFTER is_active;
