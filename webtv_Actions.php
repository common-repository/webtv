<?php

/*
Author: Edgar de Le&oacute;n - edeleon
Author URI: http://www.webstratega.com/about/
Description: Lets you attach a video to a post and upload to most popular video distribution sites like YouTube, Vimeo and Blip.tv, after video is uploaded and processed get and inserts the embed code from the sites into custom fields on the post.  The plugin has de possibility to extend any other distribution site creating extra drivers
License: GPL (http://www.fsf.org/licensing/licenses/info/GPLv2.html) 
*/

function webtv_status_box() {
	if( function_exists( 'add_meta_box' )) {
		add_meta_box('webtv-status-info','WebTV Status','webtv_display_status','post','normal','high');
	}
}



function webtv_getembed_video() {
	$webtvembedqueue = (array)get_option( 'webtv_embed_queue' );
	if (empty($webtvembedqueue)) return;
	$reschedule = false;
	foreach ($webtvembedqueue as $service => $ids) {
		//Load Settings
		$settings = (array)get_option("webtv_".$service);
		foreach ($ids as $post_id) {
			//GetEmbedCode 
			$resp = webtv_getembed_code($post_id,$service,$settings);
			if ($resp) unset($ids[$post_id]);
		}
		$webtvembedqueue[$service] = $ids;
		if (!empty($ids)) $reschedule = true;
	} 
	update_option( 'webtv_embed_queue', $webtvembedqueue );
	//Si existen elementos en la cola de getEmbed programa el cron para dentro de 5 minutos
	if ($reschedule) {
		wp_clear_scheduled_hook( 'webtv_getembed' );
		wp_schedule_single_event(time()+300, 'webtv_getembed');
	}
}

function webtv_getembed_code($post_id,$service,$settings) {
	$poststatus = (array)get_post_meta($post_id, '_webtv_status', true);
	$uploadstatus = $poststatus[$service]['upload'];
	$embedstatus = $poststatus[$service]['embed'];
	$videodata = $poststatus[$service]['data'];
	if (function_exists('getembed_'.$service)) {
			$response = call_user_func('getembed_'.$service,$settings,$videodata);
			if ($response['retry'] == 0) {
    			$return = true; //sacarlo de la cola
    		} else {
    			$return = false; //intentar de nuevo publicar
    		}
			if(empty($response)) {
				$embedstatus['status'] = 'error';
				$embedstatus['status_msg'] = _("No response from $service");				
			}
			if($response['error']) {
				$embedstatus['status'] = 'error';
				$embedstatus['status_msg'] = $response['error_description'] . " " . $response['status_description'];
				$uploadstatus['status_msg'] = $response['error_description'] . " " . $response['status_description']; 
			} else {
				$embedstatus['status'] = 'queued';
				$embedstatus['status_msg'] = $response['error_description'] . " " . $response['status_description'];
			}
			if(!empty($response['embed'])) {
				$embedstatus['status'] = 'completed';
				$embedstatus['status_msg'] = _("check custom fields");
				$uploadstatus['status_msg'] = _("completed");
				//unset basic data
				unset($response['service']);
				unset($response['status']);
				unset($response['status_description']);
				unset($response['error']);
				unset($response['error_description']);
				unset($response['retry']);
				//Insert into custom fields
				foreach ($response as $customfield => $data) {
					add_post_meta($post_id,'webtv_'.$service.'_'.$customfield,$data,true);
					$videodata[$customfield] = $data;
				}
				//Autopublish post if enabled
				$postoptions = get_post_meta($post_id, '_webtv_post_options',true);
				$autopublish = false;
				if (!empty($postoptions)) {
					$globalopts = $postoptions['global'];
					if ((isset($globalopts['autopost'])) && ($globalopts['autopost'] == 'on'))
						$autopublish = true;
				}
				if (($autopublish) && (get_post_status($post_id) === 'draft')) {
					wp_publish_post($post_id);
				}
			}
			$poststatus[$service]['upload'] = $uploadstatus;
			$poststatus[$service]['embed'] = $embedstatus;
			$poststatus[$service]['data'] = $videodata;
			add_post_meta($post_id, '_webtv_status', $poststatus, true) or
    		update_post_meta($post_id, '_webtv_status', $poststatus);
			return $return;
	}
	return true; //Sacarlo de la cola	
}

