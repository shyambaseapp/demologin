<?php

require __DIR__ . '/wp-config.php';
class Demo
{

    function __construct($dbname, $dbuser, $dbpass, $dbhost)
    {

        $this->dbname = constant("DB_NAME");
        $this->dbuser = constant("DB_USER");
        $this->dbpass = constant("DB_PASSWORD");
        $this->dbhost = constant("DB_HOST");
    }

    # Connect database using wp_config.php file : 
    function main()
    {
        global $argv;

        // Create connection
        $conn = new mysqli($this->dbhost, $this->dbuser, $this->dbpass, $this->dbname);
        // Check connection
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
        $sql = "select option_value AS 'last_login_date' from wp_options WHERE option_name='last_login_date'";
        $result = $conn->query($sql);
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $last_login_date = $row["last_login_date"];
            }
        } else {
            echo "0 results";
        }
        $conn->close();

        if (!$last_login_date) {
            echo "no any body login";
        } else {
            $current_date   =   current_time('Y-m-d H:i:s');
            $date1  =   strtotime($last_login_date);
            $date2  =   strtotime($current_date);
            $hours  =   ($date2 - $date1) / (60 * 60);
        }

        #Calling backup_site function : 

        if ($argv[1] == '--backup') {
            if (file_exists($argv[2]) == 1) {
                $backup_source = $argv[2];
                $data = str_replace('/', '_', $backup_source);
                $backup_destination = '/srv/www/backup/' . $data;
                $this->backup_site($backup_source, $backup_destination);
            } else {
                echo "File/Dir does not exists";
            }
        }

        #Calling resret_site function : 

        if ($argv[1] == '--reset') {
            if (file_exists($argv[2]) && $hours >= 24) {
                $reset_source = $argv[2];
                $data = strstr($reset_source, "_");
                $data =  preg_match("/_.*_/m", $data, $data_match);
                $data = str_replace('_', '/', $data_match[0]);
                $reset_destination = $data;
                echo $reset_source . "   ";
                echo $reset_destination . "   ";
                $this->reset_site($reset_source, $reset_destination);
            } else {
                echo "File/Dir does not exists";
            }
        }
    }


    function exec_command($command) {
        echo $command;
        $result = shell_exec($command);
        echo $result;
    }
    #  Backup Wordpress Site:

    function backup_site($backup_source, $backup_destination)
    {

        $backup_db   =   $this->exec_command('mysqldump --no-tablespaces -u '.$this->dbuser.'  -p'.$this->dbpass.' '.$this->dbname . ' >  demo_backup.sql');
        $backup_site   =   shell_exec('sudo rsync -avz ' . $backup_source . '  ' . $backup_destination . '');
    }


    #restore Wordpress Sites:

    function reset_site($reset_source, $reset_destination)
    {

        $reset_db   =   shell_exec('mysqldump -u root -p'.$this->dbname . ' < demo_backup.sql');
        $reset_site   =   shell_exec('sudo rsync -avz ' . $reset_source . ' ' . $reset_destination . '');
    }

    #restore all wordpress site : 

    function reset_all_site()
    {
    }
}
$objdemo = new Demo($dbname, $dbuser, $dbpass, $dbhost);
$objdemo->main();
