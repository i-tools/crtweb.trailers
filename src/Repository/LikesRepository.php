<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Likes;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;

class LikesRepository extends EntityRepository
{
    public function findByMovieId(int $id, int $mode = Query::HYDRATE_ARRAY): ?array
    {
        $result = $this->createQueryBuilder('l')
            //->join('m.likes', 'l')
            ->andWhere('l.movie = '.$id)
            ->getQuery()
            ->getResult($mode)
        ;

        return ($result) ? $result[0] : null;
    }

    public function getByMovieId(int $id): ?Likes
    {
        /** @var Likes $like */
        $like = $this->createQueryBuilder('l')
            ->andWhere('l.movie = '.$id)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $like;
    }
}
