<?php

namespace AppBundle\Command;

use AppBundle\Entity\Queue;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class YoutubeGetCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('youtube:get')
            ->setDescription('fetch youtube video by hash and format')
            ->addArgument(
                'hash',
                InputArgument::REQUIRED,
                'Who do you want to greet?'
            )
            ->addArgument(
                'format',
                InputArgument::REQUIRED,
                'Who do you want to greet?'
            )
        ;
    }

    private function getSpeedLimit()
    {
        $time = new \DateTime();
        $time = (int) $time->format('H');

        if (in_array($time, [18, 19, 20, 21, 22, 23, 0, 1, 2, 3, 4, 5, 6, 7])) {
            return 1800;
        } else {
            return 1200;
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $hash = $input->getArgument('hash');
        $format = $input->getArgument('format');
        $url = 'https://www.youtube.com/watch?v='.$hash;
        $em = $this->getContainer()->get('doctrine.orm.default_entity_manager');
        $proxy = $this->getContainer()->getParameter('yt_proxy');
        $storage = $this->getContainer()->getParameter('storage');

        /** @var Queue $file */
        $file = $em->getRepository('AppBundle:Queue')->getFileForDownload($hash, $format);
        $builder = new ProcessBuilder();
        $builder->setPrefix('youtube-dl');

        $fname = $storage.date('Y-m-d').'_%(title)s_%(format_id)s_%(id)s_%(resolution)s.%(ext)s';
        $args = array('--proxy', $proxy, '--restrict-filenames', '--newline', '-r', $this->getSpeedLimit().'K', '--output', $fname);

        if ($format !== 'mp3') {
            $args[] = '-f';
            $args[] = $format;
        } else {
            $args[] = '--audio-format';
            $args[] = 'mp3';
            $args[] = '--audio-quality';
            $args[] = '0';
            $args[] = '-x';
        }
        $args[] = '--';
        $args[] = $url;
        $process = $builder
            ->setArguments($args)
            ->getProcess();

        $output->writeln($process->getCommandLine());
        $process->setTimeout(3600);
        try {
            $process->run(function ($type, $buffer) use ($file, $em) {
                if (Process::ERR === $type) {
                    echo 'ERR > '.$buffer;
                } else {
                    if (preg_match('/Destination: (.*)/', $buffer, $matches)) {
                        $pieces = explode('/', $matches[1]);
                        $file->setFilename(end($pieces));
                        $em->flush();
                    } elseif (preg_match('/(\d+).\d% of ([0-9.]+)([A-Za-z]+)/', $buffer, $matches)) {
                        $size = $matches[2];
                        if ($matches[3] == 'MiB') {
                            $size *= 1024;
                        } elseif ($matches[3] == 'GiB') {
                            $size *= 1024 * 1024;
                        }
                        $file->setFilesize(round($size, 0));
                        $file->setProgress($matches[1]);
                        $em->flush();
                    }
                    echo 'OUT > '.$buffer;
                }
            });
        } catch (\Exception $e) {
            echo $e;
        }

        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput());
        }

        $file->setProgress(100);
        $file->setFinishedAt(new \DateTime());

        $em->flush();

        $arr = $process->getOutput();
        $output->writeln($arr);
    }
}
