# Runbook: actualización PHP + despliegue de AlToque / Yape

Objetivo: subir Altoque a **PHP 8.5.13** (parche de nov/2026) y dejar `https://altoque.jemigra.com` funcionando, **sin afectar los otros proyectos del VPS**.

## Reglas de seguridad (no negociables)

- **Nunca** ejecutar `docker compose down` ni `docker compose up` sin nombre de servicio dentro de `/var/www/jemigra`: eso detendría `jemigra_app`, la BD y el nginx compartido.
- `jemigra_db` es un **alias de red**, no el nombre del contenedor. El contenedor real es `c6600299f82a_jemigra_db` (comando Docker) mientras que `.env` debe seguir usando `DB_HOST=jemigra_db`.
- **Nunca** tocar `bar_php`, `jemigra_app`, `bar_app`, `jemigra_db` ni los volúmenes `dbdata`, `/var/www/bar`, `/var/www/dentamor`, `/var/www/aguilararce`, `/var/www/izipay`.
- **Nunca** cambiar contraseñas de MySQL ni ejecutar `docker system prune` / `docker image rm`.
- Todo cambio en `/var/www/jemigra/docker-compose.yml` se valida con `docker compose config` y `nginx -t` **antes** de recargar.
- Si un comando falla: **detenerse** y restaurar desde `$BK`. No seguir "a ver si funciona".
- `jemigra_web` es compartido: recargarlo interrumpe todos los vhosts por segundos. Hacerlo en ventana de bajo tráfico.

## Datos del entorno

| Item | Valor |
|---|---|
| Código | `/var/www/AlToque` (bind mount) |
| PHP-FPM | `altoque_app`, red `jemigra_jemigra_net` |
| PHP actual | 8.3.30 (`bar_php`) → **objetivo 8.5.13 (`altoque_php`)** |
| Nginx compartido | `jemigra_web`, compose `/var/www/jemigra/docker-compose.yml` |
| MySQL compartido | contenedor `c6600299f82a_jemigra_db` (alias de red `jemigra_db`), base nueva `Yape` (mayúscula) |
| Usuario app MySQL | `jemigra_user` (ya está en `.env`); se le conceden permisos **solo sobre `Yape.*`** |
| URL | `https://altoque.jemigra.com` |
| Correo Let's Encrypt | `wilbertsaul@gmail.com` |
| Admin | `admin@yape.com` / `admin123` |
| Importar | dump completo local (13 tablas, migración NC incluida); **reemplaza** la `Yape` actual (respaldo en `$BK/Yape-before.sql`) |
| No ejecutar | `php artisan db:seed` |

Artefactos a subir desde la PC a `/tmp/altoque-deploy/`:

- `yape-deploy-lite.zip` (~244 KB; sin `.env`, tokens ni `vendor/`)
- `yape_dump.sql` (~2.2 MB)
- Opcional pero necesario después: `credentials.json`, `storage/app/gmail_token.json`, certificado `.pfx` de SUNAT

## Alcance de esta entrega — fase 1

Ejecutar **solo los pasos 1 → 7, 9 y 7b**. **NO ejecutar el paso 8** (nginx, dominio, SSL) ni tocar `/var/www/jemigra/docker-compose.yml`: queda para la fase 2. La comprobación HTTP se hace temporalmente dentro de `altoque_app`, sin exponer puertos ni dominio.

---

## 0. Backups obligatorios — ANTES de cualquier cambio

Ejecutar **todo este bloque** y guardar la ruta `$BK`:

