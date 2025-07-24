<?php

namespace App\Repository;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    /**
     * Find all categories with error handling.
     *
     * @return Category[]
     */
    public function findAll(): array
    {
        try {
            return parent::findAll();
        } catch (\Exception $e) {
            error_log('Error in CategoryRepository::findAll: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
            return [];
        }
    }
}