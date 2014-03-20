<?php

namespace Boepi\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class NotifyCommand extends Command
{
    protected $githubClient;

    public function setGithubClient(\Github\Client $githubClient)
    {
        $this->githubClient = $githubClient;

        return $this;
    }

    protected function configure()
    {
        $this->setName('notify')
            ->setDescription('Notify users that new releases were made.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $releases = $this->githubClient->api('repos')
            ->releases()->all('zacharyrankin', 'sprocketeer');
        $latest_release = current($releases);
        $output->writeln("<info>{$latest_release['name']}</info>");
    }
}
