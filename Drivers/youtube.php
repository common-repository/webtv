<?php

/*
Description: Lets you attach a video to a post and upload to most popular video distribution sites like YouTube, Vimeo and Blip.tv, after video is uploaded and processed get and inserts the embed code from the sites into custom fields on the post.  The plugin has de possibility to extend any other distribution site creating extra drivers
License: GPL (http://www.fsf.org/licensing/licenses/info/GPLv2.html) 
*/

$path = dirname(__FILE__);
set_include_path(get_include_path() . PATH_SEPARATOR . $path);
require_once $path.'/Zend/Loader.php';

function youtube_settings() {
	$service_config = Array(
		'service' 	=> "youtube",      //File with actions must be named like $service
		'service_url' => "youtube.com",
		'username' 	=> "username",
		'password' 	=> "passwd",
		'devkey' 	=> "devkey",
		'enabled' 	=> false
	);
	return $service_config;
}

function youtube_extras() {
	$extras = array('category'=> array(	'Autos'=>'Autos &amp; Vehicles',
										'Music'=>'Music',
										'Animals'=>'Pets &amp; Animals',
										'Sports'=>'Sports',
										'Travel'=>'Travel &amp; Events',
										'Games'=>'Gadgets &amp; Games',
										'Comedy'=>'Comedy',
										'People'=>'People &amp; Blogs',
										'News'=>'News &amp; Politics',
										'Entertainment'=>'Entertainment',
										'Education'=>'Education',
										'Howto'=>'Howto',
										'Nonprofit'=>'Nonprofit &amp; Activism',
										'Tech'=>'Science &amp; Technology')
					);
	return $extras;
}

function upload_youtube($settings,$entry) {
	Zend_Loader::loadClass('Zend_Gdata_AuthSub');
	Zend_Loader::loadClass('Zend_Gdata_ClientLogin');
	Zend_Loader::loadClass('Zend_Gdata_YouTube');
	Zend_Loader::loadClass('Zend_Gdata_YouTube_VideoEntry');
	$error = false; $retry = 0;
	if (!isset($settings['username']) || (empty($settings['username']))) { 
		$error = true;
		$error_desc = _("Please fill username field at webtv WordPress settings"); 
		$retry = 1;
	}
	if (!isset($settings['password']) || (empty($settings['password']))) { 
		$error = true;
		$error_desc = _("Please fill password field at webtv WordPress settings"); 
		$retry = 1;
	}
	if (!isset($settings['devkey']) || (empty($settings['devkey']))) { 
		$error = true;
		$error_desc = _("Please insert your Developer Key at webtv WordPress settings"); 
		$retry = 1;
	}
	if (!isset($settings['category']) || (empty($settings['category']))) { 
		$error = true;
		$error_desc = _("Please select a default category at webtv WordPress settings"); 
		$retry = 1;
	}
	//set_time_limit(60*5);

	$authenticationURL= 'https://www.google.com/youtube/accounts/ClientLogin';
	$httpClient = Zend_Gdata_ClientLogin::getHttpClient(
	                                          $username = $settings['username'],
	                                          $password = $settings['password'],
	                                          $service = 'youtube',
	                                          $client = null,
	                                          $source = 'WebTV Vlogger Wordpress Plugin', // identifying application
	                                          $loginToken = null,
	                                          $loginCaptcha = null,
	                                          $authenticationURL);
	   
	$config_timeout = array('timeout' => 120);  //Maybe you will need to raise this parameter if you are working with large files.
    $httpClient->setConfig($config_timeout);                                   
	$myDeveloperKey = $settings['devkey'];
	$httpClient->setHeaders('X-GData-Key', "key=${myDeveloperKey}");  

	$yt = new Zend_Gdata_YouTube($httpClient);
	// create a new Zend_Gdata_YouTube_VideoEntry object
	$myVideoEntry = new Zend_Gdata_YouTube_VideoEntry();
	// create a new Zend_Gdata_App_MediaFileSource object
	$filesource = $yt->newMediaFileSource($entry['fileinfo']);
	$filesource->setContentType($entry['content_type']);
	// set slug header
	$filesource->setSlug($entry['fileinfo']);
	// add the filesource to the video entry
	$myVideoEntry->setMediaSource($filesource);
	// create a new Zend_Gdata_YouTube_MediaGroup object
	$mediaGroup = $yt->newMediaGroup();
	$mediaGroup->title = $yt->newMediaTitle()->setText($entry['titulo']);
	$mediaGroup->description = $yt->newMediaDescription()->setText($entry['desc']);
	// the category must be a valid YouTube category
	// optionally set some developer tags (see Searching by Developer Tags for more details)
	$mediaGroup->category = array(  
	  $yt->newMediaCategory()->setText($settings['category'])->setScheme('http://gdata.youtube.com/schemas/2007/categories.cat'), 
	  $yt->newMediaCategory()->setText('webtv_plugin')->setScheme('http://gdata.youtube.com/schemas/2007/developertags.cat'),
	  $yt->newMediaCategory()->setText('wordpress')->setScheme('http://gdata.youtube.com/schemas/2007/developertags.cat')
	  );
	// set keywords
	$mediaGroup->keywords = $yt->newMediaKeywords()->setText($entry['tags']);  //Linea con error, solucionado cambiando a $yt
	
	$myVideoEntry->mediaGroup = $mediaGroup;
	// set video location
	/*$yt->registerPackage('Zend_Gdata_Geo');
	$yt->registerPackage('Zend_Gdata_Geo_Extension');
	$where = $yt->newGeoRssWhere();
	$position = $yt->newGmlPos('37.0 -122.0');
	$where->point = $yt->newGmlPoint($position);
	$entry->setWhere($where);
	*/
	
	// upload URL for the currently authenticated user
	$uploadUrl = 'http://uploads.gdata.youtube.com/feeds/api/users/default/uploads';
		
	try {
	  $newEntry = $yt->insertEntry($myVideoEntry, $uploadUrl, 'Zend_Gdata_YouTube_VideoEntry');
	  
	} catch (Zend_Gdata_App_HttpException $httpException) {
	    $error_desc[] = $httpException->getRawResponseBody();
	    $error = true;
	    $retry = 1;
	} catch (Zend_Gdata_App_Exception $e) {
		$error_desc[] = $e->getMessage();
		$error = true;
		$retry = 1;
	}
	if (!$error) {
		$videoid = $newEntry->getVideoId();
	}
	if (empty($videoid)) {
		$error_desc[] = _("Can't get ID from YouTube, please verify if the video has been published and check the max_execution_time on your php.ini");
		$error = true;
		$retry = 0;
	} 
	
	// check if video is in draft status
	try {
	  $control = $newEntry->getControl();
	} catch (Zend_Gdata_App_Exception $e) {
	  	$error_desc[] = $e->getMessage();
		$error = true;
	}
	if ($control instanceof Zend_Gdata_App_Extension_Control) {
	  if ($control->getDraft() != null && $control->getDraft()->getText() == 'yes') {
	    $state = $newEntry->getVideoState();
	    if ($state instanceof Zend_Gdata_YouTube_Extension_State) {
	      $upload_status = $state->getName();
	      $upload_status_desc = $state->getText();
	    } else {
	      $upload_status_desc = _("Can't get video status from YouTube, try again later");
	      $retry = 0;
	    }
	  }
	}
	
	//Preparar la respuesta
	$service_response = array();
	$service_response['service'] = 'youtube';
	$service_response['video_id'] = $videoid;
	$service_response['status'] = $upload_status;
	$service_response['status_description'] = $upload_status_desc;
	$service_response['error'] = $error;
	$service_response['error_description'] = $error_desc;
	$service_response['retry'] = $retry;
	return $service_response;
}

