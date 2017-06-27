<?php
/**
 * Settings Screen
 *
 * Responsible for plugin-level settings screens.
 */

namespace Automattic\Syndication\Admin;

use \Automattic\Syndication\Syndication_Runner;

class Settings_Screen {


	public function __construct() {

		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_menu', array( $this, 'register_syndicate_settings' ) );
	}

	public function admin_init() {

		register_setting( 'push_syndicate_settings', 'push_syndicate_settings', array( $this, 'push_syndicate_settings_validate' ) );
		register_setting( 'push_syndicate_settings', 'push_syndication_max_pull_attempts', array( $this, 'validate_max_pull_attempts' ) );
	}

	/**
	 * Push Syndicate Settings Validate
	 *
	 * Validate the push syndication settings.
	 *
	 * @param $raw_settings array Settings to validate.
	 * @return array              Validated settings.
	 */
	public function push_syndicate_settings_validate( $raw_settings ) {
		if ( isset( $_POST['push_syndicate_pull_now'] ) && 'Pull Now & Save Changes' === $_POST['push_syndicate_pull_now'] ) {
			Syndication_Runner::pull_now_job();
		}

		$settings                                       = array();
		$settings['client_id']                          = ! empty( $raw_settings['client_id'] ) ? sanitize_text_field( $raw_settings['client_id'] ) : '';
		$settings['client_secret']                      = ! empty( $raw_settings['client_secret'] ) ? sanitize_text_field( $raw_settings['client_secret'] ) : '';
		$settings['selected_post_types']                = ! empty( $raw_settings['selected_post_types'] ) ? $this->sanitize_array( $raw_settings['selected_post_types'] ) : array() ;
		$settings['notification_methods']               = ! empty( $raw_settings['notification_methods'] ) ? $this->sanitize_array( $raw_settings['notification_methods'] ) : array();
		$settings['notification_types']                 = ! empty( $raw_settings['notification_types'] ) ? $this->sanitize_array( $raw_settings['notification_types'] ) : array();
		$settings['notification_email']                 = ! empty( $raw_settings['notification_email'] ) ? sanitize_email( $raw_settings['notification_email'] ) : '';
		$settings['notification_slack_webhook']         = ! empty( $raw_settings['notification_slack_webhook'] ) ? esc_url_raw( $raw_settings['notification_slack_webhook'] ) : '';
		$settings['delete_pushed_posts']                = ! empty( $raw_settings['delete_pushed_posts'] ) ? sanitize_text_field( $raw_settings['delete_pushed_posts'] ) : 'off' ;
		$settings['selected_pull_sitegroups']           = ! empty( $raw_settings['selected_pull_sitegroups'] ) ? $this->sanitize_array( $raw_settings['selected_pull_sitegroups'] ) : array() ;
		$settings['pull_time_interval']                 = ! empty( $raw_settings['pull_time_interval'] ) ? intval( max( $raw_settings['pull_time_interval'] ), 300 ) : '3600';
		$settings['update_pulled_posts']                = ! empty( $raw_settings['update_pulled_posts'] ) ? sanitize_text_field( $raw_settings['update_pulled_posts'] ) : 'off' ;
		$settings['push_syndication_max_pull_attempts'] = ! empty( $raw_settings['push_syndication_max_pull_attempts'] ) ? intval( $raw_settings['push_syndication_max_pull_attempts'] ) : 0 ;

		Syndication_Runner::refresh_pull_jobs();
		return $settings;

	}

	/**
	 * Sanitize Array
	 *
	 * Takes an array of raw data and runs through each element, sanitizing the
	 * raw data on the way.
	 *
	 * @since 2.1
	 * @param mixed $data The data to be sanitized.
	 * @return array|string The sanitized datsa.
	 */
	public function sanitize_array( $data ) {
		if ( ! is_array( $data ) ) {
			return sanitize_text_field( $data );
		} else {
			foreach ( $data as $key => $item ) {
				if ( is_array( $item ) ) {
					$data[ $key ] = $this->sanitize_array( $item );
				} else {
					$data[ $key ] = sanitize_text_field( $item );
				}
				return $data;
			}
		}
	}

