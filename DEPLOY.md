# Runbook de despliegue — AlToque / Yape

Objetivo: dejar `https://altoque.jemigra.com` funcionando sobre el VPS existente y luego ejecutar el smoke test. Todo se ejecuta **en el servidor** (MobaXterm) salvo la subida de archivos.

## Datos del entorno

| Item | Valor |
|---|---|
| Código | `/var/www/AlToque` (bind mount) |
| PHP-FPM | `altoque_app`, red `jemigra_jemigra_net` |
| Nginx | `jemigra_web` (gestionado por `/var/www/jemigra/docker-compose.yml`) |
| MySQL | `jemigra_db`, base de datos `Yape` (mayúscula) |
| Usuario app MySQL | `root` |
| URL | `https://altoque.jemigra.com` |
| Correo Let's Encrypt | `wilbertsaul@gmail.com` |
| Admin | `admin@yape.com` / `admin123` |
| Importar | dump completo local (13 tablas, migración NC incluida) |
| No ejecutar | `php artisan db:seed` |

Artefactos a subir desde la PC al servidor, en `/tmp/altoque-deploy/`:

- `yape-deploy-lite.zip` (~235 KB, no incluye `.env`, tokens ni `vendor/`)
- `yape_dump.sql` (~2.2 MB)
- Opcional pero necesario para integraciones: `credentials.json`, `storage/app/gmail_token.json`, certificado `.pfx` de SUNAT

---

## 0. Backup obligatorio

```bash
mkdir -p /root/backups/altoque-$(date +%F-%H%M)
BK=/root/backups/altoque-$(date +%F-%H%M)
cp /var/www/AlToque/.env "$BK/.env"
cp /var/www/jemigra/docker-compose.yml "$BK/jemigra-docker-compose.yml"
docker inspect altoque_app > "$BK/altoque_app.inspect.json"
docker ps -a > "$BK/containers.txt"
```

Si la base `Yape` ya existe, volcarla antes de sobrescribir:

```bash
read -s -p 'Contraseña root de MySQL: ' DBPASS; echo
docker exec -i -e MYSQL_PWD="$DBPASS" jemigra_db mysqldump --single-transaction Yape > "$BK/Yape-before.sql"
unset DBPASS
```

> Si el volcado falla diciendo que la base no existe, es normal: se crea en el paso 6.

## 1. Subir y verificar artefactos

```bash
mkdir -p /tmp/altoque-deploy
# Subir los archivos con WinSCP/MobaXterm a /tmp/altoque-deploy/
ls -lh /tmp/altoque-deploy
unzip -l /tmp/altoque-deploy/yape-deploy-lite.zip | grep -E '(^|/)\.env$|credentials\.json|gmail_token\.json|vendor/autoload\.php' || echo 'Paquete limpio'
```

## 2. Código

```bash
command -v unzip >/dev/null || { apt-get update && apt-get install -y unzip; }
cd /var/www/AlToque
git remote -v
git fetch origin
git reset --hard origin/main
unzip -o /tmp/altoque-deploy/yape-deploy-lite.zip -d /var/www/AlToque \
  -x 'storage/*' '.env' 'credentials.json' 'token.json'
git status --short --branch
```

`git reset --hard` descarta los cambios locales del código antiguo (por eso existe el backup). `.env` no se toca: no está versionado ni dentro del zip.

## 3. Imagen PHP con SOAP

`bar_php` no tiene SOAP y greenter/SUNAT lo exige:

```bash
docker build -t altoque_php -f /var/www/AlToque/docker/Dockerfile /var/www/AlToque/docker
docker run --rm altoque_php php -m | grep -i '^soap$'
```

Debe imprimir `soap`. Si `docker build` falla por falta de `libxml2-dev`, revisar que el contenedor tenga salida a Internet.

## 4. Recrear `altoque_app`

El contenedor fue creado con `docker run` manual; no está en ningún compose. Se recrea con el mismo mount, red y restart:

```bash
docker stop altoque_app && docker rm altoque_app
docker run -d --name altoque_app \
  --restart unless-stopped \
  --network jemigra_jemigra_net \
  -v /var/www/AlToque:/var/www/AlToque \
  -w /var/www/AlToque \
  altoque_php php-fpm
docker exec altoque_app php -m | grep -i '^soap$'
```

> Si `$BK/altoque_app.inspect.json` mostrara variables de entorno de la app además de las de PHP, añádelas con `-e CLAVE=valor`. En el contenedor actual solo aparecen variables base de PHP; la configuración de Laravel se lee desde `.env`.

