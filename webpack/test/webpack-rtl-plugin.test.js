/**
 * External dependencies
 */
const fs = require( 'fs' );
const os = require( 'os' );
const path = require( 'path' );
const webpack = require( 'webpack' );
const MiniCssExtractPlugin = require( 'mini-css-extract-plugin' );

/**
 * Internal dependencies
 */
const WebpackRTLPlugin = require( '../webpack-rtl-plugin' );

describe( 'WebpackRTLPlugin', () => {
	let dir;
	let runtime;

	beforeAll( async () => {
		dir = fs.mkdtempSync( path.join( os.tmpdir(), 'wcpay-rtl-' ) );
		fs.writeFileSync( path.join( dir, 'entry.js' ), "import( './lazy' );" );
		fs.writeFileSync( path.join( dir, 'lazy.js' ), "import './lazy.css';" );
		fs.writeFileSync( path.join( dir, 'lazy.css' ), '.a{float:left}' );

		const compiler = webpack( {
			mode: 'production',
			target: 'web',
			context: dir,
			entry: { main: './entry.js' },
			output: { path: path.join( dir, 'dist' ), publicPath: '/dist/' },
			module: {
				rules: [
					{
						test: /\.css$/,
						use: [
							MiniCssExtractPlugin.loader,
							require.resolve( 'css-loader' ),
						],
					},
				],
			},
			plugins: [
				new MiniCssExtractPlugin( {
					chunkFilename: '[name].css?ver=[contenthash]',
				} ),
				new WebpackRTLPlugin( { filenameSuffix: '-rtl.css' } ),
			],
		} );
		const stats = await new Promise( ( resolve, reject ) =>
			compiler.run( ( err, result ) =>
				err ? reject( err ) : resolve( result )
			)
		);
		expect( stats.toString( 'errors-only' ) ).toBe( '' );

		runtime = fs.readFileSync( path.join( dir, 'dist/main.js' ), 'utf8' );
	}, 30000 );

	afterAll( () => {
		fs.rmSync( dir, { recursive: true, force: true } );
	} );

	afterEach( () => {
		document.dir = '';
		document.head.innerHTML = '';
	} );

	const loadLazyChunk = () => {
		// eslint-disable-next-line no-new-func
		new Function( runtime )();
		return document.head.querySelector( 'link[rel="stylesheet"]' ).href;
	};

	it( 'writes a mirrored stylesheet for lazy chunks', () => {
		const files = fs.readdirSync( path.join( dir, 'dist' ) );
		const rtlFile = files.find( ( file ) => /-rtl\.css$/.test( file ) );
		expect( rtlFile ).toBeDefined();

		expect(
			fs.readFileSync( path.join( dir, 'dist', rtlFile ), 'utf8' )
		).toContain( 'float:right' );
	} );

	it( 'loads the RTL stylesheet with its cache buster on RTL pages', () => {
		document.dir = 'rtl';

		expect( loadLazyChunk() ).toMatch(
			/\/dist\/\w+-rtl\.css\?ver=[0-9a-f]+$/
		);
	} );

	it( 'finds the RTL stylesheet already on the page', () => {
		document.dir = 'rtl';
		loadLazyChunk();
		loadLazyChunk();

		expect(
			document.head.querySelectorAll( 'link[rel="stylesheet"]' )
		).toHaveLength( 1 );
	} );

	it( 'loads the plain stylesheet on LTR pages', () => {
		expect( loadLazyChunk() ).toMatch( /\/dist\/\w+\.css\?ver=[0-9a-f]+$/ );
	} );
} );