	public function register_syndicate_settings() {

		add_submenu_page(
			'options-general.php',
			esc_html__( 'Syndication Settings', 'push-syndication' ),
			esc_html__( 'Syndication', 'push-syndication' ),
			/* This filter is documented in includes/admin/class-settings-screen.php */
			apply_filters( 'syn_syndicate_cap', 'manage_options' ),
			'push-syndicate-settings',
			array( $this, 'display_syndicate_settings' )
		);
	}

	public function display_syndicate_settings() {

		// @todo all validation and sanitization should be moved to a separate object.
		add_settings_section( 'push_syndicate_pull_sitegroups', esc_html__( 'Site Groups' , 'push-syndication' ), array( $this, 'display_pull_sitegroups_description' ), 'push_syndicate_pull_sitegroups' );
		add_settings_field( 'pull_sitegroups_selection', esc_html__( 'select sitegroups', 'push-syndication' ), array( $this, 'display_pull_sitegroups_selection' ), 'push_syndicate_pull_sitegroups', 'push_syndicate_pull_sitegroups' );

		add_settings_section( 'push_syndicate_pull_options', esc_html__( 'Pull Options' , 'push-syndication' ), array( $this, 'display_pull_options_description' ), 'push_syndicate_pull_options' );
		add_settings_field( 'pull_time_interval', esc_html__( 'Specify time interval in seconds', 'push-syndication' ), array( $this, 'display_time_interval_selection' ), 'push_syndicate_pull_options', 'push_syndicate_pull_options' );
		add_settings_field( 'max_pull_attempts', esc_html__( 'Maximum pull attempts', 'push-syndication' ), array( $this, 'display_max_pull_attempts' ), 'push_syndicate_pull_options', 'push_syndicate_pull_options' );
		add_settings_field( 'update_pulled_posts', esc_html__( 'update pulled posts', 'push-syndication' ), array( $this, 'display_update_pulled_posts_selection' ), 'push_syndicate_pull_options', 'push_syndicate_pull_options' );

		add_settings_section( 'push_syndicate_post_types', esc_html__( 'Post Types' , 'push-syndication' ), array( $this, 'display_push_post_types_description' ), 'push_syndicate_post_types' );
		add_settings_field( 'post_type_selection', esc_html__( 'select post types', 'push-syndication' ), array( $this, 'display_post_types_selection' ), 'push_syndicate_post_types', 'push_syndicate_post_types' );

		// Delete Pushed Posts section.
		add_settings_section(
			'delete_pushed_posts',
			esc_html__( ' Delete Pushed Posts ', 'push-syndication' ),
			array( $this, 'display_delete_pushed_posts_description' ),
			'delete_pushed_posts'
		);

		add_settings_field(
			'delete_post_check',
			esc_html__( ' delete pushed posts ', 'push-syndication' ),
			array( $this, 'display_delete_pushed_posts_selection' ),
			'delete_pushed_posts',
			'delete_pushed_posts'
		);

		// Notifications section.
		add_settings_section(
			'notifications',
			esc_html__( 'Notifications', 'push-syndication' ),
			array( $this, 'display_notifications_description' ),
			'notifications'
		);

		add_settings_field(
			'notification_methods',
			esc_html__( 'Enable notifications', 'push-syndication' ),
			array( $this, 'display_notification_method_selection' ),
			'notifications',
			'notifications'
		);

		add_settings_field(
			'notification_email',
			esc_html__( 'Notification email', 'push-syndication' ),
			array( $this, 'display_notification_email' ),
			'notifications',
			'notifications'
		);

		add_settings_field(
			'notification_slack_webhook',
			esc_html__( 'Slack webhook URL', 'push-syndication' ),
			array( $this, 'display_notification_slack_webhook' ),
			'notifications',
			'notifications'
		);

		add_settings_field(
			'notification_types',
			esc_html__( 'Send notification on', 'push-syndication' ),
			array( $this, 'display_notification_type_selection' ),
			'notifications',
			'notifications'
		);
		?>

		<div class="wrap" xmlns="http://www.w3.org/1999/html">

			<?php screen_icon(); // @TODO custom screen icon ?>

			<h2><?php esc_html_e( 'Syndication Settings', 'push-syndication' ); ?></h2>

			<form action="options.php" method="post">

				<?php settings_fields( 'push_syndicate_settings' ); ?>

				<?php do_settings_sections( 'push_syndicate_pull_sitegroups' ); ?>

				<?php do_settings_sections( 'push_syndicate_pull_options' ); ?>

				<?php submit_button( 'Pull Now & Save Changes', 'primary', 'push_syndicate_pull_now' ); ?>

				<?php do_settings_sections( 'push_syndicate_post_types' ); ?>

				<?php do_settings_sections( 'delete_pushed_posts' ); ?>

				<?php do_settings_sections( 'notifications' ); ?>

				<?php submit_button(); ?>

			</form>

		</div>

	<?php

	}

