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
    protected $description = 'Sincroniza notificaciones de Yape/BCP desde Gmail';

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
        $this->info('Iniciando sincronización de notificaciones BCP...');

        if (!$this->gmailService->isAuthenticated()) {
            $this->error('No hay token de autorización. Ejecuta la autorización desde el dashboard.');
            return Command::FAILURE;
        }

        try {
            $this->gmailService->authenticate();
            $this->info('Conectado a Gmail correctamente.');

            $lastSync = $this->getLastSyncDate();
            $forceMode = $this->option('force');

            // Primero obtener los emails (esto verifica que la API funciona)
            $emails = $this->gmailService->getEmailsFromSenders(
                $this->parser->getSenderEmails(),
                $forceMode ? null : $lastSync
            );

            // Solo después de confirmar la conexión, eliminar registros en modo force
            if ($forceMode) {
                $this->info("Modo force: eliminando registros sincronizados anteriores...");
                Transaccion::where('synced_from_email', true)->delete();
                $this->saveLastSyncDate('');
                $this->info("Registros eliminados. Re-sincronizando desde cero.");
            }

            $this->info("Encontrados " . count($emails) . " emails de BCP.");

            $nuevos = 0;
            $duplicados = 0;
            $errores = 0;
            $sinClasificar = 0;

            $contador = 0;
            foreach ($emails as $email) {
                $contador++;
                
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

                    $data = $this->parser->parseEmail($body);

                    if (!$data) {
                        $sinClasificar++;
                        continue;
                    }

                    if ($data['numero_operacion'] && $this->parser->existsOperation($data['numero_operacion'])) {
                        $duplicados++;
                        continue;
                    }

                    $data['email_message_id'] = $messageId;
                    $tx = Transaccion::registrarDesdeEmail($data);
                    $tx->asignarClienteYComision();
                    
                    $this->gmailService->markAsRead($messageId);
                    $nuevos++;

                    $tipoLabel = $data['tipo_movimiento'] ?? '?';
                    $nombre = $data['remitente_nombre'] ?? '-';
                    $this->line("  ✓ [{$tipoLabel}] Op: {$data['numero_operacion']} - S/{$data['monto']} - {$nombre}");

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
            $this->info("No clasificados: $sinClasificar");
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
