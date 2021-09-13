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
	const enabledCurrenciesLength = enabledCurrencyCodes.length;
	const defaultCurrencyCode = defaultCurrency.code;

	// Prevent the enabled currencies and the store currency from displaying
	// (enabled currencies include the default currency too).
	const hiddenCurrencies = [ ...enabledCurrencyCodes ];

	// Prefill the selected currency object.
	const [ activatedCount, setActivatedCount ] = useState( 0 );
	const [ selectedCurrencies, setSelectedCurrencies ] = useState( {} );
	useEffect( () => {
		setSelectedCurrencies(
			availableCurrencyCodes.reduce( ( acc, value ) => {
				acc[ value ] = [
					...recommendedCurrencyCodes,
					...enabledCurrencyCodes,
					defaultCurrencyCode,
				].includes( value );
				return acc;
			}, {} )
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

	// Search component
	const [ searchText, setSearchText ] = useState( '' );
	const handleSearchChange = ( event ) => {
		setSearchText( event.target.value );
	};
	const filteredCurrencyCodes = ( ! searchText
		? availableCurrencyCodes
		: availableCurrencyCodes.filter( ( code ) => {
				const { symbol, name } = availableCurrencies[ code ];
				return (
					-1 <
					`${ symbol } ${ code } ${ name }`
						.toLocaleLowerCase()
						.indexOf( searchText.toLocaleLowerCase() )
				);
		  } )
	).filter( ( code ) => {
		// Hide already enabled ones from the search results.
		return ! hiddenCurrencies.includes( code );
	} );

	// This state is used for displaying the checked currencies count on the button
	// and on the description text displayed after adding those checked currencies.
	useEffect( () => {
		if ( isActive ) {
			const activatedCurrenciesCount =
				Object.values( selectedCurrencies ).filter( Boolean ).length -
				enabledCurrenciesLength;
			setActivatedCount(
				0 < activatedCurrenciesCount ? activatedCurrenciesCount : 0
			);
		}
	}, [ selectedCurrencies, isActive, enabledCurrenciesLength ] );

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

	const displayCurrencyCheckbox = ( code ) =>
		hiddenCurrencies.includes( code ) ? null : (
			<EnabledCurrenciesModalCheckbox
				key={ 'currency-checkbox-' + availableCurrencies[ code ].id }
				checked={ selectedCurrencies[ code ] }
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
				{ 1 < enabledCurrenciesLength && (
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
										recommendedCurrencyCodes.filter(
											( code ) =>
												! hiddenCurrencies.includes(
													code
												)
										).length ? (
											<>
												<li>
													<h4>
														{ __(
															'Recommended Currencies',
															'woocommerce-payments'
														) }
													</h4>
												</li>
												{ availableCurrencyCodes.length
													? recommendedCurrencyCodes.map(
															displayCurrencyCheckbox
													  )
													: '' }
												<li
													className={
														'add-currencies-task__separator'
													}
												>
													&nbsp;
												</li>
											</>
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
												return ! searchText
													? ! recommendedCurrencyCodes.includes(
															code
													  )
													: true;
											} )
											.map( displayCurrencyCheckbox ) }
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
