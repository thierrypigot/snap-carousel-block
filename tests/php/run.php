<?php
/**
 * Minimal test runner: `php tests/php/run.php` (exit code 1 on failure).
 *
 * Each tests/php/test-*.php file registers cases with test().
 *
 * @package WearewpSnapCarouselBlock
 */

require __DIR__ . '/bootstrap.php';

$GLOBALS['wearewp_tests'] = array();

/**
 * Registers a test case.
 *
 * @param string   $name Description.
 * @param callable $fn   Test body, throws on failure.
 */
function test( $name, callable $fn ) {
	$GLOBALS['wearewp_tests'][] = array( $name, $fn );
}

function assert_same( $expected, $actual, $message = '' ) {
	if ( $expected !== $actual ) {
		throw new RuntimeException( trim( $message . "\n      attendu : " . var_export( $expected, true ) . "\n      obtenu  : " . var_export( $actual, true ) ) );
	}
}

function assert_true( $condition, $message = '' ) {
	assert_same( true, (bool) $condition, $message );
}

function assert_contains( $needle, $haystack, $message = '' ) {
	if ( ! str_contains( $haystack, $needle ) ) {
		throw new RuntimeException( trim( $message . "\n      absent : {$needle}\n      dans   : {$haystack}" ) );
	}
}

function assert_not_contains( $needle, $haystack, $message = '' ) {
	if ( str_contains( $haystack, $needle ) ) {
		throw new RuntimeException( trim( $message . "\n      présent : {$needle}\n      dans    : {$haystack}" ) );
	}
}

/**
 * Calls a private static method of the block class.
 *
 * @param string $method Method name.
 * @param mixed  ...$args Arguments.
 * @return mixed
 */
function call_private( $method, ...$args ) {
	$reflection = new ReflectionMethod( Wearewp_Snapcarousel_Block::class, $method );
	$reflection->setAccessible( true );
	return $reflection->invoke( null, ...$args );
}

foreach ( glob( __DIR__ . '/test-*.php' ) as $file ) {
	require $file;
}

$failures = 0;
foreach ( $GLOBALS['wearewp_tests'] as list( $name, $fn ) ) {
	try {
		$fn();
		echo "  ok    {$name}\n";
	} catch ( Throwable $error ) {
		++$failures;
		echo "  ÉCHEC {$name}\n      " . $error->getMessage() . "\n";
	}
}

$total = count( $GLOBALS['wearewp_tests'] );
echo "\n" . ( $total - $failures ) . " / {$total} tests réussis\n";
exit( $failures ? 1 : 0 );
