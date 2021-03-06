<?php

declare(strict_types=1);

namespace Packagist\WebBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Packagist\WebBundle\Entity\Job;

class JobRepository extends EntityRepository
{
    public function start(string $jobId): bool
    {
        $conn = $this->getEntityManager()->getConnection();

        return 1 === $conn->executeUpdate('UPDATE job SET status = :status, startedAt = :now WHERE id = :id AND startedAt IS NULL', [
            'id' => $jobId,
            'status' => Job::STATUS_STARTED,
            'now' => (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
        ]);
    }

    public function markTimedOutJobs()
    {
        $conn = $this->getEntityManager()->getConnection();

        $conn->executeUpdate('UPDATE job SET status = :newstatus WHERE status = :status AND startedAt < :timeout', [
            'status' => Job::STATUS_STARTED,
            'newstatus' => Job::STATUS_TIMEOUT,
            'timeout' => (new \DateTime('-30 minutes', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
        ]);
    }

    public function getScheduledJobIds(): \Generator
    {
        $conn = $this->getEntityManager()->getConnection();

        $stmt = $conn->executeQuery('SELECT id FROM job WHERE status = :status AND (executeAfter IS NULL OR executeAfter <= :now) ORDER BY createdAt ASC', [
            'status' => Job::STATUS_QUEUED,
            'now' => (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
        ]);

        while ($row = $stmt->fetch(\PDO::FETCH_COLUMN)) {
            yield $row;
        }
    }

    /**
     * @param string $type
     * @param int $packageId
     * @param int $limit
     * @return Job[]
     */
    public function findJobsByType(string $type, $packageId = null, $limit = 25)
    {
        $qb = $this->createQueryBuilder('j')
            ->where('j.type = :type')
            ->andWhere('j.completedAt IS NOT NULL')
            ->setMaxResults($limit)
            ->setParameter('type', $type)
            ->orderBy('j.completedAt', 'DESC');

        if ($packageId) {
            $qb->andWhere('j.packageId = :packageId')
                ->setParameter('packageId', $packageId);
        }

        return $qb->getQuery()->getResult();
    }
}