function webtv_publish_video($entry,$service,$settings,$options) {
	$post_id = $entry['post_id'];
	$publish = false;
	if (isset($settings['enabled']) && ($settings['enabled'] == 1)) {
		$publish = true; 
	}
	if($publish) {
		unset($settings['enabled']);
		$poststatus = (array)get_post_meta($post_id, '_webtv_status', true);
		$driver = $poststatus[$service]['upload'];
		if ($driver['status'] == 'error') return true; //sacarlo de la cola
		$driver['count']++;
		if ($driver['count'] > $options['attemps']) {
			$driver['status'] = 'error';
			$driver['status_msg'] = sprintf(_('Impossible to upload video on %d attemps'),$options['attemps']);
			$poststatus[$service]['upload'] = $driver;
			add_post_meta($post_id, '_webtv_status', $poststatus, true) or
    		update_post_meta($post_id, '_webtv_status', $poststatus);
    		return true; //sacarlo de la cola
		}
		if (function_exists('upload_'.$service)) {
			$response = call_user_func('upload_'.$service,$settings,$entry);
			if ($response['retry'] == 0) {
    			$return = true; //sacarlo de la cola
    		} else {
    			$return = false; //intentar de nuevo publicar
    		}
			if(empty($response)) {
				$driver['status'] = 'error';
				$driver['status_msg'] = _("No response from $service");				
			}
			if($response['error']) {
				$driver['status'] = 'error';
				$driver['status_msg'] = $response['error_description'];
			}			
			if(!empty($response['video_id'])) {
				$driver['status'] = 'completed';
				$driver['status_msg'] = $response['status'] . " " . $response['status_description'];
				$poststatus[$service]['embed'] = array(	'status' => 'queued',
														'status_msg' => 'scheduled to get the embed code');
				//unset basic data
				unset($response['service']);
				unset($response['status']);
				unset($response['status_description']);
				unset($response['error']);
				unset($response['error_description']);
				unset($response['retry']);
				//Insert into custom fields
				foreach ($response as $customfield => $data) {
					add_post_meta($post_id,'webtv_'.$service.'_'.$customfield,$data,true);
					$poststatus[$service]['data'][$customfield] = $data;
				}
				//Insert into GetEmbed queue here if some upload gives a timeout, just to be sure
				$webtvembedqueue = (array)get_option( 'webtv_embed_queue' );
				if (empty($webtvembedqueue)) add_option('webtv_embed_queue', array());
				$post_ids = (array)$webtvembedqueue[$service];
				if( !array_key_exists( $post_id, $post_ids ) ) {
					$post_ids[ $post_id ] = $post_id;
					$webtvembedqueue[$service] = $post_ids;	
				}
				update_option( 'webtv_embed_queue', $webtvembedqueue );
			}
			$poststatus[$service]['upload'] = $driver;
			add_post_meta($post_id, '_webtv_status', $poststatus, true) or
    		update_post_meta($post_id, '_webtv_status', $poststatus);
    		return $return;
		}
	}
	$poststatus = (array)get_post_meta($post_id, '_webtv_status', true);
	$driver = $poststatus[$service]['upload'];
	$driver['status'] = 'error';
	$driver['status_msg'] = _("Please check webtv settings");
	$poststatus[$service]['upload'] = $driver;
	add_post_meta($post_id, '_webtv_status', $poststatus, true) or
    update_post_meta($post_id, '_webtv_status', $poststatus);
	return true;  //sacarlo de la cola  
}

function webtv_upload_video() {
	$webtvqueue = (array)get_option( 'webtv_upload_queue' );
	$reschedule = false;
	if (!empty($webtvqueue)) {
		$options = get_option("webtv");
		foreach ($webtvqueue as $service => $ids) {
			//Load Settings
			$settings = (array)get_option("webtv_".$service);
			foreach ($ids as $post_id) {
				//Get Post info
				$post = wp_get_single_post($post_id);
				$entry['post_id'] = $post_id;
				$entry['titulo'] = $post->post_title;
				$entry['desc'] = $post->post_content;
				$entry['tags'] = implode (',', $post->tags_input);
				//Get File info
				$fdetails = (array)get_post_meta($post_id,"_webtv_file_details",true);
				$entry['fileinfo'] = $fdetails['full_path'];
				$entry['content_type'] = $fdetails['content_type'];
				$entry['filesize'] = $fdetails['size'];
				//Upload file 
				$resp = webtv_publish_video($entry,$service,$settings,$options);
				if ($resp) unset($ids[$post_id]);  //descomentar al terminar pruebas
			}
			$webtvqueue[$service] = $ids;
			if (!empty($ids)) $reschedule = true;
		}
		if (isset($webtvqueue[0])) unset($webtvqueue[0]);
		update_option( 'webtv_upload_queue', $webtvqueue );
	}
	//Si existen elementos en la cola de subida programa el cron de subida para dentro de 5 minutos
	if ($reschedule) {
		wp_clear_scheduled_hook( 'webtv_upload' );
		wp_schedule_single_event(time()+300, 'webtv_upload');	
	}	
	//Se programa el cron para obtener el embed code de los videos
	if( !wp_next_scheduled( 'webtv_getembed' ) ) {
		wp_clear_scheduled_hook( 'webtv_getembed' );
		wp_schedule_single_event(time(), 'webtv_getembed');
	}
}

