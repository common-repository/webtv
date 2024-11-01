<?php

/*
Author: Edgar de Le&oacute;n - edeleon
Author URI: http://www.webstratega.com/about/
Description: Lets you attach a video to a post and upload to most popular video distribution sites like YouTube, Vimeo and Blip.tv, after video is uploaded and processed get and inserts the embed code from the sites into custom fields on the post.  The plugin has de possibility to extend any other distribution site creating extra drivers
License: GPL (http://www.fsf.org/licensing/licenses/info/GPLv2.html) 
*/

class Webtv_Admin {
	
	function Webtv_Admin() {
		register_activation_hook(dirname(__FILE__) . '/webtv.php', array(&$this, 'activate_me'));
	}
	
	public function activate_me() {
		foreach (glob(dirname (__FILE__).'/Drivers/*.php') as $file) { 
        	include_once $file;
        	$path_parts = pathinfo($file);
        	$service_name = $path_parts['filename'];
        	if (function_exists($service_name.'_settings')) {
        		$service_config = call_user_func($service_name.'_settings');
	        	if (isset($service_config)) {  
	        		$provider = $service_config['service'];      		
					if ( $options = get_option("webtv") ) {
						if (!(isset($options['services'][$provider]))) {
							$options['services'][$provider] = $provider;
							$options['order'][] = $provider;
					    	update_option("webtv", $options);
					    }
					} else {
					    $options = array();
						$options['attemps'] = 3;
						$options['services'][$provider] = $provider;
						$options['order'][] = $provider;
						add_option("webtv",$options);
					}
					
					$settings = "webtv_".$service_config['service'];
					$service_name = $service_config['service'];
					unset($service_config['service']);
					if ( !get_option($settings) ) {
					    $options = array();
					    foreach ($service_config as $option => $value) {
					    	$options[$option] = $value;
					    }
					    add_option($settings,$options);
					}    
				unset($service_config);	
	        	}
	        }
        	if (function_exists($service_name.'_extras')) {
        		$extras = call_user_func($service_name.'_extras');
        		if ( get_option($service_name.'_extras') ) {
				    update_option('webtv_'.$service_name.'_extras', $extras);
				} else {
				    add_option('webtv_'.$service_name.'_extras', $extras);
				}
        	} 
   		}
	} //Activate_me
	
	function add_config_page() {
		if ( function_exists('add_submenu_page') ) {
			add_options_page('WebTV for WordPress Configuration', 'WebTV', 8, basename(__FILE__), array(&$this,'config_page'));
			add_filter( 'plugin_action_links', array( &$this, 'filter_plugin_actions'), 10, 2 );						}
	}

	function filter_plugin_actions( $links, $file ){
		//Static so we don't call plugin_basename on every plugin row.
		static $this_plugin;
		if ( ! $this_plugin ) $this_plugin = plugin_basename(__FILE__);

		if ( dirname($file) == dirname($this_plugin) ){
			$settings_link = '<a href="options-general.php?page=webtv_Admin.php">' . __('Settings') . '</a>';
			array_unshift( $links, $settings_link ); // before other links
		}
		return $links;
	}
	
