=== Flib'Up ===
Contributors: lesflibustiers
Tags: popup, pop-up, modal, cta, marketing
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Création, configuration, programmation et affichage de fenêtres pop-up sur WordPress. Ciblage des pages, fréquence, programmation, accessibilité et mise à jour depuis GitHub.

== Description ==

Flib'Up permet de gérer plusieurs pop-ups indépendantes depuis l'administration WordPress :

* Contenu riche (éditeur WYSIWYG natif), shortcodes interprétés, image facultative dans le corps et bouton d'action.
* Dimensions, couleurs et mise en page entièrement configurables, responsive.
* Ciblage : tout le site, page d'accueil, pages, articles, sélection précise, exclusions (recherche AJAX intégrée, sans dépendance externe).
* Fréquence : à chaque chargement, une fois par session, une fois par visiteur, ou réaffichage après X jours, avec version de campagne pour forcer une réapparition.
* Déclenchement immédiat, différé, ou au clic sur un bouton (shortcode, attribut HTML ou API JavaScript).
* Position libre à l'écran : centre, coins, haut, bas ou côtés, avec marges réglables.
* Programmation par dates de début/fin, dans le fuseau horaire de WordPress.
* Masque configurable (couleur, opacité, transparence, flou, animation), blocage du défilement et mode non bloquant laissant le site utilisable.
* Bouton de fermeture personnalisable, accessible au clavier.
* Accessibilité : rôle dialog, aria-modal, piège de focus, restauration du focus, touche Échap, prise en compte de prefers-reduced-motion.
* Gestion de plusieurs pop-ups : priorité et file d'attente.
* Mise à jour depuis les releases GitHub.

== Installation ==

1. Téléversez le dossier `flib-up` dans `/wp-content/plugins/`, ou installez l'archive ZIP via Extensions > Ajouter > Téléverser une extension.
2. Activez l'extension depuis le menu Extensions.
3. Rendez-vous dans le menu « Flib'Up » pour créer votre première pop-up.

== Foire aux questions ==

= Comment ouvrir une pop-up au clic sur un bouton ? =

Dans l'onglet « Déclenchement et fréquence », choisissez « Au clic sur un élément de la page ». Trois branchements sont ensuite possibles, au choix :

1. Le shortcode `[flibup_button id="12" text="En savoir plus"]`, à poser dans une page, un article ou un widget. Attributs facultatifs : `class`, `style` (`button` ou `link`), `title`. Le shortcode force l'affichage de la pop-up sur la page concernée, même si le ciblage ne l'y prévoyait pas.
2. L'attribut `data-flibup-open="12"` sur n'importe quel bouton ou lien existant (constructeur de page, menu, bloc HTML). Un lien pointant vers `#flibup-12` fonctionne également.
3. L'appel JavaScript `FlibUp.open(12)`.

Pour les méthodes 2 et 3, vérifiez que le ciblage inclut bien la page où se trouve le déclencheur.

= Quelles fonctions JavaScript sont exposées ? =

* `FlibUp.open( id, force )` — ouvre une pop-up. Le second paramètre, facultatif, ignore le plafond de fréquence et les dates de diffusion.
* `FlibUp.close( id )` — ferme une pop-up, ou toutes les pop-ups ouvertes si l'identifiant est omis.
* `FlibUp.reset( id )` — efface la mémorisation « déjà vue » d'une pop-up (pratique en recette).

= Les shortcodes fonctionnent-ils dans le contenu de la pop-up ? =

Oui. Le contenu est rendu avec la même chaîne de traitement que le contenu d'un article (typographie, paragraphes, shortcodes). Le rendu est déclenché tôt dans la page afin que les extensions tierces (formulaires, cartes, galeries) puissent encore charger leurs propres styles et scripts.

Saisissez de préférence les shortcodes depuis l'onglet « Texte » de l'éditeur, pour éviter que l'éditeur visuel ne les reformate.

= Une pop-up placée dans un coin bloque tout mon site, est-ce normal ? =

Par défaut la pop-up est modale : le masque couvre la page entière et capte les clics. Pour un encart de type bandeau ou notification, cochez « Masque non bloquant » dans l'onglet « Masque » : le site reste cliquable et défilable, et la fenêtre n'est plus annoncée comme modale aux technologies d'assistance.

= Puis-je afficher une image et du texte dans la même pop-up ? =

Oui. L'onglet « Contenu » propose un sélecteur d'image (médiathèque ou URL externe) avec quatre emplacements : en-tête pleine largeur, au-dessus du titre, entre le titre et le texte, ou sous le texte. Vous pouvez également insérer des images directement dans l'éditeur de contenu.

== Changelog ==

= 1.1.0 =
* Ajout du choix de la position de la pop-up à l'écran (9 emplacements) avec marges réglables par rapport aux bords.
* Ajout d'une image dans le corps de la pop-up : médiathèque ou URL externe, quatre emplacements, largeur, alignement, arrondi, texte alternatif et lien facultatif.
* Interprétation des shortcodes dans le contenu, avec rendu anticipé pour que les extensions tierces puissent charger leurs assets.
* Éditeur de contenu complet (boutons média, barre d'outils étendue, onglet Texte).
* Nouveau mode de déclenchement au clic, via shortcode `[flibup_button]`, attribut `data-flibup-open`, ancre `#flibup-ID` ou API `FlibUp.open()`.
* Nouvelle option « masque non bloquant » laissant le site utilisable pendant l'affichage.
* Les pop-ups déclenchées au clic ne sont plus écartées par la règle « une seule pop-up par page ».
* Correction : les écouteurs d'événements n'étaient plus dupliqués à chaque réouverture d'une même pop-up.
* Gestion d'une pile de pop-ups ouvertes (touche Échap, restauration du focus, blocage du défilement).

= 1.0.0 =
* Version initiale.
