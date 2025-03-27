<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', "wp-ingenius" );

/** Database username */
define( 'DB_USER', "root" );

/** Database password */
define( 'DB_PASSWORD', "root" );

/** Database hostname */
define( 'DB_HOST', "localhost" );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'N?c_$5L{CZ09_9_5[)rz?CyeNO7Qj0dDbmw@JA_+.Y/6GF3lU#SLv,JvJW>ZwxP$' );
define( 'SECURE_AUTH_KEY',  ')4Ac8jk_d806=s.*8HU4`,NO_780)Mn8<f^P[w,U[G1VgE{zT|DfK<yLMv}ET7%Q' );
define( 'LOGGED_IN_KEY',    '4pixT.`H{?$l`gLcm2,8itk5D}j6SmD-HpSITx9CPpAdHa2`mgvkEkM $,qqOQ0O' );
define( 'NONCE_KEY',        'y8dR0c{qO~@F]M(Iauj[2ND;`sXibZ#|@WLJF ^_RiYS5&i8-]EyJORhYOnHlR2C' );
define( 'AUTH_SALT',        'N~-o~3.+pCF><}E%Dy6GQDqV3tK>-<adXiAAi{t_.NBce2277kgw7DV2-Anx/Be)' );
define( 'SECURE_AUTH_SALT', 'C=&VU585^[tfn? 1Jt~_[ar%?H:q2em35C-%%AjV]V^yR-H/ih^kV_XkpGt$V,/[' );
define( 'LOGGED_IN_SALT',   ',s9`6-uoN(%j?R>mn:P[x)K->S^<I$f}udZ7an>PG8M4B>I/E.k^o:ZdKoI Q=dN' );
define( 'NONCE_SALT',       'X #3.JSpay900r&5!u6q!FG(if[Sib^Kh)cyYMiw@y&{^(7Bf zC8VR09`eG-Q0z' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'isngu_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



define( 'DUPLICATOR_AUTH_KEY', '5*jgvyD-d}?kd(eD53?U$e(2hKt28S6byQUFRaOtnqp*zC_5AGx{0a>{q*GL]LAt' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
