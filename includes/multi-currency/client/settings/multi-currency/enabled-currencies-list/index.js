/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';

/**
 * Internal dependencies
 */
import {
	ExternalLink,
	Card,
	CardBody,
	CardDivider,
} from '@wordpress/components';
import './style.scss';
import { useCurrencies, useEnabledCurrencies } from 'multi-currency/data';
import EnabledCurrenciesList from './list';
import EnabledCurrenciesListItem from './list-item';
import EnabledCurrenciesListItemPlaceholder from './list-item-placeholder';
import EnabledCurrenciesModal from './modal';
import { SettingsSection } from 'multi-currency/interface/components';

const EnabledCurrenciesSettingsDescription = () => (
	<>
		<h2>{ __( 'Enabled currencies', 'woocommerce-payments' ) }</h2>
		<p>
			{ createInterpolateElement(
				__(
					'Accept payments in multiple currencies. Prices are converted ' +
						'based on exchange rates and rounding rules. <learnMoreLink>' +
						'Learn more</learnMoreLink>',
					'woocommerce-payments'
				),
				{
					learnMoreLink: (
						// @ts-expect-error: children is provided when interpolating the component
						<ExternalLink href="https://woocommerce.com/document/woopayments/currencies/multi-currency-setup/#enabled-currencies" />
					),
				}
			) }
		</p>
	</>
);

const EnabledCurrencies = () => {
	const { isLoading } = useCurrencies();
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
		<SettingsSection
			description={ EnabledCurrenciesSettingsDescription }
			className="multi-currency-settings-enabled-currencies-section"
		>
			<Card className={ `${ classBase }__enabled-currencies` }>
				<CardBody
					className={ `${ classBase }__enabled-currencies-header` }
				>
					<div>{ __( 'Name', 'woocommerce-payments' ) }</div>
					<div>{ __( 'Exchange rate', 'woocommerce-payments' ) }</div>
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
		</SettingsSection>
	);
};

export default EnabledCurrencies;
