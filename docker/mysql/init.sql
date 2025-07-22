-- MySQL initialization for SuiteCRM
-- This file runs when the MySQL container starts for the first time

-- Grant additional privileges for SuiteCRM
GRANT ALL PRIVILEGES ON suitecrm.* TO 'suitecrm'@'%';
FLUSH PRIVILEGES;

-- Note: SuiteCRM will create its own schema during installation
-- We just ensure the database exists and permissions are set