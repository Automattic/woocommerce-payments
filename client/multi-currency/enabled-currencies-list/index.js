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
import {
	useCurrencies,
	useDefaultCurrency,
	useEnabledCurrencies,
} from 'wcpay/data';
import EnabledCurrenciesList from './list';
import EnabledCurrenciesListItem from './list-item';
import EnabledCurrenciesModal from './modal';

const EnabledCurrencies = () => {
	const { isLoading } = useCurrencies();
	const defaultCurrency = useDefaultCurrency();
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

	const enabledKeys = enabledCurrencies
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
					<div>{ __( 'Exchange rate', 'woocommerce-payments' ) }</div>
				</CardBody>
				<CardDivider />
				<CardBody size={ null }>
					<EnabledCurrenciesList className="enabled-currencies-list">
						{ ! isLoading &&
							enabledCurrencies &&
							enabledKeys.map( ( code ) => (
								<EnabledCurrenciesListItem
									key={ enabledCurrencies[ code ].id }
									currency={ enabledCurrencies[ code ] }
									defaultCurrency={ defaultCurrency }
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
