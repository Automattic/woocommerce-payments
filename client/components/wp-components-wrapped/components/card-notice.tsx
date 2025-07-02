/**
 * External dependencies
 */
import BundledCardNotice from 'wcpay/components/card-notice';

/**
 * Internal dependencies
 */
import { makeWrappedComponent } from '../make-wrapped-component';

export const CardNotice = makeWrappedComponent(
	BundledCardNotice,
	'CardNotice'
);
