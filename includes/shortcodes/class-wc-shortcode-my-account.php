<?php
/**
 * My Account Shortcodes
 *
 * Shows the 'my account' section where the customer can view past orders and update their information.
 *
 * @author 		WooThemes
 * @category 	Shortcodes
 * @package 	WooCommerce/Shortcodes/My_Account
 * @version     2.0.0
 */
class WC_Shortcode_My_Account {

	/**
	 * Get the shortcode content.
	 *
	 * @access public
	 * @param array $atts
	 * @return string
	 */
	public static function get( $atts ) {
		global $woocommerce;
		return $woocommerce->get_helper( 'shortcode' )->shortcode_wrapper( array( __CLASS__, 'output' ), $atts );
	}

	/**
	 * Output the shortcode.
	 *
	 * @access public
	 * @param array $atts
	 * @return void
	 */
	public static function output( $atts ) {
		global $woocommerce, $wp;

		if ( ! is_user_logged_in() ) {

			if ( isset( $wp->query_vars['lost-password'] ) ) {

				self::lost_password();

			} else {

				woocommerce_get_template( 'myaccount/form-login.php' );

			}

		} else {

			if ( ! empty( $wp->query_vars['view-order'] ) ) {

				self::view_order( absint( $wp->query_vars['view-order'] ) );

			} elseif ( isset( $wp->query_vars['edit-account'] ) ) {

				self::edit_account();

			} elseif ( isset( $wp->query_vars['edit-address'] ) ) {

				self::edit_address( sanitize_title( $wp->query_vars['edit-address'] ) );

			} else {

				self::my_account( $atts );

			}
		}
	}

	/**
	 * My account page
	 *
	 * @param  array $atts
	 */
	private function my_account( $atts ) {
		extract( shortcode_atts( array(
	    	'order_count' => 15
		), $atts ) );

		woocommerce_get_template( 'myaccount/my-account.php', array(
			'current_user' 	=> get_user_by( 'id', get_current_user_id() ),
			'order_count' 	=> 'all' == $order_count ? -1 : $order_count
		) );
	}

	/**
	 * View order page
	 *
	 * @param  int $order_id
	 */
	private function view_order( $order_id ) {
		global $woocommerce;

		$user_id      	= get_current_user_id();
		$order 			= new WC_Order( $order_id );

		if ( $order->user_id != $user_id ) {
			echo '<div class="woocommerce-error">' . __( 'Invalid order.', 'woocommerce' ) . ' <a href="' . get_permalink( woocommerce_get_page_id( 'myaccount' ) ).'">'. __( 'My Account &rarr;', 'woocommerce' ) .'</a>' . '</div>';
			return;
		}

		$status = get_term_by( 'slug', $order->status, 'shop_order_status' );

		echo '<p class="order-info">'
		. sprintf( __( 'Order <mark class="order-number">%s</mark> made on <mark class="order-date">%s</mark>', 'woocommerce'), $order->get_order_number(), date_i18n( get_option( 'date_format' ), strtotime( $order->order_date ) ) )
		. '. ' . sprintf( __( 'Order status: <mark class="order-status">%s</mark>', 'woocommerce' ), __( $status->name, 'woocommerce' ) )
		. '.</p>';

		if ( $notes = $order->get_customer_order_notes() ) :
			?>
			<h2><?php _e( 'Order Updates', 'woocommerce' ); ?></h2>
			<ol class="commentlist notes">
				<?php foreach ( $notes as $note ) : ?>
				<li class="comment note">
					<div class="comment_container">
						<div class="comment-text">
							<p class="meta"><?php echo date_i18n(__( 'l jS \of F Y, h:ia', 'woocommerce' ), strtotime($note->comment_date)); ?></p>
							<div class="description">
								<?php echo wpautop( wptexturize( $note->comment_content ) ); ?>
							</div>
			  				<div class="clear"></div>
			  			</div>
						<div class="clear"></div>
					</div>
				</li>
				<?php endforeach; ?>
			</ol>
			<?php
		endif;

		do_action( 'woocommerce_view_order', $order_id );
	}

	/**
	 * Edit account details page
	 */
	private function edit_account() {
		woocommerce_get_template( 'myaccount/form-edit-account.php', array( 'user' => get_user_by( 'id', get_current_user_id() ) ) );
	}

	/**
	 * Edit address page.
	 *
	 * @access public
	 * @param string $load_address
	 */
	private function edit_address( $load_address = 'billing' ) {
		global $woocommerce;

		$load_address = ( $load_address == 'billing' || $load_address == 'shipping' ) ? $load_address : 'billing';

		$address = $woocommerce->countries->get_address_fields( get_user_meta( get_current_user_id(), $load_address . '_country', true ), $load_address . '_' );

		woocommerce_get_template( 'myaccount/form-edit-address.php', array(
			'load_address' 	=> $load_address,
			'address'		=> $address
		) );
	}

