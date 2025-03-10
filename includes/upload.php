<?php

// Load up Wordpress
//
$wp_load = realpath("../../../../wp-load.php");
if(!file_exists($wp_load)) {
  $wp_config = realpath("../../../../wp-config.php");
  if (!file_exists($wp_config)) {
      exit("Can't find wp-config.php or wp-load.php");
  } else {
      require_once($wp_config);
      require_once('../../../../wp-includes/pluggable.php');
  }
} else {
  require_once($wp_load);
}

global $wpdb, $wp_rewrite;

if ( is_ssl() && empty($_COOKIE[SECURE_AUTH_COOKIE]) && !empty($_REQUEST['auth_cookie']) )
			$_COOKIE[SECURE_AUTH_COOKIE] = $_REQUEST['auth_cookie'];
elseif ( empty($_COOKIE[AUTH_COOKIE]) && !empty($_REQUEST['auth_cookie']) )
			$_COOKIE[AUTH_COOKIE] = $_REQUEST['auth_cookie'];


$user_id = wp_validate_auth_cookie();
if ($user_id) set_current_user($user_id);
$nonce=$_REQUEST['_wpnonce'];
if ( !is_user_logged_in() || !current_user_can('edit_posts') ||  !wp_verify_nonce($nonce, 'webtv-upload')) {
   //header("HTTP/1.1 500 Internal Server Error"); // This will trigger an uploadError event in SWFUpload
		echo "No tiene permisos para acceder a este contenido.";
		exit(0);
}
$post_id = (int)trim($_REQUEST['id']);

if ($post_id < 0) {
		echo "Bad ID on POST.";
		exit(0);
}


// Check post_max_size (http://us3.php.net/manual/en/features.file-upload.php#73762)
	$POST_MAX_SIZE = ini_get('post_max_size');
	$unit = strtoupper(substr($POST_MAX_SIZE, -1));
	$multiplier = ($unit == 'M' ? 1048576 : ($unit == 'K' ? 1024 : ($unit == 'G' ? 1073741824 : 1)));
	
	if ((int)$_SERVER['CONTENT_LENGTH'] > $multiplier*(int)$POST_MAX_SIZE && $POST_MAX_SIZE) {
		//header("HTTP/1.1 500 Internal Server Error"); // This will trigger an uploadError event in SWFUpload
		HandleError("POST exceeded maximum allowed size.",$post_id);
		exit(0);
	}


// Settings
	$save_path = ABSPATH . get_option('upload_path') . '/';				// The path were we will save the file (getcwd() may not be reliable and should be tested in your environment)
	$upload_name = "Filedata";
	$max_file_size_in_bytes = 2147483647;				// 2GB in bytes
	$extension_whitelist = array("mp4", "m4v", "mov", "flv", "qt", "mpg", "mpeg", "3gp", "avi", "wmv", "f4v");	// Allowed file extensions
	$valid_chars_regex = '.A-Z0-9_!@#$%^&()+={}\[\]\',~`-';				// Characters allowed in the file name (in a Regular Expression format)
	
// Other variables	
	$MAX_FILENAME_LENGTH = 260;
	$file_name = "";
	$file_extension = "";
	$uploadErrors = array(
        0=>"There is no error, the file uploaded with success",
        1=>"The uploaded file exceeds the upload_max_filesize directive in php.ini",
        2=>"The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form",
        3=>"The uploaded file was only partially uploaded",
        4=>"No file was uploaded",
        6=>"Missing a temporary folder"
	);


// Validate the upload
	if (!isset($_FILES[$upload_name])) {
		HandleError("No upload found in \$_FILES for " . $upload_name);
		exit(0);
	} else if (isset($_FILES[$upload_name]["error"]) && $_FILES[$upload_name]["error"] != 0) {
		HandleError($uploadErrors[$_FILES[$upload_name]["error"]],$post_id);
		exit(0);
	} else if (!isset($_FILES[$upload_name]["tmp_name"]) || !@is_uploaded_file($_FILES[$upload_name]["tmp_name"])) {
		HandleError("Upload failed is_uploaded_file test.",$post_id);
		exit(0);
	} else if (!isset($_FILES[$upload_name]['name'])) {
		HandleError("File has no name.",$post_id);
		exit(0);
	}
	
