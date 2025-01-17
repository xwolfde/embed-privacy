<?php
namespace epiphyt\Embed_Privacy;
use WP_Post;
use function __;
use function _x;
use function add_action;
use function add_post_meta;
use function array_column;
use function array_merge;
use function array_search;
use function delete_option;
use function delete_post_thumbnail;
use function delete_site_option;
use function dirname;
use function get_option;
use function get_post_meta;
use function get_post_thumbnail_id;
use function get_posts;
use function get_site_option;
use function get_sites;
use function is_int;
use function is_multisite;
use function is_plugin_active_for_network;
use function load_plugin_textdomain;
use function plugin_basename;
use function reset;
use function restore_current_blog;
use function sprintf;
use function switch_to_blog;
use function update_option;
use function update_post_meta;
use function update_site_option;
use function wp_delete_attachment;
use function WP_Filesystem;
use function wp_insert_post;

/**
 * Migration class to update data in the database on upgrades.
 * 
 * @since	1.2.0
 *
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 */
class Migration {
	/**
	 * @var		\epiphyt\Embed_Privacy\Migration
	 */
	private static $instance;
	
	/**
	 * @var		array Default embed providers
	 */
	private $providers = [];
	
	/**
	 * @var		string Current migration version
	 * @since	1.2.2
	 */
	private $version = '1.3.0';
	
	/**
	 * Post Type constructor.
	 */
	public function __construct() {
		self::$instance = $this;
	}
	
	/**
	 * Initialize functions.
	 */
	public function init() {
		// since upgrader_process_complete hook runs the old plugin code,
		// it is useless for real migrations, unfortunately
		// thus, we need to check on every page load in the admin if there are
		// new migrations
		add_action( 'admin_init', [ $this, 'migrate' ] );
		add_action( 'activated_plugin', [ $this, 'migrate' ], 10, 2 );
	}
	
	/**
	 * Add an embed provider post.
	 * 
	 * @param	array	$embed Embed provider information
	 */
	private function add_embed( array $embed ) {
		// since meta_input doesn't work on every multisite (I don't know why)
		// extract meta data and use add_post_meta() afterwards
		// see: https://github.com/epiphyt/embed-privacy/issues/14
		if ( ! empty( $embed['meta_input'] ) ) {
			$meta_data = $embed['meta_input'];
			unset( $embed['meta_input'] );
		}
		
		$post_id = wp_insert_post( $embed );
		
		// add meta data
		if ( is_int( $post_id ) && isset( $meta_data ) ) {
			foreach ( $meta_data as $meta_key => $meta_value ) {
				add_post_meta( $post_id, $meta_key, $meta_value );
			}
		}
	}
	
	/**
	 * Delete an option either from the global multisite settings or the regular site.
	 * 
	 * @param	string	$option The option name
	 * @param	bool	$network Whether to force delete a site option or not
	 * @return	bool True if the option was deleted, false otherwise
	 */
	private function delete_option( $option, $network = false ) {
		if ( is_multisite() && ( $network || is_plugin_active_for_network( Embed_Privacy::get_instance()->plugin_file ) ) ) {
			return delete_site_option( 'embed_privacy_' . $option );
		}
		
		return delete_option( 'embed_privacy_' . $option );
	}
	
