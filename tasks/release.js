/* eslint-disable no-console */
/* eslint no-process-exit: 0, no-undef: 0, strict: 0 */
'use strict';
require( 'shelljs/global' );
// shelljs only warns on a failed command, so without this a missing source
// directory would still produce a zip and exit 0.
config.fatal = true;
const chalk = require( 'chalk' );
const archiver = require( 'archiver' );
const fs = require( 'fs' );

const pluginSlug = process.env.npm_package_name;

// some config
const releaseFolder = 'release';
const targetFolder = 'release/' + pluginSlug;
const filesToCopy = [
	'assets',
	'dist',
	'i18n',
	'includes',
	'languages',
	'lib',
	'src',
	'templates',
	'vendor',
	'woocommerce-payments.php',
	'changelog.txt',
	'readme.txt',
	'SECURITY.md',
];

// run npm dist
rm( '-rf', 'dist' );
// fatal: false to name the step that broke instead of throwing. A command
// killed by a signal still reports code 0, so check the output too.
const clientBuild = exec( 'SOURCEMAP=hidden pnpm run build:client', {
	fatal: false,
} );
if ( clientBuild.code !== 0 || ! fs.existsSync( 'dist' ) ) {
	console.error(
		chalk.red( 'The client build failed; nothing was packaged.' )
	);
	process.exit( clientBuild.code || 1 );
}

// start with a clean release folder
rm( '-rf', releaseFolder );
mkdir( releaseFolder );
mkdir( targetFolder );

// remove the 'hidden' source maps; they are used to generate the POT file and are not referenced in the source files.
rm( '-f', 'dist/*.map' );

// copy the directories to the release folder
cp( '-Rf', filesToCopy, targetFolder );

// The '/includes/multi-currency/client' directory is removed because '/includes/multi-currency/*' should contain only server-side files.
// Furthermore, the './client' directory is already included in 'dist' during the build step.
rm( '-rf', targetFolder + '/includes/multi-currency/client' );

const output = fs.createWriteStream(
	releaseFolder + '/' + pluginSlug + '.zip'
);
const archive = archiver( 'zip', { zlib: { level: 9 } } );

output.on( 'close', () => {
	console.log(
		chalk.green(
			'All done: Release is built in the ' + releaseFolder + ' folder.'
		)
	);
} );

archive.on( 'error', ( err ) => {
	console.error(
		chalk.red(
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
