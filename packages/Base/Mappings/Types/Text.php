<?php

declare(strict_types=1);

namespace Sigmie\Base\Mappings\Types;

use Sigmie\Base\Analysis\Analyzer;

class Text extends BaseType
{
    protected string $type;

    protected ?Analyzer $analyzer;

    public function searchAsYouType(Analyzer $analyzer = null)
    {
        $this->analyzer = $analyzer;
        $this->type = 'search_as_you_type';

        return $this;
    }

    public function unstructuredText(Analyzer $analyzer = null)
    {
        $this->analyzer = $analyzer;
        $this->type = 'text';

        return $this;
    }

    public function completion(Analyzer $analyzer = null)
    {
        $this->analyzer = $analyzer;
        $this->type = 'completion';

        return $this;
    }

    public function withAnalyzer(Analyzer $analyzer)
    {
        $this->analyzer = $analyzer;
    }

    public function analyzer()
    {
        return $this->analyzer;
    }

    public function raw()
    {
        return [
            'type' => $this->type,
            'analyzer' => $this->analyzer->name()
        ];
    }
}
