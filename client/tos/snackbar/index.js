/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Snackbar } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { enableGatewayAfterTosDecline } from '../request.js';
import './style.scss';

const TosSnackbar = ( { settingsUrl } ) => {
	const [ visible, setVisible ] = useState( true );
	const [ busy, setBusy ] = useState( false );

	const enableGateway = async () => {
		setBusy( true );
		await enableGatewayAfterTosDecline();
		window.location = settingsUrl;
	};

	// Hide the snackbar after 15 seconds.
	useEffect( () => {
		setTimeout( () => {
			setVisible( false );
		}, 15000 );
	} );

	if ( ! visible ) {
		return null;
	}

	if ( busy ) {
		return (
			<Snackbar>
				{ __(
					'Enabling WooCommerce Payments…',
					'woocommerce-payments'
				) }
			</Snackbar>
		);
	}

	const actions = [
		{
			label: __( 'Undo', 'woocommerce-payments' ),
			onClick: enableGateway,
			isBusy: busy,
		},
	];

	return (
		<Snackbar actions={ actions }>
			{ __( 'Disabled WooCommerce Payments', 'woocommerce-payments' ) }
		</Snackbar>
	);
};

export default TosSnackbar;
