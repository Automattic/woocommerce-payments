/**
 * External dependencies
 */
import React, {
	useCallback,
	useContext,
	useEffect,
	useMemo,
	useState,
} from 'react';
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

import EnabledCurrenciesModalCheckboxList from '../../../multi-currency/enabled-currencies-list/modal-checkbox-list';
import EnabledCurrenciesModalCheckbox from '../../../multi-currency/enabled-currencies-list/modal-checkbox';
import Search from 'components/search';

import { LoadableBlock } from '../../../components/loadable';
import LoadableSettingsSection from '../../../settings/loadable-settings-section';

import { recommendedCurrencyCodes, numberWords } from './constants';
import {
	ConcatenateCurrencyStrings,
	StringRepresentationOfCurrency,
} from './utils';

import './index.scss';

const AddCurrenciesTask = () => {
	const { isLoading } = useCurrencies();
	const { setCompleted, isActive } = useContext( WizardTaskContext );
	const [ status, setStatus ] = useState( 'resolved' );

	const {
		enabledCurrencies,
		submitEnabledCurrenciesUpdate,
	} = useEnabledCurrencies();

	const availableCurrencies = useAvailableCurrencies();
	const defaultCurrency = useDefaultCurrency();
	const availableCurrencyCodes = Object.keys( availableCurrencies );
	const enabledCurrencyCodes = Object.keys( enabledCurrencies );
	const defaultCurrencyCode = defaultCurrency.code;

	// Remove store currency from available currencies.
	availableCurrencyCodes.splice(
		availableCurrencyCodes.indexOf( defaultCurrencyCode ),
		1
	);

	// Remove store currency from recommended currencies.
	if ( recommendedCurrencyCodes.includes( defaultCurrencyCode ) ) {
		recommendedCurrencyCodes.splice(
			recommendedCurrencyCodes.indexOf( defaultCurrencyCode ),
			1
		);
	}

	// Remove already enabled currencies from available currencies.
	enabledCurrencyCodes.map( ( code ) => {
		if ( recommendedCurrencyCodes.includes( code ) )
			recommendedCurrencyCodes.splice(
				recommendedCurrencyCodes.indexOf( code ),
				1
			);
		if ( availableCurrencyCodes.includes( code ) )
			availableCurrencyCodes.splice(
				availableCurrencyCodes.indexOf( code ),
				1
			);
		return code;
	} );

	// Now, the available currencies list will only contain the selectable ones.
	// Prefill the selected currency object.
	const [ activatedCount, setActivatedCount ] = useState( 0 );
	const [ selectedCurrencies, setSelectedCurrencies ] = useState( {} );
	useEffect( () => {
		setSelectedCurrencies(
			// Prefill recommended currency codes as selected, but changeable.
			availableCurrencyCodes.reduce(
				( acc, value ) => {
					acc[ value ] = recommendedCurrencyCodes.includes( value );
					return acc;
				},
				// Prefill enabled currency codes as selected, but not changeable.
				enabledCurrencyCodes.reduce( ( acc, value ) => {
					acc[ value ] = true;
					return acc;
				}, {} )
			)
		);
		/* eslint-disable react-hooks/exhaustive-deps */
	}, [
		JSON.stringify( availableCurrencyCodes ),
		JSON.stringify( enabledCurrencyCodes ),
	] );
	/* eslint-enable react-hooks/exhaustive-deps */

	// Currency checkbox state change event
	const handleChange = ( currencyCode, enabled ) => {
		setSelectedCurrencies( ( previouslyEnabled ) => ( {
			...previouslyEnabled,
			[ currencyCode ]: enabled,
		} ) );
	};

	// This state is used for displaying the checked currencies count on the button
	// and on the description text displayed after adding those checked currencies.
	useEffect( () => {
		setActivatedCount(
			isActive
				? Object.values( selectedCurrencies ).filter( Boolean ).length -
						enabledCurrencyCodes.length
				: activatedCount
		);
	}, [ selectedCurrencies, activatedCount, enabledCurrencyCodes, isActive ] );

	// Search component
	const [ searchText, setSearchText ] = useState( '' );
	const handleSearchChange = ( event ) => {
		setSearchText( event.target.value );
	};
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

	const ContinueButton = () => {
		const checkedCurrencies = useMemo(
			() =>
				Object.entries( selectedCurrencies )
					.map( ( [ currency, enabled ] ) => enabled && currency )
					.filter( Boolean ),
			[]
		);

		const handleContinueClick = useCallback( () => {
			setStatus( 'pending' );
			checkedCurrencies.sort();
			submitEnabledCurrenciesUpdate( checkedCurrencies );
			setStatus( 'resolved' );
			setCompleted(
				{
					initialCurrencies: enabledCurrencies,
				},
				'multi-currency-settings'
			);
		}, [ checkedCurrencies ] );

		return (
			<Button
				isBusy={ 'pending' === status }
				disabled={ 'pending' === status || 1 > activatedCount }
				onClick={ handleContinueClick }
				isPrimary
			>
				{ 0 === activatedCount
					? __( 'Add Currencies', 'woocommerce-payments' )
					: sprintf(
							_n(
								'Add %s currency',
								'Add %s currencies',
								activatedCount,
								'woocommerce-payments'
							),
							activatedCount
					  ) }
			</Button>
		);
	};

	return (
		<WizardTaskItem
			className="add-currencies-task"
			title={ __( 'Add currencies', 'woocommerce-payments' ) }
			visibleDescription={ sprintf(
				_n(
					'%s currency added',
					'%s currencies added',
					activatedCount,
					'woocommerce-payments'
				),
				10 > activatedCount
					? _.capitalize( numberWords[ activatedCount ] )
					: activatedCount
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
						<LoadableBlock numLines={ 30 } isLoading={ isLoading }>
							<LoadableSettingsSection numLines={ 30 }>
								<div className="add-currencies-task__content">
									<EnabledCurrenciesModalCheckboxList>
										{ ! searchText &&
										recommendedCurrencyCodes.length ? (
											<li>
												<h4>
													{ __(
														'Recommended Currencies',
														'woocommerce-payments'
													) }
												</h4>
											</li>
										) : (
											''
										) }
										{ ! searchText &&
										recommendedCurrencyCodes.length &&
										availableCurrencyCodes.length
											? recommendedCurrencyCodes.map(
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
											  )
											: '' }
										{ ! searchText &&
										recommendedCurrencyCodes.length ? (
											<div
												className={
													'add-currencies-task__separator'
												}
											>
												&nbsp;
											</div>
										) : (
											''
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
				<LoadableBlock numLines={ 5 } isLoading={ isLoading }>
					<ContinueButton />
				</LoadableBlock>
			</CollapsibleBody>
		</WizardTaskItem>
	);
};

export default AddCurrenciesTask;
