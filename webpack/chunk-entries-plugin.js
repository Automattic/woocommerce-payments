/* eslint-disable */
const { sources } = require( 'webpack' );

const pluginName = 'ChunkEntriesPlugin';

class ChunkEntriesPlugin {
	constructor( { filename } ) {
		this.filename = filename;
	}

	apply( compiler ) {
		compiler.hooks.thisCompilation.tap( pluginName, ( compilation ) => {
			compilation.hooks.processAssets.tap(
				{
					name: pluginName,
					stage: compilation.PROCESS_ASSETS_STAGE_REPORT,
				},
				() => {
					const map = {};

					for ( const entrypoint of compilation.entrypoints.values() ) {
						const entryFiles = jsFiles(
							entrypoint.getEntrypointChunk()
						);
						const seen = new Set();
						const visit = ( group ) => {
							if ( seen.has( group ) ) {
								return;
							}
							seen.add( group );
							if ( group !== entrypoint ) {
								for ( const chunk of group.chunks ) {
									for ( const file of jsFiles( chunk ) ) {
										map[ file ] = [
											...new Set( [
												...( map[ file ] || [] ),
												...entryFiles,
											] ),
										].sort();
									}
								}
							}
							for ( const child of group.childrenIterable ) {
								visit( child );
							}
						};
						visit( entrypoint );
					}

					compilation.emitAsset(
						this.filename,
						new sources.RawSource(
							JSON.stringify( map, null, '\t' )
						)
					);
				}
			);
		} );
	}
}

// Chunk file names carry a `?ver=` cache buster that isn't part of the path on disk.
const jsFiles = ( chunk ) =>
	[ ...chunk.files ]
		.map( ( file ) => file.split( '?' )[ 0 ] )
		.filter( ( file ) => file.endsWith( '.js' ) );

module.exports = ChunkEntriesPlugin;
