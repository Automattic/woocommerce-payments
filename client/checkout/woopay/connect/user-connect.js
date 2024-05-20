/**
 * Internal dependencies
 */
import WoopayConnect from 'wcpay/checkout/woopay/connect/woopay-connect';

class WooPayUserConnect extends WoopayConnect {
	constructor() {
		super();

		// The initial state of these listeners serve as a placeholder.
		this.listeners = {
			...this.listeners,
			getIsUserLoggedInCallback: () => {},
			getUserEmailCallback: () => {},
		};
	}

	/**
	 * Checks if the user is logged in.
	 *
	 * @return {Promise<bool>} Resolves to true if the user is logged in.
	 */
	async isUserLoggedIn() {
		return await this.sendMessageAndListenWith(
			{ action: 'getIsUserLoggedIn' },
			'getIsUserLoggedInCallback'
		);
	}

	/**
	 * Retrieves the email of the logged in user.
	 *
	 * @return {Promise<string>} Resolves to the user's email if they're logged in, and an empty string otherwise.
	 */
	async getUserEmail() {
		return await this.sendMessageAndListenWith(
			{ action: 'getUserEmail' },
			'getUserEmailCallback'
		);
	}

	/**
	 * Handles the callback from the WooPayConnectIframe.
	 *
	 * @param {Object} data The data from the WooPayConnectIframe.
	 */
	callbackFn( data ) {
		super.callbackFn( data );

		switch ( data.action ) {
			case 'get_is_user_logged_in_success':
				this.listeners.getIsUserLoggedInCallback( data.value );
				break;
			case 'get_user_email_success':
				this.listeners.getUserEmailCallback( data.value );
				break;
		}
	}
}

export default WooPayUserConnect;
