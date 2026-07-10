<?php
/**
 * Fonctions utilitaires du plugin.
 *
 * @package FlibUp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clé de transient mettant en cache la liste des pop-ups actives.
 */
if ( ! defined( 'FLIBUP_ACTIVE_CACHE_KEY' ) ) {
	define( 'FLIBUP_ACTIVE_CACHE_KEY', 'flibup_active_popups_v1' );
}

/**
 * Vide le cache des pop-ups actives.
 *
 * @return void
 */
function flibup_clear_active_cache() {
	delete_transient( FLIBUP_ACTIVE_CACHE_KEY );
}

/**
 * Valide une longueur CSS (nombre + unité autorisée) sans faire confiance
 * à une saisie brute. Renvoie une chaîne sûre ou la valeur par défaut.
 *
 * @param mixed  $value        Valeur saisie (ex. « 32px », « 90vw », « 8 »).
 * @param string $default      Valeur de repli si invalide.
 * @param array  $allowed_units Unités autorisées.
 * @return string
 */
function flibup_sanitize_css_length( $value, $default = '', array $allowed_units = array( 'px', '%', 'vw', 'vh', 'em', 'rem' ) ) {
	$value = is_scalar( $value ) ? trim( (string) $value ) : '';

	if ( '' === $value ) {
		return $default;
	}

	// 0 seul est accepté (sans unité).
	if ( '0' === $value ) {
		return '0';
	}

	$units_pattern = implode( '|', array_map( 'preg_quote', $allowed_units ) );

	if ( preg_match( '/^(\d+(?:\.\d+)?)(' . $units_pattern . ')$/', $value, $m ) ) {
		return $m[1] . $m[2];
	}

	// Nombre seul : on lui applique px par défaut si px est autorisé.
	if ( preg_match( '/^(\d+(?:\.\d+)?)$/', $value ) ) {
		return in_array( 'px', $allowed_units, true ) ? $value . 'px' : $value;
	}

	return $default;
}

/**
 * Sépare une valeur numérique et son unité (pour les champs à unité choisie).
 *
 * @param mixed  $number Valeur numérique.
 * @param mixed  $unit   Unité.
 * @param string $default_unit Unité par défaut.
 * @return string
 */
function flibup_compose_length( $number, $unit, $default_unit = 'px' ) {
	$number = is_scalar( $number ) ? trim( (string) $number ) : '';
	if ( '' === $number ) {
		return '';
	}
	if ( ! preg_match( '/^\d+(?:\.\d+)?$/', $number ) ) {
		return '';
	}
	$allowed = array( 'px', '%', 'vw', 'vh' );
	$unit    = in_array( $unit, $allowed, true ) ? $unit : $default_unit;
	return $number . $unit;
}

/**
 * Valide une couleur : hexadécimale, rgb(a) ou mot-clé simple.
 *
 * @param mixed  $value   Couleur saisie.
 * @param string $default Valeur de repli.
 * @return string
 */
function flibup_sanitize_color( $value, $default = '' ) {
	$value = is_scalar( $value ) ? trim( (string) $value ) : '';

	if ( '' === $value ) {
		return $default;
	}

	// Hex #rgb / #rrggbb / #rrggbbaa.
	$hex = sanitize_hex_color( $value );
	if ( $hex ) {
		return $hex;
	}

	// rgb() / rgba().
	if ( preg_match( '/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(,\s*(0|1|0?\.\d+)\s*)?\)$/i', $value ) ) {
		return $value;
	}

	// Mots-clés CSS simples (lettres uniquement).
	if ( preg_match( '/^[a-z]{3,20}$/i', $value ) ) {
		return strtolower( $value );
	}

	return $default;
}

/**
 * Borne un entier entre un minimum et un maximum.
 *
 * @param mixed $value Valeur.
 * @param int   $min   Minimum.
 * @param int   $max   Maximum.
 * @param int   $default Valeur par défaut si non numérique.
 * @return int
 */
function flibup_sanitize_int_range( $value, $min, $max, $default ) {
	if ( ! is_scalar( $value ) || '' === $value || ! is_numeric( $value ) ) {
		return $default;
	}
	$value = (int) $value;
	return max( $min, min( $max, $value ) );
}

/**
 * Valide un nombre décimal borné (ex. opacité 0..1).
 *
 * @param mixed $value   Valeur.
 * @param float $min     Minimum.
 * @param float $max     Maximum.
 * @param float $default Valeur par défaut.
 * @return float
 */
function flibup_sanitize_float_range( $value, $min, $max, $default ) {
	if ( ! is_scalar( $value ) || '' === $value || ! is_numeric( $value ) ) {
		return $default;
	}
	$value = (float) $value;
	return max( $min, min( $max, $value ) );
}
