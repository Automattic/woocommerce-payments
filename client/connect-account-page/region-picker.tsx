/** @format **/

/**
 * External dependencies
 */
import React, { useState } from 'react';

/**
 * Internal dependencies
 */
import strings from './strings';
import InlineLabelSelect from 'components/inline-label-select';
import { RegionPickerInterface } from './types';
import { getWooBaseCountries } from '../onboarding/utils';

const RegionPicker = ( {
	country,
	setStoreCountry,
}: RegionPickerInterface ): JSX.Element => {
	const countries = getWooBaseCountries();

	return (
		<InlineLabelSelect
			className="onboarding-region-picker"
			label={ strings.regionPicker.label }
			value={ countries.find( ( option ) => option.key === country ) }
			options={ countries }
			onChange={ ( { selectedItem } ) => {
				if ( ! selectedItem ) {
					return;
				}

				setStoreCountry( selectedItem.key );
			} }
			searchable={ true }
		/>
	);
};

export default RegionPicker;
