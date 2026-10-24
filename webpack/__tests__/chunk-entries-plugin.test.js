/**
 * External dependencies
 */
const fs = require( 'fs' );
const os = require( 'os' );
const path = require( 'path' );
const webpack = require( 'webpack' );

/**
 * Internal dependencies
 */
const ChunkEntriesPlugin = require( '../chunk-entries-plugin' );

const write = ( dir, files ) =>
	Object.entries( files ).forEach( ( [ name, content ] ) =>
		fs.writeFileSync( path.join( dir, name ), content )
	);

const build = ( dir ) =>
	new Promise( ( resolve, reject ) =>
		webpack(
			{
				mode: 'production',
				context: dir,
				entry: {
					app: './app.js',
					other: './other.js',
					plain: './plain.js',
				},
				output: {
					path: path.join( dir, 'dist' ),
					chunkFilename: 'chunks/[name].js?ver=[chunkhash]',
				},
				plugins: [
					new ChunkEntriesPlugin( {
						filename: 'chunk-entries.json',
					} ),
				],
			},
			( err, stats ) =>
				err || stats.hasErrors()
					? reject( err || stats.toString() )
					: resolve()
		)
	);

describe( 'ChunkEntriesPlugin', () => {
	let dir;

	beforeEach( () => {
		dir = fs.mkdtempSync( path.join( os.tmpdir(), 'chunk-entries-' ) );
	} );

	afterEach( () => {
		fs.rmSync( dir, { recursive: true, force: true } );
	} );

	it( 'maps each lazy chunk, however deep, to every entry that loads it', async () => {
		write( dir, {
			'app.js':
				'import( /* webpackChunkName: "route" */ "./route" ); console.log( "app" );',
			'other.js':
				'import( /* webpackChunkName: "route" */ "./route" ); console.log( "other" );',
			'plain.js': 'console.log( "plain" );',
			'route.js':
				'import( /* webpackChunkName: "nested" */ "./nested" ); export default 1;',
			'nested.js': 'export default 2;',
		} );

		await build( dir );

		const map = JSON.parse(
			fs.readFileSync( path.join( dir, 'dist/chunk-entries.json' ) )
		);

		expect( map ).toEqual( {
			'chunks/route.js': [ 'app.js', 'other.js' ],
			'chunks/nested.js': [ 'app.js', 'other.js' ],
		} );
	} );
} );
