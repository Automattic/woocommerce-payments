/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import Page from 'components/page';
import ReadersList from './list';
import ReceiptSettings from './settings';
import { TabPanel } from '@wordpress/components';

import './style.scss';

export const ConnectedReaders = () => {
	return (
		<Page>
			<TabPanel
				className="wcpay-card-readers-page"
				activeClass="active-tab"
				tabs={ [
					{
						name: 'connected-readers',
						title: __(
							'Connected readers',
							'woocommerce-payments'
						),
						className: 'connected-readers-list',
					},
					{
						name: 'receipt-details',
						title: __( 'Receipt details', 'woocommerce-payments' ),
						className: 'connected-readers-receipt-details',
					},
				] }
			>
				{ ( tab ) => {
					if ( 'receipt-details' === tab.name ) {
						return <ReceiptSettings />;
					}

					return <ReadersList />;
				} }
			</TabPanel>
		</Page>
	);
};

export default ConnectedReaders;
