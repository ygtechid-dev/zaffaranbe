<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class SwaggerController extends Controller
{
    public function docs()
    {
        $html = file_get_contents(base_path('public/api-docs.html'));
        return new Response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8'
        ]);
    }

    public function spec()
    {
        $yaml = file_get_contents(base_path('public/openapi.yaml'));
        return new Response($yaml, 200, [
            'Content-Type' => 'text/yaml; charset=UTF-8',
            'Content-Disposition' => 'inline'
        ]);
    }
}
