/* eslint-disable no-console */
/* eslint no-process-exit: 0, no-undef: 0, strict: 0 */
'use strict';
require( 'shelljs/global' );
const colors = require( 'colors' );
const archiver = require( 'archiver' );
const fs = require( 'fs' );

const zipRootDirName = process.argv[ 2 ];

// some config
const releaseFolder = 'release';
const targetFolder = 'release/' + process.env.npm_package_name;
const filesToCopy = [
	'assets',
	'dist',
	'includes',
	'woocommerce-payments.php',
	'changelog.txt',
	'readme.txt',
];

// run npm dist
rm( '-rf', 'dist' );
exec( 'SOURCEMAP=none npm run build' );

// start with a clean release folder
rm( '-rf', releaseFolder );
mkdir( releaseFolder );
mkdir( targetFolder );

// copy the directories to the release folder
cp( '-Rf', filesToCopy, targetFolder );

const output = fs.createWriteStream( releaseFolder + '/' + process.env.npm_package_name + '.zip' );
const archive = archiver( 'zip' );

output.on( 'close', () => {
	console.log( colors.green( 'All done: Release is built in the ' + releaseFolder + ' folder.' ) );
} );

archive.on( 'error', ( err ) => {
	console.error( colors.red( 'An error occured while creating the zip: ' + err +
		'\nYou can still probably create the zip manually from the ' + targetFolder + ' folder.' ) );
} );

archive.pipe( output );

archive.directory( targetFolder, zipRootDirName );

archive.finalize();
