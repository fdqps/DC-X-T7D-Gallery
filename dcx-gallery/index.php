<?php
/*
Plugin Name: DCx Gallery
Plugin URI: http://teknograd.no/wp_dcx_gallery
Description: Grab images from DCx and publish on a Wordpress page.
Version: 0.0.1
Author: Fredrik Davidsson
Author URI: http://davidsson.co
License: http://www.gnu.org/licenses/gpl-2.0.html
*/

// INIT

/* Runs when plugin is activated */
register_activation_hook(__FILE__,'dcx_gallery_install'); 

/* Runs on plugin deactivation*/
register_deactivation_hook( __FILE__, 'dcx_gallery_remove' );

function dcx_gallery_install() {
	/* Creates new database field */
	add_option("dcx_gallery_server", 'http://t7ddev.mam01.teknograd.no', '', 'yes');
	add_option("dcx_gallery_username", 'Do_good_stuff', '', 'yes');
	add_option("dcx_gallery_password", '', '', 'yes');
	}

function dcx_gallery_remove() {
	/* Deletes the database field */
	delete_option('dcx_gallery_server');
	delete_option('dcx_gallery_username');
	delete_option('dcx_gallery_password');
	}
	
add_action('wp_enqueue_scripts', 'dcx_gallery_scripts_basic');  
function dcx_gallery_scripts_basic()   {  
    // Register the script like this for a plugin:  
    wp_register_script('galleria', plugins_url('galleria/galleria-1.2.8.min.js', __FILE__)); 
    wp_enqueue_script( 'galleria' );  
	}  
// END INIT



// PAGE
// add_action('init','hello_world');
// Getting called on pages when activated.
function dcx_gallery() {
	global $post;

	$dcx_gallery_server = get_option('dcx_gallery_server');
	$dcx_gallery_username = get_option('dcx_gallery_username');
	$dcx_gallery_password = get_option('dcx_gallery_password');
	$dcx_gallery_fulltext = get_post_custom_values('dcx_gallery_fulltext', $post->ID);
	$dcx_gallery_channel = get_post_custom_values('dcx_gallery_channel', $post->ID);
	// If set lets show gallery.
	if ($dcx_gallery_fulltext[0] && $dcx_gallery_username && $dcx_gallery_password && $dcx_gallery_channel) {
		$url = $dcx_gallery_server . "?q[channel][]=".$dcx_gallery_channel[0]."&q[fulltext][]=".urlencode($dcx_gallery_fulltext[0]);
		// echo "<br>URL: " . $url;
		// 300x400
		$urlReconstructed = str_replace("]", "%5D", str_replace("[", "%5B", $url));
		$ch = curl_init();
		
		// set URL and other appropriate options
		curl_setopt($ch, CURLOPT_URL, $urlReconstructed);
		
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_USERPWD, $dcx_gallery_username . ":" . $dcx_gallery_password);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		
		// grab URL and pass it to the browser
		$atom = curl_exec($ch);
		$xml = new SimpleXMLElement($atom);
		// echo "<pre>" . print_r($xml->entry, true) . "</pre>";
		echo '<div id="dcx_galleria" style="height:600px;">';
		foreach($xml->entry as $cPost) { 
			// echo $cPost->content; 
			$content = $cPost->content->div;
			echo "<img src='" . $content->div->img["src"] . "' data-title='" . $cPost->title . "' data-description='" . $cPost->published . "'>";
			// echo "<pre>" . print_r($content->div, true) . "</pre>";
			}
		
		// close cURL resource, and free up system resources
		curl_close($ch);
		echo '</div>';
		?>
		<script>
			Galleria.loadTheme('<?php echo plugins_url(); ?>/dcx-gallery/galleria/themes/classic/galleria.classic.min.js');
			Galleria.run('#dcx_galleria');
			Galleria.configure({
				imageCrop: true,
				transition: 'fade'
				});
		</script>
		<?php		
		}
	}
// END PAGE



// META BOX
add_action( 'add_meta_boxes', 'dcx_gallery_meta_box' );

function dcx_gallery_meta_box() {
    add_meta_box( 
        'myplugin_sectionid', 
        'DCx Gallery',
        'dcx_gallery_inner_custom_box',
        'post' 
    );
    
    add_meta_box(
        'myplugin_sectionid',
        'DCx Gallery', 
        'dcx_gallery_inner_custom_box',
        'page'
    );
}

