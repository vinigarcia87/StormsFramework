<?php
/**
 * Storms Framework (http://storms.com.br/)
 *
 * @author    Vinicius Garcia | vinicius.garcia@storms.com.br
 * @copyright (c) Copyright 2012-2019, Storms Websolutions
 * @license   GPLv2 - GNU General Public License v2 or later (http://www.gnu.org/licenses/gpl-2.0.html)
 * @package   Storms
 * @version   4.0.0
 *
 * Assets class
 * @package StormsFramework
 *
 * Styles and scripts control class
 * @see  _documentation/Assets_Class.md
 */

namespace StormsFramework;

use StormsFramework\Base;

class Assets extends Base\Runner
{
	public function __construct() {
		parent::__construct( __CLASS__, STORMS_FRAMEWORK_VERSION, $this );
	}

	private $jquery_version = '3.4.1';

	public function define_hooks() {

		$this->loader
			->add_filter( 'stylesheet_uri', 'stylesheet_uri', 10, 2 )
			->add_action( 'wp_enqueue_scripts', 'enqueue_main_style', 10 )
			->add_action( 'wp_enqueue_scripts', 'remove_unused_styles', 10 );

		$this->loader
			->add_action( 'wp_enqueue_scripts', 'jquery_scripts' )
			->add_filter( 'script_loader_src', 'jquery_local_fallback', 1, 2 )
			->add_filter( 'script_loader_src', 'jquery_fix_passive_listeners', 2, 2 )
			->add_filter( 'script_loader_tag', 'preload_jquery', 10, 3 );

		$this->loader
			->add_action( 'wp_enqueue_scripts', 'remove_unused_scripts' )
			->add_action( 'wp_enqueue_scripts', 'frontend_scripts' )
			->add_action( 'wp_enqueue_scripts', 'remove_gutenberg_scripts_and_styles', 999 );

	}

	//<editor-fold desc="Scripts and Styles">

