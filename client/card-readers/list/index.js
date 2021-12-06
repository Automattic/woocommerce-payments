/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { Card, CardBody, CardDivider } from '@wordpress/components';

/**
 * Internal dependencies
 */
import SettingsSection from 'wcpay/settings//settings-section';
import SettingsLayout from 'wcpay/settings//settings-layout';
import LoadableSettingsSection from 'wcpay/settings//loadable-settings-section';
import CardReaderListItem from './list-item';
import { useReaders } from 'wcpay/data';

const ReadersListDescription = () => (
	<>
		<h2>{ __( 'Connected card readers', 'woocommerce-payments' ) }</h2>
		<p>
			{ __(
				'Card readers are marked as active if they’ve processed one or more transactions durring the current billing cycle. ' +
					'To connect or disconnect card readers, use the WooCommerce Payments mobile application.',
				'woocommerce-payments'
			) }
		</p>
	</>
);

const ReadersList = () => {
	const { readers, isLoading } = useReaders( 10 );

	return (
		<SettingsLayout displayBanner={ false }>
			<SettingsSection Description={ ReadersListDescription }>
				<LoadableSettingsSection numLines={ 20 }>
					<Card className="card-readers-list__wrapper">
						<CardBody className="card-readers-list__header">
							<div className="card-readers-list__header-id">
								{ __( 'Reader ID', 'woocommerce-payments' ) }
							</div>
							<div className="card-readers-list__header-model">
								{ __( 'Model', 'woocommerce-payments' ) }
							</div>
							<div className="card-readers-list__header-status">
								{ __( 'Status', 'woocommerce-payments' ) }
							</div>
						</CardBody>
						<CardDivider />
						<CardBody size={ null }>
							<ul>
								{ ! isLoading &&
									Object.entries(
										readers
									).map( ( [ index, reader ] ) => (
										<CardReaderListItem
											key={ index }
											reader={ reader }
										/>
									) ) }
							</ul>
						</CardBody>
					</Card>
				</LoadableSettingsSection>
			</SettingsSection>
		</SettingsLayout>
	);
};

export default ReadersList;
