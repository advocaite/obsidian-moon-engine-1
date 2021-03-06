<?php
/**
 * Obsidian Moon Engine by Dark Prospect Games
 *
 * An Open Source, Lightweight and 100% Modular Framework in PHP
 *
 * PHP version 7
 *
 * @package   DarkProspectGames\ObsidianMoonEngine
 * @author    Alfonso E Martinez, III <opensaurusrex@gmail.com>
 * @copyright 2011-2018 Dark Prospect Games, LLC
 * @license   MIT https://darkprospect.net/MIT-License.txt
 * @link      https://github.com/dark-prospect-games/obsidian-moon-engine/
 */
namespace DarkProspectGames\ObsidianMoonEngine\Modules;

use DarkProspectGames\ObsidianMoonEngine\{AbstractModule, Core};
use PDO;
use PDOException;
use PDOStatement;

/**
 * Class Database
 *
 * Database class using PDO
 *
 * @package  DarkProspectGames\ObsidianMoonEngine\Modules
 * @author   Alfonso E Martinez, III <opensaurusrex@gmail.com>
 * @since    1.0.0
 * @uses     PDO
 * @uses     AbstractModule
 * @uses     Core
 * @uses     CoreException
 */
class Database extends AbstractModule
{

    /**
     * @type PDO
     */
    protected $connection;

    /**
     * @type int|null
     */
    protected $lastid;

    /**
     * @type mixed[]
     */
    protected $values;

    /**
     * Creates a new object to access database via PDO.
     *
     * @param mixed[] $configs The parameters that we will be passing to PDO.
     *
     * @since  1.0.0
     * @throws CoreException
     */
    public function __construct(array $configs = [])
    {
	    // Set and replace the default configs
	    $configs = array_replace([
		    'type'       => 'mysql',
		    'fetch_mode' => PDO::FETCH_ASSOC,
		    'error_mode' => PDO::ERRMODE_EXCEPTION,
	    ], $configs);
	    parent::__construct($configs);

        try {
            $this->connect();
        } catch (CoreException $e) {
            throw new CoreException($e->getMessage());
        }
    }

