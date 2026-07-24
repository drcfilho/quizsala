<?php

declare(strict_types=1);

// T15: gerador de QR Code 100% local (sem chamar API externa - e exatamente
// a dependencia que quebra na hora da aula, sem internet na sala).
//
// Porte para PHP, restrito ao modo Byte (arquitetura.md/tasks.md so precisa
// codificar URLs - "http://..." ja tem letra minuscula, entao nunca cabe no
// modo alfanumerico do padrao QR mesmo; os modos Numerico/Alfanumerico/Kanji
// do original foram deixados de fora de proposito, nao teriam uso aqui) do
// algoritmo de referencia:
//   QR Code generator library (Python) - Project Nayuki, licenca MIT
//   https://www.nayuki.io/page/qr-code-generator-library
//
// Copyright (c) Project Nayuki. (MIT License)
// Permission is hereby granted, free of charge, to any person obtaining a copy of
// this software and associated documentation files (the "Software"), to deal in
// the Software without restriction, including without limitation the rights to
// use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
// the Software, and to permit persons to whom the Software is furnished to do so,
// subject to the following conditions:
// - The above copyright notice and this permission notice shall be included in
//   all copies or substantial portions of the Software.
// - The Software is provided "as is", without warranty of any kind, express or
//   implied, including but not limited to the warranties of merchantability,
//   fitness for a particular purpose and noninfringement. In no event shall the
//   authors or copyright holders be liable for any claim, damages or other
//   liability, whether in an action of contract, tort or otherwise, arising from,
//   out of or in connection with the Software or the use or other dealings in the
//   Software.

final class QrCode
{
    public const ECC_LOW = 0;
    public const ECC_MEDIUM = 1;
    public const ECC_QUARTILE = 2;
    public const ECC_HIGH = 3;

    private const FORMAT_BITS = [1, 0, 3, 2]; // indice = nivel ECC acima

    private const MIN_VERSION = 1;
    private const MAX_VERSION = 40;

    private const PENALTY_N1 = 3;
    private const PENALTY_N2 = 3;
    private const PENALTY_N3 = 40;
    private const PENALTY_N4 = 10;