function getembed_youtube($settings,$videodata) {
	$error = false; $embed_code = ''; $retry = false;
	Zend_Loader::loadClass('Zend_Gdata_AuthSub');
	Zend_Loader::loadClass('Zend_Gdata_ClientLogin');
	Zend_Loader::loadClass('Zend_Gdata_YouTube');
	Zend_Loader::loadClass('Zend_Gdata_YouTube_VideoEntry');
	$authenticationURL= 'https://www.google.com/youtube/accounts/ClientLogin';
	$httpClient = Zend_Gdata_ClientLogin::getHttpClient(
	                                          $username = $settings['username'],
	                                          $password = $settings['password'],
	                                          $service = 'youtube',
	                                          $client = null,
	                                          $source = 'WebTV Wordpress Plugin', // identifying application
	                                          $loginToken = null,
	                                          $loginCaptcha = null,
	                                          $authenticationURL);
	                               
	$myDeveloperKey = $settings['devkey'];
	$httpClient->setHeaders('X-GData-Key', "key=${myDeveloperKey}");  

	$yt = new Zend_Gdata_YouTube($httpClient);
	$upload_status = '';
	
	$videoEntry = $yt->getVideoEntry($videodata['video_id'], null, true);
	
	try {
	  $control = $videoEntry->getControl();
	} catch (Zend_Gdata_App_Exception $e) {
	  	$error_desc[] = $e->getMessage();
		$error = true;
	}
	if ($control instanceof Zend_Gdata_App_Extension_Control) {
	  if ($control->getDraft() != null && $control->getDraft()->getText() == 'yes') {
	    $state = $videoEntry->getVideoState();
	    if ($state instanceof Zend_Gdata_YouTube_Extension_State) {
	      $upload_status = $state->getName();
	      $upload_status_desc = $state->getText();
	      if ($upload_status != 'processing') { $error = true; $retry = false; } else { $error = false; $retry = true; }
	    } else {
	      $upload_status_desc = _("Can't get video status from YouTube, try again later");
	      $retry = true;
	    }
	  }
	}
	
	//Si no es empty, el video esta procesado y publicado en YouTube
	if (empty($upload_status)) {
			$flv_source = $videoEntry->getFlashPlayerUrl();
			if (!empty($flv_source)) {
				$embed_code = '<object width="425" height="344"><param name="movie" value="'.$flv_source.
						'"></param><param name="allowFullScreen" value="true"></param>'.
		         		'<param name="allowscriptaccess" value="always"></param><embed src="'.$flv_source.
		         		'" type="application/x-shockwave-flash" allowscriptaccess="always"'.
		         		' allowfullscreen="true" width="425" height="344"></embed></object>';	
		      	$retry = false;
			}
			$video_url = $videoEntry->getVideoWatchPageUrl();
		    $duration = $videoEntry->getVideoDuration();
	} 

	
	//Preparar la respuesta
	$service_response = array();
	$service_response['service'] = 'youtube';
	$service_response['status'] = $upload_status;
	$service_response['status_description'] = $upload_status_desc;
	$service_response['error'] = $error;
	$service_response['error_description'] = $error_desc;
	$service_response['embed'] = $embed_code;
	$service_response['watch_url'] = $video_url;
	$service_response['retry'] = $retry;
	$service_response['video_id'] = $videodata['video_id'];
	//Optional data
	$service_response['flv_source'] = $flv_source;
	$service_response['duration'] = $duration;
	return $service_response;
}

//restore_include_path();
?>