	/**
	 * Custom stylesheet URI
	 * get_stylesheet_uri() return assets/css/style.min.css
	 *
	 * @param $stylesheet
	 * @param $stylesheet_dir
	 * @return bool|string
	 */
	public function stylesheet_uri( $stylesheet, $stylesheet_dir ) {
		return Helper::get_asset_url( '/css/style' . ( ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min' ) . '.css' );
	}

	/**
	 * Register and load main theme stylesheet
	 */
	public function enqueue_main_style() {
		// Default Theme Style
		wp_enqueue_style( 'main-style-theme', get_stylesheet_uri(), array(), STORMS_FRAMEWORK_VERSION, 'all' );
	}

	/**
	 * We remove some well-know plugin's styles, so you can add them manually only on the pages you need
	 * Styles that we remove are: contact-form-7, newsletter-subscription, newsletter_enqueue_style
	 */
	public function remove_unused_styles() {
		//wp_deregister_style( 'contact-form-7' );
		wp_deregister_style( 'newsletter-subscription' );
		add_filter( 'newsletter_enqueue_style', '__return_false' );
	}

	/**
	 * Enqueue jQuery scripts
	 */
	public function jquery_scripts() {
		// http://jquery.com/
		wp_deregister_script( 'jquery' ); // Remove o jquery padrao do wordpress
		if( Helper::get_option( 'storms_load_jquery', 'yes' ) ) {

			// Decide se carrega jquery externo ou interno
			if( !is_admin() && 'yes' == Helper::get_option( 'storms_load_external_jquery', 'no' ) ) {
				wp_register_script('jquery', 'https://ajax.googleapis.com/ajax/libs/jquery/' . $this->jquery_version . '/jquery.min.js', false, $this->jquery_version, false);
			}
			wp_register_script('jquery', Helper::get_asset_url( '/js/jquery/' . $this->jquery_version . '/jquery.min.js' ), false, $this->jquery_version, false);

			wp_enqueue_script('jquery');
		}
	}

	/**
	 * Output the local fallback immediately after jQuery's <script>
	 * Only if external jquery is been used
	 * @link http://wordpress.stackexchange.com/a/12450
	 */
	public function jquery_local_fallback( $src, $handle = null ) {

		if( is_admin() ) {
			return $src;
		}

		static $jquery_local_fallback_after_jquery = false;

		if( $jquery_local_fallback_after_jquery && 'yes' == Helper::get_option( 'storms_load_external_jquery', 'no' ) ) {
			// Defaults to match the version loaded via CDN
			$local_jquery = Helper::get_asset_url( '/js/jquery/jquery.min.js' );

			?>
			<script>window.jQuery || document.write('<script  rel="preload" src="<?php echo esc_url( $local_jquery ); ?>"><\/script>')</script>
			<?php

			$jquery_local_fallback_after_jquery = false;
		}

		if( $handle === 'jquery' ) {
			$jquery_local_fallback_after_jquery = true;
		}

		return $src;
	}

	/**
	 * Lighthouse Report flagged: Does not use passive listeners to improve scrolling performance
	 * Issue occurs on jquery
	 * Output the fix immediately after jQuery's <script>
	 * @see https://stackoverflow.com/a/65717663/1003020
	 */
	public function jquery_fix_passive_listeners( $src, $handle = null ) {
		if( is_admin() ) {
			return $src;
		}

		static $jquery_fix_passive_listeners_after_jquery = false;

		if( $jquery_fix_passive_listeners_after_jquery ) {
			?>
			<script>
				// Passive event listeners
				jQuery.event.special.touchstart = {
					setup: function (_, ns, handle) {
						this.addEventListener("touchstart", handle, {passive: !ns.includes("noPreventDefault")});
					}
				};
				jQuery.event.special.touchmove = {
					setup: function (_, ns, handle) {
						this.addEventListener("touchmove", handle, {passive: !ns.includes("noPreventDefault")});
					}
				};
			</script>
			<?php
			$jquery_fix_passive_listeners_after_jquery = false;
		}

		if( 'jquery' === $handle ) {
			$jquery_fix_passive_listeners_after_jquery = true;
		}

		return $src;
	}

	/**
	 * Add rel="preload" to jQuery
	 *
	 * @param $tag
	 * @param $handle
	 * @param $src
	 * @return mixed
	 */
	function preload_jquery( $tag, $handle, $src ) {

		if ( is_admin() ) {
			return $tag;
		}

		if( 'jquery' === $handle ) {
			return str_replace( '<script', '<script rel="preload"', $tag );
		}

		return $tag;
	}

	/**
	 * We remove some well-know plugin's scripts, so you can add them manually only on the pages you need
	 * Scripts that we remove are: jquery-form, contact-form-7, newsletter-subscription, wp-embed
	 */
	public function remove_unused_scripts() {
		// We remove some know plugin's scripts, so you can add them only on the pages you need
		wp_deregister_script( 'jquery-form' );
		//wp_deregister_script('contact-form-7');
		wp_deregister_script( 'newsletter-subscription' );
		wp_deregister_script( 'wp-embed' ); // https://codex.wordpress.org/Embeds
	}

	/**
	 * Register main theme script
	 * Adjust Thread comments WordPress script to load only on specific pages
	 */
	public function frontend_scripts() {
		// Load Thread comments WordPress script
		if ( is_singular() && comments_open() && Helper::get_option( 'storms_thread_comments' ) ) {
			wp_enqueue_script( 'comment-reply' );
		}
	}

	// DEQUEUE GUTENBERG STYLES FOR FRONT

	/**
	 * Dequeue Gutenberg styles for front
	 */
	function remove_gutenberg_scripts_and_styles() {

		wp_dequeue_script('wp-util');
		wp_dequeue_script('underscore');

		wp_dequeue_style('wp-block-library');
		wp_dequeue_style('wc-block-style');
		wp_dequeue_style('wp-block-library-theme');
	}

	//</editor-fold>

}
