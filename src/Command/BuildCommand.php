<?php

namespace Psecio\Statext\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

use Psecio\Statext\Collection;
use Psecio\Statext\Item\File;
use Psecio\Statext\Item\Directory;

use Psecio\Statext\Item\File\Output;
use Psecio\Statext\Item\File\Meta;
use Psecio\Statext\Item\File\Display;
use Psecio\Statext\Item\File\Source;
use Psecio\Statext\Config;

use League\CommonMark\CommonMarkConverter;
use Symfony\Component\Yaml\Yaml;
use Sabre\VObject;

class BuildCommand extends Command
{
    protected static $defaultName = 'build';
    protected $defaultLayout = 'layout.twig';

    protected $staticDirs = ['assets'];

    protected function configure()
    {
        $this
            ->setDescription('Runs the site build process')
            ->setHelp('This command runs the full build and output process of the static files')

            ->addOption('source', null, InputOption::VALUE_REQUIRED, 'Source directory path')
            ->addOption('target', null, InputOption::VALUE_REQUIRED, 'Target directory path')
            ;
    }

    private function loadConfig($sourcePath)
    {
        // Look for the "config.yml" file. If not there, don't load the config
        $configPath = realpath($sourcePath.'/config.yml');
        if ($configPath == false) {
            return false;
        }

        $config = Yaml::parse(file_get_contents($configPath));
        $this->config = new Config($config);
        return $config;
    }

    private function parseMeta(&$contents)
    {
        $meta = [];
        
        preg_match('/[\-]{3,}(.*?)[\-]{3,}/s', $contents, $matches);
        if (isset($matches[0])) {
            // Remove the front matter from the content
            $contents = str_replace($matches[0], '', $contents);
        }

        if (isset($matches[1])) {
            $meta = Yaml::parse($matches[1]);
        }

        return $meta;
    }

    private function parseSourceContent($path, $source, $target)
    {
        $new = new Collection();

        $targetPath = realpath(getcwd().'/'.$target);
        if ($targetPath == false) {
            throw new \Exception('Target path is invalid: '.$targetPath);
        }

        foreach (new \DirectoryIterator($path) as $info) {
            if ($info->isDot()) { continue; }

            if ($info->getType() == 'file') {
                $outputType = Output::TYPE_STATIC;
                $meta = [];
                $pathname = $info->getPathname();
                $contents = file_get_contents($info->getPathname());
                $name = '';

                // If the pathname doesn't end in .md, let it get copied over
                $ext = substr($pathname, strrpos($pathname, '.'), strlen($pathname));
                if (strtolower($ext) == '.md') {
                    $outputType = Output::TYPE_SOURCE;
                    $meta = $this->parseMeta($contents);

                    $name = str_replace('.md', '', $info->getFilename());
                }

                // File path for the rendering output
                $outputPath = str_replace([$source, '.md'], [$target, '.html'], $info->getPathname());

                // To get the display path, take the target path and strip off the target directory
                $displayPath = str_replace($targetPath.'/content', '', $outputPath);

                // Everything needs some kind of title
                if (!isset($meta['title'])) {
                    $meta['title'] = $displayPath;
                }

                $layout = $this->config->getOption('layout');
                if ($layout == null) {
                    $layout = 'layout.twig';
                }

                // Check the meta and see if there's a different layout
                if  (isset($meta['layout'])) {
                    $layout = $meta['layout'];

                    // Make sure it ends in .twig
                    if (substr($layout, -4) !== 'twig') {
                        $layout = $layout.'.twig';
                    }
                }

                $output = [
                    'path' => $outputPath,
                    'type' => $outputType,
                    'layout' => $layout,
                    'name' => $name
                ];
                if (isset($meta['icon'])) {
                    $output['icon'] = $meta['icon'];
                }

                $file = new File([
                    'source' => new Source([
                        'path' => $info->getPathname(),
                        'name' => $info->getFilename(),
                        'contents' => $contents
                    ]),
                    'output' => new Output($output),
                    'display' => new Display([
                        'path' => $displayPath
                    ]),
                    'meta' => new Meta($meta)
                ]);
                $new->add($file);

            } elseif ($info->getType() == 'dir') {
                $dir = new Directory([
                    'path' => $info->getPathname(),
                    'shortPath' => $info->getBaseName(),
                    'meta' => new Meta([
                        'title' => $info->getBasename()
                    ]),
                    'output' => new Output([
                        'path' => str_replace($source, $target, $info->getPathname()),
                        'type' => (in_array(strtolower($info->getBasename()), $this->staticDirs)) ? Output::TYPE_STATIC : Output::TYPE_SOURCE,
                    ])
                ]);
                $dir->setChildren($this->parseSourceContent($info->getPathname(), $source, $target));
                $new->add($dir);
            }
        }
        return $new;
    }

