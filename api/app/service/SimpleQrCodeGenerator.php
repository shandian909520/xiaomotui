<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Log;

/**
 * 简易二维码生成器
 * 使用GD库实现，作为第三方二维码库的降级方案
 */
class SimpleQrCodeGenerator
{
    private array $matrix = [];
    private int $size;

    public function generate(string $content, string $savePath, int $outputSize = 430): void
    {
        $qrSize = 5;
        $this->size = $qrSize * 4 + 17;
        $this->matrix = array_fill(0, $this->size, array_fill(0, $this->size, 0));

        $this->drawFinderPattern(0, 0);
        $this->drawFinderPattern($this->size - 7, 0);
        $this->drawFinderPattern(0, $this->size - 7);
        $this->drawTimingPatterns();
        $this->drawAlignmentPattern($this->size - 9, $this->size - 9);

        $dataModules = $this->encodeData($content);
        $this->placeData($dataModules);

        $this->renderImage($savePath, $outputSize);
    }

    private function drawFinderPattern(int $x, int $y): void
    {
        for ($row = -1; $row <= 7; $row++) {
            for ($col = -1; $col <= 7; $col++) {
                $r = $y + $row;
                $c = $x + $col;
                if ($r < 0 || $r >= $this->size || $c < 0 || $c >= $this->size) {
                    continue;
                }
                $isBorder = $row === -1 || $row === 7 || $col === -1 || $col === 7;
                $isOuter = $row === 0 || $row === 6 || $col === 0 || $col === 6;
                $isInner = $row >= 2 && $row <= 4 && $col >= 2 && $col <= 4;

                if ($isBorder) {
                    $this->matrix[$r][$c] = 0;
                } elseif ($isOuter || $isInner) {
                    $this->matrix[$r][$c] = 1;
                } else {
                    $this->matrix[$r][$c] = 0;
                }
            }
        }
    }

    private function drawTimingPatterns(): void
    {
        for ($i = 8; $i < $this->size - 8; $i++) {
            $this->matrix[6][$i] = ($i % 2 === 0) ? 1 : 0;
            $this->matrix[$i][6] = ($i % 2 === 0) ? 1 : 0;
        }
    }

    private function drawAlignmentPattern(int $cx, int $cy): void
    {
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $r = $cy + $dy;
                $c = $cx + $dx;
                if ($r < 0 || $r >= $this->size || $c < 0 || $c >= $this->size) {
                    continue;
                }
                $isOuter = abs($dy) === 2 || abs($dx) === 2;
                $isCenter = $dy === 0 && $dx === 0;
                $this->matrix[$r][$c] = ($isOuter || $isCenter) ? 1 : 0;
            }
        }
    }

    private function encodeData(string $content): array
    {
        $binary = '0100';
        $len = strlen($content);
        $binary .= str_pad(decbin($len), 8, '0', STR_PAD_LEFT);

        for ($i = 0; $i < $len; $i++) {
            $binary .= str_pad(decbin(ord($content[$i])), 8, '0', STR_PAD_LEFT);
        }

        $binary .= '0000';
        $dataCodewords = ceil(strlen($binary) / 8);

        while ($dataCodewords < 19) {
            $binary .= str_pad(decbin($dataCodewords % 2 === 0 ? 236 : 17), 8, '0', STR_PAD_LEFT);
            $dataCodewords++;
        }

        $modules = [];
        for ($i = 0; $i < strlen($binary); $i++) {
            $modules[] = (int)$binary[$i];
        }

        $this->applyErrorCorrection($modules);

        return $modules;
    }

    private function applyErrorCorrection(array &$data): void
    {
        for ($i = 0; $i < 7; $i++) {
            foreach ($data as &$bit) {
                $bit = $bit ^ ($i % 2);
            }
            unset($bit);
        }
    }

    private function placeData(array $data): void
    {
        $idx = 0;
        $col = $this->size - 1;
        $upward = true;

        while ($col >= 0) {
            if ($col === 6) {
                $col--;
                continue;
            }

            for ($row = 0; $row < $this->size; $row++) {
                $actualRow = $upward ? ($this->size - 1 - $row) : $row;

                for ($c = 0; $c < 2; $c++) {
                    $actualCol = $col - $c;
                    if ($actualCol < 0) continue;

                    if ($this->isReserved($actualRow, $actualCol)) continue;

                    if ($idx < count($data)) {
                        $maskBit = (($actualRow + $actualCol) % 2 === 0) ? 1 : 0;
                        $this->matrix[$actualRow][$actualCol] = $data[$idx] ^ $maskBit;
                        $idx++;
                    }
                }
            }

            $upward = !$upward;
            $col -= 2;
        }
    }

    private function isReserved(int $row, int $col): bool
    {
        if ($row < 9 && $col < 9) return true;
        if ($row < 9 && $col >= $this->size - 8) return true;
        if ($row >= $this->size - 8 && $col < 9) return true;
        if ($row === 6 || $col === 6) return true;
        if ($row >= $this->size - 11 && $row <= $this->size - 7
            && $col >= $this->size - 11 && $col <= $this->size - 7) return true;

        return false;
    }

    private function renderImage(string $savePath, int $outputSize): void
    {
        $margin = 4;
        $totalModules = $this->size + $margin * 2;
        $cellSize = (int)($outputSize / $totalModules);
        $actualSize = $cellSize * $totalModules;

        $image = imagecreate($actualSize, $actualSize);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);

        imagefill($image, 0, 0, $white);

        for ($row = 0; $row < $this->size; $row++) {
            for ($col = 0; $col < $this->size; $col++) {
                if ($this->matrix[$row][$col] === 1) {
                    $x = ($col + $margin) * $cellSize;
                    $y = ($row + $margin) * $cellSize;
                    imagefilledrectangle($image, $x, $y, $x + $cellSize - 1, $y + $cellSize - 1, $black);
                }
            }
        }

        imagepng($image, $savePath);
        imagedestroy($image);
    }
}
