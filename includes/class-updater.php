<?php
/**
 * Mécanisme de mise à jour depuis les releases GitHub.
 *
 * @package FlibUp
 */

namespace FlibUp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interroge l'API GitHub, met en cache la réponse et branche le système
 * de mise à jour natif de WordPress.
 */
class Updater {

	const TRANSIENT = 'flibup_github_release';
	const CACHE_TTL = 6 * HOUR_IN_SECONDS;

	/**
	 * Slug du plugin (flib-up/flib-up.php).
	 *
	 * @var string
	 */
	private $basename;

	/**
	 * Dossier du plugin (flib-up).
	 *
	 * @var string
	 */
	private $slug;

	/**
	 * Constructeur.
	 */
	public function __construct() {
		$this->basename = FLIBUP_BASENAME;
		$this->slug     = dirname( FLIBUP_BASENAME );
	}

	/**
	 * Accroche les hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_details' ), 10, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'fix_source_dir' ), 10, 4 );

		// Purge du cache lors d'une vérification manuelle des mises à jour.
		add_action( 'upgrader_process_complete', array( $this, 'purge_cache' ), 10, 2 );
	}

	/**
	 * Récupère la dernière release GitHub (avec cache par transient).
	 *
	 * @param bool $force Ignore le cache si vrai.
	 * @return array|null Données normalisées ou null en cas d'échec.
	 */
	private function get_latest_release( $force = false ) {
		$user = Settings::github_user();
		$repo = Settings::github_repo();

		if ( '' === $user || '' === $repo ) {
			return null;
		}

		if ( ! $force ) {
			$cached = get_transient( self::TRANSIENT );
			if ( is_array( $cached ) ) {
				return $cached;
			}
			// Un échec récent est mis en cache sous forme de chaîne pour ne pas
			// solliciter l'API à chaque page admin.
			if ( 'error' === $cached ) {
				return null;
			}
		}

		$url  = sprintf( 'https://api.github.com/repos/%s/%s/releases/latest', rawurlencode( $user ), rawurlencode( $repo ) );
		$args = array(
			'timeout' => 15,
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'FlibUp-Updater',
			),
		);

