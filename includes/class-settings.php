<?php
/**
 * Réglages globaux du plugin (option unique).
 *
 * @package FlibUp
 */

namespace FlibUp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gestion de l'option globale flibup_settings.
 */
class Settings {

	const OPTION = 'flibup_settings';

	/**
	 * Valeurs par défaut.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'delete_data_on_uninstall' => 0,
			'allow_multiple'           => 0,
			'github_user'              => '',
			'github_repo'              => '',
		);
	}

	/**
	 * Renseigne les valeurs par défaut si l'option n'existe pas.
	 *
	 * @return void
	 */
	public static function maybe_set_defaults() {
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, self::defaults() );
		}
	}

	/**
	 * Renvoie tous les réglages.
	 *
	 * @return array
	 */
	public static function all() {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, self::defaults() );
	}

	/**
	 * Renvoie un réglage.
	 *
	 * @param string $key Clé.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Enregistre les réglages (sanitisés).
	 *
	 * @param array $clean Réglages sanitisés.
	 * @return void
	 */
	public static function save( array $clean ) {
		update_option( self::OPTION, wp_parse_args( $clean, self::defaults() ) );
	}

	/**
	 * Plusieurs pop-ups sont-elles autorisées sur une même page ?
	 *
	 * @return bool
	 */
	public static function allow_multiple() {
		return (int) self::get( 'allow_multiple' ) === 1;
	}

	/**
	 * Compte GitHub effectif (réglage > filtre > constante).
	 *
	 * @return string
	 */
	public static function github_user() {
		$user = (string) self::get( 'github_user' );
		if ( '' === $user && defined( 'FLIBUP_GITHUB_USER' ) ) {
			$user = FLIBUP_GITHUB_USER;
		}
		/** Filtre du compte GitHub. */
		return (string) apply_filters( 'flibup_github_user', $user );
	}

	/**
	 * Dépôt GitHub effectif.
	 *
	 * @return string
	 */
	public static function github_repo() {
		$repo = (string) self::get( 'github_repo' );
		if ( '' === $repo && defined( 'FLIBUP_GITHUB_REPO' ) ) {
			$repo = FLIBUP_GITHUB_REPO;
		}
		/** Filtre du dépôt GitHub. */
		return (string) apply_filters( 'flibup_github_repo', $repo );
	}
}