/* Prints the box content */
function dcx_gallery_inner_custom_box($post) {
	// wp_nonce_field( basename( __FILE__ ), 'dcx_gallery_post_class_nonce' );
	
	$v_dcx_gallery_fulltext = get_post_custom_values('dcx_gallery_fulltext', $post_id);
	$v_dcx_gallery_channel = get_post_custom_values('dcx_gallery_channel', $post_id);
  	// echo "<pre>" . print_r($gallery_fulltext_value, true) . "</pre>";
  	// The actual fields for data entry
  	echo '<table><tr><td><label for="myplugin_new_field">Fulltext search</label></td><td>';
  	echo '<input type="text" id="dcx_gallery_fulltext" name="dcx_gallery_fulltext" value="';
  	echo $v_dcx_gallery_fulltext[0];
  	echo '" size="80" /></td></tr>';

  	echo '<tr><td><label for="myplugin_new_field">DCx channel</label></td><td>';
  	echo '<input type="text" id="dcx_gallery_channel" name="dcx_gallery_channel" value="';
  	echo $v_dcx_gallery_channel[0];
  	echo '" size="80" /></td></tr></table>';
	}

/* Do something with the data entered */
add_action('save_post', 'dcx_gallery_save_postdata');

/* When the post is saved, saves our custom data */
function dcx_gallery_save_postdata($post_id) {
 	// If it is our form has not been submitted, so we dont want to do anything
  	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }

	// verify this came from the our screen and with proper authorization
	// if ( !wp_verify_nonce( $_POST['dcx_gallery_post_class_nonce'], plugin_basename( __FILE__ ) ) ) { return; }

  	// Check permissions
  	if ( 'page' == $_POST['post_type']) {
    	if ( !current_user_can( 'edit_page', $post_id ) ) { return; }
  		} else {
    	if ( !current_user_can( 'edit_post', $post_id ) ) { return; }
  		}

  	// OK, we're authenticated: we need to find and save the data
	$v_dcx_gallery_fulltext = $_POST['dcx_gallery_fulltext'];
	
	delete_post_meta($post_id, "dcx_gallery_fulltext");
	update_post_meta($post_id, "dcx_gallery_fulltext", $v_dcx_gallery_fulltext);

	$v_dcx_gallery_channel = $_POST['dcx_gallery_channel'];
	
	delete_post_meta($post_id, "dcx_gallery_channel");
	update_post_meta($post_id, "dcx_gallery_channel", $v_dcx_gallery_channel);
	}
	
// END META BOX



// ADMIN
if (is_admin()){
	/* Call the html code */
	add_action('admin_menu', 'dcx_gallery_admin_menu');

	function dcx_gallery_admin_menu() {
		add_pages_page('DCx Gallery', 'DCx Gallery', 'administrator', 'dcx_gallery', 't7d_admin');
		}
	}

function t7d_admin() { ?>
	<div>
	<h2>DCx Gallery settings</h2>
	
	<form method="post" action="options.php">
	<?php wp_nonce_field('update-options'); ?>
	
	<table width="100%" border="0">
		<tr valign="top">
			<th width="60" scope="row">DCx server URL</th>
			<td width="400">
			<input name="dcx_gallery_server" type="text" id="dcx_gallery_server" value="<?php echo get_option('dcx_gallery_server'); ?>" size="60" /></td>
		</tr>
	
	
		<tr valign="top">
			<th width="60" scope="row">Username</th>
			<td width="400">
			<input name="dcx_gallery_username" type="text" id="dcx_gallery_username" value="<?php echo get_option('dcx_gallery_username'); ?>" /></td>
		</tr>
	
		<tr valign="top">
			<th width="60" scope="row">Password</th>
			<td width="400">
			<input name="dcx_gallery_password" type="text" id="dcx_gallery_password" value="<?php echo get_option('dcx_gallery_password'); ?>" /></td>
		</tr>
		
	</table>
	
	<input type="hidden" name="action" value="update" />
	<input type="hidden" name="page_options" value="dcx_gallery_server,dcx_gallery_username,dcx_gallery_password" />
	
	<p>
	<input type="submit" value="<?php _e('Save Changes') ?>" />
	</p>
	
	</form>
	</div>
	<?php
	}
// END ADMIN
?>