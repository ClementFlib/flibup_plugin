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
 * Positions d'affichage disponibles pour une pop-up.
 *
 * @return array<string,string> Clé => libellé traduit.
 */
function flibup_positions() {
	return array(
		'top-left'      => __( 'Haut gauche', 'flib-up' ),
		'top-center'    => __( 'Haut centré', 'flib-up' ),
		'top-right'     => __( 'Haut droite', 'flib-up' ),
		'middle-left'   => __( 'Milieu gauche', 'flib-up' ),
		'center'        => __( 'Centre de l\'écran', 'flib-up' ),
		'middle-right'  => __( 'Milieu droite', 'flib-up' ),
		'bottom-left'   => __( 'Bas gauche', 'flib-up' ),
		'bottom-center' => __( 'Bas centré', 'flib-up' ),
		'bottom-right'  => __( 'Bas droite', 'flib-up' ),
	);
}

/**
 * Emplacements possibles de l'image dans le corps de la pop-up.
 *
 * @return array<string,string>
 */
function flibup_image_positions() {
	return array(
		'top'           => __( 'En-tête pleine largeur (au-dessus du titre, sans marge)', 'flib-up' ),
		'above_title'   => __( 'Au-dessus du titre', 'flib-up' ),
		'below_title'   => __( 'Entre le titre et le texte', 'flib-up' ),
		'below_content' => __( 'Sous le texte', 'flib-up' ),
	);
}

/**
 * Sanitise le contenu riche d'une pop-up.
 *
 * Les utilisateurs habilités (`unfiltered_html`) conservent leur balisage
 * intégral ; les autres passent par `wp_kses_post`. Dans les deux cas les
 * shortcodes (`[...]`) sont préservés puisqu'il ne s'agit pas de HTML.
 *
 * @param mixed $value Contenu brut.
 * @return string
 */
function flibup_sanitize_content( $value ) {
	$value = is_scalar( $value ) ? (string) $value : '';

	if ( current_user_can( 'unfiltered_html' ) ) {
		return $value;
	}

	return wp_kses_post( $value );
}

/**
 * Prépare le contenu d'une pop-up pour l'affichage public.
 *
 * Reproduit la chaîne de filtres de `the_content` (paragraphes, typographie,
 * emojis, intégrations et shortcodes) sans appliquer `the_content` lui-même,
 * ce qui éviterait des effets de bord avec les extensions tierces.
 *
 * @param string $content Contenu enregistré.
 * @return string HTML prêt à afficher.
 */
function flibup_render_content( $content ) {
	$content = (string) $content;

	if ( '' === trim( $content ) ) {
		return '';
	}

	/**
	 * Permet de court-circuiter ou de remplacer le rendu du contenu.
	 *
	 * @param string|null $pre     Contenu de remplacement (null pour continuer).
	 * @param string      $content Contenu brut enregistré.
	 */
	$pre = apply_filters( 'flibup_pre_render_content', null, $content );
	if ( null !== $pre ) {
		return (string) $pre;
	}

	$content = wptexturize( $content );
	$content = convert_smilies( $content );
	$content = wpautop( $content );
	$content = shortcode_unautop( $content );
	$content = do_shortcode( $content );

	/**
	 * Filtre le HTML final du corps de la pop-up.
	 *
	 * @param string $content HTML rendu.
	 */
	return (string) apply_filters( 'flibup_render_content', $content );
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