	public function display_pull_sitegroups_description() {
		echo esc_html__( 'Select the sitegroups to pull content', 'push-syndication' );
	}

	public function display_pull_sitegroups_selection() {
		// get all sitegroups
		$sitegroups = get_terms(
			'syn_sitegroup',
			array(
				'fields'        => 'all',
				'hide_empty'    => false,
				'orderby'       => 'name',
			)
		);

		// if there are no sitegroups defined return
		if ( empty( $sitegroups ) ) {
			echo '<p>' . esc_html__( 'No sitegroups defined yet. You must group your sites into sitegroups to syndicate content', 'push-syndication' ) . '</p>';
			echo '<p><a href="' . esc_url( get_admin_url() . 'edit-tags.php?taxonomy=syn_sitegroup&post_type=syn_site' ) . '" target="_blank" >' . esc_html__( 'Create new', 'push-syndication' ) . '</a></p>';
			return;
		}

		$options = array();

		foreach ( $sitegroups as $sitegroup ) {
			$options[ $sitegroup->slug ] = array(
				'name'        => $sitegroup->name,
				'description' => $sitegroup->description,
			);
		}

		$this->form_checkbox( $options, 'selected_pull_sitegroups' );
	}

	public  function display_pull_options_description() {
		echo esc_html__( 'Configure options for pulling content', 'push-syndication' );
	}

	public function display_time_interval_selection() {
		global $settings_manager;
		echo '<input type="text" size="10" name="push_syndicate_settings[pull_time_interval]" value="' . esc_attr( $settings_manager->get_setting( 'pull_time_interval' ) ) . '"/>';
	}

	/**
	 * Display the form field for the push_syndication_max_pull_attempts option.
	 */
	public function display_max_pull_attempts() {
		global $settings_manager;
		?>
		<input type="text" size="10" name="push_syndicate_settings[push_syndication_max_pull_attempts]" value="<?php echo esc_attr( $settings_manager->get_setting( 'push_syndication_max_pull_attempts', 0 ) ); ?>" />
		<p><?php echo esc_html__( 'Site will be disabled after failure threshold is reached. Set to 0 to disable.', 'push-syndication' ); ?></p>
	<?php
	}

	/**
	 * Validate the push_syndication_max_pull_attempts option.
	 *
	 * @param $val
	 * @return int
	 */
	public function validate_max_pull_attempts( $val ) {
		/**
		 * Filter the maximum value that can be used for the
		 * push_syndication_max_pull_attempts option. This only takes effect when the
		 * option is set. Use the pre_option_push_syndication_max_pull_attempts or
		 * option_push_syndication_max_pull_attempts filters to modify values that
		 * have already been set.
		 *
		 * @param int $upper_limit Maximum value that can be used. Defaults is 100.
		 */
		$upper_limit = apply_filters( 'push_syndication_max_pull_attempts_upper_limit', 100 );

		// Ensure a value between zero and the upper limit.
		return min( $upper_limit, max( 0, (int) $val ) );
	}

