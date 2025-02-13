const { spawn } = require( 'child_process' );
const path = require( 'path' );
const fs = require( 'fs' );

// Helper function to recursively get all files in a directory
function getAllFiles( dirPath, arrayOfFiles = [] ) {
	const files = fs.readdirSync( dirPath );

	files.forEach( ( file ) => {
		const fullPath = path.join( dirPath, file );
		if ( fs.statSync( fullPath ).isDirectory() ) {
			arrayOfFiles = getAllFiles( fullPath, arrayOfFiles );
		} else {
			arrayOfFiles.push( fullPath );
		}
	} );

	return arrayOfFiles;
}

// Helper function to recursively remove a directory
function removeDirectory( dirPath ) {
	if ( fs.existsSync( dirPath ) ) {
		fs.readdirSync( dirPath ).forEach( ( file ) => {
			const curPath = path.join( dirPath, file );
			if ( fs.lstatSync( curPath ).isDirectory() ) {
				removeDirectory( curPath );
			} else {
				fs.unlinkSync( curPath );
			}
		} );
		fs.rmdirSync( dirPath );
	}
}

class PaymentMethodsPlugin {
	apply( compiler ) {
		// Track if we've built the payment methods in this session
		let hasBuilt = false;
		// Track if we're currently building
		let isBuilding = false;

		// Create build directory if it doesn't exist
		const buildDir = path.resolve( __dirname, './payment-methods' );
		if ( ! fs.existsSync( buildDir ) ) {
			fs.mkdirSync( buildDir, { recursive: true } );
		}

		// Add payment method files to webpack's watch list
		compiler.hooks.afterCompile.tap(
			'PaymentMethodsPlugin',
			( compilation ) => {
				const paymentMethodsDir = path.resolve(
					__dirname,
					'../includes/payment-methods/Configs'
				);

				// Add all files and directories recursively
				if ( fs.existsSync( paymentMethodsDir ) ) {
					// Add the root directory
					compilation.fileDependencies.add( paymentMethodsDir );

					// Add all files recursively
					const allFiles = getAllFiles( paymentMethodsDir );
					allFiles.forEach( ( file ) => {
						compilation.fileDependencies.add( file );
					} );

					// Add all directories recursively
					const addDirectory = ( dir ) => {
						compilation.fileDependencies.add( dir );
						fs.readdirSync( dir ).forEach( ( file ) => {
							const fullPath = path.join( dir, file );
							if ( fs.statSync( fullPath ).isDirectory() ) {
								addDirectory( fullPath );
							}
						} );
					};
					addDirectory( paymentMethodsDir );
				}
			}
		);

		const buildPaymentMethods = ( callback ) => {
			if ( isBuilding ) {
				callback();
				return;
			}

			isBuilding = true;
			console.log( '\nBuilding payment method definitions...' );

			// Run WP-CLI command inside Docker
			const cliScript = spawn( 'docker', [
				'compose',
				'exec',
				'-T',
				'wordpress',
				'wp',
				'wcpay',
				'generate-payment-method-configs',
				'--allow-root',
			] );

			cliScript.stdout.on( 'data', ( data ) => {
				console.log( `${ data }` );
			} );

			cliScript.stderr.on( 'data', ( data ) => {
				console.error( `Error: ${ data }` );
			} );

			cliScript.on( 'close', ( code ) => {
				if ( code !== 0 ) {
					console.error( 'WP-CLI command failed' );
					isBuilding = false;
					callback( new Error( 'WP-CLI command failed' ) );
					return;
				}

				console.log( 'WP-CLI command completed successfully' );

				// Then run the JavaScript script
				const jsScript = spawn(
					'node',
					[
						path.resolve(
							__dirname,
							'../includes/payment-methods/Configs/Scripts/generate-payment-method-types.js'
						),
					],
					{
						stdio: 'inherit',
					}
				);

				jsScript.on( 'close', ( closeCode ) => {
					if ( closeCode !== 0 ) {
						console.error( 'JavaScript script failed' );
						isBuilding = false;
						callback( new Error( 'JavaScript script failed' ) );
						return;
					}

					console.log( 'Running eslint...' );

					// Run eslint --fix on the generated file
					const eslintScript = spawn(
						'npx',
						[
							'eslint',
							'--fix',
							'client/payment-methods/types.ts',
						],
						{
							stdio: [ 'inherit', 'ignore', 'ignore' ],
						}
					);

					eslintScript.on( 'close', ( eslintCode ) => {
						isBuilding = false;
						if ( eslintCode !== 0 ) {
							console.error( 'Eslint failed' );
							callback( new Error( 'Eslint failed' ) );
							return;
						}

						// Clean up build artifacts
						removeDirectory( buildDir );

						console.log(
							'Payment method definitions built successfully\n'
						);
						hasBuilt = true;
						callback();
					} );
				} );
			} );
		};

		// Run build when files change during watch
		compiler.hooks.watchRun.tapAsync(
			'PaymentMethodsPlugin',
			( compilation, callback ) => {
				// Build on initial run or when files have changed
				if ( ! hasBuilt || compilation.modifiedFiles ) {
					// Only check for payment method changes if we have modified files
					if ( compilation.modifiedFiles ) {
						const modifiedFiles = Array.from(
							compilation.modifiedFiles || []
						);

						// Ignore changes to generated files
						const hasPaymentMethodChanges = modifiedFiles.some(
							( file ) =>
								file.includes( '/payment-methods/Configs/' ) &&
								! file.includes( 'build/' ) &&
								! file.includes(
									'client/payment-methods/types.ts'
								)
						);

						if ( ! hasPaymentMethodChanges ) {
							callback();
							return;
						}

						console.log(
							'\nPayment method files changed, rebuilding...'
						);
					}

					hasBuilt = false; // Reset build flag
					buildPaymentMethods( callback );
				} else {
					callback();
				}
			}
		);
	}
}

module.exports = PaymentMethodsPlugin;
