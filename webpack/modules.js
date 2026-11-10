/* eslint-disable */
const path = require( 'path' );
const { createHash } = require( 'crypto' );

/**
 * Builds the script modules for WooCommerce's experimental settings screen, which loads a route's
 * code as script modules. The screen's own modules bundle these packages and import the rest from
 * WordPress, so these modules do the same: the sub-pages then look and behave like the screen's pages.
 */

const OUTPUT_DIR = 'settings-screen-modules';

// Packages WordPress provides as script modules.
const SCRIPT_MODULES = [ '@wordpress/a11y', '@wordpress/route' ];

// Packages the settings screen bundles, so the sub-pages use the same versions.
const BUNDLED = [
	'@wordpress/admin-ui',
	'@wordpress/dataviews',
	'@wordpress/icons',
	'@wordpress/ui',
	'@wordpress/views',
	'dataviews-settings-screen',
];

const CLASSIC_GLOBALS = {
	react: [ 'React', 'react' ],
	'react-dom': [ 'ReactDOM', 'react-dom' ],
	'react/jsx-runtime': [ 'ReactJSXRuntime', 'react-jsx-runtime' ],
};

const isBundled = ( request ) =>
	BUNDLED.some(
		( name ) => request === name || request.startsWith( `${ name }/` )
	);

const camelCase = ( name ) =>
	name.replace( /-([a-z])/g, ( _, letter ) => letter.toUpperCase() );

/**
 * Maps a request to a WordPress script: its global and its script handle.
 */
const getClassicScript = ( request ) => {
	if ( CLASSIC_GLOBALS[ request ] ) {
		return CLASSIC_GLOBALS[ request ];
	}
	if ( request.startsWith( '@wordpress/' ) && ! isBundled( request ) ) {
		const name = request.slice( '@wordpress/'.length );
		return [ [ 'wp', camelCase( name ) ], `wp-${ name }` ];
	}
};

/**
 * Writes `{entry}.asset.php` next to each module, with the script modules and WordPress scripts it
 * imports and a version that changes with its content.
 */
class ModuleAssetsPlugin {
	apply( compiler ) {
		compiler.hooks.thisCompilation.tap(
			'ModuleAssetsPlugin',
			( compilation ) => {
				compilation.hooks.processAssets.tap(
					{
						name: 'ModuleAssetsPlugin',
						stage: compiler.webpack.Compilation
							.PROCESS_ASSETS_STAGE_ANALYSE,
					},
					() => {
						for ( const [
							name,
							entrypoint,
						] of compilation.entrypoints ) {
							const modules = new Set();
							const scripts = new Set();
							const hash = createHash( 'md5' );
							for ( const chunk of entrypoint.chunks ) {
								for ( const chunkModule of compilation.chunkGraph.getChunkModulesIterable(
									chunk
								) ) {
									// Externals can be concatenated into the entry module.
									for ( const module of [
										chunkModule,
										...( chunkModule.modules ?? [] ),
									] ) {
										if (
											module.externalType === 'module'
										) {
											modules.add( module.userRequest );
										} else if (
											module.externalType === 'window'
										) {
											scripts.add(
												getClassicScript(
													module.userRequest
												)[ 1 ]
											);
										}
									}
								}
								for ( const file of chunk.files ) {
									hash.update(
										compilation
											.getAsset( file )
											.source.buffer()
									);
								}
							}
							const toPhp = ( values ) =>
								[ ...values ]
									.sort()
									.map( ( value ) => `'${ value }'` )
									.join( ', ' );
							const moduleDependencies = [ ...modules ]
								.sort()
								.map(
									( id ) =>
										`array( 'id' => '${ id }', 'import' => 'static' )`
								)
								.join( ', ' );
							compilation.emitAsset(
								`${ name }.asset.php`,
								new compiler.webpack.sources.RawSource(
									`<?php return array( 'dependencies' => array( ${ toPhp( scripts ) } ), 'module_dependencies' => array( ${ moduleDependencies } ), 'version' => '${ hash.digest( 'hex' ).slice( 0, 20 ) }' );\n`
								)
							);
						}
					}
				);
			}
		);
	}
}

module.exports = ( mode ) => ( {
	name: 'modules',
	mode,
	devtool: mode === 'production' ? 'hidden-source-map' : 'source-map',
	// The POT strings come from a Babel plugin, which skips modules webpack restores from cache.
	cache: mode === 'production' ? false : { type: 'filesystem' },
	entry: {
		'settings-screen-route': './client/settings-screen/subpages/route.ts',
		'settings-screen-stage': './client/settings-screen/subpages/stage.tsx',
	},
	experiments: { outputModule: true },
	output: {
		path: path.resolve( process.cwd(), 'dist', OUTPUT_DIR ),
		filename: '[name].js',
		module: true,
		chunkFormat: 'module',
		library: { type: 'module' },
	},
	optimization: {
		// Each module is loaded on its own, so nothing is split into shared chunks.
		splitChunks: false,
		runtimeChunk: false,
	},
	externals: [
		( { request }, callback ) => {
			if ( SCRIPT_MODULES.includes( request ) ) {
				return callback( null, request, 'module' );
			}
			const script = getClassicScript( request );
			if ( script ) {
				return callback( null, script[ 0 ], 'window' );
			}
			callback();
		},
	],
	module: {
		rules: [
			{
				test: /\.(tsx?|jsx?)$/,
				use: [ 'babel-loader' ],
				exclude: /node_modules/,
			},
		],
	},
	resolve: {
		extensions: [ '.ts', '.tsx', '.json', '.js', '.jsx' ],
		modules: [ path.join( process.cwd(), 'client' ), 'node_modules' ],
		alias: {
			wcpay: path.resolve( process.cwd(), 'client' ),
		},
	},
	plugins: [ new ModuleAssetsPlugin() ],
} );

module.exports.OUTPUT_DIR = OUTPUT_DIR;
