# AlToque — Sincronización Yape y facturación SUNAT

Aplicación Laravel 12 que:

- Importa notificaciones de Yape/BCP desde Gmail y las convierte en transacciones.
- Mantiene historial, saldos, clientes y usuarios.
- Emite boletas electrónicas y notas de crédito a SUNAT (greenter/lite).
- Permite descargar XML/CDR de boletas y de notas de crédito desde la UI.

## Requisitos

- PHP 8.2 o superior
- Composer 2
- MySQL
- Node.js y npm (para compilar los assets)
- Credenciales OAuth de Gmail API
- Certificado digital SUNAT en formato `.pfx`

## Instalación

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
php artisan serve
```

También existe `composer setup`, que hace la instalación completa (incluye `migrate --force`). Revisa `.env` antes de usarlo: el ejemplo trae valores de producción.

## Configuración

### Base de datos

`DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.

### Gmail

- Coloca el OAuth client de Google en `credentials.json` en la raíz del proyecto.
- Configura `APP_URL` con la URL pública, porque el `redirect_uri` se deriva de ahí (`/gmail/callback`).
- Tras autorizar con `/gmail/redirect`, el token queda en `storage/app/gmail_token.json`.

Estos archivos están ignorados por Git: `.env`, `credentials.json`, `token.json`, `storage/app/gmail_token.json`.

### SUNAT

Variables principales en `.env`:

| Variable | Uso |
|---|---|
| `SUNAT_AMBIENTE` | `produccion` o `beta` |
| `SUNAT_SERIE` / `SUNAT_SERIE_NC` | Series de boletas y notas de crédito |
| `SUNAT_ULTIMO_CORRELATIVO` | Correlativo base (si aplica) |
| `SUNAT_CERT_FILE` / `SUNAT_CERT_PASSWORD` | Certificado `.pfx` en `storage/app/sunat/certificates/` |
| `SUNAT_RUC`, `SUNAT_RAZON_SOCIAL`, `SUNAT_DIRECCION`, `SUNAT_UBIGEO`, etc. | Datos del emisor |
| `SUNAT_USUARIO_SOL`, `SUNAT_CLAVE_SOL` | Credenciales SOL |
| `SUNAT_USUARIO_SOL_SEC`, `SUNAT_CLAVE_SOL_SEC` | Usuario secundario con permisos SEE |
| `SUNAT_COMISION_MAX` | Tope de comisión para boletas (default `10`) |

## Comandos útiles

| Comando | Descripción |
|---|---|
| `php artisan sync:yape [--force]` | Sincroniza notificaciones desde Gmail |
| `php artisan sunat:enviar-boletas [--id=] [--force] [--generico]` | Envía boletas pendientes vía Resumen Diario |
| `php artisan sunat:send-boleta {id} [--use-summary]` | Envía una boleta individual vía `sendBill` |
| `php artisan sunat:anular-nc [--serie=] [--numero=] [--monto=]` | Anula emitiendo una Nota de Crédito Electrónica |
| `php artisan sunat:anular-manual [--serie=] [--numero=] [--monto=] [--fecha=]` | Envía una anulación (RC) manual |
| `php artisan boleta:reconstruct-from-xml [--dry-run] [--force]` | Reconstruye transacciones desde XML/CDR existentes |
| `php artisan sunat:check-cert` | Verifica el certificado SUNAT |
| `php artisan sunat:test-connection` | Prueba conexión SOAP con SUNAT |

`routes/console.php` programa `sync:yape` cada 5 minutos y `sunat:enviar-boletas` cada hora. En producción hace falta el cron de Laravel:

```cron
* * * * * cd /ruta/altoque && php artisan schedule:run >> /dev/null 2>&1
```

## Flujo resumido

1. Gmail → `sync:yape` crea/actualiza transacciones.
2. La UI permite marcar boletas emitidas, ajustar comisión y datos del cliente.
3. `sunat:enviar-boletas` envía el resumen y guarda ticket/CDR.
4. Si se anula, la NC guarda sus propias rutas en `boleta_nc_xml_path` y `boleta_nc_cdr_path`, sin sobrescribir el XML de la boleta original.

## Pruebas

```bash
php artisan test
```

La suite usa SQLite en memoria (`phpunit.xml`), así que no afecta la base de datos de desarrollo.

## Despliegue

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
npm run build
```

Tras desplegar, revisa `storage/app/last_yape_sync.txt`: si no existe, la primera sincronización sin `--force` toma como fecha el día anterior.

### Despliegue Docker en el VPS

El runbook completo, con los comandos exactos para el servidor, está en [`DEPLOY.md`](DEPLOY.md).

El proyecto se monta en `/var/www/AlToque` dentro del contenedor `altoque_app` (red `jemigra_jemigra_net`). Archivos incluidos para ese entorno:

- `docker/Dockerfile`: imagen derivada de `bar_php` con la extensión SOAP que exige greenter/SUNAT.
- `docker/nginx/altoque.conf`: vhost HTTP/HTTPS para `altoque.jemigra.com`, con `fastcgi_pass altoque_app:9000`.

En el servidor, dentro del contenedor:

```bash
composer install --no-dev --no-interaction
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

`APP_URL` debe ser exactamente `https://altoque.jemigra.com`, porque el `redirect_uri` de Gmail se deriva de ahí. La sincronización y el envío a SUNAT los ejecutan los sidecars `altoque_scheduler` y `altoque_queue` (o el cron/Supervisor equivalente).
