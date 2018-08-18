<?php

namespace AppBundle\Command;

use AppBundle\Entity\Queue;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RemoveCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('youtube:remove')
            ->setDescription('remove old files')
            ->addArgument(
                'number',
                InputArgument::REQUIRED,
                'number of files'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $number = $input->getArgument('number');
        $em = $this->getContainer()->get('doctrine.orm.default_entity_manager');

        $files = $em->getRepository('AppBundle:Queue')->findFilesToRemove($number);
        $storage = $this->getContainer()->getParameter('storage');

        foreach ($files as $file) {
            /* @var Queue $file */
            $file->setDeletedAt(new \DateTime());
            @unlink($storage.$file->getFilename());
            $em->persist($file);
            echo $file."\n";
        }

        $em->flush();
    }
}
