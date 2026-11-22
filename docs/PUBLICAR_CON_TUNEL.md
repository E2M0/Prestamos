# Ver el sistema "publicado" con un túnel (demostración)

Esto NO es un despliegue de producción. El sistema sigue corriendo en su PC; un túnel le da una
dirección `https://` temporal para abrirlo desde el celular u otra red. Solo funciona mientras
la PC esté encendida, SQL Server activo y el túnel abierto.

## 1. Antes de abrirlo al exterior
1. Cambie las claves de `administrador` y `cajero` (las del script de datos son conocidas).
   En SQL Server Management Studio, o generando un hash nuevo con `php -r "echo password_hash('NuevaClave', PASSWORD_DEFAULT);"` y actualizando `usuarios.clave_hash`
   (tabla `usuarios`, columna `clave_hash`).
2. Comparta el enlace solo con quien quiera y cierre el túnel al terminar.

## 2. Compilar el frontend (una vez, y cada vez que cambie React)
```
cd C:\Users\elian\prestamos\frontend
npm run build
```
Esto crea `frontend/dist` (está en .gitignore, no se sube).

## 3. Arrancar el backend (sirve la página y la API en el puerto 8000)
```
cd C:\Users\elian\prestamos\backend
php -S localhost:8000 -t public public/index.php
```
Compruebe en http://localhost:8000 que aparece el login.

## 4. Abrir el túnel (elija uno)
**Cloudflare (sin cuenta):** instale `cloudflared` y ejecute
```
cloudflared tunnel --url http://localhost:8000
```
Muestra una dirección `https://algo.trycloudflare.com`.

**ngrok (cuenta gratuita):** `ngrok http 8000`.

Abra esa dirección: la cookie de sesión se marca `secure` automáticamente por HTTPS.

## 5. Cerrar
Ctrl+C en la ventana del túnel. El enlace deja de funcionar.

## Notas
- Para seguir desarrollando use el modo normal (Vite en 5173 + PHP en 8000); no cambia nada.
- Si cambia código de React, vuelva a ejecutar `npm run build`.
