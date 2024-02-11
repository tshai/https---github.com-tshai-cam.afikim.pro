<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/code/classes/includes.php');

//class_alias('\RedBeanPHP\R', '\R');
class wp_users
{
    private $ID;
    private $user_login;
    private $user_pass;
    private $user_nicename;
    private $user_email;
    private $user_url;
    private $user_registered;
    private $user_activation_key;
    private $user_status;
    private $display_name;

    // Constructor and other methods...

    // Getters
    public function getID()
    {
        return $this->ID;
    }

    public function getUserLogin()
    {
        return $this->user_login;
    }

    public function getUserPass()
    {
        return $this->user_pass;
    }

    public function getUserNicename()
    {
        return $this->user_nicename;
    }

    public function getUserEmail()
    {
        return $this->user_email;
    }

    public function getUserUrl()
    {
        return $this->user_url;
    }

    public function getUserRegistered()
    {
        return $this->user_registered;
    }

    public function getUserActivationKey()
    {
        return $this->user_activation_key;
    }

    public function getUserStatus()
    {
        return $this->user_status;
    }

    public function getDisplayName()
    {
        return $this->display_name;
    }

    // Setters
    public function setID($ID)
    {
        $this->ID = $ID;
    }

    public function setUserLogin($user_login)
    {
        $this->user_login = $user_login;
    }

    public function setUserPass($user_pass)
    {
        $this->user_pass = $user_pass;
    }

    public function setUserNicename($user_nicename)
    {
        $this->user_nicename = $user_nicename;
    }

    public function setUserEmail($user_email)
    {
        $this->user_email = $user_email;
    }

    public function setUserUrl($user_url)
    {
        $this->user_url = $user_url;
    }

    public function setUserRegistered($user_registered)
    {
        $this->user_registered = $user_registered;
    }

    public function setUserActivationKey($user_activation_key)
    {
        $this->user_activation_key = $user_activation_key;
    }

    public function setUserStatus($user_status)
    {
        $this->user_status = $user_status;
    }

    public function setDisplayName($display_name)
    {
        $this->display_name = $display_name;
    }

    private $pdo; // PDO connection

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }
}
