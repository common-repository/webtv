=== WebTV ===
Contributors: edeleon
Donate link: http://www.webstratega.com/plugins/webtv-plugin-wordpress/#donate
Tags: video, video solutions, youtube, blip.tv, vimeo, embed
Requires at least: 2.7
Tested up to: 2.8
Stable tag: 0.7

Lets you attach a video to a post and upload to most popular video distribution sites like YouTube, Vimeo and Blip.tv, after video is uploaded and processed get and inserts the embed code from the sites into custom fields on the post.  The plugin has the possibility to extend any other distribution site creating extra drivers

== Description == 

Lets you attach a video to a post and upload to most popular video distribution sites like YouTube, Vimeo and Blip.tv, after video is uploaded and processed get and inserts the embed code from the sites into custom fields on the post.  The plugin has the possibility to extend any other distribution site creating extra drivers.

Every time you create a new post a new Metabox appears letting you attach a video to the post, after the video is uploaded, it uses the post title, description and tags to publish the video to the most popular video distribution sites like YouTube, Blip, Vimeo.
You can check the option to let the plugin automatically publish the post on Wordpress after a successful upload to any of the video distribution sites configured.
The plugin uses the Wordpress internal cron system to upload the video to all the sites configured.  After a succesfull upload the plugin checks the video distribution site every 5 minutes if the video was processed and if the embed code is available, once the video is ready it gets the embed code and insert the data into a custom field on the post.

== Installation ==

Unzip the file and copy the directory to the plugin directory inside Wordpress.  The plugin uses the Wordpress upload_dir, please be sure that the directory have right permissions.  Activate the plugin and configure the settings of the plugin.

== Instructions ==

You can configure the number of times the plugin tries to upload a video to the Internet, that helps the plugin prevent an infinite loop if a video site is unavailable.
You can configure the order of how you want to use the videos published into your Wordpress template, using the template tag explained on this document.
Follow the instructions to fill the data to configure the different video distribution sites.  Some of them can ask you for a Developer Key.

== Screenshots ==
1. Configuration screen
2. Attaching video to a post
3. WebTV Status MetaBox
4. Custom fields from WebTV

== Template Tags ==

Once the plugin gets the embed code and insert it into the custom field, you can use the "template tag" webtv_embedcode() to display the video on your post following the order you had defined on the settings.

== Compatibility ==

You need PHP version 5 in order to use the plugin.  This plugin uses the curl command, please make sure your php installation have curl support.

== Acknowledgements ==