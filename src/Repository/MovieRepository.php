<?php declare(strict_types=1);

namespace App\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;

class MovieRepository extends EntityRepository
{
    public function findById(int $id, int $mode = Query::HYDRATE_ARRAY): ?array
    {
        $result = $this->createQueryBuilder('m')
            ->andWhere('m.id = '.$id)
            ->getQuery()
            ->getResult($mode)
            ;

        return ($result) ? $result[0] : null;
    }
}
