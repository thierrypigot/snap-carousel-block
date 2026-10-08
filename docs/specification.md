# Snap Carousel v2 : spécification du bloc conteneur

Statut : développé (branche `feature/v2-bloc`, 8 octobre 2026), en recette
Date : 8 octobre 2026
Auteur : WeAre[WP]
Premier cas d'usage : section « Nos combats » du site Solidarités International (`themes/solidarites/patterns/contenus/causes.php`)

---

## 1. Contexte et objectif

La v1 du plugin transforme un bloc Groupe, Boucle de requête ou Galerie en carrousel par un **style de bloc**. Cette approche atteint ses limites :

- un style de bloc ne porte aucun réglage : on est limité à 4 variantes figées (1, 2, 3, 4 éléments) ;
- impossible de régler le débord, le voile de fin, la navigation ou le comportement par taille d'écran ;
- le balisage est réécrit par regex et `DOMDocument`, ce qui est fragile ;
- la CSS éditeur est chargée sur `enqueue_block_editor_assets`, donc **absente de l'iframe** de l'éditeur des thèmes FSE ;
- le bloc reste toujours en carrousel, même quand tous les éléments tiennent à l'écran.

**Objectif de la v2** : un vrai bloc conteneur, `wearewp/snap-carousel`, qui affiche en diapositives **tout ce qu'on place dedans**, avec des réglages dans l'inspecteur, en gardant les principes de la v1 : scroll-snap CSS natif, zéro dépendance, accessibilité RGAA / WCAG 2.2 AA.

---

## 2. Périmètre

### Inclus en v2.0

- Bloc `wearewp/snap-carousel` (block.json, éditeur React, rendu serveur `render.php`).
- Trois modes de résolution des diapositives : blocs enfants, Boucle de requête, Galerie (section 4).
- Nombre de diapositives visibles par palier (ordinateur, tablette, mobile), décimales acceptées pour le débord.
- Bascule automatique en grille quand tout tient à l'écran (section 6).
- Voile de fin de piste (et de début, optionnel).
- Flèches précédent / suivant, avec choix de la position.
- Navigation clavier, région live, `prefers-reduced-motion`, RTL (reprise de la v1).
- Transformations de migration depuis les styles de bloc v1 (section 10).

### Hors périmètre v2.0 (évolutions possibles)

- Pagination par points (à étudier en v2.1 : chaque point doit être un bouton nommé, ce qui pèse à 7+ éléments).
- Défilement automatique (exigerait un bouton pause, critère RGAA 13.8 ; non souhaité).
- Boucle infinie (clonage de diapositives, incompatible avec un DOM simple et accessible).
- Glisser à la souris sur ordinateur (le défilement tactile et trackpad est natif).

---

## 3. Identité du bloc

| Clé | Valeur |
|---|---|
| Nom | `wearewp/snap-carousel` |
| Titre | Carrousel défilant (`Snap carousel`) |
| Catégorie | `design` |
| Mots-clés | carrousel, slider, diaporama, défilement |
| Text domain | `snap-carousel-block-style` (slug du plugin conservé pour la continuité des mises à jour) |
| `apiVersion` | 3 |
| `Requires at least` | 6.5 (requis par `viewScriptModule`) ; `listView` ignoré sans effet avant 7.0. Cible de recette Solidarités : WP 7.2, PHP 8.4 |
| `Requires PHP` | 8.0 |
| Version | 2.0.0 (changement majeur : nouveau mode de fonctionnement) |

**Sauvegarde** : `save` ne renvoie que `<InnerBlocks.Content />`, sans enveloppe. Tout le balisage du carrousel est produit par `render.php`.
Deux conséquences voulues :
- aucune erreur de validation de bloc quand on fait évoluer le balisage (pas de `deprecated` à maintenir) ;
- **plugin désactivé** : le contenu enfant s'affiche quand même, empilé. Pas de perte de contenu.

---

## 4. Résolution des diapositives

Le bloc accepte n'importe quel bloc enfant. Le mode est déterminé à partir des enfants directs, de la même façon dans l'éditeur (JS) et au rendu (PHP) :

| Mode | Condition | Piste de défilement | Diapositives |
|---|---|---|---|
| `blocks` | cas général | conteneur du bloc | chaque bloc enfant direct |
| `query` | un seul enfant, `core/query` | le `ul.wp-block-post-template` | chaque `li.wp-block-post` |
| `gallery` | un seul enfant, `core/gallery` | la `figure.wp-block-gallery` | chaque `figure.wp-block-image` (la `figcaption` est exclue) |

