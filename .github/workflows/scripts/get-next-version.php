<?php
/**
 * Script to calculate the next release version.
 *
 * @package WooCommercePayments/GithubActions
 */

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped

$latest_version_with_release = getenv( 'RELEASE_VERSION' );
if ( empty( $latest_version_with_release ) ) {
	echo '::error::Unable to get the latest released version';
	exit( 1 );
}

// Because we go from 5.9 to 6.0, we can get the next major_minor by adding 0.1 and formatting appropriately.
$latest_version_as_float = (float) $latest_version_with_release;
$branch_major_minor      = number_format( $latest_version_as_float + 0.1, 1 );
$next_version            = $branch_major_minor . '0';
echo "Next release version: $next_version" >> "\$getenv( 'GITHUB_STEP_SUMMARY' )";
echo "::set-output name=NEXT_RELEASE_VERSION::$next_version" . PHP_EOL;
