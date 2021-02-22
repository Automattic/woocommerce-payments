/** @format **/

/**
 * External dependencies
 */
import { Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const InstantDepositSubmitNotice = ( { notice } ) => {
	const type = 'success' === notice ? 'success' : 'error';
	let message = '';

	if ( 'success' === type ) {
		message = __(
			'The deposit was successful! Please close this window to refresh the page.',
			'woocommerce-payments'
		);
	} else {
		// TODO: supply proper error notices.
		switch ( notice.code ) {
			default:
				message = __(
					'There was an error, please try again.',
					'woocommerce-payments'
				);
		}
	}

	return (
		<Notice status={ type } isDismissible={ false }>
			<p>{ message }</p>
		</Notice>
	);
};

export default InstantDepositSubmitNotice;
