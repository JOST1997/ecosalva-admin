<?php
// ============================================================
// Configuración de conexión a PostgreSQL (ecosalva_db)
// Ajustar credenciales según entorno (.env o directo aquí)
// ============================================================

define('DB_HOST',     getenv('DB_HOST')     ?: 'localhost');
define('DB_PORT',     getenv('DB_PORT')     ?: '5432');
define('DB_NAME',     getenv('DB_NAME')     ?: 'ecosalva_db');
define('DB_USER',     getenv('DB_USER')     ?: 'ecosalva');
define('DB_PASS',     getenv('DB_PASS')     ?: 'ecosalva_secret');
define('DB_SSL',      getenv('DB_SSL')      ?: 'false');

class DB
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                DB_HOST, DB_PORT, DB_NAME
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            if (DB_SSL === 'true') {
                $options[PDO::PGSQL_ATTR_DISABLE_PREPARES] = true;
            }

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                http_response_code(500);
                die(json_encode([
                    'error' => 'Error de conexión a la base de datos: ' . $e->getMessage()
                ]));
            }
        }

        return self::$instance;
    }

    // Ejecuta query con parámetros y devuelve todos los resultados
    public static function query(string $sql, array $params = []): array
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // Devuelve una sola fila
    public static function queryOne(string $sql, array $params = []): ?array
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // Ejecuta INSERT/UPDATE/DELETE, devuelve filas afectadas
    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    // Devuelve un valor escalar
    public static function scalar(string $sql, array $params = []): mixed
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
}