```bash
STAMP=$(date +%Y%m%d-%H%M%S)
BK=/root/backups/altoque-$STAMP
DBCTR=c6600299f82a_jemigra_db
mkdir -p "$BK"
chmod 700 "$BK"

# Estado actual (solo lectura)
docker ps -a                          > "$BK/docker-ps.txt"
docker image ls                       > "$BK/docker-images.txt"
docker network ls                     > "$BK/docker-networks.txt"
docker inspect altoque_app            > "$BK/altoque_app.inspect.json"
docker inspect jemigra_web            > "$BK/jemigra_web.inspect.json"
docker inspect "$DBCTR"               > "$BK/jemigra_db.inspect.json"

# Nginx compartido y certificados (afecta a TODOS los vhosts)
docker exec jemigra_web nginx -T      > "$BK/nginx-before.conf" 2>&1 || true
tar -czf "$BK/letsencrypt.tar.gz" -C /etc letsencrypt 2>/dev/null || true
[ -d /var/www/certbot ] && tar -czf "$BK/certbot.tar.gz" -C /var/www certbot || true

# Archivos que se van a editar
cp -a /var/www/jemigra/docker-compose.yml      "$BK/jemigra-docker-compose.yml"
cp -a /var/www/jemigra/docker/nginx/default.conf "$BK/jemigra-default.conf"

# Proyecto Altoque completo (incluye .env, tokens, SUNAT y vendor)
tar -czf "$BK/altoque-code.tar.gz" -C /var/www/AlToque \
  --exclude='./node_modules' \
  --exclude='./storage/logs' \
  --exclude='./storage/framework/cache' \
  --exclude='./storage/framework/sessions' \
  --exclude='./storage/framework/views' \
  .

# Bases de datos y usuarios (solo lectura)
read -s -p 'Contraseña root de MySQL: ' DBPASS; echo
docker exec -i -e MYSQL_PWD="$DBPASS" "$DBCTR" mysql -uroot -e 'SHOW DATABASES;' > "$BK/databases.txt"
docker exec -i -e MYSQL_PWD="$DBPASS" "$DBCTR" mysql -uroot \
  -e "SELECT user,host FROM mysql.user WHERE user IN ('root','jemigra_user');" > "$BK/mysql-users.txt"

# Esquema compartido (protección extra; NO se modifica en el despliegue)
docker exec -i -e MYSQL_PWD="$DBPASS" "$DBCTR" mysqldump --single-transaction jemigra_bd \
  > "$BK/jemigra_bd-before.sql" 2> "$BK/jemigra_bd.err" || echo 'No se pudo respaldar jemigra_bd'

# Base de datos Yape (se reemplazará en el paso 7)
docker exec -i -e MYSQL_PWD="$DBPASS" "$DBCTR" mysqldump --single-transaction Yape \
  > "$BK/Yape-before.sql" 2> "$BK/mysqldump.err" || echo 'Yape aún no existe; se creará en el paso 7'
unset DBPASS

# Imagen exacta del contenedor actual, para rollback
docker commit altoque_app altoque_php:8.3-backup

chmod 600 "$BK"/*
echo "BACKUP LISTO: $BK"
```

> `$BK` contiene secretos (`.env`, tokens, certificados). Permanece en `/root`, con permisos `600`.

## 1. Subir y verificar artefactos

En el servidor, crear la carpeta destino:

```bash
mkdir -p /tmp/altoque-deploy
```

Desde **PowerShell en Windows** (reemplaza `IP_DEL_VPS` por la IP de tu VPS):

```powershell
$hostIp = "IP_DEL_VPS"
$remote = "root@$hostIp"

ssh $remote "mkdir -p /tmp/altoque-deploy"
scp "C:\Users\ANA\AppData\Local\Temp\opencode\yape-deploy-lite.zip" "${remote}:/tmp/altoque-deploy/"
scp "C:\Users\ANA\AppData\Local\Temp\opencode\yape_dump.sql" "${remote}:/tmp/altoque-deploy/"

ssh $remote "ls -lh /tmp/altoque-deploy"
```

Si `scp` no está disponible, usa WinSCP/MobaXterm o `sftp $remote` con `put`. Luego, en el servidor:

```bash
ls -lh /tmp/altoque-deploy
unzip -tq /tmp/altoque-deploy/yape-deploy-lite.zip && echo ZIP_OK
unzip -l /tmp/altoque-deploy/yape-deploy-lite.zip \
  | grep -E '(^|/)\.env$|credentials\.json|gmail_token\.json|vendor/autoload\.php' \
  || echo 'Paquete limpio'
```

## 2. Código — solo `/var/www/AlToque`

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

`git reset --hard` descarta el código antiguo de Altoque (backup en `$BK`). **No afecta** a `/var/www/jemigra`, `/var/www/bar` ni a los demás.

## 3. Imagen PHP 8.5.13

```bash
docker build -t altoque_php:8.5.13 -f /var/www/AlToque/docker/Dockerfile /var/www/AlToque/docker
docker tag altoque_php:8.5.13 altoque_php

docker run --rm altoque_php php -v
docker run --rm altoque_php php -m | grep -E '^(soap|pdo_mysql|mysqli|zip|opcache|sodium|bcmath|intl|gd)$'
```

`php -v` debe imprimir **8.5.13** y el `grep` debe listar todas las extensiones. Si falta alguna, **no continuar**.

## 4. Validación en contenedor temporal (no toca producción)

Contenedor sin puertos, con el código montado **solo-lectura** y una copia en `/tmp` para instalar dependencias:

