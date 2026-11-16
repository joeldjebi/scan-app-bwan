<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Documentation Swagger de l'API mobile (docs/openapi.yaml) et de l'API partenaires (docs/openapi-partner.yaml).
 */
class ApiDocController extends Controller
{
    public function index(): View
    {
        return view('api-docs', ['title' => 'API mobile v1', 'specUrl' => route('api-docs.spec', absolute: false)]);
    }

    public function spec(): Response
    {
        return $this->yaml('docs/openapi.yaml');
    }

    public function partner(): View
    {
        return view('api-docs', ['title' => 'API partenaires v1', 'specUrl' => route('api-docs.partner.spec', absolute: false)]);
    }

    public function partnerSpec(): Response
    {
        return $this->yaml('docs/openapi-partner.yaml');
    }

    private function yaml(string $path): Response
    {
        return response(file_get_contents(base_path($path)), 200, [
            'Content-Type' => 'application/yaml; charset=utf-8',
        ]);
    }
}