	/**
	 * Get a unique instance of the class.
	 * 
	 * @return	\epiphyt\Embed_Privacy\Migration The single instance of this class
	 */
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		
		return self::$instance;
	}
	
	/**
	 * Get an option either from the global multisite settings or the regular site.
	 * 
	 * @param	string	$option The option name
	 * @param	mixed	$default The default value if the option is not set
	 * @param	bool	$network Whether to force get a site option or not
	 * @return	mixed Value set for the option
	 */
	private function get_option( $option, $default = false, $network = false ) {
		if ( is_multisite() && ( $network || is_plugin_active_for_network( Embed_Privacy::get_instance()->plugin_file ) ) ) {
			return get_site_option( 'embed_privacy_' . $option, $default );
		}
		
		return get_option( 'embed_privacy_' . $option, $default );
	}
	
	/**
	 * Run migrations.
	 */
	public function migrate( $plugin = '', $network_wide = false ) {
		// check for active migration
		if ( $this->get_option( 'is_migrating', false, $network_wide ) ) {
			return;
		}
		
		// start the migration
		$this->update_option( 'is_migrating', true, $network_wide );
		
		// load textdomain early for migrations
		load_plugin_textdomain( 'embed-privacy', false, dirname( plugin_basename( Embed_Privacy::get_instance()->plugin_file ) ) . '/languages' );
		
		$version = $this->get_option( 'migrate_version', 'initial', $network_wide );
		
		// on initial activation, both 'admin_init' and 'activated_plugins' hooks get called
		// ignore the one of 'admin_init' here
		if ( $version === 'initial' && empty( $plugin ) ) {
			$this->delete_option( 'is_migrating', $network_wide );
			
			return;
		}
		
		if ( $version !== $this->version ) {
			$this->register_default_embed_providers();
		}
		
		switch ( $version ) {
			case $this->version:
				// most recent version, do nothing
				break;
			case '1.2.2':
				if ( is_multisite() && ( $network_wide || is_plugin_active_for_network( Embed_Privacy::get_instance()->plugin_file ) ) ) {
					$sites = get_sites( [
						'number' => 10000,
					] );
					
					foreach ( $sites as $site ) {
						switch_to_blog( $site->blog_id );
						
						$this->migrate_1_3_0();
						$this->migrate_1_2_2();
						$this->migrate_1_2_1();
					}
					
					restore_current_blog();
				}
				else {
					$this->migrate_1_3_0();
				}
				break;
			case '1.2.1':
				if ( is_multisite() && ( $network_wide || is_plugin_active_for_network( Embed_Privacy::get_instance()->plugin_file ) ) ) {
					$sites = get_sites( [
						'number' => 10000,
					] );
					
					foreach ( $sites as $site ) {
						switch_to_blog( $site->blog_id );
						
						$this->migrate_1_3_0();
						$this->migrate_1_2_2();
					}
					
					restore_current_blog();
				}
				else {
					$this->migrate_1_3_0();
					$this->migrate_1_2_2();
				}
				break;
			case '1.2.0':
				if ( is_multisite() && ( $network_wide || is_plugin_active_for_network( Embed_Privacy::get_instance()->plugin_file ) ) ) {
					$sites = get_sites( [
						'number' => 10000,
					] );
					
					foreach ( $sites as $site ) {
						switch_to_blog( $site->blog_id );
						
						$this->migrate_1_3_0();
						$this->migrate_1_2_2();
						$this->migrate_1_2_1();
					}
					
					restore_current_blog();
				}
				else {
					$this->migrate_1_3_0();
					$this->migrate_1_2_2();
					$this->migrate_1_2_1();
				}
				break;
			default:
				// run all migrations
				$this->migrate_1_2_0( $network_wide );
				break;
		}
		
		// migration done
		$this->update_option( 'migrate_version', $this->version, $network_wide );
		$this->delete_option( 'is_migrating', $network_wide );
	}
	
	/**
	 * Migrations for version 1.2.0.
	 * 
	 * @since	1.2.0
	 * 
	 * - Add default embed providers
	 */
	private function migrate_1_2_0( $network ) {
		// add embeds
		if ( is_multisite() && ( $network || is_plugin_active_for_network( Embed_Privacy::get_instance()->plugin_file ) ) ) {
			$sites = get_sites( [
				'number' => 10000,
			] );
			
			foreach ( $sites as $site ) {
				switch_to_blog( $site->blog_id );
				
				foreach ( $this->providers as $embed ) {
					$this->add_embed( $embed );
				}
			}
			
			restore_current_blog();
		}
		else {
			foreach ( $this->providers as $embed ) {
				$this->add_embed( $embed );
			}
		}
	}
	
	/**
	 * Migrations for version 1.2.1.
	 * 
	 * @since	1.2.1
	 * 
	 * - Add missing meta data
	 */
	private function migrate_1_2_1() {
		global $wp_filesystem;
		
		// initialize the WP filesystem if not exists
		if ( empty( $wp_filesystem ) ) {
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
			WP_Filesystem();
		}
		
		$available_providers = get_posts( [
			'numberposts' => -1,
			'post_type' => 'epi_embed',
		] );
		
		foreach ( $available_providers as $provider ) {
			// get according default provider
			$key = array_search( $provider->post_title, array_column( $this->providers, 'post_title' ) );
			
			// if no default provider, continue with next available provider
			if ( $key === false ) {
				continue;
			}
			
			// update default meta data, if missing
			foreach ( [ 'is_system', 'privacy_policy_url', 'regex_default' ] as $meta_key ) {
				if ( ! get_post_meta( $provider->ID, $meta_key, true ) ) {
					update_post_meta( $provider->ID, $meta_key, $this->providers[ $key ]['meta_input'][ $meta_key ] );
				}
			}
		}
	}
	
	/**
	 * Migrations for version 1.2.2.
	 * 
	 * @since	1.2.2
	 * 
	 * - Update regex for Amazon Kindle
	 */
	private function migrate_1_2_2() {
		$amazon_provider = get_posts( [
			'meta_key' => 'is_system',
			'meta_value' => 'yes',
			'name' => 'amazon-kindle',
			'post_type' => 'epi_embed',
		] );
		
		if ( ! empty( $amazon_provider ) ) {
			$amazon_provider = reset( $amazon_provider );
		}
		
		if ( ! $amazon_provider instanceof WP_Post ) {
			return;
		}
		
		update_post_meta( $amazon_provider->ID, 'regex_default', '/\\\.?(ama?zo?n\\\.|a\\\.co\\\/|z\\\.cn\\\/)/' );
	}
	
	/**
	 * Migrations for version 1.3.0.
	 * 
	 * @since	1.3.0
	 * 
	 * - Update regex for Amazon Kindle
	 */
	private function migrate_1_3_0() {
		$providers = Embed_Privacy::get_instance()->get_embeds( 'oembed' );
		$google_provider = (array) get_posts( [
			'meta_key' => 'is_system',
			'meta_value' => 'yes',
			'name' => 'google-maps',
			'post_type' => 'epi_embed',
		] );
		$providers = array_merge( $providers, $google_provider );
		
		foreach ( $providers as $provider ) {
			if ( ! $provider instanceof WP_Post ) {
				continue;
			}
			
			// delete post thumbnails
			// see https://github.com/epiphyt/embed-privacy/issues/32
			$thumbnail_id = get_post_thumbnail_id( $provider->ID );
			
			if ( $thumbnail_id ) {
				delete_post_thumbnail( $provider );
				wp_delete_attachment( $thumbnail_id, true );
			}
			
			// make regex ungreedy
			// see https://github.com/epiphyt/embed-privacy/issues/31
			if ( $provider->post_name === 'google-maps' ) {
				update_post_meta( $provider->ID, 'regex_default', '/google\\\.com\\\/maps\\\/embed/' );
			}
		}
	}
	
	/**
	 * Register default embed providers.
	 */
	public function register_default_embed_providers() {
		$this->providers = [
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://www.amazon.com/gp/help/customer/display.html?nodeId=GX7NJQ4ZB8MHFRNJ', 'embed-privacy' ),
					'regex_default' => '/\\\.?(ama?zo?n\\\.|a\\\.co\\\/|z\\\.cn\\\/)/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Amazon Kindle', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Amazon Kindle', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://animoto.com/legal/privacy_policy', 'embed-privacy' ),
					'regex_default' => '/animoto\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Animoto', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Animoto', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://automattic.com/privacy/', 'embed-privacy' ),
					'regex_default' => '/cloudup\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Cloudup', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Cloudup', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://automattic.com/privacy/', 'embed-privacy' ),
					'regex_default' => '/((poll(\\\.fm|daddy\\\.com))|croudsignal\\\.com|survey\\\.fm)/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Crowdsignal', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Crowdsignal', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://www.dailymotion.com/legal/privacy?localization=en', 'embed-privacy' ),
					'regex_default' => '/dailymotion\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'DailyMotion', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'DailyMotion', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://www.facebook.com/privacy/explanation', 'embed-privacy' ),
					'regex_default' => '/facebook\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Facebook', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Facebook', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://www.flickr.com/help/privacy', 'embed-privacy' ),
					'regex_default' => '/flickr\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Flickr', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Flickr', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://www.funnyordie.com/legal/privacy-notice', 'embed-privacy' ),
					'regex_default' => '/funnyordie\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Funny Or Die', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Funny Or Die', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://policies.google.com/privacy?hl=en', 'embed-privacy' ),
					'regex_default' => '/google\\\.com\\\/maps\\\/embed/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Google Maps', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Google Maps', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://imgur.com/privacy', 'embed-privacy' ),
					'regex_default' => '/imgur\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Imgur', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Imgur', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://www.instagram.com/legal/privacy/', 'embed-privacy' ),
					'regex_default' => '/instagram\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Instagram', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Instagram', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://issuu.com/legal/privacy', 'embed-privacy' ),
					'regex_default' => '/issuu\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Issuu', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Issuu', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://www.kickstarter.com/privacy', 'embed-privacy' ),
					'regex_default' => '/kickstarter\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Kickstarter', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Kickstarter', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://www.meetup.com/privacy/', 'embed-privacy' ),
					'regex_default' => '/meetup\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Meetup', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Meetup', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://www.mixcloud.com/privacy/', 'embed-privacy' ),
					'regex_default' => '/mixcloud\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Mixcloud', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Mixcloud', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://app.photobucket.com/privacy', 'embed-privacy' ),
					'regex_default' => '/photobucket\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Photobucket', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Photobucket', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://www.reddit.com/help/privacypolicy', 'embed-privacy' ),
					'regex_default' => '/reddit\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Reddit', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Reddit', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://www.reverbnation.com/privacy', 'embed-privacy' ),
					'regex_default' => '/reverbnation\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'ReverbNation', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'ReverbNation', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://scribd.com/privacy', 'embed-privacy' ),
					'regex_default' => '/scribd\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Scribd', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Scribd', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://sketchfab.com/privacy', 'embed-privacy' ),
					'regex_default' => '/sketchfab\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Sketchfab', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Sketchfab', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://www.slideshare.net/privacy', 'embed-privacy' ),
					'regex_default' => '/slideshare\\\.net/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'SlideShare', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'SlideShare', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://www.smugmug.com/about/privacy', 'embed-privacy' ),
					'regex_default' => '/smugmug\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'SmugMug', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'SmugMug', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://soundcloud.com/pages/privacy', 'embed-privacy' ),
					'regex_default' => '/soundcloud\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'SoundCloud', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'SoundCloud', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://speakerdeck.com/privacy', 'embed-privacy' ),
					'regex_default' => '/speakerdeck\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Speaker Deck', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Speaker Deck', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://www.spotify.com/privacy/', 'embed-privacy' ),
					'regex_default' => '/spotify\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Spotify', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Spotify', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://www.tiktok.com/legal/privacy-policy?lang=en-US', 'embed-privacy' ),
					'regex_default' => '/tiktok\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'TikTok', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'TikTok', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://www.ted.com/about/our-organization/our-policies-terms/privacy-policy', 'embed-privacy' ),
					'regex_default' => '/ted\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'TED', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'TED', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://www.tumblr.com/privacy_policy', 'embed-privacy' ),
					'regex_default' => '/tumblr\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Tumblr', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Tumblr', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://twitter.com/privacy', 'embed-privacy' ),
					'regex_default' => '/twitter\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Twitter', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Twitter', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://automattic.com/privacy/', 'embed-privacy' ),
					'regex_default' => '/videopress\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'VideoPress', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'VideoPress', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://vimeo.com/privacy', 'embed-privacy' ),
					'regex_default' => '/vimeo\\\.com/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'Vimeo', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'Vimeo', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://wordpress.org/about/privacy/', 'embed-privacy' ),
					'regex_default' => '/wordpress\\\.org\\\/plugins/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'WordPress.org', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'WordPress.org', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://wordpress.org/about/privacy/', 'embed-privacy' ),
					'regex_default' => '/wordpress\\\.tv\/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'WordPress.tv', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'WordPress.tv', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
			[
				'meta_input' => [
					'is_system' => 'yes',
					'privacy_policy_url' => __( 'https://policies.google.com/privacy?hl=en', 'embed-privacy' ),
					'regex_default' => '/https?:\\\/\\\/(?:.+?.)?youtu(?:.be|be.com)/',
				],
				'post_content' => sprintf( __( 'Click here to display content from %s.', 'embed-privacy' ), _x( 'YouTube', 'embed provider', 'embed-privacy' ) ),
				'post_status' => 'publish',
				'post_title' => _x( 'YouTube', 'embed provider', 'embed-privacy' ),
				'post_type' => 'epi_embed',
			],
		];
	}
	
	/**
	 * Update an option either from the global multisite settings or the regular site.
	 * 
	 * @param	string	$option The option name
	 * @param	mixed	$value The value to update
	 * @param	bool	$network Whether to force update a site option or not
	 * @return	mixed Value set for the option
	 */
	private function update_option( $option, $value, $network = false ) {
		if ( is_multisite() && ( $network || is_plugin_active_for_network( Embed_Privacy::get_instance()->plugin_file ) ) ) {
			return update_site_option( 'embed_privacy_' . $option, $value );
		}
		
		return update_option( 'embed_privacy_' . $option, $value );
	}
}
