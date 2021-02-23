<?php declare(strict_types=1);

namespace App\Command;

use App\Entity\Movie;
use Doctrine\ORM\EntityManagerInterface;
use DOMDocument;
use DOMXPath;
use GuzzleHttp\Psr7\Request;
use JetBrains\PhpStorm\Pure;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;
use SimpleXMLElement;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use InvalidArgumentException;
use XMLReader;

class FetchDataCommand extends Command
{
    private const SOURCE = 'https://trailers.apple.com/trailers/home/rss/newtrailers.rss';
    private const COUNT_IMPORT_ITEMS = 10;

    protected static $defaultName = 'fetch:trailers';

    private ClientInterface $httpClient;
    private LoggerInterface $logger;
    private string $source;
    private ?string $data;
    private int $count;
    private bool $importLast;
    private EntityManagerInterface $doctrine;

    /**
     * FetchDataCommand constructor.
     *
     * @param ClientInterface        $httpClient
     * @param LoggerInterface        $logger
     * @param EntityManagerInterface $em
     * @param string|null            $name
     */
    public function __construct(ClientInterface $httpClient, LoggerInterface $logger, EntityManagerInterface $em, string $name = null)
    {
        parent::__construct($name);
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->doctrine = $em;
        $this->source = self::SOURCE;
        $this->count = self::COUNT_IMPORT_ITEMS;
        $this->importLast = false;
    }

    public function configure(): void
    {
        $this
            ->setDescription('Fetch data from iTunes Movie Trailers')
            ->addArgument('source', InputArgument::OPTIONAL, 'Overwrite source')
            ->addOption('count', 'c', InputOption::VALUE_OPTIONAL, 'Number of imported records', self::COUNT_IMPORT_ITEMS)
            ->addOption('import-last', 'l', InputOption::VALUE_NONE, 'Import recent records');
        ;
    }

    /**
     * @return string|null
     */
    public function getData(): ?string
    {
        return $this->data;
    }

    /**
     * @param string|null $data
     */
    public function setData(?string $data): void
    {
        $this->data = $data;
    }

    /**
     * @return bool
     */
    public function isImportLast(): bool
    {
        return $this->importLast;
    }

    /**
     * @param bool $importLast
     */
    public function setImportLast(bool $importLast): void
    {
        $this->importLast = $importLast;
    }

    /**
     * Get url of source data.
     *
     * @return string
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * Set url of source data.
     *
     * @param string $source
     */
    public function setSource(string $source): void
    {
        $this->source = $source;
    }

    /**
     * Get number of imported records.
     *
     * @return int
     */
    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * Set number of imported records.
     *
     * @param int $count
     */
    public function setCount(int $count): void
    {
        $this->count = $count;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->info(sprintf('Start %s at %s', __CLASS__, (string) date_create()->format(DATE_ATOM)));
        $io = new SymfonyStyle($input, $output);

        $sourceArgument = $input->getArgument('source');
        if (null !== $sourceArgument) {
            if (!is_file($sourceArgument) && filter_var($sourceArgument, FILTER_VALIDATE_URL) !== true) {
                $io->error('Source data is not a file or url.');
                return 1;
            }
            $this->setSource($sourceArgument);
        }

        $countOption = (int)$input->getOption('count');
        if (!is_integer($countOption)) {
            throw new InvalidArgumentException('Source must be integer.');
        }
        $this->setCount($countOption);
        $this->setImportLast((bool)$input->getOption('import-last'));

        // Validate source data
        $io->title(sprintf('Validate data from %s', $this->getSource()));
        if ($this->validateXml('./schemas/trailers.xsd')) {
            $io->success('Validation success.');
        }

        // Load source data
        $io->title(sprintf('Fetch data from %s', $this->getSource()));
        if (is_file($this->getSource())) {
            $data = file_get_contents($this->getSource());
        } else {
            try {
                $response = $this->httpClient->sendRequest(new Request('GET', $this->getSource()));
            } catch (ClientExceptionInterface $e) {
                throw new RuntimeException($e->getMessage());
            }
            if (($status = $response->getStatusCode()) !== 200) {
                throw new RuntimeException(sprintf('Response status is %d, expected %d', $status, 200));
            }
            $data = $response->getBody()->getContents();
        }

        // Parsing data
        $this->processXml($data);

        $this->logger->info(sprintf('End %s at %s', __CLASS__, (string) date_create()->format(DATE_ATOM)));

        return 0;
    }

