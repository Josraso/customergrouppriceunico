<?php
/**
 * Script de diagnostico — customergrouppriceunico
 *
 * Ejecutar por SSH:
 *   php /var/www/vhosts/mesascomedor.es/httpdocs/modules/customergrouppriceunico/diag.php
 *
 * BORRAR ESTE ARCHIVO UNA VEZ IDENTIFICADO EL PROBLEMA.
 */
$isCli = PHP_SAPI === 'cli';

// Por web requiere parametro de seguridad; por CLI siempre corre
if (!$isCli && ($_GET['run'] ?? '') !== 'unico') {
    http_response_code(403);
    die('Ejecuta por SSH: php modules/customergrouppriceunico/diag.php');
}

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
set_error_handler(static function ($errno, $errstr, $errfile, $errline) {
    echo "\n[ERROR PHP $errno] $errstr  en $errfile:$errline\n";
    return true;
});

header('Content-Type: text/plain; charset=utf-8');
echo "=== DIAGNOSTICO customergrouppriceunico ===\n";
echo "PHP : " . PHP_VERSION . "\n";
echo "SAPI: " . PHP_SAPI . "\n";
echo "Hora: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Localizar raiz de PrestaShop
$psRoot = null;
$dir = __DIR__;
for ($i = 0; $i < 6; $i++) {
    if (file_exists($dir . '/config/config.inc.php')) {
        $psRoot = $dir;
        break;
    }
    $dir = dirname($dir);
}
if (!$psRoot) {
    die("FATAL: no encuentro config/config.inc.php subiendo desde " . __DIR__ . "\n");
}
echo "PS root : $psRoot\n";

// 2. Class index — que archivo usa PS para la clase Product
echo "\n=== CLASS INDEX ===\n";
$indexPaths = [
    "$psRoot/app/cache/prod/class_index.php",
    "$psRoot/app/cache/dev/class_index.php",
    "$psRoot/cache/class_index.php",
];
foreach ($indexPaths as $path) {
    if (!file_exists($path)) {
        echo "No existe: $path\n";
        continue;
    }
    echo "ENCONTRADO: $path\n";
    try {
        $idx = include $path;
    } catch (Throwable $t) {
        echo "  ERROR al cargar el index: " . $t->getMessage() . "\n";
        break;
    }
    echo "  Product     => " . json_encode($idx['Product']     ?? '(ausente)') . "\n";
    echo "  ProductCore => " . json_encode($idx['ProductCore'] ?? '(ausente)') . "\n";
    break;
}

// 3. Archivo override
echo "\n=== ARCHIVO OVERRIDE ===\n";
$ovFile = "$psRoot/override/classes/Product.php";
echo "Existe override/classes/Product.php: " . (file_exists($ovFile) ? "SI" : "NO") . "\n";
if (file_exists($ovFile)) {
    $lines = file($ovFile);
    echo "Primeras 5 lineas:\n";
    echo implode('', array_slice($lines, 0, 5));
}

// 4. Log de debug del override (escrito por nuestro shutdown handler)
echo "\n=== LOG DE DEBUG DEL OVERRIDE ===\n";
$logCandidates = [
    "$psRoot/var/logs/unico_override_debug.log",
    sys_get_temp_dir() . '/unico_override_debug.log',
];
foreach ($logCandidates as $lf) {
    echo "Buscando $lf : " . (file_exists($lf) ? "EXISTE" : "no existe") . "\n";
    if (file_exists($lf)) {
        $lines = file($lf);
        echo "Ultimas 20 lineas:\n";
        echo implode('', array_slice($lines, -20));
    }
}

// 5. Cargar PS y probar la clase Product
echo "\n=== CARGA DE PS Y CLASE PRODUCT ===\n";
try {
    // Silenciar warnings de PS durante el bootstrap
    ob_start();
    require_once $psRoot . '/config/config.inc.php';
    $bootOutput = ob_get_clean();
    if (trim($bootOutput)) {
        echo "Output durante bootstrap:\n$bootOutput\n";
    }
    echo "PS version: " . (defined('_PS_VERSION_') ? _PS_VERSION_ : '(no definido)') . "\n";
} catch (Throwable $t) {
    ob_end_clean();
    echo "ERROR cargando config.inc.php:\n";
    echo "  [" . get_class($t) . "] " . $t->getMessage() . "\n";
    echo "  en " . $t->getFile() . ":" . $t->getLine() . "\n";
    echo $t->getTraceAsString() . "\n";
    die("\nDiagnostico interrumpido.\n");
}

// Intentar reflectar la clase Product
echo "\nIntentando ReflectionClass('Product')...\n";
try {
    $ref = new ReflectionClass('Product');
    echo "OK — archivo: " . $ref->getFileName() . "\n";
    echo "Tiene priceCalculation: " . ($ref->hasMethod('priceCalculation') ? 'SI' : 'NO') . "\n";
    $m = $ref->getMethod('priceCalculation');
    echo "priceCalculation params: " . $m->getNumberOfParameters() . "\n";
    echo "priceCalculation es estatico: " . ($m->isStatic() ? 'SI' : 'NO') . "\n";
} catch (Throwable $t) {
    echo "ERROR al reflejar Product:\n";
    echo "  [" . get_class($t) . "] " . $t->getMessage() . "\n";
    echo "  en " . $t->getFile() . ":" . $t->getLine() . "\n";
    echo $t->getTraceAsString() . "\n";
}

// Ver si el modulo esta instalado y activo
echo "\n=== ESTADO DEL MODULO ===\n";
try {
    $row = Db::getInstance()->getRow(
        "SELECT `id_module`, `active` FROM `" . _DB_PREFIX_ . "module` WHERE `name` = 'customergrouppriceunico'"
    );
    if ($row) {
        echo "Modulo en BD: id=" . $row['id_module'] . " active=" . $row['active'] . "\n";
    } else {
        echo "Modulo NO encontrado en ps_module (no instalado)\n";
    }
    $hooks = Db::getInstance()->executeS(
        "SELECT h.`name` FROM `" . _DB_PREFIX_ . "hook_module` hm
         JOIN `" . _DB_PREFIX_ . "hook` h ON h.`id_hook` = hm.`id_hook`
         JOIN `" . _DB_PREFIX_ . "module` m ON m.`id_module` = hm.`id_module`
         WHERE m.`name` = 'customergrouppriceunico'"
    );
    echo "Hooks registrados: " . implode(', ', array_column($hooks ?: [], 'name')) . "\n";

    $cfg = Db::getInstance()->getValue(
        "SELECT `value` FROM `" . _DB_PREFIX_ . "configuration` WHERE `name` = 'CGRPPRICEUNICO_ENABLE'"
    );
    echo "CGRPPRICEUNICO_ENABLE = " . var_export($cfg, true) . "\n";
} catch (Throwable $t) {
    echo "ERROR consultando BD: " . $t->getMessage() . "\n";
}

echo "\n=== FIN DIAGNOSTICO ===\n";
echo "BORRAR ESTE ARCHIVO: rm modules/customergrouppriceunico/diag.php\n";
