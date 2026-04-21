# TravelMate — Budget & Dépenses
## Dossier de présentation complet

---

## 1. VUE D'ENSEMBLE

TravelMate est une application de gestion de voyages entre amis. Le module **Budget & Dépenses** permet de créer des budgets de voyage, suivre les dépenses en temps réel, et bénéficier d'assistants IA pour optimiser ses finances de voyage.

**Entités principales :**
- `Budget` — montant total, devise, statut, description, lié à un Voyage et un User
- `Depense` — montant, libellé, catégorie, date, type de paiement, devise, liée à un Budget

---

## 2. FONCTIONNALITÉS CRUD

### 2.1 Gestion des Budgets
| Action | Route | Description |
|--------|-------|-------------|
| Liste | `GET /budget/` | Affiche tous les budgets avec filtres |
| Créer | `GET/POST /budget/new` | Formulaire de création |
| Détail | `GET /budget/{id}` | Vue complète avec dépenses |
| Modifier | `GET/POST /budget/{id}/edit` | Édition avec conversion de devise |
| Supprimer | `POST /budget/{id}` | Suppression avec token CSRF |

### 2.2 Gestion des Dépenses
| Action | Route | Description |
|--------|-------|-------------|
| Ajouter | `POST /budget/{budgetId}/depense/new` | Ajout via modal |
| Modifier | `POST /budget/depense/{id}/edit` | Édition via modal |
| Supprimer | `POST /budget/depense/{id}/delete` | Suppression avec confirmation |

### 2.3 Catégories de dépenses
- Hébergement, Transport, Restauration, Loisirs, Achats, Santé, Autre

### 2.4 Types de paiement
- Espèces, Carte bancaire, Virement, Mobile Pay, Autre

---

## 3. INTERFACE UTILISATEUR

### 3.1 Page Liste (index)
- **Hero pleine largeur** avec photo de destination en arrière-plan, gradient sombre, texte blanc
- **Statistiques globales** : total budgets, montant total, budgets dépassés, restant global
- **Barre de filtres** : recherche textuelle, filtre par voyage (dropdown), filtre par devise (chips), filtre par statut (Contrôlé/Dépassé)
- **Grille de cartes** avec photos de destinations Unsplash, barre de progression colorée, actions rapides
- **Panneau de détail latéral** : s'ouvre au clic sur une carte, affiche dépenses, stats, actions
- **Sélection multiple** avec suppression groupée
- **Pagination** côté client (6 cartes par page)
- **Tri** : par nom, montant, restant, nombre de dépenses

### 3.2 Page Détail (show)
- **Hero pleine largeur** avec photo rotative selon l'ID du budget, barre de progression animée, stats en overlay
- **Convertisseur de devise inline** (Frankfurter API, temps réel)
- **Liste des dépenses** avec recherche, filtres par catégorie (chips), tri, sélection multiple
- **Export Excel** (.xlsx stylisé avec SheetJS) et **Export PDF** (jsPDF + autoTable)
- **Sidebar** avec : Informations, Actions, Gamification, Would You Rather, Assistants IA

### 3.3 Accessibilité
- **Widget flottant** (bouton ♿ en bas à droite) disponible sur tout le site
- Lecture au survol (TTS Web Speech API, voix française)
- Lecture de la liste des dépenses
- Zoom texte (A− / ↺ / A+) persisté en localStorage
- Dark mode avec palette terracotta/brun chaud

### 3.4 Traduction
- Boutons FR / EN / ES dans le widget accessibilité
- Traduction via API MyMemory (gratuite)
- Cache en mémoire pour éviter les appels répétés
- Textes marqués `data-translate` traduits automatiquement

---

## 4. APIS EXTERNES INTÉGRÉES

### 4.1 Frankfurter API (Conversion de devises)
- **URL** : `https://api.frankfurter.app`
- **Gratuit** : oui, sans clé
- **Usage** : conversion en temps réel entre TND, EUR, USD, GBP, MAD, CHF, JPY, CAD
- **Endpoint utilisé** : `GET /latest?from={FROM}&to={TO}&amount={AMOUNT}`
- **Implémentation** : `CurrencyService.php`
- **Fonctionnalités** :
  - Conversion inline sur la page détail
  - Conversion automatique lors de l'ajout d'une dépense dans une devise différente
  - Conversion de toutes les dépenses lors du changement de devise du budget

### 4.2 OpenRouter API (Intelligence Artificielle)
- **URL** : `https://openrouter.ai/api/v1/chat/completions`
- **Gratuit** : oui (modèles :free)
- **Clé** : stockée dans `.env.local` (jamais exposée côté client)
- **Modèles utilisés** (fallback automatique) :
  1. `meta-llama/llama-3.3-70b-instruct:free` (Llama 3.3 70B — Meta)
  2. `google/gemma-3-27b-it:free` (Gemma 3 27B — Google)
  3. `google/gemma-3-12b-it:free` (Gemma 3 12B — Google)
  4. `meta-llama/llama-3.2-3b-instruct:free` (Llama 3.2 3B — Meta)
- **Architecture** : appel côté serveur (Symfony) → réponse JSON → affichage côté client

