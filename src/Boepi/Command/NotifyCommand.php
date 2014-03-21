<?php

namespace Boepi\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class NotifyCommand extends Command
{
    protected $githubClient;
    protected $githubToken;
    protected $mailgun;

    public function setGithubClient(\Github\Client $githubClient)
    {
        $this->githubClient = $githubClient;

        return $this;
    }

    public function setGithubToken($githubToken)
    {
        $this->githubToken = $githubToken;

        return $this;
    }

    public function setMailgun(\Mailgun\Mailgun $mailgun)
    {
        $this->mailgun = $mailgun;

        return $this;
    }

    protected function configure()
    {
        $this->setName('notify')
            ->setDescription('Notify users that new releases were made.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->githubClient->authenticate($this->githubToken, 'x-oauth-basic', \Github\Client::AUTH_HTTP_PASSWORD);

        $page = 1;
        $allStarred = array();
        $hasNextPage = true;
        while ($hasNextPage) {
            $allStarred = array_merge($allStarred, $this->githubClient->api('users')->starred('zacharyrankin', $page));
            $hasNextPage = $this->githubClient->getHttpClient()->getLastResponse()
                ->getHeader('link')->getLink('next');
            $page++;
        }

        $newReleases = array();
        foreach ($allStarred as $starred) {
            $releases = $this->githubClient->api('repos')
                ->releases()->all($starred['owner']['login'], $starred['name']);
            $latestRelease = current($releases);
            $publishedAt = strtotime($latestRelease['published_at']);
            $releasedWithinTheLastDay = ($publishedAt > strtotime('-1 day'));

            if ($releasedWithinTheLastDay) {
                $newReleases[] = "**{$starred['full_name']}** released"
                    . " {$latestRelease['tag_name']} \"{$latestRelease['name']}\""
                    . "\nGet it here: {$latestRelease['html_url']}";
            }
        }

        if (!$newReleases) {
            return;
        }

        $this->mailgun->sendMessage('theweekendprogrammer.mailgun.org', [
            'from'    => 'rankin.zachary@gmail.com',
            'to'      => 'rankin.zachary@gmail.com',
            'subject' => "Oh my Boepi's!",
            'text'    => "Hello!\n\nThere have been some new releases in the heavens:\n\n"
                . implode("\n\n---\n\n", $newReleases)
                . "\n\nKeep your eyes on the Stars.\n\n---\nBoepi",
        ]);
    }
}
