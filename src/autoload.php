<?php

declare(strict_types=1);

namespace HeyGen;

spl_autoload_register(
	static function ( string $class ): void {
		$prefix = __NAMESPACE__ . '\\';
		if ( strncmp( $class, $prefix, strlen( $prefix ) ) !== 0 ) {
			return;
		}

		$classPath = substr( $class, strlen( $prefix ) );
		$file      = __DIR__ . '/' . str_replace( '\\', '/', $classPath ) . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);