#### 3 Modèles IA implémentés :

**Modèle 1 — Estimation du coût de voyage**
- Route : `POST /budget/{id}/ai/estimate`
- Inputs : destination, durée (jours), nombre de personnes
- Prompt : demande au LLM d'estimer le coût total, coût/jour/personne, faisabilité avec le budget disponible, et 2 conseils d'optimisation
- Output : texte structuré en français

**Modèle 2 — Prédiction de dépassement**
- Route : `POST /budget/{id}/ai/predict-overrun`
- Inputs : jours restants du voyage
- Contexte envoyé : budget total, montant dépensé, %, restant, catégories dépensées
- Prompt : demande une prédiction de dépassement, niveau de risque (Faible/Modéré/Élevé/Critique), projection totale, 2 recommandations
- Output : analyse personnalisée basée sur les vraies données

**Modèle 3 — Recommandation de voyage**
- Route : `POST /budget/{id}/ai/recommend-trip`
- Inputs : style de voyage (aventure, culture, plage, gastronomie, nature, luxe)
- Contexte envoyé : budget disponible (restant ou total), devise
- Prompt : demande une destination précise, durée idéale, hébergement suggéré, 3 activités, estimation du coût
- Output : recommandation complète et personnalisée

### 4.3 MyMemory Translation API
- **URL** : `https://api.mymemory.translated.net`
- **Gratuit** : oui, sans clé (avec email optionnel pour quota plus élevé)
- **Usage** : traduction FR → EN / ES des textes de l'interface
- **Implémentation** : JavaScript côté client avec cache mémoire

### 4.4 Unsplash (Photos)
- **Usage** : photos de destinations pour les cartes et heroes
- **Gratuit** : oui (URLs directes sans clé pour usage basique)
- **Rotation** : 10 photos différentes assignées par `budget.idBudget % 10`

### 4.5 SheetJS (Export Excel)
- **CDN** : `https://cdn.sheetjs.com/xlsx-0.20.2/package/dist/xlsx.full.min.js`
- **Gratuit** : oui
- **Usage** : génération de fichiers .xlsx stylisés avec en-têtes colorés, lignes alternées, totaux

### 4.6 jsPDF + AutoTable (Export PDF)
- **CDN** : cdnjs.cloudflare.com
- **Gratuit** : oui
- **Usage** : export PDF des dépenses avec tableau formaté, en-têtes, pied de page

---

## 5. GAMIFICATION

Toutes les métriques sont calculées **uniquement** depuis les entités Budget et Depense existantes.

### 5.1 Score global (0–100)
Formule : `(badges_gagnés / total_badges) × 60 + (streak × 5) + (sous_budget ? 20 : 0)`
Affiché sous forme d'anneau SVG animé (vert ≥70, orange ≥40, rouge <40)

### 5.2 Savings Streak
- Calcule les jours consécutifs où les dépenses sont inférieures au budget journalier
- Budget journalier = montant total ÷ nombre de jours actifs (déduit des dates de dépenses)
- Affiché avec flamme et compteur

### 5.3 Badges (6 achievements)
| Badge | Condition | Icône |
|-------|-----------|-------|
| Premier budget | ≥1 budget créé | ⭐ |
| Budget maîtrisé | Pas de dépassement | 🛡️ |
| Série de 3 jours | Streak ≥ 3 jours | 🔥 |
| Économe | Économisé ≥ 20% du budget | 🐷 |
| Suivi rigoureux | ≥ 10 dépenses enregistrées | 📋 |
| Voyageur expérimenté | ≥ 3 budgets créés | 🌍 |

Badges non gagnés affichent une barre de progression vers l'objectif.

### 5.4 Budget Challenge
- Compare automatiquement le budget actuel avec le précédent budget (celui avec le plus de dépenses)
- Objectif : dépenser 20% de moins en pourcentage du budget
- Barre de progression bleue/verte selon l'avancement
- Route : `GET /budget/{id}/gamification`

---

## 6. WOULD YOU RATHER — JEU INTERACTIF

Fonctionnalité ludique pour explorer les compromis financiers entre amis.

### Fonctionnement
- Génère 5 dilemmes dynamiquement depuis les vraies dépenses du budget
- Chaque dilemme présente 2 options avec calcul d'économie réel
- Clic sur une option → animation des barres de comparaison, mise en évidence du meilleur choix financier, explication détaillée

### Types de dilemmes générés
1. **Réduction globale vs suppression ciblée** : "Réduire tout de 10% vs supprimer la catégorie la plus chère"
2. **Top catégorie vs 2ème catégorie** : "Couper [Transport] de 50% vs éliminer [Restauration] complètement" (noms réels)
3. **Fréquence vs qualité** : "Moins de sorties, même qualité vs même fréquence, moins cher"
4. **Hébergement vs activités** : "Hôtel moins cher + plus d'activités vs moins de restos + hôtel confort"
5. **Transport vs shopping** : "Transports en commun uniquement vs zéro shopping souvenirs"

### Données utilisées
- `data-cat-display` : catégorie de chaque dépense
- `data-montant` : montant de chaque dépense
- Calculs en temps réel depuis le DOM

