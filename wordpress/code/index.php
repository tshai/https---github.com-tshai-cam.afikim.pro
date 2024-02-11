<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once($_SERVER['DOCUMENT_ROOT'] . '/code/classes/includes.php');

//class_alias('\RedBeanPHP\R', '\R');
class MyException extends Exception
{
}

$domains = new domains();
$domainsInstance = new domains();
$helpersInstance = new helpers();
$logsInstance = new Logs();
$errors = new errors();
$dbInstance = db::getInstance();
$shInstance=new sh();
// Include WordPress configuration file
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-config.php');

if (is_user_logged_in()) { //this api request from other site
    $domains->wp_admin_email = $current_user->user_email;
    $domains->user_id =  $current_user->ID;
    $domains->hilix_customer_id = $current_user->ID; //this is id in hilix i put 25 it for me
        $domains->hilix_customer_id = 25; //this if for me zahi 
} else { //this if the user logged in
    $domains->wp_admin_email = $_REQUEST['customer_email'];
    $domains->user_id = $_REQUEST['user_id']; // Replace with the actual user ID
    $domains->hilix_customer_id = $_REQUEST['customer_id'];
}



// Retrieve database credentials from wp-config.php
// $domains->Hilixdatabase_host = DB_HOST;
// $domains->Hilixdatabase_name = DB_NAME;
// $domains->Hilixdatabase_user = DB_USER; //root database for main website
// $domains->Hilixdatabase_password = DB_PASSWORD;
$q_type = $_REQUEST['q_type'];
foreach ($_REQUEST as $key => $value) {
    //echo $key . ': ' . $value . '<br>';
}

