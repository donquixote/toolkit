<?php

namespace Phing\Ssk\Tasks;

require_once 'phing/Task.php';

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Output\ConsoleOutput;
use Target;

class PhingHelpTask extends \Task
{
    /**
     * The location of the build file to generate docs for.
     *
     * @var string
     */
    private $buildfile = '';

    /**
     * The location of the build file to generate docs for.
     *
     * @var string
     */
    private $buildlist = '';

    /**
     * An array of help targets that are generated.
     *
     * @var array
     */
    protected $helpTargets = array();


    /**
     *  init this task by creating new instance of the phing task and
     *  configuring it's by calling its own init method.
     */
    public function init()
    {
        if (empty($this->getOwningTarget()->getName())) {
            $project = $this->getProject();
            $project->setDefaultTarget('help');
//            $location = $this->getLocation()->toString();
//            $buildFileRoot = substr($location, 0, strpos($location, ":"));
            $buildFileRoot = $project->getProperty('phing.file');
            $buildList = $this->getBuildList($buildFileRoot);
            $targets = array();

            foreach ($buildList as $buildFile => $info) {
                if (is_file($buildFile)) {
                    $target = new Target();
                    $target->setName('help-' . $info['name']);

                    $task = clone $this;
                    $task->setBuildFile($buildFile);

                    $target->addTask($task);
                    $this->project->addTarget('help-' . $info['name'], $target);

                    $targets[$buildFileRoot][] = array(
                        'name' => $target->getName(),
                        'visibility' => 'hidden',
                        'description' => $buildList[$buildFile]['description'],
                    );
                }
            }
            $this->project->helpTargets = $targets;
        }
    }

    /**
     *  hand off the work to the phing task of ours, after setting it up
     * @throws BuildException on validation failure or if the target didn't
     *  execute.
     */
    public function main()
    {
        $buildFileRoot = $this->getProject()->getProperty('phing.file');
        $buildFile = $this->buildFile;
        $targets = array();
        if (is_file($this->buildFile)) {
            $buildList = $this->getBuildList($this->buildFile);
            if ($buildFile !== $buildFileRoot) {
                $parents = array($this->buildFile);
                foreach ($buildList as $buildFile => $buildInfo) {
                    if ($this->buildFile === $buildFile || in_array($buildInfo['parent'], $parents)) {
                        $parents[] = $buildFile;
                        $targets = array_merge($targets, $this->getBuildTargets($buildFile));
                    }
                }
            }
            else {
                $targets = array_merge_recursive($targets, $this->project->helpTargets);
            }
            $this->printBuildTargets($targets, $buildFile, $buildList);
        }
    }

    protected function printBuildTargets($targets, $buildFile, $buildList) {
        $output = new ConsoleOutput();
        $table = new Table($output);
        $table->setHeaders(array(
          array('Target name', 'Visibility', 'Description')),
          array(new TableCell($buildList[$buildFile]['name'], array('colspan' => 3)))
        );
        foreach ($targets as $file => $targets) {
            $table->addRow(new TableSeparator());
            $table->addRow(array(
              new TableCell($buildList[$file]['name'], array('colspan' => 3))));
            $table->addRow(new TableSeparator());
            $table->addRows($targets);
        }
        $table->render();
    }

    /**
     * Helper function to get the targets out of file.
     *
     * @param string $importFile
     * @return array
     */
    protected function getBuildTargets($importFile) {

        $targets = array();

        // Replace tokens.
        if (preg_match_all('/\$\{(.*?)\}/s', $importFile, $matches)) {
            foreach ($matches[0] as $key => $match) {
                $tokenText = $this->getProject()->getProperty($matches[1][$key]);
                $importFile = str_replace($match, $tokenText, $importFile);
            }
        }
        $importFileXml = simplexml_load_file($importFile);

        foreach ($importFileXml->xpath('//target') as $target) {
            $targetName = (string)$target->attributes()->name;
            $targetVisibility = (string)$target->attributes()->hidden == 'true' ? 'hidden' : 'visible';
            $targetDescription = (string)$target->attributes()->description;
            $targets[$importFile][] = array(
              'name' => $targetName,
              'visibility' => $targetVisibility,
              'description' => $targetDescription,
            );
        }

        return $targets;
    }

    /**
     * Helper function to get the full list of buildfiles through imports.
     *
     * @param string $buildFile
     * @param int $level
     * @param string $parent
     * @param array $buildList
     * @return array
     */
    public function getBuildList($buildFile, $level = 0, $parent = '', &$buildList = array()) {

        if (($buildFileXml = simplexml_load_file($buildFile))
          && ($buildFileName = $buildFileXml->xpath('//project/@name')[0])) {
            $buildList[$buildFile] = array(
              'level' => $level,
              'parent' => $parent,
              'name' => (string) $buildFileName,
              'description' => (string) $buildFileXml->xpath('//project/@description')[0],
            );

            foreach ($buildFileXml->xpath('//import[@file]') as $import) {
                $importFile = (string)$import->attributes()->file;

                // Replace tokens.
                if (preg_match_all('/\$\{(.*?)\}/s', $importFile, $matches)) {
                    foreach ($matches[0] as $key => $match) {
                        $tokenText = $this->getProject()->getProperty($matches[1][$key]);
                        $importFile = str_replace($match, $tokenText, $importFile);
                    }
                }

                $this->getBuildList($importFile, $level + 1, $buildFile, $buildList);
            }
        }
        return $buildList;
    }

    /**
     * Sets the Phing file for which to generate help commands.
     *
     * @param string $buildfile
     *   The Phing file for which to generate help commands.
     */
    public function setBuildFile($buildfile) {
        $this->buildFile = $buildfile;
    }

    /**
     * Sets the build list for which we can ask for help.
     *
     * @param string $buildlist
     *   The build list for which we can ask for help.
     */
    public function setBuildList($buildlist) {
        $this->buildList = $buildlist;
    }

    /**
     * Sets the help targets for the project.
     *
     * @param array $helptargets
     *   The help targets for the project.
     */
    public function setHelpTargets($helpTargets) {
        $this->helpTargets = $helpTargets;
    }

    /**
     * Gets the help targets for the project.
     */
    public function getHelpTargets() {
        return $this->helpTargets;
    }
}