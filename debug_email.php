<?php

require __DIR__ . '/bootstrap/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\GmailService;

$gmail = new GmailService();
$gmail->authenticate();

$emails = $gmail->getEmailsFromSender('notificaciones@notificacionesbcp.com.pe', null);

echo "Total emails: " . count($emails) . "\n\n";

if (count($emails) > 0) {
    $firstEmail = $emails[0];
    $messageId = $firstEmail->getId();
    
    echo "Message ID: $messageId\n\n";
    
    $body = $gmail->getEmailBody($messageId);
    
    echo "=== RAW EMAIL (primeros 3000 chars) ===\n";
    echo substr($body, 0, 3000);
    echo "\n\n=== END ===\n";
}