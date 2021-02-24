<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\Likes;
use App\Entity\Movie;
use App\Repository\LikesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Interfaces\RouteCollectorInterface;
use Twig\Environment;
use App\Repository\MovieRepository;

class HomeController
{
    public function __construct(
        private RouteCollectorInterface $routeCollector,
        private Environment $twig,
        private EntityManagerInterface $em
    ) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $data = $this->twig->render('home/index.html.twig', [
                'trailers' => $this->fetchTrailersList(),
            ]);
        } catch (\Exception $e) {
            throw new HttpBadRequestException($request, $e->getMessage(), $e);
        }

        $response->getBody()->write($data);

        return $response;
    }

    public function view(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        //dd($this->fetchTrailerData((int)$args['id']));
        try {
            $data = $this->twig->render('home/view.html.twig', [
                'trailer' => $this->fetchTrailerData((int)$args['id']),
            ]);
        } catch (\Exception $e) {
            throw new HttpBadRequestException($request, $e->getMessage(), $e);
        }

        $response->getBody()->write($data);

        return $response;
    }

    public function like(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $this->addTrailerLike((int)$args['id']);
        } catch (\Exception $e) {
            throw new HttpBadRequestException($request, $e->getMessage(), $e);
        }

        $refererHeader = $request->getHeader('HTTP_REFERER');

        return $response->withHeader('Location', $refererHeader);
    }

    protected function fetchTrailersList(): Collection
    {
        $data = $this->em->getRepository(Movie::class)
            ->findAll();

        return new ArrayCollection($data);
    }

    protected function fetchTrailerData(int $id): array
    {
        /** @var MovieRepository $r */
        $r = $this->em->getRepository(Movie::class);

        return $r->findById($id);
    }

    protected function addTrailerLike(int $movieId): void
    {
        /** @var LikesRepository $r */
        $r = $this->em->getRepository(Likes::class);
        /** @var Likes $like */
        $like = $r->getByMovieId($movieId);
        $like->setCount(
            $like->getCount() + 1
        );
        $this->em->persist($like);
        $this->em->flush();
    }
}
