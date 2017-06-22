<?php
/**
 * Site Edit Screen
 *
 * The functionality on the add/edit screen of a Syndication Endpoint.
 *
 * @since 2.1
 * @package Automattic\Syndication\Admin
 */

namespace Automattic\Syndication\Admin;

use Automattic\Syndication\Client_Manager;

/**
 * Class Site_Edit_Screen
 *
 * @since 2.1
 * @package Automattic\Syndication\Admin
 */
class Site_Edit_Screen {

	protected $_client_manager;

	/**
	 * Site_Edit_Screen constructor.
	 *
	 * @param Client_Manager $client_manager Client manager object.
	 */
	public function __construct( Client_Manager $client_manager ) {
		$this->_client_manager = $client_manager;

		add_action( 'admin_enqueue_scripts', array( $this, 'load_scripts_and_styles' ) );
		add_action( 'add_meta_boxes_syn_site', array( $this, 'site_metaboxes' ) );
		add_action( 'save_post', array( $this, 'save_site_settings' ) );
	}

	/**
	 * Load Scripts and Styles
	 *
	 * Enqueues CSS and JS to the Syndication Endpoint edit screen.
	 *
	 * @since 2.1
	 * @see admin_enqueue_scripts
	 * @param string $hook Current admin page.
	 * @return void
	 */
	public function load_scripts_and_styles( $hook ) {
		global $typenow;

		if ( 'syn_site' === $typenow ) {
			if ( 'post.php' === $hook || 'post-new.php' === $hook ) {
				wp_enqueue_style( 'syn-edit-sites', SYNDICATION_URL . 'assets/css/admin-sites-list.css', array(), SYNDICATION_VERSION );
				//@todo verify below working, maybe need `wp_deregister_script` here
				wp_dequeue_script( 'autosave' );
			}
		}
	}

	/**
	 * Site Metaboxes
	 *
	 * Adds and removes metaboxes from the Syndication Endpoint edit screen.
	 *
	 * @return void
	 */
	public function site_metaboxes() {
		add_meta_box( 'sitediv', __( ' Syndication Endpoint Settings ' ), array( $this, 'add_site_settings_metabox' ), 'syn_site', 'normal', 'high' );
		remove_meta_box( 'submitdiv', 'syn_site', 'side' );
		add_meta_box( 'submitdiv', __( ' Syndication Endpoint Status ' ), array( $this, 'add_site_status_metabox' ), 'syn_site', 'side', 'high' );
	}

