/**
 * External dependencies
 */
import { Component } from 'react';
import { __ } from '@wordpress/i18n';
import { Snackbar } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { enableGatewayAfterTosDecline } from '../request.js';
import './style.scss';

export default class TosSnackbar extends Component {
	constructor() {
		super();

		this.state = {
			visible: true,
		};
	}

	componentDidMount() {
		setTimeout( () => {
			this.setState( {
				visible: false,
			} );
		}, 15000 );
	}

	render() {
		const { visible } = this.state;

		if ( ! visible ) {
			return null;
		}

		const actions = [
			{
				label: __( 'Undo', 'woocommerce-payments' ),
				onClick: this.enableGateway.bind( this ),
			},
		];

		return (
			<Snackbar actions={ actions }>
				{ __(
					'Disabled WooCommerce Payments',
					'woocommerce-payments'
				) }
			</Snackbar>
		);
	}

	async enableGateway() {
		// ToDo: Add a busy state!
		const { settingsUrl } = this.props;
		await enableGatewayAfterTosDecline();
		window.location = settingsUrl;
	}
}
