<?php

function vimeo_settings() {
	$service_config = Array(
		'service' 	=> "vimeo",      //File with actions must be named like $service
		'service_url' => "vimeo.com",
		'username' 	=> "email@domain.com",
		'password' 	=> "passwd",
		'devkey' 	=> "api_key",
		'api_secret' => "api_secret",
		'enabled' 	=> false
	);
	return $service_config;
}

function vimeo_extras() {
	$extras = array('privacy'=> array(	'anybody' => 'Anybody can view the video',
										'contacts' => 'Only the the uploaders contacts can view the video',
										'nobody' => 'Only the uploader can view the video'
										)
					);
	return $extras;
}

function upload_vimeo($settings,$entry) {
	$error = false; $retry = 0;
	$upload_status = '';
	$mail = $settings['username'];
	$pass = $settings['password'];
	$api_key = $settings['devkey'];
	$api_secret = $settings['api_secret'];
	$permisos = $settings['privacy'];

	$file = $entry['fileinfo'];
	@set_time_limit(0);
	$title	  = htmlentities($entry['titulo'],	ENT_NOQUOTES, 'UTF-8');
	$descripcion	= htmlentities($entry['desc'],	ENT_NOQUOTES, 'UTF-8');
	$tags = htmlspecialchars(stripslashes($entry['tags']));
	$cookie		 = tempnam (ABSPATH . get_option('upload_path') . '/', "VIMEO");
	$api_login_url  = "http://www.vimeo.com/log_in";
	$api_rest_url   = "http://www.vimeo.com/api/rest/";
	$api_auth_url   = "http://www.vimeo.com/services/auth/";
	$api_upload_url = "http://www.vimeo.com/services/upload/";
	
	if($file){
		#login
		$args = array(
			'sign_in[email]'	=> $mail,
			'sign_in[password]' => $pass
		);
		$login = source_extract($api_login_url, $args, '', '', true, $cookie);
		#frob
		$args = $_args = array(
			'api_key'   => $api_key,
			'perms'  => 'write',
			'format'	=> 'php'
		);
		$s=$api_secret; foreach($args as $k=>$v){   $s .= $k.$v;   }  $args['api_sig'] = md5($s);
		$frob = source_extract($api_auth_url, '', $args, '', true, $cookie);
		if(!$frob['header']['Location']){ //si aun no fue aceptada la autorizacion
			$frob = source_extract($api_auth_url, $_args, '', '', true, $cookie);   #lo autorizamos por POST
			$frob = source_extract($api_auth_url, '', $args, '', true, $cookie);	#y pedimos el frob de nuevo
		}
		$frob = substr($frob['header']['Location'], strpos($frob['header']['Location'], 'frob=')+strlen('frob=') );
		$frobge = $frob;
		#token
		$args = array(
			'api_key'   => $api_key,
			'format'	=> 'php',
			'frob'	=> $frob,
			'method'	=> 'vimeo.auth.getToken'
		);
		$s=$api_secret; foreach($args as $k=>$v){   $s .= $k.$v;   }  $args['api_sig'] = md5($s);
		$token = source_extract($api_rest_url, '', $args, '', true, $cookie);
		$token = unserialize($token['content']);
		$token = $token->auth->token;
		#tiket
		$args = array(
			'api_key'   => $api_key,
			'format'	=> 'php',
			'frob'	=> $frob,
			'method'	=> 'vimeo.videos.getUploadTicket'
		);
		$s=$api_secret; foreach($args as $k=>$v){   $s .= $k.$v;   }  $args['api_sig'] = md5($s);
		$tiket = source_extract($api_rest_url, '', $args, '', true, $cookie);
		$tiket = unserialize($tiket['content']);
		$tiket = $tiket->ticket->id;
		#upload video
		$file = array('video'=>$file);
		$args = array(
			'api_key'   => $api_key,
			'format'		=> 'php',
			'auth_token'	=> $token,
			'ticket_id'  => $tiket
		);
		$s=$api_secret; foreach($args as $k=>$v){   $s .= $k.$v;   }  $args['api_sig'] = md5($s);
		$upload = source_extract($api_upload_url, $args, '', $file, true, $cookie);
		#check
		$args = array(
			'api_key'   => $api_key,
			'format'	=> 'php',
			'frob'	=> $frob,
			'method'	=> 'vimeo.videos.checkUploadStatus',
			'ticket_id' => $tiket
		);
		$s=$api_secret; foreach($args as $k=>$v){   $s .= $k.$v;   }  $args['api_sig'] = md5($s);
		$check = source_extract($api_rest_url, '', $args, '', true, $cookie);
		$check = unserialize($check['content']);
		#title
		$args = array(
			'api_key'   => $api_key,
			'format'	=> 'php',
			'method'	=> 'vimeo.videos.setTitle',
			'title'  => $title,
			'video_id'  => $check->ticket->video_id,
		);
		$s=$api_secret; foreach($args as $k=>$v){   $s .= $k.$v;   }  $args['api_sig'] = md5($s);
		$title = source_extract($api_rest_url, '', $args, '', true, $cookie);
		$title = unserialize($title['content']);
		#caption
		$args = array(
			'api_key'   => $api_key,
			'caption'   => $descripcion,
			'format'	=> 'php',
			'method'	=> 'vimeo.videos.setCaption',
			'video_id'  => $check->ticket->video_id,
		);
		$s=$api_secret; foreach($args as $k=>$v){   $s .= $k.$v;   }  $args['api_sig'] = md5($s);
		$caption = source_extract($api_rest_url, '', $args, '', true, $cookie);
		$caption = unserialize($caption['content']);
		#tags
		$args = array(
			'api_key'   => $api_key,
			'format'	=> 'php',
			'method'	=> 'vimeo.videos.addTags',
			'tags'	=> $tags,
			'video_id'  => $check->ticket->video_id,
		);
		$s=$api_secret; foreach($args as $k=>$v){   $s .= $k.$v;   }  $args['api_sig'] = md5($s);
		$tags = source_extract($api_rest_url, '', $args, '', true, $cookie);
		$tags = unserialize($tags['content']);
		#privacidad
		if($permisos){
			$permisos = strtolower($permisos);
			if( $permisos!='anybody' && $permisos!='nobody' && $permisos!='contacts' && !strpos($permisos, ',')){
				$error = true;
				$error_desc = _('ERROR: Los permisos permitidos son "nobody" para que sea privado, "anybody",'.
						' para que sea publico, "contacts" para que solo lo puedan ver los contactos del'.
						' usuario registrado o una lista de usuarios separada por comas para que solo esos'.
						' usuarios puedan ver el video. (la funcion requiere que si se usa esta ultima'.
						' opci—n se ponga al menos una coma final.'); 
				$retry = 0;
			}
			if(strpos($permisos, ',')) {
				$args = array(
					'api_key'   => $api_key,
					'format'	=> 'php',
					'method'	=> 'vimeo.videos.setPrivacy',
					'privacy'   => 'users',
					'users'  => (substr($permisos, -1)==',') ? substr($permisos, 0, -1) : $permisos,
					'video_id'  => $check->ticket->video_id,
				);
			} else {
				$args = array(
					'api_key'   => $api_key,
					'format'	=> 'php',
					'method'	=> 'vimeo.videos.setPrivacy',
					'privacy'   => $permisos,
					'video_id'  => $check->ticket->video_id,
				);
			}
			$s=$api_secret; foreach($args as $k=>$v){   $s .= $k.$v;   }  $args['api_sig'] = md5($s);
			$permisos = source_extract($api_rest_url, '', $args, '', true, $cookie);
			$permisos = unserialize($permisos['content']);
		}
		
		if ($check->ticket->video_id) {
		    $videoid = $check->ticket->video_id;
		    $upload_status_desc = 'processing';	    
		    $retry = false;
		 } else {
		  	$upload_status = 'error';
		  	$error = true;
		  	$upload_status_desc = "Error";
		  	$retry = true;
		 }
		
		//Preparar la respuesta
		$service_response = array();
		$service_response['service'] = 'vimeo';
		$service_response['video_id'] = $videoid;
		$service_response['status'] = $upload_status;
		$service_response['status_description'] = $upload_status_desc;
		$service_response['error'] = $error;
		$service_response['error_description'] = $error_desc;
		$service_response['retry'] = $retry;
		//Sending the ticket
		$service_response['ticket'] = $tiket;
		$service_response['cookie'] = $cookie;

		return $service_response;
		
		/*return array(
			'login'  => $login,
			'frob'	=> $frobge,
			'token'  => $token,
			'tiket'  => $tiket,
			'cookie' => $cookie,
			'upload'	=> $upload,
			'check'  => $check,
			'title'  => $title,
			'caption'   => $caption,
			'tags'	=> $tags,
			'privacity' => $permisos,
			'video_id' => $check->ticket->video_id
		);*/
	}

}

