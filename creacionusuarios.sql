CREATE USER 'lode_app'@'localhost' IDENTIFIED BY 'contraparaapp2026';
GRANT SELECT, INSERT, UPDATE, DELETE ON lodetorres.* TO 'lode_app'@'localhost';
CREATE USER 'lode_admin'@'localhost' IDENTIFIED BY 'administracionjbm';
GRANT ALL PRIVILEGES ON lodetorres.* TO 'lode_admin'@'localhost';