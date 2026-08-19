# Flib'Up — mémoire projet

Extension WordPress de gestion de fenêtres pop-up, développée par Les Flibustiers.
Réponds et commente le code en français.

## Architecture

- `flib-up.php` — amorçage, constantes, autoloader PSR-4 maison (sans Composer).
- `includes/class-popup.php` — **source unique de vérité du schéma des champs** (`Popup::schema()`).
- `includes/class-plugin.php` — chef d'orchestre, instancie les modules.
- `admin/class-admin.php` — menu, assets admin et **sanitisation** (`Admin::sanitize_fields()`).
- `admin/class-meta-boxes.php` — interface à onglets de configuration d'une pop-up.
- `public/class-frontend.php` — collecte, shortcodes, rendu du balisage.
- `includes/class-updater.php` — mise à jour depuis les releases GitHub.

L'autoloader déduit le fichier du nom de classe : `FlibUp\Admin\Admin` → `admin/class-admin.php`,
`FlibUp\Frontend\Frontend` → `public/class-frontend.php`, tout le reste → `includes/`.

## Règle absolue : ajouter un réglage

Tout nouveau réglage se déclare à **trois endroits**, sans exception :

1. `Popup::schema()` — la clé et sa valeur par défaut.
2. `Admin::sanitize_fields()` — la règle de sanitisation (`$clean['ma_cle'] = ...`).
3. `Meta_Boxes` — le champ de formulaire, nommé `flibup_ma_cle`.

Un champ absent de l'étape 2 est **silencieusement perdu à l'enregistrement**. Vérifie la
correspondance schéma / sanitisation après toute modification.

Selon l'usage, ajoute aussi la valeur à `Popup::css_vars()` (variable CSS inline) ou à
`Popup::to_frontend_config()` (configuration JSON lue par le JavaScript public).

## Conventions

- Standards de code WordPress, **indentation par tabulations**.
- PHP 8.1 minimum, WordPress 6.0 minimum.
- Toutes les chaînes visibles sont traduisibles, domaine `flib-up`.
- Échappement systématique en sortie (`esc_html`, `esc_attr`, `esc_url`) ; toute exception
  porte un commentaire `phpcs:ignore` justifié.
- JavaScript public en natif, **sans dépendance** (pas de jQuery côté front).
  jQuery est autorisé côté administration uniquement.
- Les valeurs personnalisables passent par des variables CSS définies en inline sur
  `.flibup-overlay`, jamais par du CSS généré à la volée.

## Publier une version

La version se met à jour à **quatre endroits** :

1. En-tête `Version:` dans `flib-up.php`
2. Constante `FLIBUP_VERSION` dans `flib-up.php`
3. `Stable tag:` dans `readme.txt`
4. Section `== Changelog ==` de `readme.txt`

Puis :

```bash
git tag vX.Y.Z && git push origin vX.Y.Z
```

Le tag `v*` déclenche `.github/workflows/build.yml`, qui construit `flib-up.zip`
(dossier racine `flib-up`) et l'attache à la release.

L'updater interne retire le `v` du `tag_name` et le compare à `FLIBUP_VERSION` :
**les deux doivent correspondre**, sinon la mise à jour ne remonte pas dans l'admin du site.
Il télécharge le premier asset `.zip` de la release, pas le zipball automatique.

## Vérifications avant commit

```bash
find . -name "*.php" -not -path "./.git/*" -exec php -l {} \;
node --check assets/js/public.js
node --check assets/js/admin.js
```

Et contrôler que chaque clé de `Popup::schema()` a bien une règle dans
`Admin::sanitize_fields()`, et inversement.

## À ne pas faire

- Ne jamais committer de token, clé d'API ou identifiant.
- Ne pas régénérer `languages/flib-up.pot` à la main : utiliser `wp i18n make-pot`.
- Ne pas remplacer l'autoloader maison par Composer sans en discuter.
