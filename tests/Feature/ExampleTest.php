<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_la_raiz_redirige_al_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_el_dashboard_requiere_autenticacion(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }
}
