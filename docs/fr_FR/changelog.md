# Changelog Jeedilkamin

>**IMPORTANT**
>
>S'il n'y a pas d'information sur la mise à jour, c'est que celle-ci concerne uniquement de la mise à jour de documentation, de traduction ou de texte.

# 02/04/2026 — v0.2

### Nouvelles fonctionnalités
- Création automatique des commandes à partir du JSON `device_info` retourné par l'API Edilkamin (plus de commandes hardcodées)
- Rafraîchissement adaptatif : 30s en phase transitoire (allumage/extinction), 2 min allumé stable, 5 min éteint
- Rafraîchissement autonome dans le démon, indépendant du cron Jeedom
- Chargement des équipements connus au démarrage du démon (`load_known_devices`)
- Modal "Historique des alarmes" : toutes les alarmes groupées par année avec codes traduits en français
- Modal "Compteurs" : heures de fonctionnement par niveau P1→P5 (total et depuis dernier entretien) avec barre de progression
- Nouvelles commandes info : température fumées, température carte, pression air, entretien requis, nettoyage en cours, dernier rafraîchissement
- Commandes action liées à leur info d'état (Power ON/OFF → Etat, Auto ON/OFF → Mode AUTO, Relax ON/OFF → Mode Relax)
- Bornes des sliders fans déduites automatiquement du JSON (`fan_X_max_level + 1`, `fan_X_engine_type`)
- Bornes de la puissance utilisateur déduites dynamiquement du nombre de niveaux P dans le JSON
- Consigne température limitée à 16-22°C (recommandations GIEC)
- 17 phases du poêle détaillées avec sous-phases d'allumage (nettoyage, préchauffage, chargement, attente flamme, contrôle fumées, stabilisation, warmup)
- 17 codes d'alarme traduits en français
- Historique des alarmes et compteurs stockés en configuration de l'équipement
- Chiffrement du mot de passe en base (`$_encryptConfigKey`)
- Timeout de 30 min sur `_wait_for_state` (power on/off)
- Handler `updateJeedilkaminData` pour le cron Jeedom

### Corrections de bugs
- Doublon de la commande `state` dans `postSave()`
- Faute de casse `updatejeedilkaminData` → `updateJeedilkaminData` (cron inactif)
- Condition de timeout du démon corrigée (`>= 30` → `>= 20`)
- `postRemove()` : suppression du cron orphelin à la suppression d'un équipement
- Code mort après `return` dans `jeeJeedilkamin.php`
- Clé de configuration `refreshCron` → `autorefresh` (le cron utilisateur était ignoré)
- Mot de passe supprimé des logs du démon
- `pyserial` et `pyudev` réintégrés dans `packages.json` (requis par `jeedom.py`)
- Boucle infinie `postSave` → `save(true)` pour éviter de re-déclencher le démon
- Synchronisation du timer autonome après refresh via socket
- Intervalle de rafraîchissement qui restait à 30s après extinction

### Améliorations techniques
- `read_socket()` refactorisé avec pattern dispatch (dictionnaire de handlers)
- `refresh()` refactorisé avec dict littéral et `_PHASE_MAP` lookup
- `_ensure_valid_token_async()` : vérification JWT locale sans appel réseau, renouvellement anticipé 60s avant expiration
- Cache JWKS pour éviter les appels réseau répétés
- `edilkamin.sign_in()` appelé de façon synchrone (n'est pas une coroutine)
- `inspect.isawaitable()` pour gérer les fonctions edilkamin sync/async
- Réorganisation des commandes par blocs logiques pour le dashboard
- Commandes intercalées (info + action) pour un affichage cohérent

# 25/12/2025 — v0.1

- Init Jeedilkamin
