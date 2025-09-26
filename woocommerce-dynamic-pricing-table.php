<?php
/**
 * Plugin Name:       WooCommerce Dynamic Pricing Table
 * Plugin URI:        https://github.com/lucasstark/woocommerce-dynamic-pricing-table
 * Description:       Displays a pricing discount table on WooCommerce products when using the WooCommerce Dynamic Pricing plugin.
 * Version:           2.0.0
 * Author:            Lucas Stark
 * Author URI:        https://elementstark.com
 * Requires at least: 5.0
 * Tested up to:      6.4
 * WC requires at least: 4.0.0
 * WC tested up to: 8.0
 * Text Domain: wc-dynamic-pricing-table
 * Domain Path: /languages/
 *
 * @package WC_Dynamic_Pricing_Table
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// HPOS compatibility
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

/**
 * Returns the main instance of WC_Dynamic_Pricing_Table
 *
 * @return  object WC_Dynamic_Pricing_Table
 * @since   2.0.0
 */
function WC_Dynamic_Pricing_Table() {
	return WC_Dynamic_Pricing_Table::instance();
}

WC_Dynamic_Pricing_Table();

/**
 * Main WC_Dynamic_Pricing_Table Class
 *
 * @class     WC_Dynamic_Pricing_Table
 * @version   2.0.0
 * @since     1.0.0
 * @package   WC_Dynamic_Pricing_Table
 */
final class WC_Dynamic_Pricing_Table {

	/**
	 * The single instance of WC_Dynamic_Pricing_Table.
	 *
	 * @var     object
	 * @access  private
	 * @since   1.0.0
	 */
	private static $_instance = null;

	/**
	 * The token.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $token;

	/**
	 * The version number.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $version;

	/**
	 * The plugin url
	 *
	 * @var string
	 * @access public
	 * @since  2.0.0
	 */
	public $plugin_url;

	/**
	 * The plugin path
	 *
	 * @var string
	 * @access public
	 * @since  2.0.0
	 */
	public $plugin_path;

	/**
	 * Constructor function.
	 *
	 * @access  public
	 * @return  void
	 * @since   1.0.0
	 */
	public function __construct() {
		$this->token       = 'wc-dynamic-pricing-table';
		$this->plugin_url  = plugin_dir_url( __FILE__ );
		$this->plugin_path = plugin_dir_path( __FILE__ );
		$this->version     = '2.0.0';

		register_activation_hook( __FILE__, array( $this, 'install' ) );

		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );
		add_action( 'init', array( $this, 'plugin_setup' ) );
	}

	/**
	 * Main WC_Dynamic_Pricing_Table Instance
	 *
	 * @return  WC_Dynamic_Pricing_Table instance
	 * @since   1.0.0
	 * @static
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Load the localisation file.
	 *
	 * @access  public
	 * @return  void
	 * @since   1.0.0
	 */
	public function load_plugin_textdomain() {
		$locale = determine_locale();

		// Try to load specific locale first
		$mofile = $this->plugin_path . 'languages/' . $this->token . '-' . $locale . '.mo';
		if ( file_exists( $mofile ) ) {
			load_textdomain( $this->token, $mofile );
		} else {
			// Fallback to default
			load_plugin_textdomain( $this->token, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		}
	}

	/**
	 * Installation.
	 *
	 * @access  public
	 * @return  void
	 * @since   1.0.0
	 */
	public function install() {
		update_option( $this->token . '-version', $this->version );
	}

	/**
	 * Setup all the things.
	 *
	 * @return void
	 */
	public function plugin_setup() {
		if ( ! class_exists( 'WC_Dynamic_Pricing' ) ) {
			add_action( 'admin_notices', array( $this, 'install_wc_dynamic_pricing_notice' ) );
			return;
		}

		// Load additional classes
		$this->load_classes();

		// Initialize components
		if ( class_exists( 'WC_Dynamic_Pricing_Table_Blocksy_Integration' ) ) {
			new WC_Dynamic_Pricing_Table_Blocksy_Integration();
		}
	}

	/**
	 * Load additional classes
	 *
	 * @return void
	 */
	private function load_classes() {
		require_once $this->plugin_path . 'includes/class-pricing-table.php';
		require_once $this->plugin_path . 'includes/class-blocksy-integration.php';
	}

	/**
	 * WooCommerce Dynamic Pricing plugin install notice.
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function install_wc_dynamic_pricing_notice() {
		echo '<div class="notice is-dismissible updated">
			<p>' . esc_html__( 'The WooCommerce Dynamic Pricing Table extension requires that you have the WooCommerce Dynamic Pricing plugin installed and activated.', 'wc-dynamic-pricing-table' ) .
			' <a href="https://www.woocommerce.com/products/dynamic-pricing/">' .
			esc_html__( 'Get WooCommerce Dynamic Pricing now', 'wc-dynamic-pricing-table' ) . '</a></p>
		</div>';
	}

	/**
	 * Get the plugin path
	 *
	 * @return string
	 */
	public function plugin_path() {
		return $this->plugin_path;
	}

	/**
	 * Get the plugin URL
	 *
	 * @return string
	 */
	public function plugin_url() {
		return $this->plugin_url;
	}
}