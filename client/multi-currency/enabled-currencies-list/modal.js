/** @format */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button, Modal } from '@wordpress/components';
import { useState, useCallback, useEffect } from '@wordpress/element';
import { HorizontalRule } from '@wordpress/primitives';

/**
 * Internal dependencies
 */
import {
	useAvailableCurrencies,
	useEnabledCurrencies,
	useDefaultCurrency,
} from 'data';
import EnabledCurrenciesModalCheckboxList from './modal-checkbox-list';
import EnabledCurrenciesModalCheckbox from './modal-checkbox';
import './style.scss';
// TODO: This works when saving, but list does not refresh.
// TODO: Should we reset selected currencies on modal close?
// TODO: Need to add a currency search. V2?
// TODO: Update text and classes to reflect currencies.
// TODO: Needs to have the same footer as other modals, like Instant Deposits.
// TODO: Recommended currencies, like in the figma. V2?
const EnabledCurrenciesModal = ( { className } ) => {
	const availableCurrencies = useAvailableCurrencies();
	const availableCurrencyCodes = Object.keys( availableCurrencies );

	const {
		enabledCurrencies,
		submitEnabledCurrenciesUpdate,
	} = useEnabledCurrencies();
	const enabledCurrencyCodes = Object.keys( enabledCurrencies );

	const defaultCurrency = useDefaultCurrency();
	const defaultCurrencyCode = defaultCurrency.code;

	// Need to remove default from available codes array.
	availableCurrencyCodes.splice(
		availableCurrencyCodes.indexOf( defaultCurrencyCode ),
		1
	);

	const [ selectedCurrencies, setSelectedCurrencies ] = useState( {} );

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

	const handleChange = ( currencyCode, enabled ) => {
		setSelectedCurrencies( ( previouslyEnabled ) => ( {
			...previouslyEnabled,
			[ currencyCode ]: enabled,
		} ) );
	};

	const [
		isEnabledCurrenciesModalOpen,
		setIsEnabledCurrenciesModalOpen,
	] = useState( false );

	const handleEnabledCurrenciesAddButtonClick = useCallback( () => {
		setIsEnabledCurrenciesModalOpen( true );
	}, [ setIsEnabledCurrenciesModalOpen ] );

	const handleAddSelectedCancelClick = useCallback( () => {
		setIsEnabledCurrenciesModalOpen( false );
	}, [ setIsEnabledCurrenciesModalOpen ] );

	const handleAddSelectedClick = () => {
		setIsEnabledCurrenciesModalOpen( false );
		const newCurrencies = Object.entries( selectedCurrencies )
			.filter( ( [ , enabled ] ) => enabled )
			.map( ( [ method ] ) => method );
		newCurrencies.push( defaultCurrencyCode );
		newCurrencies.sort();
		submitEnabledCurrenciesUpdate( newCurrencies );
	};
	return (
		<>
			{ isEnabledCurrenciesModalOpen && (
				<Modal
					title={ __(
						'Add enabled currencies',
						'woocommerce-payments'
					) }
					onRequestClose={ handleAddSelectedCancelClick }
				>
					<EnabledCurrenciesModalCheckboxList>
						{ availableCurrencyCodes.map( ( code ) => (
							<EnabledCurrenciesModalCheckbox
								key={ availableCurrencies[ code ].id }
								checked={ selectedCurrencies[ code ] }
								onChange={ handleChange }
								name={ availableCurrencies[ code ].name }
								code={ code }
								flag={ availableCurrencies[ code ].flag }
							/>
						) ) }
					</EnabledCurrenciesModalCheckboxList>
					<HorizontalRule className="woocommerce-payments__payment-method-selector__separator" />
					<div className="woocommerce-payments__payment-method-selector__footer">
						<Button
							isSecondary
							onClick={ handleAddSelectedCancelClick }
						>
							{ __( 'Cancel', 'woocommerce-payments' ) }
						</Button>
						<Button isPrimary onClick={ handleAddSelectedClick }>
							{ __( 'Update selected', 'woocommerce-payments' ) }
						</Button>
					</div>
				</Modal>
			) }
			<Button
				isSecondary
				className={ className }
				onClick={ handleEnabledCurrenciesAddButtonClick }
			>
				{ __( 'Add currencies', 'woocommerce-payments' ) }
			</Button>
		</>
	);
};

export default EnabledCurrenciesModal;
