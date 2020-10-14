<?php

namespace Psecio\Statext\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

use Psecio\Statext\Collection;
use Psecio\Statext\Item\File;

use Psecio\Statext\Item\File\Output;
use Psecio\Statext\Item\File\Meta;
use Psecio\Statext\Item\File\Display;
use Psecio\Statext\Item\File\Source;

use League\CommonMark\CommonMarkConverter;

class BuildCommand extends Command
{
    protected static $defaultName = 'build';

    protected function configure()
    {
        $this
            ->setDescription('Runs a test')
            ->setHelp('This comamnd just runs a test')

            ->addOption('source', null, InputOption::VALUE_REQUIRED, 'Source directory path')
            ->addOption('target', null, InputOption::VALUE_REQUIRED, 'Target directory path')
            ;
    }

    private function parseMeta($contents)
    {
        $meta = [];
        preg_match('/[\-]{3,}(.*?)[\-]{3,}/s', $contents, $matches);

        if (isset($matches[1])) {
            $parts = explode("\n", trim($matches[1]));
            // now split it based on the ':'
            foreach ($parts as $pindex => $part) {
                $split = explode(':', $part);
                $meta[$split[0]] = trim($split[1]);
            }
        }

        return $meta;
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
        $targetPath = realpath(getcwd().'/'.$input->getOption('target'));
        if ($targetPath == false) {
            $output->writeln('<error>Target path invalid</error>');
            return Command::FAILURE;
        }

        $content = new Collection();

        $directory = new \RecursiveDirectoryIterator($sourcePath.'/content');
        $iterator = new \RecursiveIteratorIterator($directory);

        foreach ($iterator as $info) {
            if (!$info->isFile()) { continue; }

            // Get the contents so we can parse out the front matter
            $meta = [];
            $contents = file_get_contents($info->getPathname());
            $meta = $this->parseMeta($contents);

            // File path for the rendering output
            $outputPath = str_replace([$source, '.md'], [$target, '.html'], $info->getPathname());

            // To get the display path, take the target path and strip off the target directory
            $displayPath = str_replace($targetPath.'/content', '', $outputPath);

            // Everything needs some kind of title
            if (!isset($meta['title'])) {
                $meta['title'] = $displayPath;
            }

            $file = new File([
                'source' => new Source([
                    'path' => $info->getPathname(),
                    'name' => $info->getFilename(),
                ]),
                'output' => new Output([
                    'path' => $outputPath
                ]),
                'display' => new Display([
                    'path' => $displayPath
                ]),
                'meta' => new Meta($meta)
            ]);
            $content->add($file);
        }

        $loader = new \Twig\Loader\FilesystemLoader($sourcePath.'/templates');
        $twig = new \Twig\Environment($loader);

        // Clone the page content collection so any other iteration doesn't impact it
        $twig->addGlobal('pages', clone $content);

        // For each file, translate it using Twig and write it out to the target directory

        $converter = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        foreach ($content as $index => $item) {
            print_r($item);

            $markup = $converter->convertToHtml(file_get_contents($item->getSourcePath()));
            $rendered = $twig->render($item->getTemplate(), [
                'markup' => $markup
            ]);
            echo "writing to: ".$item->getOutputPath()."\n";

            file_put_contents($item->getOutputPath(), $rendered);
        }

        $output->writeln('<fg=green>Rendering complete</>');

        return Command::SUCCESS;
    }
}
