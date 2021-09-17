/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { Card, CardBody, CardDivider } from '@wordpress/components';
import { __experimentalCreateInterpolateElement as createInterpolateElement } from 'wordpress-element';

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
import EnabledCurrenciesListItemPlaceholder from './list-item-placeholder';
import EnabledCurrenciesModal from './modal';
import SettingsLayout from '../../settings/settings-layout';
import SettingsSection from '../../settings/settings-section';

const EnabledCurrenciesSettingsDescription = () => {
	const LEARN_MORE_URL =
		'https://docs.woocommerce.com/document/payments/currencies/multi-currency-setup/';

	return (
		<>
			<h2>{ __( 'Enabled currencies', 'woocommerce-payments' ) }</h2>
			<p>
				{ createInterpolateElement(
					sprintf(
						__(
							// eslint-disable-next-line max-len
							'Accept payments in multiple currencies. Prices are converted based on exchange rates and rounding rules. <a>Learn more</a>',
							'woocommerce-payments'
						),
						LEARN_MORE_URL
					),
					// eslint-disable-next-line jsx-a11y/anchor-has-content
					{ a: <a href={ LEARN_MORE_URL } /> }
				) }
			</p>
		</>
	);
};

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
		<SettingsLayout displayBanner={ false }>
			<SettingsSection
				Description={ EnabledCurrenciesSettingsDescription }
			>
				<Card className={ `${ classBase }__enabled-currencies` }>
					<CardBody
						className={ `${ classBase }__enabled-currencies-header` }
					>
						<div>{ __( 'Name', 'woocommerce-payments' ) }</div>
						<div>
							{ __( 'Exchange rate', 'woocommerce-payments' ) }
						</div>
						<div />
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
							{ isLoading &&
								[ 1, 2, 3, 4, 5 ].map( ( i ) => (
									<EnabledCurrenciesListItemPlaceholder
										key={ 'loadable-placeholder-' + i }
										isLoading={ 1 }
									></EnabledCurrenciesListItemPlaceholder>
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
			</SettingsSection>
		</SettingsLayout>
	);
};

export default EnabledCurrencies;
