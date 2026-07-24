<?php

declare(strict_types=1);

require __DIR__ . '/../../src/config.php';
require __DIR__ . '/../../src/qrcode.php';

// T15: PNG do QR Code, gerado 100% localmente (sem API de QR na internet -
// e exatamente a dependencia que quebra na hora da aula). Aponta pra tela
// de entrada do aluno (index.php?s=CODIGO), nao pro proprio qr.php.
$codigo = strtoupper((string) ($_GET['codigo'] ?? ''));

// HTTP_HOST (host:porta que o proprio navegador usou pra chegar aqui) em
// vez de SERVER_ADDR: o servidor embutido do PHP (php -S), que e o unico
// usado neste projeto, nao preenche SERVER_ADDR - fica vazio. HTTP_HOST
// sempre existe e reflete exatamente o endereco que carregou a pagina que
// pediu este QR (tela.php aberta pelo IP da rede -> HTTP_HOST = esse IP).
// IP_FIXO (src/config.php) e a valvula de escape se isso ainda errar.
if (IP_FIXO !== '') {
    $porta = (string) ($_SERVER['SERVER_PORT'] ?? '8080');
    $host = IP_FIXO . ':' . $porta;
} else {
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost:8080');
}
$url = "http://{$host}/index.php?s=" . rawurlencode($codigo);

$qr = QrCode::encodeText($url, QrCode::ECC_MEDIUM);

$escala = 20; // pixels por modulo - grande o bastante pra imprimir/exibir num tamanho A6+ e ler de longe na projecao
$borda = 4;  // quiet zone minima do padrao QR (4 modulos)
$tamanho = ($qr->getSize() + $borda * 2) * $escala;

$img = imagecreate($tamanho, $tamanho);
$branco = imagecolorallocate($img, 255, 255, 255);
$preto = imagecolorallocate($img, 0, 0, 0);
imagefilledrectangle($img, 0, 0, $tamanho, $tamanho, $branco);

for ($y = 0; $y < $qr->getSize(); $y++) {
    for ($x = 0; $x < $qr->getSize(); $x++) {
        if ($qr->getModule($x, $y)) {
            imagefilledrectangle(
                $img,
                ($x + $borda) * $escala,
                ($y + $borda) * $escala,
                ($x + $borda + 1) * $escala - 1,
                ($y + $borda + 1) * $escala - 1,
                $preto
            );
        }
    }
}

header('Content-Type: image/png');
header('Cache-Control: no-store');
imagepng($img);
imagedestroy($img);
