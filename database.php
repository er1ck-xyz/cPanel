<?php
function conectarBanco() {
    static $pdo = null;

    if ($pdo === null) {
        $host = 'localhost';
        $port = '5432';
        $dbname = 'sqlx';
        $user = 'postgres';
        $password = '0000';

        try {
            $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Migração simples: garantir coluna 'ip' na tabela usuarios
            try {
                $pdo->exec("ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS ip VARCHAR(64)");
            } catch (Throwable $e1) {
                try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN ip VARCHAR(64)"); } catch (Throwable $e2) {}
            }
        } catch (PDOException $e) {
            die("Erro na conexão com o banco: " . $e->getMessage());
        }
    }

    return $pdo;
}
?>

