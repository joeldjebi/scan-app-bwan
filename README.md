# Pass Parking

Plateforme de pass parking événementiel (Laravel 13, MySQL).

- **Admin** : crée les événements et les types de pass (VIP, Staff…), génère les QR codes, exporte les QR codes pour l'impression (ZIP SVG/PNG + `passes.csv` pour le gabarit) et la liste des pass (Excel/CSV), gère les comptes, nomme un chef agent et des agents par événement.
- **Usager** : scanne son QR code et enregistre son véhicule (immatriculation, marque, couleur, téléphone) sur `/p/{token}`.
- **Agents / chef** : scannent ce même QR code avec l'application mobile (Swagger sur `/docs/api`, source [docs/openapi.yaml](docs/openapi.yaml), résumé [docs/API.md](docs/API.md)) pour vérifier le véhicule et enregistrer les entrées/sorties. Le chef supervise aussi depuis le back-office.

## Installation

```bash
composer install
cp .env.example .env && php artisan key:generate
# Renseigner DB_* et APP_URL (domaine public : il est encodé dans les QR codes !)
php artisan migrate --seed      # inclut les marques de véhicules (BrandSeeder)
php artisan storage:link        # logos et affiches des événements
php artisan serve
php artisan queue:work          # exports de QR codes en arrière-plan (dans un 2e terminal)
```

Le seeder crée `admin@passparking.test` / `password`, plus des données de démo en local (chef `chef@passparking.test`, agents `awa|moussa|fatou@passparking.test`, mot de passe `password`).

> ⚠️ Définir le bon `APP_URL` **avant** d'exporter et d'imprimer les QR codes.

## Tests

```bash
php artisan test
```

`OpenApiContractTest` valide les réponses réelles de l'API contre `docs/openapi.yaml` : toute modification de l'API doit être reportée dans le Swagger, sinon le test échoue. La page `/docs/api` se désactive avec `API_DOCS_ENABLED=false`.

## Interface

Tailwind et Alpine.js sont chargés par CDN (Node 18 installé, alors que Vite 8 demande Node ≥ 20.19). Pour passer à un build Vite : mettre à jour Node, puis `npm install && npm run build` et remplacer les balises CDN dans `resources/views/partials/head.blade.php` par `@vite`.
