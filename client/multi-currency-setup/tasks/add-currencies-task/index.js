/**
 * External dependencies
 */
import React, { useContext, useEffect, useState } from 'react';
import { sprintf, __, _n } from '@wordpress/i18n';
import { Button, Card, CardBody } from '@wordpress/components';
import interpolateComponents from 'interpolate-components';
import _ from 'lodash';

/**
 * Internal dependencies
 */
import WizardTaskContext from '../../../additional-methods-setup/wizard/task/context';
import CollapsibleBody from '../../../additional-methods-setup/wizard/collapsible-body';
import WizardTaskItem from '../../wizard/task-item';

import {
	useCurrencies,
	useAvailableCurrencies,
	useEnabledCurrencies,
	useDefaultCurrency,
} from 'wcpay/data';

// eslint-disable-next-line max-len
import EnabledCurrenciesModalCheckboxList from '../../../multi-currency/multi-currency-settings/enabled-currencies-list/modal-checkbox-list';
import EnabledCurrenciesModalCheckbox from '../../../multi-currency/multi-currency-settings/enabled-currencies-list/modal-checkbox';
import Search from 'components/search';

import { LoadableBlock } from '../../../components/loadable';

import { recommendedCurrencyCodes, numberWords } from './constants';
import {
	ConcatenateCurrencyStrings,
	StringRepresentationOfCurrency,
} from './utils';

import './index.scss';

const ContinueButton = ( {
	enabledCurrencyCodes,
	selectedCurrencyCodes,
	selectedCurrencyCodesLength,
	isSaving,
	submitEnabledCurrenciesUpdate,
	setCompleted,
	setSaving,
} ) => {
	const handleContinueClick = () => {
		setSaving( true );
		submitEnabledCurrenciesUpdate(
			[ ...enabledCurrencyCodes, ...selectedCurrencyCodes ].sort()
		);
		setSaving( false );
		setCompleted(
			{
				initialCurrencies: enabledCurrencyCodes,
			},
			'multi-currency-settings'
		);
	};

	return (
		<Button
			isBusy={ isSaving }
			disabled={ isSaving || 1 > selectedCurrencyCodesLength }
			onClick={ handleContinueClick }
			isPrimary
		>
			{ 0 === selectedCurrencyCodesLength
				? __( 'Add currencies', 'woocommerce-payments' )
				: sprintf(
						_n(
							'Add %s currency',
							'Add %s currencies',
							selectedCurrencyCodesLength,
							'woocommerce-payments'
						),
						selectedCurrencyCodesLength
				  ) }
		</Button>
	);
};

