<?php
define("BACKUP_DIR", "/srv/backups/");
require __dir__ . '/vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class DemoManager
{

    var $logger;
    var $dbuser, $dbname, $dbpass, $dbhost;

    function __construct($logger)
    {
        $this->logger = $logger;
    }

    function read_wordpress_config($backup_destination)
    {
        try {
            if (!file_exists($backup_destination)) {
                $this->logger->error('file/dir does not exists : ' . $backup_destination);
                throw new Exception('file/dir does not exists : ' . $backup_destination);
            } else {
                $myfile = fopen("$backup_destination/wp-config.php", "r");
                $wp_config = fread($myfile, filesize("$backup_destination/wp-config.php"));
                $regex = '/DB_NAME[\' ,"]+(.*?)[\'"]/';
                preg_match($regex, $wp_config, $return);
                $this->dbname = $return[1];

                $regex = '/DB_USER[\' ,"]+(.*?)[\'"]/';
                preg_match($regex, $wp_config, $return);
                $this->dbuser = $return[1];

                $regex = '/PASSWORD[\' ,"]+(.*?)[\'"]/';
                preg_match($regex, $wp_config, $return);
                $this->dbpass = $return[1];

                $regex = '/DB_HOST[\' ,"]+(.*?)[\'"]/';
                preg_match($regex, $wp_config, $return);
                $this->dbhost = $return[1];;
            }
        } catch (Exception $e) {
            echo $e;
        }
    }

    function check_last_login($reset_destination)
    {
        try {
            $conn = new mysqli($this->dbhost, $this->dbuser, $this->dbpass, $this->dbname);
            if ($conn->connect_error) {
                $this->logger->error('Datbase Connection failed  ' . $reset_destination);
                throw new Exception("Database Connection failed ");
            }

            // Check connection
            $sql = "select option_value AS 'last_login_date' from wp_options WHERE option_name='last_login_date'";
            $result = $conn->query($sql);
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $last_login_date = $row["last_login_date"];
                }
            } else {
                echo "user not login yet";
            }
            $conn->close();

            if (!$last_login_date) {
                echo "no any body login";
            } else {
                date_default_timezone_set('Asia/Kolkata');
                $current_date = date('d-m-Y H:i');
                $date1  =   strtotime($last_login_date);
                $date2  =   strtotime($current_date);
                $hours  =   ($date2 - $date1) / (60 * 60);
            }
            return $hours;
        } catch (Exception $e) {
            echo $e;
        }
    }

    function exec_command($command)
    {
        exec($command, $output, $result);
        if ($result) {
            throw new Exception("Command execution failed for : " . $command . " with : " . $result);
        }
    }

    #  Backup Wordpress Site:

    function backup_site($backup_source, $backup_destination)
    {
        $this->logger->info("Starting Backup of : " . $backup_source);
        if (!is_dir($backup_destination)) {
            mkdir($backup_destination);
        }
        $backup_db   =   $this->exec_command('mysqldump --no-tablespaces --user=' . $this->dbuser . ' --password=' . $this->dbpass . ' --result-file=' . $backup_destination . '/dbdup.sql --databases ' . $this->dbname);
        $backup_site   =   $this->exec_command('sudo rsync -avz ' . $backup_source . '/  ' . $backup_destination . '');
        $this->logger->info('Backup Completed');
    }

    #restore Wordpress Sites:

    function reset_site($reset_source, $reset_destination)
    {
        $this->logger->info("Starting reset of : " . $reset_source);
        $reset_db   =  $this->exec_command('mysql -u ' . $this->dbuser . ' -p' . $this->dbpass . ' ' . $this->dbname . '< ' . $reset_source . '/dbdup.sql');
        $reset_site   =  $this->exec_command('sudo rsync -r ' . $reset_source . '/ ' . $reset_destination);
        $this->logger->info("Reset Completed");
    }

    function encode_backup_path($argv)
    {
        try {
            if (sizeof($argv) > 2) {
                $data = str_replace('/', '_', $argv[2]);
                $backup_destination = constant("BACKUP_DIR") . $data;
                return $backup_destination;
            } else {
                throw new Exception("please provide full path of site in command-line");
            }
        } catch (Exception $e) {
            echo $e;
        }
    }

    function encode_reset_path($backup_destination)
    {
        $reset_destination = str_replace('_', '/', basename($backup_destination));
        return $reset_destination;
    }
}


/**
 *  Start of main execution of code
 */


$logger = new Logger('demo_manager');
$logger->pushHandler(new StreamHandler(__dir__ . '/log.txt', Logger::DEBUG));

$demo_manager = new DemoManager($logger);

$logger->info("Starting Demo Manager with args '" . implode(" ", $argv) . "'");

try {
    if (sizeof($argv) < 2) {
        $logger->warning('Insufficient Arguments');
        $logger->warning("Please include the arguments like --backup, --reset or --reset-all with file/dir path ");
        throw new Exception("Please include the arguments like --backup, --reset or --reset-all with file/dir path ");
    } else {
        switch ($argv[1]) {
            case "--backup":

                try {
                    if (sizeof($argv) <= 2) {
                        $logger->error("please provide full path of site in command-line");
                        throw new Exception("please provide full path of site in command-line");
                    } else {
                        $backup_source = $argv[2];
                        $backup_destination = $demo_manager->encode_backup_path($argv);
                        $demo_manager->read_wordpress_config($backup_source);
                        if (file_exists($argv[2]) == 1) {
                            $demo_manager->backup_site($backup_source, $backup_destination, $logger);
                        }
                        break;
                    }
                } catch (Exception $e) {
                    echo $e;
                }
                break;
            case "--reset":
                try {
                    if (sizeof($argv) <= 2) {
                        $logger->error("please provide full path of site in command-line");
                        throw new Exception("please provide full path of site in command-line");
                    } else {
                        $backup_destination = $demo_manager->encode_backup_path($argv);
                        $reset_destination = $demo_manager->encode_reset_path($backup_destination);
                        $demo_manager->read_wordpress_config($backup_destination);
                        if (file_exists($argv[2]) && $demo_manager->check_last_login($reset_destination) >= 24) {
                            $demo_manager->reset_site($backup_destination, $reset_destination);
                        }
                        break;
                    }
                } catch (Exception $e) {
                    echo $e;
                }
                break;
            case "--reset-all":

                $a = scandir(constant("BACKUP_DIR"));
                $count = count($a);
                for ($i = 0; $i < $count; $i++) {
                    if ($a[$i] != '.' && $a[$i] != '..') {
                        $backup_destination =  constant("BACKUP_DIR") . $a[$i];
                        $reset_destination  = str_replace('_', '/', basename(constant("BACKUP_DIR") . $a[$i]));
                        $demo_manager->read_wordpress_config($backup_destination);
                        $hours = $demo_manager->check_last_login($reset_destination);
                        try {
                            if ($hours < 24) {
                                $logger->error("Time is less then 24 for :".$reset_destination );
                                throw new Exception("Time is less then 24 for :".$reset_destination );
                            } else {
                                if (file_exists($backup_destination) && $hours >= 24 && file_exists($reset_destination)) {
                                    $demo_manager->reset_site($backup_destination, $reset_destination);
                                }
                            }
                        } catch (Exception $e) {
                            echo $e;
                        }
                    }
                }
                break;
            default:
                $logger->error("User give invalid arguments --backup or --reset or --reset-all with file path");
                echo "Invalid Arguments";
                break;
        }
    }
} catch (Exception $e) {
    echo $e;
}
$logger->info("Exiting Demo Manager");