function webtv_display_status() {
	global $post_ID, $temp_ID;
	$post_id = (int) (0 == $post_ID ? $temp_ID : $post_ID);
	
	/*$url = get_settings('siteurl');
    $url =  'http://webtv.webstratega.com/wp-content/plugins/'.$plugin = dirname(plugin_basename(__FILE__)).'/includes/';
    $uploaddir = '/'.get_option( 'upload_path' );*/
    //$uploaddir = '/'.get_option( 'upload_path' ).'/webtv';  //A decidir si guardar dentro de subdir
	if ($post_id < 1) {
	$message = _("You need to Save Draft before upload a video");
	echo '<div id="draft">'.$message.'</div>';
	
	} else {
?>	
	<div id="uploaddiv" style="border: 1px solid rgb(127, 170, 255); padding: 2px; display: inline; background-color: rgb(197, 217, 255);">
	<?php
	$status = get_post_meta($post_id,"_webtv_upload_status",true);
	if ((!empty($status) && $status == 'error') || (empty($status))) {
	?>
	<span id="spanButtonPlaceHolder"><a href="#webtv-status-info" onclick="load_swfupload();" style="font-family: Helvetica, Arial, sans-serif; font-size: 10pt;">Upload Video</a></span>
	<?php
	} else { 
		echo _("Video Uploaded");
	}
	?>
	</div>
	<br/>
	<span>
	<?php
		$postoptions = get_post_meta($post_id, '_webtv_post_options',true);
		$checked = 'checked="checked"';
		if (!empty($postoptions)) {
			$globalopts = $postoptions['global'];
			if ((isset($globalopts['autopost'])) && ($globalopts['autopost'] == 'off'))
				$checked = '';
		}
	?>
	<br/><input type="checkbox" name="autopost" id="autopost" <?=$checked?> /> <?php echo _("Automatically publish post after one successful upload?") ?>
	</span>
	<br/><br/>
	<span><b><?php echo _("File Details")?>:</b></span><br/>
	<div  id="webtv-status-upload"></div>
	<!--<div style="border: 1px solid rgb(127, 170, 255); padding: 2px; display: inline; background-color: rgb(197, 217, 255);">
	<a href="#" id="btnCancel" onclick="swfu.cancelQueue();" disabled="disabled" >Cancel</a>
	</div>
	-->				
	<div id="eleList">
		<?php
		
		
		if (!empty($status)) {
			switch ($status) {
				case "error": 	$msg = get_post_meta($post_id,"_webtv_upload_status_msg",true);
								$status_color = "#FF0000;";
								break;	
				case "stored":
				case "uploaded":$fdetails = get_post_meta($post_id,"_webtv_file_details",true);
								$msg = $fdetails['local_file'].' '.$fdetails['content_type'].' '.file_size($fdetails['size']);
								$status_color = "#009015;";
								break;
			}
			echo '<span style="color: #6F6F6F;"> '.$msg.' </span><span style="color: '.$status_color.'">'.$status.'</span><br/>';
		}
		$poststatus = get_post_meta($post_id, '_webtv_post_status',true);
		if (!empty($poststatus)) {
			$status_color = "#FF0000;";
			echo '<span style="color: #6F6F6F;"> '.$poststatus.'</span><span style="color: '.$status_color.'">error </span><br/>';
		}
		/*if ($programado = wp_next_scheduled( 'webtv_upload' )) { 
			$webtvqueue = get_option( 'webtv_upload_queue' );
			?>
			<span style="color: #009015;"><?php echo _("scheduled ");?></span><span style="color: #6F6F6F;"><?php echo _("Next upload: "); echo date('d-m-Y h:i',$programado); ?></span>
			<?php
			$services = array();
			if (!empty($webtvqueue)) {
			foreach ($webtvqueue as $service => $ids) {
				if (array_key_exists($post_id, $ids)) {
					$services[] = $service;
				}	
			}
			}
			?><span style="color: #6F6F6F;"><?php if (!empty($services)) { echo _("to "); echo implode(",",$services); } ?></span><br/><?php
		}*/
		$poststatus = get_post_meta($post_id, '_webtv_status', true);
		if (!empty($poststatus)) {
			foreach ($poststatus as $driver => $type) {
				?><br/>
				<span><b><?php echo ucfirst($driver) . " Status:"?></b></span><br/><?
				foreach ($type as $process => $data) {
					if ($process != 'data') {						
						?>
						<span><?=ucfirst($process)?></span>
						
						<span style="color: #6F6F6F;"><?=$data['status_msg']?>
						<?php
						$queuename = ($process == 'upload') ? "webtv_upload" : "webtv_getembed";
						if (($data['status'] == 'queued') && (wp_next_scheduled( $queuename )) ) {
							echo _(" at ");
							echo date('d-m-Y H:i',wp_next_scheduled( $queuename ));	
						} 
						echo "</span>";
						$style_color = ($data['status'] == 'error') ? "#FF0000" : "#009015"; ?>
						<span style="color: <?=$style_color?>;"><?=$data['status']?></span>
						<?php 
						echo "<br/>";
					} else {
						?>
						<!--<span style="color: #6F6F6F;">Info</span><br/>-->
						<span>VideoID</span>
						<?php
						if (isset($data['video_id'])) {
							echo '<span style="color: #6F6F6F;">';
							if (isset($data['watch_url'])) { 	
							?>
								<a href="<?=$data['watch_url']?>" target="_blank"><?=$data['video_id']?></a>	
							<?php		
							} else {
								echo $data['video_id'];
							}
							echo '</span><br/>';
						}
						echo '<span>CustomFields:</span><br/>';
						foreach ($data as $customfield => $value) {
							$message = sprintf(_("at %s custom field"),"webtv_".$driver."_".$customfield);
							echo '<span style="color: #6F6F6F;">'.$customfield.' </span><span style="color: #6F6F6F;">'.$message.'</span><span style="color: #009015;"> done</span><br/>';
						}	
					}
				}
			}
		}
		?>
		
	</div>
	<?php $debug = false;
	if ($debug) {
	?>
	<div id="debug">
		<p>Debug</p>
		<?php
			print "post_id ".$post_id;
			echo "<BR/>";
			$options = get_option("webtv");
			foreach ($options['services'] as $driver) {
				print_r($driver);
				echo "<BR/>";
				$settings = get_option("webtv_".$driver);
				print_r($settings);
				echo "<BR/>";
				if ($settings['enabled']) {
					print "enabled";
					echo "<BR/>";
				}
			}
			echo "<BR/>Upload Queue<BR/>";
			$webtvqueue = get_option( 'webtv_upload_queue' );
			$webtvembedqueue = get_option( 'webtv_embed_queue' );
			//update_option( 'webtv_upload_queue',array());
			//update_option( 'webtv_embed_queue',array());
			//if (isset($webtvqueue[0])) unset($webtvqueue[0]);
			//delete_post_meta($post_id, '_webtv_status');
			print_r($webtvqueue);
			echo "<BR/>Getembed Queue<BR/>";
			print_r($webtvembedqueue);
			echo "<BR/>";
			/*$settings = get_option("webtv_youtube");
			print_r($settings);*/
			echo "<BR/>";
			print "next upload cron ";
			print date('Ymd h i',wp_next_scheduled( 'webtv_upload' ));
			print date('Ymd h i',wp_next_scheduled( 'webtv_getembed' ));
			echo "<BR/>";
			print "Post Status <BR/> ";
			print_r($poststatus = (array)get_post_meta($post_id, '_webtv_status', true));
		?>
	</div>
	<?php } ?>

<?php
	}
}

