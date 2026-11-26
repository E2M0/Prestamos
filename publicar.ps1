# Prepara la carpeta publicar\htdocs lista para subir al hosting por FTP.
# Uso (desde la carpeta del proyecto):   .\publicar.ps1
$ErrorActionPreference = 'Stop'
$raiz   = $PSScriptRoot
$salida = Join-Path $raiz 'publicar\htdocs'

Write-Host '1/4 Compilando el frontend (npm run build)...'
Push-Location (Join-Path $raiz 'frontend')
npm run build
if ($LASTEXITCODE -ne 0) { Pop-Location; throw 'Fallo npm run build' }
Pop-Location

Write-Host '2/4 Armando publicar\htdocs...'
if (Test-Path (Join-Path $raiz 'publicar')) { Remove-Item (Join-Path $raiz 'publicar') -Recurse -Force }
New-Item -ItemType Directory -Force (Join-Path $salida 'app\logs') | Out-Null
Copy-Item (Join-Path $raiz 'frontend\dist\*') $salida -Recurse
Copy-Item (Join-Path $raiz 'backend\public\index.php') $salida
Copy-Item (Join-Path $raiz 'despliegue\htaccess.txt') (Join-Path $salida '.htaccess')
Copy-Item (Join-Path $raiz 'backend\src') (Join-Path $salida 'app\src') -Recurse
if (Test-Path (Join-Path $raiz 'backend\lib')) { Copy-Item (Join-Path $raiz 'backend\lib') (Join-Path $salida 'app\lib') -Recurse }
Copy-Item (Join-Path $raiz 'despliegue\app-htaccess.txt') (Join-Path $salida 'app\.htaccess')

Write-Host '3/4 Configuracion del hosting...'
# El config.php local (con claves de su PC) nunca viaja al hosting.
Remove-Item (Join-Path $salida 'app\src\config\config.php') -ErrorAction SilentlyContinue
$cfg = Join-Path $raiz 'despliegue\config.hosting.php'
if (Test-Path $cfg) {
    Copy-Item $cfg (Join-Path $salida 'app\src\config\config.php')
} else {
    Write-Warning 'Falta despliegue\config.hosting.php (copie config.hosting.example.php y complete los datos de MySQL). Sin ese archivo el sitio no funcionara.'
}

Write-Host '4/4 Listo. Suba TODO el contenido de publicar\htdocs a la carpeta htdocs del hosting (incluidos los archivos que empiezan con punto).'
