-- Runs once, when the local database volume is first created.
-- Tests use their own database so they never touch local development data.
SELECT 'CREATE DATABASE getsorted_testing'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'getsorted_testing')\gexec