    /**
     * Create a database connection and instantiate PDO.
     *
     * @param mixed[] $configs These are the details pertaining to a newly created connection,
     *                         if not set it uses the config params.
     *
     * @since  1.0.0
     * @throws CoreException
     */
    protected function connect($configs = null)
    {
        if ($configs !== null) {
            $this->configs = array_replace($this->configs, $configs);
        }

        $dsn = "{$this->configs['type']}:dbname={$this->configs['name']};host={$this->configs['host']}";
        try {
            /** @type PDO connection */
            $this->connection = new PDO($dsn, $this->configs['user'], $this->configs['pass']);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, $this->configs['error_mode']);
        } catch (PDOException $e) {
            throw new CoreException(__CLASS__.'::__construct()->PDO::__construct() : ' . $e->getMessage());
        }
    }

    /**
     * Excutes a prepared statement
     *
     * @param array  $array An array holding the values to be used in a prepared statement.
     * @param string $stmt  The name of the variable where the statement was stored.
     *
     * @since  1.0.0
     * @return Database
     * @throws CoreException
     */
    public function execute($array, $stmt = 'stmt')
    {
        $this->values = [];
        $this->lastid = null;
        $stmt         = 'prepare_' . $stmt;
        try {
            $sth = $this->$stmt->execute($array);
        } catch (PDOException $e) {
            throw new CoreException(__CLASS__.'::execute()->PDOStatement::execute() : ' . $e->getMessage());
        }
        if ($sth instanceof PDOStatement) {
            try {
                $this->values = $sth->fetchAll($this->configs['fetch_mode']);
            } catch (PDOException $e) {
                throw new CoreException(__CLASS__.'::execute()->PDOStatement::fetchAll() : ' . $e->getMessage());
            }
        }

        $store_sql = $stmt . '_sql';
        if (preg_match('/insert/i', $this->$store_sql)) {
            try {
                $this->lastid = $this->connection->lastInsertId();
            } catch (PDOException $e) {
                throw new CoreException(__CLASS__.'::execute()->PDO::lastInsertId() : ' . $e->getMessage());
            }
        }

        return $this;
    }

    /**
     * Get the array of values fetched from database
     *
     * @param mixed[]|bool $params Specify the method that we are looking for.
     *
     * @since  1.0.0
     * @return mixed[]|bool
     */
    public function fetchArray($params = false)
    {
        if (count($this->values) === 0) {
            return false;
        } elseif (count($this->values) > 1) {
            return $this->values;
        } else {
            if ($params === true) {
                return $this->values;
            } elseif (is_array($params) && array_key_exists('item', $params)) {
                $item = $params['item'];
                if (array_key_exists($item, $this->values)) {
                    return $this->values[$item];
                } elseif (array_key_exists($item, $this->values[0])) {
                    return $this->values[0][$item];
                } else {
                    return false;
                }
            } else {
                return $this->values[0];
            }
        }
    }

    /**
     * Get the last id of the query that in an insert event.
     *
     * @since  1.0.0
     * @return null|int
     */
    public function insertId()
    {
        return $this->lastid;
    }

    /**
     * Return the number of rows found in the database.
     *
     * @since  1.0.0
     * @return int
     */
    public function numRows(): int
    {
        return count($this->values);
    }

    /**
     * Prepare a query statement to be executed at a later time.
     *
     * @param mixed  $sql  The SQL statement that will be prepared.
     * @param string $stmt The statement will be saved into this space.
     *
     * @since  1.0.0
     * @return Database
     * @throws CoreException
     */
    public function prepare($sql, string $stmt = 'stmt'): Database
    {
        $stmt = 'prepare_' . $stmt;
        try {
            $this->$stmt = $this->connection->prepare($sql);
        } catch (PDOException $e) {
            throw new CoreException(__CLASS__.'::prepare()->PDO::prepare() : ' . $e->getMessage());
        }
        $store_sql        = $stmt . '_sql';
        $this->$store_sql = $sql;

        return $this;
    }

    /**
     * Execute a query
     *
     * @param mixed $sql    The content of the SQL query.
     * @param null  $params The parameters of the query.
     *
     * @since  1.0.0
     * @return Database
     * @throws CoreException
     */
    public function query($sql, $params = null): Database
    {
        $sth          = null;
        $this->values = [];
        $this->lastid = null;
        if ($sql === '') {
            throw new CoreException(__CLASS__.'::query(): Query was undefined, please make sure you pass one.');
        }

        if ($params === null) {
            try {
                /** @type PDOStatement $sth */
                $sth = $this->connection->query($sql);
            } catch (PDOException $e) {
                throw new CoreException(__CLASS__.'::query()->PDO::query() : ' . $e->getMessage());
            }
        } else {
            try {
                /** @type PDOStatement $sth */
                $sth = $this->connection->prepare($sql);
            } catch (PDOException $e) {
                throw new CoreException(__CLASS__.'::query()->PDO::prepare() : ' . $e->getMessage());
            }
            try {
                $sth->execute($params);
            } catch (PDOException $e) {
                throw new CoreException(__CLASS__.'::query()->PDO::execute() : ' . $e->getMessage());
            }
        }

        if ($sth instanceof PDOStatement && preg_match('/^select/i', $sql)) {
            try {
                /** @type array values */
                $this->values = $sth->fetchAll($this->configs['fetch_mode']);
            } catch (PDOException $e) {
                throw new CoreException(__CLASS__.'::query()->PDOStatement::fetchAll() : ' . $e->getMessage());
            }
        }

        if (preg_match('/insert/i', $sql)) {
            try {
                /** @type int lastid */
                $this->lastid = $this->connection->lastInsertId();
            } catch (PDOException $e) {
                throw new CoreException(__CLASS__.'::query()->PDO::lastInsertId() : ' . $e->getMessage());
            }
        }

        return $this;
    }

    /**
     * Allows the user to set configurations after the object is instantiated
     *
     * @param string $name  name of the config that you want to change
     * @param mixed  $value value of the config to set
     *
     * @since  1.0.0
     */
    public function setConfig(string $name, $value)
    {
        if (array_key_exists($name, $this->configs)) {
            $this->configs[$name] = $value;
        }
    }
}