---

## 7. SCÉNARIO DE DÉMONSTRATION

### Étape 1 — Page liste (2 min)
1. Ouvrir `/budget/`
2. Montrer le hero pleine largeur avec photo de voyage
3. Démontrer les filtres : rechercher "Rome", filtrer par devise "EUR", filtrer "Dépassé"
4. Cliquer sur une carte → panneau de détail latéral s'ouvre
5. Montrer la sélection multiple et suppression groupée

### Étape 2 — Créer un budget (1 min)
1. Cliquer "Nouveau budget"
2. Remplir : "Voyage Rome Printemps", 2000 EUR, lié à un voyage
3. Sauvegarder → redirection vers la liste

### Étape 3 — Page détail (3 min)
1. Ouvrir un budget existant avec plusieurs dépenses
2. Montrer le hero avec barre de progression animée
3. Ajouter une dépense via le modal (bouton "+ Ajouter une dépense")
4. Montrer la recherche et les filtres par catégorie
5. Démontrer le convertisseur de devise inline (TND → EUR)
6. Exporter en Excel → ouvrir le fichier stylisé

### Étape 4 — Assistants IA (3 min)
1. Ouvrir le panneau "Intelligence Artificielle" dans la sidebar
2. **Modèle 1** : Saisir "Rome", 7 jours, 3 personnes → cliquer "Estimer le coût"
   - Attendre la réponse Llama 3.3 (5–10 secondes)
   - Montrer l'analyse personnalisée avec le budget disponible
3. **Modèle 2** : Saisir 3 jours restants → "Analyser le risque"
   - Montrer la prédiction de dépassement avec niveau de risque
4. **Modèle 3** : Choisir "Plage" → "Recommander un voyage"
   - Montrer la recommandation complète avec destination, hébergement, activités

### Étape 5 — Gamification (2 min)
1. Montrer le panneau "Récompenses" dans la sidebar
2. Expliquer le score (anneau animé)
3. Montrer les badges gagnés vs en cours (barres de progression)
4. Montrer le Budget Challenge vs le voyage précédent

### Étape 6 — Would You Rather (2 min)
1. Montrer le jeu "Would You Rather?" dans la sidebar
2. Lire le dilemme généré depuis les vraies dépenses
3. Cliquer sur une option → animation des barres, résultat
4. Cliquer "Prochain dilemme" pour montrer la variété
5. Expliquer que les montants sont calculés depuis les vraies dépenses

### Étape 7 — Accessibilité & Traduction (1 min)
1. Cliquer le bouton ♿ en bas à droite
2. Activer "Lire au survol" → survoler des éléments
3. Changer la langue en EN → montrer la traduction automatique
4. Activer le dark mode → montrer la palette sombre
5. Zoomer le texte avec A+

---

## 8. ARCHITECTURE TECHNIQUE

```
Frontend (Twig + JS)
    ↓ fetch()
Symfony Controller (BudgetController.php)
    ↓ cURL
OpenRouter API → Llama 3.3 / Gemma 3
    ↓ JSON
Symfony Controller
    ↓ JsonResponse
Frontend (affichage)
```

### Sécurité
- Clé API OpenRouter dans `.env.local` (gitignored), jamais exposée au navigateur
- Tokens CSRF sur tous les formulaires de modification/suppression
- Validation côté serveur de toutes les entrées (hydrateDepense)
- Sanitisation HTML des inputs avant envoi au LLM (htmlspecialchars)

### Performance
- Photos Unsplash avec `loading="lazy"` sauf hero (`loading="eager"`)
- Cache des traductions en mémoire JavaScript (évite les appels répétés)
- Filtres et pagination côté client (pas de rechargement de page)
- Fallback automatique entre 4 modèles IA si l'un est indisponible

---

## 9. RÉSUMÉ DES APIS

| API | Gratuit | Clé requise | Usage |
|-----|---------|-------------|-------|
| OpenRouter | Oui | Oui (`.env.local`) | 3 modèles IA |
| Frankfurter | Oui | Non | Conversion devises |
| MyMemory | Oui | Non (email optionnel) | Traduction |
| Unsplash | Oui | Non (URLs directes) | Photos destinations |
| SheetJS | Oui | Non (CDN) | Export Excel |
| jsPDF | Oui | Non (CDN) | Export PDF |
| Web Speech API | Oui | Non (navigateur) | Lecture vocale |

---

## 10. POINTS FORTS POUR LA PRÉSENTATION

1. **Zéro nouvelle entité** pour la gamification — tout calculé depuis Budget + Depense
2. **IA réelle** (Llama 3.3 70B) avec contexte personnalisé, pas des réponses génériques
3. **Fallback automatique** entre 4 modèles IA — robustesse garantie
4. **Clé API sécurisée** côté serveur — bonne pratique de sécurité
5. **Export Excel stylisé** — fichier professionnel avec couleurs et formatage
6. **Accessibilité complète** — TTS, zoom, dark mode, traduction multilingue
7. **Jeu interactif** généré depuis les vraies données — pas de données fictives
8. **Interface responsive** — fonctionne sur mobile et desktop
