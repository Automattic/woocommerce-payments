/**
 * Internal dependencies
 */
import SessionConnect from 'wcpay/checkout/woopay/connect/session-connect';
import request from 'wcpay/checkout/utils/request';
import { buildAjaxURL } from 'wcpay/payment-request/utils';
import { getConfig } from 'wcpay/utils/checkout';

class WooPayFirstPartyAuth {
	static sessionConnect;

	/**
	 * Initializes the WooPay first-party auth feature.
	 */
	static init() {
		this.getSessionConnect();
	}

	/**
	 * Gets the session connect.
	 *
	 * @return {*} The instance of a WooPay session connect.
	 */
	static getSessionConnect() {
		if ( ! this.sessionConnect ) {
			this.sessionConnect = new SessionConnect();
		}

		return this.sessionConnect;
	}

	/**
	 * Sends session data to WooPay, preemptively.
	 *
	 * @param {Object} data The data to send to WooPay.
	 * @return {Promise<*>} Resolves to the WooPay session response.
	 */
	static async sendPreemptiveSessionDataToWooPay( data ) {
		return this.getSessionConnect().setPreemptiveSessionData( data );
	}

	/**
	 * Gets the WooPay session.
	 *
	 * @param {Object} args The arguments to send to the server.
	 * @return {Promise<Promise<*>|*>} Resolves to the WooPay session response.
	 */
	static async getWooPaySessionFromMerchant( args ) {
		return request(
			buildAjaxURL( getConfig( 'wcAjaxUrl' ), 'get_woopay_session' ),
			args
		);
	}
}

export default WooPayFirstPartyAuth;
