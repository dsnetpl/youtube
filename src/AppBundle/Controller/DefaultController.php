<?php

namespace AppBundle\Controller;

use AppBundle\Entity\DownloadUsage;
use AppBundle\Entity\Media;
use AppBundle\Entity\Queue;
use Buzz\Exception\RequestException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\ProcessBuilder;
use AppBundle\TaskDescription\YoutubeQueueTaskDescription;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class DefaultController extends Controller
{
    public function getUser()
    {
        if (!$this->container->has('security.token_storage')) {
            throw new \LogicException('The SecurityBundle is not registered in your application.');
        }

        if (null === $token = $this->container->get('security.token_storage')->getToken()) {
            return;
        }

        if (!is_numeric($user = $token->getUser())) {
            // e.g. anonymous authentication
            return;
        }

        return $user;
    }

    private function yt_dl($arguments)
    {
        $builder = new ProcessBuilder();
        $builder->setPrefix('youtube-dl');
        $builder->setArguments($arguments);

        return $builder->getProcess();
    }

    /**
     * @Route("/", name="homepage")
     * @Template
     */
    public function indexAction(Request $request)
    {
        $form = $this->createFormBuilder()
            ->add('address', 'url', array('label' => 'Wprowadź poniżej link do filmu na youtube:', 'attr' => ['placeholder' => 'https://www.youtube.com/watch?v=...'], 'constraints' => [new Length(['max' => 100])]))
            ->add('Dalej', 'submit', array('attr' => array('class' => 'btn btn-lg btn-primary btn-block')))
            ->getForm();

        $form->handleRequest($request);

        if ($form->isValid()) {
            $data = $form->getData();
            $address = $data['address'];

            $process = $this->yt_dl(array('-s', '--get-id', '--', $address));
            $process->run();
            $id = trim($process->getOutput());

            return $this->redirect($this->generateUrl('yt_info', array('hash' => $id, 'new' => 1)));
        }

        return array('form' => $form->createView());
    }

    /**
     * @Route("/youtube/{hash}/{new}", name="yt_info")
     * @Template
     */
    public function youtubeInfoAction($hash, $new = null)
    {
        /** @var Media $media */
        $media = $this->getDoctrine()->getRepository('AppBundle:Media')->findOneByHash($hash);
        $em = $this->getDoctrine()->getManager();
        if (!$media) {
            $media = new Media();
            $media->setHash($hash);
            $media->setCreatedBy($this->getUser());
            $em->persist($media);
        }

        if (!$media->getRefreshedAt() || $media->getRefreshedAt() < new \DateTime('-7 days')) {
            $process = $this->yt_dl(array('--youtube-skip-dash-manifest', '-s', '-r', '1M', '-j', '--', $hash));
//         $process = $this->yt_dl(array('-s','-r', '1M', '-j', '-f', '[tbr>200]', $hash));
            $process->run();
            $media->setJson($process->getOutput());
            if (!$media->getJson()) {
                $this->addFlash('warn', 'Nieprawidłowy link');

                return $this->redirectToRoute('homepage');
            }
            $media->setRefreshedAt(new \DateTime());
            $media->setPopularity($media->getPopularity() + 1);
            $em->flush();
        } elseif ($new) {
            $em->getRepository('AppBundle:Media')->increasePopularity($media);
        }
        $arr = $media->getJson();
        if (!$arr || strlen($hash) > 20) {
            $this->addFlash('notice', 'Niepoprawny link do video / problem z video');

            return $this->redirectToRoute('homepage');
        }
        $arr = json_decode($arr);
        $ret['title'] = $arr->title;
        $ret['id'] = $hash;
        $ret['duration'] = $arr->duration;
        $choices = array();
        foreach ($arr->formats as $format) {
            //             if (isset($format->filesize)) {
//             if (isset($format->preference) && $format->preference < 0) continue;
            $choices[$format->format_id] = $format;
            $choices[$format->format_id]->preferred = $format->format_id == $arr->format_id;
            $format->name = '';
            if (property_exists($format, 'width') && $format->width) {
                $format->name .= $format->width.'x'.$format->height;
            }
            if (!property_exists($format, 'size') || !$format->size) {
                if ($format->format_id == 22) {
                    $format->size = '~'.round(350 * $arr->duration / 1024, 1).' MB';
                } else {
                    $format->size = '~'.round(75 * $arr->duration / 1024, 1).' MB';
                }
            }
            $format->name .= '.'.$format->ext;
//             }
        }

        $choices['mp3'] = new \stdClass();
        $choices['mp3']->ext = 'mp3';
        $choices['mp3']->format = 'mp3';
        $choices['mp3']->preferred = false;
        $choices['mp3']->format_id = 'mp3';
        $choices['mp3']->size = 0;
        $ret['choices'] = $choices;
        $ret['description'] = $arr->description;

        $ret['files'] = array();

        $files = $em->getRepository('AppBundle:Queue')->getFilesForHash($hash);
        foreach ($files as $file) {
            $ret['files'][$file->getFormat()] = $file;
        }

        return $ret;
    }

    /**
     * @Route("/login", name="login")
     */
    public function loginAction()
    {
        return $this->redirect('/');
    }

    /**
     * @Route("/youtubeQueue/{hash}/{format}", name="yt_queue")
     */
    public function youtubeQueueAction($hash, $format)
    {
        $loss = $this->getPacketLoss();
        if ($loss > 1) {
            $this->addFlash('warn', 'Łącze jest zbyt obciążone, spróbuj ponownie później');

            return $this->redirect($this->generateUrl('yt_info', array('hash' => $hash, 'loss' => $loss)));
        }

        $usage = $this->getDoctrine()->getRepository('AppBundle:DownloadUsage')->getUsageInLastWeek($this->getUser());

        $download_limit = $this->getParameter('download_limit');
        if ($usage > $download_limit) {
            $this->addFlash('warn', sprintf('Przekroczono tygodniowy limit %sMB, nie możesz dodawać w tym momencie do kolejki', $download_limit));

            return $this->redirectToRoute('yt_info', ['hash' => $hash]);
        }

        $download_concurrent = $this->getParameter('download_concurrent');
        if ($this->getDoctrine()->getRepository('AppBundle:Queue')->findFilesInQueueForUserCount($this->getUser()) > $download_concurrent - 1) {
            $this->addFlash('warn', sprintf('W kolejce nie może być więcej niż %s plików jednocześnie od tego samego użytkownika', $download_concurrent));

            return $this->redirectToRoute('yt_info', ['hash' => $hash]);
        }

        $task = new YoutubeQueueTaskDescription();
        $task->hash = $hash;
        $task->format = $format;

        $media = $this->getDoctrine()->getRepository('AppBundle:Media')->findOneByHash($hash);

        $info = json_decode($media->getJson());
        if (!$info) {
            $this->addFlash('notice', 'Problem z video');

            return $this->redirect('/');
        }
        $queue = new Queue();
        $queue->setCreatedBy($this->getUser());
        $queue->setHash($hash);
        $queue->setFilename($info->_filename);
        $downloadUsage = new DownloadUsage();
        $downloadUsage->setMedia($this->getDoctrine()->getRepository('AppBundle:Media')->findOneBy(['hash' => $hash]));
        $downloadUsage->setUserId($this->getUser());
        $download_duration = $this->getParameter('download_duration');
        if (($info->duration / 60) > $download_duration) {
            $this->addFlash('warn', sprintf('Nie można pobierać plików trwających ponad %s minut', $download_duration));

            return $this->redirectToRoute('yt_info', ['hash' => $hash]);
        }
        if ($format == 18) {
            $queue->setFilesize(75 * $info->duration);
            $downloadUsage->setFilesize($queue->getFilesize());
            $queue->setHeight(360);
            $queue->setWidth(640);
        } elseif ($format == 22) {
            $queue->setFilesize(350 * $info->duration);
            $downloadUsage->setFilesize($queue->getFilesize());
            $queue->setHeight(720);
            $queue->setWidth(1280);
        } else {
            $queue->setFilesize(0);
            $downloadUsage->setFilesize(75 * $info->duration);
            $queue->setWidth(0);
            $queue->setHeight(0);
        }
        $downloadUsage->setFilesize(round($downloadUsage->getFilesize() / 1024));
        $queue->setLength($info->duration);
        $queue->setFormat($format);
        $queue->setProgress(0);
        $queue->setTitle($info->title);
        $queue->setDownloads(0);
        $queue->setCreatedBy($this->getUser());
        $this->getDoctrine()->getManager()->persist($downloadUsage);
        $this->getDoctrine()->getManager()->persist($queue);
        $this->getDoctrine()->getManager()->flush();
//        $qid = $this->get('webdevvie_pheanstalktaskqueue.service')->queueTask($task);

        $this->addFlash('notice', 'Dodano plik do kolejki. Sprawdź za około minutę pobrane pliki.');

        return $this->redirect($this->generateUrl('yt_info', array('hash' => $hash)));
    }

    /**
     * @Route("/list/{page}", name="list_files", requirements={"page": "\d+"})
     * @Template()
     */
    public function listFilesAction($page = 1)
    {
        $sql = 'select sum(filesize/1024) from queue where finished_at is not null;';
        $stm = $this->getDoctrine()->getManager()->getConnection()->executeQuery($sql);
        $downloaded = $stm->fetch(\PDO::FETCH_COLUMN);

        $limit = 100;
        $allfiles = $this->getDoctrine()->getManager()->getRepository('AppBundle:Queue')->getFilesDownloadedQuery(1, null);
        $maxPages = ceil(count($allfiles) / $limit);

        return $this->render(
            'AppBundle:Default:listFiles.html.twig',
            array('downloaded' => round($downloaded / 1024, 1),
                'page' => $page,
                'maxPages' => $maxPages,
            )
        );
    }

    /**
     * @Template()
     * @Route("/filelist.part", name="_filelist")
     */
    public function _filelistAction(Request $request, $page = 1)
    {
        $query = $request->query->get('query');
        if ($request->isXmlHttpRequest()) {
            $offset = $request->query->get('offset');
            $limit = $request->query->get('limit');
            $files = $this->getDoctrine()->getManager()->getRepository('AppBundle:Queue')->getFilesDownloadedSearch($query, $offset, $limit);
        } else {
            $files = $this->getDoctrine()->getManager()->getRepository('AppBundle:Queue')->getFilesDownloadedQuery($page);
        }

        return array('files' => $files);
    }

    /**
     * @Route("/queueList", name="list_queue")
     * @Template()
     */
    public function listQueueAction()
    {
        $files = $this->getDoctrine()->getManager()->getRepository('AppBundle:Queue')->getFilesForDownload();

        return array(
            'files' => $files,
        );
    }

    /**
     * @Route("/get/{id}", name="download_file", requirements={"id":"\d+"})
     */
    public function getFileAction(Queue $file)
    {
        $this->getDoctrine()->getRepository('AppBundle:Queue')->increaseDownloads($file);

        return $this->redirect('/download/'.$file->getFilename());
    }

    /**
     * @Route("/watch/{id}", name="watch_video", requirements={"id": "\d+"})
     * @Template()
     */
    public function watchVideoAction(Queue $file)
    {
        $this->getDoctrine()->getRepository('AppBundle:Queue')->increaseDownloads($file);

        return array(
            'file' => $file,
        );
    }

    /**
     * @Route("/contact", name="contact")
     * @Template()
     */
    public function contactAction(Request $request)
    {
        /** @var Form $form */
        $form = $this->createFormBuilder()
            ->add('message', 'textarea', array(
                'label' => 'Treść',
                'required' => true,
                'constraints' => array(
                    new NotBlank(),
                ),
            ))
            ->add('submit', 'submit', array(
                'attr' => array(
                    'class' => 'btn btn-sm btn-success btn-block',
                ),
                'label' => 'Wyślij wiadomość',
            ))
            ->setAction($this->generateUrl('contact'))
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $data = $form->getData();
                $message = \Swift_Message::newInstance();
                $message->setSubject('Youtube - feedback');
                $message->setBody(sprintf("IP: %s\ntenant_id: %s\nWiadomość: %s", $request->getClientIp(), $this->getUser(), $data['message']));
                $message->setFrom($this->getParameter('contact.from'));
                $message->setTo($this->getParameter('contact.to'));
                $this->get('mailer')->send($message);
                $this->addFlash('success', 'Wiadomość została wysłana');
            }
            if (!$request->isXmlHttpRequest()) {
                $referer = $request->headers->get('HTTP_REFERER');
                if ($referer) {
                    return $this->redirect($referer);
                } else {
                    return $this->redirect('/');
                }
            }
        }

        return array(
            'form' => $form->createView(),
        );
    }

    // TODO do serwisu
    private function getPacketLoss()
    {
        $key = 'network_packet_loss';
        $cache = $this->get('doctrine_cache.providers.generic');
        $loss = 0;
        if ($cache->contains($key)) {
            $loss = $cache->fetch($key);
        } else {
            $res = null;
            try {
                $res = $this->get('buzz')->get($this->getParameter('loss_provider'));
            } catch (RequestException $e) {
            }
            if ($res) {
                $loss = $res->getContent();
            }
            $cache->save($key, $loss, 60);
        }

        return $loss;
    }

    /**
     * @Route("/packetLoss", name="dsnet_network_packet_loss")
     */
    public function packetLossAction()
    {
        return new Response($this->getPacketLoss());
    }
}
