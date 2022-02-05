<?php

/*
 * Slations Environment Class
 *
 * The library adds a simple and lightweight environment class using medoo for database connectivity
 *
 * @copyright Copyright (c) 2021 Alan Tiller & Slations <alan@slations.co.uk>
 * @license GNU
 *
 */

namespace Slations;

use \Exception as Exception;

class Environment {
    
    // Set the connection value
    protected $database;

    // Construct
    public function __construct($db) {
		$this->database = $db;
	}

    // Create an environment

    public function create($name, $user_id) {
        global $dbconn;        
        
        // Gets the user id from database
        $users = $this->database->select('users', '*', ['id' => $user_id]);
        if (count($users) != 1) {
            throw new Exception('The user provided does not exist');
        }
        $user = $users[0];

        // Create in environment id
        $id = \Ramsey\Uuid\Uuid::uuid4()->toString();

        // Create the environment
        $this->database->insert("users", [
            "id" => $id,
            "name" => $name,
            "created_by" => $user['id'],
            "created_at" => date("Y-m-d H:i:s")
        ]);

        // Create the user permissions
        $this->database->insert("permissions", [
            "user" => $user['id'],
            "environment" => $id,
            "administration" => 3,
            "api_keys" => 2,
            "manage_logging" => 2,
            "manage_subscriptions" =>  2,
            "created_by" => $user['id'],
            "created_at" => date("Y-m-d H:i:s")
        ]);

        // Resond successful
        return array("status" => "success", "id" => $id);
    }

    // Get environment

    public function get($environment_id) {
        $environment = $this->database->select('environments', '*', ['id' => $environment_id]);

        if (count($environment) < 1) { 
            throw new Exception('No environment was found with the id provided');
        } elseif (count($environment) > 1) {
            throw new Exception('Multiple environments were found with the same id');
        }

        return $environment[0];
    }

    // Check environment permissions

    public function check($user_id, $environment_id, $permission = null, $level = null) {
        
        // Check the user exists
        $users = $this->database->select('users', '*', ['id' => $user_id]);
        if (count($users) != 1) {
            throw new Exception('The user provided does not exist');
        }
        $user = $users[0];

        // Check the environment exists
        $environments = $this->database->select('environments', '*', ['id' => $environment_id]);
        if (count($environments) != 1) {
            throw new Exception('The environment provided does not exist');
        }
        $environment = $environments[0];

        // Check user has access to tenant
        $permissions = $this->database->select('permissions', '*', ['user' => $user['id'], 'environment' => $environment['id']]);
        if (count($permissions) != 1) {
            throw new Exception('The user provided does not have access to the tenant provided');
        }
        $permissions = $permissions[0];

        // If user wants to check a permission
        if ($permission != null && $level != null) {
            
            // Work out the level
            switch ($level) {
                case 'read':
                    $level_code = 1;
                    break;
                case 'read/write':
                    $level_code = 2;
                    break;
                case 'administrator':
                    $level_code = 3;
                    break;
            }

            // Check the permissions
            if ($level_code <= $permissions[$permission]) {
                return true;
            } else {
                throw new Exception('The user provided does not have enough permissions to ' . $level . ' ' . $permission . ' in the environment provided');
            }

        } else {
            // Just respond to say user has access to tenant
            return true;
        }
    }

    
}