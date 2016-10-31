<?PHP
require 'vendor/autoload.php';

spl_autoload_register(function ($class) {
    $data = explode('\\', $class);
    if (in_array('PHRETS', (array)($data[0]))) {
        require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . $class . '.php';
    }
}, false);

interface PHRetsFormats
{
    const STANDARD_XML = 'STANDARD-XML';
    const STANDARD_XML_ENCODED = 'STANDARD-XML-ENCODED';
    const COMPACT_DECODED = 'COMPACT-DECODED';
    const COMPACT = 'COMPACT';
}
$format = PHRetsFormats::COMPACT_DECODED;

$username = 'CXLHfDVrziCfvwgCuL8nUahC';
$password = 'mFqMsCSPdnb5WO1gpEEtDCHH';
$loginUrl = 'http://sample.data.crea.ca/Login.svc/Login';

//1) Go to http://sample.data.crea.ca/Login.svc/Login
//2) Enter sample credentials (user = CXLHfDVrziCfvwgCuL8nUahC password=mFqMsCSPdnb5WO1gpEEtDCHH)
//3) once you see the successful login response, run your query:  http://sample.data.crea.ca/Search.svc/Search?SearchType=Property&Class=Property&Query=%28LastUpdated%3D2009-07-21%29&QueryType=DMQL2&Count=1&Format=STANDARD-XML&Limit=1&StandardNames=0

$console = new \Symfony\Component\Console\Output\ConsoleOutput();
$debugMode = true;
// @TODO Set this to something like "-2 years" to get all listings, then choose "-2 days" for on-going updates, etc.
$TimeBackPull = "-200 days";
date_default_timezone_set('America/New_York');

/* Do Not Edit Zone ------------------- */
$RETS_LimitPerQuery = 100;
/* END Do Not Edit Zone --------------- */

$config = \PHRETS\Configuration::load([
    'login_url' => $loginUrl,
    'username' => $username,
    'password' => $password,
    //'user_agent' => 'MyUserAgent/1.0', // @TODO Make this yours or remove it
    'rets_version' => '1.7.2'
]);

$rets = new \PHRETS\Session($config);

if ($debugMode) {
    $log = new \Monolog\Logger('PHRETS');
    $log->pushHandler(new \Monolog\Handler\StreamHandler('php://stdout', \Monolog\Logger::DEBUG));
    $rets->setLogger($log);
}

$connect = $rets->Login();

//$rets->SetParam('compression_enabled', true);
//$RETS_PhotoSize = "LargePhoto";

$console->writeln("-----GETTING ALL ID's-----");
$results = $rets->Search(
    'Property',
    'Property',
    '(ID=*)',
    [
        'Limit' => 10,
        'Format' => $format,
        'Count' => 1,
        'Culture' => 'en-CA',
    ]
);
//$results = $rets->Search('Property', 'Property', 'ID=17520245', ['Limit' => 1, 'Format' => 'STANDARD-XML', 'Offset' => 1]);
$table = new \Symfony\Component\Console\Helper\Table($console);
$item = $results->getIterator()->current();
$headers = [];
$properties = [];
foreach ($item->getFields() as $field) {
    if (!in_array($field, ['AnalyticsClick', 'AnalyticsView'])) {
        $headers[] = $field;
    }
}
$table->setHeaders($headers);
/** @var \PHRETS\Models\Search\Record $item */
foreach ($results->getIterator() as $item) {
    foreach ($item->getFields() as $field) {
        $row = [];
        foreach ($headers as $k => $header) {
            if (0 === $k) {
                $properties[] = $item->get($header);
            }
            $row[] = $item->get($header);
        }
        $table->addRow($row);
    }
}

$results = $rets->Search(
    'Property',
    'Property',
    '(ID=' . implode(',', $properties) . ')',
    [
        'Limit' => 10,
        'Format' => $format,
        'Count' => 1,
        'Culture' => 'en-CA',
    ]
);
$table->render();
/** @var \PHRETS\Models\Search\Record $item */
foreach ($results->getIterator() as $item) {
    $table = new \Symfony\Component\Console\Helper\Table($console);
    foreach ($item->getFields() as $field) {
        if (!in_array($field, ['AnalyticsClick', 'AnalyticsView', 'PublicRemarks'])) {
            $table->addRow([$field, $item->get($field)]);
        }
    }
    $table->render();
}

$rets->Disconnect();