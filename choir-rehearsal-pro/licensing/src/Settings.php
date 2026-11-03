<?php

namespace SureCart\Licensing;

/**
 * The settings class.
 */
class Settings {
	/**
	 * SureCart\Licensing\Client
	 *
	 * @var object
	 */
	protected $client;

	/**
	 * Holds the option key
	 *
	 * @var string
	 */
	private $option_key;

	/**
	 * Holds the option name
	 *
	 * @var string
	 */
	private $name;

	/**
	 * Holds the menu arguments
	 *
	 * @var array
	 */
	private $menu_args;

	/**
	 * Create the pages.
	 *
	 * @param SureCart\Licensing\Client $client The client.
	 */
	public function __construct( Client $client ) {
		$this->client     = $client;
		$this->name       = strtolower( preg_replace( '/\s+/', '', $this->client->name ) );
		$this->option_key = $this->name . '_license_options';
	}

	/**
	 * Add the settings page.
	 *
	 * @param array $args Settings page args.
	 *
	 * @return void
	 */
	public function add_page( $args ) {
		// store menu args for proper menu creation.
		$this->menu_args = wp_parse_args(
			$args,
			array(
				'type'               => 'menu', // Can be: menu, options, submenu.
				'page_title'         => 'Manage License',
				'menu_title'         => 'Manage License',
				'capability'         => 'manage_options',
				'menu_slug'          => $this->client->slug . '-manage-license',
				'icon_url'           => '',
				'position'           => null,
				'activated_redirect' => null,
				'parent_slug'        => '',
			)
		);
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 99 );
	}

	/**
	 * Form action URL
	 */
	private function form_action_url() {
		return apply_filters( 'surecart_client_license_form_action', '' );
	}

	/**
	 * Set the option key.
	 *
	 * If someone wants to override the default generated key.
	 *
	 * @param string $key The option key.
	 */
	public function set_option_key( $key ) {
		$this->option_key = $key;
		return $this;
	}

	/**
	 * Add the admin menu
	 *
	 * @return void
	 */
	public function admin_menu() {
		switch ( $this->menu_args['type'] ) {
			case 'menu':
				$this->create_menu_page();
				break;
			case 'submenu':
				$this->create_submenu_page();
				break;
			case 'options':
				$this->create_options_page();
				break;
		}
	}

	/**
	 * Add license menu page
	 */
	private function create_menu_page() {
		call_user_func(
			'add_menu_page',
			$this->menu_args['page_title'],
			$this->menu_args['menu_title'],
			$this->menu_args['capability'],
			$this->menu_args['menu_slug'],
			array( $this, 'settings_output' ),
			$this->menu_args['icon_url'],
			$this->menu_args['position']
		);
	}

	/**
	 * Add submenu page
	 */
	private function create_submenu_page() {
		call_user_func(
			'add_submenu_page',
			$this->menu_args['parent_slug'],
			$this->menu_args['page_title'],
			$this->menu_args['menu_title'],
			$this->menu_args['capability'],
			$this->menu_args['menu_slug'],
			array( $this, 'settings_output' ),
			$this->menu_args['position']
		);
	}

	/**
	 * Add submenu page
	 */
	private function create_options_page() {
		call_user_func(
			'add_options_page',
			$this->menu_args['page_title'],
			$this->menu_args['menu_title'],
			$this->menu_args['capability'],
			$this->menu_args['menu_slug'],
			array( $this, 'settings_output' ),
			$this->menu_args['position']
		);
	}

	/**
	 * Get all options
	 *
	 * @return array
	 */
	public function get_options() {
		return (array) get_option( $this->option_key, array() );
	}

	/**
	 * Clear out the options.
	 *
	 * @return bool
	 */
	public function clear_options() {
		return update_option( $this->option_key, array() );
	}

	/**
	 * Get a specific option
	 *
	 * @param string $name Option name.
	 *
	 * @return mixed
	 */
	public function get_option( $name ) {
		$options = $this->get_options();
		return isset( $options[ $name ] ) ? $options[ $name ] : null;
	}

	/**
	 * Set the option.
	 *
	 * @param string $name The option name.
	 * @param mixed  $value The option value.
	 *
	 * @return bool
	 */
	public function set_option( $name, $value ) {
		$options          = (array) $this->get_options();
		$options[ $name ] = $value;
		return update_option( $this->option_key, $options );
	}

	/**
	 * The settings page menu output.
	 *
	 * @return void
	 */
	public function settings_output() {
		$this->license_form_submit();
		$this->restore_license_notice();

		$this->print_css();

		$activation = $this->get_activation();
		$action     = ! empty( $activation->id ) ? 'deactivate' : 'activate'
		?>

		<div class="wrap">
			<h1></h1>
			<?php settings_errors(); ?>

			<div class="<?php echo esc_attr( $this->name ) . '-form-container'; ?>">
				<form method="post" action="<?php echo esc_attr( $this->form_action_url() ); ?>">
					<input type="hidden" name="_action" value="<?php echo esc_attr( $action ); ?>">
					<input type="hidden" name="_nonce" value="<?php echo esc_attr( wp_create_nonce( $this->client->name ) ); ?>">

					<h2><?php echo esc_html( $this->menu_args['page_title'] ); ?></h2>
					<label for="license_key">
						<?php if ( 'activate' === $action ) : ?> 
							<?php echo esc_html( sprintf( $this->client->__( 'Enter your license key to activate %s.', 'surecart' ), $this->client->name ) ); ?>
						<?php else : ?>
							<?php echo esc_html( sprintf( $this->client->__( 'Your license is succesfully activated for this site.', 'surecart' ), $this->client->name ) ); ?>
						<?php endif; ?>
					</label>

					<?php if ( 'activate' === $action ) : ?> 
						<input class="widefat" type="password" autocomplete="off" name="license_key" id="license_key" value="" autofocus>
					<?php else : ?>
						<?php $this->render_masked_secret( 'activation_id', $this->client->__( 'Activation ID' ), $this->activation_id ); ?>
						<?php $this->render_masked_secret( 'license_key_display', $this->client->__( 'License key' ), $this->license_key ); ?>
					<?php endif; ?>

					<?php if ( isset( $_GET['debug'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
						<label for="license_id"><?php echo esc_html( $this->client->__( 'License ID' ) ); ?></label>
						<input class="widefat" type="text" autocomplete="off" id="license_id" value="<?php echo esc_attr( $this->mask_secret( $this->license_id ) ); ?>" readonly="readonly">
					<?php endif; ?>

					<?php $this->render_license_submit( $action ); ?>
					<?php
					if ( class_exists( '\Choir_Rehearsal_Admin', false ) && method_exists( '\Choir_Rehearsal_Admin', 'render_my_orders_button' ) ) {
						\Choir_Rehearsal_Admin::render_my_orders_button();
					} else {
						echo '<p><a class="button button-secondary" href="' . esc_url( 'https://shop.compath.ee/customer-dashboard/' ) . '" target="_blank" rel="noopener noreferrer">';
						esc_html_e( 'My orders..', 'compath-choir-rehearsal' );
						echo '</a></p>';
					}
					?>
				</form>
			</div>
		</div>
		<?php
	}

		/**
		 * Print the css for the form.
		 *
		 * @return void
		 */
	public function print_css() {
		?>
		<style>
			.spinner {
				float: none;
			}
			<?php echo '.' . esc_attr( $this->name ) . '-form-container'; ?> form {
				padding:30px;
				background: #fff;
				display: grid;
				gap: 1em;
				max-width: 600px;
			}
			h2 {
				padding: 0;
				margin: 0;
			}
			label {
				display: block;
				font-size: 1.1em;
				margin-bottom: 5px;
			}
			label[hidden] {
				display: none;
			}
			p.submit {
				margin: 0;
				padding: 0;
			}
		</style>
		<?php
	}

	/**
	 * Get the activation.
	 *
	 * A read never writes to stored options; it only surfaces an error message.
	 * On a 404 the stale activation_id is left in the database (it gets overwritten
	 * on reactivation), but this still returns false so the page shows "Activate
	 * License" instead of a "Deactivate License" button that would just keep failing.
	 *
	 * @return Object|false
	 */
	public function get_activation() {
		if ( ! $this->activation_id ) {
			return false;
		}

		$activation = $this->client->activation()->get( $this->activation_id );

		if ( ! is_wp_error( $activation ) ) {
			return $activation;
		}

		if ( 'not_found' === $activation->get_error_code() ) {
			$this->add_error( 'activation_removed', $this->client->__( 'The activation for this site was removed. Click Activate License to reconnect it.' ) );
			return false;
		}

		$this->add_error( 'unverified', $this->client->__( 'Could not verify your license right now. Your license key has been kept; please try again later.' ) );

		// Keep the form in "activated" state so the user is not asked to re-enter the key.
		return (object) array( 'id' => $this->activation_id );
	}

	/**
	 * License form submit
	 */
	public function license_form_submit() {
		// only if we are submitting.
		if ( ! isset( $_POST['submit'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			$this->add_error( 'forbidden', $this->client->__( "You don't have permission to manage licenses." ) );
			return;
		}

		// Check nonce.
		if ( ! isset( $_POST['_nonce'], $_POST['_action'] ) ) {
			$this->add_error( 'missing_info', $this->client->__( 'Please add all information' ) );
			return;
		}

		// Cerify nonce.
		if ( ! wp_verify_nonce( $_POST['_nonce'], $this->client->name ) ) {
			$this->add_error( 'unauthorized', $this->client->__( "You don't have permission to manage licenses." ) );
			return;
		}

		// handle activation.
		if ( 'activate' === $_POST['_action'] ) {
			$activated = $this->client->license()->activate( sanitize_text_field( $_POST['license_key'] ) );
			if ( is_wp_error( $activated ) ) {
				$this->add_error( $activated->get_error_code(), $activated->get_error_message() );
				return;
			}

			if ( ! empty( $this->menu_args['activated_redirect'] ) ) {
				$this->redirect( $this->menu_args['activated_redirect'] );
				exit;
			}

			return $this->add_success( 'activated', $this->client->__( 'This site was successfully activated.', 'surecart' ) );
		}

		// handle deactivation.
		if ( 'deactivate' === $_POST['_action'] ) {
			// The page never receives the activation id. Use the saved option.
			$stored        = $this->activation_id;
			$activation_id = is_scalar( $stored ) ? sanitize_text_field( (string) $stored ) : '';
			$deactivated   = $this->client->license()->deactivate( $activation_id );
			if ( is_wp_error( $deactivated ) ) {
				$url = $this->complete_deactivation( $deactivated );
				if ( '' !== $url ) {
					$this->redirect( $url );
					exit;
				}
				return;
			}

			$notice = __( 'License deactivated', 'compath-choir-rehearsal' );
			if ( class_exists( '\Choir_Rehearsal_Admin', false ) && method_exists( '\Choir_Rehearsal_Admin', 'license_deactivated_notice' ) ) {
				$notice = \Choir_Rehearsal_Admin::license_deactivated_notice();
			}
			$url = $this->complete_deactivation( true, $notice );
			if ( '' !== $url ) {
				$this->redirect( $url );
				exit;
			}
			return;
		}
	}

	/**
	 * Persist a deactivation result and return the redirect URL when one is configured.
	 *
	 * An error never falls through to the success notice. The JS redirect drops
	 * add_settings_error, so the message is stored for the next request.
	 *
	 * @param mixed  $deactivated     True on success, WP_Error on failure.
	 * @param string $success_message Notice used when deactivation succeeded.
	 */
	public function complete_deactivation( $deactivated, string $success_message = '' ): string {
		if ( is_wp_error( $deactivated ) ) {
			$message = $deactivated->get_error_message();
			if ( ! empty( $this->menu_args['deactivated_redirect'] ) ) {
				$this->store_license_notice( 'error', $message );
				return $this->license_notice_redirect_url( (string) $this->menu_args['deactivated_redirect'], 'error' );
			}
			$this->add_error( $deactivated->get_error_code(), $message );
			return '';
		}

		if ( '' === $success_message ) {
			$success_message = $this->deactivated_notice_text();
		}
		if ( ! empty( $this->menu_args['deactivated_redirect'] ) ) {
			$this->store_license_notice( 'success', $success_message );
			return $this->license_notice_redirect_url( (string) $this->menu_args['deactivated_redirect'], 'deactivated' );
		}

		$this->add_success( 'deactivated', $success_message );
		return '';
	}

	/**
	 * Show the notice saved before the client-side redirect.
	 */
	public function restore_license_notice(): void {
		if ( ! isset( $_GET['choir_license_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$flag    = sanitize_key( wp_unslash( (string) $_GET['choir_license_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$stored  = get_transient( $this->license_notice_key() );
		delete_transient( $this->license_notice_key() );
		$message = ( is_array( $stored ) && isset( $stored['message'] ) ) ? (string) $stored['message'] : '';

		if ( 'error' === $flag ) {
			if ( '' === $message ) {
				$message = __( 'Unknown error occurred, Please try again.', 'compath-choir-rehearsal' );
			}
			$this->add_error( 'deactivation_failed', $message );
			return;
		}

		if ( 'deactivated' === $flag ) {
			if ( '' === $message ) {
				$message = $this->deactivated_notice_text();
			}
			$this->add_success( 'deactivated', $message );
		}
	}

	/**
	 * @param string $type    error or success.
	 * @param string $message Notice text.
	 */
	private function store_license_notice( string $type, string $message ): void {
		set_transient(
			$this->license_notice_key(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			60
		);
	}

	private function license_notice_key(): string {
		$user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		return 'choir_rehearsal_pro_license_notice_' . $user_id;
	}

	private function license_notice_redirect_url( string $url, string $type ): string {
		$flag = ( 'error' === $type ) ? 'error' : 'deactivated';
		return add_query_arg( 'choir_license_notice', $flag, $url );
	}

	private function deactivated_notice_text(): string {
		if ( class_exists( '\Choir_Rehearsal_Admin', false ) && method_exists( '\Choir_Rehearsal_Admin', 'license_deactivated_notice' ) ) {
			return \Choir_Rehearsal_Admin::license_deactivated_notice();
		}
		return __( 'License deactivated', 'compath-choir-rehearsal' );
	}

	private function deactivate_confirm_text(): string {
		if ( class_exists( '\Choir_Rehearsal_Admin', false ) && method_exists( '\Choir_Rehearsal_Admin', 'deactivate_license_confirm_text' ) ) {
			return \Choir_Rehearsal_Admin::deactivate_license_confirm_text();
		}
		return __( 'Deactivate the Pro license on this site? Pro features will be disabled and the activation slot freed.', 'compath-choir-rehearsal' );
	}

	/**
	 * Mask a license key or activation id for display.
	 *
	 * Eight characters or fewer are replaced entirely so the value cannot be rebuilt.
	 * Longer values keep the first four and last four characters.
	 *
	 * @param mixed $value Stored secret.
	 */
	private function mask_secret( $value ): string {
		$value  = trim( is_scalar( $value ) ? (string) $value : '' );
		$length = strlen( $value );
		if ( $length < 1 ) {
			return '';
		}
		if ( $length <= 8 ) {
			return '••••';
		}

		return substr( $value, 0, 4 ) . '••••' . substr( $value, -4 );
	}

	/**
	 * Read-only masked secret. No name attribute, so it is not submitted.
	 *
	 * @param string $id    Element id.
	 * @param string $label Visible label.
	 * @param mixed  $value Stored secret.
	 */
	private function render_masked_secret( string $id, string $label, $value ): void {
		$masked = $this->mask_secret( $value );
		if ( '' === $masked ) {
			return;
		}

		echo '<label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
		echo '<input class="widefat" type="text" autocomplete="off" id="' . esc_attr( $id ) . '" value="' . esc_attr( $masked ) . '" readonly="readonly" />';
	}

	/**
	 * @param string $action activate or deactivate.
	 */
	private function render_license_submit( string $action ): void {
		if ( 'deactivate' !== $action ) {
			submit_button( $this->client->__( 'Activate License' ) );
			return;
		}

		$confirm = wp_json_encode( $this->deactivate_confirm_text() );
		echo '<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="' . esc_attr( $this->client->__( 'Deactivate License' ) ) . '" onclick="return confirm(' . esc_attr( (string) $confirm ) . ');" /></p>';
	}

	/**
	 * Redirect to a url client-side.
	 * We need to do this to avoid "headers already sent" messages.
	 *
	 * @param string $url Url to redirect.
	 *
	 * @return void
	 */
	public function redirect( $url ) {
		?>
		<div class="spinner is-active"></div>
		<script>
			window.location.assign("<?php echo esc_url( $url ); ?>");
		</script>
		<?php
	}

	/**
	 * Add a notice.
	 *
	 * @param string $code Notice code.
	 * @param string $message Notice message.
	 * @param string $type Notice type.
	 *
	 * @return void
	 */
	public function add_notice( $code, $message, $type = 'info' ) {
		add_settings_error(
			$this->name . '_license_options', // matches what we registered in `register_setting.
			$code, // the error code.
			$message,
			$type
		);
	}

	/**
	 * Add an error.
	 *
	 * @param string $code Error code.
	 * @param string $message Error message.
	 *
	 * @return void
	 */
	public function add_error( $code, $message ) {
		$this->add_notice( $code, $message, 'error' );
	}

	/**
	 * Add an success message
	 *
	 * @param string $code Success code.
	 * @param string $message Success message.
	 *
	 * @return void
	 */
	public function add_success( $code, $message ) {
		$this->add_notice( $code, $message, 'success' );
	}

	/**
	 * Set an option.
	 *
	 * @param string $name Name of option.
	 *
	 * @return mixed
	 */
	public function __get( $name ) {
		return $this->get_option( 'sc_' . $name );
	}

	/**
	 * Set an option
	 *
	 * @param string $name Name of option.
	 * @param mixed  $value Value.
	 *
	 * @return bool
	 */
	public function __set( $name, $value ) {
		return $this->set_option( 'sc_' . $name, $value );
	}
}