    protected function processXml(string $data): void
    {
        $xml = (new \SimpleXMLElement($data))->children();
        $namespace = $xml->getNamespaces(true);
        //dd((string) $xml->channel->item[0]->children($namespace['content'])->encoded);

        if (!property_exists($xml, 'channel')) {
            throw new RuntimeException('Could not find \'channel\' element in feed');
        }

        // Check if the count argument is greater than the data in the source
        $countItems = count($xml->channel->item);
        if ( $this->isImportLast()) {
            $startIndex = ($countItems - $this->getCount()) < 0 ? 0 : $countItems - $this->getCount();
            $endIndex = ($startIndex + $this->getCount()) > $countItems ? $countItems : $startIndex + $this->getCount();
        } else {
            $startIndex = 0;
            $endIndex = ($countItems < $this->getCount()) ? $countItems : $this->getCount();
        }

        for ($i = $startIndex; $i < $endIndex ; $i++) {
            /** @var SimpleXMLElement $item */
            $item = $xml->channel->item[$i];

            if ($item->getName() == 'item') {
                $posterURI = $this->parsePoster((string)$item->children($namespace['content'])->encoded);

                $trailer = $this->getMovie((string)$item->title)
                    ->setTitle((string)$item->title)
                    ->setDescription((string)$item->description)
                    ->setLink((string)$item->link)
                    ->setPubDate($this->parseDate((string)$item->pubDate))
                    ->setImage($posterURI);
                ;

                $this->doctrine->persist($trailer);
            }
        }
        $this->doctrine->flush();
    }

    protected function validateXml(string $xsdFile = null): bool
    {
        libxml_use_internal_errors(true);

        $reader = new XMLReader();
        $reader->open($this->getSource());
        $reader->setParserProperty(XMLReader::VALIDATE, true);

        //validating xml
        if (!$reader->isValid()) {
            $errors = $this->getErrorsXML();
            throw new RuntimeException(sprintf("Source data `%s` is not valid :\n%s", $this->xmlFile, $errors));
        }

        return true;
    }

    protected function parseDate(string $date): \DateTime
    {
        return new \DateTime($date);
    }

    protected function getMovie(string $title): Movie
    {
        $item = $this->doctrine->getRepository(Movie::class)->findOneBy(['title' => $title]);

        if ($item === null) {
            $this->logger->info('Create new Movie', ['title' => $title]);
            $item = new Movie();
        } else {
            $this->logger->info('Move found', ['title' => $title]);
        }

        if (!($item instanceof Movie)) {
            throw new RuntimeException('Wrong type!');
        }

        return $item;
    }

    protected function parsePoster(string $html): ?string
    {
        $parser = xml_parser_create();
        xml_parse_into_struct($parser, $html, $values, $index);
        xml_parser_free($parser);

        foreach ($values as $item) {
            if ($item['tag'] == 'IMG') return $item['attributes']['SRC'];
        }
    }

    public function getErrorsXML(): ?string
    {
        $errorsString = '';
        $errors = libxml_get_errors();

        foreach ($errors as $key => $error) {
            $level = $error->level === LIBXML_ERR_WARNING ? 'Warning' : ($error->level === LIBXML_ERR_ERROR ? 'Error' : 'Fatal');
            $errorsString .= sprintf("    [%s] %s", $level, $error->message);

            if($error->file) {
                $errorsString .= sprintf("    in %s (line %s, col %s)", $error->file, $error->line, $error->column);
            }

            $errorsString .= "\n";
        }

        return $errorsString;
    }
}
