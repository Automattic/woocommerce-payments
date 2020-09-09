/** @format */
/**
 * External dependencies
 */

/**
 * Internal dependencies
 */
import './style.scss';
import SavedCard from './saved-card';

export const SavedCards = ( { cards } ) => {
	return (
		<div class="wcpay-saved-cards">
			{ cards.map( ( card ) => (
				<SavedCard { ...card } />
			) ) }
		</div>
	);
};

export default SavedCards;
