-- Runs once, when the local database volume is first created.
-- Tests use their own database so they never touch local development data.
SELECT 'CREATE DATABASE sortd_testing'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'sortd_testing')\gexec
