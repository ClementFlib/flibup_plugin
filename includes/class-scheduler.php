<?php
/**
 * Logique de programmation des dates et de calcul du statut.
 *
 * @package FlibUp
 */

namespace FlibUp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Toutes les comparaisons utilisent le fuseau horaire configuré dans WordPress
 * (wp_timezone) afin d'éviter les décalages liés à UTC.
 */
class Scheduler {

	/**
	 * Convertit une chaîne « Y-m-d H:i » (heure locale WP) en timestamp UTC.
	 *
	 * @param string $datetime Chaîne locale.
	 * @return int|null Timestamp UTC ou null si vide/invalide.
	 */
	public static function datetime_to_timestamp( $datetime ) {
		$datetime = is_scalar( $datetime ) ? trim( (string) $datetime ) : '';
		if ( '' === $datetime ) {
			return null;
		}

		// Normalise le séparateur « T » du champ datetime-local.
		$datetime = str_replace( 'T', ' ', $datetime );

		try {
			$tz  = wp_timezone();
			$obj = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $datetime, $tz );
			if ( false === $obj ) {
				$obj = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $datetime, $tz );
			}
			if ( false === $obj ) {
				return null;
			}
			return $obj->getTimestamp();
		} catch ( \Exception $e ) {
			return null;
		}
	}

	/**
	 * Renvoie le statut logique d'une pop-up.
	 *
	 * @param Popup $popup Pop-up.
	 * @return string inactive|scheduled|active|expired
	 */
	public static function get_status( Popup $popup ) {
		if ( ! $popup->is_enabled() ) {
			return 'inactive';
		}

		$now      = time();
		$start_ts = self::datetime_to_timestamp( $popup->get( 'start_datetime' ) );
		$end_ts   = self::datetime_to_timestamp( $popup->get( 'end_datetime' ) );

		if ( null !== $end_ts && $now > $end_ts ) {
			return 'expired';
		}

		if ( null !== $start_ts && $now < $start_ts ) {
			return 'scheduled';
		}

		return 'active';
	}

	/**
	 * La pop-up est-elle « expirée » (date de fin dépassée) ?
	 * Utilisé pour éviter d'injecter côté serveur une pop-up définitivement finie.
	 *
	 * @param Popup $popup Pop-up.
	 * @return bool
	 */
	public static function is_expired( Popup $popup ) {
		$end_ts = self::datetime_to_timestamp( $popup->get( 'end_datetime' ) );
		return ( null !== $end_ts && time() > $end_ts );
	}

	/**
	 * Convertit un délai + unité en millisecondes.
	 *
	 * @param mixed  $value Valeur.
	 * @param string $unit  « s » ou « ms ».
	 * @return int
	 */
	public static function delay_in_ms( $value, $unit ) {
		$value = is_numeric( $value ) ? (int) $value : 0;
		$value = max( 0, $value );
		if ( 's' === $unit ) {
			return $value * 1000;
		}
		return $value;
	}
}
