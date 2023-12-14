/**
 * External dependencies
 */
import React, { useState } from 'react';
import { render } from '@wordpress/element';

/**
 * Internal dependencies
 */
import strings from './strings';
import { isInTestOrDevMode } from 'utils';
import { Button, CardDivider } from '@wordpress/components';
import './styles.scss';
import ResetAccountModal from 'wcpay/overview/modal/reset-account';
import { trackAccountReset } from 'wcpay/onboarding/tracking';
import { addQueryArgs } from '@wordpress/url';

interface Props {
	accountLink: string;
	openModal: () => void;
}

const handleReset = () => {
	trackAccountReset();

	window.location.href = addQueryArgs( wcpaySettings.connectUrl, {
		'wcpay-reset-account': true,
	} );
};

export const AccountTools: React.FC< Props > = ( props: Props ) => {
	const accountLink = props.accountLink;
	const [ modalVisible, setModalVisible ] = useState( false );

	// if ( isInTestOrDevMode() ) return null;

	return (
		<>
			<div className="account-tools">
				<CardDivider />
				<h4>{ strings.title }</h4>
				<p>{ strings.description }</p>
				{ /* Use wrapping div to keep buttons grouped together. */ }
				<div className="account-tools__actions">
					<Button
						variant={ 'secondary' }
						href={ accountLink }
						target={ '_blank' }
					>
						{ strings.finish }
					</Button>
					<Button
						variant={ 'link' }
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
