<?php

namespace App\Console\Commands;

use App\Services\GmailService;
use App\Services\YapeNotificationParser;
use App\Models\Transaccion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncYapeNotifications extends Command
{
    protected $signature = 'sync:yape {--force : Forzar sincronización completa}';
    protected $description = 'Sincroniza notificaciones de Yape desde Gmail';

    private GmailService $gmailService;
    private YapeNotificationParser $parser;

    public function __construct()
    {
        parent::__construct();
        $this->gmailService = new GmailService();
        $this->parser = new YapeNotificationParser();
    }

    public function handle(): int
    {
        $this->info('Iniciando sincronización de notificaciones de Yape...');

        if (!$this->gmailService->isAuthenticated()) {
            $this->error('No hay token de autorización. Ejecuta la autorización desde el dashboard.');
            return Command::FAILURE;
        }

        try {
            $this->gmailService->authenticate();
            $this->info('Conectado a Gmail correctamente.');

            $lastSync = $this->getLastSyncDate();
            $afterDate = $this->option('force') ? null : $lastSync;

            $emails = $this->gmailService->getEmailsFromSender(
                $this->parser->getSenderEmail(),
                $afterDate
            );

            $this->info("Encontrados " . count($emails) . " emails de Yape/BCP.");

            $nuevos = 0;
            $duplicados = 0;
            $errores = 0;
            $noYape = 0;

            $contador = 0;
            foreach ($emails as $email) {
                $contador++;
                if ($contador > 50) {
                    $this->info("Procesados $contador emails, encontrado $nuevos nuevos...");
                }
                
                try {
                    $messageId = $email->getId();

                    if (Transaccion::where('email_message_id', $messageId)->exists()) {
                        $duplicados++;
                        continue;
                    }

                    $body = $this->gmailService->getEmailBody($messageId);
                    
                    if (!$body) {
                        $errores++;
                        continue;
                    }

                    if (!$this->parser->isYapeNotification($body)) {
                        $noYape++;
                        continue;
                    }

                    $data = $this->parser->parseEmail($body);

                    if (!$data) {
                        $errores++;
                        continue;
                    }

                    if ($data['numero_operacion'] && $this->parser->existsOperation($data['numero_operacion'])) {
                        Log::info("Operation {$data['numero_operacion']} already exists in DB, skipping");
                        $duplicados++;
                        continue;
                    }

                    Log::info("Saving transaction for operation: {$data['numero_operacion']}");

                    $data['email_message_id'] = $messageId;
                    Transaccion::registrarDesdeEmail($data);
                    
                    Log::info("Transaction saved successfully");
                    
                    $this->gmailService->markAsRead($messageId);
                    $nuevos++;

                    $this->line("  ✓ Op: {$data['numero_operacion']} - S/{$data['monto']} - {$data['remitente_nombre']}");

                } catch (\Exception $e) {
                    Log::error("Error procesando email " . ($email->getId() ?? 'unknown') . ": " . $e->getMessage());
                    $errores++;
                }
            }

            $this->saveLastSyncDate(now()->format('Y/m/d'));

            $this->info("");
            $this->info("=== RESUMEN ===");
            $this->info("Nuevos registros: $nuevos");
            $this->info("Duplicados omitidos: $duplicados");
            $this->info("No eran Yape: $noYape");
            $this->info("Errores: $errores");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Error durante la sincronización: " . $e->getMessage());
            Log::error("SyncYapeNotifications Error: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function getLastSyncDate(): ?string
    {
        $file = storage_path('app/last_yape_sync.txt');
        if (file_exists($file)) {
            return trim(file_get_contents($file));
        }
        return null;
    }

    private function saveLastSyncDate($date): void
    {
        $file = storage_path('app/last_yape_sync.txt');
        file_put_contents($file, $date);
    }
}