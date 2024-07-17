<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Contracts\Cache\CacheInterface;

#[AsCommand(
    name: 'app:step:info',
    description: 'Get info for current git commit',
)]
class StepInfoCommand extends Command
{

    public function __construct(private readonly CacheInterface $cache,
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $step = $this->cache->get('app.current_step', function ($item) {
            $process = new Process(['git', 'branch', '--show-current']);
            $process->mustRun();
            $branch = trim($process->getOutput());
            if ($branch !== '') {
                $branch .= ' ';
            }
            $process = new Process(['git', 'rev-parse', 'HEAD']);
            $process->mustRun();
            $item->expiresAfter(30);

            return $branch . $process->getOutput();
        });

        $output->write($step);

        return Command::SUCCESS;
    }
}
