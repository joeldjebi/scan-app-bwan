# API Pass Parking — v1 (résumé)

> **Référence complète (Swagger)** : `https://<domaine>/docs/api`. Fichier source : [openapi.yaml](openapi.yaml), importable dans Postman, Insomnia ou openapi-generator (Flutter, Kotlin, Swift).

- **Base URL** : `https://<domaine>/api/v1`
- **En-têtes** : `Accept: application/json` sur toutes les requêtes, et `Authorization: Bearer <token>` sur toutes sauf le login.

## Ce que fait l'application

| | Agent | Chef agent parking |
|---|---|---|
| Scanner, vérifier, valider l'entrée ou la sortie | ✅ | ✅ |
| Forcer un pass non valide | ❌ | ✅ (son événement) |
| Voir son historique de passages | ✅ | ✅ |
| Statistiques et historique complet de l'événement | ❌ | ✅ (son événement) |

**L'agent ne choisit jamais d'événement.** Le serveur retrouve l'événement à partir du pass scanné et vérifie que l'agent y est affecté.

## Endpoints

| Méthode | Route | Qui | Rôle |
|---|---|---|---|
| POST | `/auth/login` | tous | `{phone, password}` (+ `device_name` facultatif) → `{token, user}` (connexion par **numéro de téléphone**) |
| GET | `/auth/me` | tous | Vérifie que le token est valide |
| POST | `/auth/logout` | tous | Révoque le token |
| POST | `/verify` | tous | `{code}` **ou** `{plate}` → `{valid, reason, message, method, can_force, event, pass, matches}`. N'enregistre rien. |
| POST | `/scans` | tous | `{code}` ou `{plate}` → valide le passage (sens automatique). `422` si le pass n'est pas valide. Anti-doublon automatique (même pass, même agent, moins de 10 s). |
| GET | `/scans/history` | tous | Mes passages, 30 par page |
| GET | `/events` | chef | Ses événements, avec `can_supervise` |
| GET | `/events/{id}/stats` | chef | Compteurs de l'événement |
| GET | `/events/{id}/scans` | chef | Tous les passages de l'événement, 50 par page |
| GET | `/sync?since=` | tous | Hors ligne : pass de tous mes événements, plus `deleted_pass_ids` (pass supprimés à retirer du cache) |
| POST | `/scans/batch` | tous | Hors ligne : envoi des passages en attente |

## Parcours

1. Scan → `POST /verify { code: "<contenu brut du QR>" }`
2. Si `valid = true` : afficher le type (couleur), l'**immatriculation**, la marque, la couleur et le sens (`next_direction`), puis le bouton **Valider** → `POST /scans { code, latitude, longitude, accuracy }`. La position GPS de l'agent est enregistrée à chaque passage (facultative si indisponible).
**QR code illisible ou oublié** : l'agent saisit l'immatriculation et l'app envoie `plate` à la place de `code`, sur les mêmes routes. Le format est libre (`1234 AB 01` = `1234-ab-01` = `1234ab01`), et le passage est marqué « saisie manuelle » dans les historiques.

3. Si `valid = false` : afficher `message`. Si `can_force = true` (chef) : bouton **Forcer** → `POST /scans { code, force: true }`.

## Motifs de refus

| reason | Signification | Forçable par le chef |
|---|---|---|
| `unknown_pass` | QR code inconnu | non |
| `not_assigned` | Pass d'un événement auquel l'agent n'est pas affecté | non |
| `event_closed` | Événement clôturé | non |
| `not_registered` | Aucun véhicule enregistré | oui |
| `revoked` | Pass révoqué | oui |
| `unknown_plate` | Immatriculation saisie inconnue sur mes événements | non |
| `multiple_matches` | Immatriculation présente sur plusieurs de mes événements : choisir dans `matches`, puis renvoyer avec `code = matches[i].pass.token` | non |
