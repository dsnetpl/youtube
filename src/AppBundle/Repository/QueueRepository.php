<?php

namespace AppBundle\Repository;

use AppBundle\Entity\Queue;
use Doctrine\ORM\EntityRepository;

/**
 * QueueRepository.
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class QueueRepository extends EntityRepository
{
    public function getFilesDownloadedQuery($page = 1, $limit = 100)
    {
        return $query = $this->createQueryBuilder('q')
             ->select('q, COALESCE(q.lastDownload, q.finishedAt) as HIDDEN columnOrder')
             ->where('q.finishedAt is not null')
             ->andWhere('q.deletedAt is null')
             ->orderBy('columnOrder', 'DESC')
             ->getQuery()
             ->setFirstResult($limit * ($page - 1))
             ->setMaxResults($limit)
             ->getResult();
    }

    public function getFilesDownloadedSearch($search = null, $offset = 0, $limit = 100)
    {
        $qb = $this->createQueryBuilder('q')
            ->select('q, COALESCE(q.lastDownload, q.finishedAt) as HIDDEN columnOrder')
            ->where('q.finishedAt is not null')
            ->andWhere('q.deletedAt is null')
            ->orderBy('columnOrder', 'DESC');
        if ($search) {
            $search_parsed = trim(trim($search, "\x00..\x1F"), '%_');
            if (strlen($search_parsed) > 0) {
                $qb->andWhere('LOWER(q.title) like :title')->setParameter('title', mb_strtolower('%'.$search_parsed.'%', 'UTF-8'));
            }
        }

        return $qb->getQuery()->setFirstResult($offset)->setMaxResults($limit)->getResult();
    }

    public function getFileForDownload($hash, $format)
    {
        return $this->createQueryBuilder('q')
            ->where('q.hash = :hash')
            ->andWhere('q.format = :format')
            ->setParameter('hash', $hash)
            ->setParameter('format', $format)
            ->andWhere('q.finishedAt is null')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getFilesForDownload()
    {
        return $this->createQueryBuilder('q')
            ->where('q.finishedAt is null')
            ->orderBy('q.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getFilesForHash($hash)
    {
        return $this->createQueryBuilder('q')
            ->where('q.hash = :hash')
            ->andWhere('q.deletedAt is null')
            ->setParameter('hash', $hash)
            ->getQuery()
            ->getResult();
    }

    public function increaseDownloads(Queue $file)
    {
        $this->getEntityManager()->createQuery(
            'UPDATE AppBundle:Queue q SET q.downloads = q.downloads + 1, q.lastDownload = CURRENT_TIMESTAMP() WHERE q.id = :id'
        )
            ->setParameter('id', $file->getId())
            ->execute();
    }

    public function findFilesToRemove($number)
    {
        return $this->createQueryBuilder('q')
            ->select('q, COALESCE(q.lastDownload, q.finishedAt) as HIDDEN columnOrder')
            ->andWhere('q.deletedAt is null')
            ->addOrderBy('columnOrder', 'ASC')
            ->setMaxResults($number)
            ->getQuery()
            ->getResult();
    }

    public function findFilesInQueueForUserCount($userId)
    {
        return $this->createQueryBuilder('q')
            ->select('COUNT(q)')
            ->where('q.createdBy = :userId')
            ->andWhere('q.progress < 100')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
