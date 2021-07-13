/**
 * External dependencies
 */
import React, { useCallback, useContext } from 'react';
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

/**
 * Internal dependencies
 */
import WizardTaskContext from '../wizard/task/context';
import CollapsibleBody from '../wizard/collapsible-body';
import WizardTaskItem from '../wizard/task-item';
import WcPayUpeContext from '../../settings/wcpay-upe-toggle/context';

const EnableUpePreviewTask = () => {
	const { setIsUpeEnabled, status } = useContext( WcPayUpeContext );

	const { setCompleted } = useContext( WizardTaskContext );

	const handleContinueClick = useCallback( () => {
		setIsUpeEnabled( true ).then( () => {
			setCompleted( true, 'add-payment-methods' );
		} );
	}, [ setIsUpeEnabled, setCompleted ] );

	return (
		<WizardTaskItem
			title={ __(
				'Enable the new WooCommerce Payments checkout experience',
				'woocommerce-payments'
			) }
			index={ 1 }
		>
			<CollapsibleBody>
				<p>
					{ __(
						'Get early access to additional payment methods and an improved checkout experience, ' +
							'coming soon to WooCommerce payments.',
						'woocommerce-payments'
					) }
				</p>
				<Button
					isBusy={ 'pending' === status }
					disabled={ 'pending' === status }
					onClick={ handleContinueClick }
					isPrimary
				>
					{ __( 'Enable', 'woocommerce-payments' ) }
				</Button>
			</CollapsibleBody>
		</WizardTaskItem>
	);
};

export default EnableUpePreviewTask;
