/* eslint-disable no-console */
/* eslint no-process-exit: 0, no-undef: 0, strict: 0 */
'use strict';
require( 'shelljs/global' );
const colors = require( 'colors' );
const archiver = require( 'archiver' );
const fs = require( 'fs' );

const pluginSlug = process.env.npm_package_name;

// some config
const releaseFolder = 'release';
const targetFolder = 'release/' + pluginSlug;
const filesToCopy = [
	'assets',
	'dist',
	'includes',
	'i18n',
	'languages',
	'vendor',
	'woocommerce-payments.php',
	'changelog.txt',
	'readme.txt',
	'SECURITY.md',
	'apple-developer-merchantid-domain-association',
];

// run npm dist
rm( '-rf', 'dist' );
exec( 'SOURCEMAP=hidden npm run build:client' );

// start with a clean release folder
rm( '-rf', releaseFolder );
mkdir( releaseFolder );
mkdir( targetFolder );

// remove the 'hidden' source maps; they are used to generate the POT file and are not referenced in the source files.
rm( 'dist/*.map' );

// copy the directories to the release folder
cp( '-Rf', filesToCopy, targetFolder );

const output = fs.createWriteStream(
	releaseFolder + '/' + pluginSlug + '.zip'
);
const archive = archiver( 'zip', { zlib: { level: 9 } } );

output.on( 'close', () => {
	console.log(
		colors.green(
			'All done: Release is built in the ' + releaseFolder + ' folder.'
		)
	);
} );

archive.on( 'error', ( err ) => {
	console.error(
		colors.red(
			'An error occured while creating the zip: ' +
				err +
				'\nYou can still probably create the zip manually from the ' +
				targetFolder +
				' folder.'
		)
	);
} );

archive.pipe( output );

archive.directory( targetFolder, pluginSlug );

archive.finalize();
