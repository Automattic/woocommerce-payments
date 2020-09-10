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

const getWidthClassName = ( width ) => {
	if ( width > 651 ) {
		return 'is-large';
	} else if ( width > 374 ) {
		return 'is-medium';
	} else if ( width ) {
		return 'is-small';
	}
};

export const SavedCards = ( { cards } ) => {
	const [ resizeListener, { width } ] = useResizeObserver();
	return (
		<div className={ `wcpay-saved-cards ${ getWidthClassName( width ) }` }>
			{ resizeListener }
			{ cards.map( ( card ) => (
				<SavedCard
					{ ...card }
					className={ getWidthClassName( width ) }
				/>
			) ) }
		</div>
	);
};

export default SavedCards;
