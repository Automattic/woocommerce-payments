/**
 * External dependencies
 */
import React, {
	useCallback,
	useContext,
	useEffect,
	useMemo,
	useState,
	useRef,
} from 'react';
import { sprintf, __, _n } from '@wordpress/i18n';

import { Button, Card, CardBody } from '@wordpress/components';
import interpolateComponents from 'interpolate-components';

/**
 * Internal dependencies
 */
import WizardTaskContext from '../wizard/task/context';
import CollapsibleBody from '../wizard/collapsible-body';
import WizardTaskItem from '../wizard/task-item';

import {
	useAvailableCurrencies,
	useEnabledCurrencies,
	useDefaultCurrency,
} from 'wcpay/data';

import { useSettings } from '../../data';

import EnabledCurrenciesModalCheckboxList from '../../multi-currency/enabled-currencies-list/modal-checkbox-list';
import EnabledCurrenciesModalCheckbox from '../../multi-currency/enabled-currencies-list/modal-checkbox';
import Search from 'components/search';

import { LoadableBlock } from '../../components/loadable';
import LoadableSettingsSection from '../../settings/loadable-settings-section';
import './add-currencies-task.scss';
import _ from 'lodash';

const numberWords = [
	'any',
	'one',
	'two',
	'three',
	'four',
	'five',
	'six',
	'seven',
	'eight',
	'nine',
];

const ContinueButton = ( { currencyState } ) => {
	const { setCompleted } = useContext( WizardTaskContext );
	const {
		enabledCurrencies,
		submitEnabledCurrenciesUpdate,
	} = useEnabledCurrencies();

	const defaultCurrency = useDefaultCurrency();
	const defaultCurrencyCode = defaultCurrency.code;

	const { isSaving } = useSettings();

	const checkedCurrencies = useMemo(
		() =>
			Object.entries( currencyState )
				.map( ( [ currency, enabled ] ) => enabled && currency )
				.filter( Boolean ),
		[ currencyState ]
	);

	const handleContinueClick = useCallback( () => {
		checkedCurrencies.push( defaultCurrencyCode );
		checkedCurrencies.sort();
		submitEnabledCurrenciesUpdate( checkedCurrencies );

		setCompleted(
			{
				initialCurrencies: enabledCurrencies,
			},
			'multi-currency-settings'
		);
	}, [
		checkedCurrencies,
		defaultCurrencyCode,
		submitEnabledCurrenciesUpdate,
		setCompleted,
		enabledCurrencies,
	] );

	const currencyChange =
		Object.keys( enabledCurrencies ).length -
		( checkedCurrencies.length + 1 );
	const currencyChangeAbs = Math.abs( currencyChange );

	return (
		<Button
			isBusy={ isSaving }
			disabled={ isSaving || 1 > checkedCurrencies.length }
			onClick={ handleContinueClick }
			isPrimary
		>
			{ 0 === currencyChange
				? 'Add Currencies'
				: _.capitalize(
						sprintf(
							'%s %s %s',
							0 < currencyChange
								? __( 'remove', 'woocommerce-payments' )
								: __( 'add', 'woocommerce-payments' ),
							10 > currencyChangeAbs
								? numberWords[ currencyChangeAbs ]
								: currencyChangeAbs,
							_n(
								'currency',
								'currencies',
								currencyChangeAbs,
								'woocommerce-payments'
							)
						)
				  ) }
		</Button>
	);
};

