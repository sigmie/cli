<?php

declare(strict_types=1);

namespace Sigmie\Tests\Base\Index;

use Exception;
use Sigmie\Base\Analysis\Analyzer;
use Sigmie\Base\Analysis\CharFilter\HTMLFilter;
use Sigmie\Base\Analysis\CharFilter\MappingFilter;
use Sigmie\Base\Analysis\CharFilter\Pattern as PatternCharFilter;
use Sigmie\Base\Analysis\TokenFilter\Stopwords;
use Sigmie\Base\Analysis\Tokenizers\Pattern as PatternTokenizer;
use Sigmie\Base\Analysis\Tokenizers\Whitespaces;
use Sigmie\Base\APIs\Index;
use Sigmie\Base\Documents\Document;
use Sigmie\Base\Documents\DocumentsCollection;
use Sigmie\Base\Index\Blueprint;
use function Sigmie\Helpers\name_configs;
use Sigmie\Support\Alias\Actions;
use Sigmie\Support\Update\Update as Update;

use Sigmie\Testing\TestCase;
use TypeError;

class UpdateTest extends TestCase
{
    use Index, Actions;

    /**
     * @test
     */
    public function remove_filter()
    {
        $this->sigmie->newIndex('foo')
            ->withoutMappings()
            ->stopwords(['foo', 'bar'], 'foo_stopwords')
            ->create();

        $this->assertAnalyzerHasFilter('foo', 'default', 'foo_stopwords');

        $this->sigmie->index('foo')->update(function (Update $update) {

            $update->analyzer('default')->removeFilter('foo_stopwords');

            return $update;
        });

        $this->assertAnalyzerHasNotFilter('foo', 'default', 'foo_stopwords');
    }

    /**
     * @test
     */
    public function analyzer_remove_html_char_filters()
    {
        $this->sigmie->newIndex('foo')
            ->withoutMappings()
            ->stopwords(['foo', 'bar'], 'demo')
            ->mapChars(['foo' => 'bar'], 'some_char_filter_name')
            ->stripHTML()
            ->create();

        $this->assertAnalyzerHasFilter('foo', 'default', 'demo');
        $this->assertFilterHasStopwords('foo', 'demo', ['foo', 'bar']);
        $this->assertAnalyzerHasCharFilter('foo', 'default', 'html_strip');
        $this->assertAnalyzerHasCharFilter('foo', 'default', 'some_char_filter_name');

        $this->sigmie->index('foo')->update(function (Update $update) {

            $update->analyzer('default')->removeCharFilter(new HTMLFilter)
                ->removeCharFilter('some_char_filter_name');

            return $update;
        });

        $this->assertAnalyzerHasNotCharFilter('foo', 'default', 'html_strip');
        $this->assertAnalyzerHasNotCharFilter('foo', 'default', 'some_char_filter_name');
    }

    /**
     * @test
     */
    public function update_char_filter()
    {
        $this->sigmie->newIndex('foo')
            ->withoutMappings()
            ->mapChars(['bar' => 'baz'], 'map_chars_char_filter')
            ->patternReplace('/bar/', 'foo', 'pattern_replace_char_filter')
            ->create();

        $this->assertAnalyzerHasCharFilter('foo', 'default', 'map_chars_char_filter');
        $this->assertCharFilterEquals('foo', 'map_chars_char_filter', [
            'type' => 'mapping',
            'class' => MappingFilter::class,
            'mappings' => ['bar => baz']
        ]);

        $this->assertAnalyzerHasCharFilter('foo', 'default', 'pattern_replace_char_filter');
        $this->assertCharFilterEquals('foo', 'pattern_replace_char_filter', [
            'type' => 'pattern_replace',
            'class' => PatternCharFilter::class,
            'pattern' => '/bar/',
            'replacement' => 'foo'
        ]);

        $this->sigmie->index('foo')->update(function (Update $update) {

            $update->charFilter('map_chars_char_filter', ['baz' => 'foo']);
            $update->charFilter('pattern_replace_char_filter', [
                'pattern' => '/doe/',
                'replacement' => 'john'
            ]);

            return $update;
        });

        $this->assertAnalyzerHasCharFilter('foo', 'default', 'map_chars_char_filter');
        $this->assertCharFilterEquals('foo', 'map_chars_char_filter', [
            'type' => 'mapping',
            'class' => MappingFilter::class,
            'mappings' => ['baz => foo']
        ]);

        $this->assertAnalyzerHasCharFilter('foo', 'default', 'pattern_replace_char_filter');
        $this->assertCharFilterEquals('foo', 'pattern_replace_char_filter', [
            'type' => 'pattern_replace',
            'class' => PatternCharFilter::class,
            'pattern' => '/doe/',
            'replacement' => 'john'
        ]);
    }

