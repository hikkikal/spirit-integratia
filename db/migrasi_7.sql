-- Migrasi 7: identitas SSO (OpenID Connect) yang tidak berubah.
-- Setelah pengguna cocok pertama kali (lewat NIP/NIDN/NUPTK/email), `sub` dari SSO disimpan di sini.
-- Login berikutnya memakai `sub`, sehingga NIP/email yang berganti atau dipakai orang lain tidak membuka akun yang salah.
-- Aman dijalankan berulang.
ALTER TABLE users ADD COLUMN IF NOT EXISTS sso_sub text;
CREATE UNIQUE INDEX IF NOT EXISTS ux_users_sso_sub ON users (sso_sub) WHERE sso_sub IS NOT NULL;
