<?php

namespace App\Repository;

use App\Entity\Activity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Activity>
 */
class ActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Activity::class);
    }

    /**
     * Find all activities with error handling.
     *
     * @return Activity[]
     */
    public function findAll(): array
    {
        $results = parent::findAll();
        foreach ($results as $activity) {
            error_log('Activity ID ' . $activity->getId() . ' - Difficulty: ' . ($activity->getDifficulty() ?? 'null'));
        }
        return $results;
    }

    /**
     * Find an activity by ID with its associated category.
     *
     * @param int $id
     * @return Activity|null
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findWithCategory(int $id): ?Activity
    {
        try {
            return $this->createQueryBuilder('a')
                ->addSelect('c')
                ->leftJoin('a.category', 'c')
                ->where('a.id = :id')
                ->setParameter('id', $id)
                ->getQuery()
                ->getOneOrNullResult();
        } catch (\Exception $e) {
            error_log('Error in ActivityRepository::findWithCategory: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Find activities by a search query matching name or description.
     *
     * @param string $query The search query
     * @param int $limit Maximum number of results to return
     * @return Activity[]
     */
    public function findBySearchQuery(string $query, int $limit = 10): array
    {
        try {
            return $this->createQueryBuilder('a')
                ->where('LOWER(a.name) LIKE :query OR (a.description IS NOT NULL AND LOWER(a.description) LIKE :query)')
                ->setParameter('query', '%' . strtolower(trim($query)) . '%')
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();
        } catch (\Exception $e) {
            error_log('Error in ActivityRepository::findBySearchQuery: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Find activities by criteria and optional search query.
     *
     * @param array $criteria
     * @param string|null $searchQuery
     * @return Activity[]
     */
    public function findByCriteriaAndSearch(array $criteria, ?string $searchQuery = null): array
    {
        try {
            $qb = $this->createQueryBuilder('a')
                ->select('a')
                ->leftJoin('a.category', 'c')
                ->addSelect('c');

            if ($searchQuery) {
                $qb->andWhere('LOWER(a.name) LIKE :searchQuery OR (a.description IS NOT NULL AND LOWER(a.description) LIKE :searchQuery)')
                   ->setParameter('searchQuery', '%' . strtolower(trim($searchQuery)) . '%');
            }

            if (isset($criteria['category']) && $criteria['category'] !== null) {
                $qb->andWhere('a.category = :category')
                   ->setParameter('category', $criteria['category']);
            }

            if (isset($criteria['place']) && $criteria['place'] !== 'all' && $criteria['place'] !== null) {
                $qb->andWhere('a.place = :place')
                   ->setParameter('place', $criteria['place']);
            }

            if (isset($criteria['difficulty']) && $criteria['difficulty'] !== 'all' && $criteria['difficulty'] !== null) {
                $qb->andWhere('a.difficulty = :difficulty')
                   ->setParameter('difficulty', $criteria['difficulty']);
            }

            return $qb->getQuery()->getResult();
        } catch (\Exception $e) {
            error_log('Error in ActivityRepository::findByCriteriaAndSearch: ' . $e->getMessage());
            return [];
        }
    }
}