/** @format */
/**
 * External dependencies
 */
import { useResizeObserver } from '@wordpress/compose';

/**
 * Internal dependencies
 */
import './style.scss';
import SavedCard from './saved-card';
import { __ } from '@wordpress/i18n';

const getWidthClassName = ( width ) => {
	if ( width > 651 ) {
		return 'is-large';
	} else if ( width > 374 ) {
		return 'is-medium';
	} else if ( width ) {
		return 'is-small';
	}
};

export const SavedCards = ( { cards, customerId } ) => {
	const [ resizeListener, { width } ] = useResizeObserver();
	return (
		<div className={ `wcpay-saved-cards ${ getWidthClassName( width ) }` }>
			{ resizeListener }
			<p className="wcpay-saved-cards__customer-id">
				<span className="label">
					{ __( 'Customer ID:', 'woocommerce-payments' ) }
				</span>{ ' ' }
				{ customerId ||
					__(
						'No customer found for this user',
						'woocommerce-payments'
					) }
			</p>
			<div
				className={ `wcpay-saved-cards__cards-list ${ getWidthClassName(
					width
				) }` }
			>
				{ cards.map( ( card ) => (
					<SavedCard
						key={ card.tokenId }
						{ ...card }
						className={ getWidthClassName( width ) }
					/>
				) ) }
			</div>
		</div>
	);
};

export default SavedCards;