    /**
     * @test
     */
    public function analyzer_update_char_filter()
    {
        $this->sigmie->newIndex('foo')
            ->mapping(function (Blueprint $blueprint) {

                $blueprint->text('bar')->unstructuredText()->withAnalyzer(new Analyzer('bar'));

                return $blueprint;
            })
            ->create();

        $this->assertAnalyzerHasTokenizer('foo', 'bar', 'standard');
        $this->assertAnalyzerExists('foo', 'bar');

        $this->sigmie->index('foo')->update(function (Update $update) {

            $update->analyzer('bar')->stripHTML()
                ->patternReplace('/foo/', 'something', 'bar_pattern_replace_filter')
                ->mapChars(['bar' => 'baz'], 'bar_mappings_filter');

            return $update;
        });

        $this->assertAnalyzerExists('foo', 'bar');
        $this->assertAnalyzerHasCharFilter('foo', 'bar', 'html_strip');
        $this->assertAnalyzerHasCharFilter('foo', 'bar', 'bar_pattern_replace_filter');
        $this->assertAnalyzerHasCharFilter('foo', 'bar', 'bar_mappings_filter');
    }

    /**
     * @test
     */
    public function analyzer_update_tokenizer_using_tokenize_on()
    {
        $this->sigmie->newIndex('foo')
            ->mapping(function (Blueprint $blueprint) {

                $blueprint->text('bar')->unstructuredText()->withAnalyzer(new Analyzer('bar'));

                return $blueprint;
            })
            ->create();

        $this->assertAnalyzerHasTokenizer('foo', 'bar', 'standard');
        $this->assertAnalyzerExists('foo', 'bar');

        $this->sigmie->index('foo')->update(function (Update $update) {

            $update->analyzer('bar')->tokenizeOn()->whiteSpaces();

            return $update;
        });


        $this->assertAnalyzerHasTokenizer('foo', 'bar', 'whitespace');
        $this->assertAnalyzerExists('foo', 'bar');
    }

    /**
     * @test
     */
    public function analyzer_update_tokenizer_value()
    {
        $this->sigmie->newIndex('foo')
            ->mapping(function (Blueprint $blueprint) {

                $blueprint->text('bar')->unstructuredText()->withAnalyzer(new Analyzer('bar'));

                return $blueprint;
            })
            ->create();


        $this->assertAnalyzerHasTokenizer('foo', 'bar', 'standard');
        $this->assertAnalyzerExists('foo', 'bar');

        $this->sigmie->index('foo')->update(function (Update $update) {

            $update->analyzer('bar')->tokenizer(new Whitespaces);

            return $update;
        });

        $this->assertAnalyzerHasTokenizer('foo', 'bar', 'whitespace');
        $this->assertAnalyzerExists('foo', 'bar');
    }

    /**
     * @test
     */
    public function analyzer_add_char_filter()
    {
        $this->sigmie->newIndex('foo')
            ->withoutMappings()
            ->create();

        $this->assertAnalyzerFilterIsEmpty('foo', 'default');

        $this->sigmie->index('foo')->update(function (Update $update) {

            $update->analyzer('bear')->charFilter(new PatternCharFilter('foo_pattern_filter', '//', 'bar'));

            return $update;
        });

        $this->assertAnalyzerHasCharFilter('foo', 'bear', 'foo_pattern_filter');
        $this->assertCharFilterExists('foo', 'foo_pattern_filter', ['who', 'he']);
    }

    /**
     * @test
     */
    public function analyzer_update_method()
    {
        $this->sigmie->newIndex('foo')
            ->withoutMappings()
            ->create();

        $this->assertAnalyzerFilterIsEmpty('foo', 'default');

        $this->sigmie->index('foo')->update(function (Update $update) {

            $update->analyzer('bear')->filter(new Stopwords(
                'new_stopwords',
                ['who', 'he']
            ));

            return $update;
        });

        $this->assertAnalyzerHasFilter('foo', 'bear', 'new_stopwords');
        $this->assertFilterHasStopwords('foo', 'new_stopwords', ['who', 'he']);
    }

