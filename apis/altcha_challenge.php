<?php
require_once __DIR__ . "/../vendor/autoload.php";

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\CreateChallengeOptions;

include_once __DIR__ . "/../includes/funciones.php";
iniciar_sesion_segura();

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    header("Allow: GET");
    exit();
}

$secret = $_SESSION["altcha_secret"] ??= bin2hex(random_bytes(32));
$altcha = new Altcha(hmacSignatureSecret: $secret);
$reto = $altcha->createChallenge(new CreateChallengeOptions(
    algorithm: new Pbkdf2(),
    cost: 5000,
    expiresAt: time() + 120
));

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");
echo json_encode($reto->toArray(), JSON_THROW_ON_ERROR);
