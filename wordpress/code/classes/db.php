<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/code/classes/includes.php');

class db
{
    private static $instance = null;
    // ... existing properties and methods ...

    // Public getter for host
    public function getHost()
    {
        return $this->host;
    }

    // Public getter for easy_wordpress_user
    public function getEasyWordpressUser()
    {
        return $this->easy_wordpress_user;
    }

    // Public getter for easy_wordpress_password
    public function getEasyWordpressPassword()
    {
        return $this->easy_wordpress_password;
    }

    // Public getter for easy_wordpress_database
    public function getEasyWordpressDatabase()
    {
        return $this->easy_wordpress_database;
    }

    public function getPdo()
    {
        return $this->pdo;
    }
    private $host;
    private $easy_wordpress_user;
    private $easy_wordpress_password;
    private $easy_wordpress_database;
    private $pdo;
    private $dsn;
    private $charset = 'utf8mb4';
    // Private constructor to prevent direct creation of object
    private function __construct()
    {
        require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-config.php');
        // Retrieve database credentials from wp-config.php
        $this->host = DB_HOST;
        $this->easy_wordpress_user = DB_USER;
        $this->easy_wordpress_password = DB_PASSWORD;
        $this->easy_wordpress_database = DB_NAME;
        //$this->mysql_root_user_password = 'w262sUQ4J2P';
        $this->dsn = "mysql:host={$this->host};dbname={$this->easy_wordpress_database};charset={$this->charset}";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            // Create the PDO connection
            $this->pdo = new PDO($this->dsn, $this->easy_wordpress_user, $this->easy_wordpress_password);
            // Additional PDO options like error mode can be set here
        } catch (PDOException $e) {
            // Handle any connection error
            die("DB Connection failed: " . $e->getMessage());
        }

        // Setup the RedBeanPHP database connection
        R::setup('mysql:host=' . $this->host . ';dbname=' . $this->easy_wordpress_database, $this->easy_wordpress_user, $this->easy_wordpress_password, true, $this->easy_wordpress_database);
    }

    function mysqli()
    {

        return new mysqli($this->host, $this->easy_wordpress_user, $this->easy_wordpress_password, $this->easy_wordpress_database);
    }

    // Static method to get the instance of the class
    public static function getInstance()
    {
        if (self::$instance == null) {
            self::$instance = new db();
        }
        return self::$instance;
    }

    // Optionally, you can add a method to close the database connection
    public function closeConnection()
    {
        R::close();
    }
}
