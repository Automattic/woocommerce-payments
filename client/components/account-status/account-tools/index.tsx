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

interface Props {
	accountLink: string;
	openModal: () => void;
}

const renderModal = () => {
	let container = document.querySelector( '#wcpay-reset-account-container' );

	if ( ! container ) {
		container = document.createElement( 'div' );
		container.id = 'wcpay-reset-account-container';
		document.body.appendChild( container );
	}

	render( <ResetAccountModal />, container );
};

const resetAccount = () => {
	// TODO: Should we have a generic function for rendering a modal in utils?
	renderModal();
};

export const AccountTools: React.FC< Props > = ( props: Props ) => {
	const accountLink = props.accountLink;
	const [ modalVisible, setModalVisible ] = useState( true );

	// if ( isInTestOrDevMode() ) return null;

	return (
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
				<Button variant={ 'link' } onClick={ resetAccount }>
					{ strings.reset }
				</Button>
			</div>
		</div>
	);
};
