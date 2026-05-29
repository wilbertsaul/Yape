<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GmailAuthController extends Controller
{
    private string $credentialsPath;

    public function __construct()
    {
        $this->credentialsPath = base_path('credentials.json');
    }

    public function redirect()
    {
        $client = new \Google\Client();
        $client->setApplicationName('Yape Auto Sync');
        $client->setScopes([
            \Google\Service\Gmail::GMAIL_READONLY,
            \Google\Service\Gmail::GMAIL_MODIFY,
        ]);
        $client->setAuthConfig($this->credentialsPath);
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setRedirectUri(config('app.url') . '/gmail/callback');

        $authUrl = $client->createAuthUrl();
        
        return redirect($authUrl);
    }

    public function callback(Request $request)
    {
        $code = $request->get('code');
        
        if (!$code) {
            return redirect('/dashboard')->with('error', 'Código de autorización no recibido');
        }

        try {
            $client = new \Google\Client();
            $client->setApplicationName('Yape Auto Sync');
            $client->setScopes([
                \Google\Service\Gmail::GMAIL_READONLY,
                \Google\Service\Gmail::GMAIL_MODIFY,
            ]);
            $client->setAuthConfig($this->credentialsPath);
            $client->setAccessType('offline');
            $client->setPrompt('consent');
            $client->setRedirectUri(config('app.url') . '/gmail/callback');

            $accessToken = $client->fetchAccessTokenWithAuthCode($code);
            
            // Guardar token
            $tokenPath = storage_path('app/gmail_token.json');
            file_put_contents($tokenPath, json_encode($accessToken));

            return redirect('/dashboard')->with('success', 'Autorización exitosa. Las transacciones se sincronizarán automáticamente.');
            
        } catch (\Exception $e) {
            Log::error('Gmail Auth Error: ' . $e->getMessage());
            return redirect('/dashboard')->with('error', 'Error en autorización: ' . $e->getMessage());
        }
    }
}