Le mode est exposé par une classe sur l'enveloppe : `is-mode-blocks`, `is-mode-query`, `is-mode-gallery`.

### Rendu serveur par mode

- **`blocks`** : `render.php` parcourt `$block->inner_blocks` et rend chaque enfant individuellement. Les frontières de diapositives sont donc connues sans analyser de HTML. Sur la première balise de chaque rendu, `WP_HTML_Tag_Processor` ajoute les attributs ARIA de diapositive. Un enfant dont le rendu est vide (bloc conditionnel) n'est pas compté.
- **`query`** : rendu normal de la boucle, puis `WP_HTML_Tag_Processor` repère le premier `ul.wp-block-post-template` (classe de piste) et ses `li.wp-block-post` (attributs de diapositive). Limite connue et documentée : une boucle imbriquée dans une diapositive aurait aussi des `li.wp-block-post` ; le parcours s'arrête au premier `ul` fermé.
- **`gallery`** : même principe sur les `figure.wp-block-image`.

`DOMDocument` et les regex de la v1 disparaissent.

---

## 5. Attributs et réglages de l'inspecteur

### Attributs

| Attribut | Type | Défaut | Rôle |
|---|---|---|---|
| `perView` | objet `{desktop, tablet, mobile}` | `{3.3, 2.2, 1.15}` | Diapositives visibles par palier. La partie décimale est le débord (3,3 = 3 entières + 30 % de la suivante). Bornes : 1 à 6, pas de 0,05. |
| `fadeEnd` | booléen | `true` | Voile en fin de piste tant qu'il reste du contenu. |
| `fadeStart` | booléen | `false` | Voile en début de piste dès qu'on a défilé. |
| `fadeSize` | `"small"` \| `"medium"` \| `"large"` | `"medium"` | Largeur du voile en part de largeur de diapositive (0,15, 0,3, 0,5), plafonnée à la largeur réelle du débord pour ne jamais voiler une diapositive entière visible. Choix fermé : aucune valeur libre à saisir. |
| `showArrows` | booléen | `true` | Affiche les flèches. |
| `arrowsPosition` | `"top-end"` \| `"bottom-end"` \| `"sides"` | `"top-end"` | Position des flèches. |
| `label` | chaîne | `""` | Nom accessible du carrousel. Vide : libellé générique « Carrousel ». |

### Supports natifs (pas d'attribut maison)

- `spacing.blockGap` : espacement entre diapositives (lu dans `render.php`, exposé en `--snap-gap`).
- `spacing.margin`, `spacing.padding`, `align` (`wide`, `full`), `anchor`, `className`.
- Pas de support `color` : la couleur se propagerait aux diapositives. Les flèches et le focus se règlent par variables (`--snap-arrow-color`, `--snap-arrow-bg`, `--snap-arrow-size`, `--snap-focus-color`), surchargeables par le thème.

### Inspecteur

- Panneau **Affichage** : trois `RangeControl` (ordinateur, tablette, mobile), avec une aide : « Une valeur décimale laisse dépasser la diapositive suivante ».
- Panneau **Voile** : deux bascules + taille (`ToggleGroupControl` Petit / Moyen / Grand).

Principe : uniquement des choix bornés au clic (curseurs, bascules, listes). Aucun champ où saisir une valeur CSS, une classe ou du code, pour qu'une personne non technique ne puisse rien casser.
- Panneau **Navigation** : bascule flèches + position.
- Panneau **Accessibilité** : champ `label` avec l'aide « Décrit le contenu du carrousel pour les lecteurs d'écran, par exemple : Nos combats ».

---

## 6. Comportement par palier et bascule en grille

### Paliers : requêtes de conteneur

L'enveloppe porte `container-type: inline-size`. Les paliers dépendent de la **largeur disponible pour le carrousel**, pas de la largeur de la fenêtre. C'est indispensable quand le carrousel partage la ligne avec un autre élément (le cartouche rouge de Solidarités).

| Palier | Largeur du conteneur |
|---|---|
| mobile | moins de 480 px |
| tablette | de 480 à 767 px |
| ordinateur | 768 px et plus |

Ces seuils sont figés en CSS (une requête de conteneur ne lit pas de variable). À valider en recette sur Solidarités.

### Largeur d'une diapositive