const AddCurrenciesTask = () => {
	const availableCurrencies = useAvailableCurrencies();
	const availableCurrencyCodes = Object.keys( availableCurrencies );
	const enabledCurrenciesList = useRef( null );
	const { isActive } = useContext( WizardTaskContext );

	const { enabledCurrencies } = useEnabledCurrencies();

	const enabledCurrencyCodes = Object.keys( enabledCurrencies );

	const defaultCurrency = useDefaultCurrency();
	const defaultCurrencyCode = defaultCurrency.code;

	const recommendedCurrencyCodes = [
		'USD',
		'EUR',
		'JPY',
		'GBP',
		'AUD',
		'CAD',
		'INR',
	];

	// Need to remove default from available codes array.
	availableCurrencyCodes.splice(
		availableCurrencyCodes.indexOf( defaultCurrencyCode ),
		1
	);

	// Need to remove default from recommended codes array too.
	if ( -1 !== recommendedCurrencyCodes.indexOf( defaultCurrencyCode ) ) {
		recommendedCurrencyCodes.splice(
			recommendedCurrencyCodes.indexOf( defaultCurrencyCode ),
			1
		);
	}

	const [ searchText, setSearchText ] = useState( '' );
	const [ selectedCurrencies, setSelectedCurrencies ] = useState( {} );

	const filteredCurrencyCodes = ! searchText
		? availableCurrencyCodes
		: availableCurrencyCodes.filter( ( code ) => {
				const { symbol, name } = availableCurrencies[ code ];
				return (
					-1 <
					`${ symbol } ${ code } ${ name }`
						.toLocaleLowerCase()
						.indexOf( searchText.toLocaleLowerCase() )
				);
		  } );

	useEffect( () => {
		setSelectedCurrencies(
			availableCurrencyCodes.reduce( ( acc, value ) => {
				acc[ value ] = enabledCurrencyCodes.includes( value );
				return acc;
			}, {} )
		);
		/* eslint-disable react-hooks/exhaustive-deps */
	}, [
		JSON.stringify( availableCurrencyCodes ),
		JSON.stringify( enabledCurrencyCodes ),
	] );
	/* eslint-enable react-hooks/exhaustive-deps */

	const handleSearchChange = ( event ) => {
		setSearchText( event.target.value );
	};

	const handleChange = ( currencyCode, enabled ) => {
		setSelectedCurrencies( ( previouslyEnabled ) => ( {
			...previouslyEnabled,
			[ currencyCode ]: enabled,
		} ) );
	};

	const enabledCurrenciesCount = enabledCurrencyCodes.length;

	return (
		<WizardTaskItem
			className="add-currencies-task"
			title={ interpolateComponents( {
				mixedString: __(
					'{{wrapper}}Add Currencies{{/wrapper}}',
					'woocommerce-payments'
				),
				components: {
					wrapper: <span />,
				},
			} ) }
			visibleDescription={ sprintf(
				'%s %s %s',
				10 > enabledCurrenciesCount
					? _.capitalize( numberWords[ enabledCurrenciesCount ] )
					: enabledCurrenciesCount,
				_n(
					'currency',
					'currencies',
					enabledCurrenciesCount,
					'woocommerce-payments'
				),
				__( 'added', 'woocommerce-payments' )
			) }
			index={ 1 }
		>
			<CollapsibleBody>
				<p className="wcpay-wizard-task__description-element is-muted-color">
					{ interpolateComponents( {
						mixedString: __(
							"Add currencies so international customers can shop and pay in their local currency. Your store's " +
								'default currency is {{storeCurrencyText /}}.',
							'woocommerce-payments'
						),
						components: {
							storeCurrencyText: (
								<span>
									{ defaultCurrencyCode &&
										sprintf(
											'%s (%s)',
											defaultCurrency.name,
											defaultCurrencyCode
										) }
								</span>
							),
						},
					} ) }
				</p>
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
						<div className={ 'add-currencies-task__separator' }>
							&nbsp;
						</div>
						{ searchText ? (
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
						) : (
							''
						) }
						<LoadableBlock numLines={ 30 } isLoading={ ! isActive }>
							<LoadableSettingsSection numLines={ 30 }>
								<div
									className="add-currencies-task__content"
									ref={ enabledCurrenciesList }
								>
									<EnabledCurrenciesModalCheckboxList>
										{ ! searchText && (
											<li>
												<h4>
													{ __(
														'Recommended Currencies',
														'woocommerce-payments'
													) }
												</h4>
											</li>
										) }
										{ ! searchText &&
											availableCurrencyCodes.length &&
											recommendedCurrencyCodes.map(
												( code ) => (
													<EnabledCurrenciesModalCheckbox
														key={
															'recommended-' +
															availableCurrencies[
																code
															].id
														}
														checked={
															selectedCurrencies[
																code
															]
														}
														onChange={
															handleChange
														}
														currency={
															availableCurrencies[
																code
															]
														}
													/>
												)
											) }
										{ ! searchText && (
											<div
												className={
													'add-currencies-task__separator'
												}
											>
												&nbsp;
											</div>
										) }
										{ ! searchText && (
											<li className="add-currencies-task__available-currencies">
												<h4>
													{ __(
														'All Currencies',
														'woocommerce-payments'
													) }
												</h4>
											</li>
										) }
										{ filteredCurrencyCodes
											.filter( ( code ) => {
												if ( ! searchText ) {
													return (
														-1 ===
														recommendedCurrencyCodes.indexOf(
															code
														)
													);
												}
												return true;
											} )
											.map( ( code ) => (
												<EnabledCurrenciesModalCheckbox
													key={
														'available-' +
														availableCurrencies[
															code
														].id
													}
													checked={
														selectedCurrencies[
															code
														]
													}
													onChange={ handleChange }
													currency={
														availableCurrencies[
															code
														]
													}
												/>
											) ) }
									</EnabledCurrenciesModalCheckboxList>
								</div>
							</LoadableSettingsSection>
						</LoadableBlock>
					</CardBody>
				</Card>
				<LoadableBlock numLines={ 10 } isLoading={ ! isActive }>
					<ContinueButton currencyState={ selectedCurrencies } />
				</LoadableBlock>
			</CollapsibleBody>
		</WizardTaskItem>
	);
};

export default AddCurrenciesTask;
