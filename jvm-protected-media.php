<?php
/**
 * Plugin Name: JVM Protected Media
 * Description: Protect access to all your media files and implement custom file access rules using hooks. Works for apache. No Multisite support.
 * Version: 1.0.6
 * Author: Joris van Montfort
 * Author URI: http://www.jorisvm.nl
 * Requires at least: 4.4.1
 * Tested up to: 5.2.2
 *
 * Text Domain: jvm-protected-media
 * Domain Path: /languages/
 *
 * This WordPress Plugin is a free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * It is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * See https://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'JVM_Protected_Media' ) ) {
	class JVM_Protected_Media {

		/**
		 * @var JVM_Protected_Media The single instance of the class
		 */
		protected static $_instance = null;

		/**
		 * Main JVM Protected Media Instance
		 *
		 * Ensures only one instance of JVM Protected Media is loaded or can be loaded.
		 *
		 * @static
		 * @return JVM Protected Media - Main instance
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		/**
		 * Cloning is forbidden.
		 */
		public function __clone() {
			_doing_it_wrong( __FUNCTION__, esc_html__( 'Cheatin&#8217; huh?', 'jvm-protected-media' ), '1.0' );
		}

		/**
		 * Unserializing instances of this class is forbidden.
		 */
		public function __wakeup() {
			_doing_it_wrong( __FUNCTION__, esc_html__( 'Cheatin&#8217; huh?', 'jvm-protected-media' ), '1.0' );
		}

		/**
		 * JVM Protected Media Constructor.
		 */
		public function __construct() {

			$this->define_constants();
			//$this->includes();
			$this->init_hooks();

			do_action( 'jvm_protected_media_loaded' );
		}

		/**
		 * Define WR Constants
		 */
		private function define_constants() {

			$constants = array(
				'JVM_PM_DIR' => untrailingslashit( plugin_dir_path( __FILE__ ) ),
				'JVM_PM_PATH' => untrailingslashit(plugin_basename( __FILE__ ) ),
			);

			foreach ( $constants as $name => $value ) {
				$this->define( $name, $value );
			}
		}

		/**
		 * Define constant if not already set
		 * @param  string $name
		 * @param  string|bool $value
		 */
		private function define( $name, $value ) {
			if ( ! defined( $name ) ) {
				define( $name, $value );
			}
		}

		/**
		 * Hook into actions and filters
		 */
		private function init_hooks() {
			// Check requirements
			add_action('wp_loaded', function() {

				if (false == JVM_Protected_Media::check_apache()) { 
					add_action( 'admin_notices', array( $this, 'warning_apache' ) );
					return;
				}

				if (false == JVM_Protected_Media::check_nginx()) { 
					add_action( 'admin_notices', array( $this, 'warning_nginx' ) );
					return;
				}
			});
			
			// Rewrite hook
			add_filter('mod_rewrite_rules', array('JVM_Protected_Media', 'get_rewrite_rules'));

			// File access hook
			add_action('init',  array('JVM_Protected_Media', 'parse_get'), 0);
			
			// Deactiavation  and activation hooks
			register_deactivation_hook(JVM_PM_PATH, array('JVM_Protected_Media', 'deactivate'));
			register_activation_hook(JVM_PM_PATH, array('JVM_Protected_Media', 'activate'));

			// Notices
			add_action( 'admin_init', array( $this, 'notices_dismissed') );
		}

		public static function check_apache() {
			global $is_apache;

			if ($is_apache) {
				$has_mod_rewite = apache_mod_loaded('mod_rewrite', true);
				$htacces_writeable = is_writable( ABSPATH . '.htaccess' );

				if (! is_multisite() && get_option( 'permalink_structure' ) && $has_mod_rewite && $htacces_writeable ) { 
					return true;
				}

				return false;
			}

			return true;
		}

		public static function check_nginx() {
			global $is_nginx;

			if ($is_nginx) {
				// If notice has been dimissed return true.
				$user_id = get_current_user_id();
    			if ( get_user_meta( $user_id, 'JVM_Protected_Media_nginx_notice_dismissed' ) ) {
    				return true;
    			}
				return false;
			}

			return true;
		}

		/**
		 * Activate plugin
		 */
		public static function activate() {
	  		global $wp_rewrite;
    		// Flush the rewrite rules
    		$wp_rewrite->flush_rules();
		}

		/**
		 * Deactivates plugin
		 */
		public static function deactivate() {
			global $wp_rewrite;

			// Remove rewrite rules
			remove_filter('mod_rewrite_rules', array('JVM_Protected_Media', 'get_rewrite_rules'));
	  		
	  		// Flush the rewrite rules
    		$wp_rewrite->flush_rules();
		}

		/**
		 * Get the rewrite rules for the .htaccess file
		 * @param  string $rules
		 * @return string $rules
		 */
		public static function get_rewrite_rules($rules) {
			$newRules = array('# JVM Protected Media file rewrite rules',
							  'RewriteRule ^wp-content/uploads(/.*\.\w+)$ index.php?jvm_protected_media_file=$1 [QSA,L]',
							  '# JVM Protected Media file rewrite rules end'
							  );
			
			$rewrite_rules = "\n\n" . implode("\n", $newRules). "\n\n";
			
			return $rules . $rewrite_rules;
		}

		public function notices_dismissed() {

		    $user_id = get_current_user_id();
		    // Nginx notice dismiss
		    if ( isset( $_GET['jvm-protected-media-nginx-dismissed'] ) ){
		        add_user_meta( $user_id, 'JVM_Protected_Media_nginx_notice_dismissed', 'true', true );
			}
		}

		/**
		 * Display error notice if apache requirements are not ok.
		 */
		public function warning_apache() {
			?>
			<div class="notice notice-error">
				<p><?php

				printf(
					esc_html__( 'You are using apache as server. %1$s needs mod rewrite and write access to the .htaccess file. If you see this error, one or more of these requirements is not met.', 'jvm-protected-media' ),
					'JVM Protected Media'
				);
				?></p>
			</div>
			<?php
		}

		public function warning_nginx() {
			?>
			<div class="notice notice-error">
				<p><?php

				printf(
					esc_html__( 'You are using nginx as server. %1$s needs special configuation for nginx to protect files. Read the readme.txt to find the correct nginx config line and make sure to add and test it.', 'jvm-protected-media' ),
					'JVM Protected Media'
				);
				?>
					<a href="?jvm-protected-media-nginx-dismissed">Dismiss</a>
				</p>
				
			</div>
			<?php
		}

		/**
		 * Detect wpcontent file access
		 */
		public static function parse_get() {	
			$get = filter_input_array(INPUT_GET);
			
			if (isset($get['jvm_protected_media_file'])) {
				global $wpdb;

				// Disable caching of this page by caching plugins
			    if ( ! defined( 'DONOTCACHEPAGE' ) )
			      define( 'DONOTCACHEPAGE', 1 );
			
			    if ( ! defined( 'DONOTCACHEOBJECT' ) )
			      define( 'DONOTCACHEOBJECT', 1 );
			
			    if ( ! defined( 'DONOTMINIFY' ) )
			      define( 'DONOTMINIFY', 1 );


				$file = $get['jvm_protected_media_file'];
				$uploadDir = wp_upload_dir();
				$fullUrl =  $uploadDir['baseurl'] . $file;
				$fullPath = $uploadDir['basedir'] . $file;
				$fileInfo = pathinfo($fullPath);
				$isResizedImage = false;
				
				// Get and check file id 
				$fileId = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE guid='$fullUrl'");
				// Try a file version without thumnail data
				if (empty($fileId)) {
					// Convert thumbnail urls to the main file url (for example remove -300x188 in the filename)
					$queryUrl = preg_replace("/(-\d+x\d+)/", "", $fullUrl);
					
					$fileId = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE guid='$queryUrl'");

					if ($fileId) {
						$isResizedImage = true;
					}
				}
				
				//if (!empty($fileId)) {
				// Call custom hook 
				do_action( 'jvm_protected_media_file', array(
					'id' => $fileId, 
					'url' => $fullUrl, 
					'path' => $fullPath,
					'is_resized_image' => $isResizedImage
				));
				//}
			}
		}

		/**
		 * Sends output and headers for a file request
		 */
		public static function send_file_output($fullPath) {
			$fileInfo = pathinfo($fullPath);
			
			$mime = wp_check_filetype($fullPath); // Check filetype against allowed filetypes
			if (isset( $mime['type']) && $mime['type']) {
			    $mimetype = $mime['type'];
			}
			
			header('Cache-Control: max-age=86400');	// Cache one day to save some bandwidth			
			header('Content-Type: ' . $mimetype); // always send this
			
			// Make a timestamp for our most recent modification...
			$last_modified = gmdate('D, d M Y H:i:s', filemtime($fullPath));
			$etag = '"' . md5( $last_modified ) . '"';
			
			// Support for Conditional GET etag
			$client_etag = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? stripslashes($_SERVER['HTTP_IF_NONE_MATCH']) : false;
			if (!empty($client_etag)) {
				if ($client_etag == $etag) {
					// Save some bandwith	
					status_header(304);
					exit;	
				}
			}
			
			// Support for Conditional GET Modified since
			if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($last_modified) <= strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
				// Save on some bandwidth!
				status_header(304);
				exit;	
		   	}
			
			header("HTTP/1.1 200 OK");
			header('ETag: ' . $etag );
			header("Last-Modified: $last_modified GMT" );
			header("Content-length: ".filesize($fullPath));
			header("Content-Disposition: filename=".$fileInfo['basename']);

		  	// Serve the file
		  	if (ob_get_length()) {
		    	ob_clean();
			}
		  	flush();
		
		  	readfile($fullPath);
		  	//echo file_get_contents($fullPath);
		  	//echo gzuncompress(file_get_contents($fullPath));

		  	exit;
		}
	}
} // end class check

// GO!
JVM_Protected_Media::instance();