Avec `p` diapositives visibles et `g` l'espacement :

```
largeur = (100 % − arrondi_inférieur(p) × g) / p      si p a une partie décimale
largeur = (100 % − (p − 1) × g) / p                   si p est entier
```

`render.php` calcule le nombre d'espacements par palier et l'expose en variables (`--snap-pv-d`, `--snap-gaps-d`, etc.), la CSS fait le `calc()`.

### Bascule en grille (mode statique)

Si le nombre de diapositives est **inférieur ou égal à la partie entière** de `perView` d'un palier, ce palier passe en **mode statique** :

- grille de `N` colonnes égales qui remplissent la largeur ;
- ni flèches, ni voile, ni `tabindex`, ni rôles de carrousel ;
- pour les lecteurs d'écran, c'est un simple groupe de contenus.

Le serveur pose une classe par palier concerné : `is-static-desktop`, `is-static-tablet`, `is-static-mobile`. Le JS vérifie aussi le débordement réel (`scrollWidth > clientWidth`) comme filet de sécurité, par exemple quand une Boucle renvoie moins d'articles que prévu.

**Exemple Solidarités** : `perView` ordinateur à 3,3. Avec 3 combats, grille de 3 colonnes, identique à l'affichage actuel. Au 4e combat, le carrousel s'active tout seul, sans toucher au pattern.

---

## 7. Balisage produit (mode `blocks`, carrousel actif)

```html
<section class="wp-block-wearewp-snap-carousel is-mode-blocks has-fade-end has-arrows-top-end"
	aria-roledescription="carrousel" aria-label="Nos combats"
	style="--snap-gap:var(--wp--preset--spacing--md);--snap-pv-d:3.3;--snap-gaps-d:3;…">

	<div class="snap-carousel__nav">
		<button type="button" class="snap-carousel__prev" aria-controls="snap-carousel-3" aria-disabled="true">
			<svg aria-hidden="true" focusable="false">…</svg>
			<span class="screen-reader-text">Diapositives précédentes</span>
		</button>
		<button type="button" class="snap-carousel__next" aria-controls="snap-carousel-3">
			<svg aria-hidden="true" focusable="false">…</svg>
			<span class="screen-reader-text">Diapositives suivantes</span>
		</button>
	</div>

	<div class="snap-carousel__track" id="snap-carousel-3" tabindex="0">
		<div class="wp-block-cover …" role="group" aria-roledescription="diapositive" aria-label="1 sur 7">…</div>
		<div class="wp-block-cover …" role="group" aria-roledescription="diapositive" aria-label="2 sur 7">…</div>
		…
	</div>

	<p class="snap-carousel__live screen-reader-text" aria-live="polite" aria-atomic="true"></p>
</section>
```

Points de conception :

- `<section>` avec `aria-roledescription` + `aria-label` : c'est la région carrousel (motif APG), elle englobe les boutons. La v1 posait ces rôles sur la piste seule.
- Les boutons ne sont plus dans un `<nav>` : un repère de navigation par carrousel encombre la liste des repères.
- Le voile est un `mask-image` posé **sur la piste elle-même** : un masque s'applique à la boîte de l'élément, pas au contenu défilé. Aucun élément décoratif ajouté, et ça fonctionne aussi en mode `query` où la piste est le `ul`.
- Pas de `role="region"` sans nom : si `label` est vide, libellé générique « Carrousel ».
- En mode statique : `<div>` au lieu de `<section>`, aucun attribut de carrousel ni de diapositive.

---

## 8. Front : CSS et JS

### CSS (`style.css` du bloc)

- Piste : `display: flex; overflow-x: auto; scroll-snap-type: x mandatory; overscroll-behavior-x: contain; scroll-padding-inline: 0;`.
- Diapositives : `flex: 0 0 var(--snap-slide-w); scroll-snap-align: start; min-width: 0;`, hauteur égale par `align-items: stretch`.
- Barre de défilement : masquée **seulement si les flèches sont affichées**. Sans flèches, barre fine visible pour garder un repère à la souris.
- Voile : `mask-image: linear-gradient(to right, #000 calc(100% - var(--snap-fade)), transparent)`, retiré quand `data-at-end` est présent. Inversé en RTL.
- `prefers-reduced-motion: reduce` : `scroll-behavior: auto`.
- Aucun `!important` sur les règles de mise en page des blocs enfants : la v1 en abusait pour écraser les mises en page Gutenberg ; ici la piste est notre propre élément (sauf en mode `query` / `gallery`, où une spécificité ciblée `.is-mode-query .wp-block-post-template` suffit).

