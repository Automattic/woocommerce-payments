/** @format **/

/**
 * Internal dependencies
 */
import { RECOMMENDATIONS_CATALOG } from '../recommendation-catalog';
import idSnapshot from '../recommendation-ids.snapshot.json';

/**
 * Enforces the recommendation-id stability contract. Ids are Tracks join keys,
 * so append-only: renaming or reusing one breaks existing queries. Retired
 * entries stay as tombstones, so the catalog holds every id ever shipped and
 * the snapshot mirrors it. These assertions make that a CI gate.
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

	it( 'has no duplicate ids in the snapshot ledger itself', () => {
		// The ledger is the source of truth, so a duplicate (e.g. from a
		// rebase) would pass the parity checks above. Guard it directly.
		const duplicates = snapshotIds.filter(
			( id, index ) => snapshotIds.indexOf( id ) !== index
		);
		expect( duplicates ).toEqual( [] );
	} );
} );
