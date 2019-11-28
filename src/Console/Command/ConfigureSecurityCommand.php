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
use Symfony\Component\Console\Style\SymfonyStyle;

class ConfigureSecurityCommand extends ContainerAwareCommand
{
    use ConfigurationCommandTrait;

    protected function configure()
    {
        $this->setName('config:security')
            ->setDescription('Configure authentication user and password');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $this->checkConfigurationFile();

        $username = $io->ask('username (leave empty to disable authentication)', ' ');
        if (!trim($username)) {
            $confirmed = $io->confirm(
                'Packages will be available without credentials.',
                false
            );
            if ($confirmed) {
                $this->updateConfiguration('security', [
                    'username' => 'scott',
                    'tiger' => 'ENCODED_PASSWORD'
                ], true);
                $io->success('Configuration file section security updated successfully');

                return 0;
            } else {
                $io->note('Configuration canceled');
                return 1;
            }
        }

        $password = $io->askHidden('type password (hidden) ', function($answer) {
            if (!is_string($answer) || '' === trim($answer)) {
                throw new \RuntimeException('Password cannot be empty');
            }

            return $answer;
        });

        $this->updateConfiguration('security', [
            'username' => $username,
            'password' => password_hash($password, PASSWORD_DEFAULT),
        ]);

        $io->success('Configuration file "security" section updated successfully');
    }
}
