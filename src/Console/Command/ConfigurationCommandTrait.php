<?php
/**
 * @date 2019-11-28 00:06
 */

namespace Terramar\Packages\Console\Command;

use Symfony\Component\Yaml\Yaml;

/**
 * Trait UpdateYamlTrait
 *
 * @package Terramar\Packages\Console\Command
 * @author
 */
trait ConfigurationCommandTrait
{
    protected function getBlockPattern($block)
    {
        return sprintf('/(^#?%s:.*\n(?:^#?[ ].*\n?)*$)/Dm', $block);
    }

    public function getConfiguration()
    {
        return file_get_contents($this->getConfigurationFilepath());
    }

    protected function hasConfigurationBlock($block)
    {
        return preg_match($this->getBlockPattern($block), $this->getConfiguration());
    }

    protected function updateConfiguration($block, $parameters, $comment = false)
    {
        $blockContent = $parameters === null ? '' : Yaml::dump([$block => $parameters], 2, 2);

        // add '#' at the beginning of each line
        if ($comment) {
            $blockContent = preg_replace('/^/Dm', '#', addcslashes($blockContent, '\\$'));
        }

        $configYamlContent = $this->getConfiguration();
        if ($this->hasConfigurationBlock($block))
        {
            $updatedYaml = preg_replace($this->getBlockPattern($block), addcslashes($blockContent, '\\$'), $configYamlContent);
        }
        elseif ($blockContent)
        {
            $updatedYaml = sprintf("%s\n%s", $configYamlContent, $blockContent);
        }
        else {
            return false;
        }

        file_put_contents($this->getConfigurationFilepath(), $updatedYaml);
        return true;
    }

    protected function checkConfigurationFile()
    {
        $configFile = $this->getConfigurationFilepath();
        if (!file_exists($configFile)) {
            $this->io->warning('Configuration file does not exist.');
            $response = $this->io->ask('Do you want to initialize configuration "config.yml" from "config.yml.dist"', 'y');
            if ('y' !== strtolower($response)) {
                throw new \Exception('Configuration file not found');
            }
            copy($configFile.'.dist', $configFile);
        }
    }

    protected function getConfigurationFilepath()
    {
        $app = $this->container->get('app');
        return $app->getRootDir() . '/config.yml';
    }
}