```bash
docker run -d --name altoque_upgrade_check \
  --network jemigra_jemigra_net \
  -v /var/www/AlToque:/var/www/AlToque:ro \
  --entrypoint sleep altoque_php infinity

docker exec altoque_upgrade_check sh -c \
  'mkdir -p /tmp/altoque-test && tar -C /var/www/AlToque --exclude=./vendor --exclude=./node_modules -cf - . | tar -C /tmp/altoque-test -xf -'

docker exec altoque_upgrade_check sh -c \
  'php -r "copy(\"https://getcomposer.org/installer\", \"/tmp/composer-setup.php\");" && php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer && rm -f /tmp/composer-setup.php'

docker exec -w /tmp/altoque-test altoque_upgrade_check composer install --no-interaction --prefer-dist
docker exec -w /tmp/altoque-test altoque_upgrade_check php artisan test
docker exec -w /tmp/altoque-test altoque_upgrade_check php artisan migrate:status
docker rm -f altoque_upgrade_check
```

Deben pasar los **9 tests** y `migrate:status` debe conectarse a `jemigra_db`. Si algo falla, borrar el contenedor y revisar antes de seguir.

## 5. Recrear `altoque_app` (único contenedor que se reemplaza)

```bash
docker stop altoque_app && docker rm altoque_app
docker run -d --name altoque_app \
  --restart unless-stopped \
  --network jemigra_jemigra_net \
  -v /var/www/AlToque:/var/www/AlToque \
  -w /var/www/AlToque \
  altoque_php php-fpm

docker exec altoque_app php -v
docker exec altoque_app php -m | grep -i '^soap$'
docker ps --filter name=altoque_app
```

> Si `$BK/altoque_app.inspect.json` mostrara variables de entorno de la app además de las de PHP, añádelas con `-e CLAVE=valor`. Actualmente Laravel lee todo desde `.env`.

## 6. Composer y `.env`

Instalar Composer **después** de recrear el contenedor (no sobrevive a `docker rm`):

```bash
docker exec altoque_app sh -c \
  'php -r "copy(\"https://getcomposer.org/installer\", \"/tmp/composer-setup.php\");" && php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer && rm -f /tmp/composer-setup.php'
docker exec altoque_app composer --version
docker exec -w /var/www/AlToque altoque_app composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
```

Ajustar `.env` (sin tocar `DB_PASSWORD`):

```bash
cd /var/www/AlToque
cp .env .env.bak.$(date +%Y%m%d-%H%M%S)
sed -i 's|^APP_URL=.*|APP_URL=https://altoque.jemigra.com|' .env
sed -i 's|^APP_LOCALE=.*|APP_LOCALE=es|' .env
sed -i 's|^APP_ENV=.*|APP_ENV=production|' .env
sed -i 's|^APP_DEBUG=.*|APP_DEBUG=false|' .env
sed -i 's|^DB_HOST=.*|DB_HOST=jemigra_db|' .env
sed -i 's|^DB_DATABASE=.*|DB_DATABASE=Yape|' .env
sed -i 's|^DB_USERNAME=.*|DB_USERNAME=jemigra_user|' .env
grep -q '^DB_USERNAME=' .env || printf 'DB_USERNAME=jemigra_user\n' >> .env
grep -E '^(APP_URL|APP_ENV|APP_DEBUG|APP_LOCALE|DB_HOST|DB_DATABASE|DB_USERNAME)=' .env
```

`APP_URL` debe ser exactamente `https://altoque.jemigra.com`: el `redirect_uri` de Gmail se deriva de ahí. `DB_HOST=jemigra_db` se mantiene: es el alias de red válido del contenedor `c6600299f82a_jemigra_db`. **No se toca `DB_PASSWORD`** ni se cambia la contraseña de `jemigra_user`.

## 7. Base de datos `Yape` (sin tocar `jemigra_bd`)

**Decisión confirmada: la `Yape` actual se reemplaza** con el dump local. El respaldo previo está en `$BK/Yape-before.sql`.

```bash
DBCTR=c6600299f82a_jemigra_db
read -s -p 'Contraseña root de MySQL: ' DBPASS; echo

# Permisos solo sobre Yape; no se modifica jemigra_bd ni se rotan contraseñas
docker exec -i -e MYSQL_PWD="$DBPASS" "$DBCTR" mysql -uroot \
  -e "CREATE DATABASE IF NOT EXISTS \`Yape\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
docker exec -i -e MYSQL_PWD="$DBPASS" "$DBCTR" mysql -uroot \
  -e "GRANT ALL PRIVILEGES ON \`Yape\`.* TO 'jemigra_user'@'%'; FLUSH PRIVILEGES;"

# Reemplaza completamente el esquema Yape (el dump incluye DROP TABLE IF EXISTS)
docker exec -i -e MYSQL_PWD="$DBPASS" "$DBCTR" mysql -uroot Yape < /tmp/altoque-deploy/yape_dump.sql
unset DBPASS

docker exec -w /var/www/AlToque altoque_app php artisan migrate:status
docker exec -w /var/www/AlToque altoque_app php artisan migrate --force
```

