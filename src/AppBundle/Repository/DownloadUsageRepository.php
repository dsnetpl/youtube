<?php

namespace AppBundle\Repository;

use Doctrine\ORM\EntityRepository;

/**
 * DownloadUsageRepository.
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class DownloadUsageRepository extends EntityRepository
{
    public function getUsageInLastWeek($userId)
    {
        return $this->createQueryBuilder('du')
            ->select('SUM(du.filesize)')
            ->where('du.createdAt >= :date')
            ->setParameter('date', new \DateTime('-7 days'))
            ->andWhere('du.userId = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}