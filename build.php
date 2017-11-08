#!/usr/bin/env php
<?php
/**
 * @license See the file LICENSE for copying permission
 */

declare(strict_types=1);

namespace Fig\Website;

if (version_compare(PHP_VERSION, '7.1.0', '<')) {
    fwrite(STDERR, "PHP minimum version required is 7.1.0, current is ".PHP_VERSION.PHP_EOL);
    exit(1);
}

require 'vendor/autoload.php';

(function(array $argv) {

    [ $overwrite, $createBylaws, $createPsr ] = parseArgs($argv);

    $skippedSomeBylaws = $skippedSomePsr = false;

    if ($createBylaws) {
        echo 'Creating bylaws...', PHP_EOL;
        [ $bylaws, $skippedSomeBylaws ] = createPages(Page::BYLAWS_GLOB, $overwrite);

        $numBylaws = count($bylaws);
        echo "Created {$numBylaws} bylaws", PHP_EOL, PHP_EOL;
    }

    if ($createPsr) {
        echo 'Creating PSRs...', PHP_EOL;
        [ $psr, $skippedSomePsr ] = createPages(Page::PSR_GLOB, $overwrite);

        $numPsr = count($psr);
        echo "Created {$numPsr} PSRs", PHP_EOL, PHP_EOL;
    }

    if ($skippedSomeBylaws || $skippedSomePsr) {
        echo 'use -f flag to overwrite skipped files', PHP_EOL;
    }

})($argv);

function parseArgs(array $argv): array
{
    $overwrite    = false;
    $createBylaws = false;
    $createPsr    = false;

    foreach ($argv as $arg) {
        switch ($arg) {
            case '-f': $overwrite = true; break;
            case 'psr': $createPsr = true; break;
            case 'bylaws': $createBylaws = true; break;
        }
    }

    if (! ($createBylaws || $createPsr)) {
        $createPsr = $createBylaws = true;
    }

    return [ $overwrite, $createBylaws, $createPsr ];
}

function createPages(string $globPattern, bool $overwrite): array
{
    $sources = glob($globPattern);

    $pages = array_map(function(string $source) {
        return new Page($source);
    }, $sources);

    setRelations($pages);

    $writtenPages = array_map(function(Page $page) use ($overwrite) {
        echo "Writing '$page'... ";

        if (! $overwrite && file_exists($page->getDest())) {
            echo 'Skipping, file already exists.', PHP_EOL;
            return;
        }

        $page->writeToDest();
        echo 'OK.', PHP_EOL;
    }, $pages);

    $skippedSome = count($writtenPages) < count($pages);

    return [ $writtenPages, $skippedSome ];
}

/**
 * @param Page[] $pages
 */
function setRelations(array $pages) {
    $groupedPages = array_reduce($pages, function(array $pages, Page $page) : array {
        switch ($page->getType()) {
            case Page::TYPE_PSR:
                $pages['psr'][$page->getMeta()['psr_number']] = $page;
                break;
            case Page::TYPE_PSR_RELATED:
                $pages['related'][$page->getMeta()['psr_number']] = $page;
                break;
        }

        return $pages;
    }, [ 'psr' => [], 'related' => [] ]);


    foreach ($groupedPages['related'] as $related)  {
        $groupedPages['psr'][$related->getMeta()['psr_number']]->addRelated($related);
        $related->addRelated($groupedPages['psr'][$related->getMeta()['psr_number']]);
    }
}