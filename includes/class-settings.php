<?php
/**
 * Class Settings.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay;

use WC_Payments_Account;
use WC_Payments_Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Gateway settings class for WooCommerce Payments.
 */
class Settings {

	const OPTIONS = [
		'account_statement_descriptor',
		'account_business_name',
		'account_business_url',
		'account_business_support_address',
		'account_business_support_email',
		'account_business_support_phone',
		'account_branding_logo',
		'account_branding_icon',
		'account_branding_primary_color',
		'account_branding_secondary_color',
		'deposit_schedule_interval',
		'deposit_schedule_weekly_anchor',
		'deposit_schedule_monthly_anchor',
		'deposit_delay_days',
		'deposit_status',
		'deposit_completed_waiting_period',
	];

	/**
	 * WC Payments Account.
	 *
	 * @var WC_Payments_Account
	 */
	private $account;

	/**
	 * Settings Constructor.
	 *
	 * @param WC_Payments_Account $account - Account class instance.
	 */
	public function __construct( WC_Payments_Account $account ) {
		$this->account = $account;
	}

	/**
	 * Returns the setting form for WC Payments.
	 *
	 * @return array[] of settings fields.
	 */
	public function get_form_fields() {
		return [
			'enabled'                          => [
				'title'       => __( 'Enable/disable', 'woocommerce-payments' ),
				'label'       => __( 'Enable WooCommerce Payments', 'woocommerce-payments' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			],
			'account_statement_descriptor'     => [
				'type'        => 'account_statement_descriptor',
				'title'       => __( 'Customer bank statement', 'woocommerce-payments' ),
				'description' => WC_Payments_Utils::esc_interpolated_html(
					__( 'Edit the way your store name appears on your customers’ bank statements (read more about requirements <a>here</a>).', 'woocommerce-payments' ),
					[ 'a' => '<a href="https://woocommerce.com/document/payments/bank-statement-descriptor/" target="_blank" rel="noopener noreferrer">' ]
				),
			],
			'manual_capture'                   => [
				'title'       => __( 'Manual capture', 'woocommerce-payments' ),
				'label'       => __( 'Issue an authorization on checkout, and capture later.', 'woocommerce-payments' ),
				'type'        => 'checkbox',
				'description' => __( 'Charge must be captured within 7 days of authorization, otherwise the authorization and order will be canceled.', 'woocommerce-payments' ),
				'default'     => 'no',
			],
			'saved_cards'                      => [
				'title'       => __( 'Saved cards', 'woocommerce-payments' ),
				'label'       => __( 'Enable payment via saved cards', 'woocommerce-payments' ),
				'type'        => 'checkbox',
				'description' => __( 'If enabled, users will be able to pay with a saved card during checkout. Card details are saved on our platform, not on your store.', 'woocommerce-payments' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			],
			'test_mode'                        => [
				'title'       => __( 'Test mode', 'woocommerce-payments' ),
				'label'       => __( 'Enable test mode', 'woocommerce-payments' ),
				'type'        => 'checkbox',
				'description' => __( 'Simulate transactions using test card numbers.', 'woocommerce-payments' ),
				'default'     => 'no',
				'desc_tip'    => true,
			],
			'enable_logging'                   => [
				'title'       => __( 'Debug log', 'woocommerce-payments' ),
				'label'       => __( 'When enabled debug notes will be added to the log.', 'woocommerce-payments' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			],
			'payment_request_details'          => [
				'title'       => __( 'Payment request buttons', 'woocommerce-payments' ),
				'type'        => 'title',
				'description' => '',
			],
			'payment_request'                  => [
				'title'       => __( 'Enable/disable', 'woocommerce-payments' ),
				'label'       => sprintf(
				/* translators: 1) br tag 2) Stripe anchor tag 3) Apple anchor tag */
					__( 'Enable payment request buttons (Apple Pay, Google Pay, and more). %1$sBy using Apple Pay, you agree to %2$s and %3$s\'s Terms of Service.', 'woocommerce-payments' ),
					'<br />',
					'<a href="https://stripe.com/apple-pay/legal" target="_blank">Stripe</a>',
					'<a href="https://developer.apple.com/apple-pay/acceptable-use-guidelines-for-websites/" target="_blank">Apple</a>'
				),
				'type'        => 'checkbox',
				'description' => __( 'If enabled, users will be able to pay using Apple Pay, Google Pay or the Payment Request API if supported by the browser.', 'woocommerce-payments' ),
				'default'     => empty( get_option( 'woocommerce_woocommerce_payments_settings' ) ) ? 'yes' : 'no', // Enable by default for new installations only.
				'desc_tip'    => true,
			],
			'payment_request_button_type'      => [
				'title'       => __( 'Button type', 'woocommerce-payments' ),
				'type'        => 'select',
				'description' => __( 'Select the button type you would like to show.', 'woocommerce-payments' ),
				'default'     => 'buy',
				'desc_tip'    => true,
				'options'     => [
					'default' => __( 'Only icon', 'woocommerce-payments' ),
					'buy'     => __( 'Buy', 'woocommerce-payments' ),
					'donate'  => __( 'Donate', 'woocommerce-payments' ),
					'book'    => __( 'Book', 'woocommerce-payments' ),
				],
			],
			'payment_request_button_theme'     => [
				'title'       => __( 'Button theme', 'woocommerce-payments' ),
				'type'        => 'select',
				'description' => __( 'Select the button theme you would like to show.', 'woocommerce-payments' ),
				'default'     => 'dark',
				'desc_tip'    => true,
				'options'     => [
					'dark'          => __( 'Dark', 'woocommerce-payments' ),
					'light'         => __( 'Light', 'woocommerce-payments' ),
					'light-outline' => __( 'Light-Outline', 'woocommerce-payments' ),
				],
			],
			'payment_request_button_height'    => [
				'title'       => __( 'Button height', 'woocommerce-payments' ),
				'type'        => 'text',
				'description' => __( 'Enter the height you would like the button to be in pixels. Width will always be 100%.', 'woocommerce-payments' ),
				'default'     => '44',
				'desc_tip'    => true,
			],
			'payment_request_button_label'     => [
				'title'       => __( 'Custom button label', 'woocommerce-payments' ),
				'type'        => 'text',
				'description' => __( 'Enter the custom text you would like the button to have.', 'woocommerce-payments' ),
				'default'     => __( 'Buy now', 'woocommerce-payments' ),
				'desc_tip'    => true,
			],
			'payment_request_button_locations' => [
				'title'             => __( 'Button locations', 'woocommerce-payments' ),
				'type'              => 'multiselect',
				'description'       => __( 'Select where you would like to display the button.', 'woocommerce-payments' ),
				'default'           => [
					'product',
					'cart',
					'checkout',
				],
				'class'             => 'wc-enhanced-select',
				'desc_tip'          => true,
				'options'           => [
					'product'  => __( 'Product', 'woocommerce-payments' ),
					'cart'     => __( 'Cart', 'woocommerce-payments' ),
					'checkout' => __( 'Checkout', 'woocommerce-payments' ),
				],
				'custom_attributes' => [
					'data-placeholder' => __( 'Select pages', 'woocommerce-payments' ),
				],
			],
			'upe_enabled_payment_method_ids'   => [
				'title'   => __( 'Payments accepted on checkout', 'woocommerce-payments' ),
				'type'    => 'multiselect',
				'default' => [ 'card' ],
				'options' => [],
			],
			'payment_request_button_size'      => [
				'title'       => __( 'Size of the button displayed for Express Checkouts', 'woocommerce-payments' ),
				'type'        => 'select',
				'description' => __( 'Select the size of the button.', 'woocommerce-payments' ),
				'default'     => 'default',
				'desc_tip'    => true,
				'options'     => [
					'default' => __( 'Default', 'woocommerce-payments' ),
					'medium'  => __( 'Medium', 'woocommerce-payments' ),
					'large'   => __( 'Large', 'woocommerce-payments' ),
				],
			],
		];
	}

	/**
	 * Retrieve options from connected account.
	 *
	 * @param  string $key           Option key.
	 *
	 * @return string|array|int|bool|null The value specified for the option or a default value for the option.
	 */
	public function get_option( $key ) {
		switch ( $key ) {
			case 'account_statement_descriptor':
				return $this->get_account_statement_descriptor();
			case 'account_business_name':
				return $this->get_account_business_name();
			case 'account_business_url':
				return $this->get_account_business_url();
			case 'account_business_support_address':
				return $this->get_account_business_support_address();
			case 'account_business_support_email':
				return $this->get_account_business_support_email();
			case 'account_business_support_phone':
				return $this->get_account_business_support_phone();
			case 'account_branding_logo':
				return $this->get_account_branding_logo();
			case 'account_branding_icon':
				return $this->get_account_branding_icon();
			case 'account_branding_primary_color':
				return $this->get_account_branding_primary_color();
			case 'account_branding_secondary_color':
				return $this->get_account_branding_secondary_color();
			case 'deposit_schedule_interval':
				return $this->get_deposit_schedule_interval();
			case 'deposit_schedule_weekly_anchor':
				return $this->get_deposit_schedule_weekly_anchor();
			case 'deposit_schedule_monthly_anchor':
				return $this->get_deposit_schedule_monthly_anchor();
			case 'deposit_delay_days':
				return $this->get_deposit_delay_days();
			case 'deposit_status':
				return $this->get_deposit_status();
			case 'deposit_completed_waiting_period':
				return $this->get_deposit_completed_waiting_period();
			default:
				Logger::error(
					sprintf(
						'Option %1$s key not found',
						$key
					)
				);
		}
	}

	/**
	 * Gets connected account statement descriptor.
	 *
	 * @param string $empty_value Empty value to return when not connected or fails to fetch account descriptor.
	 *
	 * @return string Statement descriptor of default value.
	 */
	public function get_account_statement_descriptor( string $empty_value = '' ): string {
		try {
			if ( $this->account->is_stripe_connected() ) {
				return $this->account->get_statement_descriptor();
			}
		} catch ( \Exception $e ) {
			Logger::error( 'Failed to get account statement descriptor.' . $e );
		}
		return $empty_value;
	}

	/**
	 * Gets connected account business name.
	 *
	 * @param string $default_value Value to return when not connected or failed to fetch business name.
	 *
	 * @return string Business name or default value.
	 */
	public function get_account_business_name( $default_value = '' ): string {
		try {
			if ( $this->account->is_stripe_connected() ) {
				return $this->account->get_business_name();
			}
		} catch ( \Exception $e ) {
			Logger::error( 'Failed to get account business name.' . $e );
		}

		return $default_value;
	}

	/**
	 * Gets connected account business url.
	 *
	 * @param string $default_value Value to return when not connected or failed to fetch business url.
	 *
	 * @return string Business url or default value.
	 */
	public function get_account_business_url( $default_value = '' ): string {
		try {
			if ( $this->account->is_stripe_connected() ) {
				return $this->account->get_business_url();
			}
		} catch ( \Exception $e ) {
			Logger::error( 'Failed to get account business name.' . $e );
		}

		return $default_value;
	}

	/**
	 * Gets connected account business address.
	 *
	 * @param array $default_value Value to return when not connected or failed to fetch business address.
	 *
	 * @return array Business address or default value.
	 */
	public function get_account_business_support_address( $default_value = [] ): array {
		try {
			if ( $this->account->is_stripe_connected() ) {
				return $this->account->get_business_support_address();
			}
		} catch ( \Exception $e ) {
			Logger::error( 'Failed to get account business name.' . $e );
		}

		return $default_value;
	}

	/**
	 * Gets connected account business support email.
	 *
	 * @param string $default_value Value to return when not connected or failed to fetch business support email.
	 *
	 * @return string Business support email or default value.
	 */
	public function get_account_business_support_email( $default_value = '' ): string {
		try {
			if ( $this->account->is_stripe_connected() ) {
				return $this->account->get_business_support_email();
			}
		} catch ( \Exception $e ) {
			Logger::error( 'Failed to get account business name.' . $e );
		}

		return $default_value;
	}

	/**
	 * Gets connected account business support phone.
	 *
	 * @param string $default_value Value to return when not connected or failed to fetch business support phone.
	 *
	 * @return string Business support phone or default value.
	 */
	public function get_account_business_support_phone( $default_value = '' ): string {
		try {
			if ( $this->account->is_stripe_connected() ) {
				return $this->account->get_business_support_phone();
			}
		} catch ( \Exception $e ) {
			Logger::error( 'Failed to get account business name.' . $e );
		}

		return $default_value;
	}

	/**
	 * Gets connected account branding logo.
	 *
	 * @param string $default_value Value to return when not connected or failed to fetch branding logo.
	 *
	 * @return string Business support branding logo or default value.
	 */
	public function get_account_branding_logo( $default_value = '' ): string {
		try {
			if ( $this->account->is_stripe_connected() ) {
				return $this->account->get_branding_logo();
			}
		} catch ( \Exception $e ) {
			Logger::error( 'Failed to get account business name.' . $e );
		}

		return $default_value;
	}

	/**
	 * Gets connected account branding icon.
	 *
	 * @param string $default_value Value to return when not connected or failed to fetch branding icon.
	 *
	 * @return string Business support branding icon or default value.
	 */
	public function get_account_branding_icon( $default_value = '' ): string {
		try {
			if ( $this->account->is_stripe_connected() ) {
				return $this->account->get_branding_icon();
			}
		} catch ( \Exception $e ) {
			Logger::error( 'Failed to get account business name.' . $e );
		}

		return $default_value;
	}

	/**
	 * Gets connected account branding primary color.
	 *
	 * @param string $default_value Value to return when not connected or failed to fetch branding primary color.
	 *
	 * @return string Business support branding primary color or default value.
	 */
	public function get_account_branding_primary_color( $default_value = '' ): string {
		try {
			if ( $this->account->is_stripe_connected() ) {
				return $this->account->get_branding_primary_color();
			}
		} catch ( \Exception $e ) {
			Logger::error( 'Failed to get account business name.' . $e );
		}

		return $default_value;
	}

	/**
	 * Gets connected account branding secondary color.
	 *
	 * @param string $default_value Value to return when not connected or failed to fetch branding secondary color.
	 *
	 * @return string Business support branding secondary color or default value.
	 */
	public function get_account_branding_secondary_color( $default_value = '' ): string {
		try {
			if ( $this->account->is_stripe_connected() ) {
				return $this->account->get_branding_secondary_color();
			}
		} catch ( \Exception $e ) {
			Logger::error( 'Failed to get account business name.' . $e );
		}

		return $default_value;
	}

	/**
	 * Gets connected account deposit schedule interval.
	 *
	 * @param string $empty_value Empty value to return when not connected or fails to fetch deposit schedule.
	 *
	 * @return string Interval or default value.
	 */
	public function get_deposit_schedule_interval( string $empty_value = '' ): string {
		try {
			if ( $this->account->is_stripe_connected() ) {
				return $this->account->get_deposit_schedule_interval();
			}
		} catch ( \Exception $e ) {
			Logger::error( 'Failed to get deposit schedule interval.' . $e );
		}
		return $empty_value;
	}

	/**
	 * Gets connected account deposit schedule weekly anchor.
	 *
	 * @param string $empty_value Empty value to return when not connected or fails to fetch deposit schedule weekly anchor.
	 *
	 * @return string Weekly anchor or default value.
	 */
	public function get_deposit_schedule_weekly_anchor( string $empty_value = '' ): string {
		try {
			if ( $this->account->is_stripe_connected() ) {
				return $this->account->get_deposit_schedule_weekly_anchor();
			}
		} catch ( \Exception $e ) {
			Logger::error( 'Failed to get deposit schedule weekly anchor.' . $e );
		}
		return $empty_value;
	}

	/**
	 * Gets connected account deposit schedule monthly anchor.
	 *
	 * @param int|null $empty_value Empty value to return when not connected or fails to fetch deposit schedule monthly anchor.
	 *
	 * @return int|null Monthly anchor or default value.
	 */
	public function get_deposit_schedule_monthly_anchor( $empty_value = null ) {
		try {
			if ( $this->account->is_stripe_connected() ) {
				return $this->account->get_deposit_schedule_monthly_anchor();
			}
		} catch ( \Exception $e ) {
			Logger::error( 'Failed to get deposit schedule monthly anchor.' . $e );
		}
		return null === $empty_value ? null : (int) $empty_value;
	}

	/**
	 * Gets connected account deposit delay days.
	 *
	 * @param int $default_value Value to return when not connected or fails to fetch deposit delay days. Default is 7 days.
	 *
	 * @return int number of days.
	 */
	public function get_deposit_delay_days( int $default_value = 7 ): int {
		try {
			if ( $this->account->is_stripe_connected() ) {
				return $this->account->get_deposit_delay_days() ?? $default_value;
			}
		} catch ( \Exception $e ) {
			Logger::error( 'Failed to get deposit delay days.' . $e );
		}
		return $default_value;
	}

	/**
	 * Gets connected account deposit status.
	 *
	 * @param string $empty_value Empty value to return when not connected or fails to fetch deposit status.
	 *
	 * @return string deposit status or default value.
	 */
	public function get_deposit_status( string $empty_value = '' ): string {
		try {
			if ( $this->account->is_stripe_connected() ) {
				return $this->account->get_deposit_status();
			}
		} catch ( \Exception $e ) {
			Logger::error( 'Failed to get deposit status.' . $e );
		}
		return $empty_value;
	}

	/**
	 * Gets the completed deposit waiting period value.
	 *
	 * @param bool $empty_value Empty value to return when not connected or fails to fetch the completed deposit waiting period value.
	 *
	 * @return bool The completed deposit waiting period value or default value.
	 */
	public function get_deposit_completed_waiting_period( bool $empty_value = false ): bool {
		try {
			if ( $this->account->is_stripe_connected() ) {
				return $this->account->get_deposit_completed_waiting_period();
			}
		} catch ( \Exception $e ) {
			Logger::error( 'Failed to get the deposit waiting period value.' . $e );
		}
		return $empty_value;
	}

	/**
	 * Generates markup for the settings screen.
	 */
	public function output_payments_settings_screen() {
		// hiding the save button because the react container has its own.
		global $hide_save_button;
		$hide_save_button = true;

		if ( ! empty( $_GET['method'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			?>
			<div
				id="wcpay-express-checkout-settings-container"
				data-method-id="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_GET['method'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>"
			></div>
		<?php else : ?>
			<div id="wcpay-account-settings-container"></div>
			<?php
		endif;
	}
}
