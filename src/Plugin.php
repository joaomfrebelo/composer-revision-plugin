<?php

namespace ComposerRevisionPlugin;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Script;
use Composer\Script\ScriptEvents;
use Composer\Util\ProcessExecutor;

class Plugin implements PluginInterface, EventSubscriberInterface
{

    public function preAutoloadDumpEvent(Script\Event $event)
    {
        if ($event->isPropagationStopped()) {
            return;
        }

        $references = array();

        // find current package reference
        $localPackageReference = $this->determineLocalPackageReference();
        if ($localPackageReference !== null) {
            $localPackage                         = $event->getComposer()->getPackage();
            $references[$localPackage->getName()] = $this->equalizeVersionString($localPackage->getVersion());
        }

        // enumerate local repository packages
        foreach ($event->getComposer()->getRepositoryManager()->getLocalRepository()->getCanonicalPackages() as $package) {
            $references[$package->getName()] = $this->equalizeVersionString($package->getVersion());
        }

        $destination = $event->getComposer()->getConfig()->get('vendor-dir')
            .DIRECTORY_SEPARATOR.
            'joaomfrebelo'.DIRECTORY_SEPARATOR.
            'composer-revision-plugin'.DIRECTORY_SEPARATOR.
            'gen'.DIRECTORY_SEPARATOR.
            'revisions.php';

        $classGenerator = new ReferenceClassGenerator($event->getComposer()->getConfig());
        $classGenerator->generate($destination, $references);
    }

    private function determineLocalPackageReference()
    {
        $basePath = realpath(getcwd());

        if (is_dir($basePath.DIRECTORY_SEPARATOR.'.git')) {
            $process = new ProcessExecutor();
            if ($process->execute('git rev-parse HEAD', $output, $basePath) === 0) {
                return trim($output);
            }
        }
        // TODO: add support for other VCS'es

        return null;
    }

    /**
     * @inheritdoc
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $composer->getEventDispatcher()->addSubscriber($this);
    }

    /**
     * @inheritdoc
     */
    public static function getSubscribedEvents()
    {
        return array(
            ScriptEvents::PRE_AUTOLOAD_DUMP => 'preAutoloadDumpEvent'
        );
    }

    public function equalizeVersionString($version)
    {
        $verParts = explode(".", $version);
        array_pop($verParts);
        return join(".", $verParts);
    }
    
    public function uninstall(Composer $composer, IOInterface $io){
        
    }
    
    public function deactivate(Composer $composer, IOInterface $io){
        
    }    
}