function getembed_vimeo($settings,$videodata) {
	$error = false; $embed_code = ''; $retry = true;
	$api_rest_url   = "http://www.vimeo.com/api/rest/";
	$api_auth_url   = "http://www.vimeo.com/services/auth/";
	$frob = false;
	$api_key = $settings['devkey'];
	$api_secret = $settings['api_secret']; 
	$tiket = $videodata['ticket'];
	$cookie = $videodata['cookie'];
	$video_id = $videodata['video_id'];
	#check
	$args = array(
		'api_key'   => $api_key,
		'format'	=> 'php',
		'frob'	=> $frob,
		'method'	=> 'vimeo.videos.checkUploadStatus',
		'ticket_id' => $tiket
	);
	$s=$api_secret; foreach($args as $k=>$v){   $s .= $k.$v;   }  $args['api_sig'] = md5($s);
	$check = source_extract($api_rest_url, '', $args, '', true, $cookie);
	$check = unserialize($check['content']);
	
	if (isset($check->ticket->transcoding_progress) && ($check->ticket->transcoding_progress == "100")) {
		$args_info = array(
			'api_key'   => $api_key,
			'format'	=> 'php',
			'frob'	=> $frob,
			'method'	=> 'vimeo.videos.getInfo',		
			'video_id' => $video_id
		);
		//$video_id = $check->ticket->video_id;
		$s=$api_secret; foreach($args_info as $k=>$v){   $s .= $k.$v;   }  $args_info['api_sig'] = md5($s);
		$check = source_extract($api_rest_url, '', $args_info, '', true, $cookie);
		$check = unserialize($check['content']);	
		$retry = 0;
		$error = false;
		$upload_status = 'completed';
		$upload_status_desc = _('completed');
		$embed_code = '<object width="640" height="390"><param name="allowfullscreen" value="true" /><param name="allowscriptaccess" value="always" /><param name="movie" value="http://vimeo.com/moogaloop.swf?clip_id='.$video_id.'&amp;server=vimeo.com&amp;show_title=1&amp;show_byline=1&amp;show_portrait=0&amp;color=&amp;fullscreen=1" /><embed src="http://vimeo.com/moogaloop.swf?clip_id='.$video_id.'&amp;server=vimeo.com&amp;show_title=1&amp;show_byline=1&amp;show_portrait=0&amp;color=&amp;fullscreen=1" type="application/x-shockwave-flash" allowfullscreen="true" allowscriptaccess="always" width="640" height="390"></embed></object>';
		$video_url = $check->video->urls->url->_content;
		
	} else {
		$retry = 1;
		$error = false;
		$upload_status = 'processing';
		$upload_status_desc = _('transcoding video');
	
	}
	
	//Preparar la respuesta
	$service_response = array();
	$service_response['service'] = 'vimeo';
	$service_response['status'] = $upload_status;
	$service_response['status_description'] = $upload_status_desc;
	$service_response['error'] = $error;
	$service_response['error_description'] = $error_desc;
	$service_response['embed'] = $embed_code;
	$service_response['watch_url'] = $video_url;
	$service_response['retry'] = $retry;
	$service_response['video_id'] = $video_id;
	//Optional data
	if (!$retry) {
		$service_response['flv_source'] = 'http://vimeo.com/moogaloop.swf?clip_id='.$video_id;
		$service_response['views'] = $check->video->number_of_plays;
		$service_response['duration'] = $check->video->duration;
		$service_response['thumbnail'] = $check->video->thumbnails->thumbnail[2]->_content;
	}
	return $service_response;
}	