    /**
     * @test
     */
    public function default_char_filter()
    {
        $this->sigmie->newIndex('foo')
            ->withoutMappings()
            ->create();

        $this->assertAnalyzerCharFilterIsEmpty('foo', 'default');

        $this->sigmie->index('foo')->update(function (Update $update) {

            $update->patternReplace('/foo/', 'bar', 'default_pattern_replace_filter');
            $update->mapChars(['foo' => 'bar'], 'default_mappings_filter');
            $update->stripHTML();

            return $update;
        });

        $this->assertAnalyzerHasCharFilter('foo', 'default', 'default_pattern_replace_filter');
        $this->assertAnalyzerHasCharFilter('foo', 'default', 'default_mappings_filter');
        $this->assertAnalyzerHasCharFilter('foo', 'default', 'html_strip');
    }

    /**
     * @test
     */
    public function default_tokenizer_configurable()
    {
        $this->sigmie->newIndex('foo')
            ->withoutMappings()
            ->create();

        $this->assertAnalyzerCharFilterIsEmpty('foo', 'default');
        $this->assertAnalyzerHasTokenizer('foo', 'default', 'standard');

        $this->sigmie->index('foo')->update(function (Update $update) {

            $update->tokenizeOn()->pattern('/foo/', 'default_analyzer_pattern_tokenizer');

            return $update;
        });

        $this->assertAnalyzerHasTokenizer('foo', 'default', 'default_analyzer_pattern_tokenizer');
        $this->assertTokenizerEquals('foo', 'default_analyzer_pattern_tokenizer', [
            'pattern' => '/foo/',
            'type' => 'pattern',
            'class' => Pattern::class,
        ]);
    }

    /**
     * @test
     */
    public function default_tokenizer()
    {
        $this->sigmie->newIndex('foo')
            ->withoutMappings()
            ->setTokenizer(new Whitespaces)
            ->create();

        $this->assertAnalyzerTokenizerIsWhitespaces('foo', 'default');

        $this->sigmie->index('foo')->update(function (Update $update) {

            $update->tokenizeOn()->wordBoundaries('foo_tokenizer');

            return $update;
        });

        $this->assertAnalyzerHasTokenizer('foo', 'default', 'foo_tokenizer');
    }

    /**
     * @test
     */
    public function update_index_one_way_synonyms()
    {
        $this->sigmie->newIndex('foo')
            ->withoutMappings()
            ->synonyms([
                'ipod' => ['i-pod', 'i pod']
            ], 'bar_name',)
            ->create();

        $this->assertFilterExists('foo', 'bar_name');
        $this->assertFilterHasSynonyms('foo', 'bar_name', [
            'i-pod, i pod => ipod',
        ]);

        $this->sigmie->index('foo')->update(function (Update $update) {

            $update->filter('bar_name', [
                'mickey' => ['mouse', 'goofy'],
            ]);

            return $update;
        });

        $this->assertFilterExists('foo', 'bar_name');
        $this->assertFilterHasSynonyms('foo', 'bar_name', [
            'mouse, goofy => mickey',
        ]);
    }

    /**
     * @test
     */
    public function update_index_stemming()
    {
        $this->sigmie->newIndex('foo')
            ->withoutMappings()
            ->stemming([
                'am' => ['be', 'are'],
                'mouse' => ['mice'],
                'feet' => ['foot'],
            ], 'bar_name')
            ->create();

        $this->assertFilterExists('foo', 'bar_name');
        $this->assertFilterHasStemming('foo', 'bar_name', [
            'be, are => am',
            'mice => mouse',
            'foot => feet',
        ]);

        $this->sigmie->index('foo')->update(function (Update $update) {

            $update->filter('bar_name', [
                'mickey' => ['mouse', 'goofy'],
            ]);

            return $update;
        });

        $this->assertFilterExists('foo', 'bar_name');
        $this->assertFilterHasStemming('foo', 'bar_name', [
            'mouse, goofy => mickey',
        ]);
    }

    /**
     * @test
     */
    public function update_index_synonyms()
    {
        $this->sigmie->newIndex('foo')
            ->withoutMappings()
            ->synonyms([
                ['treasure', 'gem', 'gold', 'price'],
                ['friend', 'buddy', 'partner']
            ], 'foo_two_way_synonyms',)
            ->create();

        $this->assertFilterExists('foo', 'foo_two_way_synonyms');
        $this->assertFilterHasSynonyms('foo', 'foo_two_way_synonyms', [
            'treasure, gem, gold, price',
            'friend, buddy, partner'
        ]);

        $this->sigmie->index('foo')->update(function (Update $update) {

            $update->filter('foo_two_way_synonyms', [['john', 'doe']]);

            return $update;
        });

        $this->assertFilterHasSynonyms('foo', 'foo_two_way_synonyms', [
            'john, doe',
        ]);
    }

