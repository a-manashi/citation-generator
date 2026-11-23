<?php

/**
 * Citation generator checks. Run from the project root:
 * php tests/citation/run.php
 */

declare(strict_types=1);

use App\Libraries\Citation\CitationHttp;
use App\Libraries\Citation\CitationRecord;
use App\Libraries\Citation\CitationService;
use App\Libraries\Citation\FormatterFactory;
use App\Libraries\Citation\InputDetector;
use CodeIgniter\Boot;
use Config\Paths;

$root = dirname(__DIR__, 2);
chdir($root);

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTPS'] = 'off';

defined('CI_DEBUG') || define('CI_DEBUG', true);
defined('ENVIRONMENT') || define('ENVIRONMENT', 'development');
defined('HOMEPATH') || define('HOMEPATH', $root . DIRECTORY_SEPARATOR);
defined('CONFIGPATH') || define('CONFIGPATH', $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR);
defined('PUBLICPATH') || define('PUBLICPATH', $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR);

require CONFIGPATH . 'Paths.php';
$paths = new Paths();

defined('APPPATH') || define('APPPATH', realpath(rtrim($paths->appDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
defined('ROOTPATH') || define('ROOTPATH', realpath(APPPATH . '../') . DIRECTORY_SEPARATOR);
defined('SYSTEMPATH') || define('SYSTEMPATH', realpath(rtrim($paths->systemDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
defined('WRITEPATH') || define('WRITEPATH', realpath(rtrim($paths->writableDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
defined('TESTPATH') || define('TESTPATH', $root . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR);
defined('FCPATH') || define('FCPATH', realpath(PUBLICPATH) . DIRECTORY_SEPARATOR);
defined('COMPOSER_PATH') || define('COMPOSER_PATH', ROOTPATH . 'vendor/autoload.php');
defined('VENDORPATH') || define('VENDORPATH', ROOTPATH . 'vendor' . DIRECTORY_SEPARATOR);

require SYSTEMPATH . 'Boot.php';
Boot::bootTest($paths);

$failed = 0;
$dash = "\u{2013}";

function check(bool $condition, string $message): void
{
    global $failed;
    if ($condition) {
        echo "OK  {$message}\n";

        return;
    }
    $failed++;
    echo "FAIL {$message}\n";
}

function checkSame(string $expected, string $actual, string $message): void
{
    if ($expected === $actual) {
        check(true, $message);

        return;
    }
    check(false, $message . "\n    expected: {$expected}\n    actual:   {$actual}");
}

$detector = new InputDetector();

$doi = $detector->detect('10.1234/abc.xyz');
check($doi['type'] === 'doi' && $doi['value'] === '10.1234/abc.xyz', 'bare DOI');

$doi = $detector->detect('https://doi.org/10.1234/abc.xyz');
check($doi['type'] === 'doi' && $doi['value'] === '10.1234/abc.xyz', 'doi.org URL');

$doi = $detector->detect('http://dx.doi.org/10.1234/abc.xyz');
check($doi['type'] === 'doi' && $doi['value'] === '10.1234/abc.xyz', 'dx.doi.org URL');

$doi = $detector->detect('doi:10.1234/abc.xyz');
check($doi['type'] === 'doi' && $doi['value'] === '10.1234/abc.xyz', 'doi: prefix');

$doi = $detector->detect('https://doi.org/10.1234/abc.xyz.');
check($doi['type'] === 'doi' && $doi['value'] === '10.1234/abc.xyz', 'DOI with trailing period');

$doi = $detector->detect('10.123/not-valid');
check($doi['type'] === 'invalid_doi', 'invalid DOI');

$isbn = $detector->detect('9780132350884');
check($isbn['type'] === 'isbn' && $isbn['value'] === '9780132350884', 'ISBN-13');

$isbn = $detector->detect('978-0-13-235088-4');
check($isbn['type'] === 'isbn' && $isbn['value'] === '9780132350884', 'hyphenated ISBN-13');

$isbn = $detector->detect('ISBN 9780132350884');
check($isbn['type'] === 'isbn' && $isbn['value'] === '9780132350884', 'ISBN prefix');

$isbn = $detector->detect('0132350882');
check($isbn['type'] === 'isbn' && $isbn['value'] === '0132350882', 'ISBN-10');

$isbn = $detector->detect('9780132350885');
check($isbn['type'] === 'invalid_isbn', 'invalid ISBN-13 checksum');

$isbn = $detector->detect('0132350883');
check($isbn['type'] === 'invalid_isbn', 'invalid ISBN-10 checksum');

$url = $detector->detect('https://example.com/article');
check($url['type'] === 'url', 'ordinary URL');

$url = $detector->detect('https://127.0.0.1/secret');
check($url['type'] === 'url', 'loopback URL is not fetched as a DOI');

$url = $detector->detect('http://169.254.169.254/latest/meta-data');
check($url['type'] === 'url', 'link-local URL is not fetched');

$search = $detector->detect('artificial intelligence in education');
check($search['type'] === 'search', 'title search');

$search = $detector->detect("' OR 1=1 --");
check($search['type'] === 'search' && $search['value'] === "' OR 1=1 --", 'SQL-looking input stays a search string');

$empty = $detector->detect('   ');
check($empty['type'] === 'empty', 'empty input');

$article = CitationRecord::fromArray([
    'title'   => 'Title of the research paper',
    'authors' => [
        ['given' => 'John', 'family' => 'Smith'],
        ['given' => 'Jane', 'family' => 'Brown'],
    ],
    'year'    => '2025',
    'journal' => 'Journal Name',
    'volume'  => '10',
    'issue'   => '2',
    'pages'   => '100-120',
    'doi'     => '10.1234/abc.xyz',
    'type'    => 'article',
]);

$apa = FormatterFactory::make('apa');
checkSame(
    'Smith, J., & Brown, J. (2025). Title of the research paper. Journal Name, 10(2), 100' . $dash . '120. https://doi.org/10.1234/abc.xyz',
    $apa->bibliography($article),
    'APA 7 two authors'
);
checkSame('(Smith & Brown, 2025)', $apa->inText($article), 'APA 7 in-text two authors');

$one = CitationRecord::fromArray([
    'title' => 'Title of the research paper', 'year' => '2025', 'journal' => 'Journal Name',
    'volume' => '10', 'issue' => '2', 'pages' => '100-120', 'doi' => '10.1234/abc.xyz', 'type' => 'article',
    'authors' => [['given' => 'John', 'family' => 'Smith']],
]);
checkSame('(Smith, 2025)', $apa->inText($one), 'APA 7 in-text one author');

$three = CitationRecord::fromArray([
    'title' => 'Title of the research paper', 'year' => '2025', 'journal' => 'Journal Name',
    'volume' => '10', 'issue' => '2', 'pages' => '100-120', 'doi' => '10.1234/abc.xyz', 'type' => 'article',
    'authors' => [
        ['given' => 'John', 'family' => 'Smith'],
        ['given' => 'Jane', 'family' => 'Brown'],
        ['given' => 'Alex', 'family' => 'Clark'],
    ],
]);
checkSame('(Smith et al., 2025)', $apa->inText($three), 'APA 7 in-text three authors');
check(str_contains($apa->bibliography($three), 'Smith, J., Brown, J., & Clark, A.'), 'APA 7 bibliography lists three authors');

$manyAuthors = [];
for ($i = 1; $i <= 21; $i++) {
    $manyAuthors[] = ['given' => 'Pat', 'family' => sprintf('N%02d', $i)];
}
$many = CitationRecord::fromArray([
    'title' => 'Title of the research paper', 'year' => '2025', 'journal' => 'Journal Name',
    'volume' => '10', 'issue' => '2', 'pages' => '100-120', 'doi' => '10.1234/abc.xyz', 'type' => 'article',
    'authors' => $manyAuthors,
]);
$manyApa = $apa->bibliography($many);
check(str_contains($manyApa, 'N01, P.') && str_contains($manyApa, 'N19, P.') && str_contains($manyApa, '...'), 'APA 7 ellipsis for 21 authors');
check(str_contains($manyApa, 'N21, P.') && ! str_contains($manyApa, 'N20, P.'), 'APA 7 keeps the final author and omits author 20');
checkSame('(N01 et al., 2025)', $apa->inText($many), 'APA 7 in-text many authors');

$missingAuthor = CitationRecord::fromArray([
    'title' => 'Title of the research paper', 'year' => '2025', 'journal' => 'Journal Name',
    'volume' => '10', 'issue' => '2', 'pages' => '100-120', 'doi' => '10.1234/abc.xyz', 'type' => 'article',
]);
check(! str_contains($apa->bibliography($missingAuthor), 'Anonymous'), 'missing author is not invented');
check(in_array('Author names could not be verified.', $apa->warnings($missingAuthor), true), 'missing author warning');

$missingYear = CitationRecord::fromArray([
    'title' => 'Title of the research paper', 'journal' => 'Journal Name', 'volume' => '10', 'issue' => '2',
    'pages' => '100-120', 'doi' => '10.1234/abc.xyz', 'type' => 'article',
    'authors' => [['given' => 'John', 'family' => 'Smith']],
]);
check(str_contains($apa->bibliography($missingYear), '(n.d.)'), 'APA uses n.d. when the year is missing');
check(in_array('Publication year could not be verified.', $apa->warnings($missingYear), true), 'missing year warning');

$missingPages = CitationRecord::fromArray([
    'title' => 'Title of the research paper', 'year' => '2025', 'journal' => 'Journal Name',
    'volume' => '10', 'issue' => '2', 'doi' => '10.1234/abc.xyz', 'type' => 'article',
    'authors' => [['given' => 'John', 'family' => 'Smith']],
]);
check(! str_contains($apa->bibliography($missingPages), '100'), 'missing pages are not invented');
check(in_array('Page numbers could not be verified.', $apa->warnings($missingPages), true), 'missing pages warning');

$missingVolume = CitationRecord::fromArray([
    'title' => 'Title of the research paper', 'year' => '2025', 'journal' => 'Journal Name',
    'issue' => '2', 'pages' => '100-120', 'doi' => '10.1234/abc.xyz', 'type' => 'article',
    'authors' => [['given' => 'John', 'family' => 'Smith']],
]);
check(in_array('Volume could not be verified.', $apa->warnings($missingVolume), true), 'missing volume warning');

$missingIssue = CitationRecord::fromArray([
    'title' => 'Title of the research paper', 'year' => '2025', 'journal' => 'Journal Name',
    'volume' => '10', 'pages' => '100-120', 'doi' => '10.1234/abc.xyz', 'type' => 'article',
    'authors' => [['given' => 'John', 'family' => 'Smith']],
]);
check(in_array('Issue could not be verified.', $apa->warnings($missingIssue), true), 'missing issue warning');
check(! str_contains($apa->bibliography($missingIssue), '(2)'), 'missing issue number is not invented');

$missingDoi = CitationRecord::fromArray([
    'title' => 'Title of the research paper', 'year' => '2025', 'journal' => 'Journal Name',
    'volume' => '10', 'issue' => '2', 'pages' => '100-120', 'type' => 'article',
    'authors' => [['given' => 'John', 'family' => 'Smith']],
]);
check(! str_contains($apa->bibliography($missingDoi), 'doi.org'), 'missing DOI is not invented');
check(in_array('DOI could not be verified.', $apa->warnings($missingDoi), true), 'missing DOI warning');

$mla = FormatterFactory::make('mla');
checkSame(
    'Smith, John, and Jane Brown. "Title of the research paper." Journal Name, vol. 10, no. 2, 2025, pp. 100' . $dash . '120. https://doi.org/10.1234/abc.xyz',
    $mla->bibliography($article),
    'MLA 9 two authors'
);
checkSame('(Smith and Brown)', $mla->inText($article), 'MLA 9 in-text two authors');
checkSame('(Smith et al.)', $mla->inText($three), 'MLA 9 in-text three authors');

$chicago = FormatterFactory::make('chicago');
checkSame(
    'Smith, John, and Jane Brown. "Title of the research paper." Journal Name 10, no. 2 (2025): 100' . $dash . '120. https://doi.org/10.1234/abc.xyz',
    $chicago->bibliography($article),
    'Chicago bibliography two authors'
);
checkSame(
    'John Smith and Jane Brown, "Title of the research paper," Journal Name 10, no. 2 (2025): 100' . $dash . '120.',
    $chicago->inText($article),
    'Chicago footnote two authors'
);
check(str_contains($chicago->inText($many), 'N01 et al.'), 'Chicago footnote uses et al. after three authors');
check($chicago->inTextLabel() === 'Footnote', 'Chicago label is Footnote');

$harvard = FormatterFactory::make('harvard');
checkSame(
    "Smith, J. and Brown, J. (2025) 'Title of the research paper', Journal Name, 10(2), pp. 100" . $dash . '120. doi:10.1234/abc.xyz',
    $harvard->bibliography($article),
    'Harvard two authors'
);
checkSame('(Smith and Brown, 2025)', $harvard->inText($article), 'Harvard in-text two authors');
checkSame('(Smith et al., 2025)', $harvard->inText($three), 'Harvard in-text three authors');

$ieee = FormatterFactory::make('ieee');
checkSame(
    '[1] J. Smith and J. Brown, "Title of the research paper," Journal Name, vol. 10, no. 2, pp. 100' . $dash . '120, 2025, doi: 10.1234/abc.xyz.',
    $ieee->bibliography($article),
    'IEEE two authors'
);
checkSame('[1]', $ieee->inText($article), 'IEEE in-text');

$vancouver = FormatterFactory::make('vancouver');
checkSame(
    'Smith J, Brown J. Title of the research paper. Journal Name. 2025;10(2):100' . $dash . '120. doi:10.1234/abc.xyz',
    $vancouver->bibliography($article),
    'Vancouver two authors'
);
checkSame('(1)', $vancouver->inText($article), 'Vancouver in-text');

$book = CitationRecord::fromArray([
    'title' => 'Book Title', 'year' => '2020', 'publisher' => 'Penguin', 'type' => 'book',
    'authors' => [['given' => 'John', 'family' => 'Smith']],
]);
checkSame('Smith, J. (2020). Book Title. Penguin.', $apa->bibliography($book), 'APA book');
check($apa->warnings($book) === [], 'complete book has no missing-field warnings');

$xss = CitationRecord::fromArray([
    'title'   => '<script>alert(1)</script>',
    'authors' => [['given' => '<b>John</b>', 'family' => 'Smith']],
    'year'    => '2025',
    'journal' => 'Journal Name',
    'type'    => 'article',
]);
check($xss->title === 'alert(1)', 'script tags are stripped from the title');
check(! str_contains($apa->bibliography($xss), '<script'), 'bibliography does not contain a script tag');
$encoded = json_encode(['title' => $xss->title], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
check(is_string($encoded) && ! str_contains($encoded, '<script'), 'JSON encoding does not emit a script tag');

$edited = CitationRecord::fromArray([
    'title' => 'Title of the research paper',
    'authors_text' => 'John Smith; Jane Brown',
    'year' => '2025',
    'journal' => 'Journal Name',
    'volume' => '10',
    'issue' => '2',
    'pages' => '100-120',
    'doi' => 'https://doi.org/10.1234/abc.xyz',
    'type' => 'article',
]);
checkSame('(Smith & Brown, 2025)', $apa->inText($edited), 'manual author text is parsed');
check($edited->doi === '10.1234/abc.xyz', 'manual DOI URL is normalized');

$http = new class (config(\Config\Citation::class)) extends CitationHttp {
    public function get(string $url, array $headers = []): array
    {
        throw new RuntimeException('HTTP called: ' . parse_url($url, PHP_URL_HOST));
    }
};
$blocked = new CitationService(null, $http);
try {
    $result = $blocked->lookup('https://127.0.0.1/secret', 'apa');
    check($result['ok'] === false && str_contains((string) $result['message'], 'general web address'), 'service rejects a private URL without requesting it');
} catch (Throwable $e) {
    check(false, 'private URL triggered a request: ' . $e->getMessage());
}
try {
    $blocked->lookup('10.1234/abc.xyz', 'apa');
    check(false, 'unknown DOI was formatted without metadata');
} catch (RuntimeException $e) {
    check(str_contains($e->getMessage(), 'HTTP called'), 'unknown DOI is looked up instead of invented');
}
$formatted = $blocked->format([
    'title' => 'Edited title',
    'authors_text' => 'John Smith',
    'year' => '2024',
    'journal' => 'Journal Name',
    'type' => 'article',
], 'mla');
check(($formatted['ok'] ?? false) === true && str_contains((string) $formatted['bibliography'], 'Edited title'), 'style change formats local metadata');
check(($formatted['record']['source'] ?? '') === 'manual', 'manual format does not claim a provider source');

echo "\nLive metadata checks\n";
$live = new CitationService();
try {
    $doiResult = $live->lookup('https://doi.org/10.1371/journal.pone.0000217', 'apa');
    $doiMessage = (string) ($doiResult['message'] ?? '');
    if (($doiResult['ok'] ?? false) !== true && (str_contains($doiMessage, 'not responding') || str_contains($doiMessage, 'is busy'))) {
        echo "SKIP live DOI: {$doiMessage}\n";
        $doiResult = null;
    } else {
        check(($doiResult['ok'] ?? false) === true && ($doiResult['mode'] ?? '') === 'citation', 'live DOI lookup');
        $record = $doiResult['record'] ?? [];
        check(($record['title'] ?? '') !== '', 'live DOI has a title');
        check(($record['year'] ?? '') !== '', 'live DOI has a year');
        check(($record['authors'] ?? []) !== [], 'live DOI has authors');
        check(str_contains((string) ($doiResult['bibliography'] ?? ''), (string) ($record['year'] ?? 'no-year')), 'live APA citation includes the year');
        $mlaLive = $live->format($record, 'mla');
        check(($mlaLive['ok'] ?? false) === true && ($mlaLive['bibliography'] ?? '') !== ($doiResult['bibliography'] ?? ''), 'changing style does not call the API again');
    }
} catch (Throwable $e) {
    echo "SKIP live DOI: {$e->getMessage()}\n";
}

try {
    $isbnResult = $live->lookup('9780132350884', 'chicago');
    $isbnMessage = (string) ($isbnResult['message'] ?? '');
    if (($isbnResult['ok'] ?? false) !== true && (str_contains($isbnMessage, 'not responding') || str_contains($isbnMessage, 'is busy'))) {
        echo "SKIP live ISBN: {$isbnMessage}\n";
    } else {
        check(($isbnResult['ok'] ?? false) === true && ($isbnResult['record']['title'] ?? '') !== '', 'live ISBN lookup');
        check(($isbnResult['record']['type'] ?? '') === 'book' || ($isbnResult['record']['publisher'] ?? '') !== '' || ($isbnResult['record']['authors'] ?? []) !== [], 'live ISBN has book metadata');
    }
} catch (Throwable $e) {
    echo "SKIP live ISBN: {$e->getMessage()}\n";
}

try {
    $found = $live->search('attention is all you need', 'apa');
    $foundMessage = (string) ($found['message'] ?? '');
    if (($found['ok'] ?? false) !== true && (str_contains($foundMessage, 'not responding') || str_contains($foundMessage, 'is busy'))) {
        echo "SKIP live search: {$foundMessage}\n";
    } else {
        $count = is_array($found['results'] ?? null) ? count($found['results']) : 0;
        check(($found['mode'] ?? '') === 'results' && $count > 1, 'live title search returns multiple matches');
        $none = $live->search('zzzzqqqxxxnotapaper123456789', 'apa');
        check(($none['ok'] ?? true) === false && str_contains((string) ($none['message'] ?? ''), 'No matching'), 'live nonsense search has no matches');
    }
} catch (Throwable $e) {
    echo "SKIP live search: {$e->getMessage()}\n";
}

echo $failed === 0 ? "\nAll checks passed.\n" : "\n{$failed} check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
