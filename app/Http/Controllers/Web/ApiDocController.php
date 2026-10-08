<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Documentation Swagger de l'API mobile (docs/openapi.yaml).
 */
class ApiDocController extends Controller
{
    public function index(): View
    {
        return view('api-docs');
    }

    public function spec(): Response
    {
        return response(file_get_contents(base_path('docs/openapi.yaml')), 200, [
            'Content-Type' => 'application/yaml; charset=utf-8',
        ]);
    }
}
