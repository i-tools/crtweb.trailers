<?php declare(strict_types=1);

namespace App\Command;

use App\Support\Config;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Setup;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Exception;
use Symfony\Component\Console\Style\SymfonyStyle;

class DatabaseCreateCommand extends Command
{
    protected static $defaultName = 'orm:database:create';
    private EntityManagerInterface $doctrine;

    /**
     * DatabaseCreateCommand constructor.
     * @param Config $config
     * @param EntityManagerInterface $em
     * @param string|null $name
     */
    public function __construct(EntityManagerInterface $em, string $name = null)
    {
        parent::__construct($name);
        $this->doctrine = $em;
    }

    public function configure(): void
    {
        $this
            ->setDescription('Create database')
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws \Doctrine\DBAL\Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dbConfig = parse_url($_ENV['DATABASE']);
        $dbConfig['path'] = preg_replace('/\//', '', $dbConfig['path']);
        $config = Setup::createAnnotationMetadataConfiguration(['Models'], false, null, null, false);

        try {
            if ($this->doctrine->getConnection()->connect()) {
                $io->error('Database is exist.');
                return 1;
            }
        }
        catch(Exception $e) {
            $connection = EntityManager::create([
                'driver'   => 'pdo_'.$dbConfig['scheme'],
                'host'     => $dbConfig['host'],
                'user'     => $dbConfig['user'],
                'password' => $dbConfig['pass'],
                'charset' => 'UTF8'
            ], $config)->getConnection();
            $connection->executeQuery('CREATE DATABASE '.$dbConfig['path'].' CHARACTER SET utf8 COLLATE utf8_general_ci');
            $io->success('Database created.');
        }

        return 0;
    }
}
