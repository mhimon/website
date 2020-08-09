<?php

namespace app\components\search\engines;

use app\components\search\SearchEngine;
use app\components\search\SearchResultset;
use Yii;

class KeywordSearchEngine extends SearchEngine
{
    protected $solr;

    protected $id = 'Solr';
    protected $lang = 'en';
    protected $fieldName = 'hadithText';

    public function __construct()
    {
        $this->solr = Yii::$app->solr;
    }

    protected function getStartOffset()
    {
        return ($this->page-1) * $this->limit;
    }

    protected function doSearchInternal()
    {
        $engine = new EnglishKeywordSearchEngine();
        $resultset = $this->doLangEngineQuery($engine);

        if ($resultset === null) {
            return null;
        }
        $enSuggestions = $resultset->getSuggestions();

        if ($resultset->getCount() === 0) {
            // If no English results were found, do Arabic search
            $engine = new ArabicKeywordSearchEngine();
            $resultset = $this->doLangEngineQuery($engine);
        }

        // Only English engine supports suggestions
        $resultset->setSuggestions($enSuggestions);
        return $resultset;
    }

    private function doLangEngineQuery($engine) {
        $resultscode = $engine->doQuery();
        if ($resultscode === null) {
            return null;
        }

        // FIXME: Avoid use of eval
        eval("\$resultsarray = ".$resultscode.";");

        $response = $resultsarray['response'];
        $docs = $resultsarray['response']['docs'];
        $highlightings = $resultsarray['highlighting'];

        $resultset = new SearchResultset($response['numFound']);

        if ($engine->hasSuggestionsSupport()) {
            $suggestions = $resultsarray['spellcheck']['suggestions'];
            if (isset($suggestions['collation'])) {
                $spellcheck = substr(strstr($suggestions['collation'], ':'), 1);
                $resultset->setSuggestions($spellcheck);
            }
        }

        foreach ($docs as $doc) {
            $urn = $doc['URN'];
            $highlightedText = null;
            if (isset($highlightings[$urn][$engine->fieldName])) {
                $highlightedText = $highlightings[$urn][$engine->fieldName][0];
            }
            $resultset->addResult($engine->lang, intval($urn), $highlightedText);
        }

        return $resultset;
    }

    protected function doQuery() {
        return false;
    }

    protected function hasSuggestionsSupport()
    {
        // whether "did you mean" spellcheck suggestions are supported
        return false;
    }

    protected static function replace_special_chars($str)
    {
        return preg_replace('/([\!\(\)\{\}\[\]\^\'\~\*\?\:\\\\])/', '\\\\${1}', $str);
    }
}