    /**
     * @test
     */
    public function update_index_stopwords()
    {
        $this->sigmie->newIndex('foo')
            ->withoutMappings()
            ->stopwords(['foo', 'bar', 'baz'], 'foo_stopwords',)
            ->create();

        $this->assertFilterExists('foo', 'foo_stopwords');

        $this->sigmie->index('foo')->update(function (Update $update) {

            $update->filter('foo_stopwords', ['john', 'doe']);

            return $update;
        });

        $this->assertFilterExists('foo', 'foo_stopwords');
        $this->assertFilterHasStopwords('foo', 'foo_stopwords', ['john', 'doe']);
    }

    /**
     * @test
     */
    public function exception_when_not_returned()
    {
        $this->expectException(TypeError::class);

        $this->sigmie->newIndex('foo')
            ->withoutMappings()
            ->stopwords(['foo', 'bar', 'baz'], 'foo_stopwords',)
            ->create();

        $this->sigmie->index('foo')->update(function (Update $update) {
        });
    }

    /**
     * @test
     */
    public function mappings()
    {
        $this->sigmie->newIndex('foo')
            ->mapping(function (Blueprint $blueprint) {

                $blueprint->text('bar')->searchAsYouType();
                $blueprint->text('created_at')->unstructuredText();

                return $blueprint;
            })
            ->create();

        $index = $this->sigmie->index('foo');

        $this->assertPropertyIsUnstructuredText('foo', 'created_at');

        $index->update(function (Update $update) {

            $update->mapping(function (Blueprint $blueprint) {
                $blueprint->date('created_at');
                $blueprint->number('count')->float();

                return $blueprint;
            });

            return $update;
        });

        $this->assertPropertyExists('foo', 'count');
        $this->assertPropertyExists('foo', 'created_at');
        $this->assertPropertyIsDate('foo', 'created_at');
    }

    /**
     * @test
     */
    public function reindex_docs()
    {
        $this->sigmie->newIndex('foo')
            ->withoutMappings()
            ->create();

        $index = $this->sigmie->index('foo');
        $oldIndexName = $index->name();

        $docs = new DocumentsCollection();
        for ($i = 0; $i < 10; $i++) {
            $docs->addDocument(new Document(['foo' => 'bar']));
        }

        $index->addDocuments($docs);

        $this->assertCount(10, $index);

        $updatedIndex = $index->update(function (Update $update) {
            $update->replicas(3);
            return $update;
        });

        [$name, $config] = name_configs($updatedIndex->toRaw());

        $this->assertEquals(3, $config['settings']['index']['number_of_replicas']);
        $this->assertNotEquals($oldIndexName, $index->name());
        $this->assertCount(10, $index);
    }

    /**
     * @test
     */
    public function delete_old_index()
    {
        $this->sigmie->newIndex('foo')
            ->withoutMappings()
            ->create();

        $index = $this->sigmie->index('foo');

        $oldIndexName = $index->name();

        $index->update(function (Update $update) {
            return $update;
        });

        $this->assertIndexNotExists($oldIndexName);
        $this->assertNotEquals($oldIndexName, $index->name());
    }

    /**
     * @test
     */
    public function index_name()
    {
        $this->sigmie->newIndex('foo')
            ->withoutMappings()
            ->create();

        $index = $this->sigmie->index('foo');

        $oldIndexName = $index->name();

        $index->update(function (Update $update) {

            return $update;
        });

        $this->assertIndexExists($index->name());
        $this->assertIndexNotExists($oldIndexName);
        $this->assertNotEquals($oldIndexName, $index->name());
    }

    /**
     * @test
     */
    public function index_shards_and_replicas()
    {
        $this->sigmie->newIndex('foo')
            ->withoutMappings()
            ->shards(1)
            ->replicas(1)
            ->create();

        $index = $this->sigmie->index('foo');

        [$name, $config] = name_configs($index->toRaw());

        $this->assertEquals(1, $config['settings']['index']['number_of_shards']);
        $this->assertEquals(1, $config['settings']['index']['number_of_replicas']);

        $index->update(function (Update $update) {

            $update->replicas(2)->shards(2);

            return $update;
        });

        [$name, $config] = name_configs($index->toRaw());

        $this->assertEquals(2, $config['settings']['index']['number_of_shards']);
        $this->assertEquals(2, $config['settings']['index']['number_of_replicas']);
    }
}
