<?php

#
/*
Plugin Name: WebTV Vlogger Plugin
Plugin URI: http://www.webstratega.com/plugins/webtv-plugin-wordpress/
Version: 0.6
Author: Edgar de Le&oacute;n - edeleon
Author URI: http://www.webstratega.com/about/
Description: Lets you attach a video to a post and upload to most popular video distribution sites like YouTube, Vimeo and Blip.tv, after video is uploaded and processed get and inserts the embed code from the sites into custom fields on the post.  The plugin has de possibility to extend any other distribution site creating extra drivers
License: GPL (http://www.fsf.org/licensing/licenses/info/GPLv2.html) 
*/

require_once (dirname (__FILE__).'/webtv_Admin.php');
require_once (dirname (__FILE__).'/webtv_Actions.php');
foreach (glob(dirname (__FILE__).'/Drivers/*.php') as $file) { 
	include_once $file;
}

if(class_exists('Webtv_Admin')) {
	$WebtvAdminClass = new Webtv_Admin;
}
else {
	die('not registered!');
}


if(isset($WebtvAdminClass)) {
	add_action('admin_menu', array(&$WebtvAdminClass,'add_config_page'));
	add_action('wp_print_scripts', array(&$WebtvAdminClass,'orderlist_js'));
}

add_action('submitpost_box', 'webtv_status_box');
add_action('save_post','webtv_save_and_schedule');
add_action('webtv_upload','webtv_upload_video');
add_action('webtv_getembed','webtv_getembed_video');


?>