// Validate the file size (Warning: the largest files supported by this code is 2GB)
	$file_size = @filesize($_FILES[$upload_name]["tmp_name"]);
	if (!$file_size || $file_size > $max_file_size_in_bytes) {
		HandleError("File exceeds the maximum allowed size",$post_id);
		exit(0);
	}
	
	if ($file_size <= 0) {
		HandleError("File size outside allowed lower bound",$post_id);
		exit(0);
	}


// Validate file name (for our purposes we'll just remove invalid characters)
	$file_name = 'webtv_' . preg_replace('/[^'.$valid_chars_regex.']|\.+$/i', "-", basename($_FILES[$upload_name]['name']));
	if (strlen($file_name) == 0 || strlen($file_name) > $MAX_FILENAME_LENGTH) {
		HandleError("Invalid file name",$post_id);
		exit(0);
	}


// Validate that we won't over-write an existing file
	if (file_exists($save_path . $file_name)) {
		HandleError("File with this name already exists",$post_id);
		exit(0);
	}

// Validate file extension
	$path_info = pathinfo($_FILES[$upload_name]['name']);
	$file_extension = $path_info["extension"];
	$is_valid_extension = false;
	foreach ($extension_whitelist as $extension) {
		if (strcasecmp($file_extension, $extension) == 0) {
			$is_valid_extension = true;
			break;
		}
	}
	if (!$is_valid_extension) {
		HandleError("Invalid file extension",$post_id);
		exit(0);
	}

// Validate file contents (extension and mime-type can't be trusted)
	/*
		Validating the file contents is OS and web server configuration dependant.  Also, it may not be reliable.
		See the comments on this page: http://us2.php.net/fileinfo
		
		Also see http://72.14.253.104/search?q=cache:3YGZfcnKDrYJ:www.scanit.be/uploads/php-file-upload.pdf+php+file+command&hl=en&ct=clnk&cd=8&gl=us&client=firefox-a
		 which describes how a PHP script can be embedded within a GIF image file.
		
		Therefore, no sample code will be provided here.  Research the issue, decide how much security is
		 needed, and implement a solution that meets the needs.
	*/


// Process the file
	/*
		At this point we are ready to process the valid file. This sample code shows how to save the file. Other tasks
		 could be done such as creating an entry in a database or generating a thumbnail.
		 
		Depending on your server OS and needs you may need to set the Security Permissions on the file after it has
		been saved.
	*/
	if (!@move_uploaded_file($_FILES[$upload_name]["tmp_name"], $save_path.$file_name)) {
		HandleError("File could not be saved.",$post_id);
		exit(0);
	}
	if (!chmod($save_path.$file_name, 0666)) {
		HandleError("Downloaded file, but we can't change file permissions",$post_id);
	    exit(0);
    }

	add_post_meta($post_id, '_webtv_upload_status', 'uploaded', true) or
	update_post_meta($post_id, '_webtv_upload_status', 'uploaded');
	$filedetails = array();
	$filedetails['local_file'] = $file_name;
	$filedetails['full_path'] = $save_path.$file_name;
	$filedetails['size'] = $file_size;
	$filedetails['content_type'] = webtv_content_type($save_path.$file_name);
	add_post_meta($post_id, '_webtv_file_details', $filedetails, true) or
	update_post_meta($post_id, '_webtv_file_details', $filedetails);
	echo "sucess";
	exit(1);


/* Handles the error output. This error message will be sent to the uploadSuccess event handler.  The event handler
will have to check for any error messages and react as needed. */
function HandleError($message,$post_id) {
	//header("HTTP/1.1 500 Internal Server Error");
	add_post_meta($post_id,'_webtv_upload_status','error',true) or
	update_post_meta($post_id, '_webtv_upload_status', 'error');
	add_post_meta($post_id, '_webtv_upload_status_msg', $message, true) or
	update_post_meta($post_id, '_webtv_upload_status_msg', $message);
	echo $message;
}
?>