function webtv_save_and_schedule() {
	global $post_ID, $temp_ID;
	$post_id = (int) (0 == $post_ID ? $temp_ID : $post_ID);    
	//Check post options 
	$autopost = 'off';
	if (isset($_POST['autopost']))
		$autopost = 'on';
	$postopts['global'] = array('autopost' => $autopost);	
	add_post_meta($post_id, '_webtv_post_options', $postopts, true) or
	update_post_meta($post_id, '_webtv_post_options', $postopts);
	//The _webtv_file_details and _webtv_upload_status custom fields are created on upload.php file using post data from swfupload
	$status = get_post_meta($post_id,"_webtv_upload_status",true);	
	if ($status === "uploaded") {
		//Get Post info
		$post = wp_get_single_post($post_id);
		if ( $post == false || $post == null ) {
	            return false;
	    }
	    $error_msg = "";
		//print_r($post);
		if (($post->post_type) != 'post') {
			$error_msg .= _("This is not a post ");
			$error = true;
		}
		if (empty($post->post_title)) {
			$error_msg .= _(" Must have a post title ");
			$error = true;
		}
		if (empty($post->post_content)) {
			$error_msg .= _(" Must have post content ");
			$error = true;
		}
		if (empty($post->tags_input)) {
			$error_msg .= _(" Must have at least one tag ");
			$error = true;
		}
		if ($error) {
			add_post_meta($post_id, '_webtv_post_status', $error_msg, true) or
	    	update_post_meta($post_id, '_webtv_post_status', $error_msg);
			return false;
		}
		delete_post_meta($post_id, '_webtv_post_status');
		$options = get_option("webtv");
		$webtvqueue = (array)get_option( 'webtv_upload_queue' );
		foreach ($options['services'] as $driver) {
			$settings = get_option("webtv_".$driver);
			if ($settings['enabled']) {
				$poststatus[$driver]['upload'] = array(	'count' => 0,
														'status' => 'queued',
														'status_msg' => 'scheduled to upload');
				add_post_meta($post_id, '_webtv_status', $poststatus, true) or
    			update_post_meta($post_id, '_webtv_status', $poststatus);
    			$post_ids = (array)$webtvqueue[$driver];
				if( !array_key_exists( $post_id, $post_ids ) ) {
					$post_ids[ $post_id ] = $post_id;
					$webtvqueue[$driver] = $post_ids;	
				}	
			}
		}
		if (isset($webtvqueue[0])) unset($webtvqueue[0]);
		update_option( 'webtv_upload_queue', $webtvqueue );
		add_post_meta($post_id, '_webtv_upload_status', 'stored', true) or
		update_post_meta($post_id, '_webtv_upload_status', 'stored');
	}  //$status === "uploaded"
	//Iniciar el cron job
	if( !wp_next_scheduled( 'webtv_upload' ) ) {
		wp_clear_scheduled_hook( 'webtv_upload' );
		wp_schedule_single_event(time(), 'webtv_upload');
	}
}