### JS (`view.js`, `viewScriptModule`)

Reprise de la logique v1, simplifiée :

- flèches : défilement d'une diapositive entière (largeur + espacement), RTL géré ;
- état des flèches : `aria-disabled="true"` au début et à la fin (et non `disabled`, qui ferait perdre le focus au bouton) ; sans débordement réel, classe `is-scrollable` retirée et flèches masquées ;
- attributs `data-at-start` / `data-at-end` sur la piste (pilotent les voiles) ;
- clavier quand la piste a le focus : flèches gauche / droite, Début, Fin ;
- région live : annonce « Diapositives 2 à 4 sur 7 » **uniquement après un clic sur une flèche**, pas à chaque défilement (la v1 annonçait aussi au défilement libre, trop bavard) ;
- `ResizeObserver` sur la piste au lieu de l'écouteur `resize` de la fenêtre (réagit aussi aux changements de palier du conteneur) ;
- débordement réel nul : passage en statique côté client (retrait du `tabindex`, flèches masquées).

Chaînes traduisibles transmises par l'API des modules de script (`wp_interactivity_config` ou attribut `data-` sur l'enveloppe), plus de `wp_localize_script`.

Budget : JS < 3 Ko compressé, CSS < 3 Ko compressée, chargés **uniquement sur les pages qui contiennent le bloc** (chargement par bloc natif de block.json).

---

## 9. Éditeur

