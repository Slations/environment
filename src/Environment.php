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
    protected $user_id;

    // Construct
    public function __construct($db, $user_id) {
		$this->database = $db;
        $this->user_id = $user_id;
	}

    // Create an environment

    public function create($name) {
        global $dbconn;        
        
        // Gets the user id from database
        $user_data = Slations_Auth::user_get_me();
        $user_id = $user_data['id'];

        // Create in environment id
        $environment_id = Slations_Utilities::uuid_v4();
        
        // Get time in SQL format
        $sql_timestamp = date("Y-m-d H:i:s");
        
        // Filter values
        $name = Slations_Utilities::string_filter($name);
        $platform = Slations_Utilities::string_filter($platform);
        
        // Check required fields
        if (!$name || !$platform) {
            return array("status" => "failed", "code" => 1, "error" => "The fields 'name' and 'platform' are required.");
        }


        // Check if slug set and create one if not
        if ($slug == false) {
            $slug = Slations_Utilities::slug_generator($name . '-' . $platform);
        } else {
            $slug = Slations_Utilities::string_filter($slug);
        }
        
        // Create environment
        if (!mysqli_query($dbconn, "INSERT INTO `Core_Environments` (`id`, `name`, `slug`, `platform`, `created_by`, `created_at`) VALUES ('$environment_id', '$name', '$slug', '$platform', '$user_id', '$sql_timestamp')")) {
            return array("status" => "failed", "code" => 2, "error" => "Environment could not be created.");
        }

        // Add user link to environment
        if (!mysqli_query($dbconn, "INSERT INTO `Core_Environment_Users` (`environment_id`, `user_id`) VALUES ('$environment_id', '$user_id')")) {
            mysqli_query($dbconn, "DELETE FROM `Core_Environments` WHERE `id` = '$environment_id'");
            return array("status" => "failed", "code" => 3, "error" => "Environment could be created but the user link could not be created, the environment was not created.");
        }

        if ($user_data['default_environment'] == null) {
            mysqli_query($dbconn, "UPDATE `Core_Users` SET `default_environment` = '$environment_id' WHERE `id` = '$user_id'");
        }

        // add to the transaction log
        $log_content = 'The following environment "'.$name.' ('.$platform.')" has been created by "'.$user_data['first_name'].' '.$user_data['last_name'].'".';
        mysqli_query($dbconn, "INSERT INTO `Core_TransactionLog` (`user_id`, `environment_id`, `action`, `content`, `timestamp`) VALUES ('$user_id', '$environment_id', 'environment_create', '$log_content', '$sql_timestamp')");

        return array("status" => "success", "id" => $environment_id);
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