function file_size($size)
{
    $filesizename = array("Bytes", "KB", "MB", "GB", "TB", "PB", "EB", "ZB", "YB");
    return $size ? round($size/pow(1024, ($i = floor(log($size, 1024)))), 2) . $filesizename[$i] : '0Bytes';
}

function webtv_content_type($filename) {
	$pathinfo = pathinfo($filename);
	$ext = strtolower($pathinfo['extension']);
	
	$valid_video_format['mpeg'] = 'video/mpeg';
	$valid_video_format['mpg'] = 'video/mpeg';
	$valid_video_format['mpe'] = 'video/mpeg';
	$valid_video_format['qt'] = 'video/quicktime';
	$valid_video_format['mov'] = 'video/quicktime';
	$valid_video_format['avi'] = 'video/x-msvideo';
	$valid_video_format['wmv'] = 'video/x-ms-wmv';
	$valid_video_format['3gp'] = 'video/3gpp';
	$valid_video_format['m4v'] = 'video/x-m4v';
	$valid_video_format['flv'] = 'video/x-flv';
	$valid_video_format['mp4'] = 'video/mp4';
	$valid_video_format['f4v'] = 'video/mp4';
	
	return $valid_video_format[$ext];

}

/*  Template Tags  */
function webtv_embedcode() {
	global $post;
	if (!isset($post->ID) || $post->ID == 0 || $post->ID == "") {
		return;
	} else {
		$post_id = $post->ID;		
	}
	$options = get_option("webtv"); 
	$embed = '';
	$orderlist = $options['order']; 
	foreach ($orderlist as $order => $service) {
	  $embed = get_post_meta($post_id,'webtv_'.$service.'_embed',true);
	  if (!empty($embed)) {
		echo $embed;
		return;
	  }
	}
	echo '';
	return;
}


?>