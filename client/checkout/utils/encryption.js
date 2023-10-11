/**
 * External dependencies
 */
import Utf8 from 'crypto-js/enc-utf8';
import AES from 'crypto-js/aes';
import Pkcs7 from 'crypto-js/pad-pkcs7';
import { getConfig } from 'wcpay/utils/checkout';

export const decryptClientSecret = function (
	encryptedValue,
	stripeAccountId = null
) {
	if (
		getConfig( 'isClientEncryptionEnabled' ) &&
		encryptedValue.length > 3 &&
		encryptedValue.slice( 0, 3 ) !== 'pi_' &&
		encryptedValue.slice( 0, 5 ) !== 'seti_'
	) {
		stripeAccountId = stripeAccountId || getConfig( 'accountId' );
		return Utf8.stringify(
			AES.decrypt(
				encryptedValue,
				Utf8.parse( stripeAccountId.slice( 5 ) ),
				{
					iv: Utf8.parse( 'WC'.repeat( 8 ) ),
					padding: Pkcs7,
				}
			)
		);
	}
	return encryptedValue;
};
