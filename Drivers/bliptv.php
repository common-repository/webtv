<?php

/*
Author: Edgar de Le&oacute;n - edeleon
Author URI: http://www.sinctrl.com/edeleon
Description: Lets you attach a video to a post and upload to most popular video distribution sites like YouTube, Vimeo and Blip.tv, after video is uploaded and processed get and inserts the embed code from the sites into custom fields on the post.  The plugin has de possibility to extend any other distribution site creating extra drivers
License: GPL (http://www.fsf.org/licensing/licenses/info/GPLv2.html) 
*/

function bliptv_settings() {
	$service_config = Array(
		'service' 	=> "bliptv",      //File with actions must be named like $service
		'service_url' => "blip.tv",
		'username' 	=> "username",
		'password' 	=> "passwd",
		'enabled' 	=> false
	);
	return $service_config;
}

function bliptv_extras() {
	//ini_get('max_execution_time'); // to Retrieve execution time
	//set_time_limit(60); // to set time limit
	
	$c = curl_init("http://www.blip.tv/?section=categories&cmd=view&skin=api");
  	curl_setopt($c, CURLOPT_HEADER, 0);
 	curl_setopt($c, CURLOPT_RETURNTRANSFER, TRUE);
  	$data = curl_exec($c);
  	$xml = simplexml_load_string($data);
  	curl_close($c);
  	$category = array();
	  if ($xml->status == "OK") {   
	    foreach ($xml->payload->category as $categorydata) {
	    	$id = intval($categorydata->id);
			$category[$id] = (string)$categorydata->name;   		
		} 
		 
	  } else {
	    $category = array();
	  }
	  
	$c = curl_init("http://www.blip.tv/?section=licenses&cmd=view&skin=api");
  	curl_setopt($c, CURLOPT_HEADER, 0);
 	curl_setopt($c, CURLOPT_RETURNTRANSFER, TRUE);
  	$data = curl_exec($c);
  	$xml = simplexml_load_string($data);
  	curl_close($c);
  	$license = array();
	  if ($xml->status == "OK") {   
	    foreach ($xml->payload->license as $licensedata) {
	    	$id = intval($licensedata->id);
			$license[$id] = (string)$licensedata->name;   		
		} 
		 
	  } else {
	    $license = array();
	  }

	  if (empty($category) && empty($license)) {
	  	$extras = array();
	  } else {
	  	$extras = array('category' => $category, 'license' => $license);
	  }
	  
	return $extras;
}

function upload_bliptv($settings,$entry) {
	//ini_get('max_execution_time'); // to Retrieve execution time
	//set_time_limit(60*10);
	$username = $settings['username'];
	$password = $settings['password'];
	$license = intval($settings['license']);
	$category = intval($settings['category']);
	
	$upload_status = '';
	$error = false;
	$file = $entry['fileinfo'];

		$postfields = array(
	    'section' => 'file',
	    'cmd' => 'post',
	    'post' => 1,
	    'userlogin' => $username,
	    'password' => $password,
	    'title' => htmlspecialchars(stripslashes($entry['titulo'])),
	    'description' => htmlspecialchars(stripslashes($entry['desc'])),
	    'topics' => htmlspecialchars(stripslashes($entry['tags'])),
	    'license' => $license,
	    'categories_id' => $category,
	    'file' => '@'.$file,
	  	);
  	
  	  $c = curl_init("http://uploads.blip.tv?skin=api");
	  curl_setopt($c, CURLOPT_POST, TRUE);
	  curl_setopt($c, CURLOPT_HEADER, 0);
	  curl_setopt($c, CURLOPT_POSTFIELDS, $postfields);
	  curl_setopt($c, CURLOPT_RETURNTRANSFER, TRUE);
	  $data = curl_exec($c);
	  $xml = simplexml_load_string($data);
	  curl_close($c);
  
	  if ($xml->status == "OK") {
	    $videoid = (string)$xml->payload->asset->item_id;
	    $upload_status_desc = 'processing';	    
	    $retry = false;
	  } else {
	  	$upload_status = 'error';
	  	$error = true;
	  	$upload_status_desc = (string)$error_desc = $xml->error->code ."-". $xml->error->message;
	  	$retry = true;
	  }
	  
	//Preparar la respuesta
	$service_response = array();
	$service_response['service'] = 'bliptv';
	$service_response['video_id'] = $videoid;
	$service_response['status'] = $upload_status;
	$service_response['status_description'] = $upload_status_desc;
	$service_response['error'] = $error;
	$service_response['error_description'] = $error_desc;
	$service_response['retry'] = $retry;
	return $service_response;	  
}

function getembed_bliptv($settings,$videodata) {
	$error = false; $embed_code = ''; $retry = true;
	//ini_get('max_execution_time'); // to Retrieve execution time
	//set_time_limit(60*5); // to set time limit
	$id = $videodata['video_id'];
	
	$c = curl_init("http://www.blip.tv/file/$id?skin=api");
  	curl_setopt($c, CURLOPT_HEADER, 0);
 	curl_setopt($c, CURLOPT_RETURNTRANSFER, TRUE);
  	$data = curl_exec($c);
  	$xml = simplexml_load_string($data);
  	curl_close($c);
  	
  if ($xml->status == "OK") {   
    $videoid = $xml->payload->asset->item_id;
    if ($xml->payload->asset->deleted == "true") { 
    	$upload_status = 'error';
    	$upload_status_desc = $error_desc = _("Video deleted");
    	$error = true;
    	$retry = false;
    } else {
     $views = $xml->payload->asset->views;
     $title = $xml->payload->asset->title;
     $license = $xml->payload->asset->license->name;
     	$i = 0;
	    foreach ($xml->payload->asset->mediaList->media as $media) {
	    	foreach($media as $detail => $data) {
	    		if ($detail == 'link') {
	    			$attr = $data->Attributes();
	    			foreach ($attr as $type => $value) {
	    				$video_role[$i][$type] = (string)$value;
	    			}
	    			//echo $xml->payload->asset->mediaList->media->link->@attributes->href;
	    		} else {
	    			$video_role[$i][$detail] = (string)$data; 
	    		}
	    	}
	    $i++;	
	    } 
	    $conversion = $xml->payload->asset->conversions->conversion;
	    $upload_status_desc = $conversion->status."-".$conversion->target."-".$conversion->role;
	    $embed_id = (string)$xml->payload->asset->embedLookup;
	    $embed_url = (string)$xml->payload->asset->embedUrl;
	    $embed_code = (string)$xml->payload->asset->embedCode;
	    $video_url = "http://www.blip.tv/file/$id";
	    $retry = false;
	 }
  } else {
    $error_desc = (string)$xml->notice;
    $error = false;  //File not found - video uploaded but cannot get the id
    $retry = false;
  }
  
  	//Preparar la respuesta
	$service_response = array();
	$service_response['service'] = 'bliptv';
	$service_response['status'] = $upload_status;
	$service_response['status_description'] = $upload_status_desc;
	$service_response['error'] = $error;
	$service_response['error_description'] = $error_desc;
	$service_response['embed'] = $embed_code;
	$service_response['watch_url'] = $video_url;
	$service_response['retry'] = $retry;
	$service_response['video_id'] = $videodata['video_id'];
	//Optional data
	$service_response['flv_source'] = $embed_url;
	$role_options = array('href','duration','size');
	foreach ($video_role as $item => $role) {
		$rolename = $role['role'];
		if (!empty($rolename)) {
			foreach ($role as $data => $value) {
				if (in_array($data,$role_options))
					$service_response[$rolename."_".$data] = $value;
			}
		}
	}
	return $service_response;

}
?>