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

namespace Sjorek\Image\Console\Command;

use Sjorek\Image\Imagick;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CompareCommand extends Command
{
    protected SymfonyStyle $stdout;
    protected SymfonyStyle $stderr;

    protected function configure()
    {
        $this
            ->setName('compare')
            ->setDescription('Compare two images')
            ->addArgument(
                'image1',
                InputArgument::REQUIRED,
                'The first image'
            )
            ->addArgument(
                'image2',
                InputArgument::REQUIRED,
                'The second image'
            )
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_REQUIRED,
                'Write difference image to given path'
            )
            ->addOption(
                'metric',
                'm',
                InputOption::VALUE_REQUIRED,
                'Override default comparison metric. Available metrics: ' . \PHP_EOL . ' • ' .
                implode(\PHP_EOL . ' • ', array_keys(Imagick::getMetrics())) . \PHP_EOL,
                'undefined',
                $this->suggestComparisonMetrics()
            )
            ->addOption(
                'threshold',
                't',
                InputOption::VALUE_REQUIRED,
                'Override difference threshold. Values below threshold are considered as identical.',
                '0.0',
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->stdout = new SymfonyStyle($input, $output);
        $this->stderr = new SymfonyStyle($input, $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output);

        $image1 = $input->getArgument('image1');
        if (!file_exists($image1)) {
            $this->stderr->error('The first given image does not exist: ' . $image1);

            return self::INVALID;
        }

        $image2 = $input->getArgument('image2');
        if (!file_exists($image2)) {
            $this->stderr->error('The second given image does not exist: ' . $image2);

            return self::INVALID;
        }

        $output = $input->getOption('output');
        if (null !== $output && file_exists($output)) {
            $this->stderr->error('The given output image already exists: ' . $output);

            return self::INVALID;
        }

        $metric = Imagick::getMetrics()[$input->getOption('metric')] ?? null;
        if (null === $metric) {
            $this->stderr->error('The given metric does not exist: ' . $input->getOption('metric'));

            return self::INVALID;
        }

        $threshold = filter_var(
            $input->getOption('threshold'),
            \FILTER_VALIDATE_FLOAT,
            [
                'flags' => \FILTER_NULL_ON_FAILURE,
                'options' => [
                    'decimal' => 3,
                    'min_range' => 0,
                    'max_range' => 1,
                ],
            ]
        );
        if (null === $threshold) {
            $this->stderr->error('Invalid threshold given: ' . $input->getOption('threshold'));

            return self::INVALID;
        }

        if ($this->stdout->isDebug()) {
            $this->stdout->horizontalTable(
                ['image1', 'image2', 'output', 'metric', 'threshold'],
                [[$image1, $image2, $output, $input->getOption('metric'), $threshold]]
            );
        }

        try {
            /** @var \Imagick $image3 */
            [$image3, $difference] = Imagick::compareImagesByPath($image1, $image2, $metric);

            if ($this->stdout->isVerbose()) {
                $this->stdout->info('Image difference: ' . $difference);
            }

            if ($output && !$image3->writeImage($output)) {
                $this->stderr->error('Failed to write given output image: ' . $input->getOption('output'));

                return self::INVALID;
            }
        } catch (\Throwable $t) {
            $messages = [$t->getMessage()];
            if ($this->stdout->isVerbose()) {
                $messages[] = $t->getTraceAsString();
            }
            $this->stderr->error($messages);

            return self::INVALID;
        }

        return $difference <= $threshold ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Suggest comparison metrics.
     */
    protected function suggestComparisonMetrics(): \Closure
    {
        return function (CompletionInput $input): array {
            $completionValue = $input->getCompletionValue();
            $metrics = array_keys(Imagick::getMetrics());
            if ('' !== $completionValue) {
                $metrics = array_filter(
                    $metrics,
                    fn (string $metric): bool => str_starts_with($metric, strtolower($completionValue))
                );
            }
            sort($metrics);

            return $metrics;
        };
    }
}