`migrate:status` debe mostrar todas las migraciones como *Ran*, incluida `2026_07_31_100000_add_nc_paths_to_transacciones`. **No ejecutar `db:seed`**: el admin `admin@yape.com` / `admin123` ya viene en el dump. Si algo falla aquí, restaurar con `$BK/Yape-before.sql` antes de seguir.

Caches:

```bash
docker exec -w /var/www/AlToque altoque_app php artisan config:clear
docker exec -w /var/www/AlToque altoque_app php artisan route:clear
docker exec -w /var/www/AlToque altoque_app php artisan view:clear
docker exec -w /var/www/AlToque altoque_app php artisan config:cache
docker exec -w /var/www/AlToque altoque_app php artisan route:cache
docker exec -w /var/www/AlToque altoque_app php artisan view:cache
docker exec -w /var/www/AlToque altoque_app php artisan storage:link
```

## 7b. Smoke test sin nginx ni dominio (fase 1)

No expone puertos ni modifica `jemigra_web`. Comprobaciones CLI:

```bash
docker exec altoque_app php -v
docker exec -w /var/www/AlToque altoque_app php artisan --version
docker exec -w /var/www/AlToque altoque_app php artisan migrate:status
docker exec -w /var/www/AlToque altoque_app php artisan db:show

docker exec -w /var/www/AlToque altoque_app php artisan tinker --execute='echo "users=".App\Models\User::count()."\nclientes=".App\Models\Cliente::count()."\ntransacciones=".App\Models\Transaccion::count()."\n";'
docker exec -w /var/www/AlToque altoque_app php artisan tinker --execute='echo \Illuminate\Support\Facades\Hash::check("admin123", App\Models\User::where("email","admin@yape.com")->first()->password) ? "ADMIN_OK\n" : "ADMIN_FAIL\n";'
```

Comprobación HTTP temporal **dentro** del contenedor (se apaga sola; no abre puertos al exterior):

```bash
docker exec -w /var/www/AlToque altoque_app sh -c '
  php artisan serve --host=127.0.0.1 --port=8000 >/tmp/artisan-serve.log 2>&1 &
  PID=$!
  sleep 3
  curl -sS -o /dev/null -w "login=%{http_code}\n" http://127.0.0.1:8000/login
  kill $PID 2>/dev/null || true
  tail -n 20 /tmp/artisan-serve.log
'
```

`login=200` confirma que Laravel responde. Si sale 500, revisar `/var/www/AlToque/storage/logs/laravel.log`.

## 8. Nginx compartido y HTTPS

Añadir **solo tres líneas** al servicio `web`. **Primero** dejar el vhost HTTP temporal: el definitivo referencia un certificado que aún no existe y haría fallar `nginx -t` al recrear el nginx compartido.

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

cd /var/www/jemigra
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

docker compose config > "$BK/jemigra-compose-rendered.yml"   # revisar que solo cambió web
mkdir -p /var/www/certbot
docker compose up -d --force-recreate web                    # SOLO web
docker exec jemigra_web nginx -t || {
  cp "$BK/jemigra-docker-compose.yml" /var/www/jemigra/docker-compose.yml
  cp "$BK/jemigra-default.conf" /var/www/jemigra/docker/nginx/default.conf
  mv -f /var/www/AlToque/docker/nginx/altoque.conf.https /var/www/AlToque/docker/nginx/altoque.conf
  docker compose up -d --force-recreate web
  echo 'ROLLBACK nginx aplicado'; exit 1
}
```

Certificado:

```bash
docker run --rm \
  -v /etc/letsencrypt:/etc/letsencrypt \
  -v /var/www/certbot:/var/www/certbot \
  certbot/certbot certonly --webroot -w /var/www/certbot \
  -d altoque.jemigra.com \
  --email wilbertsaul@gmail.com --agree-tos --no-eff-email
