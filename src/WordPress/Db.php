<?php

namespace lucatume\WPBrowser\WordPress;

use lucatume\WPBrowser\Utils\Db as DbUtil;
use lucatume\WPBrowser\Utils\Serializer;
use PDO;

class Db
{
    private ?PDO $pdo = null;
    private string $dbHost;
    private string $dbName;
    private string $dbPassword;
    private string $dbUser;
    private string $dsn;
    private string $tablePrefix;
    private string $dbUrl;

    /**
     * @throws DbException
     */
    public function __construct(
        string $dbName,
        string $dbUser,
        string $dbPassword,
        string $dbHost,
        string $tablePrefix = 'wp_'
    ) {
        if (!preg_match('/^[a-zA-Z][\w_]{0,23}$/', $dbName) || str_starts_with('ii', $dbName)) {
            throw new DbException(
                "Invalid database name: $dbName",
                DbException::INVALID_DB_NAME
            );
        }

        $this->dbName = $dbName;
        $this->dbUser = $dbUser;
        $this->dbPassword = $dbPassword;
        $this->dbHost = $dbHost;
        $this->tablePrefix = $tablePrefix;
        $this->dsn = DbUtil::dbDsnString(DbUtil::dbDsnMap($dbHost));
        $this->dbUrl = sprintf('mysql://%s:%s@%s/%s',
            $dbUser,
            $dbPassword,
            $dbHost,
            $dbName
        );
    }

    /**
     * @throws DbException|WpConfigFileException
     */
    public static function fromWpConfigFile(WPConfigFile $wpConfigFile): self
    {
        $dbName = $wpConfigFile->getConstantOrThrow('DB_NAME');
        $dbUser = $wpConfigFile->getConstantOrThrow('DB_USER');
        $dbPassword = $wpConfigFile->getConstantOrThrow('DB_PASSWORD');
        $dbHost = $wpConfigFile->getConstantOrThrow('DB_HOST');
        $tablePrefix = $wpConfigFile->getVariableOrThrow('table_prefix');

        return new self($dbName, $dbUser, $dbPassword, $dbHost, $tablePrefix);
    }

    public function getDbName(): string
    {
        return $this->dbName;
    }

    public function getDbUser(): string
    {
        return $this->dbUser;
    }

    public function getDbPassword(): string
    {
        return $this->dbPassword;
    }

    public function getDbHost(): string
    {
        return $this->dbHost;
    }

    public function getTablePrefix(): string
    {
        return $this->tablePrefix;
    }

    /**
     * @throws DbException
     */
    public function getPDO(): PDO
    {
        if (!$this->pdo instanceof PDO) {
            try {
                $this->pdo = new PDO($this->dsn, $this->dbUser, $this->dbPassword);
            } catch (\PDOException $e) {
                throw new DbException(
                    "Could not connect to the database: {$e->getMessage()}",
                    DbException::INVALID_CONNECTION_PARAMETERS
                );
            }

            if ($this->exists()) {
                $this->useDb($this->dbName);
            }
        }

        return $this->pdo;
    }

    /**
     * @throws DbException
     */
    public function create(): self
    {
        if ($this->getPDO()->query('CREATE DATABASE IF NOT EXISTS ' . $this->dbName) === false) {
            throw new DbException(
                'Could not create database ' . $this->dbName . ':' . json_encode($this->pdo->errorInfo()),
                DbException::FAILED_QUERY
            );
        }
        $this->useDb($this->dbName);

        return $this;
    }

    /**
     * @throws DbException
     */
    public function drop(): self
    {
        if ($this->getPDO()->query('DROP DATABASE IF EXISTS ' . $this->dbName) === false) {
            throw new DbException(
                'Could not drop database ' . $this->dbName . ': ' . json_encode($this->pdo->errorInfo()),
                DbException::FAILED_QUERY
            );
        }

        return $this;
    }

    public function exists(): bool
    {
        $result = $this->getPDO()->query("SHOW DATABASES LIKE '$this->dbName'", PDO::FETCH_COLUMN, 0);
        $matches = iterator_to_array($result, false);
        return !empty($matches);
    }

    /**
     * @throws DbException
     */
    public function useDb(string $dbName): self
    {
        if ($this->pdo->query('USE ' . $dbName) === false) {
            throw new DbException(
                'Could not use database ' . $this->dbName . ': ' . json_encode($this->pdo->errorInfo()),
                DbException::FAILED_QUERY
            );
        }

        return $this;
    }

    /**
     * @throws DbException
     */
    public function query(string $query, array $params = []): int
    {
        $statement = $this->getPDO()->prepare($query);
        $executed = $statement->execute($params);
        if ($executed === false) {
            throw new DbException(
                'Could not execute query ' . $query . ': ' . json_encode($statement->errorInfo(), JSON_PRETTY_PRINT),
                DbException::FAILED_QUERY
            );
        }
        return $statement->rowCount();
    }

    public function getDsn(): string
    {
        return $this->dsn;
    }

    public function getDbUrl(): string
    {
        return $this->dbUrl;
    }

    /**
     * @throws DbException
     */
    public function updateOption(string $name, mixed $value): int
    {
        $table = $this->getTablePrefix() . 'options';
        return $this->query(
            "INSERT INTO $table (option_name, option_value) VALUES (:name, :value) ON DUPLICATE KEY UPDATE option_value = :value",
            ['value' => Serializer::maybeSerialize($value), 'name' => $name]
        );
    }

    /**
     * @throws DbException
     */
    public function getOption(string $name, mixed $default = null): mixed
    {
        $table = $this->getTablePrefix() . 'options';
        $query = "SELECT option_value FROM $table WHERE option_name = :name";

        return $this->fetchFirst($query, ['name' => $name], $default);
    }

    /**
     * @throws DbException
     */
    private function fetchFirst(string $query, array $parameters = [], mixed $default = null): mixed
    {
        $statement = $this->getPDO()->prepare($query);
        $executed = $statement->execute($parameters);
        if ($executed === false) {
            throw new DbException(
                'Could not execute query ' . $query . ': ' . json_encode($statement->errorInfo(), JSON_PRETTY_PRINT),
                DbException::FAILED_QUERY
            );
        }
        $value = $statement->fetchColumn();
        return $value === false ? $default : Serializer::maybeUnserialize($value);
    }
}