    // [nivel ECC][versao] - indice 0 de cada linha nao e usado (versao comeca em 1)
    private const ECC_CODEWORDS_PER_BLOCK = [
        [-1, 7, 10, 15, 20, 26, 18, 20, 24, 30, 18, 20, 24, 26, 30, 22, 24, 28, 30, 28, 28, 28, 28, 30, 30, 26, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
        [-1, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24, 28, 28, 26, 26, 26, 26, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28],
        [-1, 13, 22, 18, 26, 18, 24, 18, 22, 20, 24, 28, 26, 24, 20, 30, 24, 28, 28, 26, 30, 28, 30, 30, 30, 30, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
        [-1, 17, 28, 22, 16, 22, 28, 26, 26, 24, 28, 24, 28, 22, 24, 24, 30, 28, 28, 26, 28, 30, 24, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
    ];

    private const NUM_ERROR_CORRECTION_BLOCKS = [
        [-1, 1, 1, 1, 1, 1, 2, 2, 2, 2, 4, 4, 4, 4, 4, 6, 6, 6, 6, 7, 8, 8, 9, 9, 10, 12, 12, 12, 13, 14, 15, 16, 17, 18, 19, 19, 20, 21, 22, 24, 25],
        [-1, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5, 5, 8, 9, 9, 10, 10, 11, 13, 14, 16, 17, 17, 18, 20, 21, 23, 25, 26, 28, 29, 31, 33, 35, 37, 38, 40, 43, 45, 47, 49],
        [-1, 1, 1, 2, 2, 4, 4, 6, 6, 8, 8, 8, 10, 12, 16, 12, 17, 16, 18, 21, 20, 23, 23, 25, 27, 29, 34, 34, 35, 38, 40, 43, 45, 48, 51, 53, 56, 59, 62, 65, 68],
        [-1, 1, 1, 2, 4, 4, 4, 5, 6, 8, 8, 11, 11, 16, 16, 18, 16, 19, 21, 25, 25, 25, 34, 30, 32, 35, 37, 40, 42, 45, 48, 51, 54, 57, 60, 63, 66, 70, 74, 77, 81],
    ];

    private int $version;
    private int $size;
    private int $errCorLvl;
    private int $mask;

    /** @var bool[][] */
    private array $modules;
    /** @var bool[][] */
    private array $isFunction;

    private function __construct(int $version, int $errCorLvl, array $dataCodewords, int $msk)
    {
        $this->version = $version;
        $this->size = $version * 4 + 17;
        $this->errCorLvl = $errCorLvl;

        $this->modules = array_fill(0, $this->size, array_fill(0, $this->size, false));
        $this->isFunction = array_fill(0, $this->size, array_fill(0, $this->size, false));

        $this->drawFunctionPatterns();
        $allCodewords = $this->addEccAndInterleave($dataCodewords);
        $this->drawCodewords($allCodewords);

        if ($msk === -1) {
            $minPenalty = PHP_INT_MAX;
            for ($i = 0; $i < 8; $i++) {
                $this->applyMask($i);
                $this->drawFormatBits($i);
                $penalty = $this->getPenaltyScore();
                if ($penalty < $minPenalty) {
                    $msk = $i;
                    $minPenalty = $penalty;
                }
                $this->applyMask($i); // desfaz (XOR de novo)
            }
        }

        $this->mask = $msk;
        $this->applyMask($msk);
        $this->drawFormatBits($msk);
        unset($this->isFunction);
    }

    // ---- API publica ----

    public static function encodeText(string $texto, int $ecl = self::ECC_MEDIUM): self
    {
        $dados = array_values(unpack('C*', $texto) ?: []);
        $numBytes = count($dados);

        $version = self::MIN_VERSION;
        $dataUsedBits = 0;
        for (; $version <= self::MAX_VERSION; $version++) {
            $dataCapacityBits = self::getNumDataCodewords($version, $ecl) * 8;
            $charCountBits = $version <= 9 ? 8 : 16;
            $dataUsedBits = 4 + $charCountBits + $numBytes * 8;
            if ($dataUsedBits <= $dataCapacityBits) {
                break;
            }
            if ($version >= self::MAX_VERSION) {
                throw new RuntimeException('Texto longo demais para um QR Code');
            }
        }

        foreach ([self::ECC_MEDIUM, self::ECC_QUARTILE, self::ECC_HIGH] as $novoEcl) {
            if ($dataUsedBits <= self::getNumDataCodewords($version, $novoEcl) * 8) {
                $ecl = $novoEcl;
            }
        }

        $charCountBits = $version <= 9 ? 8 : 16;
        $bb = new _QrBitBuffer();
        $bb->append(0x4, 4); // modo Byte
        $bb->append($numBytes, $charCountBits);
        foreach ($dados as $byte) {
            $bb->append($byte, 8);
        }

        $dataCapacityBits = self::getNumDataCodewords($version, $ecl) * 8;
        $bb->append(0, min(4, $dataCapacityBits - $bb->tamanho()));
        $bb->append(0, (8 - $bb->tamanho() % 8) % 8);

        $padBytes = [0xEC, 0x11];
        $i = 0;
        while ($bb->tamanho() < $dataCapacityBits) {
            $bb->append($padBytes[$i % 2], 8);
            $i++;
        }

        $dataCodewords = $bb->paraBytes();

        return new self($version, $ecl, $dataCodewords, -1);
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getModule(int $x, int $y): bool
    {
        return $x >= 0 && $x < $this->size && $y >= 0 && $y < $this->size && $this->modules[$y][$x];
    }

    // ---- Desenho dos padroes de funcao ----

    private function drawFunctionPatterns(): void
    {
        for ($i = 0; $i < $this->size; $i++) {
            $this->setFunctionModule(6, $i, $i % 2 === 0);
            $this->setFunctionModule($i, 6, $i % 2 === 0);
        }

        $this->drawFinderPattern(3, 3);
        $this->drawFinderPattern($this->size - 4, 3);
        $this->drawFinderPattern(3, $this->size - 4);

        $alignPatPos = $this->getAlignmentPatternPositions();
        $numAlign = count($alignPatPos);
        for ($i = 0; $i < $numAlign; $i++) {
            for ($j = 0; $j < $numAlign; $j++) {
                $canto = ($i === 0 && $j === 0) || ($i === 0 && $j === $numAlign - 1) || ($i === $numAlign - 1 && $j === 0);
                if (!$canto) {
                    $this->drawAlignmentPattern($alignPatPos[$i], $alignPatPos[$j]);
                }
            }
        }

        $this->drawFormatBits(0); // valor dummy, sobrescrito no fim do construtor
        $this->drawVersion();
    }

    private function drawFormatBits(int $mask): void
    {
        $data = self::FORMAT_BITS[$this->errCorLvl] << 3 | $mask;
        $rem = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        }
        $bits = ($data << 10 | $rem) ^ 0x5412;

        for ($i = 0; $i <= 5; $i++) {
            $this->setFunctionModule(8, $i, self::getBit($bits, $i));
        }
        $this->setFunctionModule(8, 7, self::getBit($bits, 6));
        $this->setFunctionModule(8, 8, self::getBit($bits, 7));
        $this->setFunctionModule(7, 8, self::getBit($bits, 8));
        for ($i = 9; $i <= 14; $i++) {
            $this->setFunctionModule(14 - $i, 8, self::getBit($bits, $i));
        }

        for ($i = 0; $i <= 7; $i++) {
            $this->setFunctionModule($this->size - 1 - $i, 8, self::getBit($bits, $i));
        }
        for ($i = 8; $i <= 14; $i++) {
            $this->setFunctionModule(8, $this->size - 15 + $i, self::getBit($bits, $i));
        }
        $this->setFunctionModule(8, $this->size - 8, true);
    }

    private function drawVersion(): void
    {
        if ($this->version < 7) {
            return;
        }

        $rem = $this->version;
        for ($i = 0; $i < 12; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
        }
        $bits = $this->version << 12 | $rem;

        for ($i = 0; $i < 18; $i++) {
            $bit = self::getBit($bits, $i);
            $a = $this->size - 11 + $i % 3;
            $b = intdiv($i, 3);
            $this->setFunctionModule($a, $b, $bit);
            $this->setFunctionModule($b, $a, $bit);
        }
    }

    private function drawFinderPattern(int $x, int $y): void
    {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $xx = $x + $dx;
                $yy = $y + $dy;
                if ($xx >= 0 && $xx < $this->size && $yy >= 0 && $yy < $this->size) {
                    $dist = max(abs($dx), abs($dy));
                    $this->setFunctionModule($xx, $yy, $dist !== 2 && $dist !== 4);
                }
            }
        }
    }

    private function drawAlignmentPattern(int $x, int $y): void
    {
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $this->setFunctionModule($x + $dx, $y + $dy, max(abs($dx), abs($dy)) !== 1);
            }
        }
    }

    private function setFunctionModule(int $x, int $y, bool $isDark): void
    {
        $this->modules[$y][$x] = $isDark;
        $this->isFunction[$y][$x] = true;
    }

    // ---- Codewords e mascara ----

    /** @param int[] $data @return int[] */
    private function addEccAndInterleave(array $data): array
    {
        $version = $this->version;
        $numBlocks = self::NUM_ERROR_CORRECTION_BLOCKS[$this->errCorLvl][$version];
        $blockEccLen = self::ECC_CODEWORDS_PER_BLOCK[$this->errCorLvl][$version];
        $rawCodewords = intdiv(self::getNumRawDataModules($version), 8);
        $numShortBlocks = $numBlocks - $rawCodewords % $numBlocks;
        $shortBlockLen = intdiv($rawCodewords, $numBlocks);

        $blocks = [];
        $rsDiv = self::reedSolomonComputeDivisor($blockEccLen);
        $k = 0;
        for ($i = 0; $i < $numBlocks; $i++) {
            $tamanhoDat = $shortBlockLen - $blockEccLen + ($i < $numShortBlocks ? 0 : 1);
            $dat = array_slice($data, $k, $tamanhoDat);
            $k += count($dat);
            $ecc = self::reedSolomonComputeRemainder($dat, $rsDiv);
            if ($i < $numShortBlocks) {
                $dat[] = 0;
            }
            $blocks[] = array_merge($dat, $ecc);
        }

        $result = [];
        $totalNesteBloco = count($blocks[0]);
        for ($i = 0; $i < $totalNesteBloco; $i++) {
            foreach ($blocks as $j => $blk) {
                if ($i !== $shortBlockLen - $blockEccLen || $j >= $numShortBlocks) {
                    $result[] = $blk[$i];
                }
            }
        }

        return $result;
    }

    /** @param int[] $data */
    private function drawCodewords(array $data): void
    {
        $i = 0;
        $totalBits = count($data) * 8;

        for ($right = $this->size - 1; $right >= 1; $right -= 2) {
            if ($right <= 6) {
                $right--;
            }
            for ($vert = 0; $vert < $this->size; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $upward = (($right + 1) & 2) === 0;
                    $y = $upward ? ($this->size - 1 - $vert) : $vert;
                    if (!$this->isFunction[$y][$x] && $i < $totalBits) {
                        $this->modules[$y][$x] = self::getBit($data[$i >> 3], 7 - ($i & 7));
                        $i++;
                    }
                }
            }
        }
    }

    private function applyMask(int $mask): void
    {
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                if (!$this->isFunction[$y][$x] && self::maskInvert($mask, $x, $y)) {
                    $this->modules[$y][$x] = !$this->modules[$y][$x];
                }
            }
        }
    }

    private static function maskInvert(int $mask, int $x, int $y): bool
    {
        $valor = match ($mask) {
            0 => ($x + $y) % 2,
            1 => $y % 2,
            2 => $x % 3,
            3 => ($x + $y) % 3,
            4 => (intdiv($x, 3) + intdiv($y, 2)) % 2,
            5 => ($x * $y) % 2 + ($x * $y) % 3,
            6 => (($x * $y) % 2 + ($x * $y) % 3) % 2,
            7 => (($x + $y) % 2 + ($x * $y) % 3) % 2,
            default => throw new RuntimeException('Mascara invalida'),
        };

        return $valor === 0;
    }

    private function getPenaltyScore(): int
    {
        $result = 0;
        $size = $this->size;
        $modules = $this->modules;

        for ($y = 0; $y < $size; $y++) {
            $runColor = false;
            $runX = 0;
            $runHistory = array_fill(0, 7, 0);
            for ($x = 0; $x < $size; $x++) {
                if ($modules[$y][$x] === $runColor) {
                    $runX++;
                    if ($runX === 5) {
                        $result += self::PENALTY_N1;
                    } elseif ($runX > 5) {
                        $result += 1;
                    }
                } else {
                    $this->finderPenaltyAddHistory($runX, $runHistory);
                    if (!$runColor) {
                        $result += $this->finderPenaltyCountPatterns($runHistory) * self::PENALTY_N3;
                    }
                    $runColor = $modules[$y][$x];
                    $runX = 1;
                }
            }
            $result += $this->finderPenaltyTerminateAndCount($runColor, $runX, $runHistory) * self::PENALTY_N3;
        }

        for ($x = 0; $x < $size; $x++) {
            $runColor = false;
            $runY = 0;
            $runHistory = array_fill(0, 7, 0);
            for ($y = 0; $y < $size; $y++) {
                if ($modules[$y][$x] === $runColor) {
                    $runY++;
                    if ($runY === 5) {
                        $result += self::PENALTY_N1;
                    } elseif ($runY > 5) {
                        $result += 1;
                    }
                } else {
                    $this->finderPenaltyAddHistory($runY, $runHistory);
                    if (!$runColor) {
                        $result += $this->finderPenaltyCountPatterns($runHistory) * self::PENALTY_N3;
                    }
                    $runColor = $modules[$y][$x];
                    $runY = 1;
                }
            }
            $result += $this->finderPenaltyTerminateAndCount($runColor, $runY, $runHistory) * self::PENALTY_N3;
        }

        for ($y = 0; $y < $size - 1; $y++) {
            for ($x = 0; $x < $size - 1; $x++) {
                if ($modules[$y][$x] === $modules[$y][$x + 1]
                    && $modules[$y][$x] === $modules[$y + 1][$x]
                    && $modules[$y][$x] === $modules[$y + 1][$x + 1]
                ) {
                    $result += self::PENALTY_N2;
                }
            }
        }

        $dark = 0;
        foreach ($modules as $linha) {
            foreach ($linha as $celula) {
                if ($celula) {
                    $dark++;
                }
            }
        }
        $total = $size * $size;
        $k = intdiv(abs($dark * 20 - $total * 10) + $total - 1, $total) - 1;
        $result += $k * self::PENALTY_N4;

        return $result;
    }

    // ---- Auxiliares privados ----

    /** @return int[] */
    private function getAlignmentPatternPositions(): array
    {
        if ($this->version === 1) {
            return [];
        }

        $numAlign = intdiv($this->version, 7) + 2;
        $step = intdiv($this->version * 8 + $numAlign * 3 + 5, $numAlign * 4 - 4) * 2;
        $result = [];
        for ($i = 0; $i < $numAlign - 1; $i++) {
            $result[] = $this->size - 7 - $i * $step;
        }
        $result[] = 6;

        return array_reverse($result);
    }

    private static function getNumRawDataModules(int $ver): int
    {
        $result = (16 * $ver + 128) * $ver + 64;
        if ($ver >= 2) {
            $numAlign = intdiv($ver, 7) + 2;
            $result -= (25 * $numAlign - 10) * $numAlign - 55;
            if ($ver >= 7) {
                $result -= 36;
            }
        }

        return $result;
    }

    private static function getNumDataCodewords(int $ver, int $ecl): int
    {
        return intdiv(self::getNumRawDataModules($ver), 8)
            - self::ECC_CODEWORDS_PER_BLOCK[$ecl][$ver] * self::NUM_ERROR_CORRECTION_BLOCKS[$ecl][$ver];
    }

    /** @return int[] */
    private static function reedSolomonComputeDivisor(int $degree): array
    {
        $result = array_fill(0, $degree, 0);
        $result[$degree - 1] = 1;

        $root = 1;
        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $result[$j] = self::reedSolomonMultiply($result[$j], $root);
                if ($j + 1 < $degree) {
                    $result[$j] ^= $result[$j + 1];
                }
            }
            $root = self::reedSolomonMultiply($root, 0x02);
        }

        return $result;
    }

    /**
     * @param int[] $data
     * @param int[] $divisor
     * @return int[]
     */
    private static function reedSolomonComputeRemainder(array $data, array $divisor): array
    {
        $result = array_fill(0, count($divisor), 0);
        foreach ($data as $b) {
            $factor = $b ^ array_shift($result);
            $result[] = 0;
            foreach ($divisor as $i => $coef) {
                $result[$i] ^= self::reedSolomonMultiply($coef, $factor);
            }
        }

        return $result;
    }

    private static function reedSolomonMultiply(int $x, int $y): int
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = ($z << 1) ^ (($z >> 7) * 0x11D);
            $z ^= (($y >> $i) & 1) * $x;
        }

        return $z & 0xFF;
    }

    /** @param int[] $runHistory */
    private function finderPenaltyCountPatterns(array $runHistory): int
    {
        $n = $runHistory[1];
        $core = $n > 0 && $runHistory[2] === $n && $runHistory[4] === $n && $runHistory[5] === $n && $runHistory[3] === $n * 3;

        return ($core && $runHistory[0] >= $n * 4 && $runHistory[6] >= $n ? 1 : 0)
            + ($core && $runHistory[6] >= $n * 4 && $runHistory[0] >= $n ? 1 : 0);
    }

    /** @param int[] $runHistory */
    private function finderPenaltyTerminateAndCount(bool $currentRunColor, int $currentRunLength, array $runHistory): int
    {
        if ($currentRunColor) {
            $this->finderPenaltyAddHistory($currentRunLength, $runHistory);
            $currentRunLength = 0;
        }
        $currentRunLength += $this->size;
        $this->finderPenaltyAddHistory($currentRunLength, $runHistory);

        return $this->finderPenaltyCountPatterns($runHistory);
    }

    /** @param int[] $runHistory */
    private function finderPenaltyAddHistory(int $currentRunLength, array &$runHistory): void
    {
        if ($runHistory[0] === 0) {
            $currentRunLength += $this->size;
        }
        array_pop($runHistory);
        array_unshift($runHistory, $currentRunLength);
    }

    private static function getBit(int $x, int $i): bool
    {
        return (($x >> $i) & 1) !== 0;
    }
}

/** Buffer de bits usado só na montagem dos dados antes da correção de erro. */
final class _QrBitBuffer
{
    /** @var int[] */
    private array $bits = [];

    public function append(int $val, int $n): void
    {
        for ($i = $n - 1; $i >= 0; $i--) {
            $this->bits[] = ($val >> $i) & 1;
        }
    }

    public function tamanho(): int
    {
        return count($this->bits);
    }

    /** @return int[] */
    public function paraBytes(): array
    {
        $bytes = array_fill(0, intdiv(count($this->bits), 8), 0);
        foreach ($this->bits as $i => $bit) {
            $bytes[$i >> 3] |= $bit << (7 - ($i & 7));
        }

        return $bytes;
    }
}
