/** @format **/

/**
 * External dependencies
 */
import { Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const InstantDepositSubmitNotice = ( { error } ) => {
	let message = '';
	// TODO: supply proper error notices.
	switch ( error.code ) {
		default:
			message = __(
				'There was an error, please try again.',
				'woocommerce-payments'
			);
	}

	return (
		<Notice status="error" isDismissible={ false }>
			<p>{ message }</p>
		</Notice>
	);
};

export default InstantDepositSubmitNotice;
