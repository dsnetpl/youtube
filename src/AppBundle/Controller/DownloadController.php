<?php

namespace AppBundle\Controller;

use AppBundle\Entity\DownloadUsage;
use AppBundle\Entity\Media;
use AppBundle\Entity\Queue;
use AppBundle\Form\VimeoType;
use AppBundle\Form\YoutubeType;
use AppBundle\Repository\DownloadUsageRepository;
use AppBundle\Repository\MediaRepository;
use AppBundle\Repository\QueueRepository;
use AppBundle\Services\DownloadService;
use Doctrine\ORM\EntityManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use AppBundle\TaskDescription\YoutubeQueueTaskDescription;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class DownloadController extends Controller
{
    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var MediaRepository
     */
    protected $media;

    /**
     * @var QueueRepository
     */
    protected $queue;

    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;

    /**
     * @var DownloadUsageRepository
     */
    protected $downloadUsage;

    /**
     * @var DownloadService
     */
    protected $downloadService;

    public function __construct(
        EntityManager $entityManager,
        MediaRepository $media,
        QueueRepository $queue,
        TokenStorageInterface $tokenStorage,
        DownloadUsageRepository $downloadUsage,
        DownloadService $downloadService
    )
    {
        $this->entityManager = $entityManager;
        $this->media = $media;
        $this->queue = $queue;
        $this->tokenStorage = $tokenStorage;
        $this->downloadUsage = $downloadUsage;
        $this->downloadService = $downloadService;
    }

    private function processForm($form, string $action, string $route_info, $site, Request $request)
    {
        $form = $this->createForm($form, null, [
            'action' => $this->generateUrl($action)
        ]);

        $form->handleRequest($request);

        if ($form->isValid()) {
            $data = $form->getData();
            $address = $data['address'];
            $categories = $data['category'];

            $process = $this->downloadService->yt_dl(array('--get-id', '--', $address));
            $process->run();
            $id = trim($process->getOutput());

            $this->checkMediaExist($id, $categories, $site);

            if(!$id){
                $this->addFlash("warn", "Nie udało się przetworzyć pliku");
                return $this->redirectToRoute('homepage');
            }

            return $this->redirect($this->generateUrl($route_info, array('hash' => $id, 'new' => 1)));
        }

        return [
            'form' => $form->createView(),
        ];
    }

    /**
     * @Route("vimeo/process", name="vimeo_form_process")
     * @Template()
     */
    public function processVimeoFormAction(Request $request)
    {
        return $this->processForm(VimeoType::class, 'vimeo_form_process', 'vimeo_info', 'vimeo', $request);
    }

    /**
     * @Route("youtube/process", name="youtube_form_process")
     * @Template()
     */
    public function processYoutubeFormAction(Request $request)
    {
        return $this->processForm(YoutubeType::class, 'youtube_form_process', 'yt_info', 'youtube', $request);
    }

    /**
     * @Route("/vimeo/{hash}/{new}", name="vimeo_info")
     * @Template
     */
    public function infoVimeoAction($hash, $new = null)
    {
        return $this->info('vimeo', $hash, $new);
    }

    /**
     * @Route("/youtube/{hash}/{new}", name="yt_info")
     * @Template
     */
    public function infoYoutubeAction($hash, $new = null)
    {
        return $this->info('youtube', $hash, $new);
    }

    private function prepareInfoParams($hash, $site = 'youtube')
    {
        $params = [];
        if ($site === 'youtube') {
            $params = ['--youtube-skip-dash-manifest', '-s', '-r', '1M', '-j', '--', $hash];
        }
        else if ($site === 'vimeo') {
            $vimeo_url = 'https://vimeo.com/' . $hash;
            $params = ['-s', '-r', '1M', '-j', '--', $vimeo_url];
        }

        return $params;

    }

    /**
     * @Route("/vimeoQueue/{hash}/{format}", name="vimeo_queue")
     */
    public function queueVimeoAction($hash, $format)
    {
       return $this->queue($hash, $format, 'vimeo_info');
    }

    /**
     * @Route("/vimeoQueue/{hash}/{format}", name="yt_queue")
     */
    public function queueYoutubeAction($hash, $format)
    {
        return $this->queue($hash, $format, 'yt_info');
    }

    private function checkMediaExist(string $hash, $categories = null, string $page = 'youtube')
    {

        var_dump($categories);

        /** @var Media $media */
        $media = $this->media->findOneByHash($hash);


        if (!$media) {
            $media = new Media();
            $media->setHash($hash);
            $media->setCreatedBy($this->downloadService->getUser());

            var_dump('new');

            $this->entityManager->persist($media);
        }

        $new_or_old = !$media->getRefreshedAt() || $media->getRefreshedAt() < new \DateTime('-7 days');

        if ($categories) {
            $this->addCategories($media, $categories);
        }

        if ($new_or_old) {
            $process = $this->downloadService->yt_dl($this->prepareInfoParams($hash, $page));
//         $process = $this->yt_dl(array('-s','-r', '1M', '-j', '-f', '[tbr>200]', $hash));

            $process->run();
            $media->setJson($process->getOutput());
            if (!$media->getJson()) {
                $this->addFlash('warn', 'Nieprawidłowy link');

                return $this->redirectToRoute('homepage');
            }
            $media->setRefreshedAt(new \DateTime());
            $media->setPopularity($media->getPopularity() + 1);

            $this->entityManager->flush();
        }

        if ($categories && !$new_or_old) {
            $this->entityManager->flush();
        }

        return $media;

    }

    private function addCategories($object, $categories)
    {
        foreach ($categories as $category) {
            $object->addCategory($category);
        }
    }

    private function info(string $site, string $hash, $new = null)
    {
        $media = $this->checkMediaExist($hash, null, $site);

        if ($new) {
            $this->media->increasePopularity($media);
        }

        $arr = $media->getJson();
        if (!$arr) {
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
        $ret['thumbnail'] = $arr->thumbnail;
        $ret['categories'] = $media->getCategories();


        $ret['files'] = array();

        $files = $this->queue->getFilesForHash($hash);
        foreach ($files as $file) {
            $ret['files'][$file->getFormat()] = $file;
        }

        return $ret;
    }

    private function queue(string $hash, $format, string $redirect_route_info)
    {
        $loss = $this->downloadService->getPacketLoss();
        if ($loss > 1) {
            $this->addFlash('warn', 'Łącze jest zbyt obciążone, spróbuj ponownie później');

            return $this->redirect($this->generateUrl($redirect_route_info, array('hash' => $hash, 'loss' => $loss)));
        }

        $usage = $this->downloadUsage->getUsageInLastWeek($this->downloadService->getUser());

        $download_limit = $this->getParameter('download_limit');
        if ($usage > $download_limit) {
            $this->addFlash('warn', sprintf('Przekroczono tygodniowy limit %sMB, nie możesz dodawać w tym momencie do kolejki', $download_limit));

            return $this->redirectToRoute($redirect_route_info, ['hash' => $hash]);
        }

        $download_concurrent = $this->getParameter('download_concurrent');
        if ($this->queue->findFilesInQueueForUserCount($this->downloadService->getUser()) > $download_concurrent - 1) {
            $this->addFlash('warn', sprintf('W kolejce nie może być więcej niż %s plików jednocześnie od tego samego użytkownika', $download_concurrent));

            return $this->redirectToRoute($redirect_route_info, ['hash' => $hash]);
        }

        $task = new YoutubeQueueTaskDescription();
        $task->hash = $hash;
        $task->format = $format;

        $media = $this->media->findOneByHash($hash);

        $info = json_decode($media->getJson());
        if (!$info) {
            $this->addFlash('notice', 'Problem z video');

            return $this->redirect('/');
        }
        $queue = new Queue();
        $queue->setCreatedBy($this->downloadService->getUser());
        $queue->setHash($hash);
        $queue->setFilename($info->_filename);
        $downloadUsage = new DownloadUsage();
        $downloadUsage->setMedia($this->media->findOneBy(['hash' => $hash]));
        $downloadUsage->setUserId($this->downloadService->getUser());
        $download_duration = $this->getParameter('download_duration');
        if (($info->duration / 60) > $download_duration) {
            $this->addFlash('warn', sprintf('Nie można pobierać plików trwających ponad %s minut', $download_duration));

            return $this->redirectToRoute($redirect_route_info, ['hash' => $hash]);
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
        $queue->setCreatedBy($this->downloadService->getUser());
        $this->entityManager->persist($downloadUsage);
        $this->entityManager->persist($queue);
        $this->entityManager->flush();
        $qid = $this->get('webdevvie_pheanstalktaskqueue.service')->queueTask($task);

        $this->addFlash('notice', 'Dodano plik do kolejki. Sprawdź za około minutę pobrane pliki.');

        return $this->redirect($this->generateUrl($redirect_route_info, array('hash' => $hash)));
    }
}