## 5. Composer y variables de entorno

Instalar Composer **después** de recrear el contenedor (lo installed en el filesystem del contenedor no sobrevive a `docker rm`):

```bash
docker exec altoque_app sh -c 'php -r "copy(\"https://getcomposer.org/installer\", \"/tmp/composer-setup.php\");" && php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer && rm -f /tmp/composer-setup.php'
docker exec altoque_app composer --version
docker exec -w /var/www/AlToque altoque_app composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
```

Ajustar `.env` en el host:

```bash
cd /var/www/AlToque
cp .env .env.backup.$(date +%Y%m%d-%H%M%S)
sed -i 's|^APP_URL=.*|APP_URL=https://altoque.jemigra.com|' .env
sed -i 's|^APP_LOCALE=.*|APP_LOCALE=es|' .env
sed -i 's|^APP_ENV=.*|APP_ENV=production|' .env
sed -i 's|^APP_DEBUG=.*|APP_DEBUG=false|' .env
sed -i 's|^DB_HOST=.*|DB_HOST=jemigra_db|' .env
sed -i 's|^DB_DATABASE=.*|DB_DATABASE=Yape|' .env
sed -i 's|^DB_USERNAME=.*|DB_USERNAME=root|' .env
grep -q '^DB_USERNAME=' .env || printf 'DB_USERNAME=root\n' >> .env
grep -E '^(APP_URL|APP_ENV|APP_DEBUG|APP_LOCALE|DB_HOST|DB_DATABASE|DB_USERNAME)=' .env
```

`DB_PASSWORD` debe coincidir con la contraseña `root` de MySQL. Si cambió, edítalo con `nano .env` (sin pegarlo en el chat). `APP_URL` debe ser exactamente `https://altoque.jemigra.com`: el `redirect_uri` de Gmail se deriva de ahí.

## 6. Base de datos

```bash
read -s -p 'Contraseña root de MySQL: ' DBPASS; echo
docker exec -i -e MYSQL_PWD="$DBPASS" jemigra_db mysql -uroot \
  -e "CREATE DATABASE IF NOT EXISTS \`Yape\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
docker exec -i -e MYSQL_PWD="$DBPASS" jemigra_db mysql -uroot Yape < /tmp/altoque-deploy/yape_dump.sql
unset DBPASS

docker exec -w /var/www/AlToque altoque_app php artisan migrate:status
docker exec -w /var/www/AlToque altoque_app php artisan migrate --force
```

`migrate:status` debe mostrar todas las migraciones como *Ran* (incluida `2026_07_31_100000_add_nc_paths_to_transacciones`). **No ejecutar `db:seed`.** El usuario `admin@yape.com` ya viene en el dump con contraseña `admin123`.

Caches y enlace de storage:

```bash
docker exec -w /var/www/AlToque altoque_app php artisan config:clear
docker exec -w /var/www/AlToque altoque_app php artisan route:clear
docker exec -w /var/www/AlToque altoque_app php artisan view:clear
docker exec -w /var/www/AlToque altoque_app php artisan config:cache
docker exec -w /var/www/AlToque altoque_app php artisan route:cache
docker exec -w /var/www/AlToque altoque_app php artisan view:cache
docker exec -w /var/www/AlToque altoque_app php artisan storage:link
```

## 7. Nginx y certificado HTTPS

Añadir los tres mounts al servicio `web` del compose de `jemigra`:

```bash
cd /var/www/jemigra
cp docker-compose.yml docker-compose.yml.bak.$(date +%Y%m%d-%H%M%S)
python3 - <<'PY'
from pathlib import Path
p = Path('/var/www/jemigra/docker-compose.yml')
s = p.read_text()
anchor = '      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf\n'
extra = (
    '      - /var/www/AlToque:/var/www/AlToque\n'
    '      - /var/www/AlToque/docker/nginx/altoque.conf:/etc/nginx/conf.d/altoque.conf\n'
    '      - /var/www/certbot:/var/www/certbot\n'
)
if '/var/www/AlToque:/var/www/AlToque' not in s:
    s = s.replace(anchor, anchor + extra, 1)
    p.write_text(s)
PY
mkdir -p /var/www/certbot
docker compose up -d --force-recreate web
```

El vhost definitivo referencia un certificado que aún no existe, así que `nginx -t` fallaría. Se usa un vhost HTTP temporal solo para el challenge ACME:

