<?php

require_once 'class.base.php';
require_once 'class.content-types.php';

$cnr_media =& new CNR_Media();
$cnr_media->register_hooks();

/**
 * Core properties/methods for Media management
 * @package Cornerstone
 * @subpackage Media
 * @author SM
 */
class CNR_Media extends CNR_Base {
	
	/**
	 * Legacy Constructor
	 */
	function CNR_Media() {
		$this->__construct();
	}
	
	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
	}
	
	/* Methods */
	
	function register_hooks() {
		//Register media placeholder handler
		cnr_register_placeholder_handler('media', $this->m('content_type_process_placeholder_media'));
		
		//Register field types
		add_action('cnr_register_field_types', $this->m('register_field_types'));
		
		//Register/Modify content types
		add_action('cnr_post_register_content_types', $this->m('register_content_types'));
		
		//Register handler for custom media requests
		add_action('media_upload_cnr_field_media', $this->m('field_upload_media'));
		
		//Display 'Set as...' button in media item box
		add_filter('attachment_fields_to_edit', $this->m('attachment_fields_to_edit'), 11, 2);
		
		//Add form fields to upload form (to pass query vars along with form submission)
		add_action('pre-html-upload-ui', $this->m('attachment_html_upload_ui'));
		
		//Display additional meta data for media item (dimensions, etc.)
		//add_filter('media_meta', $this->m('media_meta'), 10, 2);
		
		//Modifies media upload query vars so that request is routed through plugin
		add_filter('admin_url', $this->m('media_upload_url'), 10, 2);
		
		//Adds admin menus for content types
		add_action('cnr_admin_menu_type', $this->m('type_admin_menu'));
		
		//Modify tabs in upload popup for fields
		add_filter('media_upload_tabs', $this->m('field_upload_tabs'));
	}
	
	/**
	 * Register media-specific field types
	 */
	function register_field_types($field_types) {
		$media = new CNR_Field_Type('media');
		$media->set_description('Media Item');
		$media->set_parent('base_closed');
		$media->set_property('title', 'Select Media');
		$media->set_property('button','Select Media');
		$media->set_property('remove', 'Remove Media');
		$media->set_property('set_as', 'media');
		$media->set_layout('form', '{media}');
		$media->set_layout('display', '{media format="display"}');
		$media->set_layout('display_url', '{media format="display" type="url"}');
		$media->add_script( array('add', 'edit', 'post-new.php', 'post.php', 'media-upload-popup'), $this->add_prefix('script_media'), $this->util->get_file_url('js/media.js'), array($this->add_prefix('script_admin')));
		$field_types[$media->id] =& $media;
		
		$image = new CNR_Field_Type('image');
		$image->set_description('Image');
		$image->set_parent('media');
		$image->set_property('title', 'Select Image');
		$image->set_property('button', 'Select Image');
		$image->set_property('remove', 'Remove Image');
		$image->set_property('set_as', 'image');
		$field_types[$image->id] =& $image;
	}
	
	/**
	 * Register media-specific content types
	 */
	function register_content_types($content_types) {
		global $cnr_content_utilities;
		
		//Load post content type
		foreach ( array('post', 'project') as $type ) {
			unset($ct);
			$ct =& $cnr_content_utilities->get_type($type);
			
			//Add thumbnail image fields to post content type
			$ct->add_group('image_thumbnail', 'Thumbnail Image');
			$ct->add_field('image_thumbnail', 'image', array('title' => 'Select Thumbnail Image', 'set_as' => 'thumbnail {inherit}'));
			$ct->add_to_group('image_thumbnail', 'image_thumbnail');
			$ct->add_group('image_header', 'Header Image');
			$ct->add_field('image_header', 'image', array('title' => 'Select Header Image', 'set_as' => 'header {inherit}'));
			$ct->add_to_group('image_header', 'image_header');
		}
	}
	
	/**
	 * Media placeholder handler
	 * @param string $ph_output Value to be used in place of placeholder
	 * @param CNR_Field $field Field containing placeholder
	 * @param array $placeholder Current placeholder @see CNR_Field::parse_layout for structure of $placeholder array
	 * @param string $layout Layout to build
	 * @param array $data Extended data for field (Default: null)
	 * @return string Value to use in place of current placeholder
	 */
	function content_type_process_placeholder_media($ph_output, $field, $placeholder, $layout, $data) {
		global $post_ID, $temp_ID;
		$attr_default = array('format' => 'form', 'type' => 'html', 'id' => '', 'class' => '');
		$attr = wp_parse_args($placeholder['attributes'], $attr_default);
		//Get media ID
		$post_media = $field->get_data();
		
		//Format output based on placeholder attribute
		switch ( strtolower($attr['format']) ) {
			case 'form':
				$uploading_iframe_ID = (int) (0 == $post_ID ? $temp_ID : $post_ID);
				$media_upload_iframe_src = "media-upload.php";
				$media_id = $field->get_id(true);
				$media_name = $media_id;
				$query = array (
								'post_id'			=> $uploading_iframe_ID,
								'type'				=> 'cnr_field_media',
								'cnr_action'		=> 'true',
								'cnr_field'			=> $media_id,
								'cnr_set_as'		=> $field->get_property('set_as'),
								'TB_iframe'			=> 'true'
								);
				$media_upload_iframe_src = apply_filters('image_upload_iframe_src', $media_upload_iframe_src . '?' . http_build_query($query));
				
				//Get Attachment media URL
				$post_media_valid = get_post($post_media);
				$post_media_valid = ( isset($post_media_valid->post_type) && 'attachment' == $post_media_valid->post_type ) ? true : false;

				//Start output
				ob_start();
			?>
			<?php
				if ($post_media_valid) {
					//Add media preview 
			?>
					{media format="display" id="<?php echo "$media_name-frame"?>" class="media_frame"}
					<input type="hidden" name="<?php echo "$media_name"?>" id="<?php echo "$media_name"?>" value="<?php echo $post_media ?>" />
			<?php
				}
				//Add media action options (upload, remove, etc.)
			?>
					<div class="buttons">
						<a href="<?php echo "$media_upload_iframe_src" ?>" id="<?php echo "$media_name-lnk"?>" class="thickbox button" title="{title}" onclick="return false;">{button}</a>
						<span id="<?php echo "$media_name-options"?>" class="options <?php if (!$post_media_valid) : ?> options-default <?php endif; ?>">
						or <a href="#" title="Remove media" class="del-link" id="<?php echo "$media_name-option_remove"?>" onclick="postImageAction(this); return false;">{remove}</a>
						 <span id="<?php echo "$media_name-remove_confirmation"?>" class="confirmation remove-confirmation confirmation-default">Are you sure? <a href="#" id="<?php echo "$media_name-remove"?>" class="delete" onclick="return postImageAction(this);">Remove</a> or <a href="#" id="<?php echo "$media_name-remove_cancel"?>" onclick="return postImageAction(this);">Cancel</a></span>
						</span>
					</div>
			<?php
				//Output content
				$ph_output = ob_get_clean();
				break;
			case 'display':
				//Add placeholder attributes to attributes from function call
				
				//Remove attributes used by system
				$type = $attr['type'];
				$attr_system = array('format', 'type');
				foreach ($attr_system as $key) {
					unset($attr[$key]);
				}
				$data = wp_parse_args($data, $attr);
				$ph_output = $this->get_media_output($post_media, $type, $data);
				break;
		}
		return $ph_output;
	}
	
	/**
	 * Handles upload of Post media on post edit form
	 * @return 
	 */
	function field_upload_media() {
		$errors = array();
		$id = 0;
		
		//Process image selection
		if ( isset($_POST['setimage']) ) {
			/* Send image data to main post edit form and close popup */
			//Get Attachment ID
			$attachment_id = array_keys($_POST['setimage']);
			$attachment_id = array_shift($attachment_id); 
		 	//Get Attachment Image URL
			$src = wp_get_attachment_image_src($attachment_id, '');
			if (!$src)
				$src = '';
			else
				$src = $src[0];
			//Build JS Arguments string
			$args = "'$attachment_id', '$src'";
			$type = '';
			if ( isset($_REQUEST['attachments'][$attachment_id]['cnr_field']) )
				$type = $_REQUEST['attachments'][$attachment_id]['cnr_field'];
			elseif ( isset($_REQUEST['cnr_field']) )
				$type = $_REQUEST['cnr_field'];
			$type = ( !empty($type) ) ? ", '" . $type . "'" : '';
			$args .= $type;
			?>
			<script type="text/javascript">
			/* <![CDATA[ */
			var win = window.dialogArguments || opener || parent || top;
			win.setPostImage(<?php echo $args; ?>);
			/* ]]> */
			</script>
			<?php
			exit;
		}
		
		//Handle HTML upload
		if ( isset($_POST['html-upload']) && !empty($_FILES) ) {
			$id = media_handle_upload('async-upload', $_REQUEST['post_id']);
			//Clear uploaded files
			unset($_FILES);
			if ( is_wp_error($id) ) {
				$errors['upload_error'] = $id;
				$id = false;
			}
		}
		
		//Display default UI
					
		//Determine media type
		$type = ( isset($_REQUEST['type']) ) ? $_REQUEST['type'] : 'cnr_field_media';
		//Determine UI to use (disk or URL upload)
		$upload_form = ( isset($_GET['tab']) && 'type_url' == $_GET['tab'] ) ? 'media_upload_type_url_form' : 'media_upload_type_form';
		//Load UI
		return wp_iframe( $upload_form, $type, $errors, $id );
	}
	
	/**
	 * Modifies array of form fields to display on Attachment edit form
	 * Array items are in the form:
	 * 'key' => array(
	 * 				  'label' => "Label Text",
	 * 				  'value' => Value
	 * 				  )
	 * 
	 * @return array Form fields to display on Attachment edit form 
	 * @param array $form_fields Associative array of Fields to display on form (@see get_attachment_fields_to_edit())
	 * @param object $attachment Attachment post object
	 */
	function attachment_fields_to_edit($form_fields, $attachment) {
		
		if ($this->is_custom_media()) {
			$post =& get_post($attachment);
			//Clear all form fields
			$form_fields = array();
			if ( substr($post->post_mime_type, 0, 5) == 'image' ) {
				$set_as = 'media';
				$qvar = 'cnr_set_as';
				//Get set as text from request
				if ( isset($_REQUEST[$qvar]) && !empty($_REQUEST[$qvar]) )
					$set_as = $_REQUEST[$qvar];
				elseif ( ( strpos($_SERVER['PHP_SELF'], 'async-upload.php') !== false || isset($_POST['html-upload']) ) && ($ref = wp_get_referer()) && strpos($ref, $qvar) !== false && ($ref = parse_url($ref)) && isset($ref['query']) ) {
					//Get set as text from referer (for async uploads)
					$qs = array();
					parse_str($ref['query'], $qs);
					if ( isset($qs[$qvar]) )
						$set_as = $qs[$qvar];
				}
				//Add "Set as Image" button to form fields array
				$set_as = 'Set as ' . $set_as;
				$field = array(
								'input'		=> 'html',
								'html'		=> '<input type="submit" class="button" value="' . $set_as . '" name="setimage[' . $post->ID . ']" />'
								);
				$form_fields['buttons'] = $field;
				//Add field ID value as hidden field (if set)
				if (isset($_REQUEST['cnr_field'])) {
					$field = array(
									'input'	=> 'hidden',
									'value'	=> $_REQUEST['cnr_field']
									);
					$form_fields['cnr_field'] = $field;
				}
			}
		}
		return $form_fields;
	}
	
	/**
	 * Checks whether current media upload/selection request is initiated by the plugin
	 */
	function is_custom_media() {
		$ret = false;
		$action = 'cnr_action';
		$upload = false;
		if (isset($_REQUEST[$action]))
			$ret = true;
		else {
			$qs = array();
			$ref = parse_url($_SERVER['HTTP_REFERER']);
			if ( isset($ref['query']) )
				parse_str($ref['query'], $qs);
			if (array_key_exists($action, $qs))
				$ret = true;
		}
		
		return $ret;
	}
	
	/**
	 * Add HTML Media upload form
	 * @return void
	 */
	function attachment_html_upload_ui() {
		$vars = array ('cnr_action', 'cnr_field');
		foreach ( $vars as $var ) {
			if ( isset($_REQUEST[$var]) )
				echo '<input type="hidden" name="' . $var . '" id="' . $var . '" value="' . esc_attr($_REQUEST[$var]) . '" />';
		}
	}
	
	/**
	 * Adds additional media meta data to media item display
	 * @param object $meta Meta data to display
	 * @param object $post Attachment post object
	 * @return string Meta data to display
	 */
	function media_meta($meta, $post) {
		if ($this->is_custom_media() && wp_attachment_is_image($post->ID)) {
			//Get attachment image info
			$img = wp_get_attachment_image_src($post->ID, '');
			if (is_array($img) && count($img) > 2) {
				//Add image dimensions to output
				$meta .= sprintf('<div>%dpx&nbsp;&times;&nbsp;%dpx</div>', $img[1], $img[2]);
			}
		}
		return $meta;
	}
	
	/**
	 * Modifies media upload URL to work with CNR attachments
	 * @param string $url Full admin URL
	 * @param string $path Path part of URL
	 * @return string Modified media upload URL
	 */
	function media_upload_url($url, $path) {
		if (strpos($path, 'media-upload.php') === 0) {
			//Get query vars
			$qs = parse_url($url);
			$qs = ( isset($qs['query']) ) ? $qs['query'] : '';
			$q = array();
			parse_str($qs, $q);
			//Check for tab variable
			if (isset($q['tab'])) {
				//Replace tab value
				$q['cnr_tab'] = $q['tab'];
				$q['tab'] = 'type';
			}
			//Rebuild query string
			$qs_upd = build_query($q);
			//Update query string on URL
			$url = str_replace($qs, $qs_upd, $url);
		}
		return $url;
	}
	
	/*-** Field-Specific **-*/
	
	/**
	 * Removes URL tab from media upload popup for fields
	 * Fields currently only support media stored @ website
	 * @param array $default_tabs Media upload tabs
	 * @see media_upload_tabs() for full $default_tabs array
	 * @return array Modified tabs array
	 */
	function field_upload_tabs($default_tabs) {
		if ( $this->is_custom_media() )
			unset($default_tabs['type_url']);
		return $default_tabs;
	}
	
	/*-** Post Attachments **-*/
	
	/**
	 * Retrieves matching attachments for post
	 * @param object|int $post Post object or Post ID (Default: current global post)
	 * @param array $args (optional) Associative array of query arguments
	 * @see get_posts() for query arguments
	 * @return array|bool Array of post attachments (FALSE on failure)
	 */
	function post_get_attachments($post = null, $args = '', $filter_special = true) {
		if (!$this->util->check_post($post))
			return false;
		global $wpdb;
		
		//Default arguments
		$defaults = array(
						'post_type'			=>	'attachment',
						'post_parent'		=>	(int) $post->ID,
						'numberposts'		=>	-1
						);
		
		$args = wp_parse_args($args, $defaults);
		
		//Get attachments
		$attachments = get_children($args);
		
		//Filter special attachments
		if ( $filter_special ) {
			$start = '[';
			$end = ']';
			$removed = false;
			foreach ( $attachments as $i => $a ) {
				if ( $start == substr($a->post_title, 0, 1) && $end == substr($a->post_title, -1) ) {
					unset($attachments[$i]);
					$removed = true;
				}
			}
			if ( $removed )
				$attachments = array_values($attachments);
		}
		
		//Return attachments
		return $attachments;
	}
	
	/**
	 * Retrieve the attachment's path
	 * Path = Full URL to attachment - site's base URL
	 * Useful for filesystem operations (e.g. file_exists(), etc.)
	 * @param object|id $post Attachment object or ID
	 * @return string Attachment path
	 */
	function get_attachment_path($post = null) {
		if (!$this->util->check_post($post))
			return '';
		//Get Attachment URL
		$url = wp_get_attachment_url($post->ID);
		//Replace with absolute path
		$path = str_replace(get_bloginfo('wpurl') . '/', ABSPATH, $url);
		return $path;
	}
	
	/**
	 * Retrieves filesize of an attachment
	 * @param obj|int $post (optional) Attachment object or ID (uses global $post object if parameter not provided)
	 * @param bool $formatted (optional) Whether or not filesize should be formatted (kb/mb, etc.) (Default: TRUE)
	 * @return int|string Filesize in bytes (@see filesize()) or as formatted string based on parameters
	 */
	function get_attachment_filesize($post = null, $formatted = true) {
		$size = 0;
		if (!$this->util->check_post($post))
			return $size;
		//Get path to attachment
		$path = $this->get_attachment_path($post);
		//Get file size
		if (file_exists($path))
			$size = filesize($path);
		if ($size > 0 && $formatted) {
			$size = (int) $size;
			$label = 'b';
			$format = "%s%s";
			//Format file size
			if ($size >= 1024 && $size < 102400) {
				$label = 'kb';
				$size = intval($size/1024);
			}
			elseif ($size >= 102400) {
				$label = 'mb';
				$size = round(($size/1024)/1024, 1);
			}
			$size = sprintf($format, $size, $label);
		}
		
		return $size;
	}
	
	/**
	 * Prints the attachment's filesize 
	 * @param obj|int $post (optional) Attachment object or ID (uses global $post object if parameter not provided)
	 * @param bool $formatted (optional) Whether or not filesize should be formatted (kb/mb, etc.) (Default: TRUE)
	 */
	function the_attachment_filesize($post = null, $formatted = true) {
		echo $this->get_attachment_filesize($post, $formatted);
	}
	
	/**
	 * Build output for media item
	 * Based on media type and output type parameter
	 * @param int|obj $media Media object or ID
	 * @param string $type (optional) Output type (Default: source URL)
	 * @return string Media output
	 */
	function get_media_output($media, $type = 'url', $attr = array()) {
		$ret = '';
		$media =& get_post($media);
		if ( !!$media || 'attachment' != $media->post_type ) {
			//URL - Same for all attachments
			if ( 'url' == $type ) {
				$ret = wp_get_attachment_url($media->ID);
			} else {
				//Determine media type
				$mime = get_post_mime_type($media);
				$mime_main = substr($mime, 0, strpos($mime, '/'));
				$mime_sub = substr($mime, strpos($mime, '/') + 1);
				
				//Pass to handler for media type + output type
				$handler = implode('_', array('get', $mime_main, 'output'));
				if ( method_exists($this, $handler))
					$ret = $this->{$handler}($media, $type, $attr);
			}
		}
		
		
		return apply_filters($this->add_prefix('get_media_output'), $ret, $media, $type);
	}
	
	/**
	 * Build HTML for displaying media
	 * Output based on media type (image, video, etc.)
	 * @param int|obj $media (Media object or ID)
	 * @return string HTML for media
	 */
	function get_media_html($media) {
		$out = '';
		return $out;
	}
	
	/**
	 * Builds output for image attachments
	 * @param int|obj $media Media object or ID
	 * @param string $type Output type
	 * @return string Image output
	 */
	function get_image_output($media, $type = 'html', $attr = array()) {
		$ret = '';
		if ( !wp_attachment_is_image($media->ID) )
			return $ret;
		
		//Get image properties
		$attr = wp_parse_args($attr, array('alt' => trim(strip_tags( $media->post_excerpt ))));
		list($attr['src'], $attribs['width'], $attribs['height']) = wp_get_attachment_image_src($media->ID, '');
			
		switch ( $type ) {
			case 'html' :
				array_map('esc_attr', $attr);
				$attr_str = '';
				foreach ( $attr as $key => $val ) {
					$attr_str .= ' ' . $key . '="' . $val . '"';
				}
				$ret = '<img' . $attr_str . ' />';
				break;
		}
		
		return $ret;
	}
	
	/**
	 * Build HTML IMG element of an Image
	 * @param array $image Array of image properties
	 * 	0:	Source URI
	 * 	1:	Width
	 * 	2:	Height
	 * @return string HTML IMG element of specified image
	 */
	function get_image_html($image, $attributes = '') {
		$ret = '';
		if (is_array($image) && count($image) >= 3) {
			//Build attribute string
			if (is_array($attributes)) {
				$attribs = '';
				$attr_format = '%s="%s"';
				foreach ($attributes as $attr => $val) {
					$attribs .= sprintf($attr_format, $attr, attribute_escape($val));
				}
				$attributes = $attribs;
			}
			$format = '<img src="%1$s" width="%2$d" height="%3$d" ' . $attributes . ' />';
			$ret = sprintf($format, $image[0], $image[1], $image[2]);
		}
		return $ret;
	}
	
	/**
	 * Registers admin menus for content types
	 * @param CNR_Content_Type $type Content Type Instance
	 * 
	 * @global CNR_Content_Utilities $cnr_content_utilities
	 */
	function type_admin_menu($type) {
		global $cnr_content_utilities;
		$u =& $cnr_content_utilities;
		
		if ( 'project' != $type->id )
			return false;
			
		//Add Menu
		$parent_page = $u->get_admin_page_file($type->id);
		$menu_page = $u->get_admin_page_file($type->id, 'extra');
		$this->util->add_submenu_page($parent_page, __('Extra Menu'), __('Extra Menu'), 8, $menu_page, $this->m('type_admin_page'));
	}
	
	function type_admin_page() {
		global $title;
		?>
		<div class="wrap">
			<?php screen_icon('edit'); ?>
			<h2><?php esc_html_e($title); ?></h2>
			<p>
			This is an extra menu for a specific content type added via a plugin hook
			</p>
		</div>
		<?php
	}
}
?>