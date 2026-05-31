/** @format **/

/**
 * Internal dependencies
 */
import { RECOMMENDATIONS_CATALOG } from '../recommendation-catalog';
import idSnapshot from '../recommendation-ids.snapshot.json';

/**
 * Enforces the recommendation-id stability contract. `Recommendation.id` is the
 * join key for Outcome View Tracks queries, so ids are append-only: renaming or
 * reusing one silently breaks every existing query. Retired entries stay in the
 * catalog as tombstones (`retired: true`), so the catalog holds every id that
 * has ever existed and the snapshot mirrors it. These assertions turn the rule
 * into a CI gate instead of a review check.
 */
describe( 'recommendation id stability', () => {
	const catalogIds = RECOMMENDATIONS_CATALOG.map( ( entry ) => entry.id );
	const snapshotIds = idSnapshot as string[];

	it( 'never renames or removes an id (every snapshot id is still in the catalog)', () => {
		const missing = snapshotIds.filter(
			( id ) => ! catalogIds.includes( id )
		);
		// Non-empty means an id was renamed or hard-deleted. Restore it, or
		// tombstone the entry with `retired: true` rather than removing it.
		expect( missing ).toEqual( [] );
	} );

	it( 'records every catalog id in the snapshot', () => {
		const unrecorded = catalogIds.filter(
			( id ) => ! snapshotIds.includes( id )
		);
		// New entries must append their id to recommendation-ids.snapshot.json
		// in the same PR.
		expect( unrecorded ).toEqual( [] );
	} );

	it( 'has no duplicate ids', () => {
		const duplicates = catalogIds.filter(
			( id, index ) => catalogIds.indexOf( id ) !== index
		);
		expect( duplicates ).toEqual( [] );
	} );
} );
