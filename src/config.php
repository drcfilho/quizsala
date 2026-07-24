<?php

declare(strict_types=1);

// T15: IP usado no QR Code que o aluno escaneia. Vazio = detectar sozinho
// pelo HTTP_HOST (o endereco que o proprio navegador usou pra abrir a
// pagina que pediu o QR - funciona bem mesmo em maquina com varias
// interfaces). Preencha aqui (ex.: '192.168.0.10') so se a deteccao errar.
const IP_FIXO = '';
