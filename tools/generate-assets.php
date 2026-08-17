<?php
/**
 * Generates simple WordPress.org visual assets.
 *
 * @package SpeculationPilot
 */

declare(strict_types=1);

$root = dirname( __DIR__ );
$dir  = $root . '/assets';

if ( ! is_dir( $dir ) ) {
	mkdir( $dir, 0777, true );
}

function sp_color( $image, string $hex ): int {
	$hex = ltrim( $hex, '#' );

	return imagecolorallocate(
		$image,
		hexdec( substr( $hex, 0, 2 ) ),
		hexdec( substr( $hex, 2, 2 ) ),
		hexdec( substr( $hex, 4, 2 ) )
	);
}

function sp_text( $image, int $size, int $x, int $y, string $text, int $color ): void {
	imagestring( $image, $size, $x, $y, $text, $color );
}

function sp_banner( int $width, int $height, string $path ): void {
	$image = imagecreatetruecolor( $width, $height );
	$bg    = sp_color( $image, '#f6f7f7' );
	$ink   = sp_color( $image, '#111827' );
	$muted = sp_color( $image, '#4b5563' );
	$blue  = sp_color( $image, '#2271b1' );
	$green = sp_color( $image, '#008a20' );
	$line  = sp_color( $image, '#dcdcde' );
	$white = sp_color( $image, '#ffffff' );

	imagefilledrectangle( $image, 0, 0, $width, $height, $bg );
	imagefilledrectangle( $image, (int) ( $width * 0.62 ), 0, $width, $height, sp_color( $image, '#e7f5ee' ) );
	imagefilledrectangle( $image, 46, 48, (int) ( $width * 0.56 ), $height - 48, $white );
	imagerectangle( $image, 46, 48, (int) ( $width * 0.56 ), $height - 48, $line );

	sp_text( $image, 5, 74, 78, 'Speculation Pilot', $ink );
	sp_text( $image, 3, 76, 116, 'Safe prefetch and prerender controls for WordPress', $muted );
	sp_text( $image, 3, 76, 150, 'Diagnostics  |  Exclusions  |  Local timing reports', $muted );

	$chart_x = (int) ( $width * 0.64 );
	$chart_y = 62;
	for ( $i = 0; $i < 7; $i++ ) {
		$x = $chart_x + ( $i * (int) ( $width * 0.035 ) );
		$h = (int) ( $height * ( 0.18 + ( $i % 4 ) * 0.07 ) );
		imagefilledrectangle( $image, $x, $chart_y + 180 - $h, $x + 18, $chart_y + 180, $blue );
	}

	imagefilledellipse( $image, $chart_x + 230, $chart_y + 44, 62, 62, $green );
	sp_text( $image, 5, $chart_x + 212, $chart_y + 34, 'OK', $white );

	imagepng( $image, $path );
	imagedestroy( $image );
}

function sp_icon( int $size, string $path ): void {
	$image = imagecreatetruecolor( $size, $size );
	$bg    = sp_color( $image, '#2271b1' );
	$white = sp_color( $image, '#ffffff' );
	$green = sp_color( $image, '#00a32a' );
	$ink   = sp_color( $image, '#0a4b78' );

	imagefilledrectangle( $image, 0, 0, $size, $size, $bg );
	imagefilledellipse( $image, (int) ( $size * 0.5 ), (int) ( $size * 0.5 ), (int) ( $size * 0.72 ), (int) ( $size * 0.72 ), $white );
	imagefilledarc( $image, (int) ( $size * 0.5 ), (int) ( $size * 0.5 ), (int) ( $size * 0.52 ), (int) ( $size * 0.52 ), 300, 60, $green, IMG_ARC_PIE );
	imageline( $image, (int) ( $size * 0.5 ), (int) ( $size * 0.5 ), (int) ( $size * 0.76 ), (int) ( $size * 0.3 ), $ink );
	imagefilledellipse( $image, (int) ( $size * 0.5 ), (int) ( $size * 0.5 ), (int) ( $size * 0.1 ), (int) ( $size * 0.1 ), $ink );

	imagepng( $image, $path );
	imagedestroy( $image );
}

function sp_screenshot( string $title, string $subtitle, string $path, array $bars ): void {
	$image = imagecreatetruecolor( 1200, 900 );
	$bg    = sp_color( $image, '#f6f7f7' );
	$white = sp_color( $image, '#ffffff' );
	$ink   = sp_color( $image, '#111827' );
	$muted = sp_color( $image, '#4b5563' );
	$line  = sp_color( $image, '#dcdcde' );
	$blue  = sp_color( $image, '#2271b1' );
	$green = sp_color( $image, '#008a20' );

	imagefilledrectangle( $image, 0, 0, 1200, 900, $bg );
	sp_text( $image, 5, 64, 48, $title, $ink );
	sp_text( $image, 3, 66, 86, $subtitle, $muted );

	for ( $i = 0; $i < 3; $i++ ) {
		$x = 64 + ( $i * 350 );
		imagefilledrectangle( $image, $x, 130, $x + 310, 255, $white );
		imagerectangle( $image, $x, 130, $x + 310, 255, $line );
		sp_text( $image, 3, $x + 24, 154, array( 'Mode', 'Diagnostics', 'Samples' )[ $i ], $muted );
		sp_text( $image, 5, $x + 24, 190, array( 'prefetch', 'ok', '1,248' )[ $i ], $i === 1 ? $green : $ink );
	}

	imagefilledrectangle( $image, 64, 300, 1136, 820, $white );
	imagerectangle( $image, 64, 300, 1136, 820, $line );
	sp_text( $image, 4, 96, 332, 'Performance trend', $ink );

	$y = 390;
	foreach ( $bars as $label => $value ) {
		sp_text( $image, 3, 96, $y + 4, (string) $label, $muted );
		imagefilledrectangle( $image, 250, $y, 1030, $y + 22, sp_color( $image, '#f0f0f1' ) );
		imagefilledrectangle( $image, 250, $y, 250 + (int) ( 7.5 * $value ), $y + 22, $blue );
		sp_text( $image, 3, 1050, $y + 4, $value . '%', $ink );
		$y += 58;
	}

	imagepng( $image, $path );
	imagedestroy( $image );
}

sp_icon( 128, $dir . '/icon-128x128.png' );
sp_icon( 256, $dir . '/icon-256x256.png' );
sp_banner( 772, 250, $dir . '/banner-772x250.png' );
sp_banner( 1544, 500, $dir . '/banner-1544x500.png' );
sp_screenshot(
	'Overview',
	'Mode, diagnostics, active exclusions, and local timing at a glance.',
	$dir . '/screenshot-1.png',
	array(
		'/blog/*'    => 78,
		'/shop/*'    => 54,
		'/docs/*'    => 42,
		'/pricing/*' => 68,
	)
);
sp_screenshot(
	'Rules and exclusions',
	'Production-safe presets for WooCommerce, memberships, LMS, cache, and multilingual sites.',
	$dir . '/screenshot-2.png',
	array(
		'WooCommerce'  => 88,
		'Membership'   => 56,
		'LMS'          => 44,
		'Cache bypass' => 62,
	)
);
sp_screenshot(
	'Measurements',
	'Privacy-safe p75 trends, mode breakdowns, and path group reporting.',
	$dir . '/screenshot-3.png',
	array(
		'Mon' => 72,
		'Tue' => 61,
		'Wed' => 53,
		'Thu' => 46,
		'Fri' => 41,
	)
);

echo "Generated Speculation Pilot assets.\n";