	function config_page() {
		if (!current_user_can('manage_options')) die(__('You cannot edit the WebTV options.'));
		$options = get_option("webtv");
		//print_r($options);
		
		if (!is_array($options)) {
			$options = array();
			$options['attemps'] = 3;
			add_option("webtv",$options);
		}
		if (!is_array($options['order'])) {
			foreach ($options['services'] as $service) {
				$options['order'][] = $service; 
			}
			update_option("webtv",$options);
		}
		
		if ( isset($_POST['submit']) ) {
			check_admin_referer('webtv-config');
			//print_r($_POST);
			if (isset($_POST['attemps']) && is_numeric($_POST['attemps'])) {
				$options['attemps'] = $_POST['attemps'];
				if ($options['attemps'] < 1) {
					$options['attemps'] = 3;
				}
				if ($options['attemps'] > 6) {
					$options['attemps'] = 6;
				}
			}
			if (isset($_POST['attemps']) && $_POST['attemps'] == "") {
				$options['attemps'] = 3;
			}
			if (isset($_POST['orderedlist'])) {	
				$service_order = split("\|", $_POST['orderedlist']);
				$options['order'] = $service_order;
			}
			update_option("webtv",$options);
			$services = $_POST['service'];
			foreach ($services as $service_name => $service) {
				$service_settings = get_option("webtv_".$service_name);
				$enabled = false;
				if ($service['enabled'] == 'true') {
					$enabled = true;
				}
				unset($service['enabled']);
				foreach ($service as $option => $value) {
					if ($value == "") $enabled = false;
					$service_settings[$option] = $value;
				}
				$service_settings['enabled'] = $enabled;
				update_option("webtv_".$service_name,$service_settings);
				
			}
			
		}
		if ( isset($_POST['cleanupsubmit']) ) {
			check_admin_referer('webtv-cleanup');
			global $wpdb, $table_prefix;
			
			$query = 'DELETE FROM '.$table_prefix.'options WHERE option_name like "webtv%"'; 
			$wpdb->query($query);
			
			if (isset($_POST['cleanup'])) {
				
				$query = 'DELETE FROM '.$table_prefix.'postmeta WHERE meta_key like "webtv%"'; 
				$wpdb->query($query);
				echo "<div id=\"message\" class=\"updated fade\"><p>WebTV ha eliminado toda la informaci&oacute;n.</p></div>\n";
			}
			echo "<div id=\"message\" class=\"updated fade\"><p>WebTV ha sido desinstalado.</p></div>\n";
		}
		?>
			<div class="wrap">
				<h2>WebTV Configuration</h2>
				<form action="<?php $PHP_SELF ?>" method="post" id="webtv-conf">
					<table class="form-table" style="width:100%;">
					<?php
					if ( function_exists('wp_nonce_field') )
						wp_nonce_field('webtv-config');
					?>
						<tr>
							<th>
								<label for="attemps"><strong><?=_("How many times do you want to try to publish the video")?>:</strong></label><br/>
								<small><?=_("Define how many times do you want to try to publish a file to each service configured below.  Default value is 3 times and the maximum number you can use is 6.")?></small>
							</th>
							<td valign="top"><input style="width:60px;" type="text" name="attemps" value="<?php if (isset($options['attemps'])) { echo $options['attemps']; } ?>" id="attemps"/>
							</td>
						</tr>
						
<!-- Service Settings  -->
						<?php foreach ($options['services'] as $service) { 
								$settings = get_option("webtv_".$service);
								if (isset($settings['service_url'])) {
									$service_name = $settings['service_url'];
									unset($settings['service_url']);
								}
						?>
						<tr>
							<th>
								<label for="<?=$service_name?>"><strong><?=_("Publish to")?> <?=$service_name?>:</strong></label><br/>
								<small><?=_("Insert your account settings, all the fields are required.")?></small>
							</th>
							<td valign="top">
							<select name="service[<?=$service?>][enabled]">
								<?php
								$yes = "selected"; 
								if (!$settings['enabled']) {
									$yes = ""; $no = "selected"; 
								}
								unset($settings['enabled']);
								?>
     							<option <?=$no?>  label="no" value="false">No</option>
     							<option <?=$yes?> label="yes" value="true">Yes</option>
     						</select><br/>
							<?php 
							$extras = get_option("webtv_".$service."_extras");
							if ($extras) {
								foreach ($extras as $type => $extraoptions) { ?>
								<select name="service[<?=$service?>][<?=$type?>]">	
									<?php foreach($extraoptions as $idextra => $valueextra) {
											if ((isset($settings[$type])) && ($idextra == $settings[$type])) { $selected = "selected"; } else $selected = ""; ?>
											<option <?=$selected?> label="<?=$valueextra?>" value="<?=$idextra?>"><?=$valueextra?></option>
									<?php } ?>								
								</select> 
								<?php
								echo $type.'<BR/>';
								unset($settings[$type]);
								}
							}
							foreach ($settings as $option => $value) { 
							$type = "text"; if ($option == "password") { $type = "password"; }
							?>
							<input style="width:100px;" type="<?=$type?>" name="service[<?=$service?>][<?=$option?>]" value="<?=$value?>" id="<?=$option?>_<?=$option?>"/><span class="setting-description"><?=$option?></span><br/>
							<?php } ?>	
							</td>
						 </tr>
						 <?php } ?>
<!-- End Service Settings  -->
					
						<tr>
							<th>
								<label for="attemps"><strong><?=_("Priority on TemplateTag")?>:</strong></label><br/>
								<small><?=_("If you want to use the webtv_embedcode() template tag into your template, you need to define the order to display the embed code from the different video providers.")?></small>
							</th>
							<td valign="top">
								
									<table>
									<tr>
									<td align="middle">
									<input type="hidden" name="orderedlist" id="orderedlist" value="" />
									<?php $j = count($options['services']); ?>
									<select name="list" id="list" size="<?=$j?>" style="height:<?php echo ($j*2)?>em">
									<?php 
									foreach ($options['order'] as $order => $service) {
									?>
									<option value="<?=$order?>"><?=$service?></option>
									<?php } ?>
									</select><br><br>
									
									</td>
									
									<td valign="top">
									<input type="button" value="↑"
									onClick="move(document.getElementById('list').selectedIndex,-1)"><br><br>
									<input type="button" value="↓"
									onClick="move(document.getElementById('list').selectedIndex,+1)">
									</td>
									</tr>
									</table>
								
							
							</td>
						</tr>
				






					</table>
					<p style="border:0;" class="submit"><input type="submit" name="submit" value="<?=_("Save")?>" onClick="submitForm()" /></p>					
				</form>

				<h2><?=_("Uninstall")?></h2>
				<form action="" method="post" id="webtv-cleanup">
					<?php if ( function_exists('wp_nonce_field') )
						wp_nonce_field('webtv-cleanup');  ?>
					
					<table class="form-table" style="width:100%;">
					<tr>
						<th scope="row" style="width:400px;" valign="top">
							<p><label for="uninstall"><?=_("Without leaving any data? Use this option to delete all the data stored on Custom Fields")?></label></p>
						</th>
						<td>
							<input type="checkbox" name="cleanup" id="cleanup"/><?=_("Clean")?><br/>
						</td>
					</tr>							
					</table>
					<p style="border:0;" class="submit"><input type="submit" name="cleanupsubmit" value="<?=_("Clean Up!")?>" /></p>					
				</form>
				

			</div>
			<?php
	}