		/**
		 * Permet de fournir un token GitHub (dépôt privé) sans l'écrire dans le
		 * code. Retourner une chaîne non vide l'ajoute à l'en-tête Authorization.
		 *
		 * @param string $token Token (vide par défaut).
		 */
		$token = (string) apply_filters( 'flibup_github_token', '' );
		if ( '' !== $token ) {
			$args['headers']['Authorization'] = 'Bearer ' . $token;
		}

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			set_transient( self::TRANSIENT, 'error', 30 * MINUTE_IN_SECONDS );
			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== (int) $code ) {
			set_transient( self::TRANSIENT, 'error', 30 * MINUTE_IN_SECONDS );
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['tag_name'] ) ) {
			set_transient( self::TRANSIENT, 'error', 30 * MINUTE_IN_SECONDS );
			return null;
		}

		$version   = ltrim( (string) $body['tag_name'], 'vV' );
		$zip_url   = $this->pick_zip_url( $body );
		$changelog = isset( $body['body'] ) ? (string) $body['body'] : '';

		$data = array(
			'version'   => $version,
			'zip_url'   => $zip_url,
			'changelog' => $changelog,
			'html_url'  => isset( $body['html_url'] ) ? esc_url_raw( $body['html_url'] ) : '',
			'published' => isset( $body['published_at'] ) ? (string) $body['published_at'] : '',
		);

		set_transient( self::TRANSIENT, $data, self::CACHE_TTL );

		return $data;
	}

	/**
	 * Choisit l'URL du ZIP : un asset « .zip » attaché en priorité, sinon
	 * l'archive automatique générée par GitHub (zipball).
	 *
	 * @param array $release Corps de la release.
	 * @return string
	 */
	private function pick_zip_url( array $release ) {
		if ( ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
			foreach ( $release['assets'] as $asset ) {
				if ( isset( $asset['browser_download_url'] ) && '.zip' === substr( (string) $asset['name'], -4 ) ) {
					return esc_url_raw( $asset['browser_download_url'] );
				}
			}
		}
		return isset( $release['zipball_url'] ) ? esc_url_raw( $release['zipball_url'] ) : '';
	}

	/**
	 * Injecte l'info de mise à jour dans le transient WordPress.
	 *
	 * @param mixed $transient Transient update_plugins.
	 * @return mixed
	 */
	public function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			$transient = new \stdClass();
		}
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();
		if ( null === $release || empty( $release['version'] ) || empty( $release['zip_url'] ) ) {
			return $transient;
		}

		if ( version_compare( $release['version'], FLIBUP_VERSION, '>' ) ) {
			$item = array(
				'id'          => $this->basename,
				'slug'        => $this->slug,
				'plugin'      => $this->basename,
				'new_version' => $release['version'],
				'url'         => 'https://github.com/' . Settings::github_user() . '/' . Settings::github_repo(),
				'package'     => $release['zip_url'],
				'tested'      => '',
				'requires'    => '',
				'requires_php' => '8.1',
			);
			$transient->response[ $this->basename ] = (object) $item;
		} else {
			// Aucune mise à jour : on renseigne no_update pour un affichage propre.
			$item = array(
				'id'          => $this->basename,
				'slug'        => $this->slug,
				'plugin'      => $this->basename,
				'new_version' => FLIBUP_VERSION,
				'url'         => 'https://github.com/' . Settings::github_user() . '/' . Settings::github_repo(),
				'package'     => '',
			);
			$transient->no_update[ $this->basename ] = (object) $item;
		}

		return $transient;
	}

	/**
	 * Fournit les détails affichés dans la fenêtre « Voir les détails ».
	 *
	 * @param mixed  $result Résultat courant.
	 * @param string $action Action demandée.
	 * @param object $args   Arguments.
	 * @return mixed
	 */
	public function plugin_details( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}
		if ( empty( $args->slug ) || $args->slug !== $this->slug ) {
			return $result;
		}

		$release = $this->get_latest_release();
		if ( null === $release ) {
			return $result;
		}

		$info               = new \stdClass();
		$info->name         = "Flib'Up";
		$info->slug         = $this->slug;
		$info->version      = $release['version'];
		$info->author       = '<a href="https://lesflibustiers.fr">Les Flibustiers</a>';
		$info->homepage     = 'https://github.com/' . Settings::github_user() . '/' . Settings::github_repo();
		$info->requires     = '6.0';
		$info->requires_php = '8.1';
		$info->download_link = $release['zip_url'];
		$info->sections     = array(
			'description' => esc_html__( 'Gestion de pop-ups pour WordPress.', 'flib-up' ),
			'changelog'   => wpautop( esc_html( $release['changelog'] ) ),
		);

		return $info;
	}

	/**
	 * Renomme le dossier extrait pour qu'il corresponde au slug attendu.
	 *
	 * L'archive GitHub (zipball) crée un dossier « user-repo-hash » ; sans
	 * correction, WordPress installerait le plugin dans un mauvais dossier.
	 *
	 * @param string $source        Dossier source extrait.
	 * @param string $remote_source Dossier temporaire distant.
	 * @param object $upgrader      Instance de l'upgrader.
	 * @param array  $hook_extra    Données contextuelles.
	 * @return string|\WP_Error
	 */
	public function fix_source_dir( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		global $wp_filesystem;

		// N'agit que pour notre plugin.
		if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->basename ) {
			return $source;
		}

		if ( ! $wp_filesystem ) {
			return $source;
		}

		$desired = trailingslashit( $remote_source ) . $this->slug . '/';

		if ( trailingslashit( $source ) === $desired ) {
			return $source;
		}

		if ( $wp_filesystem->move( $source, $desired, true ) ) {
			return $desired;
		}

		return new \WP_Error(
			'flibup_rename_failed',
			esc_html__( "Impossible de renommer le dossier du plugin lors de la mise à jour.", 'flib-up' )
		);
	}

	/**
	 * Purge le cache après une mise à jour.
	 *
	 * @param object $upgrader Upgrader.
	 * @param array  $data     Données du process.
	 * @return void
	 */
	public function purge_cache( $upgrader, $data ) {
		if ( isset( $data['type'] ) && 'plugin' === $data['type'] ) {
			delete_transient( self::TRANSIENT );
		}
	}
}
