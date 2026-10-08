<?php

return [

    /*
    | Longueur du jeton aléatoire encodé dans le QR code. 12 caractères
    | alphanumériques (~71 bits) : impossible à deviner, QR code peu dense.
    */
    'token_length' => 12,

    // Anti-doublon : une même validation (même agent, même pass) reçue de nouveau dans ce
    // délai (secondes) renvoie le passage déjà enregistré au lieu d'en créer un second.
    'scan_dedup_seconds' => 10,

    // Nombre maximum de pass générés en une seule opération.
    'max_generation' => 5000,

    // Documentation Swagger publique sur /docs/api.
    'api_docs' => env('API_DOCS_ENABLED', true),

    // Couleurs proposées sous forme de pastilles (nom => teinte affichée).
    'colors' => [
        'Blanc' => '#f8fafc', 'Noir' => '#111827', 'Gris' => '#6b7280', 'Argent' => '#c0c6cf',
        'Bleu' => '#1d4ed8', 'Rouge' => '#dc2626', 'Vert' => '#15803d', 'Beige' => '#e7d8b9',
        'Marron' => '#78471f', 'Jaune' => '#facc15', 'Orange' => '#f97316', 'Bordeaux' => '#7f1d1d',
        'Violet' => '#7c3aed', 'Doré' => '#c9a227',
    ],

    // Taille maximale (Ko) du logo et de l'affiche d'un événement.
    'image_max_kb' => 5120,

];
