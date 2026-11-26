# Prepara la carpeta publicar\htdocs lista para subir al hosting por FTP.
# Uso (desde la carpeta del proyecto):   .\publicar.ps1
# Antes de armar nada comprueba lo que suele fallar (rama equivocada, configuracion incompleta, errores de PHP)
# y se detiene con un mensaje claro en vez de dejar un sitio roto en el hosting.
$ErrorActionPreference = 'Stop'
# Si el script se pega en la terminal en vez de ejecutarse como archivo, $PSScriptRoot queda vacio: se usa la carpeta actual.
$raiz   = if ($PSScriptRoot) { $PSScriptRoot } else { (Get-Location).Path }
$salida = Join-Path $raiz 'publicar\htdocs'
$cfg    = Join-Path $raiz 'despliegue\config.hosting.php'

function Fallar([string]$mensaje) { throw "PUBLICAR DETENIDO: $mensaje" }

Write-Host '1/6 Comprobando el proyecto...'
if (-not (Test-Path (Join-Path $raiz 'backend\src')) -or -not (Test-Path (Join-Path $raiz 'frontend\package.json'))) { Fallar "No parece la carpeta del proyecto: $raiz. Ejecute el script desde C:\Users\elian\prestamos." }
# a) Rama MySQL: la version de SQL Server no funciona en el hosting.
$bd = Get-Content (Join-Path $raiz 'backend\src\config\Database.php') -Raw
if ($bd -match 'sqlsrv') { Fallar 'backend\src\config\Database.php usa SQL Server (sqlsrv). Cambie a la rama MySQL (git checkout version-mysql) y vuelva a ejecutar.' }
if ($bd -notmatch 'mysql:host') { Fallar 'backend\src\config\Database.php no parece ser la version MySQL.' }

# b) Configuracion del hosting (el config.php local nunca se sube).
if (-not (Test-Path $cfg)) { Fallar 'Falta despliegue\config.hosting.php. Copie despliegue\config.hosting.example.php con ese nombre y complete los datos de MySQL, empresa y correo.' }
$texto = Get-Content $cfg -Raw
foreach ($clave in @('db', 'cors', 'empresa', 'correo', 'zona_horaria')) {
    if ($texto -notmatch "'$clave'\s*=>") { Fallar "despliegue\config.hosting.php no tiene la seccion '$clave' (compare con config.hosting.example.php)." }
}
if ($texto -match "'servidor'\s*=>\s*'([^']*)'") {
    $servidor = $Matches[1]
    if ($servidor -match '[/\s:]|^https?') { Fallar "El servidor MySQL '$servidor' no es valido: sin barra final, espacios, http:// ni puerto. Ejemplo: sql305.infinityfree.com" }
    if ($servidor -eq 'localhost') { Write-Warning "El servidor MySQL es 'localhost'. En InfinityFree debe ser el 'MySQL Hostname' del panel (sqlNNN.infinityfree.com)." }
} else { Fallar "No se encontro 'servidor' dentro de la seccion db de config.hosting.php." }
if ($texto -match "'usuario'\s*=>\s*'root'") { Write-Warning "El usuario MySQL es 'root'. En el hosting es el usuario que muestra el panel (if0_...)." }

# c) Recursos que el sistema espera.
if (-not (Test-Path (Join-Path $raiz 'frontend\public\logo.png'))) { Write-Warning 'No existe frontend\public\logo.png: el sitio saldra sin logo.' }
if (-not (Test-Path (Join-Path $raiz 'backend\lib\PHPMailer\src\PHPMailer.php'))) { Write-Warning 'No existe backend\lib\PHPMailer: el envio de correos no funcionara.' }

# d) Sintaxis de PHP (si PHP esta instalado en esta computadora).
if (Get-Command php -ErrorAction SilentlyContinue) {
    $malos = @()
    Get-ChildItem (Join-Path $raiz 'backend') -Recurse -Filter *.php | Where-Object { $_.FullName -notmatch '\\lib\\' } | ForEach-Object {
        $r = & php -l $_.FullName 2>&1
        if ($LASTEXITCODE -ne 0) { $malos += $_.FullName }
    }
    if ($malos.Count) { Fallar ("Errores de sintaxis PHP en:`n  " + ($malos -join "`n  ")) }
} else { Write-Warning 'php no esta en el PATH: no se reviso la sintaxis.' }

