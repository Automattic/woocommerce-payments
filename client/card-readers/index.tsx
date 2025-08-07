/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { TabPanel } from '@wordpress/components';

/**
 * Internal dependencies.
 */
import Page from 'components/page';
import ReadersList from './list';
import './style.scss';

export const ConnectedReaders = (): JSX.Element => {
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
				] }
			>
				{ () => <ReadersList /> }
			</TabPanel>
		</Page>
	);
};

export default ConnectedReaders;