	/**
	 * Add Site Status Metabox
	 *
	 * Adds a metabox which displays the status of the Syndication Endpoint. Also
	 * provides quick links to additional functionality.
	 *
	 * @see site_metaboxes()
	 * @param \WP_Post $site Object of the current Syndication Endpoint being viewed.
	 * @return void
	 */
	public function add_site_status_metabox( $site ) {
		$site_enabled = get_post_meta( $site->ID, 'syn_site_enabled', true );
		?>
		<div class="submitbox" id="submitpost">
			<div id="minor-publishing">
				<div id="misc-publishing-actions">
					<div class="misc-pub-section">
						<label for="post_status"><?php esc_html_e( 'Status:', 'push-syndication' ) ?></label>
						<span id="post-status-display">
						<?php
						switch ( $site_enabled ) {
							case 'on':
								esc_html_e( 'Enabled', 'push-syndication' );
								break;
							case 'off':
							default:
								esc_html_e( 'Disabled', 'push-syndication' );
								break;
						}
						?>
						</span>

						<a href="#post_status" class="edit-post-status hide-if-no-js" tabindex='4'><?php esc_html_e( 'Edit', 'push-syndication' ) ?></a>

						<div id="post-status-select" class="hide-if-js">
							<input type="hidden" name="post_status" value="publish" />
							<select name='site_enabled' id='post_status' tabindex='4'>
								<option<?php selected( $site_enabled, 'on' ); ?> value='on'><?php esc_html_e( 'Enabled', 'push-syndication' ) ?></option>
								<option<?php selected( $site_enabled, 'off' ); ?> value='off'><?php esc_html_e( 'Disabled', 'push-syndication' ) ?></option>
							</select>
							<a href="#post_status" class="save-post-status hide-if-no-js button"><?php esc_html_e( 'OK', 'push-syndication' ); ?></a>
						</div>

					</div>

					<div id="timestampdiv" class="hide-if-js"><?php touch_time( 0, 1, 4 ); ?></div>
				</div>
				<div class="clear"></div>
			</div>

			<div id="major-publishing-actions">

				<div id="delete-action">
					<a class="submitdelete deletion" href="<?php echo get_delete_post_link( $site->ID ); ?>"><?php esc_html_e( 'Move to Trash', 'push-syndication' ); ?></a>
				</div>

				<div id="publishing-action">
					<img src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) ); ?>" class="ajax-loading" id="ajax-loading" alt="" />
					<?php
					if ( ! in_array( $site_enabled, array( 'on', 'off' ), true ) || 0 === $site->ID ) { ?>
						<input name="original_publish" type="hidden" id="original_publish" value="<?php esc_attr_e( 'Add Syndication Endpoint', 'push-syndication' ) ?>" />
						<?php submit_button( __( 'Add Syndication Endpoint', 'push-syndication' ), 'primary', 'enabled', false, array( 'tabindex' => '5', 'accesskey' => 'p' ) ); ?>
					<?php } else { ?>
						<input name="original_publish" type="hidden" id="original_publish" value="<?php esc_attr_e( 'Update', 'push-syndication' ) ?>" />
						<input name="save" type="submit" class="button-primary" id="publish" tabindex="5" accesskey="p" value="<?php esc_attr_e( 'Update', 'push-syndication' ) ?>" />
					<?php } ?>
				</div>

				<div class="clear"></div>
			</div>
		</div>

	<?php
	}

	/**
	 * Add Site Settings Metabox
	 *
	 * Adds a settings metabox to the Syndication Endpoint edit screen.
	 *
	 * @see site_metaboxes()
	 * @param \WP_Post $post Post object of the current post being viewed.
	 * @return void
	 */
	public function add_site_settings_metabox( $post ) {
		global $post;

		$transport_type = get_post_meta( $post->ID, 'syn_transport_type', true );
		$site_enabled   = get_post_meta( $post->ID, 'syn_site_enabled', true );

		// Default values.
		$site_enabled   = ! empty( $site_enabled ) ? $site_enabled : 'off';

		// Nonce for verification when saving.
		wp_nonce_field( plugin_basename( __FILE__ ), 'site_settings_noncename' );

		$this->display_transports( $transport_type );

		if ( $transport_type ) {
			/**
			 * Fires when rendering the Syndication settings metabox.
			 *
			 * @param string     $transport_type The client transport type.
			 * @param int        $post_id        The post_id of the Syndication Endpoint being rendered.
			 */
			do_action( 'syndication/render_site_options/' . $transport_type, $post->ID );
		} else {
			echo '<p>' . esc_html__( 'No client configured for this Syndication Endpoint.', 'push-syndication' ) . '</p>';
		}
		?>
		<div class="clear"></div>
		<?php
	}

	public function display_transports( $transport_type ) {

		echo '<p>' . esc_html__( 'Select a transport type', 'push-syndication' ) . '</p>';
		echo '<form action="">';
		// TODO: add direction
		echo '<select name="transport_type" onchange="this.form.submit()">';

		$values = array();
		$max_len = 0;

		echo '<option value=""></option>';
		foreach ( $this->_client_manager->get_clients() as $key => $options ) {
			echo '<option value="' . esc_html( $key ) . '"' . selected( $key, $transport_type ) . '>' . sprintf( esc_html__( '%s' ), $options['label'] ) . '</option>';
		}
		echo '</select>';
		echo '</form>';

	}

	public function save_site_settings() {

		global $post;

		// autosave verification
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return;

		// if our nonce isn't there, or we can't verify it return
		if ( ! isset( $_POST['site_settings_noncename'] ) || ! wp_verify_nonce( $_POST['site_settings_noncename'], plugin_basename( __FILE__ ) ) )
			return;

		$transport_type = sanitize_text_field( $_POST['transport_type'] ); // TODO: validate this exists

		// @TODO validate that type and mode are valid
		update_post_meta( $post->ID, 'syn_transport_type', $transport_type );

		$site_enabled = sanitize_text_field( $_POST['site_enabled'] );
		update_post_meta( $post->ID, 'syn_site_enabled', $site_enabled );

		/**
		 * Fires after saving syndication site options.
		 *
		 * Clients hook into this event to save any options.
		 *
		 * @param string     $transport_type The client transport type.
		 * @param int        $post_id        The post_id of the site being rendered.
		 */
		do_action( 'syndication/save_site_options/' . $transport_type, $post->ID );

		/**
		 * Fires after saving syndication site options.
		 *
		 * Trigger the client test action to test the connection after saving.
		 *
		 * @param string     $transport_type The client transport type.
		 * @param int        $post_id        The post_id of the site being rendered.
		 */
		do_action( 'syndication/test_site_options/' . $transport_type, $post->ID );
	}
}
