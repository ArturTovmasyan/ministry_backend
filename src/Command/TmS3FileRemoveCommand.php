<?php

namespace App\Command;

use App\Services\AwsService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class TmS3FileRemoveCommand
 * @package App\Command
 */
class TmS3FileRemoveCommand extends Command
{
    protected static $defaultName = 'tm:s3-file:remove';

    protected function configure():void
    {
        $this
            ->setDescription('Remove file async from AWS S3.')
            ->addArgument('files', InputArgument::REQUIRED, 'Pass removed files path');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        // get cli argument
        $data = $input->getArgument('files');
        $data = json_decode($data, true);

        $awsService = new AwsService();

        try {
            $awsService->removeFileByPath($data);
            $io->success('');
        } catch (\Exception $e) {
        }
    }
}
