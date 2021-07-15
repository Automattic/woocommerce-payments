/** @format */
/**
 * External dependencies
 */
import { CheckboxControl } from '@wordpress/components';

const CheckboxList = ( { items, checkedIds, onCheckedIdsChange } ) => {
	const onCheckboxChange = ( itemId, isChecked ) => {
		if ( isChecked ) {
			onCheckedIdsChange( [ ...checkedIds, itemId ] );
		} else {
			onCheckedIdsChange( checkedIds.filter( ( id ) => id !== itemId ) );
		}
	};

	return (
		<ul className="checkbox-list">
			{ items.map( ( { id, label } ) => (
				<li key={ id } className="checkbox-list__item">
					<CheckboxControl
						label={ label }
						checked={ checkedIds.includes( id ) }
						onChange={ ( isChecked ) =>
							onCheckboxChange( id, isChecked )
						}
					/>
				</li>
			) ) }
		</ul>
	);
};

export default CheckboxList;
