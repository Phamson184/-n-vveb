<?php
class Database {
    private static $instance = null;
    private $pdo;

    private function __construct($config) {
        $dsn = "sqlsrv:Server={$config['host']},{$config['port']};Database={$config['database']};TrustServerCertificate=yes";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8
        ];
        $this->pdo = new PDO($dsn, $config['username'], $config['password'], $options);
    }

    public static function getInstance($config = null) {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        return self::$instance->pdo;
    }
}