- Feuilles déclarées dans block.json : `style` (front **et** iframe éditeur) et `editorStyle` (compléments propres à l'éditeur). Rien sur `enqueue_block_editor_assets`.
- Mode `blocks` : `useInnerBlocksProps` est appliqué **directement sur l'élément piste**, avec `orientation: 'horizontal'`. Les diapositives de l'éditeur sont les enveloppes `.block-editor-block-list__block` : la même règle `.snap-carousel__track > *` sert au front et à l'éditeur.
- Mode `query` : la CSS cible `.wp-block-post-template` dans l'éditeur aussi. Seul l'article actif est éditable, les autres sont des aperçus : comportement natif de la Boucle, conservé.
- La piste défile horizontalement dans l'éditeur (WYSIWYG). Les flèches sont affichées mais inertes ; la sélection d'une diapositive hors champ la fait défiler à l'écran (`scrollIntoView` sur `selectedBlockClientId`).
- Ajout d'une diapositive : appendice `ButtonBlockAppender` en fin de piste, plus la duplication native (`Ctrl+Maj+D`).
- Mode statique également rendu dans l'éditeur, pour que l'aperçu à 3 éléments corresponde au front.
- Pas de `templateLock` par défaut.

### Compositions verrouillées en `contentOnly`

C'est le point le plus sensible de la spec. D'après la documentation Gutenberg :

- `contentOnly` **ne peut pas être surchargé** par un enfant : un `templateLock: false` posé sur le carrousel serait ignoré ;
- dans une zone `contentOnly`, on ne peut pas insérer de nouveaux blocs.

Or l'ajout d'un combat passe par l'insertion (ou la duplication) d'une diapositive.

**Résultat du prototype (8 octobre 2026, WP 7.1.3, plugin jetable `_proto-snap-contentonly`)** : c'est faisable, sans déverrouiller la composition.

La règle de l'éditeur (`isContainerInsertableToInContentOnlyMode` dans `block-editor.js`) : dans une composition `contentOnly`, on peut insérer, dupliquer, déplacer et supprimer un bloc si **le conteneur ET l'enfant** sont des « blocs de contenu ». Un bloc de contenu a soit `supports.contentRole: true`, soit un attribut `role: "content"`. C'est le modèle de l'accordéon du cœur (accordion > accordion-item > accordion-panel).

| Configuration testée | Insertion / duplication / déplacement / suppression |
|---|---|
| Carrousel sans `contentRole`, enfants `core/group` | refusés, carrousel entièrement désactivé |
| Carrousel `contentRole` + `listView`, enfants `core/group` | refusés (le groupe n'est pas un bloc de contenu) |
| Carrousel `contentRole` + `listView`, enfants `core/cover` | **autorisés** (`url` de la cover porte `role: content`) |
| Carrousel `contentRole` + `listView`, enfants `proto/slide` (`contentRole`) contenant un groupe | **autorisés** |

Parcours vérifié au clic : sélection d'une cover, menu « ⋮ », « Dupliquer » ; le menu propose aussi « Ajouter avant / après » et « Supprimer », et les flèches de déplacement sont gauche / droite grâce à `orientation: 'horizontal'`. `insertBlocks` appelé par code passe aussi, mais devient inutile.

Le panneau Vue en liste (`listView`) n'apporte pas l'ajout : il liste les contenus (titres, paragraphes) à plat. Il reste utile pour la navigation, on le garde.

**Décisions qui en découlent :**

1. Le carrousel déclare `supports: { contentRole: true, listView: true }`. Obligatoire.
2. Un bloc compagnon **`wearewp/snap-carousel-slide`** (`contentRole: true`, InnerBlocks libres, `parent: ["wearewp/snap-carousel"]` pour ne pas encombrer l'outil d'insertion ailleurs) sert d'enveloppe aux diapositives faites de blocs non-contenu (groupe, colonnes). Les blocs déjà « de contenu » (cover, image, paragraphe, titre) restent des diapositives directes, sans enveloppe. La règle « chaque enfant direct est une diapositive » (section 4) ne change pas.
3. Dans un `contentOnly`, « Ajouter après » insère un paragraphe, qui deviendrait une diapositive. Le carrousel prend en charge `supports.allowedBlocks` (attribut `allowedBlocks`, WP 6.5+) pour que les patterns restreignent les enfants, par exemple à `core/cover` pour « Nos combats ».
4. Les transformations v1 enveloppent chaque enfant non-contenu dans un `snap-carousel-slide`, pour que les carrousels migrés restent éditables en `contentOnly`.
5. Non testé : la Boucle de requête dans un carrousel en `contentOnly` (sans objet pour Solidarités, à vérifier pour le cas générique).

---

## 10. Migration depuis la v1

### Transformations de bloc

| Source | Résultat |
|---|---|
| `core/group` + `is-style-snap-carousel` | carrousel, enfants conservés, `perView` ordinateur 3,2 |
| `…-single` | `perView` 1,1 / 1,1 / 1,05 |
| `…-duo` | `perView` 2,1 / 1,6 / 1,1 |
| `…-quad` | `perView` 4,1 / 2,2 / 1,15 |
| `core/query` ou `core/gallery` + style v1 | le bloc est **placé dans** un carrousel (mode `query` / `gallery`), son style v1 est retiré |

Le groupe source est remplacé par le carrousel : sa couleur, son espacement et son alignement sont reportés sur le carrousel.

### Rétrocompatibilité

- Les styles de bloc v1 restent enregistrés et fonctionnels en 2.x, libellés « (obsolète) ». Aucun site en production ne casse à la mise à jour.
- Un avis dans l'éditeur, sur un bloc portant un style v1, propose la transformation en un clic.
- Suppression des styles v1 en 3.0, annoncée dans le changelog et la section « Upgrade Notice » du readme.

---

## 11. Critères d'accessibilité (RGAA 4.1 / WCAG 2.2 AA)

| Critère | Exigence |
|---|---|
| Nom de la région | `aria-label` issu de `label` ; jamais de région sans nom |
| Diapositives | `role="group"`, `aria-roledescription="diapositive"`, `aria-label="n sur N"` |
| Boutons | `<button type="button">` natifs, nom textuel, `aria-controls`, `aria-disabled` aux extrémités, cible ≥ 44 × 44 px |
| Focus | visible sur la piste et les boutons, contraste ≥ 3:1 (couleur héritée du thème) |
| Clavier | Tab atteint les boutons puis la piste ; flèches, Début, Fin font défiler ; le focus sur un lien hors champ le ramène à l'écran |
| Mouvement | aucun défilement automatique ; `prefers-reduced-motion` respecté |
| Restitution | annonce de position polie, uniquement après action sur une flèche |
| Zoom et adaptation | lisible à 320 px de large et à 200 % de zoom texte, sans défilement horizontal de la page |
| Mode statique | aucun rôle de carrousel, aucun `tabindex` superflu |
| Sans JS | la piste reste défilable au doigt, au trackpad et au clavier ; aucun contenu masqué |

---

## 12. Intégration Solidarités : section « Nos combats »

Modifications du pattern `causes.php` :

1. Le groupe de grille à 4 colonnes devient une **rangée flex** : `[cartouche rouge] [carrousel]`.
2. Cartouche : largeur fixe (une colonne de la grille actuelle, environ 23 % de la largeur large), `flex-shrink: 0`, verrouillé avec `lock: {move: true, remove: true}`.
3. Carrousel : `flex: 1; min-width: 0`, `perView` 3,3 / 2,2 / 1,15, `label` « Nos combats », voile de fin activé, flèches en haut à droite.
4. Les covers des combats deviennent les enfants du carrousel, sans changement de contenu.
5. Le `templateLock: contentOnly` de la section est **conservé** (validé par le prototype, section 9). Le carrousel y porte `allowedBlocks: ["core/cover"]` : l'équipe ajoute un combat en dupliquant une tuile.
6. Mobile : la rangée passe en colonne, le cartouche au-dessus, le carrousel en pleine largeur.
7. La courbe des images reste exclue des tuiles (réservée aux bandeaux et au CTA 50/50).

Rendu attendu avec 3 combats : identique à aujourd'hui. Avec 4 à 7 : 3 tuiles entières, la 4e coupée sous le voile, flèches actives.

---

## 13. Critères de recette

### Fonctionnels

- [ ] Mode `blocks` avec 2, 3, 4 et 7 enfants de types variés (cover, groupe, colonnes).
- [ ] Mode `query` avec 3 et 9 articles ; mode `gallery` avec légende de galerie.
- [ ] Bascule statique / carrousel correcte à chaque palier, y compris en redimensionnant.
- [ ] Voiles début et fin apparaissent et disparaissent aux bonnes positions, en LTR et RTL.
- [ ] Flèches désactivées aux extrémités ; une flèche fait avancer d'exactement une diapositive.
- [ ] Rendu identique front et éditeur (thème FSE, iframe), y compris le mode statique.
- [ ] Plugin désactivé : contenus visibles, empilés, sans erreur.
- [ ] Transformations v1 vers v2 sur les 4 variantes et les 3 types de blocs.

### Accessibilité

- [ ] Parcours complet au clavier seul.
- [ ] NVDA + Firefox et VoiceOver + Safari : nom de région, « 2 sur 7 », annonces après flèche.
- [ ] Axe DevTools : zéro erreur sur les modes carrousel et statique.
- [ ] Zoom 200 % et largeur 320 px.

### Qualité et éco-conception

- [ ] PHPCS (WordPress-Extra) sans erreur ; `@since 2.0.0` sur les nouvelles fonctions.
- [ ] Aucun asset chargé sur une page sans carrousel.
- [ ] Budgets JS et CSS respectés.
- [ ] Chaînes traduites en fr_FR, relues contre le glossaire Polyglots.

---

## 14. Estimation

| Lot | Charge |
|---|---|
| Prototype `contentOnly` (section 9) | fait |
| Socle du bloc et du bloc diapositive : block.json, inspecteur, résolution des modes en JS et PHP | 1,25 j |
| Rendu serveur, CSS (paliers, grille statique, voiles), JS de navigation | 1 j |
| Éditeur : rendu horizontal dans l'iframe, modes `query` et `gallery`, défilement à la sélection | 0,5 j |
| Migration : transformations, styles v1 obsolètes, avis éditeur | 0,5 j |
| Accessibilité et recette multi-navigateurs / lecteurs d'écran | 0,5 j |
| Intégration Solidarités (pattern `causes.php`) et recette | 0,5 j |
| **Total** | **4,25 j** |

Marge réaliste : 4 à 5 jours, surtout selon les surprises du mode `query` dans l'éditeur.

---

## 15. Questions ouvertes

0. ~~Ajout de diapositives dans une composition `contentOnly`~~ : résolu par le prototype (section 9). À revérifier sur WP 7.2 en recette.
1. ~~Seuils des paliers~~ : passés de 560 / 880 à **480 / 768 px** de conteneur après recette (à 560 / 880, le carrousel placé à côté du cartouche, environ 840 px, tombait en palier tablette).
2. **Nom affiché du bloc** : « Carrousel défilant » ou autre ? Le slug du plugin reste `snap-carousel-block-style` pour ne pas casser les mises à jour.
3. **Pagination par points** : nécessaire en v2.0 ou reportée en v2.1 ?
4. **Publication WordPress.org** : la v2 est-elle publiée sur le dépôt, ou d'abord déployée sur Solidarités seulement ?