	/**
	 * Lost password page
	 */
	public static function lost_password() {
		global $woocommerce;

		global $post;

		// arguments to pass to template
		$args = array( 'form' => 'lost_password' );

		// process lost password form
		if( isset( $_POST['user_login'] ) ) {

			wp_verify_nonce( $_POST['_wpnonce'], 'woocommerce-lost_password' );

			self::retrieve_password();
		}

		// process reset key / login from email confirmation link
		if( isset( $_GET['key'] ) && isset( $_GET['login'] ) ) {

			$user = self::check_password_reset_key( $_GET['key'], $_GET['login'] );

			// reset key / login is correct, display reset password form with hidden key / login values
			if( is_object( $user ) ) {
				$args['form'] = 'reset_password';
				$args['key'] = esc_attr( $_GET['key'] );
				$args['login'] = esc_attr( $_GET['login'] );
			}
		}

		// process reset password form
		if( isset( $_POST['password_1'] ) && isset( $_POST['password_2'] ) && isset( $_POST['reset_key'] ) && isset( $_POST['reset_login'] ) ) :

			// verify reset key again
			$user = self::check_password_reset_key( $_POST['reset_key'], $_POST['reset_login'] );

			if( is_object( $user ) ) {

				// save these values into the form again in case of errors
				$args['key'] = esc_attr( $_POST['reset_key'] );
				$args['login'] = esc_attr( $_POST['reset_login'] );

				wp_verify_nonce( $_POST['_wpnonce'], 'woocommerce-reset_password' );

				if( empty( $_POST['password_1'] ) || empty( $_POST['password_2'] ) ) {
					wc_add_error( __( 'Please enter your password.', 'woocommerce' ) );
					$args['form'] = 'reset_password';
				}

				if( $_POST[ 'password_1' ] !== $_POST[ 'password_2' ] ) {
					wc_add_error( __( 'Passwords do not match.', 'woocommerce' ) );
					$args['form'] = 'reset_password';
				}

				if( 0 == wc_error_count() && ( $_POST['password_1'] == $_POST['password_2'] ) ) {

					self::reset_password( $user, esc_attr( $_POST['password_1'] ) );

					do_action( 'woocommerce_customer_reset_password', $user );

					wc_add_message( __( 'Your password has been reset.', 'woocommerce' ) . ' <a href="' . get_permalink( woocommerce_get_page_id( 'myaccount' ) ) . '">' . __( 'Log in', 'woocommerce' ) . '</a>' );
				}
			}

		endif;

		woocommerce_get_template( 'myaccount/form-lost-password.php', $args );
	}

	/**
	 * Handles sending password retrieval email to customer.
	 *
	 * @access public
	 * @uses $wpdb WordPress Database object
	 * @return bool True: when finish. False: on error
	 */
	public static function retrieve_password() {
		global $woocommerce,$wpdb;

		if ( empty( $_POST['user_login'] ) ) {

			wc_add_error( __( 'Enter a username or e-mail address.', 'woocommerce' ) );

		} elseif ( strpos( $_POST['user_login'], '@' ) ) {

			$user_data = get_user_by( 'email', trim( $_POST['user_login'] ) );

			if ( empty( $user_data ) )
				wc_add_error( __( 'There is no user registered with that email address.', 'woocommerce' ) );

		} else {

			$login = trim( $_POST['user_login'] );

			$user_data = get_user_by('login', $login );
		}

		do_action('lostpassword_post');

		if( wc_error_count() > 0 )
			return false;

		if ( ! $user_data ) {
			wc_add_error( __( 'Invalid username or e-mail.', 'woocommerce' ) );
			return false;
		}

		// redefining user_login ensures we return the right case in the email
		$user_login = $user_data->user_login;
		$user_email = $user_data->user_email;

		do_action('retrieve_password', $user_login);

		$allow = apply_filters('allow_password_reset', true, $user_data->ID);

		if ( ! $allow ) {

			wc_add_error( __( 'Password reset is not allowed for this user' ) );

			return false;

		} elseif ( is_wp_error( $allow ) ) {

			wc_add_error( $allow->get_error_message );

			return false;
		}

		$key = $wpdb->get_var( $wpdb->prepare( "SELECT user_activation_key FROM $wpdb->users WHERE user_login = %s", $user_login ) );

		if ( empty( $key ) ) {

			// Generate something random for a key...
			$key = wp_generate_password( 20, false );

			do_action('retrieve_password_key', $user_login, $key);

			// Now insert the new md5 key into the db
			$wpdb->update( $wpdb->users, array( 'user_activation_key' => $key ), array( 'user_login' => $user_login ) );
		}

		// Send email notification
		$mailer = $woocommerce->mailer();
		do_action( 'woocommerce_reset_password_notification', $user_login, $key );

		wc_add_message( __( 'Check your e-mail for the confirmation link.' ) );
		return true;
	}

	/**
	 * Retrieves a user row based on password reset key and login
	 *
	 * @uses $wpdb WordPress Database object
	 *
	 * @access public
	 * @param string $key Hash to validate sending user's password
	 * @param string $login The user login
	 * @return object|bool User's database row on success, false for invalid keys
	 */
	public static function check_password_reset_key( $key, $login ) {
		global $woocommerce,$wpdb;

		$key = preg_replace( '/[^a-z0-9]/i', '', $key );

		if ( empty( $key ) || ! is_string( $key ) ) {
			wc_add_error( __( 'Invalid key', 'woocommerce' ) );
			return false;
		}

		if ( empty( $login ) || ! is_string( $login ) ) {
			wc_add_error( __( 'Invalid key', 'woocommerce' ) );
			return false;
		}

		$user = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->users WHERE user_activation_key = %s AND user_login = %s", $key, $login ) );

		if ( empty( $user ) ) {
			wc_add_error( __( 'Invalid key', 'woocommerce' ) );
			return false;
		}

		return $user;
	}

	/**
	 * Handles resetting the user's password.
	 *
	 * @access public
	 * @param object $user The user
	 * @param string $new_pass New password for the user in plaintext
	 * @return void
	 */
	public static function reset_password( $user, $new_pass ) {
		do_action( 'password_reset', $user, $new_pass );

		wp_set_password( $new_pass, $user->ID );

		wp_password_change_notification( $user );
	}
}