const AddCurrenciesTask = () => {
	const { isLoading } = useCurrencies();
	const [ isSaving, setSaving ] = useState( false );
	const { isActive, setCompleted } = useContext( WizardTaskContext );

	const {
		enabledCurrencies,
		submitEnabledCurrenciesUpdate,
	} = useEnabledCurrencies();

	const availableCurrencies = useAvailableCurrencies();
	const defaultCurrency = useDefaultCurrency();
	const availableCurrencyCodes = Object.keys( availableCurrencies );
	const enabledCurrencyCodes = Object.keys( enabledCurrencies );
	const defaultCurrencyCode = defaultCurrency.code;

	const visibleCurrencyCodes = availableCurrencyCodes.filter(
		( code ) => ! enabledCurrencyCodes.includes( code )
	);

	const visibleRecommendedCurrencyCodes = recommendedCurrencyCodes.filter(
		( code ) => visibleCurrencyCodes.includes( code )
	);

	const [ selectedCurrencyCodes, setSelectedCurrencyCodes ] = useState(
		visibleRecommendedCurrencyCodes
	);

	useEffect(
		() => {
			// This is important because when the task moves on to the next task,
			// selectedCurrencyCodes seems to be refilled with the remaining
			// recommended currencies. To prevent that, only set the selected currencies
			// if the task is active.
			if ( isActive ) {
				setSelectedCurrencyCodes( visibleRecommendedCurrencyCodes );
			}
		},
		/* eslint-disable-next-line react-hooks/exhaustive-deps */
		[ visibleRecommendedCurrencyCodes.length ]
	);

	const selectedCurrencyCodesLength = selectedCurrencyCodes.length;

	// Currency checkbox state change event
	const handleChange = ( currencyCode, enabled ) => {
		if ( enabled ) {
			setSelectedCurrencyCodes( [
				...selectedCurrencyCodes,
				currencyCode,
			] );
		} else {
			setSelectedCurrencyCodes(
				_.without( selectedCurrencyCodes, currencyCode )
			);
		}
	};

	// Search component
	const [ searchText, setSearchText ] = useState( '' );
	const handleSearchChange = ( event ) => {
		setSearchText( event.target.value );
	};
	const filteredCurrencyCodes = ! searchText
		? visibleCurrencyCodes.filter(
				( code ) => ! recommendedCurrencyCodes.includes( code )
		  )
		: visibleCurrencyCodes.filter( ( code ) => {
				const { symbol, name } = availableCurrencies[ code ];
				return (
					-1 <
					`${ symbol } ${ code } ${ name }`
						.toLocaleLowerCase()
						.indexOf( searchText.toLocaleLowerCase() )
				);
		  } );

	const displayCurrencyCheckbox = ( code ) =>
		availableCurrencyCodes.length && (
			<EnabledCurrenciesModalCheckbox
				key={ 'currency-checkbox-' + availableCurrencies[ code ].id }
				checked={ selectedCurrencyCodes.includes( code ) }
				onChange={ handleChange }
				currency={ availableCurrencies[ code ] }
			/>
		);

	return (
		<WizardTaskItem
			className="add-currencies-task"
			title={ __( 'Add currencies', 'woocommerce-payments' ) }
			visibleDescription={ sprintf(
				_n(
					'%s currency added',
					'%s currencies added',
					selectedCurrencyCodesLength,
					'woocommerce-payments'
				),
				10 > selectedCurrencyCodesLength
					? _.capitalize( numberWords[ selectedCurrencyCodesLength ] )
					: selectedCurrencyCodesLength
			) }
			index={ 1 }
		>
			<CollapsibleBody>
				<p className="wcpay-wizard-task__description-element is-muted-color">
					{ ! isLoading &&
						interpolateComponents( {
							mixedString: __(
								"Add currencies so international customers can shop and pay in their local currency. Your store's " +
									'default currency is {{storeCurrencyText /}}.',
								'woocommerce-payments'
							),
							components: {
								storeCurrencyText: (
									<strong>
										{ StringRepresentationOfCurrency(
											defaultCurrency
										) }
									</strong>
								),
							},
						} ) }
				</p>
				{ 1 < enabledCurrencyCodes.length && (
					<p className="wcpay-wizard-task__description-element is-muted-color">
						{ interpolateComponents( {
							mixedString: __(
								"You've already added {{enabledCurrenciesText /}} to your store.",
								'woocommerce-payments'
							),
							components: {
								enabledCurrenciesText: (
									<strong>
										{ ConcatenateCurrencyStrings(
											enabledCurrencyCodes,
											defaultCurrencyCode,
											availableCurrencies
										) }
									</strong>
								),
							},
						} ) }
					</p>
				) }
				<Card className="add-currencies-task__currency-selector-wrapper">
					<CardBody>
						<div className="add-currencies-task__search">
							<Search
								value={ searchText }
								placeholder={ __(
									'Search currencies',
									'woocommerce-payments'
								) }
								onChange={ handleSearchChange }
							/>
						</div>
						<div className={ 'add-currencies-task__separator' } />
						{ searchText && (
							/* translators: %1: filtered currencies count */
							<h4>
								{ sprintf(
									__(
										'Search results (%1$d currencies)',
										'woocommerce-payments'
									),
									filteredCurrencyCodes.length
								) }
							</h4>
						) }
						<LoadableBlock
							numLines={ 30 }
							isLoading={ isLoading && availableCurrencies }
						>
							<div className="add-currencies-task__content">
								<EnabledCurrenciesModalCheckboxList>
									{ ! searchText &&
									visibleRecommendedCurrencyCodes.length ? (
										<>
											<li>
												<h4>
													{ __(
														'Recommended currencies',
														'woocommerce-payments'
													) }
												</h4>
											</li>
											{ visibleRecommendedCurrencyCodes.map(
												displayCurrencyCheckbox
											) }
											<li
												className={
													'add-currencies-task__separator'
												}
											/>
										</>
									) : (
										''
									) }
									{ ! searchText && (
										<li className="add-currencies-task__available-currencies">
											<h4>
												{ __(
													'All currencies',
													'woocommerce-payments'
												) }
											</h4>
										</li>
									) }
									{ filteredCurrencyCodes.map(
										displayCurrencyCheckbox
									) }
								</EnabledCurrenciesModalCheckboxList>
							</div>
						</LoadableBlock>
					</CardBody>
				</Card>
				<LoadableBlock
					numLines={ 5 }
					isLoading={ isLoading && availableCurrencies }
				>
					<ContinueButton
						enabledCurrencyCodes={ enabledCurrencyCodes }
						selectedCurrencyCodes={ selectedCurrencyCodes }
						selectedCurrencyCodesLength={
							selectedCurrencyCodesLength
						}
						isSaving={ isSaving }
						submitEnabledCurrenciesUpdate={
							submitEnabledCurrenciesUpdate
						}
						setCompleted={ setCompleted }
						setSaving={ setSaving }
					/>
				</LoadableBlock>
			</CollapsibleBody>
		</WizardTaskItem>
	);
};

export default AddCurrenciesTask;
