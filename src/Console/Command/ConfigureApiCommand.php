<?php

/*
 * Copyright (c) Terramar Labs
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Terramar\Packages\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

class ConfigureApiCommand extends ContainerAwareCommand
{
    use ConfigurationCommandTrait;

    protected function configure()
    {
        $this->setName('config:api')
            ->setDescription('Configure api security token');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $this->checkConfigurationFile();

        $enable = $io->confirm('Do you want to enable api', true);
        if (!$enable) {
            $this->updateConfiguration('api', ['enabled' => false]);
            return;
        }

        $tokenQuestion = new Question('type token api');
        $tokenQuestion->setHidden(true);
        $tokenQuestion->setValidator(function($answer) {
            if (!is_string($answer) || '' === trim($answer)) {
                throw new \RuntimeException('Password cannot be empty');
            }

            return $answer;
        });
        $token = $io->askQuestion($tokenQuestion);

        $hideSensitive = $io->choice(
            "How do you want to display sensitive data (like remote authentication token) in api response ?\n".
            "This is recommended to choose 'hide' or 'placeholder' to avoid leaking gitlab/github/bitbucket token\n".
            "in case of packages authentication is compromised'\n".
            "Note that you can still update token via api even if you hide/use placeholder for thoses values\n",
            ['hide', 'placeholder', 'none'],
            'hide'
        );

        $this->updateConfiguration('api', [
            'enabled' => true,
            'token' => password_hash($token, PASSWORD_DEFAULT),
            'sensitive_data_strategy' => $hideSensitive
        ]);
        $io->success('Configuration file "api" section updated successfully');
    }
}
