/**
 * External dependencies
 */
import { createRegistry } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { SETTINGS_STORE_NAME } from '../data/store-names';
import * as selectors from '../data/settings/selectors';
import {
	updateEnabledPaymentMethodIds,
	updateSelectedPaymentMethod,
	updateUnselectedPaymentMethod,
	updateIsPaymentRequestEnabled,
	updateIsWooPayEnabled,
} from '../data/settings/actions';
import reducer from '../data/settings/reducer';

const entity = [ 'woo_settings', 'woopayments', undefined ];

/** Adapt the existing hooks without copying settings into a second store. */
export function createSettingsRegistry( parent ) {
	const registry = createRegistry( {}, parent );
	const getState = () => ( {
		data: parent.select( 'core' ).getEditedEntityRecord( ...entity ),
		isDirty: parent.select( 'core' ).hasEditsForEntityRecord( ...entity ),
		isSaving: parent.select( 'core' ).isSavingEntityRecord( ...entity ),
		savingError: parent
			.select( 'core' )
			.getLastEntitySaveError( ...entity ),
	} );
	const setters = {
		updateEnabledPaymentMethodIds,
		updateSelectedPaymentMethod,
		updateUnselectedPaymentMethod,
		updateIsPaymentRequestEnabled,
		updateIsWooPayEnabled,
	};
	registry.register( {
		name: SETTINGS_STORE_NAME,
		instantiate: () => ( {
			getSelectors: () => ( {
				...Object.fromEntries(
					Object.entries( selectors ).map( ( [ name, selector ] ) => [
						name,
						( ...args ) =>
							selector( { settings: getState() }, ...args ),
					] )
				),
				isResolving: () =>
					! parent
						.select( 'core' )
						.hasFinishedResolution( 'getEntityRecord', entity ),
				hasFinishedResolution: () =>
					parent
						.select( 'core' )
						.hasFinishedResolution( 'getEntityRecord', entity ),
			} ),
			getActions: () =>
				Object.fromEntries(
					Object.entries( setters ).map( ( [ name, action ] ) => [
						name,
						( ...args ) => {
							const state = getState();
							if ( state.isSaving ) {
								return;
							}
							const next = reducer(
								state,
								action( ...args )
							).data;
							const edits = Object.fromEntries(
								Object.entries( next ).filter(
									( [ key, value ] ) =>
										value !== state.data[ key ]
								)
							);
							return parent
								.dispatch( 'core' )
								.editEntityRecord( ...entity, edits );
						},
					] )
				),
			subscribe: ( listener ) => parent.subscribe( listener, 'core' ),
		} ),
	} );
	return registry;
}