Write-Host '2/6 Compilando el frontend...'
Push-Location (Join-Path $raiz 'frontend')
try {
    if (-not (Test-Path 'node_modules')) { npm install; if ($LASTEXITCODE -ne 0) { Fallar 'Fallo npm install' } }
    npm run build
    if ($LASTEXITCODE -ne 0) { Fallar 'Fallo npm run build (revise los errores de arriba).' }
} finally { Pop-Location }

Write-Host '3/6 Armando publicar\htdocs...'
if (Test-Path (Join-Path $raiz 'publicar')) { Remove-Item (Join-Path $raiz 'publicar') -Recurse -Force }
New-Item -ItemType Directory -Force (Join-Path $salida 'app\logs') | Out-Null
Copy-Item (Join-Path $raiz 'frontend\dist\*') $salida -Recurse
Copy-Item (Join-Path $raiz 'backend\public\index.php') $salida
Copy-Item (Join-Path $raiz 'despliegue\htaccess.txt') (Join-Path $salida '.htaccess')
Copy-Item (Join-Path $raiz 'backend\src') (Join-Path $salida 'app\src') -Recurse
if (Test-Path (Join-Path $raiz 'backend\lib')) { Copy-Item (Join-Path $raiz 'backend\lib') (Join-Path $salida 'app\lib') -Recurse }
Copy-Item (Join-Path $raiz 'despliegue\app-htaccess.txt') (Join-Path $salida 'app\.htaccess')

Write-Host '4/6 Configuracion del hosting...'
# El config.php local (con claves de su PC) nunca viaja al hosting: se reemplaza por config.hosting.php.
Remove-Item (Join-Path $salida 'app\src\config\config.php') -ErrorAction SilentlyContinue
Copy-Item $cfg (Join-Path $salida 'app\src\config\config.php')

Write-Host '5/6 Verificando el resultado...'
$requeridos = @('index.html', 'index.php', '.htaccess', 'assets', 'app\.htaccess', 'app\logs',
                'app\src\config\config.php', 'app\src\config\Database.php', 'app\src\routes\api.php')
foreach ($ruta in $requeridos) {
    if (-not (Test-Path (Join-Path $salida $ruta))) { Fallar "Falta '$ruta' dentro de publicar\htdocs." }
}
if ((Get-Content (Join-Path $salida 'app\src\config\config.php') -Raw) -match 'ELIAN|SQLDEV|Autenticacion de Windows') { Fallar 'El config.php del paquete es el local, no el del hosting.' }
$archivos = (Get-ChildItem $salida -Recurse -File).Count
$mb = [math]::Round(((Get-ChildItem $salida -Recurse -File | Measure-Object Length -Sum).Sum / 1MB), 1)

Write-Host '6/6 Listo.'
Write-Host ''
Write-Host "Paquete: $archivos archivos, $mb MB, en: $salida"
Write-Host ''
Write-Host 'COMO SUBIR (para evitar errores de rutas):'
Write-Host '  1. Abra la carpeta publicar\htdocs y seleccione TODO su CONTENIDO (Ctrl+A), incluidos los archivos que empiezan con punto.'
Write-Host '  2. Peguelo DENTRO de la carpeta htdocs del hosting. No suba la carpeta htdocs completa (quedaria htdocs\htdocs).'
Write-Host '  3. En el hosting deben quedar, directamente en htdocs: index.html, index.php, .htaccess, assets\ y app\.'
Write-Host '  4. Si cambio la base de datos, ejecute antes el script de database\mysql\ en phpMyAdmin.'
Write-Host '  5. Abra /api/salud en su sitio: debe decir "baseDatos": "conectada". Luego recargue con Ctrl+F5.'
