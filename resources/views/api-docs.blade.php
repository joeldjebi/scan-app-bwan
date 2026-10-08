<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>API · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.17.14/swagger-ui.css">
    <style>
        body { margin: 0; background: #fff; }
        .topbar-pp { display: flex; align-items: center; justify-content: space-between; padding: 12px 20px; background: #1e1b4b; color: #fff; font-family: system-ui, sans-serif; }
        .topbar-pp a { color: #c7d2fe; font-size: 14px; }
    </style>
</head>
<body>
    <div class="topbar-pp">
        <strong>{{ config('app.name') }} — API mobile v1</strong>
        <a href="{{ route('api-docs.spec', absolute: false) }}" download="openapi.yaml">Télécharger openapi.yaml</a>
    </div>
    <div id="swagger-ui"></div>

    <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.17.14/swagger-ui-bundle.js"></script>
    <script>
        window.ui = SwaggerUIBundle({
            url: @js(route('api-docs.spec', absolute: false)),
            dom_id: '#swagger-ui',
            deepLinking: true,
            persistAuthorization: true,
            displayRequestDuration: true,
            docExpansion: 'list',
            defaultModelsExpandDepth: 1,
            tryItOutEnabled: false,
            filter: true,
            requestInterceptor: (request) => {
                request.headers['Accept'] = 'application/json';
                return request;
            },
        });
    </script>
</body>
</html>