if ($q_type == "add") {
    $logs = "";
    $helpersInstance->custom_log("add");
    try {
        $is_domain = false;
        if ($helpersInstance->isValidDomain(trim($_REQUEST['domain_name']))) {
            $domains->domain_name = trim($_REQUEST['domain_name']); // Replace with the actual domain name
            $domains->original_domain_name = trim($_REQUEST['domain_name']);
            $is_domain = true;
        } else {
            $guid = $helpersInstance->generateRandomString(8);
            $domains->domain_name = $guid . ".easy-wordpress.org"; // Replace with the actual domain name
            $domains->original_domain_name = $guid . ".easy-wordpress.org";
        }
        // Define the data for the new row

        if ($domains->domainExists($domains->original_domain_name)) {
            return "אתר זה כבר קיים. עליך למחוק אותו כדי ליצור אותו מחדש. במידה והוא לא קיים בחשבון שלך ואתה בעל הדומיין נא לפנות לתמיכה";
            die();
        }
        //echo "44";
        //die();

        $domains->database_password = trim($helpersInstance->generateRandomString(6), '{}');

        $domains->database_name = 'wp_' . $helpersInstance->generateRandomString(2) . "_" . $domains->domain_name;
        $domains->database_name = str_replace(".", "_", $domains->database_name);
        $domains->database_name = str_replace("-", "_", $domains->database_name);
        $domains->wordpress_admin_user_name = 'admin'; //'wp_admin_uName_' . trim($helpersInstance->generateRandomString(6), '{}');
        $domains->wordpress_admin_password = trim($helpersInstance->generateRandomString(6), '{}');
        $domains->database_username = 'wp_' . $domains->domain_name;
        $ssl_extra = $helpersInstance->generateRandomString(5);
        $helpersInstance->custom_log("add1");
        if ($is_domain) {
            echo $domainsInstance->create_ssl_for_domain($domains, $domains->domain_name . "_" . $ssl_extra);
            $logs = $logs . "create_ssl_for_domain\n";
        }
        
        $new_domain_uuid = Ramsey\Uuid\Uuid::uuid4();
        $new_domain_uuid=$new_domain_uuid->toString();
        //return;
        //$helpersInstance->custom_log("add2");
        $domainsInstance->add_new_domain_to_db($domains, $new_domain_uuid); //here i add it to domains table in wordpress db
        $logs = $logs . "add_new_domain_to_db\n";
        //$helpersInstance->custom_log("add3");
        if($dbInstance->create_wordpress_db($domains)==1){
            $logs = $logs . "create_wordpress_db\n";
        }
        else
        {
            echo "error";
            die();
        }

        if($dbInstance->create_db_user($domains)==1){
            $logs = $logs . "create_db_user\n";
        }
        else
        {
            echo "error";
            die();
        };
        //echo $domains->create_db_user;
        //die();
        //$helpersInstance->custom_log("add5");
        //return;
        $wpExtractDir = $_SERVER['DOCUMENT_ROOT'] . "/wp_cus/customer_" . $domains->user_id . "/" . $domains->domain_name . "/";
        if($domainsInstance->installWordPress($domains, "New wordpress")=="installWordPress"){
            $logs = $logs . "installWordPress\n";
            $domains->install_word_press=1;
        }
        else
        {
            errors::addError("installWordPress","index.php->add");
            return "error";
            die();
        }
        $sh=new sh();
        if ($is_domain) {
            $add_domain_with_ssl_to_apache = $sh->add_domain_with_ssl_to_apache($domains->original_domain_name, $wpExtractDir . "wordpress", $ssl_extra);
            if($add_domain_with_ssl_to_apache==1){
                $domains->add_domain_with_ssl_to_apache=1;
                $logs = $logs . "add_domain_with_ssl_to_apache\n";
            }
            else
            {
                errors::addError("add_domain_with_ssl_to_apache","index.php->add");
                return "error";
                die();
            }
        } else {
            $add_domain_with_ssl_to_apache = $sh->add_domain_to_apache($domains->original_domain_name, $wpExtractDir . "wordpress", $ssl_extra);
            if($add_domain_with_ssl_to_apache==1){
                $domains->add_domain_to_apache=1;
                $logs = $logs . "add_domain_to_apache\n";
            }
            else
            {
                errors::addError("add_domain_to_apache","index.php->add");
                return "error";
                die();
            }
           
        }
      
      
        try {
            Logs::insertLog($domains->user_id, $logs, $domains->original_domain_name);
        }
        catch (Exception $e) {
            $errors->addError($e->getMessage(), "index.php->add");
            echo $e->getMessage();
            die();
        }

        if (isset($_REQUEST['domain_id']) && $_REQUEST['domain_id'] != '' && $_REQUEST['domain_id'] != '0') {
           
            $dbinstanse = db::getInstance();
            $domain_theme = $domainsInstance->select_domains($_REQUEST['domain_id'], "");
            if($domain_theme==null){
                errors::addError("select_domains","index.php->add");
                die();
                //$domains->select_domains=1;
            }
            //logs::insertLog(25, $_REQUEST['domain_id'], "guid line 183 index.php");
            //logs::insertLog(25, $domain_theme->id, "guid line 183 index.php");
            //echo "backup_name :" . $domain_theme->backup_name;
            $domains->guid = $new_domain_uuid;
            if($domainsInstance->update_new_wp_after_clone_theme($domain_theme, $domains)==1){
                $domains->update_new_wp_after_clone_theme=1;
                $logs = $logs . "update_new_wp_after_clone_theme\n";
            }
            else
            {
                errors::addError("update_new_wp_after_clone_theme","index.php->add");
                return "error";
                die();
            };
            // Define the paths
            $customerDir = "/var/www/s1.ask-us.link/html/wp_cus/customer_25/";
            $newDomainPath = $customerDir . $domains->original_domain_name . "";
            $originalDomainPath = $customerDir . $domain_theme->domain_name . "/wordpress/";

            // Rename the wp-config.php file
            $originalConfig = $originalDomainPath . "wp-config.php";
            $tempConfig = $originalDomainPath . "wp-config5.php";
            //die();
            // First rename
            if (rename($originalConfig, $tempConfig)) {
                echo "wp-config.php temporarily renamed.\n";
                $sh=new sh();
                // Perform the clone operation
                $cloneResult = $sh->clone_wp_theme($originalDomainPath, $newDomainPath);//
                if($cloneResult==1){
                    $domains->clone_wp_theme=1;
                    $logs = $logs . "clone_wp_theme\n";
                }
                else
                {
                    errors::addError("clone_wp_theme","index.php->add");
                    return "error";
                    die();
                };
                // Rename back to the original wp-config.php
                if (rename($tempConfig, $originalConfig)) {
                    $domains->rename_back_to_the_original_wp_config=1;
                    //echo "wp-config.php renamed back to original.\n";
                } else {
                    errors::addError("rename_back_to_the_original_wp_config","index.php->add");
                    return "error";
                    die();
                }
            } else {
                errors::addError("rename_back_to_the_original_wp_config2","index.php->add");
                return "error";
                die();
                //echo "Error temporarily renaming wp-config.php.\n";
            }
            // rename("/var/www/s1.ask-us.link/html/wp_cus/customer_25/".$domains->original_domain_name."/wordpress/wp-config.php", "/var/www/s1.ask-us.link/html/wp_cus/customer_25/".$domains->original_domain_name."/wordpress/wp-config5.php").
            // $domains->clone_wp_theme("/var/www/s1.ask-us.link/html/wp_cus/customer_25/".$domain_theme->domain_name."/wordpress","/var/www/s1.ask-us.link/html/wp_cus/customer_25/".$domains->original_domain_name);
            // rename("/var/www/s1.ask-us.link/html/wp_cus/customer_25/".$domains->original_domain_name."/wordpress/wp-config5.php", "/var/www/s1.ask-us.link/html/wp_cus/customer_25/".$domains->original_domain_name."/wordpress/wp-config.php").    
        }
        else {
            logs::insertLog(25, $_REQUEST['domain_id'], "guid line 227 index.php");
            // The domain_id parameter is not set or is empty
            // Handle the error or missing parameter case
        }
        //echo json_encode($domains);
        //die();
        //$sql = "SELECT * FROM domains WHERE guid = $new_domain_uuid;
        $domainsInstance->update_domain_table_add_domain_logs($new_domain_uuid, $domains);
      
    } catch (Exception $e) {
        echo "error";
        $errors->addError($e->getMessage(), "index.php->add");
    }
    //return $add_domain_to_apache; 

    //} catch (PDOException $e) {
    //echo "Error50: " . $e->getMessage();
    //}
}
if ($q_type=="clear_error_table"){
    echo helpers::clearErrorsTable();
}
if($q_type=="delete_content_of_file"){
    echo (helpers::delete_content_of_file($_REQUEST['file_path']));
    die();
}
if ($q_type == "admin_export_theme_db") {

    $themes = new themes();
    echo $themes->create_databse_backup('b606f6f1-8180-11ee-8a78-96000293c1c8', 0);
}
if ($q_type == "restore_databse_backup") {
    $db_that_will_be_overwrite_guid = 'e7a2f4d9-f1c9-40aa-8580-32a4ff13c2b9';
    if (isset($_REQUEST['db_that_will_be_overwrite_guid'])) {
        $db_that_will_be_overwrite_guid = $_REQUEST['db_that_will_be_overwrite_guid'];
    }
    $themes = new themes();
    $backup_name = 'c98MZ94Q9a9FNWI8.sql';
    $backup_name = $themes->restore_databse_backup($db_that_will_be_overwrite_guid, $backup_name); //here i go to original and create sql script if not exsist
}
 else if ($q_type == "show_domains") {
    $helpersInstance = new helpers();
    $helpersInstance->display_table("domains", $domains);
} else if ($q_type == "delete_apahce_conf") {
    if ($domains->user_id == 25) {
        if ($shInstance::delete_file_sh("/etc/apache2/sites-available/" . $_REQUEST['conf_file']) == 0) {
            echo "error";
        } else {
            echo "1";
        }
    }
    //echo $domains->user_id;
    die();
} else if ($q_type == "delete_ssl_directory") {
    if ($domains->user_id == 25) {
        if ($shInstance::delete_folder_sh("/etc/letsencrypt/live/" . $_REQUEST['ssl_directory']) == 0) {
            echo "error";
        } else {
            echo "1";
        }
    }
    //echo $domains->user_id;
    die();
} else if ($q_type == "delete_domain") {
    try {

        if (isset($_REQUEST['force_delete'])) {
            $force_delete = 1;
        } else {
            $force_delete = 0;
        }
        $error = error_get_last();
        $log_str = "";
        
        $domain = $domainsInstance->select_domains($_REQUEST['db_that_will_be_overwrite_guid'], "");
        $wpExtractDirClean = $_SERVER['DOCUMENT_ROOT'] . "wp_cus/customer_" . $domain->user_id . "/" . $domain->domain_name;
        if (sh::delete_folder_sh($wpExtractDirClean) === 0 && $force_delete === 0) {
            errors::addError("delete_folder_sh","index.php->delete_domain");
            //echo "error";
            //die();
        }
        $apacheConfigFile = '/etc/apache2/sites-available/' . $domain->domain_name . '_ssl.conf';
        if (sh::delete_file_sh($apacheConfigFile) === 0 && $force_delete === 0) {
           // echo "error";
            //die();
        }
        // Define the Apache configuration file path



        if (file_exists("/etc/letsencrypt/live/$domain->domain_name/cert.pem")) {
            try{
                unlink("/etc/letsencrypt/live/$domain->domain_name/cert.pem");
            }
            catch (Exception $e) {
                $errors->addError($e->getMessage(), "index.php->delete_domain");
            }
           
        }
        if(file_exists("/etc/letsencrypt/live/$domain->domain_name/privkey.pem")){
            try{
                unlink("/etc/letsencrypt/live/$domain->domain_name/privkey.pem");
            }
            catch (Exception $e) {
                $errors->addError($e->getMessage(), "index.php->delete_domain");
            }
        }
        if(file_exists("/etc/letsencrypt/live/$domain->domain_name/chain.pem")){
            try{
                unlink("/etc/letsencrypt/live/$domain->domain_name/chain.pem");
            }
            catch (Exception $e) {
                $errors->addError($e->getMessage(), "index.php->delete_domain");
            }
        }

        $log_str = $log_str . "\nletsencrypt";
        $log_str = $log_str . $dbInstance->delete_db($domain->database_name);
        $log_str = $log_str . "\ndelete_db";
        $log_str = $log_str . $dbInstance->deleteUserAccount($domain->database_username);
        $log_str = $log_str . "\ndeleteUserAccount";
        $log_str = $log_str . $domainsInstance->delete_domain_from_domains_table($domain->guid);
        $log_str = $log_str . "\ndelete_domain_from_domains_table";
        $logsInstance->insertLog(date('Y-m-d H:i:s'), $domains->user_id, $log_str, $domain->domain_name);
        echo 1;
    } catch (Exception $e) {
        $errors->addError($e->getMessage(), "index.php->delete_domain");
        echo 0;
    }
}
else if($q_type == "show_themes"){
    $args = array(
        'post_type' => 'post',
        'post_status' => 'publish'
    );
    
    $custom_query = new WP_Query($args);
    
    $post_data = array(); // Initialize an array to store post data
    
    if ($custom_query->have_posts()) {
        $dbInstance = db::getInstance();
        $domainsInstance = new domains();
        $domains_ = $domainsInstance->domain_list_per_user_id_admin(25);
    
        while ($custom_query->have_posts()) {
            $custom_query->the_post();
    
            $post_item = array(
                'title' => get_the_title(),
                'categories' => get_the_category_list(', '),
                'sample_url' => esc_url(get_post_meta(get_the_ID(), 'sample_url', true)),
                'install_url' => 'add-site/?theme_id=' . $domainsInstance->get_domain_by_id($domains_, get_post_meta(get_the_ID(), 'domain_id', true)),
                // Add more fields as needed
            );
    
            $post_data[] = $post_item;
        }
    } else {
        // If no posts are found
        echo json_encode(array('message' => 'No posts found.'));
    }
    
    // Restore original Post Data
    wp_reset_postdata();
    
    // Encode the post data array as JSON
    echo json_encode($post_data);
}



// Close the database connection
$pdo = null;