	public function display_update_pulled_posts_selection() {
		global $settings_manager;
		// @TODO refractor this
		echo '<input type="checkbox" name="push_syndicate_settings[update_pulled_posts]" value="on" ';
		echo checked( $settings_manager->get_setting( 'update_pulled_posts' ), 'on' ) . ' />';
	}

	public function display_push_post_types_description() {
		echo esc_html__( 'Select the post types to add support for pushing content', 'push-syndication' );
	}

	public function display_post_types_selection() {
		// @todo: Add more suitable filters.
		$post_types = get_post_types( array( 'public' => true ) );
		$options    = array();

		foreach ( $post_types as $post_type ) {
			$options[ $post_type ] = array(
				'name' => $post_type,
			);
		}

		$this->form_checkbox( $options, 'selected_post_types' );
	}

	public function display_delete_pushed_posts_description() {
		echo esc_html__( 'Tick the box to delete all the pushed posts when the master post is deleted', 'push-syndication' );
	}

	public function display_delete_pushed_posts_selection() {
		global $settings_manager;

		// @todo Refractor this.
		echo '<input type="checkbox" name="push_syndicate_settings[delete_pushed_posts]" value="on" ';
		echo checked( $settings_manager->get_setting( 'delete_pushed_posts' ), 'on' ) . ' />';
	}

	/**
	 * Display Nofication Description
	 *
	 * Displays the description for the notification settings section.
	 *
	 * @since 2.1
	 */
	public function display_notifications_description() {
		echo esc_html__( 'Setup email and Slack notifications.', 'push-syndication' );
	}

	/**
	 * Display Notification Method Selection
	 *
	 * Allows the user to select if what notification methods they would like to
	 * enable.
	 *
	 * @since 2.1
	 */
	public function display_notification_method_selection() {
		$this->form_checkbox(
			array(
				'email' => array(
					'name' => 'Email notifications',
				),
				'slack' => array(
					'name' => 'Slack notifications',
				),
			),
			'notification_methods'
		);
	}

	/**
	 * Display Notification Type Selection
	 *
	 * Allows the user to select on what type of events they would like to get
	 * notified.
	 *
	 * @since 2.1
	 */
	public function display_notification_type_selection() {
		$this->form_checkbox(
			array(
				'new'    => array(
					'name' => 'New post',
				),
				'edit'   => array(
					'name' => 'Edit post',
				),
				'delete' => array(
					'name' => 'Delete post',
				),
			),
			'notification_types'
		);
	}

	/**
	 *
	 */
	public function display_notification_email() {
		$this->form_input(
			'notification_email',
			array(
				'description' => __( 'The email address where alerts should be sent', 'push-syndication' ),
			)
		);
	}

	/**
	 *
	 */
	public function display_notification_slack_webhook() {
		$this->form_input(
			'notification_slack_webhook',
			array(
				'description' => sprintf( __( 'Setup a new Slack webhook URL %s', 'push-syndication' ), '<a href="https://my.slack.com/services/new/incoming-webhook/" target="_blank">' . __( 'here', 'push-syndication' ) . '</a>' ),
			)
		);
	}

	public function display_client_id() {
		global $settings_manager;
		echo '<input type="text" size=100 name="push_syndicate_settings[client_id]" value="' . esc_attr( $settings_manager->get_setting( 'client_id' ) ) . '"/>';
	}

	public function display_client_secret() {
		global $settings_manager;
		echo '<input type="text" size=100 name="push_syndicate_settings[client_secret]" value="' . esc_attr( $settings_manager->get_setting( 'client_secret' ) ) . '"/>';
	}

