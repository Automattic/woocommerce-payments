/** @format */
/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { Button, Modal } from '@wordpress/components';
import { useState, useCallback, useEffect } from '@wordpress/element';

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
import Search from 'components/search';
import './style.scss';

// TODO: This works when saving, but list does not refresh.
// TODO: Should we reset selected currencies on modal close?
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

	const [
		isEnabledCurrenciesModalOpen,
		setIsEnabledCurrenciesModalOpen,
	] = useState( false );

	const handleEnabledCurrenciesAddButtonClick = useCallback( () => {
		setIsEnabledCurrenciesModalOpen( true );
	}, [ setIsEnabledCurrenciesModalOpen ] );

	const handleAddSelectedCancelClick = useCallback( () => {
		setIsEnabledCurrenciesModalOpen( false );
		setSearchText( '' );
	}, [ setIsEnabledCurrenciesModalOpen ] );

	const handleAddSelectedClick = () => {
		setIsEnabledCurrenciesModalOpen( false );
		setSearchText( '' );
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
					className="add-enabled-currencies-modal"
				>
					<div className="add-enabled-currencies-modal__search">
						<Search
							value={ searchText }
							placeholder={ __(
								'Search currencies',
								'woocommerce-payments'
							) }
							onChange={ handleSearchChange }
						/>
					</div>
					<h3>
						{ searchText
							? /* translators: %1: filtered currencies count */
							  sprintf(
									__(
										'Search results (%1$d currencies)',
										'woocommerce-payments'
									),
									filteredCurrencyCodes.length
							  )
							: __( 'All currencies', 'woocommerce-payments' ) }
					</h3>
					<div className="add-enabled-currencies-modal__content">
						<EnabledCurrenciesModalCheckboxList>
							{ filteredCurrencyCodes.map( ( code ) => (
								<EnabledCurrenciesModalCheckbox
									key={ availableCurrencies[ code ].id }
									checked={ selectedCurrencies[ code ] }
									onChange={ handleChange }
									currency={ availableCurrencies[ code ] }
								/>
							) ) }
						</EnabledCurrenciesModalCheckboxList>
					</div>
					<div className="add-enabled-currencies-modal__footer">
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