	private function webtv_is_admin_page($names) {
		foreach ( $names as $url )
			if ( FALSE !== stripos($_SERVER['SCRIPT_NAME'], '/wp-admin/'.$url) )
				return true;

		return false;
	}

	function orderlist_js() {
		
	?>
		<script type="text/javascript">
		/* <![CDATA[ */

			function move(index,to) {
			 var list = document.getElementById('list');
			 var total = list.options.length-1;
			 if (index == -1) return false;
			 if (to == +1 && index == total) return false;
			 if (to == -1 && index == 0) return false;
			 var items = new Array;
			 var values = new Array;
			 for (i = total; i >= 0; i--) {
			  items[i] = list.options[i].text;
			  values[i] = list.options[i].value;
			 }
			
			 for (i = total; i >= 0; i--) {
			  if (index == i) {
			   list.options[i + to] = new Option(items[i],values[i + to], 0, 1);
			   list.options[i] = new Option(items[i + to], values[i]);
			   i--;
			  } else {
			   list.options[i] = new Option(items[i], values[i]);
			  }
			 }
			
			 list.focus();
			}
			
			function submitForm() {
			 var list = document.getElementById('list');
			 var orderedlist = document.getElementById('orderedlist');
			
			 for (i = 0; i <= list.options.length-1; i++) {
			  orderedlist.value += list.options[i].text;
			  if (i != list.options.length-1) orderedlist.value += "|";
			 }
			 document.getElementById('sendform').submit();
			}
		/* ]]> */
		</script>
		
	<?php
		if ( $this->webtv_is_admin_page(array('post.php', 'post-new.php', 'page.php', 'page-new.php')) ) {
			$url = get_option('siteurl');
	    	$url .= '/wp-content/plugins/'. dirname(plugin_basename(__FILE__)).'/includes/';
	      	wp_enqueue_script('webtv-insert',$url. 'webtvhandlers.js',array('swfupload') );	
	      	global $post_ID, $temp_ID;
			$post_id = (int) (0 == $post_ID ? $temp_ID : $post_ID);
	      	$maxsize = $this->return_formatbytes(ini_get('post_max_size'));
	      	?>
	      	<script type="text/javascript">
				var swfu;
		
				window.onload = function load_swfupload() {
					
					var settings = {
						flash_url : "<?php echo get_settings('siteurl') . '/wp-includes/js/swfupload/swfupload.swf'?>",
						upload_url: "<?php echo $url . 'upload.php'?>",
						post_params: { "auth_cookie" : "<?php if ( is_ssl() ) echo $_COOKIE[SECURE_AUTH_COOKIE]; else echo $_COOKIE[AUTH_COOKIE]; ?>",
                                       "_wpnonce" : "<?php echo wp_create_nonce('webtv-upload'); ?>",
                                       "id" : "<?=$post_id?>" },
						file_size_limit : "<?=$maxsize; ?>",
						file_types : "*.mp4;*.flv;*.mpg;*.mpeg;*.mov;*.avi;*.3gp;*.wmv;*.m4v;*.qt;*.mpe;*.f4v",
						file_types_description : "Video Files",
						file_upload_limit : 5,
						//custom_settings : {
							//progressTarget : "fsUploadProgress",
							//cancelButtonId : "btnCancel"
						//},
						debug: false,
		
						// Button settings
						button_image_url: "<?php echo $url . 'SmallSpyGlassWithTransperancy_17x18.png'?>",
						button_placeholder_id: "spanButtonPlaceHolder",
						button_width: 140,
						button_height: 19,
						button_text : '<span class="button">Select Video <span class="buttonSmall">(<?=	$maxsize; ?> Max)</span></span>',
						button_text_style : '.button { font-family: Helvetica, Arial, sans-serif; font-size: 12pt; } .buttonSmall { font-size: 10pt; }',
						button_text_top_padding: 0,
						button_text_left_padding: 14,
						button_window_mode: SWFUpload.WINDOW_MODE.TRANSPARENT,
						button_cursor: SWFUpload.CURSOR.HAND,
						//handlers
						file_dialog_complete_handler : webtvfileDialogComplete,
						upload_start_handler : webtvuploadStart,
						upload_progress_handler : webtvuploadProgress,
						upload_error_handler : webtvuploadError,
						upload_success_handler : webtvuploadSuccess,
						upload_complete_handler : webtvuploadComplete,
						
					};
					
					swfu = new SWFUpload(settings);
			     };
			     
			</script>
	      	
	      	<?php
	      	
      	}
	}
	
	function return_formatbytes($val) {
	    $val = trim($val);
	    $last = strtolower(substr($val, -1));
	    $val = substr($val,0,-1);
	    switch($last) {
	        // The 'G' modifier is available since PHP 5.1.0
	        case 'g':
	            $val .= ' GB';
	            break;
	        case 'm':
	            $val .= ' MB';
	            break;
	        case 'k':
	            $val .= ' KB';
	            break;
	    }
	
	    return $val;
	}
	
	
}
	
?>