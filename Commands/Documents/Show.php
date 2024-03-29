<?php

declare(strict_types=1);

namespace Sigmie\Cli\Commands\Documents;

use Sigmie\Base\Actions\Alias as IndexActions;
use Sigmie\Base\APIs\Mget;
use Sigmie\Base\Index\AbstractIndex;
use Sigmie\Cli\BaseCommand;
use Sigmie\Cli\Outputs\DocumentTable;
use Symfony\Component\Console\Input\InputArgument;

class Show extends BaseCommand
{
    use Mget;
    use IndexActions;

    protected static $defaultName = 'doc:show';

    protected AbstractIndex $index;

    public function executeCommand(): int
    {
        $indexName = $this->input->getArgument('index');
        $documentId = $this->input->getArgument('document');

        $this->index = $this->getIndex($indexName);

        $response = $this->mgetAPICall(['docs' => [['_id' => $documentId]]]);

        $table = new DocumentTable($response->json());

        $table->output($this->output);

        return 1;
    }

    protected function configure()
    {
        parent::configure();

        $this->addArgument('index', InputArgument::REQUIRED, 'Index name');
        $this->addArgument('document', InputArgument::REQUIRED, 'Document id');
    }

    protected function index(): AbstractIndex
    {
        return $this->index;
    }
}
