<?php declare(strict_types=1);

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Exception;
use Symfony\Component\Console\Style\SymfonyStyle;

class DatabaseDropCommand extends Command
{
    protected static $defaultName = 'orm:database:drop';
    private EntityManagerInterface $doctrine;

    /**
     * DatabaseDropCommand constructor.
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
            ->setDescription('Drop database')
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dbConfig = parse_url($_ENV['DATABASE']);
        $dbConfig['path'] = preg_replace('/\//', '', $dbConfig['path']);
        $io = new SymfonyStyle($input, $output);

        try {
            if ($this->doctrine->getConnection()->connect()) {
                $this->doctrine->getConnection()->executeQuery('DROP DATABASE '.$dbConfig['path']);
                $io->success('Database dropped.');
            }
        } catch(Exception $e) {
            $io->error($e->getMessage());
            return 1;
        }
        return 0;
    }
}