```

Restaurar HTTPS y validar:

```bash
mv -f /var/www/AlToque/docker/nginx/altoque.conf.https /var/www/AlToque/docker/nginx/altoque.conf
docker exec jemigra_web nginx -t && docker exec jemigra_web nginx -s reload
curl -I https://altoque.jemigra.com
curl -sS -o /dev/null -w 'login=%{http_code}\n' https://altoque.jemigra.com/login
```

## 9. Sidecars (solo `altoque_*`)

```bash
cd /var/www/AlToque
docker compose up -d --build scheduler queue
docker ps --filter name=altoque_
docker logs --tail=30 altoque_scheduler
docker logs --tail=30 altoque_queue
```

Ejecutan `sync:yape` cada 5 min y `sunat:enviar-boletas` cada hora, más `queue:work`.

## 10. Integraciones

**Gmail** (el código usa `route('gmail.callback')`, derivado de `APP_URL`):

1. Subir `credentials.json` a `/var/www/AlToque/credentials.json`.
2. Si hay token previo, subirlo a `/var/www/AlToque/storage/app/gmail_token.json`.
3. En Google Cloud Console añadir `https://altoque.jemigra.com/gmail/callback` a las URIs autorizadas.
4. Abrir `https://altoque.jemigra.com/gmail/redirect` y completar el consentimiento.

**SUNAT**:

```bash
# Subir el .pfx a /var/www/AlToque/storage/app/sunat/certificates/
docker exec -w /var/www/AlToque altoque_app php artisan sunat:check-cert
docker exec -w /var/www/AlToque altoque_app php artisan sunat:test-connection
```

Empezar con `SUNAT_AMBIENTE=beta` y cambiar a `produccion` tras validar. Regenerar `config:cache` si cambias `.env`.

## 11. Smoke test y comprobación de los otros proyectos

Checklist de la **fase 1** (sin dominio):

- [ ] `docker exec altoque_app php -v` → 8.5.13
- [ ] `migrate:status` → todas las migraciones *Ran*
- [ ] `login=200` en el smoke test HTTP temporal del paso 7b
- [ ] `ADMIN_OK` y conteos de users/clientes/transacciones del paso 7b
- [ ] Logs de `altoque_scheduler` / `altoque_queue` sin excepciones
- [ ] `jemigra_bd` y los demás contenedores siguen intactos

Checklist de la **fase 2** (cuando se active nginx/dominio/SSL): certificado válido, login por navegador, boletas/historial/clientes/saldos/usuarios sin 500, boleta de prueba en beta y vista móvil.

Verificar que los demás proyectos siguen vivos (fase 1: sin `curl` a dominios):

```bash
DBCTR=c6600299f82a_jemigra_db
docker ps --format '{{.Names}}\t{{.Status}}\t{{.Image}}'
read -s -p 'Contraseña root de MySQL: ' P; echo
docker exec -i -e MYSQL_PWD="$P" "$DBCTR" mysql -uroot -e 'SHOW DATABASES;'   # jemigra_bd debe seguir ahí
unset P
```

Solo en la fase 2, después del paso 8:

```bash
for u in https://jemigra.com https://bar.jemigra.com https://www.bytsolution.com; do
  curl -sS -o /dev/null -w "$u %{http_code}\n" "$u"
done
```

Logs si algo falla:

```bash
docker exec altoque_app tail -n 80 /var/www/AlToque/storage/logs/laravel.log
docker logs --tail=80 jemigra_web
```

---

## Rollback por componente

Antes: `DBCTR=c6600299f82a_jemigra_db` y `BK=/root/backups/altoque-20261125-183349` (o la ruta de tu backup).

| Componente | Restauración |
|---|---|
| Código / `.env` / `vendor` | `tar -xzf "$BK/altoque-code.tar.gz" -C /var/www/AlToque` |
| Compose de jemigra | `cp "$BK/jemigra-docker-compose.yml" /var/www/jemigra/docker-compose.yml && cd /var/www/jemigra && docker compose up -d --force-recreate web` |
| Nginx | `cp "$BK/jemigra-default.conf" /var/www/jemigra/docker/nginx/default.conf && docker exec jemigra_web nginx -t && docker exec jemigra_web nginx -s reload` |
| Let's Encrypt | `tar -xzf "$BK/letsencrypt.tar.gz" -C /etc && docker exec jemigra_web nginx -s reload` |
| BD `Yape` | `read -s -p 'Contraseña root: ' P; echo; docker exec -i -e MYSQL_PWD="$P" "$DBCTR" mysql -uroot Yape < "$BK/Yape-before.sql"; unset P` |
| `altoque_app` | `docker stop altoque_app && docker rm altoque_app && docker run -d --name altoque_app --restart unless-stopped --network jemigra_jemigra_net -v /var/www/AlToque:/var/www/AlToque -w /var/www/AlToque altoque_php:8.3-backup php-fpm` |
| Sidecars | `docker stop altoque_scheduler altoque_queue 2>/dev/null; docker rm altoque_scheduler altoque_queue 2>/dev/null` |

Tras cualquier rollback, repetir la comprobación de los otros proyectos del paso 11.
