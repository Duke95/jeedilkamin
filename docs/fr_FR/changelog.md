# Changelog Jeedilkamin

>**IMPORTANT**
>
>S'il n'y a pas d'information sur la mise à jour, c'est que celle-ci concerne uniquement de la mise à jour de documentation, de traduction ou de texte.

# 28/03/2026 — v0.2

### Nouvelles fonctionnalités
- Création automatique des commandes à partir du JSON `device_info` retourné par l'API Edilkamin (plus de commandes hardcodées)
- Rafraîchissement adaptatif : 30s en phase transitoire (allumage/extinction), 2 min allumé stable, 5 min éteint
- Rafraîchissement autonome dans le démon, indépendant du cron Jeedom
- Modal "Historique des alarmes" : toutes les alarmes groupées par année avec codes traduits en français
- Modal "Compteurs" : heures de fonctionnement par niveau P1→P5 (total et depuis dernier entretien) avec barre de progression
- Nouvelles commandes info : température fumées, température carte, pression air, signal WiFi, entretien requis, nettoyage en cours, dernier rafraîchissement
- Commandes action liées à leur info d'état (Power ON/OFF → Etat, Auto ON/OFF → Mode AUTO, Relax ON/OFF → Mode Relax)
- Bornes des sliders fans déduites automatiquement du JSON (`fan_X_max_level`, `fan_X_engine_type`)
- Bornes de la puissance utilisateur déduites dynamiquement du nombre de niveaux P dans le JSON
- Consigne température limitée à 16-22°C (recommandations GIEC)
- Phases du poêle enrichies avec les sous-phases d'allumage et d'extinction
- Codes d'alarme traduits en français (21 codes)
- Historique des alarmes stocké en configuration de l'équipement
- Compteurs de fonctionnement stockés en configuration de l'équipement
- Hook git auto-commit avec message explicite

### Corrections de bugs
- Doublon de la commande `state` dans `postSave()`
- Faute de casse `updatejeedilkaminData` → `updateJeedilkaminData` (cron inactif)
- Condition de timeout du démon corrigée (`>= 30` → `>= 20`)
- `postRemove()` : suppression du cron orphelin à la suppression d'un équipement
- Code mort après `return` dans `jeeJeedilkamin.php`
- Clé de configuration `refreshCron` → `autorefresh` (le cron utilisateur était ignoré)
- Mot de passe supprimé des logs du démon
- `pyserial` et `pyudev` réintégrés dans `packages.json` (requis par `jeedom.py`)

### Améliorations techniques
- `read_socket()` refactorisé avec pattern dispatch (dictionnaire de handlers)
- `refresh()` refactorisé avec dict littéral et `_PHASE_MAP` lookup
- `_validJWT_async()` renommé `_ensure_valid_token_async()`, gestion du kid introuvable
- Constantes Cognito sorties au niveau module
- `edilkamin.sign_in()` appelé de façon synchrone (n'est pas une coroutine)
- `inspect.isawaitable()` pour gérer les fonctions edilkamin sync/async de façon transparente
- `aiohttp` utilisé pour les appels JWKS

# 25/12/2025 — v0.1

- Init Jeedilkamin
