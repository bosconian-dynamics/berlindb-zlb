<?php
namespace BosconianDynamics\BerlinDB_Zlb;

function autoload( $class_name ) {
	if( false === strpos( $class_name, __NAMESPACE__ ) )
		return;

	$file_path  = str_replace( __NAMESPACE__ . '\\', '', $class_name );
	$file_path  = preg_replace( '/([a-z])_?([A-Z])/', '$1-$2', $file_path );
	$file_path  = preg_replace( '/([A-Z])_?([A-Z][a-z])/', '$1-$2', $file_path );
	$file_path  = strtolower( $file_path );
	$path_parts = explode( '\\', $file_path );

	$path_parts[ count( $path_parts ) - 1 ] = 'class-' . $path_parts[ count( $path_parts ) - 1 ] . '.php';
	array_unshift( $path_parts, __DIR__ );

	$file_path = implode( DIRECTORY_SEPARATOR, $path_parts );

	require_once $file_path;
}

spl_autoload_register( __NAMESPACE__ . '\autoload' );