```bash
cd /var/www/AlToque/docker/nginx
cp altoque.conf altoque.conf.https
cat > altoque.conf <<'EOF'
server {
    listen 80;
    server_name altoque.jemigra.com;

    location /.well-known/acme-challenge/ { root /var/www/certbot; }
    location / { return 301 https://$host$request_uri; }
}
EOF
docker exec jemigra_web nginx -t && docker exec jemigra_web nginx -s reload
```

Emitir el certificado:

```bash
docker run --rm \
  -v /etc/letsencrypt:/etc/letsencrypt \
  -v /var/www/certbot:/var/www/certbot \
  certbot/certbot certonly --webroot -w /var/www/certbot \
  -d altoque.jemigra.com \
  --email wilbertsaul@gmail.com --agree-tos --no-eff-email
```

Restaurar el vhost HTTPS y validar:

```bash
cp /var/www/AlToque/docker/nginx/altoque.conf.https /var/www/AlToque/docker/nginx/altoque.conf
docker exec jemigra_web nginx -t && docker exec jemigra_web nginx -s reload
curl -I https://altoque.jemigra.com
curl -sS -o /dev/null -w 'login=%{http_code}\n' https://altoque.jemigra.com/login
```

## 8. Scheduler y cola

```bash
cd /var/www/AlToque
docker compose up -d --build scheduler queue
docker ps --filter name=altoque_
docker logs --tail=30 altoque_scheduler
docker logs --tail=30 altoque_queue
```

Ejecutan `sync:yape` cada 5 minutos y `sunat:enviar-boletas` cada hora, más `queue:work`. La red externa `jemigra_jemigra_net` ya existe.

## 9. Integraciones

**Gmail** (el código ya usa `route('gmail.callback')`, derivado de `APP_URL`):

1. Subir `credentials.json` a `/var/www/AlToque/credentials.json`.
2. Si hay token previo, subirlo a `/var/www/AlToque/storage/app/gmail_token.json`.
3. En Google Cloud Console, añadir `https://altoque.jemigra.com/gmail/callback` a *URIs de redireccionamiento autorizadas*.
4. Abrir `https://altoque.jemigra.com/gmail/redirect` y completar el consentimiento.
5. Verificar: `docker exec -w /var/www/AlToque altoque_app php artisan route:list --path=gmail`.

**SUNAT**:

```bash
# Subir el .pfx a /var/www/AlToque/storage/app/sunat/certificates/
docker exec -w /var/www/AlToque altoque_app php artisan sunat:check-cert
docker exec -w /var/www/AlToque altoque_app php artisan sunat:test-connection
```

Empezar con `SUNAT_AMBIENTE=beta` en `.env` y cambiar a `produccion` solo después de validar una boleta real. Recordar regenerar caches con `config:cache` tras cambiar `.env`.

## 10. Smoke test

- [ ] `curl -I https://altoque.jemigra.com` → `200`/`302`, certificado válido
- [ ] Login `admin@yape.com` / `admin123` → dashboard
- [ ] Boletas pendientes, emitidas, historial, clientes, saldos y usuarios cargan sin error 500
- [ ] Emitir una boleta de prueba en beta → XML y CDR descargables
- [ ] Anular con nota de crédito y verificar estado
- [ ] `docker logs altoque_scheduler` y `altoque_queue` sin excepciones
- [ ] Probar en celular (360/390 px): menú, tablas y botones táctiles

Revisar logs si algo falla:

```bash
docker exec altoque_app tail -n 80 /var/www/AlToque/storage/logs/laravel.log
docker logs --tail=80 jemigra_web
```

## Rollback

```bash
# 1. Restaurar .env y compose
cp "$BK/.env" /var/www/AlToque/.env
cp "$BK/jemigra-docker-compose.yml" /var/www/jemigra/docker-compose.yml
cd /var/www/jemigra && docker compose up -d --force-recreate web

# 2. Volver al contenedor original (valores leídos de $BK/altoque_app.inspect.json)
docker stop altoque_scheduler altoque_queue altoque_app 2>/dev/null
docker rm altoque_scheduler altoque_queue altoque_app 2>/dev/null
docker run -d --name altoque_app --restart unless-stopped \
  --network jemigra_jemigra_net -v /var/www/AlToque:/var/www/AlToque \
  -w /var/www/AlToque bar_php php-fpm

# 3. Restaurar BD si se sobrescribió
read -s -p 'Contraseña root de MySQL: ' DBPASS; echo
docker exec -i -e MYSQL_PWD="$DBPASS" jemigra_db mysql -uroot Yape < "$BK/Yape-before.sql"
unset DBPASS
```