    private function renderContent($content, $twig, $converter)
    {
        // We have to clone content because we do iteration elsewhere
        $content = clone $content;

        foreach ($content as $index => $item) {
            if ($item instanceof \Psecio\Statext\Item\File) {
                if ($item->getOutputType() == Output::TYPE_SOURCE) {
                    $markup = $converter->convertToHtml($item->getContents());

                    $output = $twig->render($item->getLayout(), [
                        'markup' => $markup,
                        'item' => $item
                    ]);
                } elseif ($item->getOutputType() == Output::TYPE_STATIC) {
                    $output = $item->getContents();
                }

                file_put_contents($item->getOutputPath(), $output);

            } elseif ($item instanceof \Psecio\Statext\Collection) {
                $this->renderContent($item, $twig, $converter);

            } elseif ($item instanceof \Psecio\Statext\Item\Directory) {
                // Make sure the path exists - if not, make it
                if (is_dir($item->getOutputPath()) == false) {
                    mkdir($item->getOutputPath());
                }
                $this->renderContent($item->getChildren(), $twig, $converter);

            }
        }
    }

    protected function parseEvents($path)
    {
        $events = [];
        $path = realpath($path);
        if ($path === false) {
            return $events;
        }

        $vcalendar = VObject\Reader::read(fopen($path,'r'));
        foreach ($vcalendar->VEVENT as $event) {
            $events[] = [
                'summary' => $event->SUMMARY->getValue(),
                'description' => ($event->DESCRIPTION !== null) ? $event->DESCRIPTION->getValue() : '',
                'startDate' => new \DateTime($event->DTSTART->getValue()),
                'endDate'  => new \DateTime($event->DTEND->getValue())
            ];
        }

        return $events;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $source = $input->getOption('source');
        if ($source == null) {
            throw new \Exception();
            $output->writeln('<error>Source path is required (use --source)</error>');
            return Command::FAILURE;
        }
        $sourcePath = realpath(getcwd().'/'.$source);
        if ($sourcePath == false) {
            $output->writeln('<error>Source path invalid</error>'); 
            return Command::FAILURE;
        }

        $target = $input->getOption('target');
        if ($target == null) {
            $output->writeln('<error>Target path is required (use --target)</error>');
            return Command::FAILURE;
        }

        $this->loadConfig($sourcePath);

        $events = $this->parseEvents($sourcePath.'/content/events.ics');
        if (count($events) > 0) {
            $output->writeln('<fg=green>'.count($events).' events found</>');
        }

        // Before we write out the content, first clear out the target directory and remake the content path
        exec('rm -rf '.realpath(getcwd().'/'.$target.'/content'));
        mkdir(realpath(getcwd().'/'.$target).'/content');

        try {
            $content = $this->parseSourceContent($sourcePath.'/content', $source, $target);
            $loader = new \Twig\Loader\FilesystemLoader($sourcePath.'/templates');
            $twig = new \Twig\Environment($loader);

            // Clone the page content collection and events so any other iteration doesn't impact it
            $twig->addGlobal('pages', clone $content);
            $twig->addGlobal('events', $events);

            $function = new \Twig\TwigFunction('title_format', function ($title) {
                $title = str_replace('_', ' ', $title);
                return ucwords($title);
            });
            $twig->addFunction($function);

            $function = new \Twig\TwigFunction('findDir', function ($name) use ($content) {
                $found = null;
                $loop = function($content, $name, &$found) use (&$loop) {
                    foreach ($content as $c) {
                        if ($c->type == 'directory') {
                            // Check the name
                            if ($c->shortPath == $name) {
                                $found = $c;
                            } else {
                                // Recurse through the children
                                $loop($c, $name, $found);
                            }
                        }
                    }
                };
                $loop($content, $name, $found);
                return $found;
            });
            $twig->addFunction($function);

            $function = new \Twig\TwigFunction('findFile', function ($name) use ($content) {
                $found = null;
                $loop = function($content, $name, &$found) use (&$loop) {
                    foreach ($content as $c) {
                        if ($found == null && $c->type == 'file') {
                            if (strpos($c->getSourcePath(), $name) !== false) {
                                $found = $c;
                            }
                        } elseif ($found == null && $c->type == 'directory') {
                            $loop($c->getChildren(), $name, $found);
                        }
                    }
                };
                $loop($content, $name, $found);
                return $found;
            });
            $twig->addFunction($function);

            // For each file, translate it using Twig and write it out to the target directory
            $converter = new CommonMarkConverter([
                'allow_unsafe_links' => false
            ]);

            $this->renderContent($content, $twig, $converter);

            $output->writeln('<fg=green>Rendering complete</>');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $output->writeln('<error>'.$e->getMessage().'</error>');
            return Command::FAILURE;
        }
    }
}
