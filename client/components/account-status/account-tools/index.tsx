/**
 * External dependencies
 */
import React, { useState } from 'react';
import { Button, CardDivider } from '@wordpress/components';
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
import strings from './strings';
import './styles.scss';
import ResetAccountModal from 'wcpay/overview/modal/reset-account';
import { trackAccountReset } from 'wcpay/onboarding/tracking';
import { recordEvent } from 'wcpay/tracks';

interface Props {
	accountLink: string;
	detailsSubmitted: boolean;
	openModal: () => void;
}

const handleReset = () => {
	trackAccountReset();

	window.location.href = addQueryArgs( wcpaySettings.connectUrl, {
		'wcpay-reset-account': 'true',
		source: 'wcpay-reset-account', // Overwrite any existing source because we are starting over.
	} );
};

export const AccountTools: React.FC< Props > = ( props: Props ) => {
	const source = 'wcpay-finish-setup-tool';
	const accountLink = addQueryArgs( props.accountLink, {
		source,
	} );
	const detailsSubmitted = props.detailsSubmitted;
	const [ modalVisible, setModalVisible ] = useState( false );

	return (
		<>
			<div className="account-tools">
				<CardDivider />
				<h4>{ strings.title }</h4>
				<p>{ strings.description }</p>
				{ /* Use wrapping div to keep buttons grouped together. */ }
				<div className="account-tools__actions">
					{ ! detailsSubmitted && (
						<Button
							variant={ 'secondary' }
							onClick={ () =>
								recordEvent(
									'wcpay_account_details_link_clicked',
									{
										source,
									}
								)
							}
							href={ accountLink }
						>
							{ strings.finish }
						</Button>
					) }
					<Button
						variant={ detailsSubmitted ? 'secondary' : 'tertiary' }
						onClick={ () => setModalVisible( true ) }
					>
						{ strings.reset }
					</Button>
				</div>
			</div>

			<ResetAccountModal
				isVisible={ modalVisible }
				onDismiss={ () => setModalVisible( false ) }
				onSubmit={ handleReset }
			/>
		</>
	);
};
