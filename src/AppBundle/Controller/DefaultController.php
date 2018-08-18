<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Queue;
use AppBundle\Form\ContactType;
use AppBundle\Repository\QueueRepository;
use AppBundle\Services\DownloadService;
use Doctrine\ORM\EntityManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends Controller
{
    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var QueueRepository
     */
    protected $queue;

    /**
     * @var DownloadService
     */
    protected $downloadService;

    public function __construct(EntityManager $entityManager, QueueRepository $queue, DownloadService $downloadService)
    {
        $this->entityManager = $entityManager;
        $this->queue = $queue;
        $this->downloadService = $downloadService;
    }

    /**
     * @Route("/", name="homepage")
     * @Template
     */
    public function indexAction(Request $request)
    {
        return [];
    }

    /**
     * @Route("/login", name="login")
     */
    public function loginAction()
    {
        return $this->redirect('/');
    }

    /**
     * @Route("/list/{page}", name="list_files", requirements={"page": "\d+"})
     * @Template()
     */
    public function listFilesAction($page = 1)
    {
        $sql = 'select sum(filesize/1024) from queue where finished_at is not null;';
        $stm = $this->entityManager->getConnection()->executeQuery($sql);
        $downloaded = $stm->fetch(\PDO::FETCH_COLUMN);

        $limit = 100;
        $allfiles = $this->queue->getFilesDownloadedQuery(1, null);
        $maxPages = ceil(count($allfiles) / $limit);

        return [
            'downloaded' => round($downloaded / 1024, 1),
            'page' => $page,
            'maxPages' => $maxPages,
        ];
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
            $files = $this->queue->getFilesDownloadedSearch($query, $offset, $limit);
        } else {
            $files = $this->queue->getFilesDownloadedQuery($page);
        }

        return array('files' => $files);
    }

    /**
     * @Route("/queueList", name="list_queue")
     * @Template()
     */
    public function listQueueAction()
    {
        $files = $this->queue->getFilesForDownload();

        return array(
            'files' => $files,
        );
    }

    /**
     * @Route("/get/{id}", name="download_file", requirements={"id":"\d+"})
     */
    public function getFileAction(Queue $file)
    {
        $this->queue->increaseDownloads($file);

        return $this->redirect('/download/'.$file->getFilename());
    }

    /**
     * @Route("/watch/{id}", name="watch_video", requirements={"id": "\d+"})
     * @Template()
     */
    public function watchVideoAction(Queue $file)
    {
        $this->queue->increaseDownloads($file);

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
        $form = $this->createForm(ContactType::class, null, [
            'action' => $this->generateUrl('contact'),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $data = $form->getData();
                $message = \Swift_Message::newInstance();
                $message->setSubject('Youtube - feedback');
                $message->setBody(sprintf("IP: %s\ntenant_id: %s\nWiadomość: %s", $request->getClientIp(), $this->downloadService->getUser(), $data['message']));
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

    /**
     * @Route("/packetLoss", name="dsnet_network_packet_loss")
     */
    public function packetLossAction()
    {
        return new Response($this->downloadService->getPacketLoss());
    }
}
