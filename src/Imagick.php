<?php

declare(strict_types=1);

/*
 * This file is part of the sjorek/image package.
 *
 * © Stephan Jorek <stephan.jorek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sjorek\Image;

final class Imagick
{
    public static function compareImagesByPath(string $image1, string $image2, int $metric = \Imagick::METRIC_UNDEFINED): array
    {
        return self::compareImages(new \Imagick($image1), new \Imagick($image2));
    }

    public static function compareImages(\Imagick $image1, \Imagick $image2, int $metric = \Imagick::METRIC_UNDEFINED): array
    {
        $image1Dimensions = [$image1->getImageWidth(), $image1->getImageHeight()];
        $image2Dimensions = [$image2->getImageWidth(), $image2->getImageHeight()];

        if ($image1Dimensions !== $image2Dimensions) {
            throw new \RuntimeException(sprintf('The given images do not have the exact same dimensions: %dx%d vs%dx%d', ...$image1Dimensions, ...$image2Dimensions));
        }

        /* @var \Imagick $image3 */
        return $image1->compareImages($image2, $metric);
    }

    /**
     * @return int[]
     *
     * @see https://www.php.net/manual/en/imagick.constants.php
     */
    public static function getConstants(): array
    {
        $reflectionClass = new \ReflectionClass(\Imagick::class);

        return $reflectionClass->getConstants(\ReflectionClassConstant::IS_PUBLIC);
    }

    /**
     * @return int[]
     *
     * @see https://www.php.net/manual/en/imagick.constants.php#imagick.constants.metric
     */
    public static function getMetrics(): array
    {
        $metrics = array_filter(
            self::getConstants(),
            fn (string $key): bool => str_starts_with($key, 'METRIC_'),
            \ARRAY_FILTER_USE_KEY
        );

        return array_combine(
            array_map(
                fn (string $metric): string => preg_replace(
                    [
                        '/^metric_/',
                        '/metric$/',
                        '/_/',
                    ],
                    [
                        '',
                        '',
                        '-',
                    ],
                    strtolower($metric)
                ),
                array_keys($metrics)
            ),
            array_values($metrics)
        );
    }
}