	public function display_sitegroups_selection() {

		echo '<h3>' . esc_html__( 'Select Sitegroups', 'push-syndication' ) . '</h3>';

		$selected_sitegroups = get_option( 'syn_selected_sitegroups' );
		$selected_sitegroups = ! empty( $selected_sitegroups ) ? $selected_sitegroups : array() ;

		// get all sitegroups
		$sitegroups = get_terms(
			'syn_sitegroup',
			array(
				'fields'        => 'all',
				'hide_empty'    => false,
				'orderby'       => 'name',
			)
		);

		// if there are no sitegroups defined return
		if ( empty( $sitegroups ) ) {
			echo '<p>' . esc_html__( 'No sitegroups defined yet. You must group your sites into sitegroups to syndicate content', 'push-syndication' ) . '</p>';
			echo '<p><a href="' . esc_url( get_admin_url() . 'edit-tags.php?taxonomy=syn_sitegroup&post_type=syn_site' ) . '" target="_blank" >' . esc_html__( 'Create new', 'push-syndication' ) . '</a></p>';
			return;
		}

		foreach ( $sitegroups as $sitegroup ) {

			?>

			<p>
				<label>
					<input type="checkbox" name="syn_selected_sitegroups[]" value="<?php echo esc_html( $sitegroup->slug ); ?>" <?php $this->checked_array( $sitegroup->slug, $selected_sitegroups ) ?> />
					<?php echo esc_html( $sitegroup->name ); ?>
				</label>
				<?php echo esc_html( $sitegroup->description ); ?>
			</p>

		<?php

		}

	}

	/**
	 * Form Checkbox
	 *
	 * Generates a checkbox form item.
	 *
	 * @since 2.1
	 * @param array  $setting_options The options for the checkboxes.
	 * @param string $setting_key The settings key which stores the values of the form item.
	 */
	public function form_checkbox( $setting_options = array(), $setting_key ) {
		global $settings_manager;

		$saved_option = $settings_manager->get_setting( $setting_key );

		foreach ( $setting_options as $option_key => $option ) {
			?>
			<p>
				<label>
					<input type="checkbox" name="push_syndicate_settings[<?php echo esc_attr( $setting_key ); ?>][]" value="<?php echo esc_attr( $option_key ); ?>" <?php $this->checked_array( $option_key, $saved_option ); ?> />
					<?php echo esc_html( $option['name'] ); ?>
				</label>
				<?php
				if ( ! empty( $option['description'] ) ) :
					echo wp_kses_post( $option['description'] );
				endif;
				?>
			</p>
			<?php
		}
	}

	/**
	 * Form Input
	 *
	 * Generates a form input box. Has the following arguments which should be
	 * passed as the second method argument.
	 *
	 * `default` Sets the default value for the form input box
	 * `class` Override the default class value for the input element
	 *
	 * @since 2.1
	 * @param string $setting_key The settings key which stores the values of the form item.
	 * @param array  $args Options for the form output (see above).
	 */
	public function form_input( $setting_key, $args ) {
		global $settings_manager;

		if ( ! empty( $args['default'] ) ) {
			$default = $args['default'];
		} else {
			$default = '';
		}

		if ( ! empty( $args['class'] ) ) {
			$class = $args['class'];
		} else {
			$class = 'regular-text';
		}
		?>
		<input type="text" name="push_syndicate_settings[<?php echo esc_attr( $setting_key ); ?>]" class="<?php echo esc_attr( $class ); ?>" value="<?php echo esc_attr( $settings_manager->get_setting( $setting_key, $default ) ); ?>" />
		<?php if ( ! empty( $args['description'] ) ) : ?>
		<p><?php echo wp_kses_post( $args['description'] ); ?></p>
		<?php
		endif;
	}

	/**
	 * Checked Array
	 *
	 * @param string $value The needle.
	 * @param array  $group The haystack.
	 */
	public function checked_array( $value, $group ) {
		if ( ! empty( $group ) ) {
			if ( in_array( $value, $group, true ) ) {
				echo ' checked="checked"';
			}
		}
	}
}