//Include source_extract library from tierra0.com 
//http://www.tierra0.com/2009/03/27/source_extract-version2-envio-y-extraccion-de-datos-desde-php-como-si-fuera-un-navegador/
function source_extract(
		$url,
		$POSTdata='',
		$GETdata='',
		$file_upload='',
		$header=false,
		$cookie=false
	){
	$user_agent = 'Mozilla/5.0 (Windows; U; Windows NT 5.1; ru; rv:1.8.0.9) Gecko/20061206 Firefox/1.5.0.9';
	#get
	if(is_array($GETdata)){
		foreach($GETdata as $k => $v){  $GETdata[$k] = $k.'='.urlencode($v);  }
		$GETdata = implode('&', $GETdata);
	}
	if($GETdata!='') $url .= strpos($url, '?') ? '&'.$GETdata : '?'.$GETdata;
	#post+files
	if(is_array($POSTdata)&&!is_array($file_upload)){
		foreach($POSTdata as $k => $v){ $POSTdata[$k] = $k.'='.$v;   }
		$POSTdata = implode('&', $POSTdata);
	} elseif(count($POSTdata)==0){
		$POSTdata = array();
	}
	if(is_array($file_upload)){
		foreach($file_upload as $k=>$v){
			$v		  = (substr($v,0,1)!='.'&&substr($v,0,1)!='/') ? './'.$v : ''.$v;
			$path_v	= realpath(dirname($v));
			$file_v	= basename($v);
			$file_upload[$k]	= '@'.$path_v.(substr($path_v,-1)!='/' ? '/' : '').$file_v;
		}
		$file_upload = array_merge($POSTdata, $file_upload);
	}
	$ch = curl_init($url);
	if(is_array($file_upload)|| $POSTdata!=''){
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, (is_array($file_upload) ? $file_upload : $POSTdata));
	}
	if($cookie){
		curl_setopt ($ch, CURLOPT_COOKIEJAR, $cookie);
		curl_setopt ($ch, CURLOPT_COOKIEFILE, $cookie);
	}
	if($header){
		curl_setopt ($ch, CURLOPT_HEADER, 1);
	}
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, (substr($url,0,5)=='https'));
	curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
	$_url_content = $url_content = curl_exec ($ch);
	curl_close($ch);
		$url_content = array(
			'url'	  =>$url,
			'post'	=>$file_upload ? $file_upload : $POSTdata,
			'content'   =>$_url_content
		);
	if($header){
		$_url_content = explode("\r\n\r\n", $_url_content,3);
		$url_content['header']  = (count($_url_content)>2) ? $_url_content[0]."\r\n".$_url_content[1] : $_url_content[0];
		$url_content['header']  = explode("\r\n", $url_content['header']);
		foreach($url_content['header'] as $k=>$v){  $o[$k] = explode(': ', $v,2); $o[$o[$k][0]] = $o[$k][1]; unset($o[$k]);   }
		$url_content['header'] = $o;
		$url_content['content'] = (count($_url_content)>2) ? $_url_content[2] : $_url_content[1];
	}
	return $url_content;
}

?>