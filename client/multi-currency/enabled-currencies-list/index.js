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
import './style.scss';
import { useCurrencies, useEnabledCurrencies } from 'data';
import EnabledCurrenciesList from './list';
import EnabledCurrenciesListItem from './list-item';
import EnabledCurrenciesModal from './modal';

// TODO: Loading placeholders needed?
// TODO: useEnabledCurrencies only works if you do useCurrencies first.
// TODO: Deleting works, but the list does not refresh.
const EnabledCurrencies = () => {
	const { currencies } = useCurrencies();
	const {
		enabledCurrencies,
		submitEnabledCurrenciesUpdate,
	} = useEnabledCurrencies();
	const classBase = 'wcpay-multi-currency';

	const handleDeleteClick = ( code ) => {
		const newCurrencies = Object.keys( enabledCurrencies );
		newCurrencies.splice( newCurrencies.indexOf( code ), 1 );
		submitEnabledCurrenciesUpdate( newCurrencies );
	};

	// TODO: currencies.enabled is used here to appease linters, see above about useCurrencies.
	const enabledKeys = currencies.enabled
		? Object.keys( enabledCurrencies )
		: [];

	return (
		<>
			<Card className={ `${ classBase }__enabled-currencies` }>
				<CardBody
					className={ `${ classBase }__enabled-currencies-header` }
				>
					<div>
						{ __( 'Enabled Currencies', 'woocommerce-payments' ) }
					</div>
				</CardBody>
				<CardDivider />
				<CardBody size={ null }>
					<EnabledCurrenciesList className="enabled-currencies-list">
						{ enabledCurrencies &&
							enabledKeys.map( ( code ) => (
								<EnabledCurrenciesListItem
									key={ enabledCurrencies[ code ].id }
									currency={ enabledCurrencies[ code ] }
									onDeleteClick={
										enabledCurrencies[ code ].is_default
											? undefined
											: handleDeleteClick
									}
								/>
							) ) }
					</EnabledCurrenciesList>
				</CardBody>
				<CardDivider />
				<CardBody
					className={ `${ classBase }__available-currencies-container` }
				>
					{ enabledCurrencies && (
						<EnabledCurrenciesModal
							className={ `${ classBase }__available-currencies-modal` }
						/>
					) }
				</CardBody>
			</Card>
		</>
	);
};

export default EnabledCurrencies;
