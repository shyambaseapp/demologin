<?php

$backup_source = '/var/www/demo/wordpress';
$backup_destination = '/srv/www/';
$reset_source = '/srv/www/wordpress';
$reset_destination = '/var/www/demo/';

require __DIR__ . '/wp-config.php';

$dbname = constant("DB_NAME");
$dbuser = constant("DB_USER");
$dbpass = constant("DB_PASSWORD");
$dbhost = constant("DB_HOST");

// Create connection
$conn = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
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

#  Backup Wordpress Site:

$process1   =   shell_exec('sudo mysqldump -u root -p ' . $dbname . ' >' . $backup_destination . 'demo_backup.sql');
$process2   =   shell_exec('sudo rsync -avz ' . $backup_source . '  ' . $backup_destination . '');

#restore Wordpress Sites:

if ($hours >= 24) {

    $process1   =   shell_exec('mysqldump -u root -p ' . $dbname . ' </srv/www/demo_backup.sql');
    $process2   =   shell_exec('sudo rsync -avz ' . $reset_source . ' ' . $reset_destination . '');
}
