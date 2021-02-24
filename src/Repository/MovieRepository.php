<?php declare(strict_types=1);

namespace App\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;

class MovieRepository extends EntityRepository
{
    public function findById(int $id, int $mode = Query::HYDRATE_ARRAY): ?array
    {
        $result = $this->createQueryBuilder('m')
            ->leftJoin('m.likes', 'l')
            ->andWhere('m.id = '.$id)
            ->getQuery()
            ->getOneOrNullResult($mode)
            ;

        return $result;
    }
}
